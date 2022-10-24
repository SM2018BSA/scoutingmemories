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


        <main id="main" class="d-flex justify-content-center container-fluid site-main" role="main">

            <div class="row d-flex w-75 justify-content-center">


                <div class="col  col-xl-7 col-lg-8 col-md-8 col-sm-12">


                    <?php /*   */ ?>

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


                    <?php
                    /*   while ( have_posts() ) :
                           the_post();
                           get_template_part( 'template-parts/content', get_post_type() );
                       endwhile; // End of the loop.
                      */ ?>


                </div>

                <div class="col col-xl-7 col-lg-8 col-md-8 col-sm-12  ">
                    <div class="container-fluid front-nav">
                        <?php /*   */ ?>
                        <div class="row  pt-3 ">
                            <div class="col d-flex justify-content-center">
                                <div class="card mb-3" style="width: 14em;">

                                    <div class="card-body ">
                                        <h5 id="buttonOne" class="  card-title text-center ">What Are We</h5>
                                        <p class="card-text"></p>

                                    </div>
                                </div>
                            </div>
                            <div class="col d-flex justify-content-center">
                                <div id="collapseTwoBtn" class="bg-primary text-white card mb-3 pointer"
                                     style="width: 14em;">

                                    <div class="card-body ">
                                        <h5 id="buttonTwo" class="card-title text-center ">Search Memories</h5>
                                        <p class="card-text"></p>

                                    </div>
                                </div>
                            </div>
                            <div class="col d-flex justify-content-center">
                                <div id="collapseThreeBtn" class="card mb-3 pointer" style="width: 14em;">

                                    <div class="card-body ">
                                        <h5 id="buttonThree" class="   card-title text-center  ">How To Guides</h5>
                                        <p class="card-text"></p>
                                    </div>
                                </div>
                            </div>

                        </div>


                        <div class="accordion-item">
                            <div id="collapseOne"
                                 class="accordion-collapse collapse col  col-xl-7 col-lg-8 col-md-8 col-sm-12">
                                <div class="accordion-body">
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <div id="collapseTwo" class=" accordion-collapse collapse show col ">
                                <div class="accordion-body">
                                    <?php
                                    echo SearchForm::show_form();
                                    ?>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <div id="collapseThree" class="accordion-collapse collapse col pb-3">
                                <div class="accordion-body">
                                    <iframe width="100%" height="400"
                                            src="https://www.youtube.com/embed/a_lF5F-oeEY?rel=0&modestbranding=1"
                                            title="YouTube video player" frameborder="0"
                                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                            allowfullscreen></iframe>
                                </div>
                            </div>
                        </div>


                    </div>


                </div> <?php /* End of Accordian */ ?>


            </div>


        </main><!-- #main -->
        <?php /* */ ?>

    </div>


    <script>
        jQuery(document).ready(function () {

            $('.carousel').carousel({
                interval: 10000
            });

            $('.collapse').collapse({
                toggle: false
            })


            $('#collapseTwoBtn').on('click', function () {
                if ($('#collapseThree').hasClass('show')) {
                    $('.front-nav .bg-primary').removeClass('bg-primary');
                    $('.front-nav .text-white').removeClass('text-white');
                    $('#collapseThree').removeClass('show');
                    $(this).addClass('text-white bg-primary');
                    $('#collapseTwo').addClass('show');
                } else {
                    $('#collapseThree').removeClass('show');
                    $(this).addClass('text-white bg-primary');
                    $('#collapseTwo').addClass('show');
                }
            });


            $('#collapseThreeBtn').on('click', function () {
                if ($('#collapseTwo').hasClass('show')) {
                    $('.front-nav .bg-primary').removeClass('bg-primary');
                    $('.front-nav .text-white').removeClass('text-white');
                    $('#collapseTwo').removeClass('show');
                    $(this).addClass('text-white bg-primary');
                    $('#collapseThree').addClass('show');
                } else {
                    $('#collapseTwo').removeClass('show');
                    $(this).addClass('text-white bg-primary');
                    $('#collapseTwo').addClass('show');
                }
            });


        });


    </script>
<?php
//get_sidebar();
get_footer();
