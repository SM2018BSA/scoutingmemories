<?php
/**
 * The template for displaying all single posts
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#single-post
 *
 * @package ScoutingMemories
 *
 *
 * /* Template Name: Frontpage  */


$states_view_id = 2300; // local and live
//$states_view_id = 1553; // online dev
$states_view = new View($states_view_id);

get_header();


?>

    <div id="primary" class="content-area mt-5 h-100 ">


        <main id="main" class="justify-content-center container-fluid site-main" role="main">

            <div class="row d-flex justify-content-center">

                <div class="col-xxl-5 col-xl-7 col-lg-8 col-md-9 col-sm-12">
                    <div id="carouselFrontpage" class="carousel slide mb-3" data-bs-ride="carousel">
                        <div class="carousel-inner">
                            <div class="carousel-item active">
                                <img src="https://storage.scoutingmemories.org/2021/02/935b3e27-smp-banner-graphic-1.jpg"
                                     class="d-block w-100" alt="Scouting Memories Banner 1">
                            </div>
                            <div class="carousel-item">
                                <img src="https://storage.scoutingmemories.org/2021/02/8a5d960a-smp-banner-graphic-2.jpg"
                                     class="d-block w-100" alt="Scouting Memories Banner 2">
                            </div>
                            <div class="carousel-item">
                                <img src="https://storage.scoutingmemories.org/2021/02/18062451-smp-banner-graphic-3.jpg"
                                     class="d-block w-100" alt="Scouting Memories Banner 3">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="w-100"></div>

                <div class="col-xxl-5 col-xl-7 col-lg-8 col-md-9 col-sm-12">

                    <ul class="nav flex-column flex-sm-row mb-3" id="myTab" role="tablist">
                        <li class="flex-sm-fill p-3 col-xl-4 col-lg-4 col-md-12 col-sm-12" role="presentation">

                            <div class="col d-flex justify-content-center">
                                <div class="card cursor" id="home-tab"
                                     data-bs-toggle="tab"
                                     data-bs-target="#home-tab-pane" role="tab" style="width: 14em;">
                                    <div class="card-body ">
                                        <h5 class="text-center">What Are We</h5>
                                        <p class="card-text"></p>
                                    </div>
                                </div>
                            </div>

                        </li>
                        <li class="flex-sm-fill p-3 col-xl-4 col-lg-4 col-md-12 col-sm-12" role="presentation">

                            <div class="col d-flex justify-content-center">
                                <div class="card cursor bg-primary text-white"  id="profile-tab"
                                     data-bs-toggle="tab"
                                     data-bs-target="#profile-tab-pane" role="tab" style="width: 14em;">
                                    <div class="card-body">
                                        <h5 class="text-center">Search Memories</h5>
                                        <p class="card-text"></p>
                                    </div>
                                </div>
                            </div>

                        </li>
                        <li class="flex-sm-fill p-3 col-xl-4 col-lg-4 col-md-12 col-sm-12" role="presentation">

                            <div class="col d-flex justify-content-center">
                                <div class="card cursor" id="contact-tab"
                                     data-bs-toggle="tab"
                                     data-bs-target="#contact-tab-pane" role="tab" style="width: 14em;">
                                    <div class="card-body ">
                                        <h5 class="text-center" >How To Guides</h5>
                                        <p class="card-text"></p>
                                    </div>
                                </div>
                            </div>

                        </li>

                    </ul>

                    <div class="tab-content container  " id="myTabContent">
                        <div class="tab-pane fade m-3" id="home-tab-pane" role="tabpanel"
                             tabindex="0">
                            <div class="p-3 mx-auto">
                                <p>The Scouting Memories Project is an interactive, searchable archive website of
                                    Scouting
                                    history in the United States.</p>
                                <p>As the national repository of the BSA, the Scouting Memories collection contains
                                    indexes
                                    of 2,323 councils (existing and past), 3,550 camps (present
                                    and
                                    past), and 1,002 lodges (present and past, including camp honor societies).</p>
                                <p>Every council, camp, and lodge has a unique page to capture Scouting Memories in six
                                    categories: History, Photographs, Video, Oral History, Memorabilia, and Scout
                                    Museums.</p>
                                <p>Under development through 2024, the website is nationally-chartered by the Boy Scouts
                                    and
                                    sponsored jointly by the National Scouting Museum, the National Order of the Arrow,
                                    and
                                    the Scouting Alumni Association.</p>
                                <p>Currently Scout historians around the nation are digitizing and archiving their local
                                    historical information.</p>
                                <p>After 2024, a public interface with gatekeeper monitoring for sharing of historical
                                    information is planned. Currently the general public may view and search the
                                    Scouting
                                    Memories website at: scoutingmemories.org </p>
                            </div>
                        </div>
                        <div class="tab-pane fade  m-3" id="profile-tab-pane" role="tabpanel" aria-labelledby="profile-tab"
                             tabindex="0">
                            <?php
                            echo SearchForm::show_form();
                            ?>
                        </div>
                        <div class="tab-pane fade m-3" id="contact-tab-pane" role="tabpanel" aria-labelledby="contact-tab"
                             tabindex="0">
                            <iframe width="100%" height="400"
                                    src="https://www.youtube.com/embed/a_lF5F-oeEY?rel=0&modestbranding=1"
                                    title="YouTube video player" frameborder="0"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                    allowfullscreen></iframe>
                        </div>

                    </div>

            </div>


        </main><!-- #main -->
        <?php /* */ ?>

    </div>


    <script>
        jQuery(document).ready(function () {

            $('.carousel').carousel({
                interval: 10000
            });


            $('#myTab #profile-tab').tab('show');

            $('#myTab .card.cursor').on('shown.bs.tab', function(){
                $('.card.cursor').removeClass('bg-primary text-white');

                $(this).toggleClass('bg-primary text-white');
            });







        });


    </script>
<?php
//get_sidebar();
get_footer();
