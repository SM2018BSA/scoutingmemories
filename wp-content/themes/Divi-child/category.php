<?php
/**
 * A Simple Category Template
 */

get_header(); ?>

	<section id="primary" class="site-content category" >

		<div class="et_pb_section  et_pb_section_1 et_section_regular">



			<div class=" et_pb_row et_pb_row_1">

				<div id="content" role="main">
					<?php
					// Check if there are any posts to display
					if ( have_posts() ) : ?>

						<header class="category-header">
							<?php
							// Since this template will only be used for Design category
							// we can add category title and description manually.
							// or even add images or change the layout
							?>

							<h1 class="archive-title">
								<?php

								if ( $term_ids = get_ancestors( get_queried_object_id(), 'category', 'taxonomy' ) ) {
									$crumbs = [];

									foreach ( $term_ids as $term_id ) {
										$term = get_term( $term_id, 'category' );

										if ( $term && ! is_wp_error( $term ) ) {
											$crumbs[] = sprintf( '<a href="%s">%s</a>', esc_url( get_term_link( $term ) ), esc_html( $term->name ) );
										}
									}

									echo implode( ' | ', array_reverse( $crumbs ) );
									echo ' | ';
								}
								?>
								<?php

								$category = get_category( get_query_var( 'cat' ) );
								$cat_id = $category->cat_ID;
								$category_link = get_category_link( $cat_id );

								echo '<a href="' .  $category_link . '" target="_self" >';
									single_cat_title();
								echo '</a>';
								?>
							</h1>

						</header>
						<hr />
						<?php

// The Loop
						while ( have_posts() ) : the_post(); ?>
							<h2><a href="<?php the_permalink() ?>" rel="bookmark" title="Permanent Link to <?php the_title_attribute(); ?>"><?php the_title(); ?></a></h2>
							<p class="post-meta"> by <span class="author vcard"><?php the_author_posts_link() ?> | <span class="published"><?php the_time('F jS, Y') ?></span>



							<div class="entry">
								<?php the_excerpt(); ?>
								<a class="et_pb_button  et_pb_button_1 et_pb_module et_pb_bg_layout_light"
								      href="<?php the_permalink() ?>" rel="bookmark" title="Permanent Link to <?php the_title_attribute(); ?>">Read More</a>
								<?php /*<p class="postmetadata"><?php
									comments_popup_link( 'No comments yet', '1 comment', '% comments', 'comments-link', 'Comments closed');
									?></p> */?>
							</div>
							<hr style="border:none" />

						<?php endwhile; // End Loop

					else: ?>
						<p>Sorry, no posts matched your criteria.</p>
					<?php endif; ?>
				</div>

			</div> <!-- .et_pb_row -->

		</div>



	</section>


<?php get_footer(); ?>