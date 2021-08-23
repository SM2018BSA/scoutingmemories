<?php

//  $entries is an array of entry ids
//  $views is an array of view ids
//

class Form {

	protected $form_id;
	public $entries;
	public $views;

	public function __construct(int $form_id = 0) {
		$this->form_id = $form_id;
	}




	/*   $title Bool:       show or hide title of form
	*   $description Bool:      show or hide form description
	*   $params:           array for optional parameters ('tab' => 'defaults')
	*
	*   return String:     html of form
	* */
	public function show_form( $title = false, $description = false, $minimized = '0') {
		return FrmFormsController::get_form_shortcode(array('id' => $this->form_id, 'title' => $title, 'description' => $description, 'minimized'=>$minimized));
	}



}