<?php
/**
 * Template part for displaying pages
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Scouting_Memories_2026
 */

?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

	<header>
		<?php the_title( '<h1 class="' . esc_attr( Scouting_Memories_2026_UI::H1 ) . '">', '</h1>' ); ?>
	</header>

	<?php if ( scouting_memories_2026_can_show_post_thumbnail() ) : ?>
		<div>
			<?php the_post_thumbnail(); ?>
		</div>
	<?php endif; ?>

	<div <?php scouting_memories_2026_content_class(); ?>>
		<?php
		the_content();

		wp_link_pages(
			array(
				'before' => '<div>' . esc_html__( 'Pages:', 'scouting-memories-2026' ),
				'after'  => '</div>',
			)
		);
		?>
	</div>

	<?php if ( get_edit_post_link() ) : ?>
		<footer>
			<?php
			edit_post_link(
				sprintf(
					wp_kses(
						/* translators: %s: Name of current post. Only visible to screen readers. */
						__( 'Edit <span class="sr-only">%s</span>', 'scouting-memories-2026' ),
						array(
							'span' => array(
								'class' => array(),
							),
						)
					),
					wp_kses_post( get_the_title() )
				),
				'<span>',
				'</span>',
				0,
				Scouting_Memories_2026_UI::LINK
			);
			?>
		</footer>
	<?php endif; ?>

</article><!-- #post-<?php the_ID(); ?> -->
