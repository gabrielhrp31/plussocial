<template>
    <div class="home">
        <Base>
            <v-container fluid>
                <publish></publish>
                <v-layout class="ma-8">
                    <v-flex xs12>
                        <ContentCard
                            v-for="(post, i) in posts"
                            v-bind:key="i"
                            :post="post"
                            :user="post.user"
                        ></ContentCard>
                    </v-flex>
                </v-layout>
                <v-layout class="ma-8" >
                    <v-flex>
                        <v-btn v-if="getMoreUrl" outlined block color="primary" @click="getMore()" :loading="loadingPosts"><v-icon>mdi-plus</v-icon>Ver mais posts...</v-btn>
                        <v-btn v-else text block color="accent" ><v-icon>mdi-emoticon-sad-outline</v-icon>Você viu todos os posts!</v-btn>
                    </v-flex>
                </v-layout>
            </v-container>
        </Base>
    </div>
</template>

<script>
import ContentCard from "../../components/ContentCard";
import Publish from "../../components/Publish";
import Base from "../../Layouts/Base";

export default {
    name: "Home",
    components: {
        Base,
        ContentCard,
        Publish
    },
    data: () => ({
        getMoreUrl: '',
        loadingPosts: false,
    }),
    computed:{
        posts(){
            return this.$store.state.posts.timelinePosts;
        }
    },
    methods:{
        getMore(){
            if(this.getMoreUrl){
                this.$http
                    .get(
                        this.getMoreUrl,
                        {
                            headers: {
                                authorization:
                                    "Bearer " + this.$store.state.auth.user.token
                            }
                        }
                    )
                    .then(response => {
                        if(response.data.status){
                            this.$store.commit('addTimelinePosts',response.data.posts.data);
                            this.getMoreUrl = response.data.posts.next_page_url;
                        }
                        this.loadingPosts =false;
                    })
                    .catch(error=>{

                    });
            }
        },
        scroll(){
            window.onscroll = () => {
                let bottomOfWindow = document.documentElement.scrollTop + window.innerHeight === document.documentElement.offsetHeight;
                if (bottomOfWindow && !this.loadingPosts) {
                    this.loadingPosts =true;
                    this.getMore();
                }
            };
        }
    },
    created() {
        this.$store.commit("authenticated", "/");
        this.$http
            .get(
                this.$apiUrl + "posts",
                {
                    headers: {
                        authorization:
                            "Bearer " + this.$store.state.auth.user.token
                    }
                }
            )
            .then(response => {
                if(response.data.status){
                    this.$store.commit('setTimelinePosts',response.data.posts.data);
                    this.getMoreUrl = response.data.posts.next_page_url;
                    this.loadingPosts = false;
                }
            })
            .catch(error=>{

        });
    },
    mounted() {
        this.scroll();
    }
};
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped></style>
