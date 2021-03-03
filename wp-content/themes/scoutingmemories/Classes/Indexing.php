<?php


class Indexing {

	private $unused_states;
	private $unused_councils;
	private $unused_lodges;
	private $unused_camps;

	private $category_slug;

	private $state_queries;
	private $council_queries;
	private $lodge_queries;
	private $camp_queries;

	private $start_date;
	private $start_date_query;
	private $end_date;
	private $end_date_query;

	public $query;
	public $paged;


	public function __construct( $category_slug, $paged = '1' ) {

		$this->paged = '1';

		$request['states']   = get_request_parameter( 'state' );
		$request['councils'] = get_request_parameter( 'council' );
		$request['lodges']   = get_request_parameter( 'lodge' );
		$request['camps']    = get_request_parameter( 'camp' );
		$request['paged']    = get_request_parameter( 'paged' );

		$this->start_date    = get_request_parameter( 'start_date' );
		$this->end_date      = get_request_parameter( 'end_date' );

		if ( $paged > 1 ) {
			$this->paged = $paged;
		} elseif ($request['paged'] !== null )  {
			$this->paged = $request['paged'];
		}


		if ( $request['states'] !== null ) {
			$this->unused_states = explode( ',', $request['states'] );
		}
		if ( $request['councils'] !== null ) {
			$this->unused_councils = explode( ',', $request['councils'] );
		}
		if ( $request['lodges'] !== null ) {
			$this->unused_lodges = explode( ',', $request['lodges'] );
		}
		if ( $request['camps'] !== null ) {
			$this->unused_camps = explode( ',', $request['camps'] );
		}

		$this->category_slug = $category_slug;


		$this->camp_queries    = $this->get_queries( $this->unused_camps,    'camp' );

		$this->lodge_queries   = $this->get_queries( $this->unused_lodges,   'lodge' );

		$this->council_queries = $this->get_queries( $this->unused_councils, 'council' );

		$this->state_queries   = $this->get_queries( $this->unused_states,   'state' );


		if ( $this->start_date && $this->end_date ) {

			$this->start_date_query = array(
				'key'     => 'start_date',
				'value'   => array( $this->start_date, $this->end_date ),
				'compare' => 'BETWEEN',
				'type'    => 'NUMERIC'
			);

			$this->end_date_query = array(
				'key'     => 'end_date',
				'value'   => array( $this->start_date, $this->end_date ),
				'compare' => 'BETWEEN',
				'type'    => 'NUMERIC'
			);

		}


		$this->create_final_query();



	}


	private function remove_used( $delete_value, &$array ) {
		if ( ( $key = array_search( $delete_value, $array ) ) !== false ) {
			unset( $array[ $key ] );
		}

		return $array;
	}


	private function get_queries( &$queries, $type = null ) {
		// we dont have any camps
		if ( $queries == null ) {
			return null;
		}



		if ( count( $queries ) == 1 ) {
			$queries = implode( $queries );
			if ( $type != null ) {
				$this->remove_unused( $type, $queries );
			}
			$result = $this->create_query( $type, $queries);
		} else {
			$result = array();
			foreach ( $queries as $query ) {
				if ( $type != null ) {
					$this->remove_unused( $type, $query );
				}
				if ( $query != '_' ) {
					$result[] = $this->create_query( $type, $query );
				}
			}

			$result = $this->create_combined( $result, 'OR' );
		}


		return $result;
	}


	private function remove_unused( $type, $slug ) {


		switch ( $type ) {

			case 'camp':
				$camp = new CampEntry( Entry::get_field_id_from_key( $slug ) );
				//TODO gather the linked and merged councils and remove them
				$this->remove_unused_slugs( $camp->council_slugs, $this->unused_councils );
				$this->remove_unused_slugs( $camp->camp_state_slugs, $this->unused_states );
				break;

			case 'lodge':
				$lodge = new LodgeEntry( Entry::get_field_id_from_key( $slug ) );
				//TODO gather the linked and merged councils and remove them
				$this->remove_unused_slugs($lodge->council_slugs, $this->unused_councils);
				$this->remove_unused_slugs($lodge->lodge_state_slugs, $this->unused_states);
				break;

			case 'council':
				$council = new CouncilEntry( Entry::get_field_id_from_key( $slug ), array(), FALSE );
				//TODO gather the linked and merged councils and remove them
				$this->remove_unused_slugs($council->state_slug, $this->unused_states);
				break;

		}


	}


	private function create_final_query() {

		$this->query['category_name'] = $this->category_slug;
		//$this->query['meta_query'] =
		$this->query['template'] = 'category';
		$this->query['posts_per_page'] = 10;
		$this->query['paged'] = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
		//$this->query['paged'] = '2';


		$this->query['tax_query'] = [
			[ 'relation' => 'OR',
				( isset( $this->state_queries ) ? $this->state_queries     : null ),
				( isset( $this->council_queries ) ? $this->council_queries : null ),
				( isset( $this->lodge_queries ) ? $this->lodge_queries     : null ),
				( isset( $this->camp_queries ) ? $this->camp_queries       : null )
			]

		];


		if ( $this->start_date && $this->end_date ) {

			$this->query['meta_query'] = [
				[
					'relation' => 'OR',
					[ $this->start_date_query ],
					[ $this->end_date_query ]
				]
			];


		}




	}


	private function remove_unused_slugs( $which_slugs, &$indexing ) {

		if (is_array($which_slugs)) {
			foreach ( $which_slugs as $slug ) {
				$indexing = $this->remove_used( $slug, $indexing );
			}
		} else {
			$indexing = $this->remove_used( $which_slugs, $indexing );
		}

	}





//	private function create_query( $meta_key, $meta_value, $comparison ) {
//		return [ 'key' => $meta_key, 'value' => $meta_value, 'compare' => $comparison ];
//	}


	private function create_query( $taxonomy, $terms ) {
		return [ 'taxonomy' => $taxonomy, 'field' => 'name', 'terms' => $terms ];
	}




	private function create_combined( $arrays, $comparison ) {

		$return_array = [ 'relation' => $comparison ];

		foreach ( $arrays as $array ) {
			$return_array[] = $array;
		}

		return $return_array;
	}


}