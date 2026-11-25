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

	<div id="primary" class="<?php echo esc_attr( Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::CONTAINER, 'mt-12' ) ); ?>">
		<main id="main">

			<section>
				<header>
					<h1 class="<?php echo esc_attr( Scouting_Memories_2026_UI::H1 ); ?>"><?php esc_html_e( 'Oops! That page can&rsquo;t be found.', 'scouting-memories-2026' ); ?></h1>
				</header>

				<div <?php scouting_memories_2026_content_class(); ?>>
					<p><?php esc_html_e( 'It looks like nothing was found at this location. Maybe try one of the links below or a search?', 'scouting-memories-2026' ); ?></p>
					<?php
					get_search_form();

					// Same helpers the old theme's 404 offered.
					the_widget( 'WP_Widget_Recent_Posts' );
					?>

					<div>
						<h2><?php esc_html_e( 'Most Used Categories', 'scouting-memories-2026' ); ?></h2>
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
					</div>
				</div>
			</section>

		</main><!-- #main -->
	</div><!-- #primary -->

<?php
get_footer();
