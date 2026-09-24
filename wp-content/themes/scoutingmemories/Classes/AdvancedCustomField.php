<?php


class AdvancedCustomField {

	public $field_id;
	public $field;

	public function __construct( $field_id ) {

		$this->field_id = $field_id;
		$this->field    = acf_get_field( $field_id );

	}


	public static function update_select( $name, $value, $selector ) {
		$field = acf_get_field( $selector );
		$field['choices']["$name"] = wp_unslash( $value );
		acf_update_field( $field );
		return;
	}
}