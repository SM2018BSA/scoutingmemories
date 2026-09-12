<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

if ( class_exists( 'FrmBtspAppController' ) ) {
	return;
}

class FrmBtspAppController {

	public function __construct() {
		add_action( 'admin_init', array( $this, 'include_updater' ), 1 );
		add_action( 'frm_style_general_settings', array( __CLASS__, 'general_style_settings' ), 20 );
		add_action( 'frm_update_settings', array( __CLASS__, 'update_global_settings' ) );
		add_action( 'frm_field_options_form', array( __CLASS__, 'field_options' ), 10, 3 );
		add_filter( 'frm_default_field_opts', array( __CLASS__, 'default_field_opts' ), 10, 3 );

		add_action( 'frm_form_classes', array( __CLASS__, 'form_class' ) );
		add_filter( 'frm_form_fields_class', array( __CLASS__, 'form_fields_class' ) );
		add_filter( 'frm_cpt_field_classes', array( __CLASS__, 'form_fields_class' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'front_head' ) );
		add_filter( 'frm_checkbox_class', array( __CLASS__, 'inline_class' ), 10, 2 );
		add_filter( 'frm_radio_class', array( __CLASS__, 'inline_class' ), 10, 2 );
		add_filter( 'frm_form_replace_shortcodes', array( __CLASS__, 'form_html' ), 10, 2 );
		add_filter( 'frm_before_replace_shortcodes', array( __CLASS__, 'field_html' ), 30, 2 );

		add_filter( 'frm_field_classes', array( __CLASS__, 'field_classes' ), 10, 2 );
		add_filter( 'frm_submit_button_class', array( __CLASS__, 'submit_button' ) );
		add_filter( 'frm_back_button_class', array( __CLASS__, 'back_button' ) );

		add_filter( 'frm_ul_pagination_class', array( __CLASS__, 'pagination_class' ) );
		add_filter( 'frm_filter_view', array( __CLASS__, 'on_filter_view' ) );
	}

	public static function path() {
		return dirname( dirname( __FILE__ ) );
	}

	public function include_updater() {
		if ( ! class_exists( 'FrmAddon' ) ) {
			return;
		}

		include self::path() . '/models/FrmBtspUpdate.php';
		FrmBtspUpdate::load_hooks();

		if ( FrmAppHelper::is_admin_page( 'formidable' ) ) {
			$action = FrmAppHelper::get_param( 'frm_action' );
			if ( ! $action ) {
				if ( ! get_option( 'frm_bootstrap_options_migrated' ) ) {
					if ( $this->a_field_exists_that_needs_to_be_migrated() ) {
						add_filter( 'frm_message_list', 'FrmBtspAppController::migration_notice' );
					} else {
						update_option( 'frm_bootstrap_options_migrated', true, 'no' );
					}
				}
			} elseif ( 'frm_migrate_bootstrap_options' === $action ) {
				$this->migrate_bootstrap_options();
			} elseif ( 'edit' === $action ) {
				wp_register_style( 'bootstrap-glyphicons', plugins_url( 'css/bootstrap-glyphicons.min.css', dirname( __FILE__ ) ), array(), '3.3.7' );
				wp_enqueue_style( 'bootstrap-glyphicons' );
			}

			if ( 'successful_bootstrap_migration' === FrmAppHelper::get_param( 'message' ) ) {
				add_filter( 'frm_message_list', 'FrmBtspAppController::migration_success' );
			}
		}
	}

	/**
	 * Migrating from 1.02.02 to 1.03 requires that you migrate any Prepend/Append options
	 * Check first if there are any to avoid a useless warning
	 *
	 * @return bool
	 */
	private function a_field_exists_that_needs_to_be_migrated() {
		$fields = $this->get_fields_that_require_migration();
		return count( $fields ) > 0;
	}

	/**
	 * @since 1.03
	 * @param array $messages
	 * @return array
	 */
	public static function migration_notice( $messages ) {
		if ( ! FrmAppHelper::meets_min_pro_version( '4.05b' ) ) {
			$messages[] = 'You must update Formidable Forms before you lose any saved bootstrap options!';
			return $messages;
		}

		$href       = admin_url( 'admin.php?page=formidable&frm_action=frm_migrate_bootstrap_options' );
		$messages[] = 'Required Bootstrap Update! Prepend and append options will be lost if you save a form without updating! Click <a href="' . esc_url( $href ) . '">here</a> to update now.';
		return $messages;
	}

	/**
	 * @since 1.03
	 */
	public static function migration_success( $messages ) {
		$messages[] = 'Your bootstrap options have been successfully updated! Thank you for updating!';
		return $messages;
	}

	/**
	 * Add option to not load styling
	 *
	 * @param object $frm_settings
	 */
	public static function general_style_settings( $frm_settings ) {
		$css = FrmAppHelper::get_param( 'frm_btsp_css', '', 'post', 'sanitize_key' );
		if ( $css ) {
			$frm_settings->btsp_css = $css;
		} elseif ( ! isset( $frm_settings->btsp_css ) ) {
			$frm_settings->btsp_css = 'all';
		}

		if ( ! isset( $frm_settings->btsp_version ) ) {
			$frm_settings->btsp_version = '5';
		}

		include self::path() . '/views/style-settings.php';
	}

	/**
	 * @param array $params
	 * @return void
	 */
	public static function update_global_settings( $params ) {
		$frm_settings               = FrmAppHelper::get_settings();
		$frm_settings->btsp_css     = sanitize_key( $params['frm_btsp_css'] );
		$frm_settings->btsp_version = '5' === $params['frm_btsp_version'] ? '5' : '3';
	}

	public static function field_options( $field, $display, $values ) {
		if ( FrmAppHelper::meets_min_pro_version( '4.05' ) ) {
			return;
		}

		$default = array(
			'prepend' => '',
			'append'  => '',
		);
		if ( empty( $field['btsp'] ) || ! is_array( $field['btsp'] ) ) {
			$field['btsp'] = $default;
		} else {
			foreach ( $default as $k => $v ) {
				if ( ! isset( $field['btsp'][ $k ] ) ) {
					$field['btsp'][ $k ] = $v;
				}

				unset( $k, $v );
			}
		}

		$field_types = self::field_types();

		include self::path() . '/views/field-options.php';
	}

	public static function default_field_opts( $opts, $values, $field ) {
		$opts['btsp'] = '';
		return $opts;
	}

	public static function form_class( $form ) {

	}

	/**
	 * @param string|array $classes
	 * @return string|array
	 */
	public static function form_fields_class( $classes ) {
		if ( self::using_bootstrap_5() ) {
			// form-group is removed in Bootstrap 5.
			return $classes;
		}

		if ( is_array( $classes ) ) {
			$classes[] = 'form-group';
		} else {
			$classes .= ' form-group';
		}

		return $classes;
	}

	public static function front_head() {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) || ! class_exists( 'FrmAppHelper' ) ) {
			return;
		}

		$frm_settings = FrmAppHelper::get_settings();

		wp_register_script( 'frmbtsp', plugins_url( 'js/frmbtsp.js', dirname( __FILE__ ) ), array( 'formidable' ), '1.0', true );
		add_action( 'frm_enqueue_form_scripts', array( __CLASS__, 'enqueue_script' ) );

		if ( ! isset( $frm_settings->btsp_css ) ) {
			$frm_settings->btsp_css = 'all';
		}

		if ( 'none' === $frm_settings->btsp_css ) {
			return;
		}

		$bootstrap_css_version = self::get_current_bootstrap_css_version();
		if ( '3.3.7' === $bootstrap_css_version ) {
			$css_path = 'css/bootstrap-3-3-7.min.css';
		} else {
			$css_path = 'css/bootstrap.min.css';
		}

		wp_register_style( 'bootstrap', plugins_url( $css_path, dirname( __FILE__ ) ), array(), $bootstrap_css_version );

		if ( self::using_bootstrap_5() ) {
			// In Bootstrap 4, glyph icons were dropped.
			// Load them for Bootstrap 5 so we don't break the glyph icons.
			wp_register_style( 'bootstrap-glyphicons', plugins_url( 'css/bootstrap-glyphicons.min.css', dirname( __FILE__ ) ), array(), '3.3.7' );
		}

		self::add_inline_styles();

		if ( 'all' === $frm_settings->btsp_css ) {
			// load on all pages
			self::enqueue_style();
		} else {
			// load on form pages
			add_action( 'frm_enqueue_form_scripts', array( __CLASS__, 'enqueue_style' ) );
		}
	}

	/**
	 * Check the the Bootstrap version is set to 5.
	 *
	 * @since 2.0
	 *
	 * @return bool False when Bootstrap 3 is active.
	 */
	private static function using_bootstrap_5() {
		return '5.0.2' === self::get_current_bootstrap_css_version();
	}

	/**
	 * Check global settings for the target bootstrap version.
	 *
	 * @since 2.0
	 *
	 * @return string
	 */
	private static function get_current_bootstrap_css_version() {
		$settings          = FrmAppHelper::get_settings();
		$bootstrap_version = isset( $settings->btsp_version ) ? $settings->btsp_version : '5';
		return '3' === $bootstrap_version ? '3.3.7' : '5.0.2';
	}

	/**
	 * We don't have a dedicated CSS file yet for adding extra Bootstrap CSS.
	 * So inline it for now until this gets too big and requires a new file.
	 *
	 * @since 2.0
	 *
	 * @return void
	 */
	private static function add_inline_styles() {
		wp_add_inline_style(
			'bootstrap',
			self::get_inline_pagination_style()
				. self::get_inline_radio_button_fix()
				. self::add_pagination_rules_for_bootstrap_5()
				. self::remove_margin_from_form_control()
		);
	}

	/**
	 * Get style rules for the ... pagination item.
	 * We want it to appear similar to the other buttons
	 * and floating in the proper position.
	 *
	 * @since 2.0
	 *
	 * @return string
	 */
	private static function get_inline_pagination_style() {
		if ( self::using_bootstrap_5() ) {
			// Bootstrap 5 doesn't require the float rule.
			return 'ul.pagination li.dots.disabled {
				padding: 6px 8px;
				background-color: #fff;
				border: 1px solid #ddd;
			}';
		}
		return 'ul.pagination li.dots.disabled {
			float: left;
			padding: 6px 8px;
			margin: 0 0 0 -1px;
			background-color: #fff;
			border: 1px solid #ddd;
		}';
	}

	/**
	 * Remove a padding-left: 20px rule on radio button labels when a radio button is inline.
	 *
	 * @since 2.0
	 *
	 * @return string
	 */
	private static function get_inline_radio_button_fix() {
		if ( self::using_bootstrap_5() ) {
			// This fix is only necessary for Bootstrap 3.
			return '';
		}
		return '.frm_radio.radio-inline label { padding-left: 0; }';
	}

	/**
	 * Overwrite a couple Formidable pagination styles when using Bootstrap 5.
	 *
	 * @param x.x
	 *
	 * @return string
	 */
	private static function add_pagination_rules_for_bootstrap_5() {
		if ( ! self::using_bootstrap_5() ) {
			return '';
		}
		return '
		.frm_pagination_cont ul.frm_pagination {
			display: flex;
		}
		.frm_pagination_cont ul.frm_pagination > li {
			margin: 0;
		}';
	}

	/**
	 * @since 2.0
	 *
	 * @return string
	 */
	private static function remove_margin_from_form_control() {
		if ( ! self::using_bootstrap_5() ) {
			// In Bootstrap 3, the margin-left also needs to be 0.
			return '.input-group .form-control {
				margin-left: 0;
				margin-right: 0;
			}';
		}
		return '.input-group .form-control {
			margin-right: 0;
		}';
	}

	public static function enqueue_style() {
		wp_enqueue_style( 'bootstrap' );

		if ( self::using_bootstrap_5() ) {
			wp_enqueue_style( 'bootstrap-glyphicons' );
		}
	}

	public static function enqueue_script() {
		wp_enqueue_script( 'frmbtsp' );
	}

	public static function inline_class( $class, $field ) {
		$type = $field['type'];

		if ( 'data' === $field['type'] ) {
			$type = $field['data_type'];
		} elseif ( 'lookup' === $field['type'] ) {
			$type = $field['data_type'];
		}

		if ( isset( $field['align'] ) && 'inline' === $field['align'] ) {
			$class .= ' ' . $type . '-inline';
		}

		$class .= ' ' . $type;

		return $class;
	}

	public static function form_html( $html, $form ) {
		$html = str_replace( 'frm_submit', 'form-group frm_submit', $html );

		if ( self::using_bootstrap_5() ) {
			$html = str_replace( 'class="frm_prev_page', 'class="frm_prev_page btn btn-secondary', $html );
		} else {
			$html = str_replace( 'class="frm_prev_page', 'class="frm_prev_page btn btn-default', $html );
		}

		return $html;
	}

	private static function field_types() {
		return array( 'phone', 'number', 'text', 'email', 'url', 'date', 'image', 'tag', 'password', 'select' );
	}

	public static function field_html( $html, $field ) {
		$class = '[required_class] form-group';
		self::prepend( $field, $html );
		$html = str_replace( 'frm_input_group ', 'frm_input_group input-group ', $html );

		if ( self::using_bootstrap_5() ) {
			// In Bootstrap 4 they dropped input-group-addon, and help-block.
			// In Bootstrap 4 .control-label was renamed to .col-form-label.
			// In Bootstrap 4 .help-block was replaced with .form-text.
			// In Bootstrap 5 labels now require form-label.
			// Keep the input-group-addon class for backward compatibilty.
			// Our documentation shows examples using .input-group-addon.
			$html = str_replace( 'frm_inline_box', 'frm_inline_box input-group-text input-group-addon', $html );
			$html = str_replace( 'frm_primary_label', 'frm_primary_label col-form-label form-label', $html );
			$html = str_replace( 'frm_description', 'frm_description form-text', $html );
		} else {
			$html = str_replace( 'frm_inline_box', 'frm_inline_box input-group-addon', $html );
			$html = str_replace( 'frm_primary_label', 'frm_primary_label control-label', $html );
			$html = str_replace( 'frm_description', 'frm_description help-block', $html );
		}

		$html = str_replace( '[required_class]', $class, $html );
		return $html;
	}

	/**
	 * @since 1.03
	 */
	private static function prepend( $field, &$html ) {
		$has_prepend = ! empty( $field['prepend'] );
		$has_append  = ! empty( $field['append'] );
		$is_btsp     = ! empty( $field['btsp'] ) && is_array( $field['btsp'] );

		if ( $has_prepend || $has_append || ! $is_btsp ) {
			return;
		}

		$is_field_type = in_array( $field['type'], self::field_types(), true );
		$has_prepend   = ! empty( $field['btsp']['prepend'] );
		$has_append    = ! empty( $field['btsp']['append'] );

		if ( ! $is_field_type || ( ! $has_prepend && ! $has_append ) ) {
			return;
		}

		preg_match_all( "/\[(input)\b(.*?)(?:(\/))?\]/s", $html, $matches, PREG_PATTERN_ORDER );
		foreach ( $matches[0] as $match_key => $val ) {
			$html = str_replace( $val, '<div class="input-group"' . self::get_field_size( $field ) . '>' . $val . '</div>', $html );
		}

		if ( $has_prepend ) {
			$use_class = self::using_bootstrap_5() ? 'input-group-text input-group-addon' : 'input-group-addon';
			$html = str_replace( '[input', '<span class="' . esc_attr( $use_class ) . '">' . $field['btsp']['prepend'] . '</span> [input', $html );
		}

		if ( $has_append ) {
			preg_match_all( '/\[input\b(.*?)(?:(\/))?\]/s', $html, $matches, PREG_PATTERN_ORDER );
			$input = '[input]';
			if ( isset( $matches[0] ) && isset( $matches[0][0] ) ) {
				$input = $matches[0][0];
			}
			$use_class = self::using_bootstrap_5() ? 'input-group-text input-group-addon' : 'input-group-addon';
			$html = str_replace( $input, $input . ' <span class="' . $use_class . '">' . $field['btsp']['append'] . '</span>', $html );
		}
	}

	private static function get_field_size( $field ) {
		if ( empty( $field['size'] ) ) {
			return '';
		}

		if ( is_numeric( $field['size'] ) ) {
			$field['size'] .= 'px';
		}

		return ' style="width:' . esc_attr( $field['size'] ) . '"';
	}

	public static function field_classes( $class, $field ) {
		if ( ! in_array( $field['type'], array( 'radio', 'checkbox', 'data', 'file', 'scale', 'lookup' ), true ) ) {
			$class .= ' form-control';
		} elseif ( 'data' === $field['type'] && isset( $field['data_type'] ) && 'select' === $field['data_type'] ) {
			$class .= ' form-control';
		} elseif ( 'lookup' === $field['type'] && isset( $field['data_type'] ) && 'select' === $field['data_type'] ) {
			$class .= ' form-control';
		}

		return $class;
	}

	public static function submit_button( $class ) {
		if ( self::using_bootstrap_5() ) {
			$class[] = 'btn btn-secondary';
		} else {
			$class[] = 'btn btn-default';
		}
		return $class;
	}

	public static function back_button( $class ) {
		$class[] = 'btn';
		return $class;
	}

	public static function pagination_class( $class ) {
		if ( is_array( $class ) ) {
			$class[] = 'pagination';
		} else {
			$class .= ' pagination';
		}
		return $class;
	}

	/**
	 * Move prepend/append options into Formidable.
	 *
	 * @since 1.03
	 */
	private function migrate_bootstrap_options() {
		if ( ! FrmAppHelper::meets_min_pro_version( '4.05' ) ) {
			return;
		}

		$fields = $this->get_fields_that_require_migration();

		if ( ! $fields ) {
			$this->redirect_with_bootstrap_migration_success_message();
			return;
		}

		foreach ( $fields as $field ) {
			$options = $field->field_options;
			FrmAppHelper::unserialize_or_decode( $options );

			$bootstrap_options = $options['btsp'];
			$update_options    = array();

			if ( ! empty( $bootstrap_options['prepend'] ) ) {
				$update_options['prepend'] = $bootstrap_options['prepend'];
			}

			if ( ! empty( $bootstrap_options['append'] ) ) {
				$update_options['append'] = $bootstrap_options['append'];
			}

			if ( $update_options ) {
				unset( $options['btsp'] );
				FrmField::update( $field->id, array( 'field_options' => array_merge( $options, $update_options ) ) );
			}
		}

		$this->redirect_with_bootstrap_migration_success_message();
	}

	private function get_fields_that_require_migration() {
		$query  = array(
			'field_options like'     => '"btsp";a:',
			'field_options not like' => '"btsp";a:2:{s:7:"prepend";s:0:"";s:6:"append";s:0:"";',
		);
		$fields = FrmDb::get_results( 'frm_fields', $query, 'id, field_options' );
		return array_filter( $fields, array( $this, 'field_requires_migration' ) );
	}

	/**
	 * @param object $field
	 * @return bool
	 */
	private function field_requires_migration( $field ) {
		$options = $field->field_options;
		FrmAppHelper::unserialize_or_decode( $options );
		return isset( $options['btsp'] );
	}

	protected function redirect_with_bootstrap_migration_success_message() {
		update_option( 'frm_bootstrap_options_migrated', true, 'no' );
		wp_safe_redirect( admin_url( 'admin.php?page=formidable&message=successful_bootstrap_migration' ) );
	}

	/**
	 * Maybe add a filter when filtering a view.
	 * If the view has pagination, we'll modify the HTML to inject Bootstrap classes.
	 *
	 * @since 2.0
	 *
	 * @param mixed $view
	 * @return mixed
	 */
	public static function on_filter_view( $view ) {
		if ( ! is_object( $view ) || ! self::using_bootstrap_5() ) {
			return $view;
		}
		if ( ! isset( $view->frm_page_size ) || ! is_numeric( $view->frm_page_size ) ) {
			return $view;
		}

		add_filter(
			'frm_after_display_content',
			/**
			 * @param string  $content
			 * @param WP_Post $filtered_view The view currently being filtered through frm_after_display_content.
			 * @param string  $context 'all' or 'one'.
			 * @param WP_post $view The view we want to modify the content for.
			 * @return string
			 */
			function( $content, $filtered_view, $context ) use ( $view ) {
				if ( $filtered_view->ID !== $view->ID || 'all' !== $context ) {
					return $content;
				}
				return self::modify_pagination_classes_in_view( $content );
			},
			10,
			3
		);

		return $view;
	}

	/**
	 * Apply a few required classes to Formidable pagination.
	 * The <li> elements need a page-item class.
	 * The <a> elements children of the <li> elements require a page-link class.
	 *
	 * @since 2.0
	 *
	 * @param string $content
	 * @return string
	 */
	private static function modify_pagination_classes_in_view( $content ) {
		$pagination_start = strpos( $content, '<ul class="frm_pagination' );
		if ( false === $pagination_start ) {
			return $content;
		}

		$pagination_end = strpos( $content, '</ul>', $pagination_start );
		if ( false === $pagination_end ) {
			return $content;
		}

		$length     = $pagination_end - $pagination_start;
		$pagination = substr( $content, $pagination_start, $length );

		// Add the class to the inactive list elements.
		$modified_pagination = str_replace(
			'<li class=""',
			'<li class="page-item"',
			$pagination
		);

		// Add the class to the active list element.
		$modified_pagination = str_replace(
			'<li class="active"',
			'<li class="active page-item"',
			$modified_pagination
		);

		// Add the required class to the anchor tags.
		$modified_pagination = str_replace(
			'<a href="',
			'<a class="page-link" href="',
			$modified_pagination
		);

		return str_replace( $pagination, $modified_pagination, $content );
	}
}
