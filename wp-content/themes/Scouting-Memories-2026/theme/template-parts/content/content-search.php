<?php
/**
 * The search/category result card, ported from the old theme's content-search.php:
 * title, "Posted on", thumbnail, and the State / Council / Lodge / Camps pieces.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Scouting_Memories_2026
 */

?>

<article id="post-<?php the_ID(); ?>" <?php post_class( 'sm-container sm-card sm-result-card' ); ?>>
	<div class="sm-card-body">
		<header class="entry-header">
			<?php the_title( sprintf( '<h2 class="entry-title"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a></h2>' ); ?>

			<?php if ( 'post' === get_post_type() ) : ?>
				<div class="sm-btn-group-sm">
					<?php scouting_memories_2026_sm_posted_on(); ?>
				</div>
			<?php endif; ?>
		</header><!-- .entry-header -->

		<?php scouting_memories_2026_post_thumbnail(); ?>

		<div class="sm-result-footer">
			<div class="sm-btn-group-sm">
				<?php scouting_memories_2026_entry_terms(); ?>
			</div>
		</div>
	</div>
</article><!-- #post-<?php the_ID(); ?> -->
