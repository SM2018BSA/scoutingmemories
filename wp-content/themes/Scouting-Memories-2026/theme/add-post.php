<?php
/**
 * Add a Post: the page's own content holds the Formidable form 6 shortcode.
 * The old theme had NO check here at all (AUDIT row E); the mapping's minimum
 * is a signed-in visitor. (Owner follow-ups noted in AUDIT.md: set form 6 to
 * logged-in-only inside Formidable and reconsider its visible Status field.)
 *
 * Template Name: Add Post Page
 *
 * @package Scouting_Memories_2026
 */

use ScoutingMemories\Core\Access;

get_header();
?>

	<div id="primary" class="content-area max-w-4xl mx-auto mt-8">
		<main id="main" class="site-main" role="main">
			<?php if ( Access::can_add_post() ) : ?>
				<?php
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/content/content', 'page' );
				endwhile;
				?>
			<?php else : ?>
				<p>Please <a class="link" href="<?php echo esc_url( home_url( '/login/' ) ); ?>">log in</a> to add a post.</p>
			<?php endif; ?>
		</main><!-- #main -->
	</div>

<?php
get_footer();
