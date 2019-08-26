<?php
/*
  Template Name: My Account


 */

get_header();




$current_user = wp_get_current_user();



?>

    <section id="primary" class="content-area">
        <main id="main" class="site-main">

            <div class="container bootstrap snippet">
                <div class="row">
                    <div class="col-sm-3 border"><!--left col--> 

                        <div class="text-center">
                            <?php

                            echo get_cuf_val($avatar_image_fid);
                            ?>
                        </div>
                        <div class="panel panel-default">
                            <div class="panel-heading text-center">
                                <?php
                                echo get_cuf_val($first_name_fid);
                                echo get_cuf_val($last_name_fid);
                                ?>

                            </div>
                            <div class="panel-body text-center">
                                <a class="frm_logout_link "
                                   href="<?=wp_logout_url()?>">Logout</a>
                            </div>
                        </div>
                    </div>
                    <p><!--/col-3--></p>
                    <div class="col-sm-9">
                        <ul class="nav nav-tabs">
                            <li style="list-style-type: none;">
                                <ul class="nav nav-tabs">

                                    <li class="nav-item active"><a class="nav-link active" href="#myAccount" data-toggle="tab">Home</a></li>

                                    <?php if( in_array('create_posts', (array) $current_user->allcaps ) ) : ?>
                                        <li class="nav-item"><a class="nav-link" href="#myPosts" data-toggle="tab">Posts</a></li>
                                    <? endif ?>

                                    <li class="nav-item"><a class="nav-link" href="#myMedia" data-toggle="tab">Media</a></li>

                                    <li class="nav-item"><a class="nav-link" href="#myDefaults" data-toggle="tab">Defaults</a></li>

                                    <?php if( in_array( 'index_contributor', (array) $current_user->roles ) ) : ?>
                                        <li class="nav-item"><a class="nav-link" href="#myIndexing" data-toggle="tab">Indexing</a></li>
                                    <?php endif ?>

                                </ul>
                            </li>
                        </ul>
                        <div id="main-tabs" class="tab-content">
                            <div id="myAccount"  class="tab-pane container active">
                                <p><?=show_form($my_account_form_id)?></p>
                            </div>
                            <!--/tab-pane-->
                            <div id="myPosts"    class="tab-pane container fade">
                                <?php if( in_array('create_posts', (array) $current_user->allcaps)) : ?>


                                    <div class="row ">
                                        <div class="col p-3">
                                            <nav>
                                                <div id="posts-nav-tabs" class="nav nav-pills" role="tablist">

                                                    <a id="councils-tab"
                                                       class="nav-item nav-link active"
                                                       role="tab"
                                                       href="#posts"
                                                       data-toggle="tab">My Posts</a>

                                                    <a id="camps-tab"
                                                       class="nav-item nav-link"
                                                       role="tab"
                                                       href="#pendingReview"
                                                       data-toggle="tab">Pending Review</a>

                                                </div>
                                            </nav>
                                            <div id="posts-tabs" class="tab-content">
                                                <div id="posts"
                                                     class="tab-pane fade pt-3 show active"
                                                     role="tabpanel">

                                                    <?=show_view($posts_view_id)?>
                                                </div>
                                                <div id="pendingReview"
                                                     class="tab-pane fade pt-3"
                                                     role="tabpanel">
                                                    <?=show_view($posts_pending_view_id)?>
                                                </div>

                                            </div>
                                        </div>
                                    </div>



                                <?php else: ?>
                                    <p>You do not have permission to upload content.</p>
                                <?php endif ?>
                            </div>
                            <!--/tab-pane-->
                            <div id="myMedia"    class="tab-pane container fade">
                                <div class="row ">
                                    <div class="col p-5">
                                        <p><?=do_shortcode('[frontend-button]')?></p>
                                    </div>
                                </div>
                            </div>
                            <!--/tab-pane-->
                            <div id="myDefaults" class="tab-pane container fade">
                                <?php if(in_array('create_posts', (array) $current_user->allcaps)) : ?>
                                    <div class="row ">
                                        <div class="col p-3">
                                            <p><?=show_form($user_defaults_form_id)?></p>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <p>You do not have permission to upload content.</p>
                                <?php endif ?>
                            </div>
                            <!--/tab-pane-->
                            <div id="myIndexing" class="tab-pane container fade">

                                <?php if(in_array('index_contributor', (array) $current_user->roles)) : ?>
                                <div class="row ">
                                    <div class="col p-3">
                                        <nav>
                                            <div id="indexing-nav-tabs" class="nav nav-pills" role="tablist">

                                                <a id="councils-tab"
                                                   class="nav-item nav-link active"
                                                   role="tab"
                                                   href="#councils"
                                                   data-toggle="tab">Councils</a>

                                                <a id="camps-tab"
                                                   class="nav-item nav-link"
                                                   role="tab"
                                                   href="#camps"
                                                   data-toggle="tab">Camps</a>

                                                <a id="lodges-tab"
                                                   class="nav-item nav-link"
                                                   role="tab"
                                                   href="#lodges"
                                                   data-toggle="tab">Lodges</a>
                                            </div>
                                        </nav>
                                        <div id="indexing-tabs" class="tab-content">
                                            <div id="councils"
                                                 class="tab-pane fade pt-3 show active"
                                                 role="tabpanel">

                                                <?=show_view($councils_stf_view_id, '0')?>
                                            </div>
                                            <div id="camps"
                                                 class="tab-pane fade pt-3"
                                                 role="tabpanel">
                                                <?=show_view($all_camps_view_id)?>
                                            </div>
                                            <div id="lodges"
                                                 class="tab-pane fade pt-3"
                                                 role="tabpanel">
                                                <?=show_view($all_lodges_view_id)?>
                                            </div>
                                        </div>
                                    </div>
                                </div>




                                <?php else: ?>
                                    <p>You do not have permission to upload content.</p>
                                <?php endif ?>

                            </div>
                            <!--/tab-pane-->
                        </div>
                        <!--/tab-pane-->
                    </div>
                    <!--/tab-content-->
                </div>
                <!--/col-9-->
            </div>
            <!--/row-->



            <?php if( !is_admin()) : ?>
            <style>
                .show_only_for_admin { display: none !important; }
            </style>
            <?php endif ?>
        </main><!-- #main -->
    </section><!-- #primary -->

<?php
get_footer();
