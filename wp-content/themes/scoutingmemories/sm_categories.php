<?php
/**
 * /*  Template Name: SM Categories
 *
 * This is the most generic template file in a WordPress theme
 * and one of the two required files for a theme (the other being style.css).
 * It is used to display a page when nothing more specific matches a query.
 * E.g., it puts together the home page when no home.php file exists.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package ScoutingMemories
 */


ini_set('xdebug.var_display_max_depth', '10');
ini_set('xdebug.var_display_max_children', '256');
ini_set('xdebug.var_display_max_data', '1024');


$current_category = get_category(get_query_var('cat'));


$current_category = get_category(get_query_var('cat'));
$paged = get_query_var('paged') ? absint(get_query_var('paged')) : 1;

$indexing = new Indexing($current_category->slug, $paged);


$the_query = new WP_Query($indexing->query, array('paged' => $paged));
//$the_query = new WP_Query( $args );


/*global $wp_query;


// Pagination fix
$temp_query = $wp_query;
$wp_query   = NULL;
$wp_query   = $the_query;*/


//foreach ($the_query->posts as $post) {
//    var_dump( get_post($post->ID));
//    var_dump( get_the_category($post->ID));
//    var_dump( get_post_meta($post->ID) );
//}
//die();


get_header();
?>

    <main id="primary" class="site-main container mt-5 mb-5">
        <div class="page-header mb-5 border-bottom">


            <div class="card">
                <div class="card-header" id="headingOne">
                    <h5 class="mb-0">
                        <div class="title ">
                            <h1 class=" "> Category:
                                <?php
                                $cat = get_category_by_slug($current_category->slug);
                                echo $cat->name;
                                ?>


                            </h1>
                        </div>
                    </h5>
                </div>
            </div>
            <?php if ($cat->name == "Museums") : ?>

                <div class="container p-3">


                    <div class="row">
                        <div class="col"><h5>National List of Scout Museums: </h5></div>
                        <div class="col"><a
                                    class="btn btn-secondary"
                                    href="http://storage.scoutingmemories.org/2022/01/aa48b284-smp-museum-list-2022.pdf"

                                    target="_blank">Searchable PDF</a></div>
                        <div class="col"><a class="btn btn-secondary" href="scouting-memories-museum-list"> View Online</a>
                        </div>
                    </div>


                </div>

            <?php endif; ?>

            <?php /*
            <div id="accordion" class="hide hidden"  role="tablist" aria-multiselectable="true">
                <div class="card">
                    <div class="card-header" id="headingOne">
                        <h5 class="mb-0">
                            <div class="title cursor" data-toggle="collapse" data-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                              <h1 class="accordion-button "> Category:
                                    <?php
                                    $cat = get_category_by_slug($current_category->slug) ;
                                    echo $cat->name;
                                    ?>

                                  <i class="fas fa-angle-down rotate-icon float-right"></i>
                              </h1>
                            </div>
                        </h5>
                    </div>

                    <div id="collapseOne" class="collapse hide hidden" aria-labelledby="headingOne" data-parent="#accordion">
                        <div class="card-body">

	                        <?php echo SearchForm::show_form2(); ?>

                        </div>
                    </div>
                </div>

            </div>

 */ ?>


            <script>
                jQuery(document).ready(function () {


                    jQuery('.title').on("click", function () {

                        jQuery('.fas.fa-angle-down').addClass('fa-angle-up').removeClass('fa-angle-down');

                    });


                });
            </script>


        </div>
        <?php
        if ($the_query->have_posts()) :


            /* Start the Loop */
            while ($the_query->have_posts()) :
                $the_query->the_post();

                /*
                 * Include the Post-Type-specific template for the content.
                 * If you want to override this in a child theme, then include a file
                 * called content-___.php (where ___ is the Post Type name) and that will be used instead.
                 */
                get_template_part('template-parts/content-search', get_post_type());


            endwhile;

            Theme::bootstrap_pagination($the_query);

            // Reset postdata
            wp_reset_postdata();


            /*            previous_posts_link( 'Older Posts' );
                        next_posts_link( 'Newer Posts', $the_query->max_num_pages );*/


            // typically these functions will be enclosed
            // by 'div' elements for display / styling
            //previous_posts_link( 'Older Posts' );
            //next_posts_link( 'Newer Posts', $the_query->max_num_pages );


            //the_posts_pagination();

            // Reset main query object
            /*            $wp_query = NULL;
                        $wp_query = $temp_query;*/
            wp_reset_query();

        else :

            get_template_part('template-parts/content', 'none');

        endif;


        //wp_reset_query();     // Restore global post data stomped by the_post().
        ?>

    </main><!-- #main -->

<?php


echo SearchForm::search_form_js();


//get_sidebar();
get_footer();
