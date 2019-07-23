<?php
/**
 * Created by PhpStorm.
 * User: Roger Ellis
 * Date: 12/17/2017
 * Time: 4:44 PM
 */

require_once('inc/wp-bootstrap-walker.php');
require_once('inc/add-google-fonts.php');
require_once('inc/utility-functions.php');
require_once('inc/add-group.php');
require_once('inc/add-shortcodes.php');
require_once('inc/edit-defaults.php');


function after_entry_updated($entry_id, $form_id)
{


    // editing a Post
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
            $council_slug = FrmProEntriesController::get_field_value_shortcode(array(
                'field_id' => 105,
                'entry' => $args['council_id']
            ));
        } else {
            $council_slug = 'none';
        }

        if ($lodge_form_id > 0) {
            $lodge_slug = FrmProEntriesController::get_field_value_shortcode(array(
                'field_id' => 97,
                'entry' => $args['lodge_id']
            ));
        } else {
            $lodge_slug = 'none';
        }

        if ($camp_form_id > 0) {
            $camp_slug = FrmProEntriesController::get_field_value_shortcode(array(
                'field_id' => 123,
                'entry' => $args['camp_id']
            ));
        } else {
            $camp_slug = 'none';
        }


        if (is_array($_state_form_ids)) {
            foreach ($args['state_ids'] as $state_form_id => $key) {
                if ((int)$key > 0) {
                    $state_slugs[] = FrmProEntriesController::get_field_value_shortcode(array(
                        'field_id' => 114,
                        'entry' => $key
                    ));
                }
            }
        } else {

            $state_slugs[] = FrmProEntriesController::get_field_value_shortcode(array(
                'field_id' => 114,
                'entry' => $_state_form_ids
            ));

        }

        //$state_slug = FrmProEntriesController::get_field_value_shortcode(array('field_id' => 114, 'entry' => '307'));


        $post_id = $my_entry->post_id;


        //Your responses were successfully submitted. Thank you!


        ///  if we have a council slug add it\
        ///
        ///


        if (strlen($council_slug) > 1) {

            $meta_key = 'council';
            $meta_value = $council_slug;

            if (metadata_exists('post', $post_id, 'council')) {
                // we have one lets updated it
                update_post_meta($post_id, $meta_key, $meta_value);
            } else {
                //we dont have one lets add a new one
                add_post_meta($post_id, 'council', $meta_value);
            }

        }

        ///  if we have a lodge slug add it
        if (strlen($lodge_slug) > 1) {
            $meta_key = 'lodge';
            $meta_value = $lodge_slug;

            if (metadata_exists('post', $post_id, 'lodge')) {
                // we have one lets updated it
                update_post_meta($post_id, $meta_key, $meta_value);
            } else {
                //we dont have one lets add a new one
                add_post_meta($post_id, 'lodge', $meta_value);
            }

        }

        /// if we have a camp slug add it
        if (strlen($camp_slug) > 1) {


            $meta_key = 'camp';
            $meta_value = $camp_slug;

            if (metadata_exists('post', $post_id, 'camp')) {
                // we have one lets updated it
                update_post_meta($post_id, $meta_key, $meta_value);
            } else {
                //we dont have one lets add a new one
                add_post_meta($post_id, 'camp', $meta_value);
            }

        }


        // if we have states selected
        if (isset($state_slugs)) {


            $meta_key = 'state';
            $meta_value = $state_slugs;


            if (metadata_exists('post', $post_id, 'state')) {
                // we have one lets updated it
                update_post_meta($post_id, $meta_key, $meta_value);
            } else {
                //we dont have one lets add a new one
                add_post_meta($post_id, 'state', $meta_value);
            }

        }


    }


    if ($form_id == 30) {

        $current_user_id = get_current_user_id();

        $user_state = $_POST['item_meta']['394'];
        $user_council = $_POST['item_meta']['396'];
        $user_camp = $_POST['item_meta']['398'];
        $user_lodge = $_POST['item_meta']['399'];
        $user_category = $_POST['item_meta']['395'];
        $user_credit_author = $_POST['item_meta']['407'];
        $user_photographer = $_POST['item_meta']['408'];
        $user_contributors = $_POST['item_meta']['409'];
        $date_original = $_POST['item_meta']['412'];
        $user_date_digital = $_POST['item_meta']['414'];
        $user_pub_digital = $_POST['item_meta']['418'];
        $user_subject = $_POST['item_meta']['416'];
        $user_location = $_POST['item_meta']['417'];
        $user_identifier = $_POST['item_meta']['413'];
        $user_physical_description = $_POST['item_meta']['421'];
        $user_state_slug = $_POST['item_meta']['452'];
        $user_council_slug = $_POST['item_meta']['453'];
        $user_camp_slug = $_POST['item_meta']['454'];
        $user_lodge_slug = $_POST['item_meta']['455'];



        update_user_meta($current_user_id, 'user_state', $user_state);
        update_user_meta($current_user_id, 'user_council', $user_council);
        update_user_meta($current_user_id, 'user_camp', $user_camp);
        update_user_meta($current_user_id, 'user_lodge', $user_lodge);
        update_user_meta($current_user_id, 'user_category', $user_category);
        update_user_meta($current_user_id, 'user_credit_author', $user_credit_author);
        update_user_meta($current_user_id, 'user_photographer', $user_photographer);
        update_user_meta($current_user_id, 'user_contributors', $user_contributors);
        update_user_meta($current_user_id, 'date_original', $date_original);
        update_user_meta($current_user_id, 'user_date_digital', $user_date_digital);
        update_user_meta($current_user_id, 'user_pub_digital', $user_pub_digital);
        update_user_meta($current_user_id, 'user_subject', $user_subject);
        update_user_meta($current_user_id, 'user_location', $user_location);
        update_user_meta($current_user_id, 'user_identifier', $user_identifier);
        update_user_meta($current_user_id, 'user_physical_description', $user_physical_description);
        update_user_meta($current_user_id, 'user_state_slug', $user_state_slug);
        update_user_meta($current_user_id, 'user_council_slug', $user_council_slug);
        update_user_meta($current_user_id, 'user_camp_slug', $user_camp_slug);
        update_user_meta($current_user_id, 'user_lodge_slug', $user_lodge_slug);


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


    //////////////////////////// NO START DATE END DATE  ////////////////////////////////
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


function limit_historian_posts($query)
{
    global $pagenow;

    if ('edit.php' != $pagenow || !$query->is_admin)
        return $query;

    $user = wp_get_current_user();
    if (in_array('historian', (array)$user->roles)) {

        $assigned_council_slug = get_user_meta(get_current_user_id(), 'assigned_council_slug', true);

        $meta_query = [];
        // append meta query
        $meta_query[] =
            array(
                'key' => 'council',
                'value' => $assigned_council_slug,
                'compare' => 'LIKE'
            );
        $query->set('meta_query', $meta_query);
    }
    return $query;
}

add_filter('pre_get_posts', 'limit_historian_posts');


function frm_reorder_options($values, $field)
{
    if ($field->id == 439) {// Field ID of assigned councils in new account form
        asort($values['options']); // sort the values here

        // Filter Assigned Councils to only show active ones
        $council_active_fid = '329';
        foreach ($values['options'] as $key => $value) {
            $val = FrmProEntriesController::get_field_value_shortcode(array('field_id' => $council_active_fid, 'entry' => $key));
            if ($val == 'No')
                unset($values['options'][$key]);
        }
    }
    return $values;
}

add_filter('frm_setup_new_fields_vars', 'frm_reorder_options', 30, 2);





