<?php
/*
 *  This gets executed every time a form is updated
 *
 *
 * */


if (!function_exists('after_entry_updated')) :
    add_action('frm_after_update_entry', 'after_entry_updated', 20, 2);
    function after_entry_updated($entry_id, $form_id)
    {
        // editing a Post
        if ($form_id == 6) {

            echo 'after_entry_updated';

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

            debug_to_console($_state_form_ids);

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

        return;
    }

endif;

//if (!function_exists('frm_set_edit_val')) :
//    add_filter('frm_setup_edit_fields_vars', 'frm_set_edit_val', 20, 3);
//    function frm_set_edit_val($values, $field, $entry_id)
//    {
//
//
//
//        return $values;
//    }
//endif;



