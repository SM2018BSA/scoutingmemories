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
        if ($form_id == ADD_A_POST_FORMID) {


            // used to get my new posts ID
            $my_entry = FrmEntry::getOne($entry_id);
            $post_id = $my_entry->post_id;



            //  Get my slugs ////////////////////////
            $args = array();
            $slugs = array();

            var_dump($my_entry);

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

            foreach ($slugs as $key => $value) update_meta_slugs($key,$value,$post_id);

        }

        return;
    }

endif;





// - - - --  UTILITY FUNCTION FOR UPDATING POSTS -- - - -- //


if (!function_exists('get_update_values')) :
    function get_update_values($which_arg, $which_ids, &$args) {
        if (isset($which_ids) && is_array($which_ids)) {
            foreach ($which_ids as $which_id) :
                $which_id = sanitize_text_field($which_id);
                $args[$which_arg][] = isset($which_id) ? $which_id : '';
            endforeach;
        } else {
            $args[$which_arg][] = sanitize_text_field($which_ids);
        }
    }
endif;


if (!function_exists('get_slugs_value')) :
    function get_slugs_value($which_arg, &$args ) {
        $field_ids = array(
            "state_ids"   => AASTATE_STATE_ACL_FID,
            "council_ids" => AACOUNCIL_COUNCIL_SLUG_FID,
            "camp_ids"    => AACAMP_CAMP_SLUG_FID,
            "lodge_ids"   => AALODGE_LODGE_SLUG_FID
        );
        $slugs = array();
        foreach ($args[$which_arg] as $value )
            if ((int)$value > 0)
                $slugs[] = get_field_val($field_ids[$which_arg], $value);
            else
                $slugs[]='';
        return $slugs;
    }
endif;


if(!function_exists('update_meta_slugs')) :
    function update_meta_slugs($which_slug, $meta_value, $post_id)
    {
        $meta_key = array(
            "state_slugs"   => "state",
            "council_slugs" => "council",
            "camp_slugs"    => "camp",
            "lodge_slugs"   => "lodge"
        );
        if (metadata_exists('post', $post_id, $meta_key[$which_slug]))  // we have one lets updated it
            update_post_meta($post_id, $meta_key[$which_slug], $meta_value);
        else //we dont have one lets add a new one
            add_post_meta($post_id, $meta_key[$which_slug], $meta_value);
    }
endif;