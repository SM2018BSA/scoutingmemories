<?php
/*
 *  This is executed every time a form is submitted
 *
 *
 * */

if (!function_exists('add_group')) :
    add_action('frm_after_create_entry', 'add_group', 30, 2);
    function add_group($entry_id, $form_id)
    {

        // Add a Council
        if ($form_id == 8) {

            $field_id = 105; // hidden field form id for the council slug in the add a council form
            $field_acl = 'field_5c1700423245d';

            $args = array();
            $council_name = sanitize_text_field($_POST['item_meta'][98]);
            $args['council_name'] = isset($council_name) ? $council_name : '';

            $council_num = sanitize_text_field($_POST['item_meta'][138]);
            $args['council_num'] = isset($council_num) ? $council_num : '';

            $council_name = clean($council_name);
            $council_name = $council_name . '_' . $args['council_num'];

            // add the council_name to the hidden field for the cat slug
            FrmEntryMeta::update_entry_meta($entry_id, $field_id, '', $council_name);


            $selector = $field_acl;
            $field = acf_get_field($selector, true);
            $field['choices'][$council_name] = wp_unslash($args['council_name']);
            acf_update_field($field);

        }

        // Add a Lodge
        if ($form_id == 7) {

            $field_id = 97; // hidden field id for the slug
            $field_acl = 'field_5c1700203245c';

            $args = array();
            $lodge_name = sanitize_text_field($_POST['item_meta'][90]);
            $args['lodge_name'] = isset($lodge_name) ? $lodge_name : 'none'; // field IDs from the form

            $lodge_num = sanitize_text_field($_POST['item_meta'][91]);
            $args['lodge_num'] = isset($lodge_num) ? $lodge_num : '';

            $lodge_name = clean($lodge_name);
            $lodge_name = $lodge_name . '_' . $args['council_num'];

            // add the lodge_name to the hidden field for the cat slug
            FrmEntryMeta::update_entry_meta($entry_id, $field_id, '', $lodge_name);

            $selector = $field_acl;
            $field = acf_get_field($selector, true);
            $field['choices'][$lodge_name] = wp_unslash($args['lodge_name']);
            acf_update_field($field);


        }


        // Add a Camp
        if ($form_id == 11) {

            $field_id = 123; // hidden field id for the slug
            $field_acl = 'field_5c1700163245b';

            $args = array();
            $camp_name = $_POST['item_meta'][117];
            $args['camp_name'] = isset($camp_name) ? $camp_name : ''; // field IDs from the form

            $camp_name = clean($camp_name);
            $camp_name = $camp_name . '_' . $entry_id;

            // add the lodge_name to the hidden field for the cat slug
            FrmEntryMeta::update_entry_meta($entry_id, $field_id, '', $camp_name);

            $selector = $field_acl;
            $field = acf_get_field($selector, true);
            $field['choices'][$camp_name] = wp_unslash($args['camp_name']);
            acf_update_field($field);

        }


        // Add a Post
        if ($form_id == 6) {

            // used to get my new posts ID
            $my_entry = FrmEntry::getOne($entry_id);


            //  Get my slugs ////////////////////////
            $args = array();

//            $council_form_id = sanitize_text_field($_POST['item_meta'][AAP_COUNCIL_FID]);
//
//

            $state_form_ids = $_POST['item_meta'][AAP_STATES_FID];
            if (isset($state_form_ids)) {
                if (is_array($state_form_ids)) {
                    foreach ($state_form_ids as $state_form_id) :
                        $state_form_id = sanitize_text_field($state_form_id);
                        $args['state_ids'][] = isset($state_form_id) ? $state_form_id : '';
                    endforeach;
                } else {
                    $args['state_ids'] = isset($state_form_ids) ? sanitize_text_field($state_form_ids) : ''; // field IDs from your form
                }
            }


            $council_form_ids = $_POST['item_meta'][AAP_COUNCIL_FID];
            if (isset($council_form_ids)) {
                if (is_array($council_form_ids)) {
                    foreach ($council_form_ids as $council_form_id) :
                        $council_form_id = sanitize_text_field($council_form_id);
                        $args['council_ids'][] = isset($council_form_id) ? $council_form_id : '';
                    endforeach;
                } else {
                    $args['council_ids'] = isset($council_form_ids) ? sanitize_text_field($council_form_ids) : ''; // field IDs from your form

                }
            }

            $camp_form_ids = $_POST['item_meta'][AAP_CAMP_FID];
            if (isset($camp_form_ids)) {
                if (is_array($camp_form_ids)) {
                    foreach ($camp_form_ids as $camp_form_id) :
                        $camp_form_id = sanitize_text_field($camp_form_id);
                        $args['camp_ids'][] = isset($camp_form_id) ? $camp_form_id : '';
                    endforeach;
                } else {
                    $args['camp_ids'] = isset($camp_form_ids) ? sanitize_text_field($camp_form_ids) : ''; // field IDs from your form

                }
            }



            $lodge_form_ids = $_POST['item_meta'][AAP_LODGE_FID];
            if (isset($lodge_form_ids)) {
                if (is_array($lodge_form_ids)) {
                    foreach ($lodge_form_ids as $lodge_form_id) :
                        $lodge_form_id = sanitize_text_field($lodge_form_id);
                        $args['lodge_ids'][] = isset($lodge_form_id) ? $lodge_form_id : '';
                    endforeach;
                } else {
                    $args['lodge_ids'] = isset($lodge_form_ids) ? sanitize_text_field($lodge_form_ids) : ''; // field IDs from your form

                }
            }





            if (is_array($state_form_ids)) {
                foreach ($args['state_ids'] as $state_form_ids => $key) :
                    if ((int)$key > 0)
                        $state_slugs[] = get_field_val(S_STATE_ACL_FID, $key);
                endforeach;
            } else {

                $state_slugs[] = get_field_val(S_STATE_ACL_FID, $state_form_ids);


            }


            if (is_array($council_form_ids)) {
                foreach ($args['council_ids'] as $council_form_ids => $key) :
                    if ((int)$key > 0)
                        $council_slugs[] = get_field_val(AACOUNCIL_COUNCIL_SLUG_FID, $key);
                endforeach;
            } elseif ($council_form_ids > 0) {

                $council_slugs[] = get_field_val(AACOUNCIL_COUNCIL_SLUG_FID, $args['council_ids']);

            } else {
                $council_slugs[] = 'none';
            }

            if (is_array($camp_form_ids)) {
                foreach ($args['camp_ids'] as $camp_form_ids => $key) :
                    if ((int)$key > 0)
                        $camp_slugs[] = get_field_val(AACAMP_CAMP_SLUG_FID, $key);
                endforeach;
            } elseif ($camp_form_ids > 0) {

                $camp_slugs[] = get_field_val(AACAMP_CAMP_SLUG_FID, $args['camp_ids']);

            } else {
                $camp_slugs[] = 'none';
            }


            if (is_array($lodge_form_ids)) {
                foreach ($args['lodge_ids'] as $lodge_form_ids => $key) :
                    if ((int)$key > 0)
                        $lodge_slugs[] = get_field_val(AALODGE_LODGE_SLUG_FID, $key);
                endforeach;
            } elseif ($lodge_form_ids > 0) {

                $lodge_slugs[] = get_field_val(AALODGE_LODGE_SLUG_FID, $args['lodge_ids']);

            } else {
                $lodge_slugs[] = 'none';
            }





            ///  if we have a council slug add it
            ///

            $post_id = $my_entry->post_id;

            if (isset($council_slugs)) {
                $meta_key = 'council';
                $meta_value = $council_slugs;
                update_post_meta($post_id, $meta_key, $meta_value);
            }

            ///  if we have a lodge slug add it
            if (isset($lodge_slugs)) {
                $meta_key = 'lodge';
                $meta_value = $lodge_slugs;
                update_post_meta($post_id, $meta_key, $meta_value);
            }

            /// if we have a camp slug add it
            if (isset($camp_slugs)) {
                $meta_key = 'camp';
                $meta_value = $camp_slugs;
                update_post_meta($post_id, $meta_key, $meta_value);
            }

            /// if we have an entry ID save it with the post
            if (isset($entry_id)) {
                $meta_key = 'frm_entry_id';
                $meta_value = $entry_id;
                update_post_meta($post_id, $meta_key, $meta_value);
            }

            // if we have states selected
            if (isset($state_slugs)) {
                $meta_key = 'state';
                $meta_value = $state_slugs;
                update_post_meta($post_id, $meta_key, $meta_value);

            }

        }




    } //end of frm_after_create_entry


endif;