<?php


Class Taxonomy {

	public $post_id;
	public $taxonomy;
	public $value;


	public function __construct($taxonomy_name, $post_id, $value='') {
		$this->taxonomy  = $taxonomy_name;
		$this->post_id   = $post_id;
		$this->value     = $value;
	}



	public function update_tax(){
		wp_set_post_terms( $this->post_id, $this->value,  $this->taxonomy,  false );
	}




}