<?php
/*
  Template Name: My Account


 */

get_header();

global $current_user;

$current_user = wp_get_current_user();

$all_caps = $current_user->allcaps;
$user_roles = $current_user->roles;

$create_posts__cap_allowed = in_array('create_posts', $all_caps );
$index_contributor__role_allowed = in_array('index_contributor', $user_roles );
$administrator__role_allowed = in_array('administrator', $current_user->roles);


?>

    <section id="primary" class="content-area">
        <main id="main" class="site-main">

            <div class="container bootstrap snippet">
                <div class="row">
                    <div class="col-sm-3 border "><!--left col--> 
                        <div class=" d-flex justify-content-center">


                            <div class="d-flex flex-column ">
                                <div><?= get_cuf_val($avatar_image_fid) ?></div>
                                <div class="panel-heading mt-3">
                                    <p><?= get_cuf_val($first_name_fid) ?> <?= get_cuf_val($last_name_fid) ?> </p>
                                    <p class="font-weight-bold">User Roles:</p>
                                    <p><?= show_user_roles($current_user) ?></p>
                                </div>
                                <div><a class="btn btn-secondary btn-sm " href="<?= wp_logout_url() ?>">Logout</a></div>



                            </div>
                        </div>

                    </div>
                    <p><!--/col-3--></p>
                    <div class="col-sm-9">
                        <ul class="nav nav-tabs dashboard-tabs">
                            <li style="list-style-type: none;">
                                <ul class="nav nav-tabs">

                                    <li class="nav-item active"><a class="myAccount nav-link active" href="#myAccount"
                                                                   data-toggle="tab">Home</a></li>

                                    <?php



                                    if ($create_posts__cap_allowed) { ?>
                                    <li class="nav-item"><a class="myPosts nav-link" href="#myPosts"
                                                            data-toggle="tab">Posts</a></li>
                                    <?php }  ?>


                                    <li class="nav-item"><a class="myMedia nav-link" href="#myMedia" data-toggle="tab">Media</a>
                                    </li>

                                    <li class="nav-item"><a class="myDefaults nav-link" href="#myDefaults" data-toggle="tab">Defaults</a>
                                    </li>

                                    <?php

                                    if ($index_contributor__role_allowed) { ?>
                                        <li class="nav-item"><a class="myIndexing nav-link" href="#myIndexing" data-toggle="tab">Indexing</a>
                                        </li>
                                    <?php } ?>
                                    <?php if ($administrator__role_allowed) { ?>
                                        <li class="nav-item"><a class="myAdmin nav-link" href="#myAdmin" data-toggle="tab">Admin</a>
                                        </li>
                                        <li class="nav-item hidden"><a class="myAdmin2 nav-link" href="#myAdmin2" data-toggle="tab">Admin 2 </a>
                                        </li>


                                    <?php } ?>


                                </ul>
                            </li>
                        </ul>
                        <div id="main-tabs" class="tab-content">
                            <div id="myAccount" class="tab-pane container active">
                                <p><?= show_form($my_account_form_id) ?></p>
                            </div>
                            <!--/tab-pane-->
                            <div id="myPosts" class="tab-pane container fade">
                                <?php if ($create_posts__cap_allowed) { ?>


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

                                                    <?= show_view($posts_view_id) ?>
                                                </div>
                                                <div id="pendingReview"
                                                     class="tab-pane fade pt-3"
                                                     role="tabpanel">
                                                    <?= show_view($posts_pending_view_id) ?>
                                                </div>

                                            </div>
                                        </div>
                                    </div>


                                <?php } else { ?>
                                    <p>You do not have permission to upload content.</p>
                                <?php } ?>
                            </div>
                            <!--/tab-pane-->
                            <div id="myMedia" class="tab-pane container fade">
                                <div class="row ">
                                    <div class="col p-5">
                                        <p><?= do_shortcode('[frontend-button]') ?></p>
                                    </div>
                                </div>
                            </div>
                            <!--/tab-pane-->
                            <div id="myDefaults" class="tab-pane container fade">
                                <?php if ($create_posts__cap_allowed) { ?>
                                    <div class="row ">
                                        <div class="col p-3">
                                            <p><?= show_form(EDIT_USER_DEFAULTS_FORMID) ?></p>
                                        </div>
                                    </div>
                                <?php } else {  ?>
                                    <p>You do not have permission to upload content.</p>
                                <?php } ?>
                            </div>
                            <!--/tab-pane-->
                            <div id="myIndexing" class="tab-pane container fade">

                                <?php if ($index_contributor__role_allowed) { ?>
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

                                                    <?= show_view($councils_stf_view_id, '0') ?>
                                                </div>
                                                <div id="camps"
                                                     class="tab-pane fade pt-3"
                                                     role="tabpanel">
                                                    <?= show_view($all_camps_view_id) ?>
                                                </div>
                                                <div id="lodges"
                                                     class="tab-pane fade pt-3"
                                                     role="tabpanel">
                                                    <?= show_view($all_lodges_view_id) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>


                                <?php } else { ?>
                                    <p>You do not have permission to upload content.</p>
                                <?php } ?>

                            </div>
                            <!--/tab-pane-->
                            <?php if ($administrator__role_allowed) { ?>
                                <div id="myAdmin" class="tab-pane container fade pt-5">
                                    <?=show_view(EDIT_USERS_VIEWID)?>
                                </div>
                                <div id="myAdmin2" class="tab-pane container fade pt-5">
                                    <?=show_form(EDIT_USERS_FORMID)?>
                                </div>
                            <?php } ?>
                        </div>
                        <!--/tab-pane-->
                    </div>
                    <!--/tab-content-->
                </div>
                <!--/col-9-->
            </div>
            <!--/row-->





            <?php if ($administrator__role_allowed) { ?>
                <style>
                    .show_only_for_admin {
                        display: none !important;
                    }
                </style>
            <?php } ?>
        </main><!-- #main -->
    </section><!-- #primary -->
    <script type="text/javascript">

        (function($) {
            // $("#field_council_active_0").val(this.checked);
            // let currentYear = new Date().getFullYear();
            // $('#field_council_active_0').on( "change", function() {
            //     if(this.checked) {
            //         $('#field_council_end').val(currentYear);
            //         $('#field_council_active_1').val('Yes');
            //     } else {
            //         $('#field_council_active_1').val('No');
            //     }
            // });
            //
            // if ( $('#field_council_active_1').val() == 'Yes') {
            //     $('#field_council_active_0').prop("checked", true);
            // }

            let tab = '<?=get_request_parameter('tab') ?>';

            //console.log(tab);
            switch(tab) {
                case 'myDefaults':
                    $('a.myDefaults').tab('show');
                    break;
                case 'myAdmin':
                    $('a.myAdmin').tab('show');
                    break;
                case 'myAdmin2':
                    $('a.myAdmin2').tab('show');
                    break;
                default:
                // code block
            }


            $("#myAdmin a").each( function(){
                let href = this.href += '&tab=myAdmin2';
                $(this).attr('href', href );
            });

            $(".dashboard-tabs a").on("click", function(){
                $(".admin-tab").removeClass("active");
            });

        })( jQuery );
    </script>

<?php
get_footer();
