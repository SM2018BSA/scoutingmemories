<?php


class View {

	public $view_id;

	public function __construct(int $view_id = 0) {
		$this->view_id = $view_id;




	}

	public function setup_hooks() {
		add_filter( 'frm_prev_page_link',  array(&$this, 'change_pagination_link'), 10, 2 );
		add_filter( 'frm_first_page_link', array(&$this, 'change_pagination_link'), 10, 2 );
		add_filter( 'frm_page_link',       array(&$this, 'change_pagination_link'), 10, 2 );
		add_filter( 'frm_last_page_link',  array(&$this, 'change_pagination_link'), 10, 2 );
		add_filter( 'frm_next_page_link',  array(&$this, 'change_pagination_link'), 10, 2 );
	}

	/*
	*   $view_id Int:      id of the view to show
	*   $filter String:    value for filter
	*
	*   return String:     html of view
	*
	*   https://formidableforms.com/knowledgebase/publish-a-view/
	* */
	public function show_view($args = array(), $filter = 'limited' )	{

		$array = ['id' => $this->view_id, 'filter' => $filter];
		$array = array_merge($array, $args);

		return FrmProDisplaysController::get_shortcode($array);
	}





}