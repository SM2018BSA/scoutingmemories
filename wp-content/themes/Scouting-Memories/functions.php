<?php
/**
 * Created by PhpStorm.
 * User: Roger Ellis
 * Date: 12/17/2017
 * Time: 4:44 PM
 */

function debug_to_console($data)
{
    $output = $data;
    if (is_array($output))
        $output = implode(',', $output);

    echo "<script>console.log( 'Debug Objects: " . $output . "' );</script>";
}


add_action('wp_enqueue_scripts', 'enqueue_parent_styles');

function enqueue_parent_styles()
{
    wp_enqueue_style('parent-style', get_template_directory_uri() . '/style.css');
}

function add_group($entry_id, $form_id)
{


    global $wpdb;


    // Add a Council
    if ($form_id == 8) {


        $field_id = 105; // hidden field id for the slug

        // where all the council data is stored
        $row = $wpdb->get_results("SELECT post_content FROM wp_posts WHERE ID = 606");


        $args = array();
        $council_name = sanitize_text_field($_POST['item_meta'][98]);
        $args['council_name'] = isset($council_name) ? $council_name : '';

        $council_num = sanitize_text_field($_POST['item_meta'][138]);
        $args['council_num'] = isset($council_num) ? $council_num : '';


        $council_name = str_replace(' ', '_', strtolower($args['council_name']));
        $council_name = $council_name . '_' . $args['council_num'];

        // add the council_name to the hidden field for the cat slug
        FrmEntryMeta::update_entry_meta($entry_id, $field_id, '', $council_name);

        $array = unserialize($row[0]->post_content);
        $array['choices'] = array_merge($array['choices'], [$council_name => $args['council_name']]);
        $post_content = serialize($array);

        $tablename = 'wp_posts';

        $sql = $wpdb->prepare(
            "
			UPDATE $tablename 
			SET post_content = '$post_content'
			WHERE ID = %d
			
			", "606");

        $wpdb->query($sql);

        if ($wpdb->last_error) {
            echo "It's! not my fault! " . $wpdb->last_error;
        }


    }

    // Add a Lodge
    if ($form_id == 7) {

        $field_id = 97; // hidden field id for the slug
        $row = $wpdb->get_results("SELECT post_content FROM wp_posts WHERE ID = 605");
        $args = array();

        $lodge_name = sanitize_text_field($_POST['item_meta'][90]);
        $args['lodge_name'] = isset($lodge_name) ? $lodge_name : 'none'; // field IDs from the form


        $lodge_num = sanitize_text_field($_POST['item_meta'][91]);
        $args['lodge_num'] = isset($lodge_num) ? $lodge_num : '';


        $lodge_name = str_replace(' ', '_', strtolower($args['lodge_name']));
        $lodge_name = $lodge_name . '_' . $args['lodge_num'];


        // add the lodge_name to the hidden field for the cat slug
        FrmEntryMeta::update_entry_meta($entry_id, $field_id, '', $lodge_name);

        $array = unserialize($row[0]->post_content);
        $array['choices'] = array_merge($array['choices'], [$lodge_name => $args['lodge_name']]);
        $post_content = serialize($array);

        $tablename = 'wp_posts';

        $sql = $wpdb->prepare(
            "
			UPDATE $tablename 
			SET post_content = '$post_content'
			WHERE ID = %d
			
			", "605");

        $wpdb->query($sql);

        if ($wpdb->last_error) {
            echo "It's! not my fault! " . $wpdb->last_error;
        }


    }


    // Add a Camp
    if ($form_id == 11) {


        $field_id = 123; // hidden field id for the slug
        $row = $wpdb->get_results("SELECT post_content FROM wp_posts WHERE ID = 604");


        $args = array();
        $camp_name = $_POST['item_meta'][117];
        $args['camp_name'] = isset($camp_name) ? $camp_name : ''; // field IDs from the form


        $camp_name = str_replace(' ', '_', strtolower($args['camp_name']));
        $camp_name = $camp_name . '_' . $entry_id;


        // add the lodge_name to the hidden field for the cat slug
        FrmEntryMeta::update_entry_meta($entry_id, $field_id, '', $camp_name);

        $array = unserialize($row[0]->post_content);
        $array['choices'] = array_merge($array['choices'], [$camp_name => $args['camp_name']]);
        $post_content = serialize($array);

        $tablename = 'wp_posts';

        $sql = $wpdb->prepare(
            "
			UPDATE $tablename 
			SET post_content = '$post_content'
			WHERE ID = %d
			
			", "604");

        $wpdb->query($sql);

        if ($wpdb->last_error) {
            echo "It's! not my fault! " . $wpdb->last_error;
        }


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


        $_state_form_ids = $_POST['item_meta']['69'];
        if (isset($_state_form_ids)) {
            foreach ($_state_form_ids as $state_form_id) :
                $state_form_id = sanitize_text_field($state_form_id);
                $args['state_ids'][] = isset($state_form_id) ? $state_form_id : '';
            endforeach;
        }


        $council_slug = FrmProEntriesController::get_field_value_shortcode(array(
            'field_id' => 105,
            'entry' => $args['council_id']
        ));
        $lodge_slug = FrmProEntriesController::get_field_value_shortcode(array(
            'field_id' => 97,
            'entry' => $args['lodge_id']
        ));
        $camp_slug = FrmProEntriesController::get_field_value_shortcode(array(
            'field_id' => 123,
            'entry' => $args['camp_id']
        ));


        foreach ($args['state_ids'] as $state_form_id => $key)
            if ((int)$key > 0)
                $state_slugs[] = FrmProEntriesController::get_field_value_shortcode(array('field_id' => 114, 'entry' => $key));


        //Your responses were successfully submitted. Thank you!


        ///  if we have a council slug add it
        ///


        if (isset($council_slug)) {
            $tablename = 'wp_postmeta';
            $post_id = $my_entry->post_id;
            $meta_key = 'council';
            $meta_value = $council_slug;

            $sql = $wpdb->prepare(
                "
			INSERT INTO $tablename ( post_id, meta_key, meta_value ) 
			VALUES ( '%d', '$meta_key', '$meta_value') 
			", $post_id);

            $wpdb->query($sql);

            if ($wpdb->last_error)
                echo "It's! not my fault! " . $wpdb->last_error;

        }

        ///  if we have a lodge slug add it
        if (isset($lodge_slug)) {
            $tablename = 'wp_postmeta';
            $post_id = $my_entry->post_id;
            $meta_key = 'lodge';
            $meta_value = $lodge_slug;

            $sql = $wpdb->prepare(
                "
			INSERT INTO $tablename ( post_id, meta_key, meta_value ) 
			VALUES ( '%d', '$meta_key', '$meta_value') 
			", $post_id);

            $wpdb->query($sql);


            if ($wpdb->last_error) {
                echo "It's! not my fault! " . $wpdb->last_error;
            }

        }

        /// if we have a camp slug add it
        if (isset($camp_slug)) {
            $tablename = 'wp_postmeta';
            $post_id = $my_entry->post_id;
            $meta_key = 'camp';
            $meta_value = $camp_slug;

            $sql = $wpdb->prepare(
                "
			INSERT INTO $tablename ( post_id, meta_key, meta_value ) 
			VALUES ( '%d', '$meta_key', '$meta_value') 
			", $post_id);

            $wpdb->query($sql);


            if ($wpdb->last_error) {
                echo "It's! not my fault! " . $wpdb->last_error;
            }

        }

        // if we have states selected
        if (isset($state_slugs)) {

            $tablename = 'wp_postmeta';
            $post_id = $my_entry->post_id;
            $meta_key = 'state';
            $meta_value = serialize($state_slugs);

            $sql = $wpdb->prepare(
                "
			UPDATE $tablename 
			SET meta_value = '$meta_value'
			WHERE post_id = '$post_id' AND 
			      meta_key = '$meta_key'
			", $post_id);

            $wpdb->query($sql);

            if ($wpdb->last_error)
                echo "It's! not my fault! " . $wpdb->last_error;


        }


    }


} //end of frm_after_create_entry


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


add_action('frm_after_create_entry', 'add_group', 30, 2);

