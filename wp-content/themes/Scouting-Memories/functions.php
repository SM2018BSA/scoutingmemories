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
    if ($field->id == 439) { // Field ID of assigned councils in new account form
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

add_filter('frm_setup_new_fields_vars', 'frm_reorder_userlist', 30, 2);





