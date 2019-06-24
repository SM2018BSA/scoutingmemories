<?php

namespace WPE\ContentPerformance;

class Update
{
	/**
	 * The plugin current version
	 * @var string
	 */
	private $current_version;

	/**
	 * The plugin remote update path
	 * @var string
	 */
	private $update_path;

	/**
	 * Plugin Slug (plugin_directory/plugin_file.php)
	 * @var string
	 */
	private $plugin_slug;

	/**
	 * Plugin name (plugin_file)
	 * @var string
	 */
	private $slug;

	/**
	 * Initialize a new instance of the WordPress Auto-Update class
	 * @param string $current_version
	 * @param string $update_path
	 * @param string $slug
	 * @param string $plugin_slug
	 */
	public function __construct( $current_version, $update_path, $slug, $plugin_slug ) {
		$this->current_version = $current_version;
		$this->update_path = $update_path;

		// Set the Plugin Slug
		$this->plugin_slug = $plugin_slug;
		$this->slug = $slug;

		// define the alternative API for updating checking
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );

		// Define the alternative response for information checking
		add_filter( 'plugins_api', array( $this, 'check_info' ), 10, 3 );
	}

	/**
	 * Add our self-hosted plugin to the filter transient.
	 *
	 * @param $transient
	 * @return object
	 */
	public function check_update( $transient ) {
		// Get the remote version.
		$remote_version = $this->get_remote();
		// If a newer version is available, add the update.
		if ( version_compare( $this->current_version, $remote_version->version, '<' ) ) {
			$obj = new \stdClass();
			$obj->slug = $this->slug;
			$obj->new_version = apply_filters( 'wpecp_remote_version_display', $remote_version->version );
			$obj->url = $remote_version->url;
			$obj->plugin = $this->plugin_slug;
			$obj->package = $remote_version->package;
			$obj->tested = $remote_version->tested;
			$transient->response[ $this->plugin_slug ] = $obj;
		}

		return $transient;
	}

	public function update() {
		$updates = get_site_transient( 'update_plugins' );

		// Quit if there isn't an update.
		if ( ! $updates || ! isset( $updates->response[ $this->plugin_slug ] ) ) {
			return 'up_to_date';
		}

		// Finish the upgrade even if the user navigates away.
		ignore_user_abort( true );

		include_once( ABSPATH . '/wp-admin/includes/admin.php' );
		include_once( ABSPATH . '/wp-admin/includes/class-wp-upgrader.php' );

		// Skins affect the output of the upgrader, this skin returns JSON.
		$skin = new \WP_Ajax_Upgrader_Skin();
		$upgrader = apply_filters( 'wpecp_plugin_upgrader', new \Plugin_Upgrader( $skin ) );

		// Prevent plugin deactivation. See: https://core.trac.wordpress.org/browser/tags/4.8/src/wp-admin/includes/class-plugin-upgrader.php#L408
		add_filter( 'wp_doing_cron', '__return_true' );

		$result = $upgrader->upgrade( $this->plugin_slug );
		// The plugin gets deactivated during the process.
		activate_plugin( $this->plugin_slug );

		return $result;
	}

	/**
	 * Add our self-hosted description to the filter.
	 *
	 * @param boolean $false
	 * @param array $action
	 * @param object $arg
	 * @return bool|object
	 */
	public function check_info( $false, $action, $arg ) {
		if ( isset( $arg->slug ) && $arg->slug === $this->slug ) {
			$response = $this->get_remote();
			if ( $response ) {
				$response->sections = (array) $response->sections;
				return $response;
			}
		}

		return $false;
	}

	/**
	 * Return the remote version from a JSON manifest.
	 *
	 * @return object The decoded JSON file.
	 */
	public function get_remote() {
		// Make the GET request.
		$request = apply_filters( 'wpecp_update_response', wp_remote_get( $this->update_path ) );
		// Check if response is valid.
		if ( ! is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) === 200 ) {
			return json_decode( $request['body'] );
		}

		return false;
	}
}
