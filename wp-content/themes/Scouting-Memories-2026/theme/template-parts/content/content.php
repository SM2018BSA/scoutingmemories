<?php
/**
 * Template part for displaying posts, as the old theme's content.php showed
 * them: the title, the bordered "Posted on / Posted in" box, the thumbnail and
 * the full content. Nothing is printed after the content.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Scouting_Memories_2026
 */

?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<div class="<?php echo esc_attr( Scouting_Memories_2026_UI::CONTAINER ); ?>">
		<header>
			<?php
			if ( is_singular() ) :
				the_title( '<h1 class="' . esc_attr( Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::H1, 'break-words' ) ) . '">', '</h1>' );
			else :
				the_title( '<h2 class="' . esc_attr( Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::H2, 'break-words' ) ) . '"><a class="' . esc_attr( Scouting_Memories_2026_UI::LINK ) . '" href="' . esc_url( get_permalink() ) . '" rel="bookmark">', '</a></h2>' );
			endif;
			?>
		</header>

		<?php scouting_memories_2026_post_box(); ?>
	</div>

	<?php scouting_memories_2026_post_thumbnail(); ?>

	<div class="<?php echo esc_attr( Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::CONTAINER, 'my-12' ) ); ?>">
	<div class="<?php echo esc_attr( scouting_memories_2026_prose_classes() ); ?>">
		<?php
		the_content(
			sprintf(
				wp_kses(
					/* translators: %s: Name of current post. Only visible to screen readers. */
					__( 'Continue reading<span class="sr-only"> "%s"</span>', 'scouting-memories-2026' ),
					array(
						'span' => array(
							'class' => array(),
						),
					)
				),
				wp_kses_post( get_the_title() )
			)
		);

		wp_link_pages(
			array(
				'before' => '<div>' . esc_html__( 'Pages:', 'scouting-memories-2026' ),
				'after'  => '</div>',
			)
		);
		?>
	</div>
	</div>

</article><!-- #post-<?php the_ID(); ?> -->
