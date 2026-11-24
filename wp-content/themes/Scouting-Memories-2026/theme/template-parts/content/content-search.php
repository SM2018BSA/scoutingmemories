<?php
/**
 * The search/category result card, ported from the old theme's content-search.php:
 * title, posted-on chip, thumbnail, and the state/council/lodge/camp chips.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Scouting_Memories_2026
 */

?>

<article id="post-<?php the_ID(); ?>" <?php post_class( 'card card-border bg-base-100 shadow-sm mb-4' ); ?>>
	<div class="card-body">
		<header class="entry-header">
			<?php the_title( sprintf( '<h2 class="entry-title card-title text-xl"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a></h2>' ); ?>

			<?php if ( 'post' === get_post_type() ) : ?>
				<div class="entry-meta text-sm text-base-content/60">
					<?php scouting_memories_2026_sm_posted_on(); ?>
				</div><!-- .entry-meta -->
			<?php endif; ?>
		</header><!-- .entry-header -->

		<?php scouting_memories_2026_post_thumbnail(); ?>

		<footer class="entry-footer mt-2">
			<?php scouting_memories_2026_entry_terms(); ?>
		</footer><!-- .entry-footer -->
	</div>
</article><!-- #post-<?php the_ID(); ?> -->
