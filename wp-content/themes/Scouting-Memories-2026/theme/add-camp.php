<?php
/**
 * Add a Camp: the form comes from the forms gateway; who may use the page
 * comes from the core plugin's Access class.
 *
 * Template Name: Add Camp
 *
 * @package Scouting_Memories_2026
 */

use ScoutingMemories\Core\Access;
use ScoutingMemories\Core\Forms\FormsGateway;
use ScoutingMemories\Core\Forms\GatewayFactory;

get_header();
?>

	<div id="primary" class="content-area mx-auto mt-4 mb-8 max-w-4xl">
		<main id="main" class="site-main">
			<?php if ( Access::can_add_camp() ) : ?>
				<h1 class="mb-4 text-3xl"><?php the_title(); ?></h1>
				<?php echo GatewayFactory::current()->form( FormsGateway::FORM_ADD_CAMP ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php else : ?>
				<p><?php esc_html_e( 'You do not have permissions to add a camp.', 'scouting-memories-2026' ); ?></p>
			<?php endif; ?>
		</main><!-- #main -->
	</div>

<?php
get_footer();
