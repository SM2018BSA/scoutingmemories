<?php
/**
 * Add a Lodge: the form comes from the forms gateway; who may use the page
 * comes from the core plugin's Access class.
 *
 * Template Name: Add Lodge
 *
 * @package Scouting_Memories_2026
 */

use ScoutingMemories\Core\Access;
use ScoutingMemories\Core\Forms\FormsGateway;
use ScoutingMemories\Core\Forms\GatewayFactory;

get_header();
?>

	<div id="primary" class="<?php echo esc_attr( Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::CONTAINER, 'mt-12' ) ); ?>">
		<main id="main">
			<?php if ( Access::can_add_lodge() ) : ?>
				<h1 class="<?php echo esc_attr( Scouting_Memories_2026_UI::H1 ); ?>"><?php the_title(); ?></h1>
				<?php echo GatewayFactory::current()->form( FormsGateway::FORM_ADD_LODGE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php else : ?>
				<?php esc_html_e( 'You do not have permissions to add a council.', 'scouting-memories-2026' ); ?>
			<?php endif; ?>
		</main><!-- #main -->
	</div>

<?php
get_footer();
