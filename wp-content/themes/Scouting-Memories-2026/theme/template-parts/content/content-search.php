<?php
/**
 * The search/category result card, ported from the old theme's content-search.php
 * (a Bootstrap card): title, "Posted on", thumbnail, and the State / Council /
 * Lodge / Camps pieces as small plain text.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Scouting_Memories_2026
 */

?>

<article id="post-<?php the_ID(); ?>" <?php post_class( Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::CONTAINER, Scouting_Memories_2026_UI::CARD ) ); ?>>
	<div class="<?php echo esc_attr( Scouting_Memories_2026_UI::CARD_BODY ); ?>">
		<header>
			<?php
			the_title(
				sprintf(
					'<h2 class="%1$s"><a class="%2$s" href="%3$s" rel="bookmark">',
					esc_attr( Scouting_Memories_2026_UI::H2 ),
					esc_attr( Scouting_Memories_2026_UI::LINK ),
					esc_url( get_permalink() )
				),
				'</a></h2>'
			);
			?>

			<?php if ( 'post' === get_post_type() ) : ?>
				<div>
					<?php scouting_memories_2026_sm_posted_on( true ); ?>
				</div>
			<?php endif; ?>
		</header>

		<?php scouting_memories_2026_post_thumbnail(); ?>

		<footer>
			<div>
				<?php scouting_memories_2026_entry_terms(); ?>
			</div>
		</footer>
	</div>
</article><!-- #post-<?php the_ID(); ?> -->
