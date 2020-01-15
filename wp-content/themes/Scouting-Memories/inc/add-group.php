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
        if ($form_id == ADD_A_POST_FORMID) {


            // used to get my new posts ID
            $my_entry = FrmEntry::getOne($entry_id);
            $post_id = $my_entry->post_id;



            //  Get my slugs ////////////////////////
            $args = array();
            $slugs = array();

            //var_dump($my_entry);

            $state_form_ids   = $_POST['item_meta'][AAP_STATES_FID];
            $council_form_ids = $_POST['item_meta'][AAP_COUNCIL_FID];
            $lodge_form_ids   = $_POST['item_meta'][AAP_LODGE_FID];
            $camp_form_ids    = $_POST['item_meta'][AAP_CAMP_FID];

            get_update_values('state_ids',  $state_form_ids,$args);
            get_update_values('council_ids',$council_form_ids,$args);
            get_update_values('camp_ids',   $camp_form_ids,$args);
            get_update_values('lodge_ids',  $lodge_form_ids,$args);

            $slugs['state_slugs']    = get_slugs_value('state_ids',$args);
            $slugs['council_slugs']  = get_slugs_value('council_ids',$args);
            $slugs['camp_slugs']     = get_slugs_value('camp_ids',$args);
            $slugs['lodge_slugs']    = get_slugs_value('lodge_ids',$args);

            update_post_meta( $post_id, 'state', $state_form_ids  );
            update_entry($entry_id, AAP_FRM_ENTRY_ID, (string)$entry_id );

            foreach ($slugs as $key => $value) update_meta_slugs($key,$value,$post_id);



        }

    } //end of frm_after_create_entry


endif;