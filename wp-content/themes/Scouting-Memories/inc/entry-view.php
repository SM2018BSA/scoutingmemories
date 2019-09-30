<?php


if (!function_exists('frm_show_post_defaults')) :
    add_filter('frm_setup_new_fields_vars', 'frm_populate_user_dropdown', 25, 2);
    add_filter('frm_setup_edit_fields_vars', 'frm_populate_user_dropdown', 25, 2);
    function frm_show_post_defaults($values, $field)
    {

       switch ($field->id){

           case AAP_STATES_FID:

               var_dump($values);
               die();

               break;
       }

        return $values;
    }
endif;