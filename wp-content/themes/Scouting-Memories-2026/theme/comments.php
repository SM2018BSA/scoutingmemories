<?php
/**
 * The template for displaying comments, as the old theme showed them: a card
 * headed "{N} Comments" holding the comments, then a card headed "Leave a
 * reply" holding the form. Each comment is printed by
 * scouting_memories_2026_comment() in inc/template-functions.php.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Scouting_Memories_2026
 */

/*
 * If the current post is protected by a password and the visitor has not yet
 * entered the password we will return early without loading the comments.
 */
if ( post_password_required() ) {
	return;
}

$smem_req       = (bool) get_option( 'require_name_email' );
$smem_commenter = wp_get_current_commenter();
$smem_required  = $smem_req ? '<span class="required">*</span>' : '';
$smem_aria_req  = $smem_req ? ' aria-required="true"' : '';

$smem_comment_field = '<div class="sm-form-group">'
	. '<label for="comment">' . esc_html__( 'Your comment', 'scouting-memories-2026' ) . '</label>'
	. '<textarea id="comment" name="comment" class="sm-form-control" rows="5"></textarea>'
	. '</div>';

if ( is_user_logged_in() ) {
	// Members see their own picture beside the box.
	$smem_comment_field = '<div class="sm-comment-row">'
		. '<div class="sm-comment-row-avatar">' . get_avatar( get_current_user_id(), 100 ) . '</div>'
		. '<div class="sm-comment-row-field">' . $smem_comment_field . '</div>'
		. '</div>';
}

$smem_comment_form_args = array(
	'fields'               => apply_filters(
		'comment_form_default_fields', // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own filter, applied as the old theme did.
		array(
			'author' => '<label for="author">' . esc_html__( 'Your name', 'scouting-memories-2026' ) . $smem_required . ' </label>'
				. '<input type="text" id="author" name="author" class="sm-form-control" value="' . esc_attr( $smem_commenter['comment_author'] ) . '"' . $smem_aria_req . '>',
			'email'  => '<label for="email">' . esc_html__( 'Your email', 'scouting-memories-2026' ) . $smem_required . '</label>'
				. '<input type="text" id="email" name="email" class="sm-form-control" value="' . esc_attr( $smem_commenter['comment_author_email'] ) . '"' . $smem_aria_req . '>',
		)
	),
	'comment_field'        => $smem_comment_field,
	'comment_notes_after'  => '',
	'comment_notes_before' => '',
	'logged_in_as'         => '<p class="sm-logged-in-as">(' . sprintf(
		wp_kses(
			/* translators: 1: profile address, 2: the member's name, 3: log-out address. */
			__( 'Logged in as <a href="%1$s">%2$s</a>. <a href="%3$s" title="Log out of this account">Log out?</a>', 'scouting-memories-2026' ),
			array(
				'a' => array(
					'href'  => array(),
					'title' => array(),
				),
			)
		),
		esc_url( admin_url( 'profile.php' ) ),
		esc_html( wp_get_current_user()->display_name ),
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own filter.
		esc_url( wp_logout_url( apply_filters( 'the_permalink', get_permalink() ) ) )
	) . ')</p>',
	'title_reply'          => '',
	'title_reply_before'   => '<div id="reply-title" class="comment-reply-title">',
	'title_reply_after'    => '</div>',
	'class_submit'         => 'btn btn-info btn-md',
	'label_submit'         => __( 'post', 'scouting-memories-2026' ),
);
?>

<div id="comments" class="sm-comments">

	<?php if ( have_comments() ) : ?>

		<div class="sm-card card-comments">
			<div class="sm-card-header">
				<?php
				/* translators: %s: number of comments. */
				printf( esc_html__( '%s Comments', 'scouting-memories-2026' ), esc_html( get_comments_number() ) );
				?>
			</div>

			<?php scouting_memories_2026_comment_nav( 'comment-nav-above' ); ?>

			<div class="sm-card-body">
				<?php
				wp_list_comments(
					array(
						'style'    => 'div',
						'callback' => 'scouting_memories_2026_comment',
					)
				);
				?>
			</div><!-- .commentlist -->
		</div>

		<?php scouting_memories_2026_comment_nav( 'comment-nav-below' ); ?>

	<?php endif; ?>

	<?php
	// If comments are closed and there are comments, leave a little note.
	if ( ! comments_open() && '0' != get_comments_number() && post_type_supports( get_post_type(), 'comments' ) ) : // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- the count comes back as a string or a number.
		?>
		<p class="nocomments"><?php esc_html_e( 'Comments are closed.', 'scouting-memories-2026' ); ?></p>
	<?php endif; ?>

	<!--Leave a reply section-->
	<div class="sm-card">
		<div class="sm-card-header"><?php esc_html_e( 'Leave a reply', 'scouting-memories-2026' ); ?></div>
		<div class="sm-card-body">
			<?php comment_form( $smem_comment_form_args ); ?>
		</div>
	</div>
	<!--/.Leave a reply section-->

</div><!-- #comments -->
