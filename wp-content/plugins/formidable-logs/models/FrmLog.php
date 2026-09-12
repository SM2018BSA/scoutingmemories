<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}
/**
 * Class FrmLog.
 *
 * @since 1.0
 */
class FrmLog {

	/**
	 * Posttype name.
	 *
	 * @var string post_type
	 * @since 1.0
	 */
	public $post_type = 'frm_logs';

	/**
	 * Insert post and metadata for frmlogs.
	 *
	 * @param array<string> $values values.
	 *
	 * @since 1.0
	 *
	 * @return void
	 */
	public function add( $values ) {
		$this->maybe_init_wp_rewrite();

		$post = array(
			'post_type'    => $this->post_type,
			'post_title'   => $values['title'],
			'post_content' => $values['content'],
			'post_status'  => 'publish',
		);
		if ( isset( $values['excerpt'] ) ) {
			$post['post_excerpt'] = $values['excerpt'];
			$this->maybe_encode( $post['post_excerpt'] );
		}
		$this->maybe_encode( $post['post_content'] );

		$post_id = wp_insert_post( $post );

		if ( is_wp_error( $post_id ) && ! $post_id ) {
			return;
		}

		if ( isset( $values['fields'] ) ) {
			$this->add_custom_fields( $post_id, $values['fields'] );
		}
	}

	/**
	 * When wp_insert_post is called, if $wp_rewrite is not defined, WordPress will try to access "feeds" on null.
	 * To avoid this, we guarantee that $wp_rewrite is not null by initializing it first.
	 *
	 * @since 1.0
	 *
	 * @return void
	 */
	private function maybe_init_wp_rewrite() {
		global $wp_rewrite;

		if ( ! $wp_rewrite ) {
			$wp_rewrite = new WP_Rewrite(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$wp_rewrite->init();
		}
	}

	/**
	 * Add metadata to post.
	 *
	 * @param int           $post_id post id.
	 * @param array<string> $fields fields.
	 *
	 * @since 1.0
	 * @return void
	 */
	private function add_custom_fields( $post_id, $fields ) {
		foreach ( $fields as $name => $value ) {
			update_post_meta( $post_id, 'frm_' . $name, $value );
		}
	}

	/**
	 * Maybe encode.
	 *
	 * @param mixed $value value.
	 * @return void
	 */
	private function maybe_encode( &$value ) {
		if ( is_array( $value ) ) {
			$value = json_encode( $value );
		}
	}

	/**
	 * Prepare data for output.
	 *
	 * @param stdclass $custom_field custom field.
	 * @return mixed
	 */
	public static function prepare_meta_for_output( $custom_field ) {
		$value = $custom_field->meta_value;
		$value = self::maybe_decode( $value );
		if ( is_array( $value ) ) {
			if ( 'frm_request' == $custom_field->meta_key ) {
				if ( version_compare( phpversion(), '5.4', '>=' ) ) {
					$value = json_encode( $value, JSON_PRETTY_PRINT );
				} else {
					$value = json_encode( $value, true );
				}
			}
		}

		return self::prepare_for_output( $value );
	}

	/**
	 * Prepare output.
	 *
	 * @param mixed $value string.
	 * @return string
	 */
	public static function prepare_for_output( $value ) {
		$value = self::flatten_array( $value );
		$value = str_replace( array( '":"', '","', '{', '},' ), array( '": "', '", "', "\r\n{\r\n", "\r\n},\r\n" ), $value );
		return wpautop( strip_tags( $value ) );
	}

	/**
	 * Flatten a variable that may be multi-dimensional array
	 *
	 * @since 1.0
	 * @param mixed $original_value value.
	 *
	 * @return string
	 */
	private static function flatten_array( $original_value ) {
		if ( ! is_array( $original_value ) ) {
			return $original_value;
		}

		$final_value = '';
		foreach ( $original_value as $current_value ) {
			if ( is_array( $current_value ) ) {
				$final_value .= self::flatten_array( $current_value );
			} else {
				$final_value .= "\r\n" . $current_value;
			}
		}

		return $final_value;
	}

	/**
	 * Maybe decode.
	 *
	 * @param mixed $value value.
	 * @return mixed
	 */
	private static function maybe_decode( $value ) {
		return FrmAppHelper::maybe_json_decode( $value );
	}

	/**
	 * Destroy all frmlogs posts and metadata.
	 *
	 * @since 1.0.1
	 *
	 * @return void
	 */
	public function destroy_all() {
		if ( ! current_user_can( 'administrator' ) || ! wp_verify_nonce( FrmAppHelper::simple_get( '_wpnonce', '', 'sanitize_text_field' ), '-1' ) ) {
			$frm_settings = FrmAppHelper::get_settings();
			wp_die( esc_html( $frm_settings->admin_permission ) );
		}

		// Delete posts and metadata.
		try {
			$this->delete_frmlogs_posts_metadata( true );
			// @todo create appropriate notice.
			$message = 'destroy_all';
		} catch ( Exception $e ) {
			$message = false;
		}

		$url = admin_url( 'edit.php?post_type=frm_logs' );

		if ( $message ) {
			$url .= '&message=' . $message;
		}

		wp_safe_redirect( $url );
		die();
	}

	/**
	 * Try to fetch frmlogs postids and clear metadata.
	 *
	 * @param bool $force_delete check for force delete.
	 *
	 * @since 1.0.1
	 *
	 * @return true
	 */
	protected function delete_frmlogs_posts_metadata( $force_delete = false ) {
		global $wpdb;

		$prepare = $wpdb->prepare( "DELETE p.*, pm.* FROM {$wpdb->posts} as p LEFT OUTER JOIN {$wpdb->postmeta} as pm on (p.ID=pm.post_id) WHERE p.post_type=%s", $this->post_type );

		if ( ! $force_delete ) {
			/**
			 * Filter for keeping logs during cron purge, by default logs older than one week will be purge.
			 *
			 * @since 1.0.1
			 */
			$remains         = apply_filters( 'frm_logs_cron_remains', '-1 week' );
			$applicable_date = gmdate( 'Y-m-d H:i:s', strtotime( $remains ) );
			$prepare        .= $wpdb->prepare( 'AND post_date_gmt < %s', $applicable_date );
		}

		return $wpdb->query( $prepare ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Get all data for frmlogs posttype.
	 *
	 * @since 1.0.1
	 *
	 * @param int $page current page.
	 * @param int $page_size page size to fetch.
	 *
	 * @return array<int, array<string, mixed>>.
	 */
	public static function get_all_frmlogs( $page, $page_size = 1000 ) {
		$frmlogs = array();

		$frmlog_query = get_posts(
			array(
				'post_type'      => 'frm_logs',
				'post_status'    => 'any',
				'posts_per_page' => $page_size,
				'offset'         => ( $page - 1 ) * $page_size,
				'order'          => 'ASC',
			)
		);

		if ( ! empty( $frmlog_query ) ) {
			foreach ( $frmlog_query as $frmlog_post ) {
				$frmlogid             = $frmlog_post->ID;
				$frmlogs[ $frmlogid ] = array(
					'id'      => $frmlogid,
					'title'   => $frmlog_post->post_title,
					'content' => $frmlog_post->post_content,
					'fields'  => get_post_meta( $frmlogid ),
				);
			}
		}

		return $frmlogs;
	}

}
