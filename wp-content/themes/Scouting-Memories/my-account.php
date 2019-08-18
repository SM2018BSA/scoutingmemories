<?php
/*
  Template Name: My Account


 */

get_header();
/*   get current user field value
 *   return String:     current user field value for the field id given
 *
 * */
function get_cuf_val( $field_id ) {
    return FrmProEntriesController::get_field_value_shortcode(array('field_id' => $field_id, 'user_id' => 'current')) ;
}

/*   $title Bool:       show or hide title of form
 *   $description Bool:      show or hide form description
 *
 *   return String:     html of form
 * */
function show_form($form_id=NULL, $title=false, $description=false) {
    return FrmFormsController::get_form_shortcode(array('id' => $form_id, 'title' => $title, 'description' => $description));
}
/*
 *   $view_id Int:      id of the view to show
 *   $filter String:    value for filter
 *
 *   return String:     html of view
 * */
function show_view($view_id ) {
    return FrmProDisplaysController::get_shortcode(array('id' => $view_id ));
}


$avatar_image_fid      = 183;
$first_name_fid        = 168;
$last_name_fid         = 184;

$my_account_form_id    = 22;
$user_defaults_form_id = 30;

$posts_view_id         = 1186;
$all_councils_view_id  = 1172;
$all_camps_view_id     = 1179;
$all_lodges_view_id    = 1180;
$councils_stf_view_id  = 1319;   // councils filtered by state

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
                        <div class="tab-content">
                            <div id="myAccount" class="tab-pane container active">
                                <p><?=show_form($my_account_form_id)?></p>
                            </div>
                            <p><!--/tab-pane--></p>
                            <div id="myPosts" class="tab-pane container fade">
                                <?php if( in_array('create_posts', (array) $current_user->allcaps)) : ?>
                                    <div class="row">
                                        <div class="col p-5">
                                            <div class="pagination ">
                                                <p><?=show_view($posts_view_id )?> </p>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <p>You do not have permission to upload content.</p>
                                <?php endif ?>
                            </div>
                            <p><!--/tab-pane--></p>
                            <div id="myMedia" class="tab-pane container fade">
                                <div class="row ">
                                    <div class="col p-5">
                                        <p><?=do_shortcode('[frontend-button]')?></p>
                                    </div>
                                </div>
                            </div>
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
                            <div id="myIndexing" class="tab-pane container fade">

                                <?php if(in_array('index_contributor', (array) $current_user->roles)) : ?>
                                <div class="row ">
                                    <div class="col p-3">
                                        <nav>
                                            <div id="nav-tab" class="nav nav-pills" role="tablist">

                                                <a id="councils-tab"
                                                   class="nav-item nav-link active"
                                                   role="tab" href="#councils"
                                                   data-toggle="tab"
                                                   aria-controls="councils"
                                                   aria-selected="true">Councils</a>

                                                <a
                                                    id="camps-tab"
                                                    class="nav-item nav-link"
                                                    role="tab"
                                                    href="#camps"
                                                    data-toggle="tab"
                                                    aria-controls="councils"
                                                    aria-selected="true">Camps</a>

                                                <a
                                                    id="lodges-tab"
                                                    class="nav-item nav-link"
                                                    role="tab"
                                                    href="#lodges"
                                                    data-toggle="tab"
                                                    aria-controls="councils"
                                                    aria-selected="true">Lodges</a>

                                            </div>
                                        </nav>
                                        <div id="nav-tabContent" class="tab-content">
                                            <div id="councils"
                                                 class="tab-pane fade pt-3 show active"
                                                 role="tabpanel"
                                                 aria-labelledby="councils-tab">

                                                <?=show_view($councils_stf_view_id, '0')?>
                                            </div>
                                            <div id="camps"
                                                 class="tab-pane fade pt-3"
                                                 role="tabpanel"
                                                 aria-labelledby="nav-camps-tab">
                                                <?=show_view($all_camps_view_id)?>
                                            </div>
                                            <div id="lodges"
                                                 class="tab-pane fade pt-3"
                                                 role="tabpanel"
                                                 aria-labelledby="nav-lodges-tab">
                                                <?=show_view($all_lodges_view_id)?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <?php else: ?>
                                    <p>You do not have permission to upload content.</p>
                                <?php endif ?>

                            </div>
                        </div>
                        <p><!--/tab-pane--></p>
                    </div>
                    <p><!--/tab-content--></p>
                </div>
                <p><!--/col-9--></p>
            </div>
            <p><!--/row--></p>



            <?php if( !is_admin()) : ?>
            <style>
                .show_only_for_admin { display: none !important; }
            </style>
            <?php endif ?>
        </main><!-- #main -->
    </section><!-- #primary -->

<?php
get_footer();
