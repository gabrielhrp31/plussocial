<?php

namespace App\Http\Controllers\API;

use App\Post;
use App\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    
    protected function checkFollowing($loggedUser, $users){
        foreach ($users as $user) {
            $following = $users = DB::table('follows')->where([
                ['user_id', '=', $loggedUser->id],
                ['follow_id','=',$user->id]
            ])->count();
            if($following>0){
                $user->following = true;
            }else{
                $user->following = false;
            }
        }
    }
    
    
    public function profile(Request $request, $id=null){
        if(isset($id)){
            $user = User::with('followed','followers')->find($id);
            $user->followed_count=DB::table('follows')->where('user_id', '=', $id)->count();
            $user->followers_count=DB::table('follows')->where('follow_id', '=', $id)->count();

        }else{
            $user = $request->user();
        }

        $this->checkFollowing($request->user() , $user->followers);
        $this->checkFollowing($request->user() , $user->followed);

        $following = $users = DB::table('follows')->where([
                ['user_id', '=', $request->user()->id],
                ['follow_id','=',$user->id]
            ])->count();
        if($following>0){
            $user->following = true;
        }else{
            $user->following = false;
        }

        if($user){

            $posts = Post::withCount('likes', 'comments')->with('user', 'likes',  'comments', 'comments.user')->where('user_id','=', $id)->orderBy('created_at', 'desc')->paginate(5);

            foreach ($posts as $post){
                $liked  = $user->likes()->find($post->id);
                if($liked){
                    $post->liked = true;
                }else{
                    $post->liked = false;
                }
            }

            return ['status'=>true, 'posts'=>$posts, 'user'=>$user];
        }else{
            return ['status'=>false,'message'=>'Esse usuário não existe'];
        }

    }

    public function update(Request $request){
        $user = $request->user();
        $data = $request->all();

        if(isset($data['password'])){
            $validation = Validator::make($data, [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255',Rule::unique('users')->ignore($user->id)],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ]);

            if($validation->fails()){
                return $validation->errors();
            }

            $user->name = $data['name'];
            $user->email = $data['email'];
            $user->password = bcrypt($data['password']);

        }else{
            $validation = Validator::make($data, [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255',Rule::unique('users')->ignore($user->id)],
            ]);

            $user->name = $data['name'];
            $user->email = $data['email'];
        }



        if(isset($data['image'])){
            $time = time();
            $root = 'profile';
            $imageFolder = $root.DIRECTORY_SEPARATOR.'user'.$user->id;
            $ext = substr($data['image'], 11, strpos($data['image'],';')-11);
            $imageURL = $imageFolder.DIRECTORY_SEPARATOR.$time.'.'.$ext;

            $file = str_replace('data:image/'.$ext.';base64,','',$data['image']);
            $file = base64_decode($file);

            $amazonUrl = str_replace(DIRECTORY_SEPARATOR, '/',
                str_replace('https://' . env('AWS_BUCKET') . '.s3.amazonaws.com/','',$user->image));


                $result = Storage::disk('s3')->exists($amazonUrl);

            if($result){
                Storage::disk('s3')->delete($amazonUrl);
            }

            if(!$result){
                Storage::disk('s3')->put($imageURL, $file, 'public');
            }

            $user->image = $imageURL;

        }


        $user->save();

        $user->token = $user->createToken($user->email)->accessToken;

        return $user;
    }


    //usada para seguir direto do componente UsersList
    public function followOnly(Request $request, $id, $idUserPage=null){
        $user = $request->user();
        $user->followed()->toggle($id);
        $userFollowed = User::find($id);




        if(isset($idUserPage)){

            $userPage =  User::withCount('followed', 'followers')->with('followed','followers')->find($idUserPage);

            $this->checkFollowing($request->user() , $userPage->followers);
            $this->checkFollowing($request->user() , $userPage->followed);



            $following = DB::table('follows')->where([
                ['user_id', '=', $request->user()->id],
                ['follow_id','=',$userPage->id]
            ])->count();

            if($following>0){
                $userPage->following = true;
            }else{
                $userPage->following = false;
            }

            return ['status'=>true, 'user'=>$userPage];
        }else{

            $following = DB::table('follows')->where([
                ['user_id', '=', $request->user()->id],
                ['follow_id','=',$userFollowed->id]
            ])->count();

            if($following>0){
                $userFollowed->following = true;
            }else{
                $userFollowed->following = false;
            }

            return ['status'=>true, 'user'=>$userFollowed];
        }



    }

    public function follow(Request $request, $id)
    {
        $user = $request->user();
        $user->followed()->toggle($id);

        $userFollowed = User::withCount('followed', 'followers')->with('followed','followers')->find($id);



        $this->checkFollowing($request->user() , $userFollowed->followers);
        $this->checkFollowing($request->user() , $userFollowed->followed);

        $following = DB::table('follows')->where([
            ['user_id', '=', $request->user()->id],
            ['follow_id','=',$userFollowed->id]
        ])->count();

        if($following>0){
            $userFollowed->following = true;
        }else{
            $userFollowed->following = false;
        }

        return ['status'=>true, 'user'=>$userFollowed];

    }

    public function find(Request $request){

        $data =  $request->all();

        $users = User::where('name','LIKE','%'.$data['search'].'%')->orWhere('email','LIKE','%'.$data['search'].'%')->get();

        foreach ($users as $user){
            $following = DB::table('follows')->where([
                ['user_id', '=', $request->user()->id],
                ['follow_id','=',$user->id]
            ])->count();

            if($following>0){
                $user->following = true;
            }else{
                $user->following = false;
            }
        }

        return ['status'=>true, 'users'=>$users];
    }

}
