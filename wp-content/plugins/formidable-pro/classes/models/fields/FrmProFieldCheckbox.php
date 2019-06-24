<?php

/**
 * @since 3.0
 */
class FrmProFieldCheckbox extends FrmFieldCheckbox {

	protected function field_settings_for_type() {
		$settings = parent::field_settings_for_type();

		$settings['read_only'] = true;
		$settings['default_value'] = true;

		FrmProFieldsHelper::fill_default_field_display( $settings );
		return $settings;
	}

	/**
	 * @since 4.0
	 * @param array $args - Includes 'field', 'display', and 'values'
	 */
	public function show_extra_field_choices( $args ) {
		$field = $args['field'];
		if ( isset( $field['post_field'] ) && $field['post_field'] == 'post_category' ) {
			return;
		}

		$hide_other = false;
		include( FrmProAppHelper::plugin_path() . '/classes/views/frmpro-fields/back-end/other-option.php' );
		include( FrmProAppHelper::plugin_path() . '/classes/views/frmpro-fields/back-end/separate-values.php' );
	}
}
