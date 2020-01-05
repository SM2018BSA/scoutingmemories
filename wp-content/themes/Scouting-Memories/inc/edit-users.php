<?php

//if (!function_exists('active_assigned_council')) :
//    function two_fields_unique($errors, $posted_field)
//    {
//        $first_field_id = 125; // change 125 to the id of the first field
//        $second_field_id = 126; // change 126 to the id of the second field
//        if ($posted_field->id == $first_field_id) {
//            $entry_id = isset($_POST['id']) ? absint($_POST['id']) : 0;
//            $values_used = FrmDb::get_col('frm_item_metas',
//                array('item_id !' => $entry_id,
//                    array('or' => 1,
//                        array('field_id' => $first_field_id, 'meta_value' => $_POST['item_meta'][$first_field_id]),
//                        array('field_id' => $second_field_id, 'meta_value' => $_POST['item_meta'][$second_field_id]),
//                    )
//                ), 'item_id', array('group_by' => 'item_id', 'having' => 'COUNT(*) > 1')
//            );
//            if (!empty($values_used)) {
//                $errors['field' . $first_field_id] = 'You have already selected that option';
//                $errors['field' . $second_field_id] = 'You have already selected that option';
//            }
//        }
//        return $errors;
//    }
//
//    add_filter('frm_validate_field_entry', 'two_fields_unique', 10, 2);
//endif;


if (!function_exists('frm_populate_user_dropdown')) :
    add_filter('frm_setup_new_fields_vars',  'frm_populate_user_dropdown', 20, 2);
    add_filter('frm_setup_edit_fields_vars', 'frm_populate_user_dropdown', 20, 2);
    function frm_populate_user_dropdown($values, $field)
    {


        if ($field->id == EU_CURRENT_ROLES_FID ||
            $field->id == EU_ASSIGNED_STATE_FID ||
            $field->id == EU_ASSIGNED_COUNCIL_FID ||
            $field->id == EU_ASSIGNED_ACTIVE_COUNCIL_FID ||
            $field->id == EU_ASSIGNED_REGION_FID ||
            $field->id == EU_ASSIGNED_ACTIVE_REGION_FID
        ){
            $entry_id = get_request_parameter('entry');
            $user_email = get_field_val(NUR_EMAIL_FID, (int)$entry_id);
            $user = get_user_by('email', $user_email);
            $roles = $user->roles;
        }

        switch ($field->id) {
            case EU_CURRENT_ROLES_FID:
                $values['value'] = implode(', ', $roles);
            return $values;

            case EU_ASSIGNED_STATE_FID:
                $assigned_state = get_user_meta($user->ID, 'assigned_state', true);
                $values['value'] = $values['dyn_default_value'] = $values['default_value'] = $assigned_state;
            return $values;

            case EU_ASSIGNED_COUNCIL_FID:
                $assigned_council = get_user_meta($user->ID, 'assigned_council', true);
                $values['value'] = $values['dyn_default_value'] = $values['default_value'] = $assigned_council;
            return $values;

            case EU_ASSIGNED_ACTIVE_COUNCIL_FID:
                $assigned_active_council = get_user_meta($user->ID, 'active_assigned_council', true);
                $values['value'] = $values['dyn_default_value'] = $values['default_value'] = $assigned_active_council;
            return $values;

            case EU_ASSIGNED_REGION_FID:
                $assigned_region = get_user_meta($user->ID, 'assigned_region', true);
                $values['value'] = $values['dyn_default_value'] = $values['default_value'] = $assigned_region;
            return $values;

            case EU_ASSIGNED_ACTIVE_REGION_FID:
                $assigned_active_region = get_user_meta($user->ID, 'active_assigned_council', true);
                $values['value'] = $values['dyn_default_value'] = $values['default_value'] = $assigned_active_region;
            return $values;


        }









        return $values;
    }
endif;


if (!function_exists('frm_edit_users')) :
    add_filter('frm_pre_create_entry', 'frm_edit_users');
    function frm_edit_users($values)
    {
        if ($values['form_id'] == EDIT_USERS_FORMID) {

            $entry_id = get_request_parameter('entry');
            $user_email = get_field_val(NUR_EMAIL_FID, (int)$entry_id);
            $user = get_user_by('email', $user_email);
            $roles = $user->roles;

            $set_roles = $values['item_meta'][EU_SET_ROLE_FID];



            update_entry($entry_id, NUR_ASSIGNED_STATE_FID,              $values['item_meta'][EU_ASSIGNED_STATE_FID]);
            update_entry($entry_id, NUR_ASSIGNED_COUNCIL_FID,            $values['item_meta'][EU_ASSIGNED_COUNCIL_FID]);
            update_entry($entry_id, NUR_ASSIGNED_COUNCIL_ACTIVE_FID,     $values['item_meta'][EU_ASSIGNED_ACTIVE_COUNCIL_FID]);
            update_entry($entry_id, NUR_ASSIGNED_REGION_SLUG_FID,        $values['item_meta'][EU_ASSIGNED_REGION_SLUG_FID]);
            update_entry($entry_id, NUR_ASSIGNED_REGION_ACTIVE_FID,      $values['item_meta'][EU_ASSIGNED_ACTIVE_REGION_FID]);

            update_usermeta_data($user->ID, 'assigned_state',           $values['item_meta'][EU_ASSIGNED_STATE_FID]);
            update_usermeta_data($user->ID, 'assigned_council',         $values['item_meta'][EU_ASSIGNED_COUNCIL_FID]);
            update_usermeta_data($user->ID, 'active_assigned_council',  $values['item_meta'][EU_ASSIGNED_ACTIVE_COUNCIL_FID]);
            update_usermeta_data($user->ID, 'assigned_region_slug',     $values['item_meta'][EU_ASSIGNED_REGION_SLUG_FID]);
            update_usermeta_data($user->ID, 'active_assigned_region',   $values['item_meta'][EU_ASSIGNED_ACTIVE_REGION_FID]);


            if ($set_roles != '') {
                foreach ($roles as $role) $user->remove_role($role);
                if (count($set_roles) > 1): foreach ($set_roles as $set_role) $user->add_role($set_role);
                else: $user->set_role($set_roles);
                endif;
            }
        }

        // map user meta info
        if ($values['form_id'] == EDIT_USER_DEFAULTS_FORMID) {

            $current_user_id = get_current_user_id();

            update_user_meta($current_user_id, 'user_state',         $values['item_meta'][EAD_STATE_FID]);
            update_user_meta($current_user_id, 'user_council',       $values['item_meta'][EAD_COUNCIL_FID]);
            update_user_meta($current_user_id, 'user_camp',          $values['item_meta'][EAD_CAMP_FID]);
            update_user_meta($current_user_id, 'user_lodge',         $values['item_meta'][EAD_LODGE_FID]);
            update_user_meta($current_user_id, 'user_category',      $values['item_meta'][EAD_CATEGORY_FID]);
            update_user_meta($current_user_id, 'user_credit_author', $values['item_meta'][EAD_AUTHOR_FID]);
            update_user_meta($current_user_id, 'user_photographer',  $values['item_meta'][EAD_PHOTOGRAPHER_FID]);
            update_user_meta($current_user_id, 'user_contributors',  $values['item_meta'][EAD_CONTRIBUTORS_FID]);
            update_user_meta($current_user_id, 'date_original',      $values['item_meta'][EAD_DATE_ORIGINAL_FID]);
            update_user_meta($current_user_id, 'user_date_digital',  $values['item_meta'][EAD_DATE_DIGITAL_FID]);
            update_user_meta($current_user_id, 'user_pub_digital',   $values['item_meta'][EAD_PUB_DIGITAL_FID]);
            update_user_meta($current_user_id, 'user_subject',       $values['item_meta'][EAD_SUBJECT_FID]);
            update_user_meta($current_user_id, 'user_location',      $values['item_meta'][EAD_LOCATION_FID]);
            update_user_meta($current_user_id, 'user_identifier',    $values['item_meta'][EAD_IDENTIFIER_FID]);
            update_user_meta($current_user_id, 'user_physical_description', $values['item_meta'][EAD_PHY_DSC_FID]);
            update_user_meta($current_user_id, 'user_state_slug',    $values['item_meta'][EAD_STATE_SLUG_FID]);
            update_user_meta($current_user_id, 'user_council_slug',  $values['item_meta'][EAD_COUNCIL_SLUG_FID]);
            update_user_meta($current_user_id, 'user_camp_slug',     $values['item_meta'][EAD_CAMP_SLUG_FID]);
            update_user_meta($current_user_id, 'user_lodge_slug',    $values['item_meta'][EAD_LODGE_SLUG_FID]);


        }

        return $values;
    }
endif;


// Conditionally require and make Requested Councils Unique if it is active
// This will affect the New User Signup if they try to request a council that is already activated for another historian
//
add_filter('frm_validate_field_entry', 'conditionally_require_a_field', 10, 3);
function conditionally_require_a_field($errors, $field, $value)
{
    $active_councils = get_users_active_councils();

    if ($field->id == NUR_ASSIGNED_COUNCIL_FID) {
        foreach ($active_councils as $active_council) {
            if ($active_council->council == $value && $active_council->active == 'Yes')
                $errors['field' . $field->id] = 'That council already has an assigned historian.';
        }
    }

    if ($field->id == EU_ASSIGNED_COUNCIL_FID) {
        // check the user being edited
        // skip check for active if changing the user with the active council
        $entry_id = get_request_parameter('entry');
        foreach ($active_councils as $active_council) {
            if ($active_council->council == $value && $active_council->active == 'Yes' && $entry_id != $active_council->entry_id)
                $errors['field' . $field->id] = 'That council already has an assigned historian.';
        }
    }
    return $errors;
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
