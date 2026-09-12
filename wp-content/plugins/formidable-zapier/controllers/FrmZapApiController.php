<?php

class FrmZapApiController {

	/**
	 * @var int $timeout
	 */
	public static $timeout = 10;

	/**
	 * @return void
	 */
	public static function load_hooks() {
		$uri = self::get_server_value( 'REQUEST_URI' );
		if ( false !== strpos( $uri, '/frm-api/' ) ) {
			add_action( 'wp_loaded', 'FrmZapApiController::api_route' );
		}

		add_action( 'frm_trigger_zapier_action', 'FrmZapApiController::send_entry_to_zapier', 10, 4 );
	}

	/**
	 * Get the entry array from the entry and call send_to_zapier.
	 *
	 * @since 2.0
	 *
	 * @param WP_Post  $action
	 * @param stdClass $entry
	 * @param stdClass $form
	 * @param string   $event
	 * @return void
	 */
	public static function send_entry_to_zapier( $action, $entry, $form, $event ) {
		$body = self::get_entry_array( $entry );
		self::send_to_zapier( $body, $action, $event );
	}

	/**
	 * Get the form, action, and entry objects from entry and form ID and pass it on.
	 *
	 * @since 2.0
	 *
	 * @param int|string $entry_id
	 * @param int|string $form_id
	 * @param string     $event
	 * @return void
	 */
	private static function send_entry( $entry_id, $form_id, $event ) {
		$form = FrmForm::getOne( $form_id );
		$entry = FrmEntry::getOne( $entry_id );
		$action = FrmFormAction::get_action_for_form( $form_id, 'zapier' );
		self::send_entry_to_zapier( $action, $entry, $form, $event );
	}

	/**
	 * Remote POST the entry array to the Zapier WebHook contained in $action
	 *
	 * @since 2.0
	 *
	 * @param array   $body Entry data.
	 * @param WP_Post $action a FrmZapAction object
	 * @param string  $event
	 * @return void
	 */
	private static function send_to_zapier( $body, $action, $event ) {
		$headers = array();
		if ( empty( $body ) ) {
			$headers['X-Hook-Test'] = 'true';
		}

		$arg_array = array(
			'body'      => json_encode( $body ),
			'timeout'   => self::$timeout,
			'sslverify' => false,
			'ssl'       => true,
			'headers'   => $headers,
		);

		$response = wp_remote_post( $action->post_content['zap_url'], $arg_array );
		$processed = self::process_response( $response );

		$log_args = array(
			'url'       => $action->post_content['zap_url'],
			'request'   => $arg_array,
			'processed' => $processed,
			'entry'     => $body['id'],
			'response'  => $response,
			'event'     => $event,
			'action'    => $action,
		);

		self::log_results( $log_args );

		do_action( 'frm_zap_sent', $log_args );
	}

	/**
	 * @param array|WP_Error $response
	 * @return array
	 */
	private static function process_response( $response ) {
		$body      = wp_remote_retrieve_body( $response );
		$processed = array(
			'message' => '',
			'code'    => 'FAIL',
		);
		if ( is_wp_error( $response ) ) {
			$processed['message'] = $response->get_error_message();
		} elseif ( $body == 'error' || is_wp_error( $body ) ) {
			$processed['message'] = __( 'You had an HTTP connection error', 'frmzap' );
		} elseif ( isset( $response['response'] ) && isset( $response['response']['code'] ) ) {
			$processed['code'] = $response['response']['code'];
			$processed['message'] = $response['body'];
		}

		return $processed;
	}

	/**
	 * Write a message to the FrmLog (if it exists).
	 *
	 * @since 2.0
	 *
	 * @param array $atts Values to print to the log.
	 * @return void
	 */
	private static function log_results( $atts ) {
		if ( ! class_exists( 'FrmLog' ) ) {
			return;
		}

		$content = $atts['processed'];
		$message = isset( $content['message'] ) ? $content['message'] : '';

		$headers = '';
		self::array_to_list( $atts['request']['headers'], $headers );

		$log = new FrmLog();
		$log->add(
			array(
				'title'   => $atts['action']->post_title,
				'content' => (array) $atts['response'],
				'fields'  => array(
					'entry'   => $atts['entry'],
					'action'  => $atts['action']->ID,
					'code'    => isset( $content['code'] ) ? $content['code'] : '',
					'message' => $message,
					'url'     => $atts['url'],
					'request' => $atts['request']['body'],
					'headers' => $headers,
				),
			)
		);
	}

	/**
	 * @param array  $array
	 * @param string $list
	 * @return void
	 */
	private static function array_to_list( $array, &$list ) {
		foreach ( $array as $k => $v ) {
			$list .= "\r\n" . $k . ': ' . $v;
		}
	}

	/**
	 * @param stdClass $entry
	 * @return array
	 */
	private static function get_entry_array( $entry ) {
		if ( ! method_exists( 'FrmEntriesController', 'show_entry_shortcode' ) ) {
			return array();
		}

		add_filter( 'frm_date_format', 'FrmZapApiController::set_date_format' );
		$meta = FrmEntriesController::show_entry_shortcode(
			array(
				'format'        => 'array',
				'include_blank' => true,
				'id'            => $entry->id,
				'user_info'     => false,
				'entry'         => $entry,
			)
		);

		$data = maybe_unserialize( $entry->description );
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		$entry_array = array(
			'id'         => $entry->id,
			'ip'         => $entry->ip,
			'browser'    => isset( $data['browser'] ) ? $data['browser'] : '',
			'referrer'   => isset( $data['referrer'] ) ? $data['referrer'] : '',
			'user_id'    => FrmFieldsHelper::get_user_display_name( $entry->user_id, 'user_login' ),
			'form_id'    => $entry->form_id,
			'is_draft'   => $entry->is_draft,
			'updated_by' => FrmFieldsHelper::get_user_display_name( $entry->updated_by, 'user_login' ),
			'post_id'    => $entry->post_id,
			'key'        => $entry->item_key,
			'created_at' => get_date_from_gmt( $entry->created_at ),
			'updated_at' => get_date_from_gmt( $entry->updated_at ),
		);

		if ( is_array( $meta ) ) {
			foreach ( $meta as $k => $m ) {
				$is_id = is_numeric( $k );
				$this_key = $k;
				$other_key = $is_id ? FrmField::get_key_by_id( $k ) : FrmField::get_id_by_key( $k );
				if ( $is_id ) {
					$other_key = 'x' . $other_key;
				} else {
					$this_key = 'x' . $this_key;
				}

				$entry_array[ $this_key ] = $m;

				if ( $other_key ) {
					$entry_array[ $other_key ] = $m;
				}

				unset( $k, $m );
			}
		}

		return (array) apply_filters( 'frmzap_entry_array', $entry_array );
	}

	/**
	 * Send dates in Y-m-d format for maximum compatibility
	 *
	 * @since 1.0.1
	 *
	 * @return string
	 */
	public static function set_date_format() {
		return 'Y-m-d';
	}

	/**
	 * @return void
	 */
	public static function api_route() {
		// allow without API key for testing
		if ( ! is_user_logged_in() || ! current_user_can( 'administrator' ) ) {
			self::setup_basic_auth();

			error_reporting( 0 );

			self::check_api_key();

			$admins = new WP_User_Query(
				array(
					'role'    => 'Administrator',
					'number'  => 1,
					'fields'  => 'ID',
					'orderby' => 'ID',
					'order'   => 'ASC',
				)
			);
			if ( empty( $admins ) ) {
				return;
			}

			$admin_users = $admins->results;
			$user = reset( $admin_users );
			$user = get_userdata( $user );
		} else {
			$user = wp_get_current_user();
		}

		header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
		header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', mktime( gmdate( 'H' ) + 2, gmdate( 'i' ), gmdate( 's' ), gmdate( 'm' ), gmdate( 'd' ), gmdate( 'Y' ) ) ) . ' GMT' );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Pragma: no-cache' );

		// Only allow for v1 for now.
		$uri   = self::get_server_value( 'REQUEST_URI' );
		$split = explode( '/frm-api/v1/', strtok( $uri, '?' ), 2 );

		if ( count( $split ) < 2 ) {
			status_header( 400 );
			$response = array( 'error' => 'Invalid API URL' );
			echo json_encode( $response, 999 );
			die();
		}

		list( $url, $request ) = $split;
		$data = json_decode( file_get_contents( 'php://input' ) );
		$request = untrailingslashit( $request );
		if ( strpos( $request, '/' ) ) {
			list( $request, $atts ) = explode( '/', $request, 2 );
			$atts = explode( '/', $atts );
		} else {
			$atts = array();
		}
		if ( method_exists( 'FrmZapApiController', $request ) ) {
			$response = self::$request( $data, $user, $atts );
		} else {
			status_header( 409 );
			error_log( 'No route for ' . $request . ' ' . print_r( $atts, 1 ) );
			$response = array( 'error' => 'There is no endpoint for ' . $request );
		}

		echo json_encode( $response, 999 );
		die();
	}

	/**
	 * Servers running FastCGI may not have these values set
	 * php-cgi under Apache does not pass HTTP Basic user/pass to PHP by default
	 * For this workaround to work, add this line to your .htaccess file:
	 * RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
	 *
	 * @return void
	 */
	private static function setup_basic_auth() {
		if ( isset( $_SERVER['PHP_AUTH_USER'] ) ) {
			return;
		}

		self::maybe_check_http_auth();

		self::maybe_check_url_auth();

		if ( ! isset( $_SERVER['PHP_AUTH_USER'] ) ) {
			status_header( 403 );
			echo json_encode( array( 'error' => 'Your API key is missing. See the troubleshooting guide at https://formidableforms.com/knowledgebase/formidable-zapier/#kb-authorization-failed-your-api-key-is-missing' ) );
			die();
		}
	}

	/**
	 * If no Basic Auth API key was found, check if the htaccess placed it
	 * in another param.
	 *
	 * @since 1.06
	 * @return void
	 */
	private static function maybe_check_http_auth() {
		if ( isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) && ! isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			$_SERVER['HTTP_AUTHORIZATION'] = self::get_server_value( 'REDIRECT_HTTP_AUTHORIZATION' );
		}

		$http_auth = self::get_server_value( 'HTTP_AUTHORIZATION' );
		if ( strlen( $http_auth ) > 0 ) {
			list( $user, $pw ) = explode( ':', base64_decode( substr( $http_auth, 6 ) ) );
			if ( strlen( $user ) > 0 && strlen( $pw ) > 0 ) {
				$_SERVER['PHP_AUTH_USER'] = $user;
				$_SERVER['PHP_AUTH_PW']   = $pw;
			} else {
				unset( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] );
			}
		}
	}

	/**
	 * If no API key is found, maybe check the URL for ?frmzap=KEYHERE.
	 *
	 * @since 1.06
	 * @return void
	 */
	private static function maybe_check_url_auth() {
		if ( isset( $_SERVER['PHP_AUTH_USER'] ) ) {
			return;
		}

		/**
		 * Set to true if using a server without Basic Auth support.
		 *
		 * @since 1.06
		 */
		$check_url = apply_filters( 'frm_zap_url_auth', false );
		if ( $check_url && isset( $_SERVER['REQUEST_URI'] ) ) {
			$api_key = get_option( 'frm_api_key' );

			// Check if the url contains the api key.
			$uri = self::get_server_value( 'REQUEST_URI' );
			if ( strpos( $uri, $api_key ) ) {
				$_SERVER['PHP_AUTH_USER'] = $api_key;
			}
		}
	}

	/**
	 * @return void
	 */
	private static function check_api_key() {
		$api_key = get_option( 'frm_api_key' );
		$check_key = self::get_server_value( 'PHP_AUTH_USER' );
		if ( $api_key == $check_key ) {
			return;
		}

		$api_key = get_site_option( 'frm_api_key' ); // for reverse compatability
		if ( $api_key != $check_key ) {
			status_header( 403 );
			echo json_encode( array( 'error' => 'Your API key is incorrect: ' . $check_key ) );
			die();
		}
	}

	/**
	 * Get and sanitize a SERVER parameter.
	 *
	 * @since 1.06
	 *
	 * @param string $value The server parameter name.
	 * @return string
	 */
	private static function get_server_value( $value ) {
		return isset( $_SERVER[ $value ] ) ? wp_strip_all_tags( wp_unslash( $_SERVER[ $value ] ) ) : '';
	}

	/**
	 * Route /ping.
	 *
	 * @return array
	 */
	private static function ping() {
		return array(
			'status' => 'verified',
		);
	}

	/**
	 * Route /forms.
	 *
	 * @param object $data
	 * @return array
	 */
	private static function forms( $data ) {
		// published and not template
		$forms = array(
			'forms' => (array) FrmForm::getAll(
				array(
					'is_template' => 0,
					'status'      => 'published',
				)
			),
		);

		return $forms;
	}

	/**
	 * Route /form/:id.
	 * Get form HTML.
	 *
	 * @param object $data
	 * @param mixed  $user
	 * @param array  $atts
	 * @return array
	 */
	private static function form( $data, $user, $atts ) {
		if ( ! isset( $atts[0] ) ) {
			status_header( 409 );
			return array( 'error' => 'No form ID provided' );
		}
		$id = $atts[0];

		if ( is_numeric( $id ) ) {
			$shortcode_atts = array( 'id' => $id );
		} else {
			$shortcode_atts = array( 'key' => $id );
		}

		$form = FrmFormsController::get_form_shortcode( $shortcode_atts );
		return (array) $form;
	}

	/**
	 * Route /fields/:id
	 *
	 * @param object $data
	 * @param mixed  $user
	 * @param array  $atts
	 * @return array
	 */
	private static function fields( $data, $user, $atts ) {
		if ( ! isset( $atts[0] ) ) {
			status_header( 409 );
			return array( 'error' => 'No form ID provided' );
		}
		$id = $atts[0];

		$where = array();
		if ( is_numeric( $id ) ) {
			$where = array( 'fi.form_id' => $id );
		} else {
			$where = array( 'fi.form_key' => $id );
		}
		$fields = array( 'fields' => (array) FrmField::getAll( $where, 'field_order' ) );
		return $fields;
	}

	/**
	 * Route /zap_fields/:id.
	 * Get custom fields in Zapier format.
	 *
	 * @param object $data
	 * @param mixed  $user
	 * @param array  $atts
	 * @return array
	 */
	private static function zap_fields( $data, $user, $atts ) {
		if ( ! isset( $atts[0] ) ) {
			status_header( 409 );
			return array( 'error' => 'No form ID provided' );
		}
		$id = $atts[0];

		$fields     = FrmField::get_all_for_form( $id, '', 'include', 'include' );
		$zap_fields = array();
		$field_map  = array(
			'rte'      => 'text',
			'textarea' => 'text',
			'number'   => 'decimal',
			'date'     => 'datetime',
			'scale'    => 'int',
		);
		$field_map = (array) apply_filters( 'frmzap_map_fields', $field_map, $fields, $id );

		foreach ( $fields as $f ) {
			if ( in_array( $f->type, array( 'divider', 'captcha', 'break', 'html' ), true ) ) {
				continue;
			}

			$zap_fields[] = array(
				'type'      => ( isset( $field_map['type'] ) ? $field_map['type'] : 'unicode' ),
				'key'       => 'x' . $f->field_key, // Make sure key starts with an alpha.
				'required'  => (bool) $f->required,
				'label'     => $f->name,
				'help_text' => $f->description,
				'default'   => $f->default_value,
			);
		}
		return $zap_fields;
	}

	/**
	 * Route /subscribe.
	 * Save the url in a form action.
	 *
	 * @param object $data
	 * @param mixed  $user
	 * @return array
	 */
	private static function subscribe( $data, $user ) {
		if ( ! isset( $data->target_url ) ) {
			status_header( 409 );
			return array( 'error' => 'No target URL provided in ' . print_r( $data, 1 ) );
		}

		$atts = array(
			'zap_url'  => $data->target_url,
			'data'     => $data,
			'event'    => 'subscribe',
		);
		self::log_subscribe_status( $atts );

		// create form action to notify zap
		// Events: frm_after_create_entry, frm_after_update_entry, frm_after_delete_entry

		try {
			$trigger_name = $data->event;
			$zap_url = $data->target_url;
			$form_id = $data->form->form_id;
			$trigger_list = explode( '_', $trigger_name );

			if ( count( $trigger_list ) > 2 ) {
				$new_event = $trigger_list[2];
			} else {
				$new_event = 'create';
			}

			if ( $new_event === 'destroy' ) {
				$new_event = 'delete';
			}

			$action    = new FrmZapAction();
			$action_id = $action->create_new( $form_id, $zap_url, $new_event );

			if ( is_numeric( $action_id ) ) {
				status_header( 201 );

				if ( ! empty( $data->form->_zap_static_hook_code ) ) {
					add_post_meta( $action_id, 'frm_zapier_test_hook', 1, true );
				}
			}

			self::send_poll_entry( $form_id, $zap_url );

			return array( 'id' => $action_id );
		} catch ( Exception $e ) {
			status_header( 409 );
			error_log( 'Caught exception when creating zap: ' . $e->getMessage() );
			return array( 'error' => $e->getMessage() );
		}
	}

	/**
	 * Route /unsubscribe.
	 * Delete zap.
	 *
	 * @param object $data
	 * @return array
	 */
	private static function unsubscribe( $data ) {
		if ( ! isset( $data->target_url ) ) {
			status_header( 409 );
			return array( 'error' => 'No target URL provided in ' . print_r( $data, 1 ) );
		}

		$atts = array(
			'zap_url'  => $data->target_url,
			'data'     => $data,
			'event'    => 'unsubscribe',
		);
		self::log_subscribe_status( $atts );

		// replace / with % so that the final URL looks like:
		// '%https:%%hooks.zapier.com%hooks%standard%1923262%61b68f9cc8c3436395f18763d62046c0%%'
		// this is to avoid the escaping backslash issue with how the URL is stored in the db
		$searchable_url = '%"' . str_replace( '/', '%', $data->target_url ) . '"%';

		// post type: frm_form_actions, post_content LIKE url.
		global $wpdb;
		$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type=%s AND post_content LIKE %s", 'frm_form_actions', $searchable_url ) );

		if ( is_numeric( $post_id ) ) {
			wp_delete_post( $post_id );
		} else {
			status_header( 409 );
			return array( 'error' => 'No zap found to delete' );
		}

		return array( 'id' => $post_id );
	}

	/**
	 * Get an entry from a particular form and send the entry array to the specified WebHook.
	 *
	 * @since 2.0
	 *
	 * @param int    $form_id
	 * @param string $zap_url
	 * @return void
	 */
	private static function send_poll_entry( $form_id, $zap_url ) {
		global $wpdb;

		$entries = FrmEntry::getAll( array( 'form_id' => $form_id ), ' ORDER BY it.id DESC', 1 );
		if ( ! $entries ) {
			return;
		}

		$latest_entry = array_pop( $entries );

		$body = self::get_entry_array( $latest_entry );
		$atts = array(
			'zap_url'  => $zap_url,
			'data'     => $body,
			'event'    => 'poll',
		);
		self::log_subscribe_status( $atts );

		$headers = array();
		if ( empty( $body ) ) {
			$headers['X-Hook-Test'] = 'true';
		}

		$arg_array = array(
			'body'      => json_encode( $body ),
			'timeout'   => self::$timeout,
			'sslverify' => false,
			'ssl'       => true,
			'headers'   => $headers,
		);

		$response = wp_remote_post( $zap_url, $arg_array );
		$processed = self::process_response( $response );
	}

	/**
	 * Log subscribe, unsubscribe, and poll events if FrmLog exists.
	 *
	 * @since 2.0
	 *
	 * @param array $atts Message parameters.
	 * @return void
	 */
	private static function log_subscribe_status( $atts ) {
		if ( ! class_exists( 'FrmLog' ) ) {
			return;
		}

		$log = new FrmLog();
		$log->add(
			array(
				'title'   => 'Zapier: ' . $atts['event'],
				'content' => (array) $atts['data'],
				'fields'  => array(
					'url'     => $atts['zap_url'],
				),
			)
		);
	}

	/**
	 * Get current filepath.
	 *
	 * @since 2.0
	 *
	 * @return string
	 */
	public static function path() {
		return dirname( dirname( __FILE__ ) );
	}

	/**
	 * Log a quick message if FrmLog exists.
	 *
	 * @since 2.0
	 *
	 * @param string $title
	 * @param string $msg
	 * @return void
	 */
	private static function log_debug( $title, $msg ) {
		if ( ! class_exists( 'FrmLog' ) ) {
			return;
		}

		$log = new FrmLog();
		$log->add(
			array(
				'title'   => $title,
				'content' => $msg,
			)
		);
	}

	/**
	 * @deprecated 2.0
	 *
	 * @param mixed $entry_id
	 * @param mixed $form_id
	 * @return void
	 */
	public static function send_new_entry( $entry_id, $form_id ) {
		_deprecated_function( __FUNCTION__, '2.0', 'send_entry_to_zapier' );
		self::send_entry( $entry_id, $form_id, 'create' );
	}

	/**
	 * @deprecated 2.0
	 *
	 * @param mixed $entry_id
	 * @param mixed $form_id
	 * @return void
	 */
	public static function send_updated_entry( $entry_id, $form_id ) {
		_deprecated_function( __FUNCTION__, '2.0', 'send_entry_to_zapier' );
		self::send_entry( $entry_id, $form_id, 'update' );
	}

	/**
	 * @deprecated 2.0
	 *
	 * @param mixed          $entry_id
	 * @param stdClass|false $entry
	 * @return void
	 */
	public static function send_deleted_entry( $entry_id, $entry = false ) {
		_deprecated_function( __FUNCTION__, '2.0', 'send_entry_to_zapier' );
		if ( ! $entry ) {
			$entry = FrmEntry::getOne( $entry_id );
			if ( ! $entry ) {
				return;
			}
		}

		$form_id = $entry->form_id;

		self::send_entry( $entry_id, $form_id, 'delete' );
	}
}
