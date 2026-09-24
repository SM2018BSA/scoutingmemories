<?php
/**
 * Custom template tags for this theme
 *
 * Eventually, some of the functionality here could be replaced by core features.
 *
 * @package ScoutingMemories
 */

if ( ! function_exists( 'scoutingmemories_posted_on' ) ) :
	/**
	 * Prints HTML with meta information for the current post-date/time.
	 */
	function scoutingmemories_posted_on() {
		$time_string = '<time class="entry-date published updated" datetime="%1$s">%2$s</time>';
		$last_updated = '';
		if ( get_the_time( 'U' ) !== get_the_modified_time( 'U' ) ) {
			$time_string  = '<time class="entry-date published" datetime="%1$s">%2$s</time>';
			$last_updated = '<time class="updated" datetime="%3$s">%4$s</time>';
		}

		$time_string = sprintf(
			$time_string,
			esc_attr( get_the_date( DATE_W3C ) ),
			esc_html( get_the_date() ),
			esc_attr( get_the_modified_date( DATE_W3C ) ),
			esc_html( get_the_modified_date() )
		);

		$last_updated = sprintf(
			$last_updated,
			esc_attr( get_the_date( DATE_W3C ) ),
			esc_html( get_the_date() ),
			esc_attr( get_the_modified_date( DATE_W3C ) ),
			esc_html( get_the_modified_date() )
		);

		$posted_on  = sprintf(
		/* translators: %s: post date. */
			esc_html_x( 'Posted on %s', 'post date', 'scoutingmemories' ),
			'<a href="' . esc_url( get_permalink() ) . '" rel="bookmark">' . $time_string . '</a>'
		);
		$updated_on = sprintf(
		/* translators: %s: post date. */
			esc_html_x( 'Last Updated %s', 'post date', 'scoutingmemories' ),
			'<a href="' . esc_url( get_permalink() ) . '" rel="bookmark">' . $last_updated . '</a>'
		);


		echo '<div class="posted-on btn">' . $posted_on . '</div> '; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		//echo '<div class="posted-on btn">' . $updated_on . '</div> '; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

	}
endif;

if ( ! function_exists( 'scoutingmemories_posted_by' ) ) :
	/**
	 * Prints HTML with meta information for the current author.
	 */
	function scoutingmemories_posted_by() {

	    /*
		$byline = sprintf(
		/* translators: %s: post author.
			esc_html_x( 'by %s', 'post author', 'scoutingmemories' ),
			' <a class="url fn n" href="' . esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ) . '">' . esc_html( get_the_author() ) . '</a> '
		);

		echo '<div class="byline btn"> ' . $byline . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        */


	}
endif;

if ( ! function_exists( 'scoutingmemories_entry_footer' ) ) :
	/**
	 * Prints HTML with meta information for the categories, tags and comments.
	 */
	function scoutingmemories_entry_footer() {
		// Hide category and tag text for pages.
		if ( 'post' === get_post_type() ) {


			/* translators: used between list items, there is a space after the comma */
//			$tags_list = get_the_tag_list( '', esc_html_x( ', ', 'list item separator', 'scoutingmemories' ) );
//			if ( $tags_list ) {
//				/* translators: 1: list of tags. */
//				printf( '<div class="tags-links btn">' . esc_html__( 'Tagged %1$s', 'scoutingmemories' ) . '</div>', $tags_list ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
//			}

			$post_id = get_the_ID();
			$thePost = new Post( $post_id );


			if ( is_countable( $thePost->state_val )   ) {
				echo( '<div class="cat-links btn text-left">State: ' . $thePost->say_states() . '</div>' );
			}


			if ( is_countable( $thePost->council )   ) {
				echo( '<div class="cat-links btn text-left">Council: ' . $thePost->say_councils() . '</div>' );
            }

			if ( is_countable( $thePost->lodge )   ) {
				echo( '<div class="cat-links btn text-left">Lodge: ' . $thePost->say_lodges() . '</div>' );
			}

			if ( is_countable( $thePost->camp )  ) {
				echo( '<div class="cat-links btn text-left">Camps: ' . $thePost->say_camps() . '</div>' );
			}







		}

		if ( ! is_single() && ! post_password_required() && ( comments_open() || get_comments_number() ) ) {
			echo '<div class="comments-link btn">';
			comments_popup_link(
				sprintf(
					wp_kses(
					/* translators: %s: post title */
						__( 'Leave a Comment', 'scoutingmemories' ),
						array(
							'span' => array(
								'class' => array(),
							),
						)
					),
					wp_kses_post( get_the_title() )
				)
			);
			echo '</div>';
		}

		edit_post_link(
			sprintf(
				wp_kses(
				/* translators: %s: Name of current post. Only visible to screen readers */
					__( 'Edit', 'scoutingmemories' ),
					array(
						'span' => array(
							'class' => array(),
						),
					)
				),
				wp_kses_post( get_the_title() )
			),
			'<div class="edit-link btn">',
			'</div>'
		);
	}
endif;

if ( ! function_exists( 'scoutingmemories_post_thumbnail' ) ) :
	/**
	 * Displays an optional post thumbnail.
	 *
	 * Wraps the post thumbnail in an anchor element on index views, or a div
	 * element when on single views.
	 */
	function scoutingmemories_post_thumbnail() {
		if ( post_password_required() || is_attachment() || ! has_post_thumbnail() ) {
			return;
		}

		if ( is_singular() ) :
			?>

            <div class="post-thumbnail">
				<?php the_post_thumbnail(); ?>
            </div><!-- .post-thumbnail -->

		<?php else : ?>

            <a class="post-thumbnail" href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1">
				<?php
				the_post_thumbnail(
					'post-thumbnail',
					array(
						'alt' => the_title_attribute(
							array(
								'echo' => false,
							)
						),
					)
				);
				?>
            </a>

		<?php
		endif; // End is_singular().
	}
endif;

if ( ! function_exists( 'wp_body_open' ) ) :
	/**
	 * Shim for sites older than 5.2.
	 *
	 * @link https://core.trac.wordpress.org/ticket/12563
	 */
	function wp_body_open() {
		do_action( 'wp_body_open' );
	}
endif;







if ( !function_exists( 'shape_comment' ) ) :

	/**
	 * Template for comments and pingbacks.
	 *
	 * Used as a callback by wp_list_comments() for displaying the comments.
	 *
	 * @since Shape 1.0
	 */
	function shape_comment( $comment, $args, $depth ) {
		$GLOBALS[ 'comment' ] = $comment;
		switch ( $comment->comment_type ) :
			// case 'pingback' :
			case 'trackback' :
				?>
                <div class="post pingback">
                    <p><?php _e( 'Pingback:', 'shape' ); ?> <?php comment_author_link(); ?><?php edit_comment_link( __( '(Edit)', 'shape' ), ' ' ); ?></p>
                </div>
				<?php
				break;
			default :
				?>
				<?php
				if ( $depth > 1 ) {
					echo '<div class="media d-block d-md-flex ml-5">';
				}
				?>
                <div class="media d-block d-md-flex mt-4" <?php comment_class(); ?> id="li-comment-<?php comment_ID(); ?>">
					<?php if($comment->user_id) { ?>

						<?php $default = $alt = $comment_id = '';
						echo get_avatar( $comment, null, $default, $alt, array( 'class' => array( 'd-flex', 'mb-3', 'mx-auto' ) ) ); ?>

					<?php } else { ?>
                        <span>
                                    <?php echo get_avatar( $comment, 100 ); ?>
                            </span>
					<?php } ?>
                    <div class="media-body text-center text-md-left ml-md-3 ml-0">
                        <h5 class="mt-0 font-weight-bold">
							<?php if($comment->user_id) { ?>

                                <a href="<?php echo get_home_url().'/profile/?id='.$comment->user_id ?>" class="user"><?php printf( __( '%s', 'shape' ), sprintf( '<cite data-toggle="tooltip" data-placement="top" title="View profile" class="fn">%s</cite>', get_comment_author_link() ) ); ?></a>
							<?php } else { ?>
                                <a class="user"><?php printf( __( '%s', 'shape' ), sprintf( '<cite class="fn">%s</cite>', get_comment_author_link() ) ); ?></a>
							<?php } ?>

							<?php comment_reply_link( array_merge( $args, array( 'depth' => $depth, 'max_depth' => $args[ 'max_depth' ], 'add_below' => 'li-comment', 'reply_text' => '<i class="fa fa-reply pull-right"></i>' ) ), $comment_id ); ?>

                        </h5>
                        <h7>
                            <i class="fa fa-clock-o"></i> <a href="<?php echo esc_url( get_comment_link( $comment->comment_ID ) ); ?>"><time pubdate datetime="<?php comment_time( 'c' ); ?>">
									<?php
									/* translators: 1: date, 2: time */
									printf( __( '%1$s', 'shape' ), get_comment_date(), get_comment_time() );
									?>
                                </time></a>                             <?php edit_comment_link( __( '(Edit)', 'shape' ), ' ' ); ?>
                        </h7>
						<?php if ( $comment->comment_approved == '0' ) : ?>
                            <em><?php _e( 'Your comment is awaiting moderation.', 'shape' ); ?></em>
                            <br />
						<?php endif; ?>
                        <p><?php echo get_comment_text(); ?></p>
						<?php
						if($comment->comment_parent == 0){
							$comment_id = $comment->comment_ID;
						}else {
							$comment_id = $comment->comment_parent;
						}
						?>

                    </div>
                </div>

				<?php
				if ( $depth > 1 ) {
					echo '</div>';
				}
				?>


				<?php
				break;
		endswitch;
	}

endif; // ends check for shape_comment()
?>