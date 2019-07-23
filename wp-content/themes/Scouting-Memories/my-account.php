<?php /* Template Name: My Account  */

get_header(); ?>


    <div class="container h-100">
        <div id="primary" class="content-area row h-100">
            <main id="main" class="site-main container my-auto" role="main">

                <hr>
                <div class="container bootstrap snippet">

                    <div class="row">
                        <div class="col-sm-3 border"><!--left col-->


                            <div class="text-center">
                                [frm-field-value field_id=183 user_id=current]

                            </div>

                            <div class="panel panel-default">
                                <div class="panel-heading text-center"> [frm-field-value field_id=168 user_id=current]
                                    [frm-field-value
                                    field_id=184 user_id=current]
                                </div>
                                <div class="panel-body text-center"><a
                                            href="http://scoutingmemories.org/admin/?action=logout&amp;redirect_to=http%3A%2F%2Fscoutingmemories.org%2Flogin%2F"
                                            class="frm_logout_link ">Logout</a></div>
                            </div>


                        </div><!--/col-3-->
                        <div class="col-sm-9">

                            <ul class="nav nav-tabs">
                                <li class="nav-item active"><a class="nav-link active" data-toggle="tab"
                                                               href="#myAccount">Home</a></li>
                                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#myPosts">Posts</a>
                                </li>
                                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#myMedia">Media</a>
                                </li>
                                <li class="nav-item"><a class="nav-link" data-toggle="tab"
                                                        href="#myDefaults">Defaults</a></li>
                                <li class="nav-item"><a class="nav-link" data-toggle="tab"
                                                        href="#myIndexing">Indexing</a></li>
                            </ul>


                            <div class="tab-content">
                                <div class="tab-pane container active" id="myAccount">


                                    [formidable id="22"]


                                </div><!--/tab-pane-->
                                <div class="tab-pane container fade" id="myPosts">
                                    <div class="row ">
                                        <div class="col p-5">
                                            <div class="pagination ">

                                                [display-frm-data id=1186 filter=limited order_by="created_at"
                                                order="DESC" ]

                                            </div>
                                        </div>
                                    </div>


                                </div><!--/tab-pane-->
                                <div class="tab-pane container fade" id="myMedia">


                                    <div class="row ">
                                        <div class="col p-5">
                                            <p>[hide for="subscriber"][frontend-button][/hide][hide
                                                for="!subscriber"]You do not have
                                                permission to upload content.[/hide]</p>

                                        </div>
                                    </div>

                                </div>
                                <div class="tab-pane container fade" id="myDefaults">


                                    <div class="row ">
                                        <div class="col p-3">
                                            <p>[hide for="subscriber"]

                                                [formidable id="30"]

                                                [/hide][hide for="!subscriber"]You do not have
                                                permission to upload content.[/hide]</p>

                                        </div>
                                    </div>

                                </div>
                                <div class="tab-pane container fade" id="myIndexing">


                                    <div class="row ">
                                        <div class="col p-3">
                                            <p>[hide for="subscriber"]


                                                <nav>
                                                    <div class="nav nav-pills" id="nav-tab" role="tablist">

                                                        <a class="nav-item nav-link active" id="councils-tab"
                                                           data-toggle="tab"
                                                           role="tab" aria-controls="councils" aria-selected="true"
                                                           href="#councils">Councils</a>

                                                        <a class="nav-item nav-link" id="camps-tab" data-toggle="tab"
                                                           role="tab" aria-controls="councils" aria-selected="true"
                                                           href="#camps">Camps</a>

                                                        <a class="nav-item nav-link" id="lodges-tab" data-toggle="tab"
                                                           href="#lodges"
                                                           role="tab" aria-controls="councils" aria-selected="true">Lodges</a>


                                                    </div>
                                                </nav>
                                            <div class="tab-content" id="nav-tabContent">
                                                <div class="tab-pane fade show active" id="councils" role="tabpanel"
                                                     aria-labelledby="councils-tab">[display-frm-data id=1172
                                                    filter=limited]
                                                </div>
                                                <div class="tab-pane fade" id="camps" role="tabpanel"
                                                     aria-labelledby="nav-camps-tab">[display-frm-data id=1179
                                                    filter=limited]
                                                </div>
                                                <div class="tab-pane fade" id="lodges" role="tabpanel"
                                                     aria-labelledby="nav-lodges-tab">[display-frm-data id=1180
                                                    filter=limited]
                                                </div>
                                            </div>


                                            [/hide][hide for="!subscriber"]You do not have
                                            permission to upload content.[/hide]</p>

                                        </div>
                                    </div>

                                </div>

                            </div><!--/tab-pane-->
                        </div><!--/tab-content-->

                    </div><!--/col-9-->
                </div><!--/row-->

                <div class="m-5 p-2 w-100"></div>

            </main><!-- #main -->
        </div><!-- #primary -->
    </div>


<?php
get_footer();
