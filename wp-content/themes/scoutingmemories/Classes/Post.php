<?php


class Post
{

    public $post_id;

    public Taxonomy $state_tax;
    public Taxonomy $council_tax;
    public Taxonomy $lodge_tax;
    public Taxonomy $camp_tax;
    public Taxonomy $start_date_tax;
    public Taxonomy $end_date_tax;


    public $the_post;
    public $the_post_meta;

    public $title;


    public $state_val;
    public array $state_slugs;

    public $council;
    public $council_slug;
    public $lodge;
    public $camp;
    public $start_date;
    public $end_date;

    public $terms;

    public function __construct($post_id)
    {
        $this->post_id = $post_id;
        $this->the_post = get_post($this->post_id);
        $this->the_post_meta = get_post_meta($this->post_id);

        $this->title = $this->the_post->post_title;


        if (array_key_exists('state', $this->the_post_meta)) {
            $this->state_val = maybe_unserialize($this->the_post_meta['state'][0]);
        }
        if (array_key_exists('council', $this->the_post_meta)) {
            $council_keys = maybe_unserialize($this->the_post_meta['council'][0]);
        }
        if (array_key_exists('lodge', $this->the_post_meta)) {
            $this->lodge = maybe_unserialize($this->the_post_meta['lodge'][0]);
        }
        if (array_key_exists('camp', $this->the_post_meta)) {
            $this->camp = maybe_unserialize($this->the_post_meta['camp'][0]);
        }


        if (is_string($council_keys)) {

            if (is_numeric($council_keys)) {
                echo ' is numeric';
            }

            $council_keys = explode(', ', $council_keys);
        }

        if (is_string($this->state_val)) {
            $this->state_val = explode(', ', $this->state_val);
        }


        if (is_array($council_keys)) {

            foreach ($council_keys as $council_key) {
                if ($council_key != 'none') {
                    $entry_id = Entry::get_field_id_from_key($council_key);
                    if (!is_null($entry_id)) {

                        $entry = new Entry($entry_id);
                        //var_dump($entry);
                        //$this->council[] = [ "name" => $entry->name, "number" => $entry->number ];
                        $this->council[] = [
                            "name" => $entry->entry_array['council_name'],
                            "number" => $entry->entry_array['council_num']
                        ];

                        $this->council_slug[] = $entry->entry_array['council_slug'];

                    }
                }
            }


        }


        if (is_string($this->lodge)) {
            $this->lodge = explode(', ', $this->lodge);
        }

        if (is_string($this->camp)) {
            $this->camp = explode(', ', $this->camp);
        }


        $this->start_date = $this->the_post_meta['start_date'][0];
        $this->end_date = $this->the_post_meta['end_date'][0];


        ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
        // get taxonomy

        $this->terms[] = wp_get_post_terms($this->post_id, 'state');
        $this->terms[] = wp_get_post_terms($this->post_id, 'council');
        $this->terms[] = wp_get_post_terms($this->post_id, 'lodge');
        $this->terms[] = wp_get_post_terms($this->post_id, 'camp');
        $this->terms[] = wp_get_post_terms($this->post_id, 'start_date');
        $this->terms[] = wp_get_post_terms($this->post_id, 'end_date');


        $is_numeric = false;
        foreach ($this->state_val as $state) {
            if (is_numeric($state)) {
                //echo 'found another one';
                $is_numeric = true;
            }
        }

        if ($is_numeric) {

            $this->get_state_slugs();


            $this->state_tax = new Taxonomy('state', $this->post_id, $this->state_slugs);


        } else {

            $this->state_tax = new Taxonomy('state', $this->post_id, $this->state_val);


        }


        $this->council_tax = new Taxonomy('council', $this->post_id, $this->council_slug);
        $this->lodge_tax = new Taxonomy('lodge', $this->post_id, $this->lodge);
        $this->camp_tax = new Taxonomy('camp', $this->post_id, $this->camp);
        $this->start_date_tax = new Taxonomy('start_date', $this->post_id, (string)$this->start_date);
        $this->end_date_tax = new Taxonomy('end_date', $this->post_id, (string)$this->end_date);


        //if (empty($terms)) {

        $this->state_tax->update_tax();
        $this->council_tax->update_tax();
        $this->lodge_tax->update_tax();
        $this->camp_tax->update_tax();
        $this->start_date_tax->update_tax();
        $this->end_date_tax->update_tax();
        //}


    }


    private function get_state_slugs()
    {

        if (is_array($this->state_val)) {

            foreach ($this->state_val as $state) {
                $state = new Entry($state);
                $this->state_slugs[] = $state->entry_array['state_acl'];
            }

        } else {
            $state = new Entry($this->state_val);
            $this->state_slugs = $state->entry_array['state_acl'];
        }
    }


    public function say_states()
    {

        if (count($this->state_val) == 1) {
            return $this->state_val[0];
        } else {
            $html = '<ul class="small">';
            foreach ($this->state_val as $state) {
                $myState = new Entry($state);
                $html .= '<li>' . $myState->entry_array['state_acl'] . '</li>';
            }
            $html .= '</ul>';
            return $html;
        }
    }


    public function say_councils()
    {
        $html = '';
        $check_councils = is_countable($this->council);

        if (is_string($this->council[0])) return false;

        if ($check_councils) {
            if (($this->council[0]['name'] != null)) {

                if ((count($this->council) == 1)) {
                    return $this->council[0]['name'] . ' ' . $this->council[0]['number'];
                } else {
                    $html = '<ul class="small">';
                    foreach ($this->council as $council) {
                        $html .= '<li>' . $council['name'] . ' ' . $council['number'] . '</li>';
                    }
                    $html .= '</ul>';

                    return $html;
                }

            } else {
                return false;
            }
        }

    }


    public function say_camps()
    {
        $html = '';

        $check_camps = is_countable($this->camp[0]);


        if (is_string($this->camp[0])) {
            $entry_id = Entry::get_field_id_from_key($this->camp[0]);
            if (!is_null($entry_id)) {
                $entry = new Entry($entry_id);
                return $entry->entry_array['camp_name'];
            }

        }

        if ($check_camps) {
            if ((count($this->camp) == 1)) {


                if (count($this->camp[0]) > 1) {
                    $entry_id = Entry::get_field_id_from_key($this->camp[0]);
                    if (!is_null($entry_id)) {
                        $entry = new Entry($entry_id);
                        return $entry->entry_array['camp_name'];
                    }
                }

            } else {
                $html = '<ul class="small">';
                foreach ($this->camp as $camp) {

                    $entry_id = Entry::get_field_id_from_key($camp);
                    if (!is_null($entry_id)) {
                        $entry = new Entry($entry_id);
                        $html .= '<li>' . $entry->entry_array['camp_name'] . '</li>';
                    }


                }
                $html .= '</ul>';

                return $html;
            }
        }


    }


    public function say_lodges()
    {

        $html = '';

        $check_lodges = is_countable($this->lodge);

        if ($check_lodges) {
            if ((count($this->lodge) == 1)) {
                if ($this->lodge[0] != 'none') {
                    $entry_id = Entry::get_field_id_from_key($this->lodge[0]);
                    $entry = new Entry($entry_id);
                    return $entry->entry_array['lodge_name'];
                }
                return 'none';
            } else {
                $html = '<ul class="small">';
                foreach ($this->lodge as $lodge) {

                    $entry_id = Entry::get_field_id_from_key($lodge);
                    $entry = new Entry($entry_id);
                    $html .= '<li>' . $entry->entry_array['lodge_name'] . '</li>';
                }
                $html .= '</ul>';

                return $html;
            }
        }


    }


}