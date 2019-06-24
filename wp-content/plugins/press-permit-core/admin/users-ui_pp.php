<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

add_filter('manage_users_columns', array('PP_AdminUsers', 'flt_users_columns'));
add_action('manage_users_custom_column', array('PP_AdminUsers', 'flt_users_custom_column'), 99, 3); // filter late in case other plugin filters do not retain passed value
add_filter( 'manage_users_sortable_columns', array( 'PP_AdminUsers', 'flt_users_columns_sortable' ) );

add_filter('pre_user_query', array('PP_AdminUsers', 'flt_user_query_exceptions' ) );
add_filter('query', array('PP_AdminUsers', 'flt_user_query' ) );

add_action( 'restrict_manage_users', array('PP_AdminUsers', 'bulk_groups_ui' ) );

PP_AdminUsers::groups_bulk();

class PP_AdminUsers {
	public static function bulk_groups_ui() {
		static $done;
		$sfx = ( ! empty($done) ) ? '2' : '';
		$done = true;
		
		if ( ! pp_get_option('users_bulk_groups') )
			return;
		
		if ( ! $agent_type = apply_filters( 'pp_query_group_type', '' ) )
			$agent_type = 'pp_group';
		
		$groups = pp_get_groups( $agent_type, array( 'where' => " AND metagroup_type = ''" ) );
		
		if ( ! count($groups) || ! current_user_can( 'list_users' ) )
			return;
		
		if ( ! current_user_can( 'pp_manage_members' ) ) {
			if ( ! $editable_groups = _pp_retrieve_admin_groups() )
				return;
			
			$groups = pp_array_subset( $groups, $editable_groups );
		}
		?>

		<label class="screen-reader-text" for="pp-add-group"><?php esc_html_e( 'Permissions&hellip;', 'pp' ) ?></label>
		<select name="pp-bulk-group<?php echo $sfx;?>" id="pp-bulk-group<?php echo $sfx;?>" class="pp-bulk-groups" style="display:inline-block; float:none;">
			<option value=''><?php esc_html_e( 'Permissions&hellip;', 'pp' ) ?></option>
			<?php 
			foreach ( $groups as $group_id => $group ) : ?>
				<option value="<?php echo $group_id; ?>"><?php echo $group->name; ?></option>
			<?php endforeach; ?>
		</select>
		
		<?php submit_button( __( 'Add', 'pp' ), 'secondary', 'pp-add-group-members' . $sfx, false, array( 'title' => __('Add selected users to Permission Group', 'pp' ) ) );?>
		<?php submit_button( __( 'Remove', 'pp' ), 'secondary', 'pp-remove-group-members' . $sfx, false, array( 'title' => __('Remove selected users from Permission Group', 'pp' ) ) );
		
		wp_nonce_field( 'pp-bulk-groups', 'pp-bulk-groups-nonce' );
	}
	
	public static function groups_bulk() {		
		if ( ! empty( $_REQUEST['pp-bulk-group2'] ) )
			$sfx = '2';
		elseif( empty( $_REQUEST['pp-bulk-group'] ) )
			return;
		
		if ( empty( $_REQUEST['users'] ) || ( empty( $_REQUEST['pp-add-group-members' . $sfx] ) && empty( $_REQUEST['pp-remove-group-members' . $sfx] ) ) )
			return;
		
		// Bail if nonce check fails
		check_admin_referer( 'pp-bulk-groups', 'pp-bulk-groups-nonce' );
		
		if ( ! current_user_can( 'list_users' ) )
			return;
		
		$group_id = $_REQUEST['pp-bulk-group' . $sfx];
		
		if ( ! $has_manage_members_cap = current_user_can( 'pp_manage_members' ) ) {
			if ( ! in_array( $group_id, _pp_retrieve_admin_groups() ) )
				return;
			
			global $current_user;
			$_REQUEST['users'] = array_diff( $_REQUEST['users'], array( $current_user->ID ) );
		}

		if ( ! empty( $_REQUEST['pp-add-group-members' . $sfx] ) ) {
			pp_add_group_user( $group_id, $_REQUEST['users'] );
		} elseif ( ! empty( $_REQUEST['pp-remove-group-members' . $sfx] ) ) {
			pp_remove_group_user( $group_id, $_REQUEST['users'] );
		}
	}
	
	public static function flt_users_columns($defaults) {
		$title = __( 'Click to show only users who have no group', 'pp' );
		$style = ( ! empty( $_REQUEST['pp_no_group'] ) && ( empty( $_REQUEST['orderby'] ) || 'pp_group' != $_REQUEST['orderby'] ) ) ? 'style="font-weight:bold; color:black"' : '';
		$defaults['pp_no_groups'] = sprintf( __('%1$s(x)%2$s', 'pp'), "<a href='?pp_no_group=1' title='$title' $style>", '</a>' );
		
		$defaults['pp_groups'] = __('Groups', 'pp');
		
		$title = __( 'Click to show only users who have supplemental roles', 'pp' );
		$style = ( ! empty( $_REQUEST['pp_has_roles'] ) ) ? 'style="font-weight:bold; color:black"' : '';
		$defaults['pp_roles'] = sprintf( __('Roles %1$s*%2$s', 'pp'), "<a href='?pp_has_roles=1' title='$title' $style>", '</a>' );
		
		unset($defaults['role']);
		unset($defaults['bbp_user_role']);
		
		$title = __( 'Click to show only users who have exceptions', 'pp' );
		$style = ( ! empty( $_REQUEST['pp_has_exceptions'] ) ) ? 'style="font-weight:bold; color:black"' : '';
		$defaults['pp_exceptions'] = sprintf( __('Exceptions %1$s*%2$s', 'pp'), "<a href='?pp_has_exceptions=1' title='$title' $style>", '</a>' );
		return $defaults;
	}

	public static function flt_users_columns_sortable( $columns ) {
		$columns['pp_groups'] = 'pp_group';
		return $columns;
	}
	
	public static function flt_users_custom_column($content = '', $column_name, $id) {
		switch( $column_name ) {
			case 'pp_groups' :
				global $wp_list_table;
				
				//if ( ! $agent_type = apply_filters( 'pp_query_group_type', '' ) )
				//	$agent_type = 'pp_group';
				
				static $all_groups;
				static $all_group_types;
				
				if ( ! isset($all_groups) ) {
					$all_groups = array();
					$all_group_types = pp_get_group_types( array( 'editable' => true ) );
				}

				$all_group_names = array();

				foreach( $all_group_types as $agent_type ) {
					if ( ! isset($all_groups[$agent_type]) )
						$all_groups[$agent_type] = pp_get_groups( $agent_type );
		
					if ( empty($all_groups[$agent_type]) )
						continue;

					if ( ( 'pp_group' == $agent_type ) && in_array( 'pp_net_group', $all_group_types ) && ( 1 == get_current_blog_id() ) )
						continue;
					
					$group_names = array();
					
					if ( $group_ids = pp_get_groups_for_user( $id, $agent_type, array( 'cols' => 'id', 'query_user_ids' => array_keys( $wp_list_table->items ) ) ) ) {
						if ( 'pp_group' == $agent_type ) {
							if ( ! current_user_can( 'pp_manage_members' ) ) {
								$group_ids = pp_array_subset( $group_ids, _pp_retrieve_admin_groups() );
							}
						}
						
						foreach ( array_keys($group_ids) as $group_id ) {
							if ( isset( $all_groups[$agent_type][$group_id] ) ) {
								if ( empty($all_groups[$agent_type][$group_id]->metagroup_type) || ( 'wp_role' != $all_groups[$agent_type][$group_id]->metagroup_type ) ) {
									$group_names [ $all_groups[$agent_type][$group_id]->name ] = $group_id;
								}
							}
						}

						if ( $group_names ) {
							uksort($group_names, "strnatcasecmp");

							foreach( $group_names as $name => $_id ) {
								if ( defined( 'PP_USERS_UI_GROUP_FILTER_LINK' ) ) {
									$url = add_query_arg( 'pp_group', $_id, $_SERVER['REQUEST_URI'] );
									$all_group_names[] = "<a href='$url'>$name</a>";
								} else
									$all_group_names[] = "<a href='" . "admin.php?page=pp-edit-permissions&amp;action=edit&amp;agent_type=$agent_type&amp;agent_id=$_id'>$name</a>";
							}
							//$group_names = array_merge( $group_names, $this_group_names );
						}
					}
				}
				
				return implode(", ", $all_group_names);
				break;
				
			case 'pp_no_groups' :
				break;
				
			case 'pp_roles' :
				global $wp_list_table, $wp_roles;
				static $role_info;
				
				$role_str = '';
				
				if ( ! isset($role_info) )
					$role_info = ppc_count_assigned_roles( 'user', array( 'query_agent_ids' => array_keys( $wp_list_table->items ) ) );
				
				$user_object = new WP_User( (int) $id );

				static $hide_roles;
				if ( ! isset($hide_roles) ) {
					$hide_roles = ( ! defined('bbp_get_version') ) ? array( 'bbp_participant', 'bbp_moderator', 'bbp_keymaster', 'bbp_blocked', 'bbp_spectator' ) : array();
					$hide_roles = apply_filters( 'pp_hide_roles', $hide_roles );
				}

				// === clean up after any inappropriate role metagroup auto-deletion ===
				$user_groups = pp_get_groups_for_user( $id, 'pp_group' );	// these are already being buffered, so no extra DB overhead
				$has_wp_role_metagroup = false;
				foreach( $user_groups as $group ) {
					if ( ( 'wp_role' == $group->metagroup_type ) && ! in_array( $group->metagroup_id, array( 'wp_auth', 'wp_all' ) ) && ! in_array( $group->metagroup_id, $hide_roles ) ) {
						$has_wp_role_metagroup = true;
						break;
					}
				}
				
				// if this user does not have at least on role metagroup stored, see if one should be added
				if ( ! $has_wp_role_metagroup ) {
					foreach( $user_object->roles as $role_name ) {
						if ( $role_group = pp_get_metagroup( 'wp_role', $role_name ) ) {
							pp_add_group_user( $role_group->ID, $id );
							
							// force reload of supplemental roles and exceptions
							$role_info = ppc_count_assigned_roles( 'user', array( 'query_agent_ids' => array_keys( $wp_list_table->items ), 'force_refresh' => true ) );
							ppc_list_agent_exceptions( 'user', $id, array( 'query_agent_ids' => array_keys( $wp_list_table->items ), 'force_refresh' => true ) );
							break;
						}
					}
				}
				// === end role metagroup cleanup ===

				$user_object->roles = array_diff( $user_object->roles, $hide_roles );
				
				$role_titles = array();
				foreach( $user_object->roles as $role_name ) {
					if ( isset( $wp_roles->role_names[$role_name] ) )
						$role_titles []= $wp_roles->role_names[$role_name];
				}
				
				if ( isset( $role_info[$id] ) && isset( $role_info[$id]['roles'] ) )
					$role_titles = array_merge( $role_titles, array_keys($role_info[$id]['roles']) );
					
				$display_limit = 3;
				if ( count($role_titles) > $display_limit ) {
					$excess = count($role_titles) - $display_limit;
					$role_titles = array_slice( $role_titles, 0, $display_limit	);
					$role_titles []= sprintf( __('%s&nbsp;more', 'pp'), $excess );
				}
	
				$role_str = '<span class="pp-group-site-roles">' . implode( ', ', $role_titles ) . '</span>';
				
				if ( current_user_can('edit_user', $id) && current_user_can('pp_assign_roles') ) {
					$edit_link = "admin.php?page=pp-edit-permissions&amp;action=edit&amp;agent_id=$id&amp;agent_type=user";
					$role_str = "<a href=\"$edit_link\">$role_str</a><br />";
				}
				
				return $role_str;
				break;
				
			case 'pp_exceptions' :
				global $wp_list_table;
				return ppc_list_agent_exceptions( 'user', $id, array( 'query_agent_ids' => array_keys( $wp_list_table->items ) ) );
				break;
			
			default :
				return $content;
		}
	}
	
	public static function flt_user_query( $query ) {
		// invalid user count due to redundant joined usermeta rows; no WP_User_Query filter effective when fields arg is 'all_with_meta'
	
		global $wpdb;
		if ( 0 === strpos( $query, "SELECT SQL_CALC_FOUND_ROWS $wpdb->users.ID FROM" ) )
			$query = str_replace( "$wpdb->users.ID FROM", "DISTINCT $wpdb->users.ID FROM", $query );

		return $query;
	}

	public static function flt_user_query_exceptions( $query_obj ) {
		if ( isset( $_REQUEST['orderby'] ) && 'pp_group' == $_REQUEST['orderby'] ) {
			global $wpdb;
			
			$query_obj->query_where = " INNER JOIN $wpdb->pp_group_members AS gm ON gm.user_id = $wpdb->users.ID 
										INNER JOIN $wpdb->pp_groups as g ON gm.group_id = g.ID AND g.metagroup_id='' " . $query_obj->query_where;
			
			$order = ( isset($_REQUEST['order']) && ( 'desc' == $_REQUEST['order'] ) ) ? 'DESC' : 'ASC';
			$query_obj->query_orderby = "ORDER BY g.group_name $order, $wpdb->users.display_name";
			
		} elseif ( isset( $_REQUEST['pp_no_group'] ) ) {
			global $wpdb;
			$query_obj->query_where .= " AND $wpdb->users.ID NOT IN ( SELECT gm.user_id FROM $wpdb->pp_group_members AS gm INNER JOIN $wpdb->pp_groups as g ON gm.group_id = g.ID AND g.metagroup_id='' )";
		}
		
		if ( ! empty( $_REQUEST['pp_user_exceptions'] ) ) {
			global $wpdb;
			$query_obj->query_where .= " AND ID IN ( SELECT agent_id FROM $wpdb->ppc_exceptions AS e INNER JOIN $wpdb->ppc_exception_items AS i ON e.exception_id = i.exception_id WHERE e.agent_type = 'user' )";
		}
		
		if ( ! empty( $_REQUEST['pp_user_roles'] ) ) {
			global $wpdb;
			$query_obj->query_where .= " AND ID IN ( SELECT agent_id FROM $wpdb->ppc_roles WHERE agent_type = 'user' )";
		}

		if ( ! empty( $_REQUEST['pp_user_perms'] ) ) {
			global $wpdb;
			$query_obj->query_where .= " AND ( ID IN ( SELECT agent_id FROM $wpdb->ppc_roles WHERE agent_type = 'user' ) OR ID IN ( SELECT agent_id FROM $wpdb->ppc_exceptions AS e INNER JOIN $wpdb->ppc_exception_items AS i ON e.exception_id = i.exception_id WHERE e.agent_type = 'user' ) )";
		}
		
		if ( ! empty( $_REQUEST['pp_has_exceptions'] ) ) {
			global $wpdb;
			$query_obj->query_where .= " AND ID IN ( SELECT agent_id FROM $wpdb->ppc_exceptions AS e INNER JOIN $wpdb->ppc_exception_items AS i ON e.exception_id = i.exception_id WHERE e.agent_type = 'user' ) OR ID IN ( SELECT user_id FROM $wpdb->pp_group_members AS ug INNER JOIN $wpdb->ppc_exceptions AS e ON e.agent_id = ug.group_id AND e.agent_type = 'pp_group' )";
		}
		
		if ( ! empty( $_REQUEST['pp_has_roles'] ) ) {
			global $wpdb;
			$query_obj->query_where .= " AND ID IN ( SELECT agent_id FROM $wpdb->ppc_roles WHERE agent_type = 'user' ) OR ID IN ( SELECT user_id FROM $wpdb->pp_group_members AS ug INNER JOIN $wpdb->ppc_roles AS r ON r.agent_id = ug.group_id AND r.agent_type = 'pp_group' )";
		}
		
		if ( ! empty( $_REQUEST['pp_has_perms'] ) ) {
			global $wpdb;
			$query_obj->query_where .= " AND ID IN ( SELECT agent_id FROM $wpdb->ppc_exceptions AS e INNER JOIN $wpdb->ppc_exception_items AS i ON e.exception_id = i.exception_id WHERE e.agent_type = 'user' ) OR ID IN ( SELECT user_id FROM $wpdb->pp_group_members AS ug INNER JOIN $wpdb->ppc_exceptions AS e ON e.agent_id = ug.group_id AND e.agent_type = 'pp_group' ) OR ID IN ( SELECT agent_id FROM $wpdb->ppc_roles WHERE agent_type = 'user' ) OR ID IN ( SELECT user_id FROM $wpdb->pp_group_members AS ug INNER JOIN $wpdb->ppc_roles AS r ON r.agent_id = ug.group_id AND r.agent_type = 'pp_group' )";
		}
		
		if ( ! empty( $_REQUEST['pp_group'] ) ) {
			global $wpdb;
			$query_obj->query_where .= " AND ID IN ( SELECT user_id FROM $wpdb->pp_group_members WHERE group_id = '" . (int) $_REQUEST['pp_group'] . "' )";
		}
		
		return $query_obj;
	}
	
} // end class
