<?php
/**
 * Add a Council (Formidable form 8), gated by the real add_council capability
 * set (AUDIT fix D) instead of the old broken check.
 *
 * Template Name: Add Council
 *
 * @package Scouting_Memories_2026
 */

use ScoutingMemories\Core\Access;

get_header();
?>

	<div id="primary" class="content-area max-w-4xl mx-auto mt-8">
		<main id="main" class="site-main" role="main">
			<?php if ( Access::can_add_council() ) : ?>
				<h1 class="text-3xl mb-4"><?php the_title(); ?></h1>
				<?php echo ( new Form( ADD_A_COUNCIL_FORMID ) )->show_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php else : ?>
				<p>You do not have permissions to add a council.</p>
			<?php endif; ?>
		</main><!-- #main -->
	</div>

<?php
get_footer();
