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
    add_filter('frm_setup_new_fields_vars', 'frm_populate_user_dropdown', 20, 2);
    add_filter('frm_setup_edit_fields_vars', 'frm_populate_user_dropdown', 20, 2);
    function frm_populate_user_dropdown($values, $field)
    {


        $entry_id = get_request_parameter('entry');
        $user_email = get_field_val(NUR_EMAIL_FID, (int)$entry_id);
        $user = get_user_by_email($user_email);


        if ($field->id == EU_CURRENT_ROLES_FID) {
            $roles = implode(', ', $user->roles);
            $values['value'] = $roles;
        }


        if ($field->id == EU_ASSIGNED_STATE_FID) {
            $assigned_state = get_user_meta($user->ID, 'assigned_state', true);
            // set default on dynamic drop down
            $values['value'] = $values['dyn_default_value'] = $values['default_value'] = $assigned_state;
            return $values;
        }

        if ($field->id == EU_ASSIGNED_COUNCIL_FID) {
            $assigned_council = get_user_meta($user->ID, 'assigned_council', true);
            // set default on dynamic drop down
            $values['value'] = $values['dyn_default_value'] = $values['default_value'] = $assigned_council;
            return $values;
        }



        return $values;
    }
endif;



//
//if (!function_exists('frm_edit_users')) :
//    add_filter('frm_pre_create_entry', 'frm_edit_users');
//    function adjust_my_field($values) {
//        if ($values['form_id'] == EDIT_USERS_FORMID) {
//            var_dump($values);
//
//
//            //        if ($field->id == EU_SET_ROLE_FID) {
////
////            $roles = array(
////                'administrator'
////
////            );
////
////            $user->set_role($roles[0]);
////
////        }
//
//
//            //die('hello');
//        }
//    }
//endif;


