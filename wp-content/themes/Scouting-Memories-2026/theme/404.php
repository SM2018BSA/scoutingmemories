<?php
/**
 * The template for displaying 404 pages (not found)
 *
 * @link https://codex.wordpress.org/Creating_an_Error_404_Page
 *
 * @package Scouting_Memories_2026
 */

get_header();
?>

	<section id="primary">
		<main id="main">

			<div>
				<header class="page-header">
					<h1 class="page-title"><?php esc_html_e( 'Page Not Found', 'scouting-memories-2026' ); ?></h1>
				</header><!-- .page-header -->

				<div <?php scouting_memories_2026_content_class( 'page-content' ); ?>>
					<p><?php esc_html_e( 'This page could not be found. It might have been removed or renamed, or it may never have existed.', 'scouting-memories-2026' ); ?></p>
					<?php
					get_search_form();

					// Same helpers the old theme's 404 offered.
					the_widget( 'WP_Widget_Recent_Posts' );
					?>

					<div class="widget widget_categories mt-6">
						<h2 class="widget-title text-lg"><?php esc_html_e( 'Most Used Categories', 'scouting-memories-2026' ); ?></h2>
						<ul>
							<?php
							wp_list_categories(
								array(
									'orderby'    => 'count',
									'order'      => 'DESC',
									'show_count' => 1,
									'title_li'   => '',
									'number'     => 10,
								)
							);
							?>
						</ul>
					</div><!-- .widget -->
				</div><!-- .page-content -->
			</div>

		</main><!-- #main -->
	</section><!-- #primary -->

<?php
get_footer();
