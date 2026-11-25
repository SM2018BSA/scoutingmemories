<?php
/**
 * Add a Council: the form comes from the forms gateway; who may use the page
 * comes from the core plugin's Access class.
 *
 * Template Name: Add Council
 *
 * @package Scouting_Memories_2026
 */

use ScoutingMemories\Core\Access;
use ScoutingMemories\Core\Forms\FormsGateway;
use ScoutingMemories\Core\Forms\GatewayFactory;

get_header();
?>

	<div id="primary" class="content-area sm-container mt-12">
		<main id="main" class="site-main">
			<?php if ( Access::can_add_council() ) : ?>
				<h1><?php the_title(); ?></h1>
				<?php echo GatewayFactory::current()->form( FormsGateway::FORM_ADD_COUNCIL ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php else : ?>
				<?php esc_html_e( 'You do not have permissions to add a council.', 'scouting-memories-2026' ); ?>
			<?php endif; ?>
		</main><!-- #main -->
	</div>

<?php
get_footer();
