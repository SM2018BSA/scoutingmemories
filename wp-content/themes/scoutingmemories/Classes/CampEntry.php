<?php


class CampEntry extends Entry
{

    public $name;
    public $slug;
    public $active;
    public $start;
    public $end;

    private $camp_state;
    private $camp_state_val;
    public $camp_state_slugs;

    private $council_state;
    private $council_state_val;
    private $council_names;
    private $council_names_val;
    public $council_slugs;

    private $author_uid;
    private $author_uid_val;

    public $linked_councils;


    public function __construct(int $entry_id = 0)
    {
        parent::__construct($entry_id);

        if ($entry_id) {

            $this->entry_array = $this->get_entry_array();


            (array_key_exists('ac_camp_name', $this->entry_array) ? $this->name = $this->entry_array['ac_camp_name'] : $this->name = '');
            (array_key_exists('ac_camp_slug', $this->entry_array) ? $this->slug = $this->entry_array['ac_camp_slug'] : $this->slug = '');
            (array_key_exists('ac_camp_start', $this->entry_array) ? $this->start = $this->entry_array['ac_camp_start'] : $this->start = '');
            (array_key_exists('ac_camp_end', $this->entry_array) ? $this->end = $this->entry_array['ac_camp_end'] : $this->end = '');
            (array_key_exists('ac_camp_state', $this->entry_array) ? $this->camp_state = $this->entry_array['ac_camp_state'] : $this->camp_state = '');
            (array_key_exists('ac_camp_state-value', $this->entry_array) ? $this->camp_state_val = $this->entry_array['ac_camp_state-value'] : $this->camp_state_val = '');
            (array_key_exists('ac_council_state', $this->entry_array) ? $this->council_state = $this->entry_array['ac_council_state'] : $this->council_state = '');
            (array_key_exists('ac_council_state-value', $this->entry_array) ? $this->council_state_val = $this->entry_array['ac_council_state-value'] : $this->council_state_val = '');
            (array_key_exists('ac_council_name', $this->entry_array) ? $this->council_names = $this->entry_array['ac_council_name'] : $this->council_names = '');
            (array_key_exists('ac_council_name-value', $this->entry_array) ? $this->council_names_val = $this->entry_array['ac_council_name-value'] : $this->council_names_val = '');
            (array_key_exists('ac_user_id', $this->entry_array) ? $this->author_uid = $this->entry_array['ac_user_id'] : $this->author_uid = '');
            (array_key_exists('ac_user_id-value', $this->entry_array) ? $this->author_uid_val = $this->entry_array['ac_user_id-value'] : $this->author_uid_val = '');
            (array_key_exists('ac_add_council_slug', $this->entry_array) ? $this->linked_councils = $this->entry_array['ac_add_council_slug'] : $this->linked_councils = '');
            (array_key_exists('ac_camp_active', $this->entry_array) ? $this->active = $this->entry_array['ac_camp_active'] : $this->active = '');


            $this->get_state_slugs();


            if (!array_key_exists('ac_council_slug', $this->entry_array)) {

                $this->council_slugs = $this->get_council_slugs();

            } else {

                $this->council_slugs = $this->entry_array['ac_council_slug-value'];
            }

            if (!is_array($this->council_slugs)) {
                $this->council_slugs = array($this->council_slugs);
            }

            if (!is_array($this->camp_state_slugs)) {
                $this->camp_state_slugs = array($this->camp_state_slugs);
            }


        }


    }

    public function setup_hooks()
    {
        add_action('frm_after_create_entry', array(&$this, 'frm_after_create_entry'), 30, 2);
        add_action('frm_where_filter', array(&$this, 'frm_where_filter'), 10, 2);
    }


    public function frm_where_filter($where, $args)
    {
        if ($args['display']->ID == ALL_CAMPS_SEARCH_VIEWID) {

            $search_val = array();
            $linked_ids = array();


            foreach ($args['where_val'] as $entry_id) {
                $council = new Entry($entry_id);
                $search_val[] = $council->entry_array['council_slug'];
                $linked_ids[] = array(Entry::get_repeater_ids_from_key($council->entry_array['council_slug'], AACAMP_ADD_LINKED_COUNCIL_SLUGS_FID));
            }

            //var_dump($search_val);


            if ($search_val) {
                $where = "( (meta_value like '%" . implode($search_val) . "%' and fi.id = " . AACAMP_COUNCIL_SLUG_FID . ") ";
                $where .= " OR (meta_value like '%" . implode($linked_ids) . "%' and fi.id = " . AACAMP_ADD_LINKED_COUNCILS_FID . ") )";
            }


        }

        return $where;
    }


    public function frm_after_create_entry($entry_id, $form_id)
    {

        if ($form_id === ADD_A_CAMP_FORMID) {


            $args = array();
            $camp_name = $_POST['item_meta'][AACAMP_NAME_FID];
            $args['camp_name'] = isset($camp_name) ? $camp_name : ''; // field IDs from the form

            $camp_name = clean($camp_name);
            $camp_name = $camp_name . '_' . $entry_id;


            Entry::update_an_entry(AACAMP_CAMP_SLUG_FID, 'ac_camp_slug', $camp_name, $entry_id);


            AdvancedCustomField::update_select($camp_name, wp_unslash($args['camp_name']), CAMP_ACF);

            // Make sure council slug gets updated
            $camp = new CampEntry($entry_id);


        }

    }

    public function get_council_slugs()
    {
        if (!is_null($this->council_names_val)) {

            if (!is_array($this->council_names_val)) {


                if (strlen($this->council_names_val) > 1) {

                    $camp_council = new CouncilEntry($this->council_names_val);

                    if (is_array($camp_council->council_slug)) {
                        $add_camp_council_slug = implode(',', $camp_council->council_slug);
                    } else {
                        $add_camp_council_slug = $camp_council->council_slug;
                    }

                    $added = $this->update_meta_entry(AACAMP_COUNCIL_SLUG_FID, 'ac_council_slug', $add_camp_council_slug);
                    if (!$added) {
                        $this->update_entry(AACAMP_COUNCIL_SLUG_FID, 'ac_council_slug', $add_camp_council_slug);
                    }
                    return $add_camp_council_slug;

                }


            } else {


                $camp_councils = array();

                foreach ($this->council_names_val as $council_id) {
                    $camp_council = new CouncilEntry($council_id);
                    $camp_councils[] = $camp_council->council_slug;
                }

                $added = $this->update_meta_entry(AACAMP_COUNCIL_SLUG_FID, 'ac_council_slug', $camp_councils);
                if (!$added) {
                    $this->update_entry(AACAMP_COUNCIL_SLUG_FID, 'ac_council_slug', $camp_councils);
                }

                return $camp_councils;


            }
        }


        return false;
    }

    public static function get_camp_name($camp_slug)
    {
        $entry_id = Entry::get_field_id_from_key($camp_slug);
        $entry = new Entry($entry_id);
        if (!is_null($entry->entry_array)) {
            if (array_key_exists('ac_camp_name', $entry->entry_array)) {
                return $entry->entry_array['ac_camp_name'];
            }
        }

        return null;
    }


    private function get_state_slugs()
    {

        if (is_array($this->camp_state_val)) {

            foreach ($this->camp_state_val as $state) {
                $state = new Entry($state);
                $this->camp_state_slugs[] = $state->entry_array['state_acl'];
            }

        } else {
            $state = new Entry($this->camp_state_val);
            $this->camp_state_slugs = $state->entry_array['state_acl'];
        }

    }




    //// // //
    ///
    /// This function updates Camp indexing. Anything marked as active will have it's end date updated to the current year
    /// Echo the result to show how many indexes were updated.
    //
    public static function update_active()
    {
        $camp_entries = FrmEntry::getAll(['form_id' => ADD_A_CAMP_FORMID], '', '', true);
        $active_camps = array();
        $current_year = date("Y");


        if (!Helpers::multiKeyExists("metas", $camp_entries)) {
            foreach ($camp_entries as $key => $value) {
                $metas = FrmEntry::get_meta($value);
                $metas = Helpers::object_to_array($metas);

                if (array_key_exists("metas", $metas)) {
                    if ($metas["metas"][AACAMP_CAMP_ACTIVE_FID] == 'Yes') {
                        $active_camp = new Entry($value->id);
                        $active_camp->entry_array['ac_camp_end'] = (string)$current_year;
                        Entry::update_an_entry(AACAMP_END_DATE_FID, 'ac_camp_end', (string)$current_year, $active_camp->entry_id);
                        $active_camps[] = $active_camps;
                    }
                }


            }

        } else {
            foreach ($camp_entries as $key => $value) {
                if ($value->metas[AACAMP_CAMP_ACTIVE_FID] == 'Yes') {
                    $active_camp = new Entry($value->id);
                    $active_camp->entry_array['ac_camp_end'] = (string)$current_year;
                    Entry::update_an_entry(AACAMP_END_DATE_FID, 'ac_camp_end', (string)$current_year, $active_camp->entry_id);
                    $active_camps[] = $active_camp;
                }
            }
        }

        return count($active_camps);
    }


}