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
require_once('inc/form-field-ids.php');
require_once('inc/utility-functions.php');
require_once('inc/entry-updated.php');
require_once ('inc/entry-view.php');
require_once('inc/edit-users.php');


// include custom Bootstrap JS
if (!function_exists('include_custom_bootstrapjs')):
    add_action('wp_enqueue_scripts', 'include_custom_bootstrapjs');
    function include_custom_bootstrapjs()
    {
        $rand = rand(1, 9999999);
        wp_enqueue_script('bootstrap',get_theme_file_uri() .'/js/bootstrap.min.js' ,array(), $rand);
    }
endif;

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
add_action('pre_get_posts', 'my_pre_get_posts');
function my_pre_get_posts($query)
{

    $current_category = $query->get_queried_object_id();


    // bail early if is in admin
    if (is_admin()) return $query;

    // bail early if not main query
    if (!$query->is_main_query()) return $query;

    // bail if not in one of my categories
    if (!in_array($current_category, $GLOBALS['wp_category_ids'])) return $query;


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
                    'relation' => 'AND',
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


add_filter('pre_get_posts', 'limit_historian_posts');
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




// Adds council number to council name in drop downs
// if a new form with council selection is made it should be added here
//
add_filter('frm_setup_new_fields_vars', 'frm_add_council_number', 30, 2);
add_filter('frm_setup_edit_fields_vars', 'frm_add_council_number', 30, 2);
function frm_add_council_number($values, $field)
{


    if ($field->id == NUR_ASSIGNED_COUNCIL_FID || $field->id == EU_ASSIGNED_COUNCIL_FID) {
        asort($values['options']); // sort the values here

        add_council_number($values);

        foreach ($values['options'] as $key => $value) {
            $val = get_field_val(AACOUNCIL_COUNCIL_ACTIVE_FID, $key);
            if ($key == '') unset($values['options'][$key]);
            if ($val == 'No') unset($values['options'][$key]);
        }
    }


    if ($field->id == SM1_COUNCIL_FID
        || $field->id == SM2_COUNCIL_FID
        || $field->id == SM3_COUNCIL_FID
        || $field->id == EAD_COUNCIL_FID
        || $field->id == AAP_COUNCIL_FID
        || $field->id == AACAMP_COUNCIL_FID
        || $field->id == AALODGE_COUNCIL_FID
        || $field->id == AACOUNCIL_OLDER_NAME_FID
        || $field->id == AACAMP_LINKED_COUNCIL_FID
        || $field->id == AALODGE_LINKED_COUNCIL_FID
        || $field->id == NUR_DEFAULT_COUNCIL_FID
    ) {

        foreach ($values['options'] as $key => $value)
            if ($key == '') unset($values['options'][$key]);

        add_council_number($values);
    }


    return $values;
}




add_filter('frm_setup_new_fields_vars', 'frm_reorder_userlist', 30, 2);
function frm_reorder_userlist($values, $field)
{
    if ($field->id == 457) { // Field ID of assigned councils in new account form
        asort($values['options']); // sort the values here

        // Filter usernames that are not empty

        foreach ($values['options'] as $key => $value) {
            //$val = FrmProEntriesController::get_field_value_shortcode(array('field_id' => $council_active_fid, 'entry' => $key));
            if ($value !== '')
                unset($values['options'][$key]);
        }
    }
    return $values;
}







