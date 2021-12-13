<?php
/*
  Template Name: My Account Page


 */

get_header();


$myAccountForm        = new Form( MY_ACCOUNT_FORMID );
$editUsersForm        = new Form( EDIT_USERS_FORMID );
$editUserDefaultsForm = new Form( EDIT_USER_DEFAULTS_FORMID );
$councilsForm         = new Form( COUNCILS_FORMID );


$postsView        = new View( POSTS_VIEWID );
$postsPendingView = new View( POSTS_PENDING_VIEWID );
$editUsersView    = new View( EDIT_USERS_VIEWID );


/** @var $the_user CurrentUser */
/** @var $councilsView CurrentUser */
/** @var $lodgesView CurrentUser */
/** @var $campsView CurrentUser */




//$councilsView = new View (ALL_COUNCILS_VIEWID);
//$lodgesView   = new View (ALL_LODGES_VIEWID);
//$campsView    = new View (ALL_CAMPS_VIEWID);

//$newuser = new NewUserEntry(565);


//var_dump($newuser);

//echo 'finished';
//die();

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

									<?php

/*
									if ( get_request_parameter( 'runit' ) ) {

										// If so echo the value
										$runit = get_request_parameter( 'runit' );


										if ( $runit === 'yes_' ) {
											echo 'lets do this';

											//											$post_ids = get_posts(array(
											//												'fields'          => 'ids', // Only get post IDs
											//												'posts_per_page'  => -1
											//											));
											//
											//											$post = array();
											//
											//											$i  = 0;
											//											foreach($post_ids as $post_id) {
											//											    $post[] = new Post ($post_id);
											//                                                //if ( $i >= 25) break;
											//
											//
											//
											//                                                $i++;
											//                                            }
											//
											//											var_dump ($post);

											//											// make this a  admin function to update meta for lodges council slugs
											////											//
											//											$camp_entries  = FrmEntry::getAll([	'form_id' => ADD_A_CAMP_FORMID  ]);
											////											$lodge_entries = FrmEntry::getAll([ 'form_id' => ADD_A_LODGE_FORMID ]);
											////
											////
											//////
											////											foreach ($lodge_entries as $entries) {
											////												$lodges[] = new LodgeEntry($entries->id);
											////
											////											}
											//////
											//											foreach ($camp_entries as $entries) {
											//												$camps[] = new CampEntry($entries->id);
											//
											//											}
											//
											////                                            var_dump($lodges);
											//                                            var_dump($camps);
											echo 'no no, already ran!';
											die();


										}


									}

*/
									?>


									<?php if ( $the_user->role_allowed( 'administrator' ) ) { ?>

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


							if ( $the_user->cap_allowed( 'create_posts' ) ) {
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

							if ( $the_user->role_allowed( 'index_contributor' ) ) { ?>
                                <li class="nav-item">
                                    <button class="myIndexing nav-link"
                                            id="myIndexing-tab"
                                            data-bs-target="#myIndexing"
                                            data-bs-toggle="tab">Indexing
                                    </button>
                                </li>
							<?php } ?>
							<?php if ( $the_user->role_allowed( 'administrator' ) ) { ?>
                                <li class="nav-item">
                                    <button class="myAdmin nav-link"
                                            id="myAdmin-tab"
                                            data-bs-target="#myAdmin"
                                            data-bs-toggle="tab">Admin
                                    </button>
                                </li>
                                <li class="nav-item "  >
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
								<?php if ( $the_user->cap_allowed( 'create_posts' ) ) { ?>

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
                                                        */?>

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
                                                    */?>

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
                                            <p><?= do_shortcode( '[frontend-button]' ); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!--/tab-pane-->
                            <div id="myDefaults" class="tab-pane  fade" role="tabpanel"
                                 aria-labelledby="myDefaults-tab">
								<?php if ( $the_user->cap_allowed( 'create_posts' ) ) { ?>
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
									<?php if ( $the_user->role_allowed( 'index_contributor' ) ) { ?>
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
                                                </div>
                                            </div>
                                        </div>


									<?php } else { ?>
                                        <p>You do not have permission to upload content.</p>
									<?php } ?>
                                </div>
                            </div>
                            <!--/tab-pane-->
							<?php if ( $the_user->role_allowed( 'administrator' ) ) { ?>
                                <div id="myAdmin" class="tab-pane  fade pt-5" role="tabpanel"
                                     aria-labelledby="myAdmin-tab">
                                    <div class="container">
										<?= $editUsersView->show_view(); ?>
                                    </div>
                                </div>
                                <div id="myAdmin2" class="tab-pane fade pt-5"  role="tabpanel"
                                     aria-labelledby="myAdmin2-tab">
                                    <div class="container">
										<?= $editUsersForm->show_form(); ?>
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


			<?php if ( $the_user->role_allowed( 'administrator' ) ) { ?>
                <style>
                    .show_only_for_admin {
                        display: none !important;
                    }
                </style>
			<?php } ?>
        </main><!-- #main -->
    </section><!-- #primary -->
    <script type="text/javascript">

        (function ($) {


            $("ul.pagination").wrap('<nav></nav>').removeClass("frm_pagination");

            $("ul.pagination li").each(function () {
                $(this).addClass("page-item");
                $(this).find("a").addClass("page-link");
            });


            let tab = '<?=get_request_parameter( 'tab' ) ?>';
            let tab2 = '<?=get_request_parameter( 'tab2' ) ?>';


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
