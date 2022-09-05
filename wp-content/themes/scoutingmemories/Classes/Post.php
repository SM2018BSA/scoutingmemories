<?php
require_once(get_template_directory() . '/Classes/Helpers.php');
Helpers::check_file_access();


class Post
{

    public $post_id;

//    public Taxonomy $state_tax;
//    public Taxonomy $council_tax;
//    public Taxonomy $lodge_tax;
//    public Taxonomy $camp_tax;
//    public Taxonomy $start_date_tax;
//    public Taxonomy $end_date_tax;


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

        if ($post_id === null) {
            return false;
        }

        $this->post_id = $post_id;
        $this->the_post = get_post($this->post_id);
        $this->the_post_meta = get_post_meta($this->post_id);

        $this->title = $this->the_post->post_title;

        $council_keys = null;


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


        if (!is_null($council_keys) && is_string($council_keys)) {

            $council_keys = explode(', ', $council_keys);
        }

        if (is_string($this->state_val)) {
            $this->state_val = explode(', ', $this->state_val);
        }

        if (is_numeric($this->state_val)) {
            $this->state_val = PostEntry::get_state_slugs_from($this->state_val);
        }


        if (!is_null($council_keys) && is_array($council_keys)) {

            foreach ($council_keys as $council_key) {

                $council_name = CouncilEntry::get_council_name($council_key);
                $council_number = CouncilEntry::get_council_number($council_key);

                $this->council[] = [
                    "name" => $council_name,
                    "number" => $council_number
                ];
                $this->council_slug[] = $council_key;


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

        $this->terms['state'] = wp_get_post_terms($this->post_id, 'state');
        $this->terms['council'] = wp_get_post_terms($this->post_id, 'council');
        $this->terms['lodge'] = wp_get_post_terms($this->post_id, 'lodge');
        $this->terms['camp'] = wp_get_post_terms($this->post_id, 'camp');
        $this->terms['start_date'] = wp_get_post_terms($this->post_id, 'start_date');
        $this->terms['end_date'] = wp_get_post_terms($this->post_id, 'end_date');


    }


    private
    function get_state_slugs()
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


    public
    function say_states()
    {
        $html = '';

        if (count($this->state_val) == 1 && !is_null($this->state_val)) {


            if (ctype_digit($this->state_val[0])) {
                $myState = new StateEntry($this->state_val[0]);
                $html .= $myState->name;
            } else {

                $myState = StateEntry::get_state_name($this->state_val[0]);
                $html .= $this->state_val[0];
            }

            return $html;

        } else {

            $html = '<ul class="small">';
            foreach ($this->state_val as $state) {

                if (ctype_digit($state)) {
                    $myState = new StateEntry($state);
                    $html .= '<li class="name">' . $myState->name . '</li>';
                } elseif (is_string($state)) {
                    $html .= '<li>' . $state . '</li>';
                }


            }
            $html .= '</ul>';
            return $html;
        }
    }


    public function say_councils()
    {
        $html = '';

        if (is_null($this->council)) {
            return "";
        }

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
        if (is_null($this->camp)) {
            return "";
        }

        $check_camps = is_countable($this->camp[0]);

        if (is_string($this->camp[0])) {

            return CampEntry::get_camp_name($this->camp[0]);

        }

        if ($check_camps) {
            if ((count($this->camp) == 1)) {
                if (count($this->camp[0]) > 1) {
                    return CampEntry::get_camp_name($this->camp[0]);
                }

            } else {
                $html = '<ul class="small">';
                foreach ($this->camp as $camp_slug) {
                    if (!is_null($camp_slug)) {
                        $camp_name = CampEntry::get_camp_name($camp_slug);
                        $html .= '<li>' . $camp_name . '</li>';
                    }
                }
                $html .= '</ul>';
                return $html;
            }
        }


    }


    public
    function say_lodges()
    {
        $html = '';
        if (is_null($this->lodge)) {
            return "";
        }

        $check_lodges = is_countable($this->lodge);


        if ($check_lodges) {
            if ((count($this->lodge) == 1)) {
                if ($this->lodge[0] != 'none') {


                    return LodgeEntry::get_lodge_name($this->lodge[0]);

                }
                return 'none';
            } else {
                $html = '<ul class="small">';
                foreach ($this->lodge as $lodge_slug) {

                    $lodge_name = LodgeEntry::get_lodge_name($lodge_slug);
                    $html .= '<li>' . $lodge_name . '</li>';
                }
                $html .= '</ul>';

                return $html;
            }
        }


    }


}