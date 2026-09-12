<?php
/*
  Template Name: Add Post Page


 */

get_header();

$my_entry_id = get_request_parameter( 'entry' );


//if ( ! empty( $my_entry_id ) ) {
//	$addAPost = new PostEntry( (int) $my_entry_id, $the_user );
//}
//



?>
    <div id="primary" class="content-area container mt-5 h-100">
        <main id="main" class="site-main container-fluid p-0 m-0" role="main">

			<?php
			while ( have_posts() ) :
				the_post();

				get_template_part( 'template-parts/content', 'page' );

				// If comments are open or we have at least one comment, load up the comment template.
				if ( comments_open() || get_comments_number() ) :
					comments_template();
				endif;

			endwhile; // End of the loop.
			?>

        </main><!-- #main -->
    </div><!-- #primary -->

<?php
//get_sidebar();
get_footer();
