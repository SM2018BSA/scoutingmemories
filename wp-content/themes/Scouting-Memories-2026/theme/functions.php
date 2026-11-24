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
	define( 'SCOUTING_MEMORIES_2026_VERSION', '0.1.0' );
}

/*
 * This theme is presentation only. The Scouting Memories domain layer (the
 * Formidable entry/view wrappers, search AJAX, taxonomy writers and access
 * checks) lives in the scouting-memories-core plugin; this flag tells the
 * plugin to wire itself up.
 */
define( 'SM_THEME_DELEGATES_TO_CORE', true );

/**
 * The site cannot work without the core plugin: say so plainly in wp-admin.
 */
function scouting_memories_2026_core_missing_notice() {
	if ( ! defined( 'SM_CORE_VERSION' ) ) {
		echo '<div class="notice notice-error"><p>Scouting Memories 2026: the <strong>Scouting Memories Core</strong> plugin must be active. The search, dashboard and indexing features are switched off until it is.</p></div>';
	}
}
add_action( 'admin_notices', 'scouting_memories_2026_core_missing_notice' );

/**
 * The daisyUI nav walker for the primary menu.
 */
require get_template_directory() . '/inc/class-sm-nav-walker.php';

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

// Category archives show 10 posts per page.
function scouting_memories_2026_category_query( $query ) {
	if ( ! is_admin() && $query->is_main_query() && is_category() ) {
		$query->set( 'posts_per_page', 10 );
	}
}
add_action( 'pre_get_posts', 'scouting_memories_2026_category_query' );

// Paged single posts must not be redirected back to page one.
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
				'menu-2' => __( 'Footer Menu', 'scouting-memories-2026' ),
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
 * Enqueue scripts and styles.
 */
function scouting_memories_2026_scripts() {
	// Roboto Slab for headings, from Google Fonts like the old theme.
	wp_enqueue_style( 'roboto-slab', 'https://fonts.googleapis.com/css2?family=Roboto+Slab:wght@400;500;700&display=swap', array(), null );
	// select2 for the chained search dropdowns, self-hosted (the old theme used a CDN).
	wp_enqueue_style( 'select2', get_template_directory_uri() . '/css/select2.min.css', array(), '4.1.0' );
	wp_enqueue_script( 'select2', get_template_directory_uri() . '/js/vendor/select2.min.js', array( 'jquery' ), '4.1.0', false );
	wp_enqueue_style( 'scouting-memories-2026-style', get_stylesheet_uri(), array(), SCOUTING_MEMORIES_2026_VERSION );
	wp_enqueue_script( 'scouting-memories-2026-script', get_template_directory_uri() . '/js/script.min.js', array(), SCOUTING_MEMORIES_2026_VERSION, true );

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
