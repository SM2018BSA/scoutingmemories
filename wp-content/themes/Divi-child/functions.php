<?php
/**
 * Created by PhpStorm.
 * User: Roger Ellis
 * Date: 12/17/2017
 * Time: 4:44 PM
 */

add_action( 'wp_enqueue_scripts', 'enqueue_parent_styles' );

function enqueue_parent_styles() {
	wp_enqueue_style( 'parent-style', get_template_directory_uri().'/style.css' );
}

add_filter('frm_use_embedded_form_actions', 'frm_trigger_embedded_form_actions', 10, 2);
function frm_trigger_embedded_form_actions( $trigger_actions, $args ) {
	if ( $args['form']->id == 9 ) {
		$trigger_actions = true;
	}
	return $trigger_actions;
}