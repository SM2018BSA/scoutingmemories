<?php
/*
  Template Name: My Account Page

 */



get_header();

$helper = new Helpers();

$myAccountForm = new Form(MY_ACCOUNT_FORMID);
$editUsersForm = new Form(EDIT_USERS_FORMID);
$editUserDefaultsForm = new Form(EDIT_USER_DEFAULTS_FORMID);
$councilsForm = new Form(COUNCILS_FORMID);


$postsView = new View(POSTS_VIEWID);
$postsPendingView = new View(POSTS_PENDING_VIEWID);
$editUsersView = new View(EDIT_USERS_VIEWID);


$the_user = new CurrentUser();
$councilsView = new CouncilView(ALL_COUNCILS_VIEWID);
$lodgesView = new LodgeView(ALL_LODGES_VIEWID);
$campsView = new CampView(ALL_CAMPS_VIEWID);


//set_query_var('adm_posts_paged','3');
//set_query_var('smp_action','yoo');


$adm_posts_paged = Helpers::get_request_parameter('adm_posts_paged', '1');
$smp_action      = Helpers::get_request_parameter('smp_action', 'none');


//var_dump( get_query_var('adm_posts_paged') );
//var_dump( get_query_var('smp_action') );




//var_dump ( 'adm_posts_paged: ' . $adm_posts_paged );
//var_dump ( 'smp_action: ' . $smp_action );





?>


    <section id="primary" class="content-area container-fluid ">
        <main id="main" class="site-main container">

            <div class="container-fluid mt-5 pt-5">
                <div class="row">
                    <div class="col col-sm-3 border "><!--left col-->
                        <div class=" d-flex justify-content-center">


                            <div class="d-flex flex-column ">
                                <div>

                                    <?= $the_user->show_avatar(); ?>


                                </div>
                                <div class="panel-heading mt-3">
                                    <p><?= $the_user->show_first_name(); ?> <?= $the_user->show_last_name(); ?></p>
                                    <p class="font-weight-bold">User Roles:</p>
                                    <p><?= $the_user->show_user_roles(); ?></p>

                                    <?php if ($the_user->role_allowed('administrator')) { ?>

                                        <p><a class="btn btn-danger hidden"
                                              href="<?= get_site_url() ?>/my-account/?runit=yes">Process Data</a></p>

                                    <?php } ?>
                                </div>


                                <div><a class="btn btn-secondary btn-sm " href="<?= wp_logout_url() ?>">Logout</a></div>


                            </div>
                        </div>

                    </div>
                    <!--/col-3-->
                    <div class="col col-sm-9">


                        <ul class="nav nav-tabs" id="dashboard-tabs">

                            <li class="nav-item active" role="presentation">
                                <button class="myAccount nav-link active"
                                        id="myAccount-tab"
                                        data-bs-target="#myAccount"
                                        data-bs-toggle="tab">Home
                                </button>
                            </li><?php


                            if ($the_user->cap_allowed('create_posts')) {
                                ?>
                                <li class="nav-item">
                                    <button class="myPosts nav-link"
                                            id="myPosts-tab"
                                            data-bs-target="#myPosts"
                                            data-bs-toggle="tab">Posts
                                    </button>
                                </li>
                            <?php } ?>


                            <li class="nav-item">
                                <button class="myMedia nav-link"
                                        id="myMedia-tab"
                                        data-bs-target="#myMedia"
                                        data-bs-toggle="tab">Media
                                </button>
                            </li>

                            <li class="nav-item">
                                <button class="myDefaults nav-link"
                                        id="myDefaults-tab"
                                        data-bs-target="#myDefaults"
                                        data-bs-toggle="tab">Defaults
                                </button>
                            </li>

                            <?php

                            if ($the_user->role_allowed('index_contributor')) { ?>
                                <li class="nav-item">
                                    <button class="myIndexing nav-link"
                                            id="myIndexing-tab"
                                            data-bs-target="#myIndexing"
                                            data-bs-toggle="tab">Indexing
                                    </button>
                                </li>
                            <?php } ?>
                            <?php if ($the_user->role_allowed('administrator')) { ?>
                                <li class="nav-item">
                                    <button class="myAdmin nav-link"
                                            id="myAdmin-tab"
                                            data-bs-target="#myAdmin"
                                            data-bs-toggle="tab">Admin
                                    </button>
                                </li>
                                <li class="nav-item ">
                                    <button class="myAdmin2 hidden nav-link"
                                            id="myAdmin2-tab"
                                            data-bs-target="#myAdmin2"
                                            data-bs-toggle="tab">Admin 2
                                    </button>
                                </li>


                            <?php } ?>


                        </ul>
                        <div id="main-tabs" class="tab-content">
                            <div id="myAccount" class="tab-pane active" role="tabpanel" aria-labelledby="myAccount-tab">
                                <div class="container">
                                    <?= $myAccountForm->show_form(); ?>
                                </div>
                            </div>
                            <!--/tab-pane-->
                            <div id="myPosts" class="tab-pane  fade" role="tabpanel" aria-labelledby="myPosts-tab">
                                <?php if ($the_user->cap_allowed('create_posts')) { ?>

                                    <div class="container">
                                        <div class="row ">
                                            <div class="col p-3">
                                                <nav>
                                                    <div id="posts-nav-tabs" class="nav nav-pills" role="tablist">

                                                        <a id="councils-tab"
                                                           class="nav-item nav-link active"
                                                           role="tab"
                                                           href="#posts"
                                                           data-bs-toggle="tab">My Posts</a>
                                                        <?php /*
                                                        <a id="camps-tab"
                                                           class="nav-item nav-link"
                                                           role="tab"
                                                           href="#pendingReview"
                                                           data-bs-toggle="tab">Pending Review</a>
                                                        */ ?>

                                                    </div>
                                                </nav>
                                                <div id="posts-tabs" class="tab-content">
                                                    <div id="posts"
                                                         class="tab-pane fade pt-3 show active"
                                                         role="tabpanel">

                                                        <?= $postsView->show_view(); ?>
                                                    </div>
                                                    <?php /*
                                                    <div id="pendingReview"
                                                         class="tab-pane fade pt-3"
                                                         role="tabpanel">
														<?= $postsPendingView->show_view(); ?>
                                                    </div>
                                                    */ ?>

                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                <?php } else { ?>
                                    <p>You do not have permission to upload content.</p>
                                <?php } ?>
                            </div>
                            <!--/tab-pane-->
                            <div id="myMedia" class="tab-pane  fade" role="tabpanel" aria-labelledby="myMedia-tab">
                                <div class="container">
                                    <div class="row ">
                                        <div class="col p-5">
                                            <p><?= do_shortcode('[frontend-button]'); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!--/tab-pane-->
                            <div id="myDefaults" class="tab-pane  fade" role="tabpanel"
                                 aria-labelledby="myDefaults-tab">
                                <?php if ($the_user->cap_allowed('create_posts')) { ?>
                                    <div class="container">
                                        <div class="row ">
                                            <div class="col p-3">
                                                <p><?= $editUserDefaultsForm->show_form(); ?></p>
                                            </div>
                                        </div>
                                    </div>

                                <?php } else { ?>
                                    <p>You do not have permission to upload content.</p>
                                <?php } ?>
                            </div>
                            <!--/tab-pane-->
                            <div id="myIndexing" class="tab-pane -fluid fade">
                                <div class="container">
                                    <?php if ($the_user->role_allowed('index_contributor')) { ?>
                                        <div class="row ">
                                            <div class="col p-3">
                                                <nav>
                                                    <div id="indexing-nav-tabs" class="nav nav-pills" role="tablist">
                                                        <a id="councils-tab"
                                                           class="nav-item nav-link active"
                                                           role="tab"
                                                           href="#councils"
                                                           data-bs-toggle="tab">Councils</a>

                                                        <a id="camps-tab"
                                                           class="nav-item nav-link"
                                                           role="tab"
                                                           href="#camps"
                                                           data-bs-toggle="tab">Camps</a>

                                                        <a id="lodges-tab"
                                                           class="nav-item nav-link"
                                                           role="tab"
                                                           href="#lodges"
                                                           data-bs-toggle="tab">Lodges</a>
                                                        <a id="tools-tab"
                                                           class="nav-item nav-link"
                                                           role="tab"
                                                           href="#tools"
                                                           data-bs-toggle="tab">Tools</a>
                                                    </div>
                                                </nav>
                                                <div id="indexing-tabs" class="tab-content">
                                                    <div id="councils"
                                                         class="tab-pane fade pt-3 show active"
                                                         role="tabpanel">

                                                        <?= $councilsView->show_view(); ?>
                                                    </div>
                                                    <div id="camps"
                                                         class="tab-pane fade pt-3"
                                                         role="tabpanel">
                                                        <?= $campsView->show_view(); ?>
                                                    </div>
                                                    <div id="lodges"
                                                         class="tab-pane fade pt-3"
                                                         role="tabpanel">
                                                        <?= $lodgesView->show_view(); ?>
                                                    </div>
                                                    <div id="tools"
                                                         class="tab-pane fade pt-3"
                                                         role="tabpanel">

                                                        <div class="container">
                                                            <div class="col">


                                                                <div class="card w-50 ">
                                                                    <div class="card-body">
                                                                        <h5 class="card-title">Update Active End
                                                                            Dates</h5>
                                                                        <p class="card-text">Click below to update the
                                                                            End Date values for
                                                                            indexing marked as "Active" with the current
                                                                            year.</p>
                                                                        <p class="card-text">
                                                                            This indexing includes Councils, Lodges and
                                                                            Camps.</p>
                                                                        <a href="?smp_action=update_end_dates"
                                                                           class="btn btn-primary">Update</a>
                                                                    </div>
                                                                </div>


                                                            </div>


                                                        </div>

                                                    </div>
                                                </div>
                                            </div>
                                        </div>


                                    <?php } else { ?>
                                        <p>You do not have permission to upload content.</p>
                                    <?php } ?>
                                </div>
                            </div>
                            <!--/tab-pane-->
                            <?php if ($the_user->role_allowed('administrator')) { ?>
                            <div id="myAdmin" class="tab-pane  fade pt-5" role="tabpanel"
                                 aria-labelledby="myAdmin-tab">
                                <div class="container">

                                    <div class="row ">
                                        <div class="col p-3">
                                            <nav>
                                                <div id="admin-nav-tabs" class="nav nav-pills" role="tablist">
                                                    <a class="navbar-brand" href="#">Class Object Tools</a>
                                                    <a id="councils-tab"
                                                       class="nav-item nav-link active"
                                                       role="tab"
                                                       href="#admin-users"
                                                       data-bs-toggle="tab">Users</a>
                                                    <a id="councils-tab"
                                                       class="nav-item nav-link "
                                                       role="tab"
                                                       href="#admin-posts"
                                                       data-bs-toggle="tab">Posts</a>
                                                    <a id="councils-tab"
                                                       class="nav-item nav-link "
                                                       role="tab"
                                                       href="#admin-states"
                                                       data-bs-toggle="tab">States</a>
                                                    <a id="councils-tab"
                                                       class="nav-item nav-link "
                                                       role="tab"
                                                       href="#admin-councils"
                                                       data-bs-toggle="tab">Councils</a>
                                                    <a id="camps-tab"
                                                       class="nav-item nav-link"
                                                       role="tab"
                                                       href="#admin-camps"
                                                       data-bs-toggle="tab">Camps</a>
                                                    <a id="lodges-tab"
                                                       class="nav-item nav-link"
                                                       role="tab"
                                                       href="#admin-lodges"
                                                       data-bs-toggle="tab">Lodges</a>
                                                    <a id="tools-tab"
                                                       class="nav-item nav-link"
                                                       role="tab"
                                                       href="#admin-tools"
                                                       data-bs-toggle="tab">Tools</a>
                                                </div>
                                            </nav>
                                            <div id="admin-tabs" class="tab-content">
                                                <div id="admin-users"
                                                     class="tab-pane fade pt-3 show active"
                                                     role="tabpanel">
                                                    <h2>Users</h2>
                                                    <?= $editUsersView->show_view(); ?>
                                                </div>
                                                <div id="admin-posts"
                                                     class="tab-pane fade pt-3  "
                                                     role="tabpanel">
                                                    <h2>Posts</h2>
                                                    <div class="container">
                                                        <?php
                                                        //$object = PostEntry::get_one(); // for the header
                                                        //$objects = PostEntry::get_all(); // for the body

                                                        //$object_columns = array("id", "item_key", "name", "post_id");

                                                        //$table_html = Templates::show_object_table($object, $objects, $object_columns, $adm_posts_paged);
                                                        //$raw_html   = Templates::show_object_table($object, $objects, null, $adm_posts_paged);


//                                                        $postsAcc = array(
//                                                            array("Table Data", $table_html),
//                                                            array("Object Data", $table_html),
//                                                            array("Raw Data", $raw_html)
//                                                        );
//
//                                                        echo Templates::accordion('posts', $postsAcc);

                                                        ?>
                                                    </div>
                                                </div>
                                                <div id="admin-states"
                                                     class="tab-pane fade pt-3  "
                                                     role="tabpanel">
                                                    <h2>States</h2>
                                                    <div class="container">
                                                        <?php
//                                                        $object = StateEntry::get_one(); // for the header
//                                                        $objects = StateEntry::get_all(); // for the body
//
//                                                        $object_columns = array("id", "item_key", "name");
//
//                                                        $table_html = Templates::show_object_table($object, $objects, $object_columns);
//                                                        $raw_html = Templates::show_object_table($object, $objects);
//
//                                                        foreach ($objects as $object) {
//                                                            $states[] = new StateEntry($object->id);
//                                                        }
//
//                                                        $object_html = Templates::show_object_table($states[0], $states);
//
//                                                        $statesAcc = array(
//                                                            array("Table Data",  $table_html),
//                                                            array("Object Data", $object_html),
//                                                            array("Raw Data",    $raw_html)
//                                                        );
//
//                                                        echo Templates::accordion('states', $statesAcc);

                                                        ?>


                                                    </div>
                                                </div>
                                                    <div id="admin-camps"
                                                         class="tab-pane fade pt-3"
                                                         role="tabpanel">
                                                        Camps
                                                    </div>
                                                    <div id="admin-lodges"
                                                         class="tab-pane fade pt-3"
                                                         role="tabpanel">
                                                        Lodges
                                                    </div>
                                                    <div id="admin-tools"
                                                         class="tab-pane fade pt-3"
                                                         role="tabpanel">
                                                        <div class="container">
                                                            <div class="col">


                                                                <div class="card w-50 ">
                                                                    <div class="card-body">
                                                                        <h5 class="card-title">Update Active End
                                                                            Dates</h5>
                                                                        <p class="card-text">Click below to update the
                                                                            End Date values for
                                                                            indexing marked as "Active" with the current
                                                                            year.</p>
                                                                        <p class="card-text">
                                                                            This indexing includes Councils, Lodges and
                                                                            Camps.</p>
                                                                        <a href="?smp_action=update_end_dates"
                                                                           class="btn btn-primary">Update</a>
                                                                    </div>
                                                                </div>


                                                            </div>


                                                        </div>

                                                    </div>
                                                </div>


                                            </div>
                                        </div>


                                    </div>
                                </div>
                                <div id="myAdmin2" class="tab-pane fade pt-5" role="tabpanel"
                                     aria-labelledby="myAdmin2-tab">
                                    <div class="container">
                                        <?php

                                        echo $editUsersForm->show_form(); ?>
                                    </div>
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


                <?php if ($the_user->role_allowed('administrator')) { ?>
                    <style>
                        .show_only_for_admin {
                            display: none !important;
                        }
                    </style>
                <?php } ?>
        </main><!-- #main -->
    </section><!-- #primary -->


    <?php if ($smp_action === "update_end_dates") : ?>

    <!-- Modal -->
    <div class="modal fade" id="sysMessageModal" tabindex="-1" aria-labelledby="sysMessageModalLabel"
         aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="sysMessageModalLabel">Updating Entries</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php
                    echo '<p>';
                    echo "Council Entries updated: " . CouncilEntry::update_active();
                    echo '</p><p>';
                    echo "Camp Entries updated: " . CampEntry::update_active();
                    echo '</p><p>';
                    echo "Lodge Entries updated: " . LodgeEntry::update_active();
                    echo '</p>';
                    ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <script type="text/javascript">
        (function ($) {


            var myModal = new bootstrap.Modal(document.getElementById('sysMessageModal'));

            myModal.show();

        })(jQuery);
    </script>
<?php endif; ?>


    <?php /*
    <script type="text/javascript">
        (function ($) {

        })(jQuery);
    </script>
*/ ?>

    <script type="text/javascript">

        (function ($) {

            $("ul.pagination").wrap('<nav></nav>').removeClass("frm_pagination");

            $("ul.pagination li").each(function () {
                $(this).addClass("page-item");
                $(this).find("a").addClass("page-link");
            });


            let tab = '<?=get_request_parameter('tab') ?>';
            let tab2 = '<?=get_request_parameter('tab2') ?>';


            switch (tab) {
                case 'myDefaults':
                    let myDefaultsTab = new bootstrap.Tab(document.querySelector('#myDefaults-tab')).show();
                    break;
                case 'myIndexing':
                    let myIndexingTab = new bootstrap.Tab(document.querySelector('#myIndexing-tab')).show();
                    break;
                case 'myAdmin':
                    let myAdminTab = new bootstrap.Tab(document.querySelector('#myAdmin-tab')).show();
                    break;
                case 'myAdmin2':
                    let myAdmin2Tab = new bootstrap.Tab(document.querySelector('#myAdmin2-tab')).show();
                    break;
                default:
                // code block
            }


            switch (tab2) {
                case 'myCouncils':
                    let myCouncilsTab = new bootstrap.Tab(document.querySelector('a#councils-tab')).show();
                    break;
                case 'myCamps':
                    let myCampsTab = new bootstrap.Tab(document.querySelector('a#camps-tab')).show();
                    break;
                case 'myLodges':
                    let myLodgesTab = new bootstrap.Tab(document.querySelector('a#lodges-tab')).show();
                    break;

                default:
                // code block
            }


            $("#myAdmin a").each(function () {
                let href = this.href += '&tab=myAdmin2';
                $(this).attr('href', href);
            });


            $(".dashboard-tabs a").on("click", function () {
                $(".admin-tab").removeClass("active");
            });

        })(jQuery);
    </script>

    <?php
get_footer();
