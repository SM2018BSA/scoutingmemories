<?php
//Debugging utilities
if (!function_exists('clean')) :
    function clean($string)
    {
        $string = str_replace(' ', '_', $string); // Replaces all spaces with underscores.
        $string = str_replace('&amp;', '_and_', $string); // Replaces all spaces with underscores.
        return preg_replace('/[^A-Za-z0-9\-]/', '_', $string); // Removes special chars.
    }
endif;


if (!function_exists('debug_to_console')) :
    function debug_to_console($data)
    {
        $output = $data;
        if (is_array($output))
            $output = implode(',', $output);
        echo "<script>console.log( 'Debug Objects: " . $output . "' );</script>";
    }
endif;


/**
 * Gets the request parameter.
 *
 * @param string $key The query parameter
 * @param string $default The default value to return if not found
 *
 * @return     string  The request parameter.
 */

function get_request_parameter($key, $default = '')
{
    // If not request set
    if (!isset($_REQUEST[$key]) || empty($_REQUEST[$key])) {
        return $default;
    }

    // Set so process it
    return strip_tags((string)wp_unslash($_REQUEST[$key]));
}

if (!function_exists('get_all_field_values')) :
    function get_all_field_values($field_id)
    {
        global $wpdb;
        $sql = $wpdb->prepare("SELECT * FROM $wpdb->prefix" . "frm_item_metas WHERE field_id = %d", $field_id);
        $entry_data = $wpdb->get_results($sql);
        if ($wpdb->last_error !== '') $wpdb->print_error();

        return $entry_data;
    }
endif;


if (!function_exists('get_users_active_councils')) :
    function get_users_active_councils()
    {
        // perform self join to get info from both rows matching entries
        global $wpdb;
        $sql = $wpdb->prepare("SELECT a.item_id AS entry_id, a.meta_value AS council, b.meta_value AS active
                               FROM $wpdb->prefix" . "frm_item_metas a, $wpdb->prefix" . "frm_item_metas b
                               WHERE a.item_id = b.item_id AND a.field_id = %d AND b.field_id = %d",
                               NUR_ASSIGNED_COUNCIL_FID, NUR_ASSIGNED_COUNCIL_ACTIVE_FID);

        $entry_data = $wpdb->get_results($sql);
        if ($wpdb->last_error !== '') $wpdb->print_error();
        return $entry_data;
    }
endif;


if (!function_exists('get_field_id_from_key')) :
    function get_field_id_from_key($field_key)
    {
        if (is_array($field_key)) $field_key = implode(",", $field_key);
        global $wpdb;
        $entry_id = $wpdb->get_var($wpdb->prepare("SELECT item_id FROM $wpdb->prefix" . "frm_item_metas WHERE meta_value LIKE %s", $field_key));
        if ($wpdb->last_error !== '') $wpdb->print_error();
        return $entry_id;
    }
endif;


//   $state_acl  Array from get_field ACL function
//               is the state abreviation
//   this function returns the full human readble name of the state
if (!function_exists('get_state_name')) :
    function get_state_name($state_acl)
    {
        $state_name = 'none';

        if (is_array($state_acl) && count($state_acl) == 1) {
            $entry_id = get_field_id_from_key($state_acl);
            $state_name = '<span class="small">' . get_field_val(S_NAME_FID, $entry_id) . '</span>';
        } elseif (is_array($state_acl) && count($state_acl) > 1) {
            $state_name = '<ul class="small">';
            foreach ($state_acl as $state) {
                $entry_id = get_field_id_from_key($state);
                $state_name .= '<li>' . get_field_val(S_NAME_FID, $entry_id) . '</li>';
            }
            $state_name .= '</ul>';
        }


        return $state_name;
    }
endif;
// $council_slug is a string comma separated

if (!function_exists('get_council_name_number')) :
    function get_council_name_number($council_slug)
    {
        $council_name_number = 'none';


        if (is_string($council_slug)) {
            if ($council_slug[0] == 'none') return '<span class="small">' . $council_name_number . '</span>';
            $entry_id = get_field_id_from_key($council_slug);
            $council_name = get_field_val(AACOUNCIL_NAME_FID, $entry_id);
            $council_number = get_field_val(AACOUNCIL_NUMBER_FID, $entry_id);
            $council_name_number = '<span class="small">' . $council_name . ' #' . $council_number . '</span>';
        } elseif (is_array($council_slug) && count($council_slug) > 1) {
            $council_name_number = '<ul class="small">';
            foreach ($council_slug as $council) {
                $entry_id = get_field_id_from_key($council);
                $council_name = get_field_val(AACOUNCIL_NAME_FID, $entry_id);
                $council_number = get_field_val(AACOUNCIL_NUMBER_FID, $entry_id);
                $council_name_number .= '<li>' . $council_name . ' #' . $council_number . '</li>';
            }
            $council_name_number .= '</ul>';
        }

        return $council_name_number;
    }
endif;


if (!function_exists('get_camp_name')) :
    function get_camp_name($camp_slug)
    {
        $camp_name = 'none';

        if (is_string($camp_slug)) {
            if ($camp_slug[0] == 'none') return '<span class="small">' . $camp_name . '</span>';
            $entry_id = get_field_id_from_key($camp_slug);
            $camp_name = '<span class="small">' . get_field_val(AACAMP_NAME_FID, $entry_id) . '</span>';
        } elseif (is_array($camp_slug) && count($camp_slug) > 1) {
            $camp_name = '<ul class="small">';
            foreach ($camp_slug as $camp) {
                $entry_id = get_field_id_from_key($camp);
                $camp_name .= '<li>' . get_field_val(AACAMP_NAME_FID, $entry_id) . '</li>';
            }
            $camp_name .= '</ul>';
        }
        return $camp_name;
    }
endif;


if (!function_exists('get_lodge_name')) :
    function get_lodge_name($lodge_slug)
    {
        $lodge_name = 'none';

        if (is_string($lodge_slug)) {
            if ($lodge_slug[0] == 'none') return '<span class="small">' . $lodge_name . '</span>';
            $entry_id = get_field_id_from_key($lodge_slug);
            $lodge_name = '<span class="small">' . get_field_val(AALODGE_NAME_FID, $entry_id) . '</span>';
        } elseif (is_array($lodge_slug) && count($lodge_slug) > 1) {
            $lodge_name = '<ul class="small">';
            foreach ($lodge_slug as $lodge) {
                $entry_id = get_field_id_from_key($lodge);
                $lodge_name .= '<li>' . get_field_val(AALODGE_NAME_FID, $entry_id) . '</li>';
            }
            $lodge_name .= '</ul>';
        }
        return $lodge_name;
    }
endif;

/////////MY ACCOUNT UTILITY FUNCTIONS
///
///
///

if (!function_exists('update_entry')) :
    function update_entry($entry_id, $field_id, $meta_value)
    {
        global $wpdb;
        $sql = $wpdb->prepare("SELECT field_id FROM $wpdb->prefix" . "frm_item_metas WHERE item_id = %d AND field_id=%d", $entry_id, $field_id);
        $result = $wpdb->query($sql);
        if (!$result) {
            // the field wasnt there it needs to be added
            $sql = $wpdb->prepare("INSERT INTO $wpdb->prefix" . "frm_item_metas ( meta_value, field_id, item_id, created_at )
                            VALUES (%s, %d, %d, %s)",
                            $meta_value, $field_id, $entry_id, current_time('mysql'));
            $wpdb->query($sql);
            return;
        }
        $sql = $wpdb->prepare("UPDATE $wpdb->prefix" . "frm_item_metas SET meta_value = %s
                        WHERE item_id = %d AND field_id = %d",
                        $meta_value, $entry_id, $field_id);
        $wpdb->query($sql);
    }
endif;

if (!function_exists('show_user_roles')) :
    function show_user_roles(&$current_user)
    {
        $user_roles = '';
        $roles = $current_user->roles;
        if (count($roles) == 1) {
            $user_roles = '<span class="small">' . @implode(", ", $roles) . '</span>';
        } elseif (count($roles) > 1) {
            $user_roles = '<ul class="text-capitalize small">';
            foreach ($roles as $role) {
                $user_roles .= '<li>' . str_replace("_", " ", $role) . '</li>';
            }
            $user_roles .= '</ul>';
        }
        return $user_roles;
    }
endif;

// show council number with council name
// @params    $values
if (!function_exists('add_council_number')) :
    function add_council_number(&$values)
    {

        foreach ($values['options'] as $key => $value) {
            $val = get_field_val(AACOUNCIL_NUMBER_FID, $key);
            $values['options'][$key] .= ' (#' . $val . ')';
        }

    }

endif;

/*   get current user field value
 *   return String:     current user field value for the field id given
 *
 * */
if (!function_exists('get_field_val')) :
    function get_field_val($field_id, $entry_id)
    {
        return FrmProEntriesController::get_field_value_shortcode(array('field_id' => $field_id, 'entry' => $entry_id));
    }
endif;


/*   get current user field value
 *   return String:     current user field value for the field id given
 *
 * */
if (!function_exists('get_cuf_val')) :
    function get_cuf_val($field_id)
    {
        return FrmProEntriesController::get_field_value_shortcode(array('field_id' => $field_id, 'user_id' => 'current'));
    }
endif;


/*   $title Bool:       show or hide title of form
 *   $description Bool:      show or hide form description
 *   $params:           array for optional parameters ('tab' => 'defaults')
 *
 *   return String:     html of form
 * */
if (!function_exists('show_form')) :
    function show_form($form_id = NULL, $title = false, $description = false)
    {
        return FrmFormsController::get_form_shortcode(array('id' => $form_id, 'title' => $title, 'description' => $description));
    }
endif;

/*
 *   $view_id Int:      id of the view to show
 *   $filter String:    value for filter
 *
 *   return String:     html of view
 * */
if (!function_exists('show_view')) :
    function show_view($view_id, $filter = 'limited')
    {
        return FrmProDisplaysController::get_shortcode(array('id' => $view_id, 'filter' => $filter));
    }
endif;