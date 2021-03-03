<?php
/**
 * The main template file
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

$current_category = get_category( get_query_var( 'cat' ) );

//category/history/?state=PA&council=Cradle_of_Liberty_525&lodge=&camp=&start_date=1900&end_date=2020&pass_entry=

//$req['states'] = get_request_parameter('state');
//$req['councils'] = get_request_parameter('council');
//$req['lodges'] = get_request_parameter('lodge');
//$req['camps'] = get_request_parameter('camp');
//$req['start'] = get_request_parameter('start_date');
//$req['end'] = get_request_parameter('end_date');
//
//
////var_dump($req );die();
//
//$unused_states   = explode(',', $req['states']);
//$unused_councils = explode(',', $req['councils']);
//$unused_lodges   = explode(',', $req['lodges']);
//$unused_camps    = explode(',', $req['camps']);



ini_set('xdebug.var_display_max_depth', '10');
ini_set('xdebug.var_display_max_children', '256');
ini_set('xdebug.var_display_max_data', '1024');



$paged = get_query_var( 'paged' ) ? absint( get_query_var( 'paged' ) ) : 1;


$indexing = new Indexing($current_category->slug, $paged);

//var_dump($indexing);




//$post = new Post('3131');


//$args = array(
//
//    'category_name' => $current_category->slug,
//	'meta_query' =>
//		array('relation' => 'OR',
//			array(
//				'key'     => 'state',
//				'value'   => 'PE',
//				'compare' => 'LIKE'
//			),
//			array(
//				'key'     => 'council',
//				'value'   => 'general_herkimer_400',
//				'compare' => 'LIKE'
//			),
//
//        )
//
//);





$the_query = new WP_Query( $indexing->query );


//var_dump($the_query->query);die();

//var_dump( $the_query->posts );



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






            <div id="accordion" role="tablist" aria-multiselectable="true">
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

                    <div id="collapseOne" class="collapse hide" aria-labelledby="headingOne" data-parent="#accordion">
                        <div class="card-body">

	                        <?php echo SearchForm::show_form2(); ?>

                        </div>
                    </div>
                </div>

            </div>



<script>
    jQuery(document).ready( function() {


        jQuery('.title').on("click", function(){

            jQuery('.fas.fa-angle-down').addClass('fa-angle-up').removeClass('fa-angle-down');

        });


    });
</script>







        </div>
		<?php
		if ( $the_query->have_posts() ) :



			/* Start the Loop */
			while ( $the_query->have_posts() ) :
				$the_query->the_post();

				/*
				 * Include the Post-Type-specific template for the content.
				 * If you want to override this in a child theme, then include a file
				 * called content-___.php (where ___ is the Post Type name) and that will be used instead.
				 */
				get_template_part( 'template-parts/content-search', get_post_type() );

			endwhile;

            Theme::bootstrap_pagination($the_query);

		else :

			get_template_part( 'template-parts/content', 'none' );

		endif;


		//wp_reset_query();     // Restore global post data stomped by the_post().
		?>

    </main><!-- #main -->

<?php


echo SearchForm::search_form_js();



//get_sidebar();
get_footer();
