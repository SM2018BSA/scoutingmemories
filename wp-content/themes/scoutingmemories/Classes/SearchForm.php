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

    public function load_ajax()
    {

        if (!is_admin()) {


            // Register the JS file with a unique handle, file location, and an array of dependencies
            wp_register_script("search_ajax", get_template_directory_uri() . '/js/search_ajax.js', array('jquery'));

            // localize the script to your domain name, so that you can reference the url to admin-ajax.php file easily
            wp_localize_script('search_ajax', 'myCouncilAjax', array('ajaxurl' => admin_url('admin-ajax.php', $this->ssl)));
            wp_localize_script('search_ajax', 'myLodgeAjax', array('ajaxurl' => admin_url('admin-ajax.php', $this->ssl)));
            wp_localize_script('search_ajax', 'myCampAjax', array('ajaxurl' => admin_url('admin-ajax.php', $this->ssl)));

            // enqueue jQuery library and the script you registered above
            wp_enqueue_script('jquery');
            wp_enqueue_script('search_ajax');
        }
    }


    static function show_form()
    {


        $states_view_id = 2300; // local and live
        //$states_view_id = 1553; // online dev
        $states_view = new View($states_view_id);


        $html = '
		     <div class="card-body w-100 w-md-100 w-lg-75 w-xl-50   ">
                    <form method="post" enctype="multipart/form-data" class="row container-fluid frm-show-form  frm_pro_form " action="' . esc_url(admin_url('admin-post.php')) . '" >' .
            wp_nonce_field('search_form', 'search_nonce') . ' 
						
                        <input type="hidden" name="action" value="search_form">
                        <input type="hidden" name="page" value="info-page">



						<div class="mb-2 col-12" >
							<label class="form-label" >Select the following optional fields to search memories.</label>							
						</div>



                        <div class="mb-2 col-12" >
                            <label class="form-label" for="select_state">Select State</label>
                            <select name="select_state[]" class="form-select" id="select_state" multiple="multiple" style="display: none;">
                                ' . $states_view->show_view() . '
                            </select>
                        </div>
                        
                        <div id="select_council_loading" class="d-flex justify-content-center form-group col-12 hidden">
					    	<div class="spinner-border sm_green_color" role="status">
					    		<span class="sr-only">Loading...</span>
					    	</div>
					    </div>
					    
                        <div class="mb-2 col-12 hidden">
                            <label class="form-label" for="select_council">Select Council</label>
                            <select name="select_council[]" class="form-select" id="select_council" multiple="multiple" ></select>
                        </div>
                        
                        <div id="select_merged_council_loading" class="d-flex justify-content-center form-group col-12 hidden">
					    	<div class="spinner-border sm_green_color" role="status">
					    		<span class="sr-only">Loading...</span>
					    	</div>
					    </div>
					    
                        <div class="mb-2 col-12 hidden mr-0 pr-0">
                            <label class="form-label" for="select_council">Selected Merged Council</label>
                            <select name="select_merged_council[]" class="form-select" id="select_merged_council" multiple="multiple" ></select>
                        </div>
                        
                        <div id="select_lodge_loading" class="d-flex justify-content-center form-group col-12 hidden">
					    	<div class="spinner-border sm_green_color" role="status">
					    		<span class="sr-only">Loading...</span>
					    	</div>
					    </div>
					    
                        <div class="mb-2 col-12 hidden">
                            <label class="form-label" for="select_lodge">Select Lodge</label>
                            <select name="select_lodge[]" class="form-select" id="select_lodge" multiple="multiple" ></select>
                        </div>
                        
                        <div id="select_camp_loading" class="d-flex justify-content-center form-group col-12 hidden">
					    	<div class="spinner-border sm_green_color" role="status">
					    		<span class="sr-only">Loading...</span>
					    	</div>
					    </div>
					    
                        <div class="mb-2 col-12 hidden">
                            <label class="form-label" for="select_camp">Select Camp</label>
                            <select name="select_camp[]" class="form-select" id="select_camp" multiple="multiple" ></select>
                        </div>
                        <div class="mb-2 col-6 ">
                            <label class="form-label" for="start_date">Start Date</label>
                            <input name="start_date" class="form-control" id="start_date" value="1900"  />
                        </div>
                        <div class="mb-2 col-6 ">
                            <label class="form-label" for="end_date">End Date</label>
                            <input name="end_date" class="form-control" id="end_date" value="' . date('Y') . '" />
                        </div>
						<div class="col w-100 clearfix mb-3"></div>
                        <div class="mb-2 col-3">
                            <button type="submit" class="btn btn-primary">Submit</button>
                        </div>
                        <div class="mb-2 col-9">
                        	<div class="form-check-inline">
							    <label class="form-check-label">
							        <input type="checkbox" class="form-label form-check-input " id="show_merged" value=""> Show Merged Councils? 
							    </label>
						    </div>
                        </div>



                    </form>




                </div>';


        return $html;

    }


    static function show_form2()
    {


        $states_view_id = 2300; // local and live
        //$states_view_id = 1553; // online dev
        $states_view = new View($states_view_id);


        $html = '
		     <div class="card-body w-lg-50 w-md-100 mx-auto divi-form">
                    <form method="post" enctype="multipart/form-data" class="row frm-show-form  frm_pro_form " action="' . esc_url(admin_url('admin-post.php')) . '" >' .
            wp_nonce_field('search_form', 'search_nonce') . ' 
						
                        <input type="hidden" name="action" value="search_form">
                        <input type="hidden" name="page" value="info-page">



						<div class="mb-2 col-12" >
							<label class="form-label">Select the following optional fields to search memories.</label>							
						</div>



                        <div class="mb-2 col-4" >
                            <label class="form-label" for="select_state">Select State</label>
                            <select name="select_state[]" class="form-control" id="select_state" multiple="multiple" >
                                ' . $states_view->show_view() . '
                            </select>
                        </div>
                        
                        <div class="mb-2 col-4 ">
                            <label class="form-label" for="start_date">Start Date</label>
                            <input name="start_date" class="form-control" id="start_date" value="1900"  />
                        </div>
                        <div class="mb-2 col-4 ">
                            <label class="form-label" for="end_date">End Date</label>
                            <input name="end_date" class="form-control" id="end_date" value="2020"  />
                        </div>
                        
                        
                        <div id="select_council_loading" class="d-flex justify-content-center form-group col-4 hidden">
					    	<div class="spinner-border sm_green_color" role="status">
					    		<span class="sr-only">Loading...</span>
					    	</div>
					    </div>
                        <div class="mb-2 col-4 hidden">
                            <label class="form-label" for="select_council">Select Council</label>
                            <select name="select_council[]" class="form-control" id="select_council" multiple="multiple" ></select>
                        </div>
                        <div id="select_merged_council_loading" class="d-flex justify-content-center form-group col-4 hidden">
					    	<div class="spinner-border sm_green_color" role="status">
					    		<span class="sr-only">Loading...</span>
					    	</div>
					    </div>
                        <div class="form-group col-8 hidden mr-0 pr-0">
                            <label class="form-label" for="select_council">Selected Merged Council</label>
                            <select name="select_merged_council[]" class="form-control" id="select_merged_council" multiple="multiple" ></select>
                        </div>
                        <div id="select_lodge_loading" class="d-flex justify-content-center form-group col-4 hidden">
					    	<div class="spinner-border sm_green_color" role="status">
					    		<span class="sr-only">Loading...</span>
					    	</div>
					    </div>
                        <div class="form-group col-4 hidden">
                            <label class="form-label" for="select_lodge">Select Lodge</label>
                            <select name="select_lodge[]" class="form-control" id="select_lodge" multiple="multiple" ></select>
                        </div>
                        <div id="select_camp_loading" class="d-flex justify-content-center form-group col-4 hidden">
					    	<div class="spinner-border sm_green_color" role="status">
					    		<span class="sr-only">Loading...</span>
					    	</div>
					    </div>
                        <div class="form-group col-4 hidden">
                            <label class="form-label" for="select_camp">Select Camp</label>
                            <select name="select_camp[]" class="form-control" id="select_camp" multiple="multiple" ></select>
                        </div>



                        <div class="form-group col-3">
                            <button type="submit" class="btn btn-primary">Submit</button>
                        </div>
                        <div class="form-group col-9">
                        	<div class="form-check-inline">
							    <label class="form-check-label">
							        <input type="checkbox" class="form-control form-check-input" id="show_merged" value="">Show Merged Councils?
							    </label>
						    </div>
                        </div>



                    </form>




                </div>';


        return $html;

    }


    public static function search_form_js()
    {


        $html = '
		
		<script>

		 jQuery(document).ready( function() {

 
		
			

		



    });

		</script>
		
		';


        return $html;

    }

}