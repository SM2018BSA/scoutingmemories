<?php
/**
 * Custom template tags for this theme
 *
 * Every piece printed here carries Tailwind utility classes only (see docs/TAILWIND-ONLY.md);
 * the sizes and spacing are the old Bootstrap site's.
 *
 * @package Scouting_Memories_2026
 */

if ( ! function_exists( 'scouting_memories_2026_text_button_classes' ) ) :
	/**
	 * The classes of a plain text button (Bootstrap's bare .btn: no background, no line), used
	 * for "Posted on", "Posted in" and the State / Council / Lodge / Camps pieces. Links inside
	 * it are blue and underlined.
	 *
	 * @param bool $small The small size (inside a result card) instead of the normal one.
	 */
	function scouting_memories_2026_text_button_classes( $small = false ) {
		return Scouting_Memories_2026_UI::classes(
			$small ? Scouting_Memories_2026_UI::BTN_SM : Scouting_Memories_2026_UI::BTN,
			Scouting_Memories_2026_UI::BTN_PLAIN,
			'max-w-full break-words [&_a]:text-blue [&_a]:underline [&_a:hover]:text-blue-link-hover'
		);
	}
endif;

if ( ! function_exists( 'scouting_memories_2026_prose_classes' ) ) :
	/**
	 * The classes of a post's body (text an editor typed): Tailwind Typography, set to the old
	 * site's Bootstrap text look (spaced paragraphs, blue underlined links, Roboto Slab headings).
	 */
	function scouting_memories_2026_prose_classes() {
		return 'prose max-w-none prose-headings:mt-0 prose-headings:mb-2 prose-headings:font-slab prose-headings:leading-tight prose-headings:font-medium prose-p:mt-0 prose-p:mb-4 prose-p:leading-normal prose-a:font-normal prose-a:text-blue prose-a:underline prose-a:hover:text-blue-link-hover prose-ul:mt-0 prose-ul:mb-4 prose-ul:pl-8 prose-ol:mt-0 prose-ol:mb-4 prose-ol:pl-8 prose-li:my-0 prose-li:leading-normal prose-figure:mt-0 prose-figure:mb-4 prose-img:my-0';
	}
endif;

if ( ! function_exists( 'scouting_memories_2026_post_thumbnail' ) ) :
	/**
	 * Displays an optional post thumbnail, wrapping the post thumbnail in an
	 * anchor element except when viewing a single post.
	 */
	function scouting_memories_2026_post_thumbnail() {
		if ( ! scouting_memories_2026_can_show_post_thumbnail() ) {
			return;
		}

		if ( is_singular() ) :
			?>

			<div>
				<?php the_post_thumbnail(); ?>
			</div>

			<?php
		else :
			?>

			<a href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1">
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

if ( ! function_exists( 'scouting_memories_2026_content_class' ) ) :
	/**
	 * Displays the class names for the post content wrapper.
	 *
	 * This allows us to add Tailwind Typography’s modifier classes throughout
	 * the theme without repeating them in multiple files. (They can be edited
	 * at the top of the `../functions.php` file via the
	 * SCOUTING_MEMORIES_2026_TYPOGRAPHY_CLASSES constant.)
	 *
	 * Based on WordPress core’s `body_class` and `get_body_class` functions.
	 *
	 * @param string|string[] $classes Space-separated string or array of class
	 *                                 names to add to the class list.
	 */
	function scouting_memories_2026_content_class( $classes = '' ) {
		$all_classes = array( $classes, SCOUTING_MEMORIES_2026_TYPOGRAPHY_CLASSES );

		foreach ( $all_classes as &$class_groups ) {
			if ( ! empty( $class_groups ) ) {
				if ( ! is_array( $class_groups ) ) {
					$class_groups = preg_split( '#\s+#', $class_groups );
				}
			} else {
				// Ensure that we always coerce class to being an array.
				$class_groups = array();
			}
		}

		$combined_classes = array_merge( $all_classes[0], $all_classes[1] );
		$combined_classes = array_map( 'esc_attr', $combined_classes );

		// Separates class names with a single space, preparing them for the
		// post content wrapper.
		echo 'class="' . esc_attr( implode( ' ', $combined_classes ) ) . '"';
	}
endif;

if ( ! function_exists( 'scouting_memories_2026_sm_posted_on' ) ) :
	/**
	 * "Posted on <date>" as the old theme printed it: a plain text button with
	 * the published date only, linked to the post.
	 *
	 * @param bool $small The small size (inside a result card).
	 */
	function scouting_memories_2026_sm_posted_on( $small = false ) {
		$time_string = sprintf(
			'<time datetime="%1$s">%2$s</time>',
			esc_attr( get_the_date( DATE_W3C ) ),
			esc_html( get_the_date() )
		);

		printf(
			'<div class="%1$s">%2$s</div> ',
			esc_attr( scouting_memories_2026_text_button_classes( $small ) ),
			sprintf(
				/* translators: %s: post date. */
				esc_html_x( 'Posted on %s', 'post date', 'scouting-memories-2026' ),
				'<a href="' . esc_url( get_permalink() ) . '" rel="bookmark">' . wp_kses(
					$time_string,
					array(
						'time' => array(
							'datetime' => true,
						),
					)
				) . '</a>'
			)
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
endif;

if ( ! function_exists( 'scouting_memories_2026_post_box' ) ) :
	/**
	 * The bordered box the old theme showed under a post's title: "Posted on
	 * <date>" and "Posted in <categories>", nothing else. Other post types get
	 * the empty box, as before.
	 */
	function scouting_memories_2026_post_box() {
		echo '<footer class="mb-4 border border-line p-4 break-words">';
		if ( 'post' === get_post_type() ) {
			echo '<div>';
			scouting_memories_2026_sm_posted_on();

			/* translators: used between list items, there is a space after the comma. */
			$categories_list = get_the_category_list( esc_html__( ', ', 'scouting-memories-2026' ) );
			if ( $categories_list ) {
				printf(
					/* translators: %s: list of categories. */
					'<div class="' . esc_attr( scouting_memories_2026_text_button_classes() ) . '">' . esc_html__( 'Posted in %s', 'scouting-memories-2026' ) . '</div>',
					$categories_list // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				);
			}
			echo '</div>';
		}
		echo '</footer>';
	}
endif;

if ( ! function_exists( 'scouting_memories_2026_entry_terms' ) ) :
	/**
	 * The State / Council / Lodge / Camps pieces on result cards, read through
	 * the core plugin's Post class exactly like the old theme's entry footer
	 * did: plain small text buttons, no background and no line.
	 */
	function scouting_memories_2026_entry_terms() {
		$piece = esc_attr( scouting_memories_2026_text_button_classes( true ) );

		if ( 'post' === get_post_type() && class_exists( 'Post' ) ) {
			$the_post = new Post( get_the_ID() );

			$chips = array(
				'State'   => array( $the_post->state_val, $the_post->say_states() ),
				'Council' => array( $the_post->council, $the_post->say_councils() ),
				'Lodge'   => array( $the_post->lodge, $the_post->say_lodges() ),
				'Camps'   => array( $the_post->camp, $the_post->say_camps() ),
			);

			foreach ( $chips as $label => $chip ) {
				if ( is_countable( $chip[0] ) ) {
					echo '<div class="' . $piece . '">' . esc_html( $label ) . ': ' . $chip[1] . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
			}
		}

		if ( ! is_single() && ! post_password_required() && ( comments_open() || get_comments_number() ) ) {
			echo '<div class="' . $piece . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			comments_popup_link( __( 'Leave a Comment', 'scouting-memories-2026' ) );
			echo '</div>';
		}

		edit_post_link( __( 'Edit', 'scouting-memories-2026' ), '<div class="' . $piece . '">', '</div>' );
	}
endif;

if ( ! function_exists( 'scouting_memories_2026_comment_nav' ) ) :
	/**
	 * "Older Comments" / "Newer Comments", shown above and below the comment
	 * list when the comments run over several pages.
	 *
	 * @param string $id The nav element's id.
	 */
	function scouting_memories_2026_comment_nav( $id ) {
		if ( get_comment_pages_count() <= 1 || ! get_option( 'page_comments' ) ) {
			return;
		}
		?>
		<nav id="<?php echo esc_attr( $id ); ?>" class="mb-2 text-center [&_a]:text-blue [&_a]:underline [&_a:hover]:text-blue-link-hover" aria-label="<?php esc_attr_e( 'Comments', 'scouting-memories-2026' ); ?>">
			<?php previous_comments_link( esc_html__( 'Older Comments', 'scouting-memories-2026' ) ); ?>
			<?php next_comments_link( esc_html__( 'Newer Comments', 'scouting-memories-2026' ) ); ?>
		</nav>
		<?php
	}
endif;
