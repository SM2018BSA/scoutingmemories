<?php
/**
 * Single posts (memories): the post content followed by the Timeline and Meta
 * boxes — state/council/camp/lodge and dates from the core plugin's Post class,
 * the archive fields from Advanced Custom Fields — then comments, and the
 * "Related Info" widget area on museum posts. Ported from the old theme.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#single-post
 *
 * @package Scouting_Memories_2026
 */

$smem_the_post = class_exists( 'Post' ) ? new Post( get_the_ID() ) : null;

get_header();
?>

	<div id="primary" class="content-area sm-container sm-edge mt-12">
		<main id="main" class="site-main px-3">
			<?php
			while ( have_posts() ) :
				the_post();
				?>

				<div class="post-content sm-container"><?php get_template_part( 'template-parts/content/content', 'single' ); ?></div>

				<?php if ( $smem_the_post ) : ?>
					<div class="post-meta sm-container">
						<div class="sm-meta-row">

							<ul class="sm-meta-list">
								<li class="sm-meta-head"><?php esc_html_e( 'Timeline', 'scouting-memories-2026' ); ?></li>
								<li class="sm-meta-item"><strong>State:</strong> <?php echo $smem_the_post->say_states(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></li>
								<li class="sm-meta-item"><strong>Council:</strong> <?php echo $smem_the_post->say_councils(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></li>
								<li class="sm-meta-item"><strong>Camp:</strong> <?php echo $smem_the_post->say_camps(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></li>
								<li class="sm-meta-item"><strong>Lodge:</strong> <?php echo $smem_the_post->say_lodges(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></li>
								<li class="sm-meta-item"><strong>Start Date:</strong> <span class="small"><?php echo esc_html( (string) $smem_the_post->start_date ); ?></span></li>
								<li class="sm-meta-item"><strong>End Date:</strong> <span class="small"><?php echo esc_html( (string) $smem_the_post->end_date ); ?></span></li>
							</ul>

							<?php if ( function_exists( 'the_field' ) ) : ?>
								<ul class="sm-meta-list">
									<li class="sm-meta-head"><?php esc_html_e( 'Meta', 'scouting-memories-2026' ); ?></li>
									<li class="sm-meta-item"><strong>Publisher of Digital:</strong> <?php the_field( 'publisher_of_digital' ); ?></li>
									<li class="sm-meta-item"><strong>Date of Original:</strong> <?php the_field( 'date_of_original' ); ?></li>
									<li class="sm-meta-item"><strong>Date of Digital:</strong> <?php the_field( 'date_of_digital' ); ?></li>
									<li class="sm-meta-item"><strong>Identifier:</strong> <?php the_field( 'identifier' ); ?></li>
									<li class="sm-meta-item"><strong>Subject:</strong> <?php the_field( 'meta_subject' ); ?></li>
									<li class="sm-meta-item"><strong>Location:</strong> <?php the_field( 'meta_location' ); ?></li>
									<li class="sm-meta-item"><strong>Physical Description:</strong> <?php the_field( 'meta_physical_description' ); ?></li>
								</ul>
							<?php endif; ?>

						</div>
					</div>
				<?php endif; ?>

				<div class="post-comments mt-12">
					<?php
					if ( comments_open() || get_comments_number() ) {
						comments_template();
					}
					?>
				</div>

			<?php endwhile; ?>
		</main><!-- #main -->

		<?php
		// The "Related Info" widget area shows on museum posts only, as before.
		$smem_show_related = false;
		foreach ( wp_get_post_categories( get_the_ID() ) as $smem_cat_id ) {
			$smem_cat = get_category( $smem_cat_id );
			if ( $smem_cat && ! is_wp_error( $smem_cat ) && 'museums' === $smem_cat->slug ) {
				$smem_show_related = true;
				break;
			}
		}
		if ( $smem_show_related && function_exists( 'dynamic_sidebar' ) ) {
			dynamic_sidebar( 'Related Info' );
		}
		?>

	</div><!-- #primary -->

<?php
get_footer();
