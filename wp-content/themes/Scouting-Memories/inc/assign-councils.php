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

//
//if (!function_exists('frm_populate_user_dropdown')) :
//    add_filter('frm_setup_new_fields_vars', 'frm_populate_user_dropdown', 20, 2);
//    add_filter('frm_setup_edit_fields_vars', 'frm_populate_user_dropdown', 20, 2);
//    function frm_populate_user_dropdown($values, $field)
//    {
//
//        if ($field->id == EU_USERNAME_FID) {
//
//            $users = get_users();
//
//            $values['options'] = array();
//            foreach ($users as $user) {
//                $user_meta=get_userdata($user->ID);
//                $values['options'][] = array(
//                    'label' => $user->display_name,
//                    'value' => $user->ID
//                );
//            }
//
//            var_dump($_POST);
//            //get_field_val(483,)
//
//        }
//
//
//
//        return $values;
//    }
//endif;
//
//if(!function_exists('do_not_restrict_admin')) :
//add_filter('frm_setup_new_fields_vars', 'do_not_restrict_admin', 20, 2);
//function do_not_restrict_admin( $values, $field ) {
//    if ( $values['restrict'] && current_user_can('administrator') ) {
//        if ( $values['type'] == 'data' && in_array( $values['data_type'], array( 'select', 'radio', 'checkbox' ) ) && is_numeric( $values['form_select'] ) ) {
//            $values['restrict'] = 0;
//            $values['options'] = FrmProDynamicFieldsController::get_independent_options($values, $field);
//        }
//    }
//    return $values;
//}
//endif;

