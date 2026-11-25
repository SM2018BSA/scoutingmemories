<?php
/**
 * Single posts (memories): the post content followed by the Timeline and Meta
 * boxes (state/council/camp/lodge and dates from the core plugin's Post class,
 * the archive fields from Advanced Custom Fields), then comments, and the
 * "Related Info" widget area on museum posts. Ported from the old theme: the two
 * boxes are Bootstrap list-groups, side by side from the md screen size.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#single-post
 *
 * @package Scouting_Memories_2026
 */

$smem_the_post = class_exists( 'Post' ) ? new Post( get_the_ID() ) : null;

// One list (Bootstrap's .list-group in a half-width column), its heading and its lines.
$smem_list       = 'm-0 flex w-full max-w-full min-w-0 shrink-0 list-none flex-col rounded-md pr-3 pl-0 break-words md:w-1/2 [&_a]:text-blue [&_a]:underline [&_a:hover]:text-blue-link-hover';
$smem_list_head  = 'border border-line bg-ink/[.03] p-4 font-normal uppercase';
$smem_list_first = 'border border-black/[.125] bg-white px-4 py-2';
$smem_list_line  = 'border border-t-0 border-black/[.125] bg-white px-4 py-2 last:rounded-b-md';

get_header();
?>

	<div id="primary" class="<?php echo esc_attr( Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::CONTAINER, 'mt-12 pt-0' ) ); ?>">
		<main id="main" class="<?php echo esc_attr( Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::CONTAINER_FLUID, 'pt-0' ) ); ?>">
			<?php
			while ( have_posts() ) :
				the_post();
				?>

				<div class="-mx-3 flex flex-wrap">
					<div class="w-full max-w-full min-w-0 flex-1 px-3">

						<div class="<?php echo esc_attr( Scouting_Memories_2026_UI::CONTAINER ); ?>"><?php get_template_part( 'template-parts/content/content', 'single' ); ?></div>

						<?php if ( $smem_the_post ) : ?>
							<div class="<?php echo esc_attr( Scouting_Memories_2026_UI::CONTAINER ); ?>">
								<div class="m-0 flex w-full flex-wrap">

									<ul class="<?php echo esc_attr( $smem_list ); ?>">
										<li class="<?php echo esc_attr( $smem_list_head ); ?>"><?php esc_html_e( 'Timeline', 'scouting-memories-2026' ); ?></li>
										<li class="<?php echo esc_attr( $smem_list_first ); ?>"><strong>State:</strong> <?php echo $smem_the_post->say_states(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></li>
										<li class="<?php echo esc_attr( $smem_list_line ); ?>"><strong>Council:</strong> <?php echo $smem_the_post->say_councils(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></li>
										<li class="<?php echo esc_attr( $smem_list_line ); ?>"><strong>Camp:</strong> <?php echo $smem_the_post->say_camps(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></li>
										<li class="<?php echo esc_attr( $smem_list_line ); ?>"><strong>Lodge:</strong> <?php echo $smem_the_post->say_lodges(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></li>
										<li class="<?php echo esc_attr( $smem_list_line ); ?>"><strong>Start Date:</strong> <span class="text-[0.875em]"><?php echo esc_html( (string) $smem_the_post->start_date ); ?></span></li>
										<li class="<?php echo esc_attr( $smem_list_line ); ?>"><strong>End Date:</strong> <span class="text-[0.875em]"><?php echo esc_html( (string) $smem_the_post->end_date ); ?></span></li>
									</ul>

									<?php if ( function_exists( 'the_field' ) ) : ?>
										<ul class="<?php echo esc_attr( $smem_list ); ?>">
											<li class="<?php echo esc_attr( $smem_list_head ); ?>"><?php esc_html_e( 'Meta', 'scouting-memories-2026' ); ?></li>
											<li class="<?php echo esc_attr( $smem_list_first ); ?>"><strong>Publisher of Digital:</strong> <?php the_field( 'publisher_of_digital' ); ?></li>
											<li class="<?php echo esc_attr( $smem_list_line ); ?>"><strong>Date of Original:</strong> <?php the_field( 'date_of_original' ); ?></li>
											<li class="<?php echo esc_attr( $smem_list_line ); ?>"><strong>Date of Digital:</strong> <?php the_field( 'date_of_digital' ); ?></li>
											<li class="<?php echo esc_attr( $smem_list_line ); ?>"><strong>Identifier:</strong> <?php the_field( 'identifier' ); ?></li>
											<li class="<?php echo esc_attr( $smem_list_line ); ?>"><strong>Subject:</strong> <?php the_field( 'meta_subject' ); ?></li>
											<li class="<?php echo esc_attr( $smem_list_line ); ?>"><strong>Location:</strong> <?php the_field( 'meta_location' ); ?></li>
											<li class="<?php echo esc_attr( $smem_list_line ); ?>"><strong>Physical Description:</strong> <?php the_field( 'meta_physical_description' ); ?></li>
										</ul>
									<?php endif; ?>

								</div>
							</div>
						<?php endif; ?>

						<div class="-mx-3 mt-12 flex flex-wrap">
							<div class="w-full max-w-full shrink-0 px-3">
								<?php
								if ( comments_open() || get_comments_number() ) {
									comments_template();
								}
								?>
							</div>
						</div>

					</div>
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
