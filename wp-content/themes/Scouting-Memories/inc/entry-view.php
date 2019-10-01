<?php


if (!function_exists('frm_show_post_defaults')) :
    add_filter('frm_setup_new_fields_vars', 'frm_show_post_defaults', 25, 2);
    add_filter('frm_setup_edit_fields_vars', 'frm_show_post_defaults', 25, 2);
    function frm_show_post_defaults($values, $field)
    {


       switch ($field->id){

           case AAP_STATES_FID:


               $state_form_ids = $values['value'];

               echo 'frm_show_post_defaults';

               // convert value to id
               if (isset($state_form_ids)) {
                   if (is_array($state_form_ids)) {

                       echo 'before change: ' ; var_dump( $state_form_ids);

                       foreach ($state_form_ids as $key => $state_form_id) :

                           //check if VAL or ID already
                           if (!is_numeric($state_form_id) ) {
                                echo 'true';
                               $state_form_id = get_field_id_from_key(sanitize_text_field($state_form_id));
                           }



                           $values['value'][$key] = isset($state_form_id) ? $state_form_id : '';
                       endforeach;
                   } else {
                       $values['value'] = isset($state_form_ids) ? sanitize_text_field($state_form_ids) : '';
                   }
               }

         


                $values['dyn_default_value'] = $values['default_value'] = $values['value'];
               echo 'after change: ';
                var_dump($values['value']);
               return $values;
               break;
       }

        return $values;
    }
endif;