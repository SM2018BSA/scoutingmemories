<?php
/**
 * Created by PhpStorm.
 * User: Roger Ellis
 * Date: 12/17/2017
 * Time: 4:44 PM
 */


// Register Custom Navigation Walker
function wpb_widgets_init() {
	register_sidebar( array(
			'name' => 'Related Info',
			'id' => 'related_info',
			'before_widget' => '<div class = "container card-body">',
			'after_widget' => '</div>',
			'before_title' => '<h3>',
			'after_title' => '</h3>',
		) );
}
add_action( 'widgets_init', 'wpb_widgets_init' );



require_once ('inc/wp-bootstrap-walker.php');


function custom_add_google_fonts() {
    wp_enqueue_style( 'custom-google-fonts', 'https://fonts.googleapis.com/css?family=Roboto+Slab', false );

}
add_action( 'wp_enqueue_scripts', 'custom_add_google_fonts' );








function clean($string) {
    $string = str_replace(' ', '_', $string); // Replaces all spaces with underscores.
    $string = str_replace('&amp;', '_and_', $string); // Replaces all spaces with underscores.
    return preg_replace('/[^A-Za-z0-9\-]/', '_', $string); // Removes special chars.
}

function debug_to_console($data)
{
    $output = $data;
    if (is_array($output))
        $output = implode(',', $output);

    echo "<script>console.log( 'Debug Objects: " . $output . "' );</script>";
}


function add_query_vars_filter($vars)
{
    $vars[] = "state";
    $vars[] .= "council";
    $vars[] .= "lodge";
    $vars[] .= "camp";
    $vars[] .= "start_date";
    $vars[] .= "end_date";
    $vars[] .= "pass_entry";
    return $vars;
}
add_filter('query_vars', 'add_query_vars_filter');






function add_group($entry_id, $form_id)
{


    global $wpdb;



    // Add a Council
    if ($form_id == 8) {

        $field_id = 105; // hidden field form id for the council slug in the add a council form
        $field_acl = 'field_5c1700423245d';

        $args = array();
        $council_name = sanitize_text_field($_POST['item_meta'][98]);
        $args['council_name'] = isset($council_name) ? $council_name : '';

        $council_num = sanitize_text_field($_POST['item_meta'][138]);
        $args['council_num'] = isset($council_num) ? $council_num : '';

        $council_name = clean($council_name);
        $council_name = $council_name . '_' . $args['council_num'];

        // add the council_name to the hidden field for the cat slug
        FrmEntryMeta::update_entry_meta($entry_id, $field_id, '', $council_name);


        $selector = $field_acl;
        $field = acf_get_field( $selector, true );
        $field['choices'][ $council_name ] = wp_unslash($args['council_name']);
        acf_update_field( $field );

    }

    // Add a Lodge
    if ($form_id == 7) {

        $field_id = 97; // hidden field id for the slug
        $field_acl = 'field_5c1700203245c';

        $args = array();
        $lodge_name = sanitize_text_field($_POST['item_meta'][90]);
        $args['lodge_name'] = isset($lodge_name) ? $lodge_name : 'none'; // field IDs from the form

        $lodge_num = sanitize_text_field($_POST['item_meta'][91]);
        $args['lodge_num'] = isset($lodge_num) ? $lodge_num : '';

        $lodge_name = clean($lodge_name);
        $lodge_name = $lodge_name . '_' . $args['council_num'];

        // add the lodge_name to the hidden field for the cat slug
        FrmEntryMeta::update_entry_meta($entry_id, $field_id, '', $lodge_name);

        $selector = $field_acl;
        $field = acf_get_field( $selector, true );
        $field['choices'][ $lodge_name ] = wp_unslash($args['lodge_name']);
        acf_update_field( $field );




    }


    // Add a Camp
    if ($form_id == 11) {

        $field_id = 123; // hidden field id for the slug
        $field_acl = 'field_5c1700163245b';

        $args = array();
        $camp_name = $_POST['item_meta'][117];
        $args['camp_name'] = isset($camp_name) ? $camp_name : ''; // field IDs from the form

        $camp_name = clean($camp_name);
        $camp_name = $camp_name . '_' . $entry_id;

        // add the lodge_name to the hidden field for the cat slug
        FrmEntryMeta::update_entry_meta($entry_id, $field_id, '', $camp_name);

        $selector = $field_acl;
        $field = acf_get_field( $selector, true );
        $field['choices'][ $camp_name ] = wp_unslash($args['camp_name']);
        acf_update_field( $field );

    }


    // Add a Post
    if ($form_id == 6) {

        // used to get my new posts ID
        $my_entry = FrmEntry::getOne($entry_id);


        //  Get my slugs ////////////////////////
        $args = array();

        $council_form_id = sanitize_text_field($_POST['item_meta'][70]);
        $args['council_id'] = isset($council_form_id) ? $council_form_id : ''; // field IDs from your form


        $lodge_form_id = sanitize_text_field($_POST['item_meta'][72]);
        $args['lodge_id'] = isset($lodge_form_id) ? $lodge_form_id : ''; // field IDs from your form


        $camp_form_id = sanitize_text_field($_POST['item_meta'][74]);
        $args['camp_id'] = isset($camp_form_id) ? $camp_form_id : ''; // field IDs from your form


        $_state_form_ids = $_POST['item_meta']['288'];
        if (isset($_state_form_ids)) {
            foreach ($_state_form_ids as $state_form_id) :
                $state_form_id = sanitize_text_field($state_form_id);
                $args['state_ids'][] = isset($state_form_id) ? $state_form_id : '';
            endforeach;
        }


	    if ($council_form_id > 0) {
		    $council_slug = FrmProEntriesController::get_field_value_shortcode( array(
			    'field_id' => 105,
			    'entry'    => $args['council_id']
		    ) );
	    } else { $council_slug = 'none'; }

        if ($lodge_form_id > 0) {
	        $lodge_slug = FrmProEntriesController::get_field_value_shortcode( array(
		        'field_id' => 97,
		        'entry'    => $args['lodge_id']
	        ) );
        } else { $lodge_slug = 'none'; }

	    if ($camp_form_id > 0) {
		    $camp_slug = FrmProEntriesController::get_field_value_shortcode( array(
			    'field_id' => 123,
			    'entry'    => $args['camp_id']
		    ) );
	    } else { $camp_slug = 'none'; }



        if ( is_array($_state_form_ids)) {
	        foreach ( $args['state_ids'] as $state_form_id => $key ) {
		        if ( (int) $key > 0 ) {
			        $state_slugs[] = FrmProEntriesController::get_field_value_shortcode( array(
				        'field_id' => 114,
				        'entry'    => $key
			        ) );
		        }
	        }
        } else {

	        $state_slugs[] = FrmProEntriesController::get_field_value_shortcode( array(
		        'field_id' => 114,
		        'entry'    => $_state_form_ids
	        ) );

        }

	        //$state_slug = FrmProEntriesController::get_field_value_shortcode(array('field_id' => 114, 'entry' => '307'));




            //Your responses were successfully submitted. Thank you!


        ///  if we have a council slug add it
        ///

	    $post_id = $my_entry->post_id;


        if (isset($council_slug)) {


            $meta_key = 'council';
            $meta_value = $council_slug;

            add_post_meta($post_id,$meta_key,$meta_value);

        }

        ///  if we have a lodge slug add it
        if (isset($lodge_slug)) {


            $meta_key = 'lodge';
            $meta_value = $lodge_slug;

	        add_post_meta($post_id,$meta_key,$meta_value);

        }

        /// if we have a camp slug add it
        if (isset($camp_slug)) {


            $meta_key = 'camp';
            $meta_value = $camp_slug;

	        add_post_meta($post_id,$meta_key,$meta_value);

        }


	    /// if we have an entry ID save it with the post
	    if (isset($entry_id)) {


		    $meta_key = 'frm_entry_id';
		    $meta_value = $entry_id;

		    add_post_meta($post_id,$meta_key,$meta_value);

	    }




        // if we have states selected
       if (isset($state_slugs)) {

	       $meta_key = 'state';
           $meta_value = $state_slugs;

	       update_post_meta($post_id, $meta_key, $meta_value);
	   
        }

    }

} //end of frm_after_create_entry
add_action('frm_after_create_entry', 'add_group', 30, 2);


















function after_entry_updated($entry_id, $form_id) {
	global $wpdb;

	// editing a Post
	if ( $form_id == 6 ) {

		// used to get my new posts ID
		$my_entry = FrmEntry::getOne( $entry_id );


		//  Get my slugs ////////////////////////
		$args = array();

		$council_form_id    = sanitize_text_field( $_POST['item_meta'][70] );
		$args['council_id'] = isset( $council_form_id ) ? $council_form_id : ''; // field IDs from your form


		$lodge_form_id    = sanitize_text_field( $_POST['item_meta'][72] );
		$args['lodge_id'] = isset( $lodge_form_id ) ? $lodge_form_id : ''; // field IDs from your form


		$camp_form_id    = sanitize_text_field( $_POST['item_meta'][74] );
		$args['camp_id'] = isset( $camp_form_id ) ? $camp_form_id : ''; // field IDs from your form


		$_state_form_ids = $_POST['item_meta']['288'];
		if ( isset( $_state_form_ids ) ) {
			foreach ( $_state_form_ids as $state_form_id ) :
				$state_form_id       = sanitize_text_field( $state_form_id );
				$args['state_ids'][] = isset( $state_form_id ) ? $state_form_id : '';
			endforeach;
		}



		if ( $council_form_id > 0 ) {
			$council_slug = FrmProEntriesController::get_field_value_shortcode( array(
				'field_id' => 105,
				'entry'    => $args['council_id']
			) );
		} else {
			$council_slug = 'none';
		}

		if ( $lodge_form_id > 0 ) {
			$lodge_slug = FrmProEntriesController::get_field_value_shortcode( array(
				'field_id' => 97,
				'entry'    => $args['lodge_id']
			) );
		} else {
			$lodge_slug = 'none';
		}

		if ( $camp_form_id > 0 ) {
			$camp_slug = FrmProEntriesController::get_field_value_shortcode( array(
				'field_id' => 123,
				'entry'    => $args['camp_id']
			) );
		} else {
			$camp_slug = 'none';
		}




		if ( is_array( $_state_form_ids ) ) {
			foreach ( $args['state_ids'] as $state_form_id => $key ) {
				if ( (int) $key > 0 ) {
					$state_slugs[] = FrmProEntriesController::get_field_value_shortcode( array(
						'field_id' => 114,
						'entry'    => $key
					) );
				}
			}
		} else {

			$state_slugs[] = FrmProEntriesController::get_field_value_shortcode( array(
				'field_id' => 114,
				'entry'    => $_state_form_ids
			) );

		}

		//$state_slug = FrmProEntriesController::get_field_value_shortcode(array('field_id' => 114, 'entry' => '307'));




		$post_id = $my_entry->post_id;


		//Your responses were successfully submitted. Thank you!


		///  if we have a council slug add it\
		///
		///


		if ( strlen($council_slug) > 1  ) {

			$meta_key   = 'council';
			$meta_value = $council_slug;

			if ( metadata_exists( 'post', $post_id, 'council' ) ) {
				// we have one lets updated it
				update_post_meta( $post_id, $meta_key, $meta_value );
			} else {
				//we dont have one lets add a new one
				add_post_meta( $post_id, 'council', $meta_value );
			}

		}

		///  if we have a lodge slug add it
		if ( strlen($lodge_slug) > 1 ) {
			$meta_key   = 'lodge';
			$meta_value = $lodge_slug;

			if ( metadata_exists( 'post', $post_id, 'lodge' ) ) {
				// we have one lets updated it
				update_post_meta( $post_id, $meta_key, $meta_value );
			} else {
				//we dont have one lets add a new one
				add_post_meta( $post_id, 'lodge', $meta_value );
			}

		}

		/// if we have a camp slug add it
		if ( strlen($camp_slug) > 1 ) {


			$meta_key   = 'camp';
			$meta_value = $camp_slug;

			if ( metadata_exists( 'post', $post_id, 'camp' ) ) {
				// we have one lets updated it
				update_post_meta( $post_id, $meta_key, $meta_value );
			} else {
				//we dont have one lets add a new one
				add_post_meta( $post_id, 'camp', $meta_value );
			}

		}



		// if we have states selected
		if ( isset($state_slugs) ) {


			$meta_key   = 'state';
			$meta_value =  $state_slugs ;


			if ( metadata_exists( 'post', $post_id, 'state' ) ) {
				// we have one lets updated it
				update_post_meta( $post_id, $meta_key, $meta_value );
			} else {
				//we dont have one lets add a new one
				add_post_meta( $post_id, 'state', $meta_value );
			}

		}


	}

}
add_action('frm_after_update_entry', 'after_entry_updated', 10, 2);


// Add media button to front end forms
add_filter('frm_rte_options', 'frm_rte_options', 10, 2);
function frm_rte_options($opts, $field)
{
    $opts['media_buttons'] = true;
    return $opts;
}

// hide the admin bar on the front end when NOT logged in!
add_filter('show_admin_bar', '__return_false');


// array of filters (field key => field name)
$GLOBALS['my_query_filters'] = array(
    'field_0' => 'state',
    'field_1' => 'council',
    'field_2' => 'lodge',
    'field_3' => 'camp',
    'field_4' => 'start_date',
    'field_5' => 'end_date'
);
$GLOBALS['wp_category_ids'] = array(
    'history' => '3',
    'memorabilia' => '4',
    'movies' => '8',
    'museums' => '7',
    'oral-history' => '6',
    'photographs' => '5',
    'uncategorized' => '1'

);

// action

function my_pre_get_posts($query)
{

    $current_category = $query->get_queried_object_id();


    // bail early if is in admin
    if (is_admin()) return;

    // bail early if not main query
    if (!$query->is_main_query()) return;

    // bail if not in one of my categories
    if (!in_array($current_category, $GLOBALS['wp_category_ids'])) return;


    // get meta query
    //$meta_query = (array)$query->get('meta_query');

    $meta_query = [];
    $meta_query_dates = [];


    // loop over filters
    foreach ($GLOBALS['my_query_filters'] as $key => $name) {


        // continue if not found in url
        if (empty(sanitize_text_field($_GET[$name]))) continue;


        // get the value for this filter
        $value = explode(',', sanitize_text_field($_GET[$name]));


        if ($name == 'start_date') :
            $start_date = $value[0];
            continue;
        endif;
        if ($name == 'end_date') :
            $end_date = $value[0];
            continue;
        endif;

        if ($name == 'state') :
            $state_code = $value[0];
            $meta_query[] = array(
                'key' => $name,
                'value' => $state_code,
                'compare' => 'LIKE',
            );

            continue;
        endif;

        // append meta query
        $meta_query[] = array(
            'key' => $name,
            'value' => $value,
            'compare' => 'IN',
        );

    }//end of foreach loop


    if (isset($start_date) && isset($end_date)) :


        $meta_query_dates[] = array(
            'key' => 'start_date',
            'value' => array($start_date, $end_date),
            'compare' => 'BETWEEN',
            'type' => 'NUMERIC'
        );

        $meta_query_dates[] = array(
            'key' => 'end_date',
            'value' => array($start_date, $end_date),
            'compare' => 'BETWEEN',
            'type' => 'NUMERIC'
        );


        $query->set('meta_query',
            array('relation' => 'AND',
                array(
                    'relation' => 'OR',
                    $meta_query[0],
                    (isset($meta_query[1]) ? $meta_query[1] : null),
                    (isset($meta_query[2]) ? $meta_query[2] : null),
                    (isset($meta_query[3]) ? $meta_query[3] : null)
                ),
                array(
                    'relation' => 'OR',
                    $meta_query_dates[0],
                    $meta_query_dates[1]
                )
            )

        );


        return $query;
    endif;


    //////////////////////////// NO START DATE END DATE ////////////////////////////////
    ///
    ///
    ///
    ///
    ///
    ///


    $query->set('meta_query',

        array(
            'relation' => 'OR',
            $meta_query[0],
            (isset($meta_query[1]) ? $meta_query[1] : null),
            (isset($meta_query[2]) ? $meta_query[2] : null),
            (isset($meta_query[3]) ? $meta_query[3] : null)
        )


    );


    $query->set('meta_query', $meta_query);


    return $query;
}

add_action('pre_get_posts', 'my_pre_get_posts');








