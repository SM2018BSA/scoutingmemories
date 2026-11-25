<?php
/**
 * Functions which enhance the theme by hooking into WordPress
 *
 * @package Scouting_Memories_2026
 */

/**
 * Add a pingback url auto-discovery header for single posts, pages, or attachments.
 */
function scouting_memories_2026_pingback_header() {
	if ( is_singular() && pings_open() ) {
		printf( '<link rel="pingback" href="%s">', esc_url( get_bloginfo( 'pingback_url' ) ) );
	}
}
add_action( 'wp_head', 'scouting_memories_2026_pingback_header' );

/**
 * Changes comment form default fields.
 *
 * @param array $defaults The default comment form arguments.
 *
 * @return array Returns the modified fields.
 */
function scouting_memories_2026_comment_form_defaults( $defaults ) {
	$comment_field = $defaults['comment_field'];

	// Adjust height of comment form.
	$defaults['comment_field'] = preg_replace( '/rows="\d+"/', 'rows="5"', $comment_field );

	return $defaults;
}
add_filter( 'comment_form_defaults', 'scouting_memories_2026_comment_form_defaults' );

/**
 * Determines whether the post thumbnail can be displayed.
 */
function scouting_memories_2026_can_show_post_thumbnail() {
	return apply_filters( 'scouting_memories_2026_can_show_post_thumbnail', ! post_password_required() && ! is_attachment() && has_post_thumbnail() );
}

/**
 * Returns the size for avatars used in the theme.
 */
function scouting_memories_2026_get_avatar_size() {
	return 60;
}

/**
 * Create the continue reading link
 *
 * @param string $more_string The string shown within the more link.
 */
function scouting_memories_2026_continue_reading_link( $more_string ) {

	if ( ! is_admin() ) {
		$continue_reading = sprintf(
			/* translators: %s: Name of current post. */
			wp_kses( __( 'Continue reading %s', 'scouting-memories-2026' ), array( 'span' => array( 'class' => array() ) ) ),
			the_title( '<span class="sr-only">"', '"</span>', false )
		);

		$more_string = '<a href="' . esc_url( get_permalink() ) . '">' . $continue_reading . '</a>';
	}

	return $more_string;
}

// Filter the excerpt more link.
add_filter( 'excerpt_more', 'scouting_memories_2026_continue_reading_link' );

// Filter the content more link.
add_filter( 'the_content_more_link', 'scouting_memories_2026_continue_reading_link' );

/**
 * Prints one comment the way the old theme did (its shape_comment()): the
 * avatar, the author's name as a heading (members link to their profile), the
 * date, "(Edit)" and the text.
 *
 * Used with wp_list_comments( 'style' => 'div' ): the outer div is left open
 * here and WordPress closes it after the comment's replies.
 *
 * @param WP_Comment $comment Comment to display.
 * @param array      $args    An array of arguments.
 * @param int        $depth   Depth of the current comment.
 */
function scouting_memories_2026_comment( $comment, $args, $depth ) {
	if ( 'trackback' === $comment->comment_type ) {
		?>
		<div id="comment-<?php comment_ID(); ?>" class="post pingback">
			<p><?php esc_html_e( 'Pingback:', 'scouting-memories-2026' ); ?> <?php comment_author_link( $comment ); ?><?php edit_comment_link( __( '(Edit)', 'scouting-memories-2026' ), ' ' ); ?></p>
		<?php
		return;
	}

	$is_member = (int) $comment->user_id > 0;
	?>
	<div id="comment-<?php comment_ID(); ?>" <?php comment_class( 'sm-comment-item', $comment ); ?>>
		<?php if ( $depth > 1 ) : ?>
			<div class="sm-comment-nest">
		<?php endif; ?>

		<div class="sm-comment">
			<?php
			if ( $is_member ) {
				echo get_avatar( $comment, 96, '', '', array( 'class' => array( 'sm-comment-avatar-member' ) ) );
			} else {
				echo '<span>' . get_avatar( $comment, 100 ) . '</span>';
			}
			?>
			<div class="sm-comment-body">
				<h5>
					<?php if ( $is_member ) : ?>
						<a href="<?php echo esc_url( home_url( '/profile/?id=' . (int) $comment->user_id ) ); ?>" class="user"><cite title="<?php esc_attr_e( 'View profile', 'scouting-memories-2026' ); ?>" class="fn"><?php echo esc_html( get_comment_author( $comment ) ); ?></cite></a>
					<?php else : ?>
						<span class="user"><cite class="fn"><?php echo wp_kses_post( get_comment_author_link( $comment ) ); ?></cite></span>
					<?php endif; ?>
				</h5>
				<span class="sm-comment-date">
					<a href="<?php echo esc_url( get_comment_link( $comment ) ); ?>"><time datetime="<?php echo esc_attr( get_comment_time( 'c' ) ); ?>"><?php echo esc_html( get_comment_date( '', $comment ) ); ?></time></a>
					<?php edit_comment_link( __( '(Edit)', 'scouting-memories-2026' ), ' ' ); ?>
				</span>
				<?php if ( '0' === $comment->comment_approved ) : ?>
					<em><?php esc_html_e( 'Your comment is awaiting moderation.', 'scouting-memories-2026' ); ?></em>
					<br />
				<?php endif; ?>
				<p><?php echo wp_kses_post( get_comment_text( $comment ) ); ?></p>
			</div>
		</div>

		<?php if ( $depth > 1 ) : ?>
			</div>
		<?php endif; ?>
	<?php
}
