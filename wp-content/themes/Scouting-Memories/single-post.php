<?php
/**
 * The template for displaying all single posts
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#single-post
 *
 * @package StrapPress
 */


get_header(); ?>

	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">

		<?php
		while ( have_posts() ) : the_post();

			get_template_part( 'template-parts/content', get_post_format() ); ?>

            <div class="row container credits">





            </div>
            <div class="row container timeline">

                <ul class="list-group col-6">
                    <li class="list-group-item-dark text-white strong p-3 list-unstyled">TIMELINE</li>
                    <li class="list-group-item"><strong>State:</strong> <?php the_field('state') ?></li>
                    <li class="list-group-item"><strong>Council:</strong> <?php the_field('council') ?></li>
                    <li class="list-group-item"><strong>Camp:</strong> <?php the_field('camp') ?></li>
                    <li class="list-group-item"><strong>Lodge:</strong> <?php the_field('lodge') ?></li>
                    <li class="list-group-item"><strong>Start Date:</strong> <?php the_field('start_date') ?></li>
                    <li class="list-group-item"><strong>End Date:</strong> <?php the_field('end_date') ?></li>

                </ul>

                <ul class="list-group col-6">
                    <li class="list-group-item-dark text-white strong p-3 list-unstyled">META </li>
                    <li class="list-group-item"><strong>Publisher of Digital:</strong> <?php the_field('publisher_of_digital') ?></li>
                    <li class="list-group-item"><strong>Date of Original:</strong> <?php the_field('date_of_original') ?></li>
                    <li class="list-group-item"><strong>Date of Digital:</strong> <?php the_field('date_of_digital') ?></li>
                    <li class="list-group-item"><strong>Identifier:</strong> <?php the_field('identifier') ?></li>
                    <li class="list-group-item"><strong>Subject:</strong> <?php the_field('meta_subject') ?></li>
                    <li class="list-group-item"><strong>Location:</strong> <?php the_field('meta_location') ?></li>
                    <li class="list-group-item"><strong>Physical Description:</strong> <?php the_field('meta_physical_description') ?></li>

                </ul>


            </div>

<?php
			// If comments are open or we have at least one comment, load up the comment template.
			if ( comments_open() || get_comments_number() ) :
				comments_template();
			endif;

		endwhile; // End of the loop.
		?>

		</main><!-- #main -->
<?php




        $post_id = get_the_ID();
        $post_categories = wp_get_post_categories( $post_id );


        $cats = array();
        foreach ( $post_categories as $c ) {
            $cat    = get_category( $c );
            $cats[] = array( 'name' => $cat->name, 'slug' => $cat->slug );
        }


        // Only show this widget on posts in the museums category
        $show_related = false;
        foreach ($cats as $cat) {
	        if (in_array("museums", $cat) )
		        $show_related = true;
        }




        ?>

        <?php if ($show_related) : ?>
		    <?php if ( !function_exists('dynamic_sidebar') || !dynamic_sidebar("Related Info") ) : ?>
	        <?php endif;?>
        <?php endif;?>

	</div><!-- #primary -->

<?php
get_footer();
