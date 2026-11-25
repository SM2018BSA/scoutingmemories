<?php
/**
 * The template for displaying comments, as the old theme showed them: a card
 * headed "{N} Comments" holding the comments, then a card headed "Leave a
 * reply" holding the form. Each comment is printed by
 * scouting_memories_2026_comment() in inc/template-functions.php.
 *
 * Every class WordPress prints inside the form is set here, through
 * comment_form()'s arguments: Tailwind utility classes only.
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
$smem_required  = $smem_req ? '<span>*</span>' : '';
$smem_aria_req  = $smem_req ? ' aria-required="true"' : '';

// Bootstrap's plain label and .form-control field; links are blue and underlined.
$smem_label = 'inline-block';
$smem_field = esc_attr( Scouting_Memories_2026_UI::FIELD );
$smem_area  = esc_attr( Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::FIELD, 'min-h-[calc(1.5em+0.75rem+2px)]' ) );
$smem_links = '[&_a]:text-blue [&_a]:underline [&_a:hover]:text-blue-link-hover';
$smem_card  = esc_attr( Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::CARD, 'mb-4' ) );

$smem_comment_field = '<div>'
	. '<label class="' . $smem_label . '" for="comment">' . esc_html__( 'Your comment', 'scouting-memories-2026' ) . '</label>'
	. '<textarea id="comment" name="comment" class="' . $smem_area . '" rows="5"></textarea>'
	. '</div>';

if ( is_user_logged_in() ) {
	// Members see their own picture beside the box (2 of 12 columns, from the sm screen size).
	$smem_comment_field = '<div class="-mx-3 flex flex-wrap">'
		. '<div class="w-full max-w-full shrink-0 px-3 sm:w-1/6">' . get_avatar( get_current_user_id(), 100 ) . '</div>'
		. '<div class="w-full max-w-full shrink-0 px-3 sm:w-5/6">' . $smem_comment_field . '</div>'
		. '</div>';
}

$smem_comment_form_args = array(
	'fields'               => apply_filters(
		'comment_form_default_fields', // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own filter, applied as the old theme did.
		array(
			'author' => '<label class="' . $smem_label . '" for="author">' . esc_html__( 'Your name', 'scouting-memories-2026' ) . $smem_required . ' </label>'
				. '<input type="text" id="author" name="author" class="' . $smem_field . '" value="' . esc_attr( $smem_commenter['comment_author'] ) . '"' . $smem_aria_req . '>',
			'email'  => '<label class="' . $smem_label . '" for="email">' . esc_html__( 'Your email', 'scouting-memories-2026' ) . $smem_required . '</label>'
				. '<input type="text" id="email" name="email" class="' . $smem_field . '" value="' . esc_attr( $smem_commenter['comment_author_email'] ) . '"' . $smem_aria_req . '>',
		)
	),
	'comment_field'        => $smem_comment_field,
	'comment_notes_after'  => '',
	'comment_notes_before' => '',
	'must_log_in'          => '<p class="mb-4 ' . $smem_links . '">' . sprintf(
		wp_kses(
			/* translators: %s: log-in address. */
			__( 'You must be <a href="%s">logged in</a> to post a comment.', 'scouting-memories-2026' ),
			array(
				'a' => array(
					'href' => array(),
				),
			)
		),
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own filter.
		esc_url( wp_login_url( apply_filters( 'the_permalink', get_permalink() ) ) )
	) . '</p>',
	'logged_in_as'         => '<p class="mb-4 text-center ' . $smem_links . '">(' . sprintf(
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
	'title_reply_before'   => '<div id="reply-title" class="mb-2 ' . $smem_links . '">',
	'title_reply_after'    => '</div>',
	'class_container'      => '',
	'class_form'           => '',
	'submit_field'         => '<p class="mb-4">%1$s %2$s</p>',
	'class_submit'         => Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::BTN, Scouting_Memories_2026_UI::BTN_INFO ),
	'label_submit'         => __( 'post', 'scouting-memories-2026' ),
);
?>

<div id="comments">

	<?php if ( have_comments() ) : ?>

		<div class="<?php echo $smem_card; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>">
			<div class="<?php echo esc_attr( Scouting_Memories_2026_UI::CARD_HEADER ); ?>">
				<?php
				/* translators: %s: number of comments. */
				printf( esc_html__( '%s Comments', 'scouting-memories-2026' ), esc_html( get_comments_number() ) );
				?>
			</div>

			<?php scouting_memories_2026_comment_nav( 'comment-nav-above' ); ?>

			<div class="<?php echo esc_attr( Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::CARD_BODY, '[&_.children]:mb-4 [&_.children]:pl-8' ) ); ?>">
				<?php
				wp_list_comments(
					array(
						'style'    => 'div',
						'callback' => 'scouting_memories_2026_comment',
					)
				);
				?>
			</div>
		</div>

		<?php scouting_memories_2026_comment_nav( 'comment-nav-below' ); ?>

	<?php endif; ?>

	<?php
	// If comments are closed and there are comments, leave a little note.
	if ( ! comments_open() && '0' != get_comments_number() && post_type_supports( get_post_type(), 'comments' ) ) : // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- the count comes back as a string or a number.
		?>
		<p class="mb-4"><?php esc_html_e( 'Comments are closed.', 'scouting-memories-2026' ); ?></p>
	<?php endif; ?>

	<!--Leave a reply section-->
	<div class="<?php echo $smem_card; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>">
		<div class="<?php echo esc_attr( Scouting_Memories_2026_UI::CARD_HEADER ); ?>"><?php esc_html_e( 'Leave a reply', 'scouting-memories-2026' ); ?></div>
		<div class="<?php echo esc_attr( Scouting_Memories_2026_UI::CARD_BODY ); ?>">
			<?php comment_form( $smem_comment_form_args ); ?>
		</div>
	</div>
	<!--/.Leave a reply section-->

</div><!-- #comments -->
