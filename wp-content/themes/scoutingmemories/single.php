<?php
/**
 * The template for displaying all single posts
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#single-post
 *
 * @package ScoutingMemories
 */

get_header();
?>

    <div id="primary" class="content-area container mt-5 h-100 aasdf" >
        <main id="main" class="site-main container-fluid p-0 m-0" role="main">

		<?php
		while ( have_posts() ) :
			the_post();

            get_template_part( 'template-parts/content', get_post_type() );




//			the_post_navigation(
//				array(
//					'prev_text' => '<span class="nav-subtitle">' . esc_html__( 'Previous:', 'scoutingmemories' ) . '</span> <span class="nav-title">%title</span>',
//					'next_text' => '<span class="nav-subtitle">' . esc_html__( 'Next:', 'scoutingmemories' ) . '</span> <span class="nav-title">%title</span>',
//				)
//			);





            // If comments are open or we have at least one comment, load up the comment template.
            if ( comments_open() || get_comments_number() ) :
                comments_template();
            endif;

		endwhile; // End of the loop.
		?>

	    </main><!-- #main -->
    </div>

<?php
//get_sidebar();
get_footer();
