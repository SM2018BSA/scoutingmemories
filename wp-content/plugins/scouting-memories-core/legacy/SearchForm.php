<?php


class SearchForm
{

    public $ssl;

    public function __construct()
    {

        $this->ssl = SSL_MODE;


    }

    public function setup_hooks()
    {
        add_action('init', array(&$this, 'load_ajax'));
        add_action('admin_post_search_form', array(&$this, 'search_form'));
        add_action('admin_post_nopriv_search_form', array(&$this, 'search_form'));

        add_action('admin_post_search_category_form', array(&$this, 'search_category_form'));
        add_action('admin_post_nopriv_search_category_form', array(&$this, 'search_category_form'));


    }

    private function clean_request($value)
    {
          return Helpers::get_request_parameter($value, "");
    }

    public function search_form()
    {

        if (wp_verify_nonce($_REQUEST['search_nonce'], 'search_form')) {

            $form_url = home_url('/' . 'info-page');

            $state = $this->clean_request('select_state');
            $council = $this->clean_request('select_council');
            $lodge = $this->clean_request('select_lodge');
            $camp = $this->clean_request('select_camp');
            $start_date = $this->clean_request('start_date');
            $end_date = $this->clean_request('end_date');

            wp_redirect(esc_url_raw(
                add_query_arg(
                    array(
                        'state' => (is_array($state)) ? implode(',', $state) : $state,
                        'council' => (is_array($council)) ? implode(',', $council) : $council,
                        'lodge' => (is_array($lodge)) ? implode(',', $lodge) : $lodge,
                        'camp' => (is_array($camp)) ? implode(',', $camp) : $camp,
                        'start_date' => $start_date,
                        'end_date' => $end_date

                    ), $form_url)
            ));


        }


    }

    public function search_category_form()
    {

        if (wp_verify_nonce($_REQUEST['search_nonce'], 'search_form')) {


            $cat_term_id = $this->clean_request('cat');
            $state = $this->clean_request('select_state');
            $council = $this->clean_request('select_council');
            $lodge = $this->clean_request('select_lodge');
            $camp = $this->clean_request('select_camp');
            $start_date = $this->clean_request('start_date');
            $end_date = $this->clean_request('end_date');

            $category = get_category( $cat_term_id );



            $form_url = home_url('/' . $category->slug);


            wp_redirect(esc_url_raw(
                add_query_arg(
                    array(
                        'cat' => $category->term_id,
                        'state' => (is_array($state)) ? implode(',', $state) : $state,
                        'council' => (is_array($council)) ? implode(',', $council) : $council,
                        'lodge' => (is_array($lodge)) ? implode(',', $lodge) : $lodge,
                        'camp' => (is_array($camp)) ? implode(',', $camp) : $camp,
                        'start_date' => $start_date,
                        'end_date' => $end_date

                    ), $form_url)
            ));


        }


    }

    public function load_ajax()
    {

        if (!is_admin()) {


            // The search lists' script (javascript/search.js) is bundled into the theme's script.min.js;
            // the admin-ajax address and the nonce reach it through the form's data attributes.
        }
    }


    static function show_form()
    {
        // A theme may ship its own markup for this form (the Tailwind theme does);
        // without one - the old Bootstrap theme - the legacy markup below renders.
        $override = locate_template('template-parts/search/search-form.php');
        if ($override) {
            $smem_states_options = (new View(2300))->show_view();
            ob_start();
            include $override;
            return ob_get_clean();
        }

        // The markup lives in the theme's template (template-parts/search/); without one there is
        // nothing to print.
        return '';
    }


    static function show_form2()
    {
        // Theme override, as in show_form().
        $override = locate_template('template-parts/search/search-form2.php');
        if ($override) {
            $smem_states_options = (new View(2300))->show_view();
            ob_start();
            include $override;
            return ob_get_clean();
        }

        // The markup lives in the theme's template (template-parts/search/); without one there is
        // nothing to print.
        return '';
    }

    static function show_category_form()
    {

        $category = get_category( get_query_var( 'cat' ) );

        // Theme override, as in show_form().
        $override = locate_template('template-parts/search/category-form.php');
        if ($override) {
            $smem_states_options = (new View(2300))->show_view();
            $smem_category       = $category;
            ob_start();
            include $override;
            return ob_get_clean();
        }

        // The markup lives in the theme's template (template-parts/search/); without one there is
        // nothing to print.
        return '';
    }


}