<?php
/*
Plugin Name: WP Engine Content Performance
Version: 1.5.2+604.a37d478
Description: Content Performance assists you with your content strategy by optimizing Google Analytics data specifically for WordPress. It integrates raw data from Google Analytics and automatically keeps it in sync with WordPress so that you can see how visitors are engaging with your site content. See content performance metrics against WordPress object types like posts, authors, categories, and tags without the custom development work.
Author: WP Engine
Author URI: https://wpengine.com
Plugin URI: https://wpengine.com/support/about-content-performance/
Text Domain: wpengine-content-performance
Domain Path: /languages
*/

require_once( __DIR__ . '/inc/class-wpe-content-performance-core.php' );
require_once( __DIR__ . '/inc/class-wpe-content-performance-admin.php' );
require_once( __DIR__ . '/inc/class-wpe-content-performance-auth.php' );
require_once( __DIR__ . '/inc/class-wpe-content-performance-rest-api.php' );
require_once( __DIR__ . '/inc/class-wpe-content-performance-connection.php' );
require_once( __DIR__ . '/inc/class-wpe-content-performance-site.php' );
require_once( __DIR__ . '/inc/class-wpe-content-performance-service.php' );
require_once( __DIR__ . '/inc/class-wpe-content-performance-update.php' );
require_once( __DIR__ . '/inc/class-wpe-content-performance-upgrade.php' );
require_once( __DIR__ . '/inc/class-wpe-content-performance-debug.php' );

if ( is_admin() ) {
	new WPE\ContentPerformance\Admin();
}
