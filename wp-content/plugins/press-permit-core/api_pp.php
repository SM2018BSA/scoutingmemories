<?php
global $pp_extensions, $pp_group_types;
$pp_extensions = array();
$pp_group_types = array();

function pp_load_admin_api() {
	require_once( dirname(__FILE__).'/admin/admin-api_pp.php' );
}

function pp_register_extension( $slug, $label, $basename, $version, $min_pp_version = '0', $min_wp_version = '0' ) {
	global $pp_extensions, $pp_min_ext_version;
	
	$slug = pp_sanitize_key($slug);
	
	if ( ! isset($pp_extensions) || ! is_array($pp_extensions) )
		$pp_extensions = array();
		
	// avoid lockout in case of editing plugin via wp-admin
	if ( constant('PP_DEBUG') && is_admin() && ppc_editing_plugin() ) {
		return false;
	}

	$register = true;
	$error = false;
	
	if ( ! pp_wp_ver( $min_wp_version ) ) {
		require_once( dirname(__FILE__) . '/lib/error_pp.php' );
		$pp_error = new PP_Error();
		$error = $pp_error->old_wp( $label, $min_wp_version );
		$register = false;
		
	} elseif ( version_compare( PPC_VERSION, $min_pp_version, '<' ) ) {
		require_once( dirname(__FILE__) . '/lib/error_pp.php' );
		$pp_error = new PP_Error();
		$error = $pp_error->old_pp( $label, $min_pp_version );
		$register = false;
		
	} elseif( ! empty($pp_min_ext_version[$slug]) && version_compare( $version, $pp_min_ext_version[$slug], '<' ) ) {
		if ( is_admin() ) {
			require_once( dirname(__FILE__) . '/lib/error_pp.php' );
			$pp_error = new PP_Error();
			$error = $pp_error->old_extension( $label, $pp_min_ext_version[$slug] );
			// but still register extension so it can be updated!
			
		} else {
			$error = true;
			$register = false;
		}
	}

	if ( $register ) {
		$version = pp_sanitize_word( $version );
		$pp_extensions[$slug] = (object) compact( 'slug', 'version', 'label', 'basename' );
	}

	return ! $error;
}

// =========================== Capabilities API ===========================

/**
 * Retrieve supplemental roles for a user or group

 * @param string agent_type
 * @param int agent_id
 * @param array args :
 *   - post_types (default true)
 *   - taxonomies (default true)
 *   - force_refresh (default false)
 * @return array : $roles[role_name] = role_assignment_id
 */
function ppc_get_roles( $agent_type, $agent_id, $args = array() ) {
	require_once( dirname(__FILE__).'/groups-retrieval_pp.php' );
	return PP_GroupRetrieval::get_roles( $agent_type, $agent_id, $args );
}

/**
 * Assign supplemental roles for a user or group

 * @param array roles : roles[role_name][agent_id] = true
 * @param string agent_type
 */
function ppc_assign_roles( $group_roles, $agent_type = 'pp_group', $args = array() ) {
	require_once( dirname(__FILE__).'/admin/role_assigner_pp.php' );
	return PP_RoleAssigner::assign_roles( $group_roles, $agent_type, $args );
}

/**
 * Retrieve exceptions for a user or group

 * @param array args :
 *  - agent_type         ('user'|'pp_group'|'pp_net_group'|'bp_group')
 *  - agent_id           (group or user ID)
 *  - operations         ('read'|'edit'|'associate'|'assign'...)
 *  - for_item_source    ('post' or 'term' - data source to which the roles may apply)
 *  - post_types         (post_types to which the roles may apply)
 *  - taxonomies         (taxonomies to which the roles may apply)
 *  - for_item_status    (status to which the roles may apply i.e. 'post_status:private'; default '' means all stati)
 *  - via_item_source    ('post' or 'term' - data source which the role is tied to)
 *  - item_id            (post ID or term_taxonomy_id)
 *  - assign_for         (default 'item'|'children'|'' means both)
 *  - inherited_from     (base exception assignment ID to retrieve propagated assignments for; default '' means N/A)
 */
function ppc_get_exceptions( $args = array() ) {
	require_once( dirname(__FILE__).'/groups-retrieval_pp.php' );
	return PP_GroupRetrieval::get_exceptions( $args );
}

/**
 * Assign exceptions for a user or group

 * @param array agents : agents['item'|'children'][agent_id] = true|false
 * @param string agent_type
 * @param array args :
 *  - operation          ('read'|'edit'|'associate'|'assign'...)
 *  - mod_type           ('additional'|'exclude'|'include')
 *  - for_item_source    ('post' or 'term' - data source to which the role applies)
 *  - for_item_type      (post_type or taxonomy to which the role applies)
 *  - for_item_status    (status which the role applies to; default '' means all stati)
 *  - via_item_source    ('post' or 'term' - data source which the role is tied to)
 *  - item_id            (post ID or term_taxonomy_id)
 *  - via_item_type      (post_type or taxonomy of item which the role is tied to; default '' means unspecified when via_item_source is 'post')
 */
function ppc_assign_exceptions( $agents, $agent_type = 'pp_group', $args = array() ) {
	require_once( dirname(__FILE__).'/admin/role_assigner_pp.php' );
	return PP_RoleAssigner::assign_exceptions( $agents, $agent_type, $args );
}


// $args['labels']['name'] = translationed caption
// $args['labels']['name'] = translated caption
// $args['default_caps'] = array( cap_name => true, another_cap_name => true ) defines caps for pattern roles which do not have a corresponding WP role 
//
function pp_register_pattern_role( $role_name, $args = array() ) {
	global $pp_role_defs;
	
	$role_obj = (object) $args;
	$role_obj->name = $role_name;
	
	$pp_role_defs->pattern_roles[$role_name] = $role_obj;
}



// =========================== Groups API ===========================
function pp_register_group_type( $agent_type, $args = array() ) {
	$defaults = array( 'labels' => array(), 'schema' => array() );
	$args = (object) array_merge( $defaults, (array) $args );

	$args->labels = (object) $args->labels;
	
	if ( empty( $args->labels->name ) )
		$args->labels->name = $agent_type;

	if ( empty( $args->labels->singular_name ) )
		$args->labels->singular_name = $agent_type;
	
	global $pp_group_types;
	$pp_group_types[$agent_type] = (object) $args;
}

function pp_get_group_type_object( $agent_type ) {
	global $pp_group_types;
	
	_pp_group_init_labels();
	
	return ( isset( $pp_group_types[$agent_type] ) ) ? $pp_group_types[$agent_type] : false;
}

function pp_get_group_types( $args = array(), $return = 'name' ) {  // todo: handle $args
	global $pp_group_types;
	
	if ( ! isset( $pp_group_types ) )
		return array();
	
	if ( 'object' == $return ) _pp_group_init_labels();
	
	if ( ! empty( $args['editable'] ) ) {
		$editable_group_types = apply_filters( 'pp_editable_group_types', array( 'pp_group' ) );
		return ( 'object' == $return ) ? pp_array_subset( $pp_group_types, $editable_group_types ) : $editable_group_types;
	} else
		return ( 'object' == $return ) ? $pp_group_types : array_keys( $pp_group_types );
}

function _pp_group_init_labels( $args = array() ) {
	global $pp_group_types;
	
	if ( isset( $pp_group_types['pp_group'] ) && ( 'group' == $pp_group_types['pp_group']->labels->singular_name ) ) {
		$pp_group_types['pp_group']->labels->singular_name = __( 'Group', 'pp' );
		$pp_group_types['pp_group']->labels->name = __( 'Groups', 'pp' );
	}
}

function pp_group_type_exists( $agent_type ) {
	global $pp_group_types;
	return isset( $pp_group_types[$agent_type] );
}

function pp_group_type_editable( $agent_type ) {
	return in_array( $agent_type, pp_get_group_types( array( 'editable' => true ) ) );
}

/**
 * Retrieve users who are members of a specified group

 * @param int group_id
 * @param string agent_type
 * @param string cols ('all' | 'id')
 * @param array args :
 *   - status ('active' | 'scheduled' | 'expired' | 'any')
 * @return array of objects or IDs
 */
function pp_get_group_members( $group_id, $agent_type = 'pp_group', $cols = 'all', $args = array() ) {
	if ( 'pp_group' == $agent_type ) {
		require_once( dirname(__FILE__).'/groups-retrieval_pp.php' );
		return PP_GroupRetrieval::get_pp_group_members( $group_id, $cols, $args );
	} else {
		$val = ( 'count' == $cols ) ? 0 : array();
		return apply_filters( 'pp_get_group_members', $val, $group_id, $agent_type, $cols, $args );
	}
}

/**
 * Add User(s) to a Permission Group

 * @param int group_id
 * @param array user_ids
 * @param array args :
 *   - agent_type (default 'pp_group')
 *   - status ('active' | 'scheduled' | 'expired' | 'any')
 *   - date_limited (default false)
 *   - start_date_gmt
 *   - end_date_gmt
 */
function pp_add_group_user( $group_id, $user_ids, $args = array() ){
	require_once( dirname(__FILE__).'/admin/groups-update_pp.php' );
	return PP_GroupsUpdate::add_group_user( $group_id, $user_ids, $args );
}

/**
 * Remove User(s) from a Permission Group

 * @param int group_id
 * @param array user_ids
 * @param array args :
 *   - group_type (default 'pp_group')
 */
function pp_remove_group_user($group_id, $user_ids, $args = array() ) {
	require_once( dirname(__FILE__).'/admin/groups-update_pp.php' );
	return PP_GroupsUpdate::remove_group_user($group_id, $user_ids, $args);
}

/**
 * Update Group Membership for User(s)

 * @param int group_id
 * @param array user_ids
 * @param array args :
 *   - agent_type (default 'pp_group')
 *   - status ('active' | 'scheduled' | 'expired' | 'any')
 *   - date_limited (default false)
 *   - start_date_gmt
 *   - end_date_gmt
 */
function pp_update_group_user( $group_id, $user_ids, $args = array() ) {
	require_once( dirname(__FILE__).'/admin/groups-update_pp.php' );
	return PP_GroupsUpdate::update_group_user( $group_id, $user_ids, $args );
}

/**
 * Retrieve groups for a specified user

 * @param int user_id
 * @param string agent_type
 * @param array args :
 *   - cols ('all' | 'id')
 *   - status ('active' | 'scheduled' | 'expired' | 'any')
 *   - metagroup_type (default null)
 *   - query_user_ids (array, default false)
 *   - force_refresh (default false)
 * @return array (object or storage date string, with group id as array key)
 */
function pp_get_groups_for_user( $user_id, $agent_type = 'pp_group', $args = array() ) {
	if( 'pp_group' == $agent_type ) {
		require_once( dirname(__FILE__).'/groups-retrieval_pp.php' );
		return PP_GroupRetrieval::get_pp_groups_for_user( $user_id, $args );
	}

	return apply_filters( 'pp_get_groups_for_user', array(), $user_id, $agent_type, $args );
}

function pp_get_groups( $agent_type = 'pp_group', $args = array() ) {
	if ( 'pp_group' == $agent_type ) {
		require_once( PPC_ABSPATH.'/groups-retrieval_pp.php' );
		return PP_GroupRetrieval::get_pp_groups( $args );
	} else
		return apply_filters( 'pp_get_groups', array(), $agent_type, $args );
}

/**
 * Retrieve a Permission Group object
 
 * @param int group_id
 * @param string agent_type (pp_group, bp_group, etc.)
 * @return object Permission Group
 *  - ID
 *  - group_name
 *  - group_description
 *  - metagroup_type
 *  - metagroup_id
 */
function pp_get_group( $group_id, $agent_type = 'pp_group' ) {
	pp_load_admin_api();
	return pp_get_agent( $group_id, $agent_type );
}

/**
 * Create a new Permission Group
 
 * @param array group_vars_arr :
 *   - group_name
 *   - group_description (optional)
 *   - metagroup_type (optional, for internal use)
 * @return int ID of new group
 */
function pp_create_group ($group_vars_arr){
	require_once( dirname(__FILE__).'/admin/groups-update_pp.php' );
	return PP_GroupsUpdate::create_group($group_vars_arr);
}

/**
 * Delete a Permission Group
 
 * @param array group_id
 * @param array agent_type (pp_group, bp_group, etc.)
 */
function pp_delete_group( $group_id, $agent_type ) {
	require_once( dirname(__FILE__).'/admin/groups-update_pp.php');
	return PP_GroupsUpdate::delete_group($group_id, $agent_type);
}

/**
 * Retrieve the Permission Group object for a WP Role or other metagroup, by providing its name

 * @param string metagroup_type
 * @param string metagroup_id
 * @param array args :
 *   - cols (return format - 'all' | 'id')
 * @return object Permission Group (unless cols = 'id')
 *  - ID
 *  - group_name
 *  - group_description
 *  - metagroup_type
 *  - metagroup_id
 */
function pp_get_metagroup( $metagroup_type, $metagroup_id, $args = array() ) {
	global $wpdb;
	
	$defaults = array( 'cols' => 'all' );
	$args = array_merge( $defaults, $args );

	$site_key = md5( get_option( 'site_url' ) . constant('DB_NAME') . $wpdb->prefix ); // guard against groups table being imported into a different database (with mismatching options table)
	
	if ( ! $buffered_groups = pp_get_option( "buffer_metagroup_id_{$site_key}" ) )
		$buffered_groups = array();
	
	$key = $metagroup_id . ':' . $wpdb->pp_groups;  // PP setting may change to/from netwide groups after buffering
	if ( ! isset( $buffered_groups[$key] ) ) {
		$query = "SELECT * FROM $wpdb->pp_groups WHERE metagroup_type = '$metagroup_type' AND metagroup_id = '$metagroup_id' LIMIT 1";

		if ( ! $group = $wpdb->get_row( $query ) ) {
			// Groups table not created early enough on some multisite installations when third party code triggers early set_current_user action. 
			// TODO: Identify indicators to call db_setup() pre-emptively.
			if ( ! empty( $wpdb->last_error ) && is_string( $wpdb->last_error ) && strpos( $wpdb->last_error, ' exist' ) ) {
				require_once( dirname(__FILE__).'/db-setup_pp.php');
				PP_DB_Setup::db_setup();
				
				$group = $wpdb->get_row( $query );
			}
		}
		
		if ( $group ) {
			$group->group_id = $group->ID;
			$group->status = 'active';
			$buffered_groups[$key] = $group;
			pp_update_option( "buffer_metagroup_id_{$site_key}", $buffered_groups );
		}
	}
	
	if ( 'id' == $args['cols'] ) {
		return ( isset($buffered_groups[$key]) ) ? $buffered_groups[$key]->ID : false;
	} else {
		return ( isset($buffered_groups[$key]) ) ? $buffered_groups[$key] : false;
	}
}

/**
 * Retrieve a Permission Group object by providing its name

 * @param int group_name
 * @param string agent_type (pp_group, bp_group, etc.)
 * @return object Permission Group
 *  - ID
 *  - group_name
 *  - group_description
 *  - metagroup_type
 *  - metagroup_id
 */
function pp_get_group_by_name($name, $agent_type = 'pp_group') {
	global $wpdb;
	$groups_table = apply_filters( 'pp_use_groups_table', $wpdb->pp_groups, $agent_type );
	
	$result = $wpdb->get_row( $wpdb->prepare( "SELECT ID, group_name AS name, group_description FROM $groups_table WHERE group_name = %s", $name ) );
	return $result;
}

