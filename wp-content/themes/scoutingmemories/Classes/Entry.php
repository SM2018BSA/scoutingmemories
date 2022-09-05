<?php
require_once (get_template_directory() . '/Classes/Helpers.php');

Helpers::check_file_access();


class Entry
{

    public $entry_id;
    public $entry_array;


    public function __construct($entry_id = null)
    {
        if ($entry_id == null) {
            return null;
        }
        $this->entry_id = $entry_id;
        $this->entry_array = $this->get_entry_array();
    }

//	public function set_entry() {
//		$this->entry = FrmProEntriesController::show_entry_shortcode( array( 'id' => $this->entry_id,  'plain_text' => 0, 'format' => 'text' ) );
//	}

    public function get_entry_array()
    {
        return FrmProEntriesController::show_entry_shortcode(array('id' => $this->entry_id, 'format' => 'array'));
    }


    public function update_entry($field_id, $meta_key, $value)
    {
        return FrmEntryMeta::update_entry_meta($this->entry_id, $field_id, $meta_key, $value);
    }

    public function update_meta_entry($field_id, $meta_key, $meta_value)
    {
        return FrmEntryMeta::add_entry_meta($this->entry_id, $field_id, $meta_key, $meta_value);
    }


    public static function get_repeater_ids_from_key($field_key, $field_id)
    {
        global $wpdb;
        $entry_id = $wpdb->get_var($wpdb->prepare("SELECT item_id FROM $wpdb->prefix" . "frm_item_metas WHERE meta_value LIKE %s AND field_id=" . $field_id, $field_key));
        if ($wpdb->last_error !== '') {
            $wpdb->print_error();
        }

        return $entry_id;
    }

    public static function get_field_id_from_key($field_key)
    {
        if (is_array($field_key)) {
            $field_key = implode(",", $field_key);
        }
        global $wpdb;
        $entry_id = $wpdb->get_var($wpdb->prepare("SELECT item_id FROM $wpdb->prefix" . "frm_item_metas WHERE meta_value LIKE %s", $field_key));
        if ($wpdb->last_error !== '') {
            $wpdb->print_error();
        }

        return $entry_id;
    }


    public static function update_an_entry($field_id, $meta_key, $value, $entry_id)
    {
        return FrmEntryMeta::update_entry_meta($entry_id, $field_id, $meta_key, $value);
    }


    public static function get_field_val($field_id, $entry_id, $user_id = null)
    {
        if (is_null($user_id)) {
            return FrmProEntriesController::get_field_value_shortcode(array(
                'field_id' => $field_id,
                'entry' => $entry_id
            ));
        } else {
            return FrmProEntriesController::get_field_value_shortcode(array(
                'field_id' => $field_id,
                'user_id' => $user_id
            ));
        }
    }




    //    Function to search for multidimentional array
    //
    public static function search($array, $key, $value)
    {

        $result = array();

        // RecursiveArrayIterator to traverse an
        // unknown amount of sub arrays within
        // the outer array.
        $arrIt = new RecursiveArrayIterator($array);

        // RecursiveIteratorIterator used to iterate
        // through recursive iterators
        $it = new RecursiveIteratorIterator($arrIt);

        foreach ($it as $sub) {

            // Current active sub iterator
            $subArray = $it->getSubIterator();

            if ($subArray[$key] === $value) {
                $result[] = iterator_to_array($subArray);
            }
        }

        return $result;
    }


    private static function get_ids_query($where, $order_by, $limit, $unique, $args, array &$query)
    {
        global $wpdb;
        $query[] = 'SELECT';
        $defaults = array(
            'return_parent_id' => false,
            'return_parent_id_if_0_return_id' => false,
        );
        $args = array_merge($defaults, $args);

        if ($unique) {
            $query[] = 'DISTINCT';
        }

        if ($args['return_parent_id_if_0_return_id']) {
            $query[] = 'IF ( e.parent_item_id = 0, it.item_id, e.parent_item_id )';
        } elseif ($args['return_parent_id']) {
            $query[] = 'e.parent_item_id';
        } else {
            $query[] = 'it.item_id';
        }

        $query[] = 'FROM ' . $wpdb->prefix . 'frm_item_metas it LEFT OUTER JOIN ' . $wpdb->prefix . 'frm_fields fi ON it.field_id=fi.id';

        $query[] = 'INNER JOIN ' . $wpdb->prefix . 'frm_items e ON (e.id=it.item_id)';
        if (is_array($where)) {
            if (!$args['is_draft']) {
                $where['e.is_draft'] = 0;
            } elseif ($args['is_draft'] == 1) {
                $where['e.is_draft'] = 1;
            }

            if (!empty($args['user_id'])) {
                $where['e.user_id'] = $args['user_id'];
            }
            $query[] = Database::prepend_and_or_where(' WHERE ', $where) . $order_by . $limit;

            if ($args['group_by']) {
                $query[] = ' GROUP BY ' . sanitize_text_field($args['group_by']);
            }

            return;
        }

        $draft_where = '';
        $user_where = '';
        if (!$args['is_draft']) {
            $draft_where = $wpdb->prepare(' AND e.is_draft=%d', 0);
        } elseif ($args['is_draft'] == 1) {
            $draft_where = $wpdb->prepare(' AND e.is_draft=%d', 1);
        }

        if (!empty($args['user_id'])) {
            $user_where = $wpdb->prepare(' AND e.user_id=%d', $args['user_id']);
        }

        if (strpos($where, ' GROUP BY ')) {
            // don't inject WHERE filtering after GROUP BY
            $parts = explode(' GROUP BY ', $where);
            $where = $parts[0];
            $where .= $draft_where . $user_where;
            $where .= ' GROUP BY ' . $parts[1];
        } else {
            $where .= $draft_where . $user_where;
        }

        // The query has already been prepared
        $query[] = Database::prepend_and_or_where(' WHERE ', $where) . $order_by . $limit;
    }


    public static function getEntryIds($where = array(), $order_by = '', $limit = '', $unique = true, $args = array())
    {
        $defaults = array(
            'is_draft' => false,
            'user_id' => '',
            'group_by' => '',
        );
        $args = wp_parse_args($args, $defaults);


        $query = array();
        self::get_ids_query($where, $order_by, $limit, $unique, $args, $query);


        $query = implode(' ', $query);


        $cache_key = 'ids_' . Helpers::maybe_json_encode($where) . $order_by . 'l' . $limit . 'u' . $unique . Helpers::maybe_json_encode($args);
        $type = 'get_' . (' LIMIT 1' === $limit ? 'var' : 'col');


        return Database::check_cache($cache_key, 'frm_entry', $query, $type);
    }


}