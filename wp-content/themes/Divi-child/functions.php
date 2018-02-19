<?php
/**
 * Created by PhpStorm.
 * User: Roger Ellis
 * Date: 12/17/2017
 * Time: 4:44 PM
 */

add_action( 'wp_enqueue_scripts', 'enqueue_parent_styles' );

function enqueue_parent_styles() {
	wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
}

add_action( 'frm_after_create_entry', 'add_council', 30, 2 );
function add_council( $entry_id, $form_id ) {
	if ( $form_id == 9 ) { //replace 5 with the id of the form


		$args = array();
		if ( isset( $_POST['item_meta'][73] ) ) //replace 30 and 31 with the appropriate field IDs from your form
		{
			$args['council_name'] = $_POST['item_meta'][73];
		} //change 'data1' to the named parameter to send
		if ( isset( $_POST['item_meta'][79] ) ) {
			$args['council_num'] = $_POST['item_meta'][79];
		} //change 'data2' to whatever you need

		global $wpdb;
		$row = $wpdb->get_results( "SELECT meta_value FROM bsd_postmeta WHERE meta_id = 448" );

		$wpdb->flush();

		$array = unserialize( $row[0]->meta_value );


		print_r( $row[0]->meta_value );
		echo '<pre>';
		echo '<br /><br /> --------------------------<br /><br />';

		$array['choices'] = array_merge( $array['choices'], [ $args['council_name'] => $args['council_name'] ] );

		$meta_value = serialize( $array );
		echo '<pre>';
		echo '<br /><br /> --------------------------<br /><br />';

		$tablename = 'bsd_postmeta';

		$sql = $wpdb->prepare(
			"
			UPDATE $tablename 
			SET meta_value = '$meta_value'
			WHERE meta_id = %d
			
			", "448" );

	

		echo 'sql: ';
		print_r( $sql );





		echo 'return code: ';
		 $rez = $wpdb->query( $sql ) ;
		var_dump($rez);
		if ($wpdb->last_error) {
			echo 'You done bad! ' . $wpdb->last_error;
		}



		print_r( $array['choices'] );

		echo $args['council_name'];
		die();

		echo '</pre>';

		//add_post_meta( 252, '$meta_key', '$meta_value', true );

		//$result = wp_remote_post('http://example.com', array('body' => $args));
	}
}