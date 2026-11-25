<?php
/**
 * Template part for displaying a message that posts cannot be found
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Scouting_Memories_2026
 */

?>

<section>
	<header>
		<h1 class="<?php echo esc_attr( Scouting_Memories_2026_UI::H1 ); ?>"><?php esc_html_e( 'Nothing Found', 'scouting-memories-2026' ); ?></h1>
	</header>

	<div class="[&_a]:text-blue [&_a]:underline [&_a:hover]:text-blue-link-hover">
		<?php
		if ( is_home() && current_user_can( 'publish_posts' ) ) :

			printf(
				'<p class="mb-4">' . wp_kses(
					/* translators: 1: link to WP admin new post page. */
					__( 'Ready to publish your first post? <a href="%1$s">Get started here</a>.', 'scouting-memories-2026' ),
					array(
						'a' => array(
							'href' => array(),
						),
					)
				) . '</p>',
				esc_url( admin_url( 'post-new.php' ) )
			);

		elseif ( is_search() ) :
			?>

			<p class="mb-4"><?php esc_html_e( 'Sorry, but nothing matched your search terms. Please try again with some different keywords.', 'scouting-memories-2026' ); ?></p>

			<?php
		else :
			?>

			<p class="mb-4"><?php esc_html_e( 'It seems we can&rsquo;t find what you&rsquo;re looking for. Perhaps searching can help.', 'scouting-memories-2026' ); ?></p>

			<?php
		endif;
		?>
	</div>
</section>
