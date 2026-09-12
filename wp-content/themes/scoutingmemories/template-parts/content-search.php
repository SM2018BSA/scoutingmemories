<?php
/**
 * Template part for displaying results in search pages
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package ScoutingMemories
 */

?>

<article id="post-<?php the_ID(); ?>" <?php post_class( 'container card' ); ?> >
    <div class="card-body">
        <header class="entry-header  ">
			<?php the_title( sprintf( '<h2 class="entry-title card-title "><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a></h2>' ); ?>

			<?php if ( 'post' === get_post_type() ) : ?>


                <div class="entry-meta btn-group-sm">
					<?php
					scoutingmemories_posted_on();
					//scoutingmemories_posted_by();
					?>
                </div><!-- .entry-meta -->
			<?php endif; ?>
        </header><!-- .entry-header -->

		<?php scoutingmemories_post_thumbnail(); ?>

        <div class="entry-summary">
			<?php //the_excerpt(); ?>
        </div><!-- .entry-summary -->

        <footer class="entry-footer ">
            <div class="btn-group-sm">
			    <?php scoutingmemories_entry_footer(); ?>
            </div>
        </footer><!-- .entry-footer -->
    </div>
</article><!-- #post-<?php the_ID(); ?> -->
