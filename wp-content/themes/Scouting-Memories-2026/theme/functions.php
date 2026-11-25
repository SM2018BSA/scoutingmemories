<?php
/**
 * Scouting Memories 2026 functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package Scouting_Memories_2026
 */

if ( ! defined( 'SCOUTING_MEMORIES_2026_VERSION' ) ) {
	/*
	 * Set the theme’s version number.
	 *
	 * This is used primarily for cache busting. If you use `npm run bundle`
	 * to create your production build, the value below will be replaced in the
	 * generated zip file with a timestamp, converted to base 36.
	 */
	define( 'SCOUTING_MEMORIES_2026_VERSION', '1.0.0' );
}

/*
 * This theme is presentation only. The Scouting Memories domain layer (the
 * Formidable entry/view wrappers, search AJAX, taxonomy writers and access
 * checks) lives in the scouting-memories-core plugin; this flag tells the
 * plugin to wire itself up.
 */
define( 'SM_THEME_DELEGATES_TO_CORE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- the core plugin's own name for this switch.

/**
 * The site cannot work without the core plugin: say so plainly in wp-admin.
 */
function scouting_memories_2026_core_missing_notice() {
	if ( ! defined( 'SM_CORE_VERSION' ) ) {
		echo '<div class="notice notice-error"><p>' . wp_kses_post( __( 'Scouting Memories 2026: the <strong>Scouting Memories Core</strong> plugin must be active. The search, dashboard and indexing features are switched off until it is.', 'scouting-memories-2026' ) ) . '</p></div>';
	}
}
add_action( 'admin_notices', 'scouting_memories_2026_core_missing_notice' );

/**
 * The shared Tailwind class lists (page container, buttons, cards, fields, headings, tables, paging).
 */
require get_template_directory() . '/inc/class-scouting-memories-2026-ui.php';

/**
 * The primary menu's walker, which prints the menu with its Tailwind classes.
 */
require get_template_directory() . '/inc/class-scouting-memories-2026-nav-walker.php';

/**
 * Numbered pagination that keeps the search filters on every page link.
 */
require get_template_directory() . '/inc/pagination.php';

/*
 * Site behaviour carried over from the old theme's functions.php
 * (these stay with the theme, not the plugin, so each theme owns its own).
 */
// Hide the admin bar on the front end.
add_filter( 'show_admin_bar', '__return_false' );

/**
 * Category archives show 10 posts per page.
 *
 * @param WP_Query $query The query about to run.
 */
function scouting_memories_2026_category_query( $query ) {
	if ( ! is_admin() && $query->is_main_query() && is_category() ) {
		$query->set( 'posts_per_page', 10 );
	}
}
add_action( 'pre_get_posts', 'scouting_memories_2026_category_query' );

/**
 * Paged single posts must not be redirected back to page one.
 *
 * @param string|false $redirect_url The address WordPress wants to redirect to.
 * @return string|false
 */
function scouting_memories_2026_disable_redirect_canonical( $redirect_url ) {
	if ( is_paged() && is_singular() ) {
		$redirect_url = false;
	}
	return $redirect_url;
}
add_filter( 'redirect_canonical', 'scouting_memories_2026_disable_redirect_canonical' );

// Proper ob_end_flush() for all levels, replacing wp_ob_end_flush_all()
// (https://www.kevinleary.net/wordpress-ob_end_flush-error-fix/).
remove_action( 'shutdown', 'wp_ob_end_flush_all', 1 );
add_action(
	'shutdown',
	function () {
		while ( @ob_end_flush() ); // phpcs:ignore
	}
);

if ( ! defined( 'SCOUTING_MEMORIES_2026_TYPOGRAPHY_CLASSES' ) ) {
	/*
	 * Text an editor typed (page and post content) is styled by Tailwind Typography
	 * ("prose"), with modifiers that give it the old Bootstrap site's look: body text
	 * at 1rem/1.5 in the site's ink colour, paragraphs and lists with 1rem under them,
	 * blue underlined links, and Roboto Slab headings at Bootstrap's sizes (growing
	 * with the screen up to 1200px, like Scouting_Memories_2026_UI::H1 to H6).
	 *
	 * Set Tailwind Typography classes for the front end, block editor and
	 * classic editor using the constant below.
	 *
	 * For the front end, these classes are added by the `scouting_memories_2026_content_class`
	 * function. You will see that function used on every wrapper element that
	 * holds page or post content.
	 *
	 * For the block editor, these classes are converted to a JavaScript array
	 * and then used by the `./javascript/block-editor.js` file, which adds
	 * them to the appropriate elements in the block editor (and adds them
	 * again when they’re removed.)
	 *
	 * For the classic editor (and anything using TinyMCE, like Advanced Custom
	 * Fields), these classes are added to TinyMCE’s body class when it
	 * initializes.
	 */
	define(
		'SCOUTING_MEMORIES_2026_TYPOGRAPHY_CLASSES',
		implode(
			' ',
			array(
				'prose max-w-none text-base leading-normal break-words text-ink',
				'prose-p:mt-0 prose-p:mb-4',
				'prose-a:font-normal prose-a:text-blue prose-a:underline prose-a:hover:text-blue-link-hover',
				'prose-strong:font-bold prose-strong:text-inherit',
				'prose-h1:mt-0 prose-h1:mb-2 prose-h1:font-slab prose-h1:text-[calc(1.375rem+1.5vw)] prose-h1:leading-tight prose-h1:font-medium prose-h1:text-ink xl:prose-h1:text-[2.5rem]',
				'prose-h2:mt-0 prose-h2:mb-2 prose-h2:font-slab prose-h2:text-[calc(1.325rem+0.9vw)] prose-h2:leading-tight prose-h2:font-medium prose-h2:text-ink xl:prose-h2:text-[2rem]',
				'prose-h3:mt-0 prose-h3:mb-2 prose-h3:font-slab prose-h3:text-[calc(1.3rem+0.6vw)] prose-h3:leading-tight prose-h3:font-medium prose-h3:text-ink xl:prose-h3:text-[1.75rem]',
				'prose-h4:mt-0 prose-h4:mb-2 prose-h4:font-slab prose-h4:text-[calc(1.275rem+0.3vw)] prose-h4:leading-tight prose-h4:font-medium prose-h4:text-ink xl:prose-h4:text-2xl',
				'[&_h5]:mt-0 [&_h5]:mb-2 [&_h5]:font-slab [&_h5]:text-xl [&_h5]:leading-tight [&_h5]:font-medium [&_h5]:text-ink',
				'[&_h6]:mt-0 [&_h6]:mb-2 [&_h6]:font-slab [&_h6]:text-base [&_h6]:leading-tight [&_h6]:font-medium [&_h6]:text-ink',
				'prose-ul:mt-0 prose-ul:mb-4 prose-ul:pl-8 prose-ol:mt-0 prose-ol:mb-4 prose-ol:pl-8 prose-li:my-0 prose-li:pl-0 prose-li:marker:text-ink',
				'prose-blockquote:mt-0 prose-blockquote:mb-4 prose-blockquote:border-s-0 prose-blockquote:ps-0 prose-blockquote:font-normal prose-blockquote:text-ink prose-blockquote:not-italic',
				'prose-hr:my-4 prose-hr:border-ink/25',
				'prose-img:my-0',
			)
		)
	);
}

if ( ! function_exists( 'scouting_memories_2026_setup' ) ) :
	/**
	 * Sets up theme defaults and registers support for various WordPress features.
	 *
	 * Note that this function is hooked into the after_setup_theme hook, which
	 * runs before the init hook. The init hook is too late for some features, such
	 * as indicating support for post thumbnails.
	 */
	function scouting_memories_2026_setup() {
		/*
		 * Make theme available for translation.
		 * Translations can be filed in the /languages/ directory.
		 * If you're building a theme based on Scouting Memories 2026, use a find and replace
		 * to change 'scouting-memories-2026' to the name of your theme in all the template files.
		 */
		load_theme_textdomain( 'scouting-memories-2026', get_template_directory() . '/languages' );

		// Add default posts and comments RSS feed links to head.
		add_theme_support( 'automatic-feed-links' );

		/*
		 * Let WordPress manage the document title.
		 * By adding theme support, we declare that this theme does not use a
		 * hard-coded <title> tag in the document head, and expect WordPress to
		 * provide it for us.
		 */
		add_theme_support( 'title-tag' );

		/*
		 * Enable support for Post Thumbnails on posts and pages.
		 *
		 * @link https://developer.wordpress.org/themes/functionality/featured-images-post-thumbnails/
		 */
		add_theme_support( 'post-thumbnails' );

		// This theme uses wp_nav_menu() in one location.
		register_nav_menus(
			array(
				// Same location slug as the old theme, so the existing menu carries over.
				'primary' => __( 'Primary Menu', 'scouting-memories-2026' ),
			)
		);

		/*
		 * Switch default core markup for search form, comment form, and comments
		 * to output valid HTML5.
		 */
		add_theme_support(
			'html5',
			array(
				'search-form',
				'comment-form',
				'comment-list',
				'gallery',
				'caption',
				'style',
				'script',
			)
		);

		// Add theme support for selective refresh for widgets.
		add_theme_support( 'customize-selective-refresh-widgets' );

		// Add support for editor styles.
		add_theme_support( 'editor-styles' );

		// Enqueue editor styles.
		add_editor_style( 'style-editor.css' );

		// Add support for responsive embedded content.
		add_theme_support( 'responsive-embeds' );

		// Wide and full-width blocks in the editor.
		add_theme_support( 'align-wide' );

		// The badge in the masthead can be replaced in Appearance > Customize > Site Identity.
		add_theme_support(
			'custom-logo',
			array(
				'height'      => 300,
				'width'       => 300,
				'flex-height' => true,
				'flex-width'  => true,
			)
		);

		// Remove support for block templates.
		remove_theme_support( 'block-templates' );
	}
endif;
add_action( 'after_setup_theme', 'scouting_memories_2026_setup' );

/**
 * Register widget area.
 *
 * @link https://developer.wordpress.org/themes/functionality/sidebars/#registering-a-sidebar
 */
function scouting_memories_2026_widgets_init() {
	register_sidebar(
		array(
			'name'          => __( 'Sidebar', 'scouting-memories-2026' ),
			'id'            => 'sidebar-1',
			'description'   => __( 'Add widgets here.', 'scouting-memories-2026' ),
			'before_widget' => '<section id="%1$s" class="mb-4">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="' . esc_attr( Scouting_Memories_2026_UI::H2 ) . '">',
			'after_title'   => '</h2>',
		)
	);
}
add_action( 'widgets_init', 'scouting_memories_2026_widgets_init' );

/**
 * A version for one of the theme's built files: the time the file last changed, so a new
 * build is never hidden by a browser's cache. Falls back to the theme's version number.
 *
 * @param string $relative_path The file's path inside the theme folder.
 * @return string
 */
function scouting_memories_2026_asset_version( $relative_path ) {
	$file = get_template_directory() . '/' . ltrim( $relative_path, '/' );
	return is_file( $file ) ? (string) filemtime( $file ) : SCOUTING_MEMORIES_2026_VERSION;
}

/**
 * The masthead badge: the Site Identity logo when one is set, the Scouting Memories badge otherwise.
 *
 * @return string The image address, not yet escaped.
 */
function scouting_memories_2026_logo_url() {
	$logo_id = (int) get_theme_mod( 'custom_logo' );
	$custom  = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
	return $custom ? $custom : 'https://storage.scoutingmemories.org/2021/02/b5564491-scouting_memories_project-e1613015799300.png';
}

/**
 * Enqueue scripts and styles.
 */
function scouting_memories_2026_scripts() {
	$uri = get_template_directory_uri();

	// One stylesheet (Tailwind's build) and one script (Alpine.js and the theme's own parts).
	// Nothing in this theme loads from a CDN. The theme asks for no jQuery, and never for
	// WordPress's media window or its TinyMCE editor (both need jQuery): the media library
	// and the rich-text editor are the theme's own (javascript/media.js, javascript/editor.js).
	wp_enqueue_style( 'scouting-memories-2026-style', get_stylesheet_uri(), array(), scouting_memories_2026_asset_version( 'style.css' ) );
	wp_enqueue_script(
		'scouting-memories-2026-script',
		$uri . '/js/script.min.js',
		array(),
		scouting_memories_2026_asset_version( 'js/script.min.js' ),
		array(
			'strategy'  => 'defer',
			'in_footer' => true,
		)
	);

	// The rich-text editor (Tiptap) is its own file, only registered here: it is asked for
	// when a form prints a rich-text field (scouting_memories_2026_rich_text_script below).
	wp_register_script(
		'scouting-memories-2026-editor',
		$uri . '/js/editor.min.js',
		array( 'scouting-memories-2026-script' ),
		scouting_memories_2026_asset_version( 'js/editor.min.js' ),
		array(
			'strategy'  => 'defer',
			'in_footer' => true,
		)
	);

	// WordPress's own reply script for threaded comments (plain JavaScript, no jQuery).
	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'scouting_memories_2026_scripts' );

/**
 * Loads the rich-text editor's script on a page that shows a rich-text field.
 *
 * The Scouting Forms plugin fires the action `sm_forms_rich_text_field` each time it prints
 * such a field; the script goes out once, in the footer, after the main script.
 */
function scouting_memories_2026_rich_text_script() {
	wp_enqueue_script( 'scouting-memories-2026-editor' );
}
add_action( 'sm_forms_rich_text_field', 'scouting_memories_2026_rich_text_script' );

/**
 * Enqueue the block editor script.
 */
function scouting_memories_2026_enqueue_block_editor_script() {
	$current_screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	if (
		$current_screen &&
		$current_screen->is_block_editor() &&
		'widgets' !== $current_screen->id
	) {
		wp_enqueue_script(
			'scouting-memories-2026-block-editor',
			get_template_directory_uri() . '/js/block-editor.min.js',
			array(
				'wp-blocks',
				'wp-edit-post',
			),
			SCOUTING_MEMORIES_2026_VERSION,
			true
		);
		wp_add_inline_script( 'scouting-memories-2026-block-editor', 'tailwindTypographyClasses = ' . wp_json_encode( explode( ' ', SCOUTING_MEMORIES_2026_TYPOGRAPHY_CLASSES ) ) . ';', 'before' );
	}
}
add_action( 'enqueue_block_assets', 'scouting_memories_2026_enqueue_block_editor_script' );

/**
 * Add the Tailwind Typography classes to TinyMCE (the classic editor in wp-admin only;
 * the front end never loads TinyMCE).
 *
 * @param array $settings TinyMCE settings.
 * @return array
 */
function scouting_memories_2026_tinymce_add_class( $settings ) {
	$settings['body_class'] = SCOUTING_MEMORIES_2026_TYPOGRAPHY_CLASSES;
	return $settings;
}
add_filter( 'tiny_mce_before_init', 'scouting_memories_2026_tinymce_add_class' );

/**
 * Limit the block editor to heading levels supported by Tailwind Typography.
 *
 * @param array  $args Array of arguments for registering a block type.
 * @param string $block_type Block type name including namespace.
 * @return array
 */
function scouting_memories_2026_modify_heading_levels( $args, $block_type ) {
	if ( 'core/heading' !== $block_type ) {
		return $args;
	}

	// Remove <h1>, <h5> and <h6>.
	$args['attributes']['levelOptions']['default'] = array( 2, 3, 4 );

	return $args;
}
add_filter( 'register_block_type_args', 'scouting_memories_2026_modify_heading_levels', 10, 2 );

/**
 * Custom template tags for this theme.
 */
require get_template_directory() . '/inc/template-tags.php';

/**
 * Functions which enhance the theme by hooking into WordPress.
 */
require get_template_directory() . '/inc/template-functions.php';
