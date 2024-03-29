<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}


class LodgeView extends View {

	public function __construct( int $view_id = 0 ) {
		parent::__construct( $view_id );
	}


	public function show_view( $states = array( 'search_param' => 'General Herkimer Council' ), $filter = 'limited' ) {
		return parent::show_view( $states, $filter );
	}


	public static function show_static_view( $council_slug = 'miami_valley_council_444', $filter = 'limited' ) {

		$search_param = array('fi.form_id' => ADD_A_LODGE_FORMID, 'meta_value like' => $council_slug  );
		$entry_ids    = FrmEntryMeta::getEntryIds( $search_param );

		if ( ! empty( $entry_ids ) ) {
			$entries = FrmEntry::getAll( array( 'it.id' => $entry_ids ), '', '', true );

			return LodgeView::show_mview( $entries );
		} else {
			return '';
		}


	}

	public static function show_mview( $entries, $selected = false ) {
		$output     = '';
		$lodge_name = '';
		$lodge_slug = '';




		foreach ( $entries as $key => $entry ) {

			if ( ! is_null( $key ) ) {
				$lodge_slug = $entry->metas[ AALODGE_LODGE_SLUG_FID ];
				$lodge_name = $entry->metas[ AALODGE_NAME_FID ];
				if ( array_key_exists( AALODGE_NUMBER_FID, $entry->metas ) ) {
					$lodge_name .= ' ' . $entry->metas[ AALODGE_NUMBER_FID ];
				}

				if ( $selected ) {
					$output .= '<option value="' . $lodge_slug . '" selected="selected">' . $lodge_name . '</option>';
				} else {
					$output .= '<option value="' . $lodge_slug . '" >' . $lodge_name . '</option>';
				}
			}

		}

		return $output;
	}


	public function setup_hooks() {
		parent::setup_hooks();

		add_action( "wp_ajax_search_lodges", array( &$this, "search_lodges" ) );
		add_action( "wp_ajax_nopriv_search_lodges", array( &$this, "search_lodges" ) );

	}

	public function change_pagination_link( $link, $atts ) {


		if ( $atts['view']->ID === $this->view_id ) { // set the ID of the view to modify

			if ( strpos( $link, '&tab=myIndexing&tab2=myCouncils' ) ) {
				str_replace( '&tab=myIndexing&tab2=myCouncils', '', $link );
			}
			if ( strpos( $link, '&tab=myIndexing&tab2=myCamps' ) ) {
				str_replace( '&tab=myIndexing&tab2=myCamps', '', $link );
			}


			if ( ! strpos( $link, '&tab=myIndexing&tab2=myLodges' ) ) {
				$link .= '&tab=myIndexing&tab2=myLodges';
			} // change this to the ID of the HTML container to go to

		}


		return $link;
	}










///////////// Lodge Ajax Hooks ////////////////////////////////////////


	// define the function to be fired for logged in users
	public function search_lodges() {

		// nonce check for an extra layer of security, the function will exit if it fails
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], "search_form" ) ) {
			exit( "Woof Woof Woof" );
		}

		$selected_councils = $_REQUEST['selected_councils'];
        $selected_councils = View::clean_request($selected_councils);


		$results        = $result['state'] = '';
		$result['type'] = 'none';


        if (count($selected_councils) == 0) {
            $result['type'] = 'none';
        } elseif ( count( $selected_councils ) == 1 ) {

			$selected_councils_id = Entry::get_field_id_from_key($selected_councils);
			$results = LodgeView::show_static_view($selected_councils_id);
			//$results = LodgeView::show_static_view( $selected_councils );
			$result['type'] = 'one';


		} else {
			foreach ( $selected_councils as $selected_council ) {
				$selected_councils_id = Entry::get_field_id_from_key($selected_council);
				$results .= LodgeView::show_static_view($selected_councils_id);
				//$results .= LodgeView::show_static_view( $selected_council );

			}
			$result['type'] = 'many';
		}


		$results = strip_tags( $results, '<option><div>' );

		$results = explode( PHP_EOL, $results );

		$results = str_replace( '<div class="frm_no_entries">No Entries Found</div>', '', $results );

		$results = array_unique( $results );

		$results = implode( $results );

		$result['state'] = $results;
		//$result['state'] = $_REQUEST['selected_councils'];
		//$result['state'] = $selected_councils;


		// Check if action was fired via Ajax call. If yes, JS code will be triggered, else the user is redirected to the post page
		if ( ! empty( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) == 'xmlhttprequest' ) {
			$result = json_encode( $result );
			echo $result;
		} else {
			header( "Location: " . $_SERVER["HTTP_REFERER"] );
		}

		// don't forget to end your scripts with a die() function - very important
		die();
	}


}

