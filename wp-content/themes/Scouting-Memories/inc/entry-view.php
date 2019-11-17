<?php
if (!function_exists('frm_edit_post_entry')) :
    add_filter('frm_setup_edit_fields_vars', 'frm_edit_post_entry', 25, 3);
    function frm_edit_post_entry($values, $field, $entry_id)
    {


        switch ($field->id) {

            case AAP_STATES_FID:

                $state_form_ids = get_field_val(AAP_STATES_FID, $entry_id);



                // convert value to id
                if (isset($state_form_ids)) {
                    if (is_array($state_form_ids)) {

                        foreach ($state_form_ids as $key => $state_form_id) :

                            //check if VAL or ID already
                            if (!is_numeric($state_form_id)) {
                                //echo 'true';
                                $state_form_id = get_field_id_from_key(sanitize_text_field($state_form_id));
                            }

                            $values['value'][$key] = isset($state_form_id) ? $state_form_id : '';
                        endforeach;
                    } else {
                        //check if VAL or ID already
                        if (!is_numeric($state_form_ids)) $state_form_ids = get_field_id_from_key(sanitize_text_field($state_form_ids));
                        $values['value'] = isset($state_form_ids) ? sanitize_text_field($state_form_ids) : '';
                    }
                }


                $values['dyn_default_value'] = $values['default_value'] = $values['value'];
                return $values;
        }

        return $values;
    }
endif;

if (!function_exists('frm_new_post_defaults')) :
    add_filter('frm_setup_new_fields_vars', 'frm_new_post_defaults', 25, 2);
    function frm_new_post_defaults($values, $field)
    {

        $frm_action = '';
        if (isset($_GET['frm_action']) && !empty($_GET['frm_action']) ) $frm_action = 'edit';


        if ($frm_action != 'edit') :
            switch ($field->id) {

                case AAP_STATES_FID:  // This is to change abbreviated states PA into its number 342

                    $state_form_ids = $values['value'];

                    // convert value to id
                    if (isset($state_form_ids)) {
                        if (is_array($state_form_ids)) {

                            foreach ($state_form_ids as $key => $state_form_id) :

                                //check if VAL or ID already
                                if (!is_numeric($state_form_id)) {
                                    //echo 'true';
                                    $state_form_id = get_field_id_from_key(sanitize_text_field($state_form_id));
                                }

                                $values['value'][$key] = isset($state_form_id) ? $state_form_id : '';
                            endforeach;
                        } else {
                            //check if VAL or ID already
                            if (!is_numeric($state_form_ids)) $state_form_ids = get_field_id_from_key(sanitize_text_field($state_form_ids));
                            $values['value'] = isset($state_form_ids) ? sanitize_text_field($state_form_ids) : '';
                        }
                    }

                    $values['dyn_default_value'] = $values['default_value'] = $values['value'];
                    return $values;
            }
        endif;

        return $values;
    }
endif;