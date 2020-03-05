<?php

if (!function_exists('filter_states_options')) :
    add_filter('frm_setup_new_fields_vars', 'filter_states_options', 25, 2);
    function filter_states_options($values, $field)
    {
        if ($field->id == SM1_STATES_FID && !empty($values['options'])) {//Replace 125 with the ID of your Dynamic field

            $options = $values['options'];
            $n = 355; //this is the array index of the National BSA / High Adventure State
            $intl = [2030, 2029, 2032, 2031]; // array indexes of items in the list international states
            $intl_options = $new_options = [];
            $bsa_value = $options[$n];

            unset($options['']);
            unset($options[$n]);
            foreach ($intl as $key) {
                $intl_options[$key] = $options[$key];
                unset($options[$key]);
            }

            $new_options[''] = '';
            $new_options[$n] = $bsa_value;

            foreach ($intl as $key) $new_options[$key] = $intl_options[$key];
            foreach ($options as $id => $v) $new_options[$id] = $v;

            $values['options'] = $new_options;
        }
        return $values;
    }
endif;

