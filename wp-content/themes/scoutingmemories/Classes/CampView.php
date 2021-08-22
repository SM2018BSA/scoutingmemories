<?php



class CampView extends View {

	public function __construct( int $view_id = 3102 ) {
		parent::__construct( $view_id );
	}

	public function show_view( $states = array( 'search_param' => 'General Herkimer Council' ), $filter = 'limited' ) {
		return parent::show_view( $states, $filter );
	}


	public static function show_static_view( $council_slug = 'miami_valley_council_444', $filter = 'limited' ) {

		$search_param = array( 'meta_value like' => $council_slug, 'fi.form_id' => ADD_A_CAMP_FORMID );
		$entry_ids    = FrmEntrymeta::getEntryIds( $search_param );


		if ( ! empty( $entry_ids ) ) {
			$entries = FrmEntry::getAll( array( 'it.id' => $entry_ids ), '', '', true );

			return CampView::show_mview( $entries );
		} else {
			return '';
		}


	}



	public static function show_mview( $entries, $selected = false ) {
		$output     = '';
		$camp_name = '';
		$camp_slug = '';


		foreach ( $entries as $key => $entry ) {

			if ( ! is_null( $key ) ) {
				$camp_name = $entry->metas[ AACAMP_NAME_FID ];
				$camp_slug = $entry->metas[ AACAMP_CAMP_SLUG_FID ];

				if ( $selected ) {
					$output .= '<option value="' . $camp_slug . '" selected="selected">' . $camp_name . '</option>';
				} else {
					$output .= '<option value="' . $camp_slug . '" >' . $camp_name . '</option>';
				}
			}

		}

		return $output;
	}



	public function change_pagination_link( $link, $atts ) {


		if ( $atts['view']->ID === $this->view_id ) { // set the ID of the view to modify

			if(strpos($link, '&tab=myIndexing&tab2=myCouncils'))
				str_replace('&tab=myIndexing&tab2=myCouncils', '', $link);
			if(strpos($link, '&tab=myIndexing&tab2=myLodges'))
				str_replace('&tab=myIndexing&tab2=myLodges', '', $link);


			if (!strpos($link, '&tab=myIndexing&tab2=myCamps'))
				$link  .= '&tab=myIndexing&tab2=myCamps'; // change this to the ID of the HTML container to go to

		}


		return $link;
	}
}





///////////// Camp Ajax Hooks ////////////////////////////////////////





// define the actions for the two hooks created, first for logged in users and the next for logged out users
add_action("wp_ajax_search_camps", "search_camps");
add_action("wp_ajax_nopriv_search_camps", "search_camps");

// define the function to be fired for logged in users
function search_camps() {

	// nonce check for an extra layer of security, the function will exit if it fails
	if ( !wp_verify_nonce( $_REQUEST['nonce'], "search_form")) {
		exit("Woof Woof Woof");
	}

	$selected_councils = $_REQUEST['selected_councils'];
	//$selected_councils = ['general_herkimer_400','Ilion_Council_','Herkimer_County_Council_','Herkimer_County_Council_400'];


	if ( strpos( $selected_councils, ',' ) ) {
		$selected_councils = explode( ',', $selected_councils );
	}



	$results = $result['state'] = '';
	$result['type'] = 'none';

	if ( is_array( $selected_councils ) && count( $selected_councils ) == 1 ) {

		if (strlen($selected_councils[0]) >  0) {
			$selected_councils_id = Entry::get_field_id_from_key($selected_councils);
			$results = CampView::show_static_view($selected_councils_id);
			$results .= CampView::show_static_view( $selected_councils );
		}

		$result['type']  = 'one';
	}
	else {
		foreach ( $selected_councils as $selected_council ) {
			if (strlen($selected_council)  > 1 ) {
				$selected_councils_id = Entry::get_field_id_from_key($selected_council);
				$results          .= CampView::show_static_view($selected_councils_id);
				$results          .= CampView::show_static_view( $selected_council );
			}
		}
		$result['type']  = 'many';
	}


	$results = strip_tags($results, '<option><div>');

	$results = explode(PHP_EOL, $results );

	$results = str_replace('<div class="frm_no_entries">No Entries Found</div>', '', $results );

	$results = array_unique($results);

	$results = implode($results);

	$result['state'] = $results;
//$result['state'] = $selected_councils;





	// Check if action was fired via Ajax call. If yes, JS code will be triggered, else the user is redirected to the post page
	if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
		$result = json_encode($result);
		echo $result;
	}
	else {
		header("Location: ".$_SERVER["HTTP_REFERER"]);
	}

	// don't forget to end your scripts with a die() function - very important
	die();
}






