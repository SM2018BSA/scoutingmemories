<?php
function ppc_delete_agent_permissions( $agent_ids, $agent_type ) {
	global $wpdb;
	
	$agent_id_csv = implode( "','", array_map( 'intval', (array) $agent_ids ) );
	
	$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->ppc_roles WHERE agent_type = %s AND agent_id IN ('$agent_id_csv')", $agent_type ) );
	
	if ( $exc_ids = $wpdb->get_col( $wpdb->prepare( "SELECT exception_id FROM $wpdb->ppc_exceptions WHERE agent_type = %s AND agent_id IN ('$agent_id_csv')", $agent_type ) ) ) {
		$wpdb->query( "DELETE FROM $wpdb->ppc_exception_items WHERE exception_id IN ('" . implode( "','", $exc_ids ) . "')" );
		$wpdb->query( "DELETE FROM $wpdb->ppc_exceptions WHERE exception_id IN ('" . implode( "','", $exc_ids ) . "')" );
	}
}

function pp_get_agent( $agent_id, $agent_type = 'pp_group' ) {
	if ( 'pp_group' == $agent_type ) {
		global $wpdb;
		if ( $result = $wpdb->get_row( $wpdb->prepare( "SELECT ID, group_name AS name, group_description, metagroup_type, metagroup_id FROM $wpdb->pp_groups WHERE ID = %d", $agent_id ) ) ) {
			$result->name = stripslashes($result->name);
			$result->group_description = stripslashes($result->group_description);
			$result->group_name = $result->name;	// TODO: review usage of these properties
		}
	} elseif ( 'user' == $agent_type ) {
		if ( $result = new WP_User( $agent_id ) ) {
			$result->name = $result->display_name;
		}
	} else 
		$result = null;
	
	return apply_filters( 'pp_get_group', $result, $agent_id, $agent_type );
}
