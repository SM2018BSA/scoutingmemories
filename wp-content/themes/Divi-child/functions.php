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

add_action( 'frm_after_create_entry', 'add_group', 30, 2 );
function add_group( $entry_id, $form_id ) {




	global $wpdb;

	// Add a Council
	if ( $form_id == 9 ) {


		$field_id = 112; // hidden field id for the slug

		$row = $wpdb->get_results( "SELECT meta_value FROM bsd_postmeta WHERE meta_id = 448" );

		$args = array();
		if ( isset( $_POST['item_meta'][73] ) ) // field IDs from your form
		{
			$args['council_name'] = $_POST['item_meta'][73];
		}
		if ( isset( $_POST['item_meta'][79] ) ) {
			$args['council_num'] = $_POST['item_meta'][79];
		}

		$council_name = str_replace( ' ', '_', strtolower( $args['council_name'] ) );

		$council_name = $council_name . '_' . $args['council_num'];

		// add the council_name to the hidden field for the cat slug
		FrmEntryMeta::update_entry_meta( $entry_id, $field_id, '', $council_name );

		$array            = unserialize( $row[0]->meta_value );
		$array['choices'] = array_merge( $array['choices'], [ $council_name => $args['council_name'] ] );

		$meta_value = serialize( $array );


		$tablename = 'bsd_postmeta';


		$sql = $wpdb->prepare(
			"
			UPDATE $tablename 
			SET meta_value = '$meta_value'
			WHERE meta_id = %d
			
			", "448" );


		$wpdb->query( $sql );


		if ( $wpdb->last_error ) {
			echo "It's! not my fault! " . $wpdb->last_error;
		}




	}

	// Add a Lodge
	if ( $form_id == 8 ) {


		$row = $wpdb->get_results( "SELECT meta_value FROM bsd_postmeta WHERE meta_id = 447" );

		$field_id = 113; // hidden field id for the slug

		$args = array();
		if ( isset( $_POST['item_meta'][86] ) ) // field IDs from your form
		{
			$args['lodge_name'] = $_POST['item_meta'][86];
		}
		if ( isset( $_POST['item_meta'][89] ) ) {
			$args['lodge_num'] = $_POST['item_meta'][89];
		}

		$lodge_name = str_replace( ' ', '_', strtolower( $args['lodge_name'] ) );

		$lodge_name = $lodge_name . '_' . $args['lodge_num'];

		// add the lodge_name to the hidden field for the cat slug
		FrmEntryMeta::update_entry_meta( $entry_id, $field_id, '', $lodge_name );

		$array            = unserialize( $row[0]->meta_value );
		$array['choices'] = array_merge( $array['choices'], [ $lodge_name => $args['lodge_name'] ] );

		$meta_value = serialize( $array );


		$tablename = 'bsd_postmeta';

		$sql = $wpdb->prepare(
			"
			UPDATE $tablename 
			SET meta_value = '$meta_value'
			WHERE meta_id = %d
			", "447" );


		$wpdb->query( $sql );


		if ( $wpdb->last_error ) {
			echo "It's! not my fault! " . $wpdb->last_error;
		}


	}

	// Add a Camp
	if ( $form_id == 13 ) {


		$row = $wpdb->get_results( "SELECT meta_value FROM bsd_postmeta WHERE meta_id = 446" );

		$field_id = 108; // hidden field if for cat slug

		$args = array();
		if ( isset( $_POST['item_meta'][102] ) ) // field IDs from your form
		{
			$args['camp_name'] = $_POST['item_meta'][102];
		}

		$camp_name = str_replace( ' ', '_', strtolower( $args['camp_name'] ) );

		$camp_name = $camp_name . '_' . $entry_id;

		// add the lodge_name to the hidden field for the cat slug
		FrmEntryMeta::update_entry_meta( $entry_id, $field_id, '', $camp_name );

		$array            = unserialize( $row[0]->meta_value );
		$array['choices'] = array_merge( $array['choices'], [ $camp_name => $args['camp_name'] ] );

		$meta_value = serialize( $array );


		$tablename = 'bsd_postmeta';

		$sql = $wpdb->prepare(
			"
			UPDATE $tablename 
			SET meta_value = '$meta_value'
			WHERE meta_id = %d
			", "446" );


		$wpdb->query( $sql );


		if ( $wpdb->last_error ) {
			echo "It's! not my fault! " . $wpdb->last_error;
		}


	}


	// Add a Post
	if ( $form_id == 3 ) {

		// used to get my new posts ID
		$my_entry = FrmEntry::getOne( $entry_id );



		//  Get my slugs ////////////////////////
		$args = array();
		if ( isset( $_POST['item_meta'][121] ) ) // field IDs from your form
		{
			$args['council_id'] = $_POST['item_meta'][121];
		}
		if ( isset( $_POST['item_meta'][124] ) ) // field IDs from your form
		{
			$args['lodge_id'] = $_POST['item_meta'][124];
		}
		if ( isset( $_POST['item_meta'][122] ) ) // field IDs from your form
		{
			$args['camp_id'] = $_POST['item_meta'][122];
		}



		$council_slug = FrmProEntriesController::get_field_value_shortcode( array(
			'field_id' => 112,
			'entry'    => $args['council_id']
		) );
		$lodge_slug   = FrmProEntriesController::get_field_value_shortcode( array(
			'field_id' => 113,
			'entry'    => $args['lodge_id']
		) );
		$camp_slug    = FrmProEntriesController::get_field_value_shortcode( array(
			'field_id' => 108,
			'entry'    => $args['camp_id']
		) );


		//Your responses were successfully submitted. Thank you!


		///  if we have a council slug add it
		if ( isset( $council_slug ) ) {
			$tablename  = 'bsd_postmeta';
			$post_id    = $my_entry->post_id;
			$meta_key   = 'council';
			$meta_value = $council_slug;

			$sql = $wpdb->prepare(
				"
			INSERT INTO $tablename ( post_id, meta_key, meta_value ) 
			VALUES ( '%d', '$meta_key', '$meta_value') 
			
			", $post_id );

			$wpdb->query( $sql );


			if ( $wpdb->last_error ) {
				echo "It's! not my fault! " . $wpdb->last_error;
			}

		}

		///  if we have a lodge slug add it
		if ( isset( $lodge_slug ) ) {
			$tablename  = 'bsd_postmeta';
			$post_id    = $my_entry->post_id;
			$meta_key   = 'lodge';
			$meta_value = $lodge_slug;

			$sql = $wpdb->prepare(
				"
			INSERT INTO $tablename ( post_id, meta_key, meta_value ) 
			VALUES ( '%d', '$meta_key', '$meta_value') 
			
			", $post_id );

			$wpdb->query( $sql );


			if ( $wpdb->last_error ) {
				echo "It's! not my fault! " . $wpdb->last_error;
			}

		}

		/// if we have a camp slug add it
		if ( isset( $camp_slug ) ) {
			$tablename  = 'bsd_postmeta';
			$post_id    = $my_entry->post_id;
			$meta_key   = 'camp';
			$meta_value = $camp_slug;

			$sql = $wpdb->prepare(
				"
			INSERT INTO $tablename ( post_id, meta_key, meta_value ) 
			VALUES ( '%d', '$meta_key', '$meta_value') 
			
			", $post_id );

			$wpdb->query( $sql );


			if ( $wpdb->last_error ) {
				echo "It's! not my fault! " . $wpdb->last_error;
			}

		}



	}


} //end of add_council


// Add media button to front end forms
add_filter('frm_rte_options', 'frm_rte_options', 10, 2);
function frm_rte_options($opts, $field){
	$opts['media_buttons'] = true;
	return $opts;
}

// hide the admin bar on the front end when NOT logged in!
add_filter('show_admin_bar', '__return_false');









// array of filters (field key => field name)
$GLOBALS['my_query_filters'] = array(
	'field_1'	=> 'council',
	'field_2'	=> 'lodge',
	'field_3'	=> 'camp'
);


// action
add_action('pre_get_posts', 'my_pre_get_posts', 10, 1);

function my_pre_get_posts( $query ) {

	//ignore my filter for the info page!
	if ($query->get_queried_object_id() == 84) return;


	// bail early if is in admin
	if( is_admin() ) return;



	// bail early if not main query
	// - allows custom code / plugins to continue working
	if( !$query->is_main_query() ) return;


	// get meta query
	$meta_query = $query->get('meta_query');


	// loop over filters
	foreach( $GLOBALS['my_query_filters'] as $key => $name ) {

		// continue if not found in url
		if( empty($_GET[ $name ]) ) {

			continue;

		}


		// get the value for this filter
		// eg: http://www.website.com/events?city=melbourne,sydney
		$value = explode(',', $_GET[ $name ]);


		// append meta query
		$meta_query[] = array(
			'key'		=> $name,
			'value'		=> $value,
			'compare'	=> 'IN',
		);

	}


	// update meta query
	$query->set('meta_query', $meta_query);

}