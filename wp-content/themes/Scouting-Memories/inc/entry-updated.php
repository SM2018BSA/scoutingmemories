<?php
/*
 *  This gets executed every time a form is updated
 *
 *
 * */

if (!function_exists('after_entry_updated') ) :

    function after_entry_updated($entry_id, $form_id)
    {


// editing a Post
        if ($form_id == 6) {

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

// map user meta info
        if ($form_id == 30) {

            $current_user_id = get_current_user_id();

            $user_state = $_POST['item_meta']['394'];
            $user_council = $_POST['item_meta']['396'];
            $user_camp = $_POST['item_meta']['398'];
            $user_lodge = $_POST['item_meta']['399'];
            $user_category = $_POST['item_meta']['395'];
            $user_credit_author = $_POST['item_meta']['407'];
            $user_photographer = $_POST['item_meta']['408'];
            $user_contributors = $_POST['item_meta']['409'];
            $date_original = $_POST['item_meta']['412'];
            $user_date_digital = $_POST['item_meta']['414'];
            $user_pub_digital = $_POST['item_meta']['418'];
            $user_subject = $_POST['item_meta']['416'];
            $user_location = $_POST['item_meta']['417'];
            $user_identifier = $_POST['item_meta']['413'];
            $user_physical_description = $_POST['item_meta']['421'];
            $user_state_slug = $_POST['item_meta']['452'];
            $user_council_slug = $_POST['item_meta']['453'];
            $user_camp_slug = $_POST['item_meta']['454'];
            $user_lodge_slug = $_POST['item_meta']['455'];


            update_user_meta($current_user_id, 'user_state', $user_state);
            update_user_meta($current_user_id, 'user_council', $user_council);
            update_user_meta($current_user_id, 'user_camp', $user_camp);
            update_user_meta($current_user_id, 'user_lodge', $user_lodge);
            update_user_meta($current_user_id, 'user_category', $user_category);
            update_user_meta($current_user_id, 'user_credit_author', $user_credit_author);
            update_user_meta($current_user_id, 'user_photographer', $user_photographer);
            update_user_meta($current_user_id, 'user_contributors', $user_contributors);
            update_user_meta($current_user_id, 'date_original', $date_original);
            update_user_meta($current_user_id, 'user_date_digital', $user_date_digital);
            update_user_meta($current_user_id, 'user_pub_digital', $user_pub_digital);
            update_user_meta($current_user_id, 'user_subject', $user_subject);
            update_user_meta($current_user_id, 'user_location', $user_location);
            update_user_meta($current_user_id, 'user_identifier', $user_identifier);
            update_user_meta($current_user_id, 'user_physical_description', $user_physical_description);
            update_user_meta($current_user_id, 'user_state_slug', $user_state_slug);
            update_user_meta($current_user_id, 'user_council_slug', $user_council_slug);
            update_user_meta($current_user_id, 'user_camp_slug', $user_camp_slug);
            update_user_meta($current_user_id, 'user_lodge_slug', $user_lodge_slug);


        }


    }

    add_action('frm_after_update_entry', 'after_entry_updated', 10, 2);

endif;