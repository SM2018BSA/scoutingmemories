<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

require_once( dirname(__FILE__).'/exceptions_pp.php' );

/**
 * Primary query filtering functions for posts / terms listing and access validation
 * 
 * @package PP
 * @author Kevin Behrens <kevin@agapetry.net>
 * @copyright Copyright (c) 2019, PublishPress
 * 
 */
class PP_QueryInterceptor
{
	var $skip_teaser; 	// for use by templates making a direct call to query_posts for non-teased results
	var $anon_results = array();
	var $inserting_post = false;
	
	function __construct( $args = array() ) {
		add_filter( 'posts_clauses_request', array(&$this, 'flt_posts_clauses'), 50, 2 );
		
		// use late-firing filter so teaser filtering is also applied to sticky posts (passthrough for logged content administrator)
		add_filter( 'the_posts', array(&$this, 'flt_the_posts'), 50, 2 );

		add_action( 'parse_query', array(&$this, 'act_parse_query_followup'), 99 );
		add_filter( 'pp_posts_clauses', array(&$this, 'flt_do_posts_clauses'), 50, 2 );
		add_filter( 'pp_posts_where', array(&$this, 'flt_posts_where'), 2, 2 );
		add_filter( 'pp_posts_request', array(&$this, 'flt_do_posts_request'), 2, 2 );
		
		if ( defined( 'PP_ALL_ANON_FULL_EXCEPTIONS' ) ) {
			global $current_user;
			if ( empty($current_user->ID) ) {
				add_filter( 'posts_results', array( &$this, 'log_anon_results' ) );
				add_filter( 'the_posts', array( &$this, 'reinstate_anon_results' ) );
			}
		}
		
		add_filter( 'wp_insert_post_empty_content', array( &$this, 'flt_log_insert_post' ), 10, 2 );
		
		//add_filter( 'posts_request', array( &$this, 'flt_debug_query'), 999 );
		do_action( 'pp_query_interceptor' );
	}
	
	function log_anon_results( $results ) {
		$this->anon_results = $results;
		return $results;
	}
	
	// enable PP to grant read permissions to Anonymous users for private posts, but only if constant PP_ALL_ANON_FULL_EXCEPTIONS is defined
	function reinstate_anon_results($posts) {
		global $wp_query;
		if ( $wp_query->is_single || $wp_query->is_page ) {
			$posts = $this->anon_results;
		}
		
		return $posts;
	}
	
	//function flt_debug_query( $query ) {
	//	d_echo( $query . '<br /><br />' );
	//	return $query;
	//}
	
	// avoid unexpected query behavior due to external calling of $wp_query->get_queried_object()
	function act_parse_query_followup( $query_obj = false ) {
		if ( $query_obj && isset($query_obj->queried_object_id) && ! $query_obj->queried_object_id ) {
			unset( $query_obj->queried_object );
			unset( $query_obj->queried_object_id );
		}
	}
	
	function _get_teaser_post_types( $post_types, $args = array() ) {
		if ( is_admin() || pp_is_content_administrator() || ! empty( $args['skip_teaser'] ) || defined('XMLRPC_REQUEST') || ( defined('REST_REQUEST') && REST_REQUEST ) )
			return array();

		return apply_filters( 'pp_teased_post_types', array(), $post_types, $args );
	}
	
	// for wp_add_trashed_suffix avoidance
	function flt_log_insert_post( $val ) {
		$this->inserting_post = true;
		return $val;
	}
	
	function flt_posts_clauses( $clauses, $_wp_query = false, $args = array() ) {
		global $pagenow;
		
		if ( pp_unfiltered() && ( ! is_admin() || ( $pagenow != 'nav-menus.php' ) ) ) // need to make private items selectable for nav menus
			return $clauses;

		$action = ( isset( $_REQUEST['action'] ) ) ? $_REQUEST['action'] : '';
		
		if ( defined( 'PP_MEDIA_LIB_UNFILTERED' ) && ( ( 'upload.php' == $pagenow ) || ( defined('DOING_AJAX') && DOING_AJAX && ( 'query-attachments' == $action ) ) ) )
			return $clauses;

		if ( is_admin() && ( ! defined( 'PPCE_VERSION' ) || defined( 'PP_ADMIN_READONLY_LISTABLE' ) ) && ! pp_get_option( 'admin_hide_uneditable_posts' ) )
			return $clauses;
		
		if ( ! empty( $_wp_query ) && ! empty( $_wp_query->query_vars ) ) {
			$args['query_vars'] = $_wp_query->query_vars;
			
			// don't filter wp_add_trashed_suffix_to_post_name_for_trashed_posts()
			//if ( ! empty( $args['query_vars']['post_status'] ) && ( 'trash' == $args['query_vars']['post_status'] ) && ! empty( $args['query_vars']['name'] ) && $this->inserting_post ) {  
			if ( ! empty( $args['query_vars']['post_status'] ) && ( 'trash' == $args['query_vars']['post_status'] ) && ! empty( $args['query_vars']['name'] ) && ! empty( $args['query_vars']['post__not_in'] ) ) {  
				return $clauses;
			}
		}
		
		if ( defined('DOING_AJAX') && DOING_AJAX ) { // todo: separate function to eliminate redundancy with PP_Find::find_post_type()
			if ( in_array( $action, (array) apply_filters( 'pp_unfiltered_ajax', array() ) ) )
				return $clauses;

			$nofilter_prefixes = (array) apply_filters( 'pp_unfiltered_ajax_prefix', array( 'acf/' ) );  // Advanced Custom Fields (conflict with action=acf/fields/relationship/query_posts)
			foreach( $nofilter_prefixes as $prefix ) {
				if ( 0 === strpos( $action, $prefix ) ) {
					return $clauses;
				}
			}

			$ajax_post_types = apply_filters( 'pp_ajax_post_types', array( 'attachment' => 'attachment', 'ai1ec_doing_ajax' => 'ai1ec_event', 'tribe_calendar' => 'tribe_events' ) );
			
			foreach( array_keys($ajax_post_types) as $arg ) {
				if ( ! empty( $_REQUEST[$arg] ) || ( $arg == $action ) ) {
					$_wp_query->post_type = $ajax_post_types[$arg];
					break;
				}
			}
			
			/*
			$read_actions = apply_filters( 'pp_ajax_read_actions', array( 'infinite_scroll', 'tribe_calendar', 'tribe_list', 'tribe_event_day', 'tribe_event_week', 'tribe_geosearch', 'tribe_photo' ) );
			if ( in_array( $action, $read_actions ) ) {
				$_wp_query->query_vars['required_operation'] = 'read';
			*/
			
			if ( empty( $_wp_query->query_vars['required_operation'] ) )
				$_wp_query->query_vars['required_operation'] = 'read';  // default to requiring read access for all ajax queries
			
			$edit_actions = apply_filters( 'pp_ajax_edit_actions', array() );
			if ( in_array( $action, $edit_actions ) ) {
				$_wp_query->query_vars['required_operation'] = 'edit';
			
			} elseif ( ! empty($_wp_query->post_type) && is_scalar($_wp_query->post_type) ) {
				//$ajax_required_operation = apply_filters( 'pp_ajax_required_operation', array( 'ai1ec_event' => 'read' ) );
				$ajax_required_operation = apply_filters( 'pp_ajax_required_operation', array() );
				
				foreach( array_keys($ajax_required_operation) as $arg ) {
					if ( $arg == $_wp_query->post_type ) {
						$_wp_query->query_vars['required_operation'] = $ajax_required_operation[$arg];
						break;
					}
				}
			} 
		}
		
		if ( $_clauses = apply_filters( 'pp_posts_clauses_intercept', false, $clauses, $_wp_query, $args ) )
			return $_clauses;
			
		//d_echo( "flt_posts_clauses input: " );
		//dump($clauses);
		
		$post_type = '';
		if ( is_object($_wp_query) ) {
			if ( ! empty($_wp_query->post_type) )
				$post_type = $_wp_query->post_type;
			elseif ( isset($_wp_query->query) && isset($_wp_query->query['post_type']) )
				$post_type = $_wp_query->query['post_type'];
		}
			
		$post_types = apply_filters( 'pp_main_posts_clauses_types', $post_type );
		$clauses['where'] = apply_filters( 'pp_main_posts_clauses_where', $clauses['where'] );
		
		if ( 'any' == $post_types )
			$post_types = '';

		$args['post_types'] = $post_types;
		
		if ( isset( $_wp_query->query_vars['required_operation'] ) )
			$args['required_operation'] = $_wp_query->query_vars['required_operation'];

		$clauses = $this->flt_do_posts_clauses( $clauses, $args );
		
		//d_echo( "filtered flt_posts_clauses: " );
		//dump($clauses);

		return $clauses;
	}
	
	function flt_do_posts_clauses( $clauses, $args = array() ) {
		global $wpdb;

		$args['where'] = $clauses['where'];
		$clauses['where'] = apply_filters( 'pp_posts_clauses_where', $this->flt_posts_where( $clauses['where'], $args ), $clauses, $args );

		return $clauses;
	}
	
	function flt_do_posts_request( $request, $args = array() ) {
		if ( pp_unfiltered() )
			return $request;
		
		require_once( dirname(__FILE__).'/query-interceptor-extra_pp.php' );
		return PP_QueryInterceptorExtra::flt_posts_request( $request, $args );
	}
	
	// Filter existing where clause
	function flt_posts_where( $where, $args = array() ) {
		$defaults = array( 	
			'post_types' => array(),
			'source_alias' => false,	 		
			'skip_teaser' => false,
			'retain_status' => false,
			'required_operation' => '',
			'alternate_required_ops' => false,
			'include_trash' => 0,
			'query_contexts' => array(),
			'force_types' => false,
		);
		$args = array_merge( $defaults, (array) $args );
		foreach( array_keys( $defaults ) as $var ) {
			$$var = $args[$var];
		}
		
		global $wpdb, $pp_current_user;

		//d_echo ("<br /><strong>flt_posts_where input:</strong> $where<br />");
		
		$src_table = ( $source_alias ) ? $source_alias : $wpdb->posts;
		$args['src_table'] = $src_table;
		
		$limit_post_types = ( $post_types ) ? (array) $post_types : false;
		
		// need to allow ambiguous object type for special cap requirements like comment filtering
		$post_types = ( $post_types ) ? (array) $post_types : pp_get_enabled_post_types();  // include all defined otypes in the query if none were specified
		
		if ( ! $required_operation ) {
			if ( ! empty( $_REQUEST['preview'] ) ) {
				$required_opertion = 'edit';
			} else {
				$required_operation = apply_filters( 'pp_get_posts_operation', '', $args );
			}
		}
		
		$args['required_operation'] = $required_operation;
		
		// Avoid superfluous clauses by limiting object types to those already specified in the query 
		if ( preg_match( "/post_type\s*=/", $where ) || preg_match( "/post_type\s*IN/", $where ) ) {  // post_type clause present? 
			foreach( $post_types as $key => $type ) {
				if ( ! preg_match( "/post_type\s*=\s*'$type'/", $where ) && ! preg_match( "/post_type\s*IN\s*\([^)]*'$type'[^)]*\)/", $where ) )
					unset( $post_types[$key] );
			}
		}

		if ( ! $force_types )
			$post_types = array_intersect( $post_types, pp_get_enabled_post_types() );

		if ( defined( 'PP_UNFILTERED_FRONT' ) && ( ( 'read' == $required_operation ) || ( ! $required_operation && pp_is_front() && ! is_preview() ) || apply_filters( 'pp_skip_filtering', false, $args ) ) ) {
			if ( defined( 'PP_UNFILTERED_FRONT_TYPES' ) ) {
				$unfiltered_types = str_replace( ' ', '', PP_UNFILTERED_FRONT_TYPES );
				$unfiltered_types = explode( ',', constant( $unfiltered_types ) );
				$post_types = array_diff( $post_types, $unfiltered_types );
			} else {
				return $where;
			}
		}
		
		if ( ! $post_types )
			return $where;
	
		// Since Press Permit can restrict or expand access regardless of post_status, query must be modified such that
		//  * the default owner inclusion clause "OR post_author = [user_id] AND post_status = 'private'" is removed
		//  * all statuses are listed apart from owner inclusion clause (and each of these status clauses is subsequently filtered to impose any necessary access limits)
		//  * a new filtered owner clause is constructed where appropriate
		//
		$where = preg_replace( str_replace('[user_id]', $pp_current_user->ID, "/OR\s*($src_table.|)post_author\s*=\s*[user_id]\s*AND\s*($src_table.|)post_status\s*=\s*'([a-z0-9_\-]*)'/"), str_replace('[user_id]', $pp_current_user->ID, "OR $src_table.post_status = '$3'"), $where );
		
		// If the passed request contains a single status criteria, maintain that status exclusively (otherwise include each available status)
		// (But not if user is anon and hidden content teaser is enabled.  In that case, we need to replace the default "status=publish" clause)
		$matches = array();
		if ( $num_matches = preg_match_all( "/{$src_table}.post_status\s*=\s*'([^']+)'/", $where, $matches ) ) {
			if ( pp_is_front() || ( defined('REST_REQUEST') && REST_REQUEST ) ) {
				if ( pp_is_front() || 'read' == $required_operation ) {
					$valid_stati = array_merge( pp_get_post_stati( array( 'public' => true, 'post_type' => $post_types ) ), pp_get_post_stati( array( 'private' => true, 'post_type' => $post_types ) ) );
					
					if ( is_single() || ! empty( $args['has_cap_check'] ) || defined( 'PP_FUTURE_POSTS_BLOGROLL' ) )
						$valid_stati['future'] = 'future';
				} else
					$valid_stati = pp_get_post_stati( array( 'internal' => false, 'post_type' => $post_types ), 'names' );
				
				if ( in_array( 'attachment', $post_types ) )
					$valid_stati []= 'inherit';
				
				$new_status_clause = "{$src_table}.post_status IN ('" . implode("','", $valid_stati) . "')";
			
				foreach( $matches[0] as $status_string ) {
					$where = str_replace( $status_string, $new_status_clause, $where );	// we will append our own status clauses instead
				}
			}
		}

		if ( 1 == $num_matches ) {
			// Eliminate a primary plugin incompatibility by skipping this preservation of existing single status requirements if we're on the front end and the requirement is 'publish'.  
			// (i.e. include private posts that this user has access to via PP roles or exceptions).  
			if ( ( ! pp_is_front() && ( ! defined('REST_REQUEST') || ! REST_REQUEST ) ) || ( 'publish' != $matches[1][0] ) || $retain_status || defined('PP_RETAIN_PUBLISH_FILTER') ) { 
				$limit_statuses = array();

				if ( 'inherit' != $matches[1][0] )
					$limit_statuses[ $matches[1][0] ] = true;
				
				if ( $limit_statuses = apply_filters( 'pp_posts_where_limit_statuses', $limit_statuses, $post_types ) )
					$args['limit_statuses'] = $limit_statuses;
			}
		}
		
		$args['post_types'] = $post_types;
		$args['limit_post_types'] = $limit_post_types;
		
		if ( is_array($alternate_required_ops) ) {
			if ( ! $required_operation )
				$required_operation = ( ( pp_is_front() || ( defined('REST_REQUEST') && REST_REQUEST ) ) && ! is_preview() ) ? 'read' : 'edit';

			$alternate_required_ops = array_unique( array_merge( $alternate_required_ops, (array) $required_operation ) );

			$pp_where = array();
			foreach( $alternate_required_ops as $op ) {
				$args['required_operation'] = $op;
				$pp_where[$op] = '1=1' . $this->get_posts_where( $args );
			}

			$where_prepend = 'AND ( ' . pp_implode( 'OR', $pp_where ) . ' ) ';
		} else {
			$where_prepend = $this->get_posts_where( $args );
		}

		// Prepend so we don't disturb any orderby/groupby/limit clauses which are along for the ride
		if ( $where_prepend ) {
			$where = apply_filters( 'pp_objects_where', " $where_prepend $where", 'post' );
		}

		//d_echo ("<br /><br /><strong>flt_posts_where output:</strong> $where<br /><br />");

		return $where;
	} // end function flt_posts_where
	
	// build a new posts request
	//
	function construct_posts_request( $clauses = array(), $args = array() ) {
		global $wpdb;

		$args = apply_filters( 'pp_construct_posts_request_args', $args );
		
		$defaults = array_fill_keys( array( 'distinct', 'join', 'where', 'groupby', 'orderby', 'limits' ), '' );
		$defaults['fields'] = '*';
		$clauses = array_merge( $defaults, $clauses );
		$clauses['where'] .= $this->get_posts_where( $args );
		
		$clauses = apply_filters( 'pp_construct_posts_request_clauses', $clauses, $args );
		
		foreach( array_keys( $defaults ) as $var ) {
			$$var = $clauses[$var];
		}

		$found_rows = ( $limits ) ? 'SQL_CALC_FOUND_ROWS' : '';

		return "SELECT $found_rows $distinct $fields FROM $wpdb->posts $join WHERE 1=1 $where $groupby $orderby $limits";
	}
	
	// determines status usage, calls generate_where_clause() for each applicable post_type and appends resulting clauses
	//
	function get_posts_where( $args ) {
		$defaults = array(
			'post_types' => array(),
			'source_alias' => false,
			'src_table' => '',
			'apply_term_restrictions' => true,
			'include_trash' => 0,
			'required_operation' => '',
			'limit_statuses' => false,
			'skip_teaser' => false,
			'query_contexts' => array(),
			'force_types' => false,
			'limit_post_types' => false
		);
		$args = array_merge( $defaults, (array) $args );
		foreach( array_keys( $defaults ) as $var ) {
			$$var = $args[$var];
		}

		//d_echo ("<br /><strong>get_posts_where:</strong> <br />");	
		
		global $wpdb;
		
		if ( ! $src_table ) {
			$src_table = ( $source_alias ) ? $source_alias : $wpdb->posts;
			$args['src_table'] = $src_table;
		}

		if ( ! $force_types )
			$post_types = array_intersect( (array) $post_types, pp_get_enabled_post_types() );
		
		$tease_otypes = array_intersect( $post_types, $this->_get_teaser_post_types($post_types, $args) );
		
		if ( ! $required_operation ) {
			$required_operation = ( ( pp_is_front() || ( defined('REST_REQUEST') && REST_REQUEST ) ) && ! is_preview() ) ? 'read' : 'edit';
			$args['required_operation'] = $required_operation;
		}
		
		if ( $query_contexts )
			$query_contexts = (array) $query_contexts;
			
		$meta_cap = "{$required_operation}_post";

		if ( 'read' == $required_operation ) {
			$use_statuses = array_merge( pp_get_post_stati( array( 'public' => true, 'post_type' => $post_types ), 'object' ), pp_get_post_stati( array( 'private' => true, 'post_type' => $post_types ), 'object' ) );
			
			if ( is_single() || ! empty( $args['has_cap_check'] ) || defined( 'PP_FUTURE_POSTS_BLOGROLL' ) )
				$use_statuses['future'] = 'future';
			
			foreach( $use_statuses as $key => $obj ) {
				if ( ! empty($obj->exclude_from_search) )	// example usage is bbPress hidden status
					unset($use_statuses[$key]);
			}
			
			if ( $limit_statuses ) {
				// don't block author from reading their own draft post on REST permission check
				$use_statuses = array_merge( $use_statuses, $limit_statuses );
			}
		} else {
			$use_statuses = pp_get_post_stati( array( 'internal' => false, 'post_type' => $post_types ), 'object' );
		}
		
		if ( in_array( 'attachment', $post_types ) )
			$use_statuses['inherit'] = (object) array();

		if ( is_array( $limit_statuses ) )
			$use_statuses = array_intersect_key( $use_statuses, $limit_statuses );
		
		if ( empty($skip_teaser) && ! array_diff($post_types, $tease_otypes) ) {
			// All object types potentially returned by this query will have a teaser filter applied to results, so we don't need to use further query filtering
			$status_clause = "AND $src_table.post_status IN ('" . implode( "','", array_keys($use_statuses) ) . "')";
			return $status_clause;
		}

		if ( ! is_bool($include_trash) ) {
			if ( ! empty( $_REQUEST['post_status'] ) && ( 'trash' == $_REQUEST['post_status'] ) )
				$include_trash = true;
		}
		
		$where_arr = array();
		
		global $pp_current_user;
		global $pp_meta_caps;
		
		$flag_meta_caps = ! empty($pp_meta_caps);
		
		if ( 'read' == $required_operation && ! defined( 'PP_DISABLE_UNFILTERED_TYPES_CLAUSE' ) && ! $pp_current_user->ID ) {
			$all_post_types = get_post_types( array( 'public' => true ), 'names' );
			
			$unfiltered_post_types = array_diff( $all_post_types, $post_types );
			
			if ( $limit_post_types ) {
				$unfiltered_post_types = array_intersect( $unfiltered_post_types, (array) $limit_post_types );
			}
			
			// This proved necessary for WPML compat.  It ensures a default of normal visibility for public and user-authored posts when PP Filtering is not enabled for the post type
			foreach( $unfiltered_post_types as $_post_type ) {
				$where_arr[$_post_type] = "$src_table.post_type = '$_post_type' AND $src_table.post_status = 'publish'";
			}
		}
		
		foreach ( $post_types as $post_type ) {
			if ( in_array($post_type, $tease_otypes) && empty($skip_teaser) )
				$where_arr[$post_type] = "$src_table.post_type = '$post_type' AND 1=1";
			else {
				$have_site_caps = array();
				
				$type_obj = get_post_type_object( $post_type );
				
				foreach( array_keys($use_statuses) as $status ) {
					if ( 'private' == $status ) {
						$cap_property = "{$required_operation}_private_posts";
						if ( empty( $type_obj->cap->$cap_property ) ) {
							continue;
						}
					}
					
					if ( 'future' == $status ) {
						$cap_property = "edit_others_posts";
						if ( empty( $type_obj->cap->$cap_property ) ) {
							continue;
						}
					}

					if ( $flag_meta_caps ) { $pp_meta_caps->do_status_cap_map = true; }
					$reqd_caps = pp_map_meta_cap( $meta_cap, $pp_current_user->ID, 0, compact( 'post_type', 'status', 'query_contexts' ) );
					if ( $flag_meta_caps ) { $pp_meta_caps->do_status_cap_map = false; }
					
					if ( $reqd_caps ) {  // note: this function is called only for listing query filters (not for user_has_cap filter)
						if ( $missing_caps = apply_filters( 'pp_query_missing_caps', array_diff( $reqd_caps, array_keys( $pp_current_user->allcaps ) ), $reqd_caps, $post_type, $meta_cap ) ) {
							$owner_reqd_caps = $this->get_base_caps( $reqd_caps, $post_type );  // remove "others" and "private" cap requirements for post author

							if ( ( $owner_reqd_caps != $reqd_caps ) && $pp_current_user->ID ) {
								if ( ! array_diff( $owner_reqd_caps, array_keys( $pp_current_user->allcaps ) ) )
									$have_site_caps['owner'] []= $status;
							}
						} else {
							$have_site_caps['user'] []= $status;
						}
					}
				}
				
				$have_site_caps = apply_filters( 'pp_have_site_caps', $have_site_caps, $post_type, $args );
				
				if ( $include_trash ) {
					if ( $type_obj = get_post_type_object($post_type) ) {
						if ( ( ( 'edit_post' == $meta_cap ) && ! empty( $pp_current_user->allcaps[$type_obj->cap->edit_posts] ) ) || ( ( 'delete_post' == $meta_cap ) && ! empty( $pp_current_user->allcaps[$type_obj->cap->delete_posts] ) ) ) {
							if ( ! isset($type_obj->cap->delete_others_posts) || ! empty( $pp_current_user->allcaps[$type_obj->cap->delete_others_posts] ) )
								$have_site_caps['user'] []= 'trash';
							else
								$have_site_caps['owner'] []= 'trash';
						}
					}
				}
				
				$where_arr[$post_type] = array();
				if ( ! empty( $have_site_caps['user'] ) ) {
					$where_arr[$post_type] ['user']= "$src_table.post_status IN ('" . implode( "','", array_unique($have_site_caps['user']) ) . "')";
				}
				
				//dump($have_site_caps);

				if ( ! empty( $have_site_caps['owner'] ) ) {
					$parent_clause = ''; // PPCE may be set to "ID IN (...) OR " to enable post revisors to edit their own pending revisions
					$args['post_type'] = $post_type;
					$_vars = apply_filters( 'pp_generate_where_clause_force_vars', null, 'post', $args );
					if ( is_array( $_vars ) ) {
						if ( isset( $_vars['parent_clause'] ) ) {
							$parent_clause = $_vars['parent_clause'];
						}
					}

					if ( ! empty($args['skip_stati_usage_clause']) && ! $limit_statuses && ! array_diff_key( $use_statuses, array_flip($have_site_caps['owner']) ) ) {
						$where_arr[$post_type] ['owner']= "$parent_clause ( $src_table.post_author = $pp_current_user->ID )";
					} else
						$where_arr[$post_type] ['owner']= "$parent_clause ( $src_table.post_author = $pp_current_user->ID ) AND $src_table.post_status IN ('" . implode( "','", array_unique($have_site_caps['owner']) ) . "')";
				}
				
				if ( is_array($where_arr[$post_type]) ) {
					if ( $where_arr[$post_type] ) {
						$where_arr[$post_type] = pp_implode( 'OR', $where_arr[$post_type] );
						$where_arr[$post_type] = "1=1 AND ( " . $where_arr[$post_type] . " )"; 
					} elseif ( ! in_array( 'comments', $args['query_contexts'] ) || ! defined( 'REST_REQUEST' ) || ! REST_REQUEST  ) { // if PPCE is not activated, don't filter comments
						$where_arr[$post_type] = '1=2';
					}
				}
				
				if ( $modified = apply_filters( 'pp_adjust_posts_where_clause', false, $where_arr[$post_type], $post_type, $args ) )
					$where_arr[$post_type] = $modified;
					
				if ( 'attachment' == $post_type ) {
					if ( ( 'read' == $required_operation ) || apply_filters( 'pp_force_attachment_parent_clause', false, $args ) ) {
					//if ( ( 'read' == $required_operation ) || ( defined('DOING_AJAX') && DOING_AJAX && ( false != strpos( $_SERVER['REQUEST_URI'], 'async-upload.php' ) ) ) || apply_filters( 'pp_force_attachment_parent_clause', false, $args ) ) {
						$where_arr[$post_type] = "( " . $this->append_attachment_clause( "$src_table.post_type = 'attachment'", array(), $args ) . " )";
					}
				}
				
				if ( 'delete' == $required_operation ) {
					$const = "PP_EDIT_EXCEPTIONS_ALLOW_" . strtoupper( $post_type ) . "_DELETION";
					if ( defined( 'PP_EDIT_EXCEPTIONS_ALLOW_DELETION' ) || defined( $const ) )
						$required_operation = 'edit';
				}
				
				$where_arr[$post_type] = PP_Exceptions::add_exception_clauses( $where_arr[$post_type], $required_operation, $post_type, $args );
			}

		} // end foreach post_type
		
		if ( ! $pp_where = pp_implode( 'OR', $where_arr ) )
			$pp_where = '1=1';

		// term restrictions which apply to any post type
		if ( $apply_term_restrictions ) {
			if ( $term_exc_where = PP_Exceptions::add_term_restrictions_clause( $required_operation, '', $src_table, array( 'merge_universals' => true, 'merge_additions' => true, 'exempt_post_types' => $tease_otypes ) ) ) {
				$pp_where = "( $pp_where ) $term_exc_where";
			}
		}
		
		if ( $pp_where )
			$pp_where = " AND ( $pp_where )";
	
		return $pp_where;
	}
	
	function append_attachment_clause( $where, $clauses, $args ) {
		static $busy = false;
		if ( $busy ) // recursion sanity check
			return '1=2';

		$busy = true;
		require_once( dirname(__FILE__).'/query-attachments_pp.php' );
		$return = PP_QueryAttachments::append_attachment_clause( $where, $clauses, $args );
		$busy = false;

		return $return;
	}
	
	// currently only used to conditionally launch teaser filtering
	function flt_the_posts( $results, $query_obj ) {
		if ( empty($this->skip_teaser) ) {
			// won't do anything unless teaser is enabled for object type(s)
			$results = apply_filters( 'pp_posts_teaser', $results, '', array( 'request' => $query_obj->request, 'force_teaser' => true) );
		}

		return $results;	
	}
	
	// converts _others caps to equivalent base cap, for specified object type
	function get_base_caps( $reqd_caps, $post_type, $return_op = false ) {
		$reqd_caps = (array) $reqd_caps;
	
		if ( $type_obj = get_post_type_object( $post_type ) ) {
			$replace_caps = array();
			
			if ( isset( $type_obj->cap->read_private_posts ) )
				$replace_caps[ $type_obj->cap->read_private_posts ] = 'read';
			
			$cap_match = array( 'edit_others_posts' => 'edit_posts', 'delete_others_posts' => 'delete_posts', 'edit_private_posts' => 'edit_published_posts', 'delete_private_posts' => 'delete_published_posts' );
			foreach( $cap_match as $status_prop => $replacement_prop ) {
				if ( isset( $type_obj->cap->$status_prop ) && isset( $type_obj->cap->$replacement_prop ) )
					$replace_caps[ $type_obj->cap->$status_prop ] = $type_obj->cap->$replacement_prop;
			}
			
			$replace_caps = apply_filters( 'pp_base_cap_replacements', $replace_caps, $reqd_caps, $post_type );
			
			$replace_caps['edit_others_drafts'] = 'read';
			
			foreach( $replace_caps as $cap_name => $base_cap ) {
				$key = array_search( $cap_name, $reqd_caps );
				if ( false !== $key ) {
					$reqd_caps[$key] = $base_cap;
					$reqd_caps = array_unique( $reqd_caps );
				}
			}
		}
		
		return $reqd_caps;
	}
} // end class PP_QueryInterceptor

function agp_get_suffix_pos( $request ) {
	$request_u = strtoupper($request);

	$pos_suffix = strlen($request) + 1;
	foreach ( array(' ORDER BY ', ' GROUP BY ', ' LIMIT ') as $suffix_term )
		if ( $pos = strrpos($request_u, $suffix_term) )
			if ( $pos < $pos_suffix )
				$pos_suffix = $pos;
				
	return $pos_suffix;
}

// wrapper for WP map_meta_cap, for use in determining caps for a specific post_status and user class (owner/non-owner), without a specific post id
function pp_map_meta_cap( $cap_name, $user_id = 0, $post_id = 0, $args = array() ) {
	if ( $post_id )
		return map_meta_cap( $cap_name, $user_id, $post_id );

	$defaults = array( 'is_author' => false, 'post_type' => '', 'status' => '', 'query_contexts' => array() );
	$args = array_merge( $defaults, $args );
	foreach( array_keys( $defaults ) as $var ) {
		$$var = $args[$var];
	}
	
	global $current_user;
	
	if ( ! $post_type )  // sanity check
		return (array) $cap_name;

	if ( ! $user_id )
		$user_id = $current_user->ID;

	// force desired status caps and others caps by passing a fake post into map_meta_cap
	$post_author = ( $is_author ) ? $user_id : -1;

	if ( ! $status )
		$status = ( in_array( $cap_name, array( 'read_post', 'read_page' ) ) ) ? 'publish' : 'draft';  // default to draft editing caps, published reading caps
	elseif ( 'auto-draft' == $status )
		$status = 'draft';
		
	$_post = (object) array( 'ID' => -1, 'post_type' => $post_type, 'post_status' => $status, 'filter' => 'raw', 'post_author' => $post_author, 'query_contexts' => $query_contexts );
	
	wp_cache_add( -1, $_post, 'posts' );  // prevent querying for fake post
	$return = array_diff( map_meta_cap( $cap_name, $user_id, $_post ), array(null) );  // post types which leave some basic cap properties undefined result in nulls
	wp_cache_delete( -1, 'posts' );

	return $return;
}
