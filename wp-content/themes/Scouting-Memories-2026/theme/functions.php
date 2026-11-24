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
 * The daisyUI nav walker for the primary menu.
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
	 * Set Tailwind Typography classes for the front end, block editor and
	 * classic editor using the constant below.
	 *
	 * For the front end, these classes are added by the `scouting_memories_2026_content_class`
	 * function. You will see that function used everywhere an `entry-content`
	 * or `page-content` class has been added to a wrapper element.
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
		'prose prose-neutral max-w-none prose-a:text-primary'
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

		// This theme uses wp_nav_menu() in two locations.
		register_nav_menus(
			array(
				// Same location slug as the old theme, so the existing menu carries over.
				'primary' => __( 'Primary', 'scouting-memories-2026' ),
				'menu-2'  => __( 'Footer Menu', 'scouting-memories-2026' ),
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
			'name'          => __( 'Footer', 'scouting-memories-2026' ),
			'id'            => 'sidebar-1',
			'description'   => __( 'Add widgets here to appear in your footer.', 'scouting-memories-2026' ),
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title">',
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

	// select2 for the chained search dropdowns, self-hosted. Nothing in this theme loads from a CDN.
	wp_enqueue_style( 'select2', $uri . '/css/select2.min.css', array(), '4.1.0' );
	wp_enqueue_script( 'select2', $uri . '/js/vendor/select2.min.js', array( 'jquery' ), '4.1.0', false );

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

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'scouting_memories_2026_scripts' );

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
			'scouting-memories-2026-editor',
			get_template_directory_uri() . '/js/block-editor.min.js',
			array(
				'wp-blocks',
				'wp-edit-post',
			),
			SCOUTING_MEMORIES_2026_VERSION,
			true
		);
		wp_add_inline_script( 'scouting-memories-2026-editor', "tailwindTypographyClasses = '" . esc_attr( SCOUTING_MEMORIES_2026_TYPOGRAPHY_CLASSES ) . "'.split(' ');", 'before' );
	}
}
add_action( 'enqueue_block_assets', 'scouting_memories_2026_enqueue_block_editor_script' );

/**
 * Add the Tailwind Typography classes to TinyMCE.
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
