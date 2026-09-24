<?php
/**
 * Template part for displaying posts
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package ScoutingMemories
 */

?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<div class="container">
        <header class="entry-header">
            <?php
            if ( is_singular() ) :
                the_title( '<h1 class="entry-title">', '</h1>' );
            else :
                the_title( '<h2 class="entry-title"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">', '</a></h2>' );
            endif;
?>

        </header><!-- .entry-header -->
        <footer class="entry-footer border p-3 mb-3">
            <?php
            if ( 'post' === get_post_type() ) :
	            ?>
                <div class="entry-meta">
		            <?php
		            scoutingmemories_posted_on();
		            scoutingmemories_posted_by();

		            /* translators: used between list items, there is a space after the comma */
		            $categories_list = get_the_category_list( esc_html__( ', ', 'scoutingmemories' ) );
		            if ( $categories_list ) {
			            /* translators: 1: list of categories. */
			            printf( '<div class="cat-links btn">' . esc_html__( 'Posted in %1$s', 'scoutingmemories' ) . '</div>', $categories_list ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		            }


		            ?>
                </div><!-- .entry-meta -->
            <?php endif;




           // scoutingmemories_entry_footer();
            ?>
        </footer><!-- .entry-footer -->
    </div>

	<?php scoutingmemories_post_thumbnail(); ?>

	<div class="entry-content container mt-5 mb-5">
		<?php
		the_content(
			sprintf(
				wp_kses(
					/* translators: %s: Name of current post. Only visible to screen readers */
					__( 'Continue reading<span class="screen-reader-text"> "%s"</span>', 'scoutingmemories' ),
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
				'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'scoutingmemories' ),
				'after'  => '</div>',
			)
		);
		?>
	</div><!-- .entry-content -->


</article><!-- #post-<?php the_ID(); ?> -->
