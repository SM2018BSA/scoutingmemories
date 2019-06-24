<?php

namespace WPE\ContentPerformance;

/**
 * Code that is common across the WPE Content Performance plugin.
 */
class Core {

	// Various option names
	const config_option = 'wpecp_config';

	/**
	 * Update an option
	 */
	static function update( $key, $value ) {
		$options = self::get();

		$options[ $key ] = $value;

		update_option( self::config_option, $options );
	}

	/**
	 * Get a single option, or all options as an array.
	 *
	 * @return string|array|null
	 */
	static function get( $opt = null ) {
		$options = get_option( self::config_option );

		if ( ! is_array( $options ) ) {
			$options = array();
		}

		$options = wp_parse_args( $options, array(
			'access_key' => '',
			'secret_key' => '',
			'ga_authorized' => '',
			'ga_profile_id' => '',
			'dimensions' => array(),
			'dbversion' => 0,
			'default_date_range' => '30-days',
			'default_published_date_range' => '90-days',
		) );

		if ( isset( $opt ) ) {
			$option = isset( $options[ $opt ] ) ? $options[ $opt ] : null;
			/**
			 * Filters a single WPECP option after it's returned from the database.
			 *
			 * @param string  $option The option to filter.
			 */
			return apply_filters( "wpecp_get_option_{$opt}", $option );
		} else {
			/**
			 * Filters the WPECP options array after it's returned from the database.
			 *
			 * @param array $options The options to filter.
			 */
			return apply_filters( 'wpecp_get_options', $options );
		}

	}

	/**
	 * Get the access key from our plugin settings.
	 *
	 * @return string The access key.
	 * @todo Trash me
	 */
	static function get_access_key() {
		/**
		 * The access_key used for rest authentication.
		 *
		 * @param $access_key
		 */
		return apply_filters( 'wpecp_access_key', Core::get( 'access_key' ) );
	}

	/**
	 * Get the secret key from our plugin settings.
	 *
	 * @return string The secret key.
	 * @todo Trash me
	 */
	static function get_secret_key() {
		/**
		 * The secret_key used for rest authentication.
		 *
		 * @param $secret_key
		 */
		return apply_filters( 'wpecp_secret_key', Core::get( 'secret_key' ) );
	}

	/**
	 * Get the metrics for a particular post
	 *
	 * @param WP_Post $Post
	 * @return array $post_data
	 */
	public static function get_post_data( $_post ) {
		global $post;

		setup_postdata( $post = $_post );

		// Extract the tags and format them.
		$tags = array_values( // Re-index the array to account for any removed tags.
			array_map( 'self::build_taxonomy_object',
				get_the_tags() ?: array() // Deal with WP's dumb return types
			)
		);
		// Do the same for categories.
		$categories = array_values(
			array_map( 'self::build_taxonomy_object',
				get_the_category() ?: array()
			)
		);

		// Get the authors name and id.
		$author = array(
			'id' => $post->post_author,
			'name' => get_the_author(),
		);

		$post_data = array(
			'id' => $post->ID,
			'post_type' => $post->post_type,
			'post_title' => $post->post_title,
			'post_date' => $post->post_date,
			'post_modified' => $post->post_modified,
			'path' => wp_make_link_relative( get_permalink( ) ),
			'authors' => apply_filters( 'wpecp_post_authors', array( $author ), $post ),
			'tags' => $tags,
			'post_status' => $post->post_status,
			'categories' => $categories,
		);

		wp_reset_postdata();

		return apply_filters( 'wpecp_post_data', $post_data );
	}

	/**
	 * Determine if a path belongs to this site.
	 *
	 * @param  string  $path The path to check.
	 * @return boolean       True if at least one post exists at the requested path.
	 */
	public static function is_path_valid( $path ) {
		global $wp_rewrite, $wp_query;

		// Grab the current rewrites.
		$rewrites = $wp_rewrite->wp_rewrite_rules();

		// Trim the leading and trailing slashes.
		$request_uri = trim( parse_url( $path, PHP_URL_PATH ), '/' );

		/**
		 *  Loop through the rewrites, logic borrowed from core:
		 *  https://github.com/WordPress/WordPress/blob/493f76a3d2ef8c030ab5dcd4333f9a401208f534/wp-includes/class-wp.php#L226
		 */
		foreach ( (array) $rewrites as $match => $query ) {
			if (  preg_match( "#^{$match}#", $request_uri, $matches )
					|| preg_match( "#^{$match}#", urldecode( $request_uri ), $matches ) ) {

				if ( preg_match( '/pagename=\$matches\[([0-9]+)\]/', $query, $varmatch ) ) {
					// This is a verbose page match, let's check to be sure about it.
					$page = get_page_by_path( $matches[ $varmatch[1] ] );
					if ( ! $page ) {
						continue;
					}
				}
				// Got a match;
				break;
			}
		}

		// Trim the query of everything up to the '?'.
		$query = preg_replace( '!^.+\?!', '', $query );
		// Substitute the substring matches into the query.
		$query = addslashes( \WP_MatchesMapRegex::apply( $query, $matches ) );
		// Extract the query vars.
		parse_str( $query, $query_vars );
		// Extract the query string vars.
		parse_str( parse_url( $path, PHP_URL_QUERY ), $query_string_vars );

		// Merge them if necessary.
		if ( ! empty( $query_string_vars ) ) {
			$query_vars = array_merge( $query_vars, $query_string_vars );
		}

		// Run the WP_Query.
		$wp_query = new \WP_Query( $query_vars );

		// If any posts/pages live at this URL, it's valid.
		if ( $wp_query->found_posts > 0 ) {
			return true;
		}

		return false;
	}

	/**
	 * Extract data from the WP_Term object and format it.
	 *
	 * @param  object $term The WP_Term object.
	 * @return array        Array containing the id and name of the term.
	 */
	static function build_taxonomy_object( $term ) {
		return array(
			'id' => $term->term_id,
			'name' => $term->name,
		);
	}

	/**
	 *	Get the site's ID.
	 *
	 * @return string The site's ID.
	 */
	static function get_site_id() {
		if ( self::is_wpe() ) {
			$id = 'wpe-' . self::get_install_name();
		} else {
			// TODO: Figure out what to do with non-WP Engine customers.
			$id = null;
		}
		return apply_filters( 'wpecp_site_id', $id );
	}

	/**
	 *	Get the site's WPE install name.
	 *
	 * @return string The site's WPE install name.
	 */
	static function get_install_name() {
		return apply_filters( 'wpecp_install_name', ! empty( $_SERVER['WPENGINE_ACCOUNT'] ) ? $_SERVER['WPENGINE_ACCOUNT'] : false );
	}

	/**
	 * Detect if the site is hosted on WPE.
	 *
	 * @return boolean True if IS_WPE is set, false if it isn't.
	 */
	static function is_wpe() {
		return isset( $_SERVER['IS_WPE'] );
	}

	/**
	 * Get an instance of the Service class with the site ID and WP_Http.
	 *
	 * @return WPE\ContentPerformance\Service The initialized Service class.
	 */
	static function get_service() {
		$http = apply_filters( 'wpecp_wp_http', new \WP_Http() );
		$site_id = self::get_site_id();
		return new Service( $http, $site_id );
	}

	/**
	 * Get the build string from WordPress.
	 *
	 * @return string The build meta data seperated by periods.
	 */
	static function get_build_string() {
		$parts = explode( '+', self::get_version() );
		if ( count( $parts ) > 1 ) {
			return $parts[1];
		} else {
			return false;
		}
	}

	/**
	 * Get the version string from WordPress.
	 *
	 * @return string The version string without the build meta data.
	 */
	static function get_version() {
		// WordPress is looking for the directory that Content Performance lives in.
		$plugins = get_plugins( '/' . dirname( self::get_plugin_root() ) );
		$version = false;
		// WordPress uses the root PHP file to identify the plugin.
		if ( isset( $plugins['wpengine-content-performance.php'] ) ) {
			$version = $plugins['wpengine-content-performance.php']['Version'];
		}
		return apply_filters( 'wpecp_plugin_version',  $version );
	}

	/**
	 * Return the plugin basename.
	 *
	 * @return string The WordPress plugin basename.
	 */
	static function get_plugin_root() {
		return dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/wpengine-content-performance.php';
	}
}
