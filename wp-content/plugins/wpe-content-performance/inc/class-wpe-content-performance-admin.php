<?php

namespace WPE\ContentPerformance;

/**
 * Content Performance Admin
 */
class Admin {
	protected $connection;
	protected $site;
	protected $update;

	// Page hooks.
	public $dashboard_hook;
	public $settings_hook;
	public $plugins_hook;

	// Cookie to use queueing nags for display
	const notice_cookie = 'wpecp_notice';

	// Nonces
	const nonce_report_widget = 'wpecp_nonce_report_widget_';
	const nonce_dashboard = 'wp_ajax_wpecp_service_request';
	const nonce_settings = 'wp_ajax_wpecp_settings';

	const capability = 'view_cperf';

	/**
	 * Constructor
	 */
	function __construct() {
		$this->plugins_hook = 'plugins';
		$this->connection = new Connection();
		$this->site = new Site();

		// Initialization
		add_action( 'admin_menu', array( $this, 'action_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Hook update functionality.
		add_action( 'admin_init', array( $this, 'hook_update' ) );

		// Redirect if the Plugin is not configured. Priority 1, so we do this first.
		add_action( 'current_screen', array( $this, 'redirect_if_not_configured' ), 1 );

		// Initialize connection & site classes.
		add_action( 'current_screen', array( $this, 'check_connection' ), 99 );
		add_action( 'current_screen', array( $this, 'check_site' ), 99 );

		// Plugin Activate
		add_action( 'admin_notices', array( $this, 'plugins_page_notices' ) );

		// Admin notices
		add_action( 'admin_notices', array( $this, 'settings_page_notices' ) );

		// Scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts_reports' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'dequeue_conflicting_scripts' ) );

		// Action links for Plugins page.
		add_filter( 'plugin_action_links_' . basename( dirname( __DIR__ ) ) . '/wpengine-content-performance.php', array( $this, 'plugin_action_links' ) );

		// Register the new dashboard widget with the 'wp_dashboard_setup' action
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widgets' ) );

		// Service requests
		add_action( 'wp_ajax_wpecp_service_request', array( $this, 'service_request' ) );
		add_action( 'wp_ajax_wpecp_settings', array( $this, 'service_request' ) );

		// Update request.
		add_action( 'wp_ajax_wpecp_update_plugin', array( $this, 'update_plugin' ) );

		add_action( 'admin_post_wpecp_download_csv', array( $this, 'download_csv' ) );

		add_action( 'activated_plugin', array( $this, 'activation_actions' ) );
		add_action( 'install_plugins_pre_plugin-information', array( $this, 'already_installed_redirect' ) );

		add_action( 'admin_footer', array( $this, 'print_debug_messages' ) );

		add_filter( 'custom_menu_order', '__return_true' );
		add_filter( 'menu_order', array( $this, 'cp_menu_order' ), 15 );

		register_deactivation_hook( Core::get_plugin_root(), array( $this, 'deactivation_actions' ) );
	}

	/**
	 * Print debug messages if wpecp_debug is set.
	 *
	 * @codeCoverageIgnore
	 */
	public function print_debug_messages() {
		if ( isset( $_GET['wpecp_debug'] ) ) {
			Debug::display_debug_messages();
		}
	}

	/**
	 * Hook update functionality.
	 *
	 * @action admin_init
	 * @codeCoverageIgnore
	 */
	public function hook_update() {
		$build_string = Core::get_build_string();
		if ( false !== $build_string ) {
			$this->update = new Update(
				$build_string,
				apply_filters( 'wpecp_update_url', 'https://s3.amazonaws.com/wpengine-wordpress-plugins/content-performance/production/manifest.json' ),
				'content-performance',
				Core::get_plugin_root()
			);

			// Display the full version string "1.4.1+444" instead of just "444".
			add_filter( 'wpecp_remote_version_display', function( $build ) {
				return explode( '+', Core::get_version() )[0] . '+' . $build;
			} );
		}
	}

	/**
	 * Actions performed on plugin activation.
	 *
	 * @action activated_plugin
	 * @param  string $plugin Plugin base name.
	 */
	public function activation_actions( $plugin ) {
		if ( Core::get_plugin_root() === $plugin ) {
			$service = Core::get_service();
			$service->dispatch_json( 'enable', array( 'reason' => 'plugin_toggle', 'username' => wp_get_current_user()->user_login ), 'PATCH' );

			$this->redirect_to_settings();
		}
	}

	/**
	 * Actions performanced on plugin deactivation.
	 *
	 * @action deactivate_PLUGINNAME
	 */
	public function deactivation_actions() {
		$service = Core::get_service();
		$service->dispatch_json( 'disable', array( 'reason' => 'plugin_toggle', 'username' => wp_get_current_user()->user_login ), 'PATCH' );
	}

	/**
	 * Redirect to plugin settings on plugin-install page.
	 *
	 * @action install_plugins_pre_plugin-information
	 */
	public function already_installed_redirect() {
		if ( isset( $_GET['plugin'] ) && 'content-performance' === $_GET['plugin'] && ! isset( $_GET['section'] ) ) {
			$this->redirect_to_settings();
		}
	}

	/**
	 * Enqueues admin script
	 *
	 * @codeCoverageIgnore
	 */
	public function enqueue_scripts_settings( $hook ) {
		// Only enqueue these assets on the settings page.
		if ( $this->settings_hook !== $hook ) {
			return;
		}

		$build_string = Core::get_build_string();

		// Only enqueue for production environments.
		if ( ! $this->is_development() ) {
			$this->enqueue_reporting_scripts( $build_string );
		}

		wp_enqueue_script( 'wpecp-settings-react', $this->asset_url( 'js/settings.js' ), array( 'jquery' ), $build_string );
		wp_enqueue_style( 'wpecp-settings-css', plugins_url( 'wpe-content-performance-settings.css', __FILE__ ), array(), $build_string );

		$nonce = wp_create_nonce( self::nonce_settings );

		$options = Core::get();

		$wpecp = array(
			'serviceUrl' => Service::service_url(),
			'siteUrl' => site_url(),
			'viewId' => $this->connection->get_view_id(),
			'error' => $this->connection->get_error(),
			'nonce' => $nonce,
			'optionGroup' => 'wpengine-content-performance',
			'optionNonce' => wp_create_nonce( 'wpengine-content-performance-options' ),
			'referrer' => esc_attr( wp_unslash( $_SERVER['REQUEST_URI'] ) ),
			'accessKey' => $options['access_key'],
			'secretKey' => $options['secret_key'],
			'viewId' => $this->connection->get_view_id(),
			'defaultDateRange' => $options['default_date_range'],
			'defaultPubliedDateRange' => $options['default_published_date_range'],
			'isEnabled' => $this->connection->is_enabled(),
			'isKeyPairEnabled' => $this->connection->is_key_pair_enabled(),
			'isGaAccessEnabled' => $this->connection->is_ga_access_enabled(),
			'isGaViewEnabled' => $this->connection->is_ga_view_enabled(),
			'configOption' => Core::config_option,
			'capability' => self::capability,
			'roles' => get_editable_roles(),
			'dashboardUrl' => esc_url( admin_url( 'admin.php?page=content-performance' ) ),
			'keysUrl' => esc_url( sprintf( 'https://my.wpengine.com/installs/%s/utilities#content-performance', Core::get_install_name() ) ),
		);

		wp_localize_script( 'wpecp-settings-react', 'wpecp', $wpecp );
	}

	/**
	 * Enqueues admin script
	 *
	 * @codeCoverageIgnore
	 */
	public function enqueue_scripts_reports( $hook ) {
		// Only enqueue these assets on the dashboard(s).
		if ( $this->dashboard_hook !== $hook && 'index.php' !== $hook ) {
			return;
		}

		$build_string = Core::get_build_string();

		// Error tracking, only enqueue on our Dashboard.
		if ( $this->dashboard_hook === $hook && ! $this->is_development() ) {
			$this->enqueue_reporting_scripts( $build_string );
		}

		// Only load the application related JavaScript when it's needed.
		if ( $this->site->enough_data() ) {

			// Only grab public custom post types and exclude builtin types.
			$args = array(
				'public'   => true,
				'_builtin' => false,
			);

			// Pull custom post types and extra labels.
			$custom_post_types = array_map( function ( $type ) {
				return $type->label;
			}, get_post_types( $args, 'objects', 'and' ) );

			wp_enqueue_script( 'wpe-react', $this->asset_url( 'js/app.js' ), array( 'jquery-ui-progressbar', 'jquery' ), $build_string );

			$nonce = wp_create_nonce( self::nonce_dashboard );
			$wpecp = array(
				'nonce' => $nonce,
				'last_fetched' => $this->site->get( 'last_fetched' ),
				'is_dashboard' => $this->dashboard_hook === $hook,
				'admin_post_url' => esc_url( admin_url( 'admin-post.php' ) ),
				'help_url' => esc_url( admin_url( 'admin.php?page=content-performance-help' ) ),
				'dashboard_url' => esc_url( admin_url( 'admin.php?page=content-performance' ) ),
				'summary_url' => esc_url( admin_url( 'admin.php?page=content-performance&path=summary' ) ),
				'date_format' => get_option( 'date_format' ),
				'time_format' => get_option( 'time_format' ),
				'default_date_range' => Core::get( 'default_date_range' ),
				'default_published_date_range' => Core::get( 'default_published_date_range' ),
				'feedback_url_yes' => esc_url( sprintf( 'https://docs.google.com/forms/d/e/1FAIpQLSebqC_irYznsKRbz5JGy9L59GCVdLs9sRfKfgsm9zUn7PF_-A/viewform?usp=pp_url&entry.728330654=%s&entry.602557247=Yes', Core::get_install_name() ) ),
				'feedback_url_no' => esc_url( sprintf( 'https://docs.google.com/forms/d/e/1FAIpQLSebqC_irYznsKRbz5JGy9L59GCVdLs9sRfKfgsm9zUn7PF_-A/viewform?usp=pp_url&entry.728330654=%s&entry.602557247=No', Core::get_install_name() ) ),
				'site_url' => site_url(),
				'user_id' => substr( base64_encode( md5( Core::get_install_name(), true ) ), 0, 12 ),
				'check_for_update' => ( null !== $this->update && current_user_can( 'update_plugins' ) && apply_filters( 'wpecp_auto_update', true ) ),
				'tracking_enabled' => apply_filters( 'wpecp_tracking_enabled', $this->is_production() ),
				'fetch_progress' => $this->site->get_fetch_progress() < '100' ? $this->site->get_fetch_progress() : null,
				'start_date' => $this->site->get( 'start_date' ),
				'end_date' => $this->site->get( 'end_date' ),
				'traffic_sources_enabled' => apply_filters( 'wpecp_traffic_sources_enabled', ! $this->is_production() ),
				'settings_url' => esc_url( admin_url( 'options-general.php?page=wpengine-content-performance.php' ) ),
				'plugin_url' => esc_url( plugins_url( 'js/', __FILE__ ) ),
				'is_enabled' => $this->site->is_enabled(),
				'custom_post_types' => $custom_post_types,
			);
			wp_localize_script( 'wpe-react', 'wpecp', $wpecp );
		}

		// Always load the application CSS, even if the site is disabled.
		wp_enqueue_style( 'wpe-react-css', $this->asset_url( 'js/app.min.css' ), array(), $build_string );

		wp_enqueue_style( 'wpecp-jquery-ui-style', '//ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/base/jquery-ui.css' );
	}

	/**
	 * Dequeue prototype.js: it modifies the global array prototype and causes issues.
	 * Dequeue datepicker: it requires prototype.
	 *
	 * @codeCoverageIgnore
	 */
	public function dequeue_conflicting_scripts( $hook ) {
		// Only enqueue these assets on the dashboard(s).
		if ( $this->dashboard_hook !== $hook ) {
			return;
		}

		wp_dequeue_script( 'datepicker' );
		wp_dequeue_script( 'prototype' );
	}

	/**
	 * Enqueue the error reporting scripts.
	 *
	 * @param  string $build_string The build string for the plugin.
	 */
	private function enqueue_reporting_scripts( $build_string ) {
		$raven_config = array(
			'release' => $build_string ? explode( '.', $build_string )[0] : false,
			'environment' => $this->is_production() ? 'production' : 'staging',
			'username' => Core::get_install_name(),
		);

		wp_enqueue_script( 'wpecp-raven-js' , 'https://cdn.ravenjs.com/3.16.0/raven.js', array(), '3.16.0' );
		wp_enqueue_script( 'wpecp-raven-custom', plugins_url( 'js/thirdparty/raven-custom.js', dirname( __FILE__ ) ), array( 'wpecp-raven-js' ) );
		wp_localize_script( 'wpecp-raven-custom', 'wpecpraven', $raven_config );

		if ( $this->is_production() ) {
			wp_enqueue_script( 'wpecp-fullstory', plugins_url( 'js/thirdparty/fullstory.js', dirname( __FILE__ ) ) );
			wp_localize_script( 'wpecp-fullstory', 'wpecpfullstory', Core::get_install_name() );
		}
	}

	/**
	 * Detect our development environment.
	 * @return boolean Returns true if we're in the Docker container.
	 */
	public function is_development() {
		$is_development = isset( $_ENV['SITE_ID'] ) && 'local-dev' === $_ENV['SITE_ID'];
		/**
		 * Determine if we're running in a development environment.
		 *
		 * @param boolean $is_development
		 */
		return apply_filters( 'wpecp_is_development', $is_development );
	}

	/**
	 * Build the correct URL for assets based on environment.
	 *
	 * @return string The full URL for the asset.
	 */
	public function asset_url( $path ) {
		if ( $this->is_development() ) {
			/**
			 * The URL to the webpack dev server.
			 *
			 * @param string $url
			 */
			$webpack_server_url = apply_filters( 'wpecp_webpack_server_url', 'http://localhost:8888/inc/' );
			return "$webpack_server_url$path";
		} else {
			return plugins_url( $path, __FILE__ );
		}
	}

	/**
	 * Detects sites using the production service.
	 * @return boolean Returns true if we're pointing to the production service.
	 */
	public function is_production() {
		return strpos( Service::service_url(), Service::service_url ) !== false;
	}

	/**
	 * Make the call to the connection endpoint on the settings page.
	 *
	 * @action current_screen
	 */
	public function check_connection() {
		// Only check for keys on the settings page.
		if ( get_current_screen()->id !== $this->settings_hook ) {
			return;
		}

		$this->connection->init();
	}

	/**
	 * Make the call to the sites endpoint on the WP Dashboard page.
	 *
	 * @action current_screen
	 */
	public function check_site() {
		// Only check for keys on the WP Dashboard.
		if ( 'dashboard' !== get_current_screen()->id ) {
			return;
		}

		$this->site->init();
	}

	/**
	 * Check if the plugin is properly configured after activation.
	 *
	 * @todo  This should be triggered based on the enabled status.
	 */
	public function plugins_page_notices() {
		// Only display this notice on the Plugins admin page.
		if ( get_current_screen()->id !== $this->plugins_hook ) {
			return;
		}

		$options = Core::get();

		if ( empty( $options['access_key'] ) || empty( $options['secret_key'] ) ) {
			$this->render_notice( 'Content Performance has been activated but is not yet ready for use. Please go to the <a href="' . admin_url( 'options-general.php?page=wpengine-content-performance.php' ) . '">' . __( 'settings page', 'wpecp' ) . '</a> for more information.' );
		}
	}

	/**
	 * Show notices on the settings page from the Service.
	 */
	public function settings_page_notices() {
		if ( get_current_screen()->id !== $this->settings_hook ) {
			return;
		}
		// Suppress settings saved notice.
		delete_transient( 'settings_errors' );

		if ( ! $this->connection->is_enabled() ) {
			$error = $this->connection->get_error();
			// Show a different notice if we're validating.
			if ( 'validation_validating_site' === $error['code'] ) {
				$this->render_notice( $error['text'], 'updated' );
			} else if ( ! $error ) {
				$this->render_notice( 'Content Performance encountered an unknown issue while updating your data. Please contact WP Engine Support.' );
			} else {
				$this->render_notice( $error['text'] );
			}
		} else if ( ! $this->rest_endpoint_enabled() ) {
			$this->render_notice( '<b>We were unable to reach our plugin, Content Performance might not work correctly. <br>Common reasons for this are:</b>
			<ol>
				<li>Another plugin has disabled the WP REST API.</li>
				<li>The WP REST API prefix has been changed.</li>
			</ol>', 'notice notice-warning' );
		} else if ( $this->connection->is_enabled() && 'site_fetching_historical_data' === $this->connection->get_error()['code'] ) {
			$this->render_notice( 'You may now begin using Content Performance, though your data is still being populated.', 'updated' );
		} else {
			// TODO:  Figure out how to only display on first successful save
			$this->render_notice( 'Content Performance has been successfully configured.', 'updated' );
		}
	}

	/**
	 * Detect if we're able to access our endpoint.
	 *
	 * @return boolean Returns true if the WP REST API is enabled.
	 */
	public function rest_endpoint_enabled() {
		$http = apply_filters( 'wpecp_wp_http', new \WP_Http() );
		$url = apply_filters( 'wpecp_rest_url', get_rest_url() . 'cpp/v1/status' );

		$options = Core::get();
		$auth = new Auth( $options['access_key'], $options['secret_key'] );

		$headers = $auth->sign( $url );
		$response = $http->get( $url, array( 'headers' => $headers ) );

		Debug::log( $response['response'] );
		return 200 === $response['response']['code'];
	}

	/**
	 * Used to print a notice to the screen.
	 *
	 * @codeCoverageIgnore
	 */
	public function render_notice( $message, $class = 'error' ) {
		echo "<div class=\"wrap\"><div class=\"$class\"><p>$message</p></div></div>";
	}

	/**
	 * Adds the 'Settings' link to the plugin on the plugins page.
	 */
	public function plugin_action_links( $links ) {
		$wpecp_links = array( '<a href="' . admin_url( 'options-general.php?page=wpengine-content-performance.php' ) . '">Settings</a>' );
		return array_merge( $links, $wpecp_links );
	}

	/**
	 * Adds the necessary menu pages
	 *
	 * @codeCoverageIgnore
	 */
	public function action_admin_menu() {
		add_menu_page( 'Content Performance', 'Content Performance', self::capability, 'content-performance', array( $this, 'wpe_reports_page' ), plugins_url( 'images/favicon.ico', __FILE__ ), 1 );
		$this->dashboard_hook = add_submenu_page( 'content-performance', 'Dashboard', 'Dashboard', self::capability, 'content-performance', array( $this, 'wpe_reports_page' ) );
		add_submenu_page( 'content-performance', 'Settings', 'Settings', 'manage_options', 'wpengine-content-performance.php', array( $this, 'options_page' ) );
		add_submenu_page( 'content-performance', 'Help', 'Help', self::capability, 'content-performance-help', array( $this, 'wpe_help_page' ) );
		$this->settings_hook = add_options_page( 'Content Performance', 'Content Performance', 'manage_options', 'wpengine-content-performance.php', array( $this, 'options_page' ) );
	}

	/**
	 * Markup for the Options page
	 *
	 * @return null
	 */
	public function options_page() {
		?>
		<div class="wpengine-admin-page">
			<div class="wrap">
				<noscript><div class="error"><p>Content Performance requires JavaScript.</p></div></noscript>
				<h2>WP Engine Content Performance Settings</h2>
				<div id="wpecp-react-settings-component"></div>
			</div>
		</div>

		<?php
		// Make sure the administrator user has our capability.
		$r = get_role( 'administrator' );
		if ( ! array_key_exists( self::capability, $r->capabilities ) ) {
			$r->add_cap( self::capability );
		}
	}

	/**
	 * Register the settings options and validator used by this plugin.
	 *
	 * @action admin_init
	 * @codeCoverageIgnore
	 */
	public function register_settings() {
		register_setting( 'wpengine-content-performance', Core::config_option, array( $this, 'validate' ) );
	}

	/**
	 * Validate settings screen form. Execute authentication or sync steps when
	 * those buttons are pressed. Otherwise validate and save the form inputs.
	 *
	 * @param array $options - The input options to validate.
	 * @return array - The validated and sanitized options.
	 */
	public function validate( $options ) {
		global $wp_settings_errors;
		$current = Core::get();
		if ( ! is_array( $options ) ) {
			return $current;
		}

		// Sanitize options.
		$clean_options = filter_var_array( $options, array(
			'authenticate' => FILTER_SANITIZE_STRING,
			'retry' => FILTER_SANITIZE_STRING,
			'ga_profile_id' => FILTER_SANITIZE_STRING,
			'access_key' => FILTER_SANITIZE_STRING,
			'secret_key' => FILTER_SANITIZE_STRING,
			'default_date_range' => FILTER_SANITIZE_STRING,
			'default_published_date_range' => FILTER_SANITIZE_STRING,
		) );

		// Handle roles, which could potentially not be set during initial setup.
		$clean_options['roles'] = isset( $options['roles'] )
			? filter_var_array( $options['roles'], FILTER_SANITIZE_STRING )
			: array();

		if ( ! $clean_options ) {
			return $current;
		}

		extract( $clean_options );

		// Maybe we're setting up google authentication
		if ( $authenticate ) {
			// Redirect to the service
			$query_args = array(
				'callback_url' => admin_url( 'options-general.php?page=wpengine-content-performance.php' ),
				'base_url' => site_url(),
			);
			$service = Core::get_service();
			$url = $service->sign_url( 'authorization', $query_args );

			$redirect = wp_redirect( $url );
			exit( 0 );
		}

		if ( ! empty( $ga_profile_id ) ) {
			// Save the view ID
			$service = Core::get_service();
			$body = json_encode( array( 'site' => array( 'view_id' => $ga_profile_id ) ) );
			$args = array( 'method' => 'PUT', 'body' => $body, 'headers' => array( 'Content-Type' => 'application/json' ) );
			$service->dispatch( '', array(), $args );
		}

		// The retry button was pressed.
		if ( $retry ) {
			$service = Core::get_service();
			$args = array( 'method' => 'POST', 'body' => '', 'headers' => array( 'Content-Type' => 'application/json' ) );
			$service->dispatch( 'retries', array(), $args );
		}

		// Loop through the roles and configure add/remove our capability.
		foreach ( get_editable_roles() as $role => $value ) {
			// The administrator role has to have access.
			if ( 'administrator' === $role ) {
				continue;
			}
			$r = get_role( $role );
			if ( in_array( $role, $roles ) ) {
				$r->add_cap( self::capability );
			} else {
				$r->remove_cap( self::capability );
			}
		}

		// Save the settings locally.
		if ( isset( $access_key ) ) {
			$current['access_key'] = trim( $access_key );
		}
		if ( isset( $secret_key ) ) {
			$current['secret_key'] = trim( $secret_key );
		}
		if ( isset( $default_date_range ) ) {
			$current['default_date_range'] = $default_date_range;
		}
		if ( isset( $default_published_date_range ) ) {
			$current['default_published_date_range'] = $default_published_date_range;
		}
		return $current;
	}

	/**
	 * Contents of the dashboard widget
	 */
	public function dashboard_widget( /* $post, $callback_args */ $options = array() ) {
		if ( ! $this->site->enough_data() ) {
			?>
			<div class="contents-center">
				<p>Content Performance has been activated but is not yet ready for use.</p>
				<p>Please go to the settings page for more information.</p>
				<br class="clear" />
				<p><a class="button button-primary" href="<?php echo admin_url( 'options-general.php?page=wpengine-content-performance.php' ); ?>">View Settings</a></p>
				<br class="clear" />
			</div>
			<?php
		} else {
			?>
			<noscript><div class="error"><p>Content Performance requires JavaScript.</p></div></noscript>
			<div id="react-component"></div>
			<br class="clear" />
			<?php
			// Display this button only on the WP Dashbard.
			if ( 'dashboard' == get_current_screen()->id ) {
				?>
				<p><a class="button button-primary button-right" href="<?php echo esc_url( admin_url( 'admin.php?page=content-performance' ) ); ?>">View Full Report</a></p>
				<br class="clear" />
				<?php
			}
		}
	}

	/**
	 * Adds the Content Performance page to the admin menu.
	 */
	public function add_dashboard_widgets() {
		if ( current_user_can( self::capability ) ) {
			wp_add_dashboard_widget( 'wpecp_dashboard_widget', 'WP Engine Content Performance', array( $this, 'dashboard_widget' ), 'side', 'high' );
		}
	}

	/**
	 * Contents for the Content Performance page.
	 */
	public function wpe_reports_page() {
		echo '<div class="wrap">';
		$this->dashboard_widget();
		echo '</div>';
	}

	/**
	 * Redirect to the settings page if the plugin isn't configured.
	 *
	 * @action current_screen
	 * @todo Replace is_service_setup_complete() w/ is_plugin_setup_complete() after GA flow is complete.
	 */
	public function redirect_if_not_configured() {
		// Return if we're not on the dashboard page.
		if ( ! $this->dashboard_hook || get_current_screen()->id !== $this->dashboard_hook ) {
			return;
		}

		$options = Core::get();
		$access_key = $options['access_key'];
		$secret_key = $options['secret_key'];

		if ( empty( $access_key ) || empty( $secret_key ) ) {
			$this->redirect_to_settings();
			return;
		}
		// Make request to the service.
		$this->site->init();

		if ( ! $this->site->enough_data() ) {
			$this->redirect_to_settings();
			return;
		}
	}

	/**
	 * Redirect to the settings page.
	 *
	 * @codeCoverageIgnore
	 */
	public function redirect_to_settings() {
		wp_safe_redirect( admin_url( 'options-general.php?page=wpengine-content-performance.php' ) );
		exit;
	}

	/**
	 * Generic redirect.
	 *
	 * @codeCoverageIgnore
	 */
	public function redirect( $url ) {
		wp_redirect( $url );
		exit;
	}

	/**
	 * Make a request to the Content Performance service.
	 *
	 * @action wp_ajax_wpecp_service_request
	 * @action wp_ajax_wpecp_settings
	 */
	public function service_request() {
		// Make sure the nonce matches, use the current filter a the key.
		check_ajax_referer( current_filter(), 'nonce' );

		// TODO: Replace this with the role selected in the settings.
		if ( ! current_user_can( self::capability ) ) {
			wp_send_json_error( 'No access' );
		}

		// We can't do anything without an endpoint.
		if ( ! isset( $_REQUEST['endpoint'] ) ) {
			wp_send_json_error( 'Missing endpoint' );
		}
		// Grab and sanitize the endpoint.
		$endpoint = sanitize_text_field( $_REQUEST['endpoint'] );
		$params = array();
		// Sanitize our parameters.
		if ( isset( $_REQUEST['params'] ) ) {
			$params = array_map( 'sanitize_text_field', $_REQUEST['params'] );
			// Need to preserve ids.
			if ( isset( $_REQUEST['params']['wp_ids'] ) ) {
				$ids = $_REQUEST['params']['wp_ids'];
				$params['wp_ids'] = $ids;
			}
		}

		$service = Core::get_service();
		$response = $service->dispatch( $endpoint, $params, array( 'timeout' => 28 ) );

		if ( ! is_wp_error( $response ) ) {
			wp_send_json_success( $response );
		}

		// See WP_Error::get_error_message
		wp_send_json_error( $response->get_error_message() );
	}

	/**
	 * Update Content Performance
	 *
	 * @action wp_ajax_wpecp_update_plugin
	 */
	public function update_plugin() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_send_json_error( 'No access' );
		}

		// Set up update class.
		$this->hook_update();

		// Prevent redirection.
		remove_action( 'activated_plugin', array( $this, 'activation_actions' ) );
		remove_action( 'deactivate_' . Core::get_plugin_root(), array( $this, 'deactivation_actions' ) );

		// We don't really care about the result here.
		wp_send_json_success( $this->update->update() );
	}

	/**
	 * Download a CSV file from the Content Performance Service.
	 *
	 * @action admin_post_wpecp_download_csv
	 */
	public function download_csv() {
		// TODO: Replace with the capability from the settings.
		if ( ! current_user_can( self::capability ) ) {
			die( 'No access' );
		}

		$input = filter_var_array( $_REQUEST, array(
			'dimension' => FILTER_SANITIZE_STRING,
			'metric' => FILTER_SANITIZE_STRING,
			'startDate' => FILTER_SANITIZE_STRING,
			'endDate' => FILTER_SANITIZE_STRING,
			'publishedStartDate' => FILTER_SANITIZE_STRING,
			'publishedEndDate' => FILTER_SANITIZE_STRING,
			'customPostType' => FILTER_SANITIZE_STRING,
		) );

		$params = array(
			'metric' => $input['metric'],
			'start_date' => $input['startDate'],
			'end_date' => $input['endDate'],
			'published_start_date' => $input['publishedStartDate'],
			'published_end_date' => $input['publishedEndDate'],
			'limit' => '100',
		);

		if ( strlen( $input['customPostType'] ) > 0 ) {
			$params['custom_post_type'] = $input['customPostType'];
		}

		$service = Core::get_service();
		$url = $service->sign_url( 'dimensions/' . $input['dimension'] . '.csv', $params );

		$this->redirect( $url );
	}

	public function wpe_help_page() {
		$help_file = __DIR__  . '/docs/help.html';
		echo '<div class="wrap">';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=content-performance' ) ) . '">Back to WP Engine Content Performance</a>';
		echo '<h1>WP Engine Content Performance Help</h1>';
		if ( file_exists( $help_file ) ) {
			echo file_get_contents( $help_file );
		} else {
			echo 'There was a problem loading the help.';
		}
		echo '</div>';
	}

	public function cp_menu_order( $menu_order ) {
		$cp_menu_order = array();
		foreach ( $menu_order as $index => $item ) {
			if ( 'content-performance' != $item ) {
				$cp_menu_order[] = $item;
			}
			if ( 0 == $index ) {
				$cp_menu_order[] = 'content-performance';
			}
		}
		return $cp_menu_order;
	}
}
