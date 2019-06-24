<?php
/**
 * Post / Term metabox UI, ported by Kevin Behrens from wp-admin/nav-menus.php
 *
*/

require_once(ABSPATH . 'wp-admin/includes/template.php');

/**
 * Create HTML list of nav menu input items.
 *
 * Ported from Walker_Nav_Menu_Checklist to eliminate hidden inputs which are not useful to PP usage on Edit Permission Group screen
 */
class PP_Walker_Nav_Menu_Checklist extends Walker_Nav_Menu {
	function __construct( $fields = false ) {
		if ( $fields ) {
			$this->db_fields = $fields;
		}
	}
	
	function start_lvl( &$output, $depth = 0, $args = array() ) {
		$indent = str_repeat( "\t", $depth );
		$output .= "\n$indent<ul class='children'>\n";
	}

	function end_lvl( &$output, $depth = 0, $args = array() ) {
		$indent = str_repeat( "\t", $depth );
		$output .= "\n$indent</ul>";
	}

	/**
	 * @see Walker::start_el()
	 * @since 3.0.0
	 *
	 * @param string $output Passed by reference. Used to append additional content.
	 * @param object $item Menu item data object.
	 * @param int $depth Depth of menu item. Used for padding.
	 * @param object $args
	 */
	function start_el( &$output, $item, $depth = 0, $args = array(), $current_object_id = 0 ) {
		global $_nav_menu_placeholder;

		$_nav_menu_placeholder = ( 0 > $_nav_menu_placeholder ) ? intval($_nav_menu_placeholder) - 1 : -1;

		$indent = ( $depth ) ? str_repeat( "\t", $depth ) : '';
		
		$default_depth_display = ( ! empty( $args->default_depth_display ) ) ? $args->default_depth_display : 1;
		
		$hide = ( $depth > $default_depth_display - 1 ) ? ' style="display:none"' : '';
		
		$output .= $indent . "<li{$hide}>";
		
		$output .= '<input type="checkbox" class="menu-item-checkbox" value="'. esc_attr( $item->object_id ) .'" /> ';
		
		$output .= ( $depth || ( ! empty( $args->hierarchical ) && ( 1 == $default_depth_display ) ) ) ? "<span> &ndash; </span>" : '';
		
		if ( ! empty( $args->is_search_result ) && ! empty( $args->hierarchical ) ) {
			require_once( PPC_ABSPATH . '/lib/ancestry_lib_pp.php' );
			$title_attrib = ' title="' . esc_attr( PP_Ancestry::get_post_path( $item->object_id ) ) . '"';
		} else {
			$title_attrib = '';
		}
		
		$output .= "<label test='test'{$title_attrib}>";
		$output .= esc_html( $item->title );
		$output .= '</label>';
	}
}

/**
 * Displays a metabox for a post type menu item.
 *
 * @param string $object Not used.
 * @param string $post_type The post type object.
 */
function pp_nav_menu_item_post_type_meta_box( $object, $post_type ) {
	global $_nav_menu_placeholder, $nav_menu_selected_id;

	$post_type_name = $post_type['args']->name;

	// paginate browsing for large numbers of post objects
	if ( is_post_type_hierarchical( $post_type_name ) ) {
		$per_page = defined( 'PP_ITEM_MENU_HIERARCHICAL_PER_PAGE' ) ? PP_ITEM_MENU_HIERARCHICAL_PER_PAGE : 1000;
	} else {
		$per_page = defined( 'PP_ITEM_MENU_PER_PAGE' ) ? PP_ITEM_MENU_PER_PAGE : 100;
	}
	
	$pagenum = isset( $_REQUEST[$post_type_name . '-tab'] ) && isset( $_REQUEST['paged'] ) ? absint( $_REQUEST['paged'] ) : 1;
	$offset = 0 < $pagenum ? $per_page * ( $pagenum - 1 ) : 0;
	
	$args = array(
		'offset' => $offset,
		'order' => 'ASC',
		'orderby' => 'title',
		'posts_per_page' => $per_page,
		'post_type' => $post_type_name,
		'suppress_filters' => true,
		'update_post_term_cache' => false,
		'update_post_meta_cache' => false
	);

	if ( 'attachment' == $post_type_name )
		$args['post_status'] = 'inherit';
	
	if ( isset( $post_type['args']->_default_query ) )
		$args = array_merge($args, (array) $post_type['args']->_default_query );
	
	$get_posts = new WP_Query;
	$posts = $get_posts->query( $args );
	if ( ! $get_posts->post_count ) {
		echo '<p>' . __( 'No items.' ) . '</p>';
		return;
	}

	$post_type_object = get_post_type_object($post_type_name);

	$num_pages = $get_posts->max_num_pages;

	$page_links = paginate_links( array(
		'base' => add_query_arg(
			array(
				$post_type_name . '-tab' => 'all',
				'paged' => '%#%',
				'item-type' => 'post_type',
				'item-object' => $post_type_name,
			)
		),
		'format' => '',
		'prev_text' => __('&laquo;'),
		'next_text' => __('&raquo;'),
		'total' => $num_pages,
		'current' => $pagenum
	));

	if ( !$posts )
		$error = '<li id="error">'. $post_type['args']->labels->not_found .'</li>';

	$db_fields = false;
	if ( $hierarchical = is_post_type_hierarchical( $post_type_name ) ) {
		$db_fields = array( 'parent' => 'post_parent', 'id' => 'ID' );
	}

	$walker = new PP_Walker_Nav_Menu_Checklist( $db_fields );

	$current_tab = 'most-recent';
	if ( isset( $_REQUEST[$post_type_name . '-tab'] ) && in_array( $_REQUEST[$post_type_name . '-tab'], array('all', 'search') ) ) {
		$current_tab = pp_sanitize_key($_REQUEST[$post_type_name . '-tab']);
	}

	if ( ! empty( $_REQUEST['quick-search-posttype-' . $post_type_name] ) ) {
		$current_tab = 'search';
	}

	$removed_args = array(
		'action',
		'customlink-tab',
		'edit-menu-item',
		'menu-item',
		'page-tab',
		'_wpnonce',
	);

	?>
	<div id="posttype-<?php echo $post_type_name; ?>" class="posttypediv<?php if ( $hierarchical ) echo ' hierarchical';?>">
		<ul id="posttype-<?php echo $post_type_name; ?>-tabs" class="posttype-tabs add-menu-item-tabs">
			<li <?php echo ( 'most-recent' == $current_tab ? ' class="tabs"' : '' ); ?>><a class="nav-tab-link" href="<?php if ( $nav_menu_selected_id ) echo esc_url(add_query_arg($post_type_name . '-tab', 'most-recent', remove_query_arg($removed_args))); ?>#tabs-panel-posttype-<?php echo $post_type_name; ?>-most-recent"><?php _e('Most Recent'); ?></a></li>
			<li <?php echo ( 'all' == $current_tab ? ' class="tabs"' : '' ); ?>><a class="nav-tab-link" href="<?php if ( $nav_menu_selected_id ) echo esc_url(add_query_arg($post_type_name . '-tab', 'all', remove_query_arg($removed_args))); ?>#<?php echo $post_type_name; ?>-all"><?php _e('View All'); ?></a></li>
			<li <?php echo ( 'search' == $current_tab ? ' class="tabs"' : '' ); ?>><a class="nav-tab-link" href="<?php if ( $nav_menu_selected_id ) echo esc_url(add_query_arg($post_type_name . '-tab', 'search', remove_query_arg($removed_args))); ?>#tabs-panel-posttype-<?php echo $post_type_name; ?>-search"><?php _e('Search'); ?></a></li>
		</ul>

		<div id="tabs-panel-posttype-<?php echo $post_type_name; ?>-most-recent" class="tabs-panel <?php
			echo ( 'most-recent' == $current_tab ? 'tabs-panel-active' : 'tabs-panel-inactive' );
		?>">
			<ul id="<?php echo $post_type_name; ?>checklist-most-recent" class="categorychecklist form-no-clear">
				<?php
				$recent_args = array_merge( $args, array( 'orderby' => 'post_date', 'order' => 'DESC', 'posts_per_page' => 15 ) );
				$most_recent = $get_posts->query( $recent_args );
				$args['walker'] = $walker;
				echo walk_nav_menu_tree( array_map('pp_setup_nav_menu_item', $most_recent), 0, (object) $args );
				?>
			</ul>
		</div><!-- /.tabs-panel -->

		<div class="tabs-panel <?php
			echo ( 'search' == $current_tab ? 'tabs-panel-active' : 'tabs-panel-inactive' );
		?>" id="tabs-panel-posttype-<?php echo $post_type_name; ?>-search">
			<?php
			if ( isset( $_REQUEST['quick-search-posttype-' . $post_type_name] ) ) {
				if ( function_exists( '_filter_query_attachment_filenames' ) ) add_filter( 'posts_clauses', '_filter_query_attachment_filenames' );
				
				$searched = esc_attr( $_REQUEST['quick-search-posttype-' . $post_type_name] );
				$post_status = ( 'attachment' == $post_type_name ) ? 'inherit' : '';
				$search_results = query_posts( array( 's' => $searched, 'post_type' => $post_type_name, 'fields' => 'all', 'order' => 'DESC', 'post_status' => $post_status ) );
				
				remove_filter( 'posts_clauses', '_filter_query_attachment_filenames' );
			} else {
				$searched = '';
				$search_results = array();
			}
			?>
			<p class="quick-search-wrap">
				<input type="search" class="pp-quick-search input-with-default-title" title="<?php esc_attr_e('Search'); ?>" value="<?php echo $searched; ?>" name="quick-search-posttype-<?php echo $post_type_name; ?>" />
				<img class="waiting" style="display:none" src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" alt="" />
				<?php submit_button( __( 'Search' ), 'quick-search-submit button-secondary hide-if-js', 'submit', false, array( 'id' => 'submit-quick-search-posttype-' . $post_type_name ) ); ?>
			</p>

			<ul id="<?php echo $post_type_name; ?>-search-checklist" class="list:<?php echo $post_type_name?> categorychecklist form-no-clear">
			<?php if ( ! empty( $search_results ) && ! is_wp_error( $search_results ) ) : ?>
				<?php
				$args['walker'] = $walker;
				echo walk_nav_menu_tree( array_map('pp_setup_nav_menu_item', $search_results), 0, (object) $args );
				?>
			<?php elseif ( is_wp_error( $search_results ) ) : ?>
				<li><?php echo $search_results->get_error_message(); ?></li>
			<?php elseif ( ! empty( $searched ) ) : ?>
				<li><?php _e('No results found.'); ?></li>
			<?php endif; ?>
			</ul>
		</div><!-- /.tabs-panel -->

		<div id="<?php echo $post_type_name; ?>-all" class="tabs-panel tabs-panel-view-all <?php
			echo ( 'all' == $current_tab ? 'tabs-panel-active' : 'tabs-panel-inactive' );
		?>">
			<?php if ( ! empty( $page_links ) ) : ?>
				<div class="add-menu-item-pagelinks">
					<?php echo $page_links; ?>
				</div>
			<?php endif; ?>
			<ul id="<?php echo $post_type_name; ?>checklist" class="list:<?php echo $post_type_name?> categorychecklist form-no-clear">
				<?php
				$args['walker'] = $walker;

				// kevinB: add "(none)" item for include exceptions
				$front_page_obj = (object) array( 'ID' => 0, 'post_parent' => 0, 'post_content' => '', 'post_excerpt' => '', 'post_title' => __( '(none)', 'pp' ), 'object_id' => 0, 'title' => __( '(none)', 'pp' ), 'menu_item_parent' => 0, 'db_id' => 0 );
				$front_page_obj->_add_to_top = true;
				$front_page_obj->label = __( '(none)', 'pp' );
				array_unshift( $posts, $front_page_obj );
				
				$posts = apply_filters( 'nav_menu_items_'.$post_type_name, $posts, $args, $post_type );
				
				if ( is_post_type_hierarchical( $post_type_name ) ) {
					if ( defined( 'PP_ITEM_MENU_FORCE_DISPLAY_DEPTH' ) ) {
						$default_depth_display = max( 1, constant('PP_ITEM_MENU_FORCE_DISPLAY_DEPTH') );
					} else {
						require_once( PPC_ABSPATH . '/lib/ancestry_lib_pp.php' );
						
						$max_visible_items = ( defined( 'PP_ITEM_MENU_DEFAULT_MAX_VISIBLE' ) ) ? PP_ITEM_MENU_DEFAULT_MAX_VISIBLE : 50;
						
						for( $default_depth_display=10; $default_depth_display > 1; $default_depth_display-- ) {
							$arr = PP_Ancestry::get_page_descendants( 0, array( 'post_type' => $post_type_name, 'pages' => $posts, 'max_depth' => $default_depth_display ) );
							
							if ( count( $arr ) <= $max_visible_items ) {
								break;
							}
						}
					}
					
					$args['default_depth_display'] = $default_depth_display;
					$args['hierarchical'] = true;
				}
				
				$checkbox_items = walk_nav_menu_tree( array_map('pp_setup_nav_menu_item', $posts), 0, (object) $args );

				if ( 'all' == $current_tab && ! empty( $_REQUEST['selectall'] ) ) {
					$checkbox_items = preg_replace('/(type=(.)checkbox(\2))/', '$1 checked=$2checked$2', $checkbox_items);

				}

				echo $checkbox_items;
				?>
			</ul>
			<?php if ( ! empty( $page_links ) ) : ?>
				<div class="add-menu-item-pagelinks">
					<?php echo $page_links; ?>
				</div>
			<?php endif; ?>
		</div><!-- /.tabs-panel -->

		<p class="button-controls">
			<span class="list-controls">
				<a href="<?php
					echo esc_url(add_query_arg(
						array(
							$post_type_name . '-tab' => 'all',
							'selectall' => 1,
						),
						remove_query_arg($removed_args)
					));
				?>#posttype-<?php echo $post_type_name; ?>" class="select-all"><?php _e('Select All'); ?></a>
			</span>
			
			<span class="add-to-menu">
				<img class="waiting" style="display:none" src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" alt="" />
				<input type="submit"<?php disabled( $nav_menu_selected_id, 0 ); ?> class="button-secondary submit-add-item-exception" value="<?php esc_attr_e('Add Exceptions', 'pp'); ?>" name="add-post-type-menu-item" id="submit-posttype-<?php echo $post_type_name; ?>" />
			</span>
		</p>

	</div> <!-- /.posttypediv -->

	<?php
}


// Displays a metabox for pp_group items.
function pp_nav_menu_item_group_meta_box( $object, $post_type ) {
	global $_nav_menu_placeholder, $nav_menu_selected_id;

	$post_type_name = $post_type['args']->name;

	// paginate browsing for large numbers of post objects
	$per_page = ( defined( 'PP_ITEM_MENU_PER_PAGE' ) ) ? PP_ITEM_MENU_PER_PAGE : 50;
	$pagenum = isset( $_REQUEST[$post_type_name . '-tab'] ) && isset( $_REQUEST['paged'] ) ? absint( $_REQUEST['paged'] ) : 1;
	$offset = 0 < $pagenum ? $per_page * ( $pagenum - 1 ) : 0;

	$args = array(
		'offset' => $offset,
		'order' => 'ASC',
		'orderby' => 'title',
		'posts_per_page' => $per_page,
		'post_type' => $post_type_name,
		'suppress_filters' => true,
		'update_post_term_cache' => false,
		'update_post_meta_cache' => false
	);

	if ( 'attachment' == $post_type_name )
		$args['post_status'] = 'inherit';
	
	if ( ! $posts = pp_get_groups( $post_type_name ) ) {
		echo '<p>' . __( 'No items.' ) . '</p>';
		return;
	}
	
	$post_type_object = pp_get_group_type_object($post_type_name);
	
	//$num_pages = $get_posts->max_num_pages;
	$num_pages = 1;
	
	$page_links = paginate_links( array(
		'base' => add_query_arg(
			array(
				$post_type_name . '-tab' => 'all',
				'paged' => '%#%',
				'item-type' => 'post_type',
				'item-object' => $post_type_name,
			)
		),
		'format' => '',
		'prev_text' => __('&laquo;'),
		'next_text' => __('&raquo;'),
		'total' => $num_pages,
		'current' => $pagenum
	));

	if ( !$posts )
		$error = '<li id="error">'. $post_type['args']->labels->not_found .'</li>';

	$db_fields = false;
	
	$db_fields = array( 'parent' => 'post_parent', 'id' => 'ID' );
	$walker = new PP_Walker_Nav_Menu_Checklist( $db_fields );

	$current_tab = 'most-recent';
	if ( isset( $_REQUEST[$post_type_name . '-tab'] ) && in_array( $_REQUEST[$post_type_name . '-tab'], array('all', 'search') ) ) {
		$current_tab = pp_sanitize_key($_REQUEST[$post_type_name . '-tab']);
	}

	$removed_args = array(
		'action',
		'customlink-tab',
		'edit-menu-item',
		'menu-item',
		'page-tab',
		'_wpnonce',
	);

	?>
	<div id="posttype-<?php echo $post_type_name; ?>" class="posttypediv">
		<ul id="posttype-<?php echo $post_type_name; ?>-tabs" class="posttype-tabs add-menu-item-tabs">
			<li <?php echo ( 'most-recent' == $current_tab ? ' class="tabs"' : '' ); ?>><a class="nav-tab-link" href="<?php if ( $nav_menu_selected_id ) echo esc_url(add_query_arg($post_type_name . '-tab', 'most-recent', remove_query_arg($removed_args))); ?>#tabs-panel-posttype-<?php echo $post_type_name; ?>-most-recent"><?php _e('Most Recent'); ?></a></li>
			<li <?php echo ( 'all' == $current_tab ? ' class="tabs"' : '' ); ?>><a class="nav-tab-link" href="<?php if ( $nav_menu_selected_id ) echo esc_url(add_query_arg($post_type_name . '-tab', 'all', remove_query_arg($removed_args))); ?>#<?php echo $post_type_name; ?>-all"><?php _e('View All'); ?></a></li>
			<!-- <li -search </li> -->
		</ul>

		<div id="tabs-panel-posttype-<?php echo $post_type_name; ?>-most-recent" class="tabs-panel <?php
			echo ( 'most-recent' == $current_tab ? 'tabs-panel-active' : 'tabs-panel-inactive' );
		?>">
			<ul id="<?php echo $post_type_name; ?>checklist-most-recent" class="categorychecklist form-no-clear">
				<?php
				//$recent_args = array_merge( $args, array( 'orderby' => 'post_date', 'order' => 'DESC', 'posts_per_page' => 15 ) );
				//$most_recent = $get_posts->query( $recent_args );
				
				$_args = array( 'skip_meta_types' => 'wp_role', 'order_by' => 'ug.add_date_gmt DESC' );
				
				global $wpdb;
				$groups_table = apply_filters( 'pp_use_groups_table', $wpdb->pp_groups, $post_type_name );
				$group_members_table = apply_filters( 'pp_use_group_members_table', $wpdb->pp_group_members, $post_type_name );
				
				$_args['join'] = "INNER JOIN $group_members_table AS ug ON $groups_table.ID = ug.group_id";
				
				$most_recent = pp_get_groups( $post_type_name, $_args );
				foreach( array_keys( $most_recent ) as $key ) {
					$most_recent[$key]->object_id = $posts[$key]->ID;
					$most_recent[$key]->title = $posts[$key]->name;
					$most_recent[$key]->post_parent = 0;
					//$most_recent[$key]->post_type = $post_type_name;
					$most_recent[$key]->custom_source = $post_type_name;
				}
				
				$args['walker'] = $walker;
				echo walk_nav_menu_tree( array_map('pp_setup_nav_menu_item', $most_recent), 0, (object) $args );
				?>
			</ul>
		</div><!-- /.tabs-panel -->

		<!-- search
		<div class="tabs-panel">
		</div>
		-->

		<div id="<?php echo $post_type_name; ?>-all" class="tabs-panel tabs-panel-view-all <?php
			echo ( 'all' == $current_tab ? 'tabs-panel-active' : 'tabs-panel-inactive' );
		?>">
			<?php if ( ! empty( $page_links ) ) : ?>
				<div class="add-menu-item-pagelinks">
					<?php echo $page_links; ?>
				</div>
			<?php endif; ?>
			<ul id="<?php echo $post_type_name; ?>checklist" class="list:<?php echo $post_type_name?> categorychecklist form-no-clear">
				<?php
				$db_fields = array( 'parent' => 'post_parent', 'id' => 'ID' );
				$walker = new PP_Walker_Nav_Menu_Checklist( $db_fields );
				$args['walker'] = $walker;

				/*
				// kevinB: add "(none)" item for include exceptions
				$front_page_obj = (object) array( 'ID' => 0, 'post_parent' => 0, 'post_content' => '', 'post_excerpt' => '', 'post_title' => __( '(none)', 'pp' ), 'object_id' => 0, 'title' => __( '(none)', 'pp' ), 'menu_item_parent' => 0, 'db_id' => 0 );
				$front_page_obj->_add_to_top = true;
				$front_page_obj->label = __( '(none)', 'pp' );
				array_unshift( $posts, $front_page_obj );
				
				$posts = apply_filters( 'nav_menu_items_'.$post_type_name, $posts, $args, $post_type );
				*/
				
				$_args = array( 'skip_meta_types' => 'wp_role' );
				$posts = pp_get_groups( $post_type_name, $_args );
				foreach( array_keys( $posts ) as $key ) {
					$posts[$key]->object_id = $posts[$key]->ID;
					$posts[$key]->title = $posts[$key]->name;
					$posts[$key]->post_parent = 0;
					//$posts[$key]->post_type = $post_type_name;
					$posts[$key]->custom_source = $post_type_name;
				}
				
				$checkbox_items = walk_nav_menu_tree( array_map('pp_setup_nav_menu_item', $posts), 0, (object) $args );

				if ( 'all' == $current_tab && ! empty( $_REQUEST['selectall'] ) ) {
					$checkbox_items = preg_replace('/(type=(.)checkbox(\2))/', '$1 checked=$2checked$2', $checkbox_items);
				}
				
				echo $checkbox_items;
				?>
			</ul>
			<?php if ( ! empty( $page_links ) ) : ?>
				<div class="add-menu-item-pagelinks">
					<?php echo $page_links; ?>
				</div>
			<?php endif; ?>
		</div><!-- /.tabs-panel -->

		<p class="button-controls">
			<span class="list-controls">
				<a href="<?php
					echo esc_url(add_query_arg(
						array(
							$post_type_name . '-tab' => 'all',
							'selectall' => 1,
						),
						remove_query_arg($removed_args)
					));
				?>#posttype-<?php echo $post_type_name; ?>" class="select-all"><?php _e('Select All'); ?></a>
			</span>
			
			<span class="add-to-menu">
				<img class="waiting" style="display:none" src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" alt="" />
				<input type="submit"<?php disabled( $nav_menu_selected_id, 0 ); ?> class="button-secondary submit-add-<?php echo $post_type_name;?>-exception" value="<?php esc_attr_e('Add Exceptions', 'pp'); ?>" name="add-post-type-menu-item" id="submit-posttype-<?php echo $post_type_name; ?>" />
			</span>
		</p>

	</div> <!-- /.posttypediv -->

	<?php
}



/**
 * Displays a metabox for a taxonomy menu item.
 *
 * @param string $object Not used.
 * @param string $taxonomy The taxonomy object.
 */
function pp_nav_menu_item_taxonomy_meta_box( $object, $taxonomy ) {
	global $nav_menu_selected_id;
	$taxonomy_name = $taxonomy['args']->name;

	// paginate browsing for large numbers of objects
	$per_page = 50;
	$pagenum = isset( $_REQUEST[$taxonomy_name . '-tab'] ) && isset( $_REQUEST['paged'] ) ? absint( $_REQUEST['paged'] ) : 1;
	$offset = 0 < $pagenum ? $per_page * ( $pagenum - 1 ) : 0;

	$args = array(
		'child_of' => 0,
		'exclude' => '',
		'hide_empty' => false,
		'hierarchical' => 1,
		'include' => '',
		'number' => $per_page,
		'offset' => $offset,
		'order' => 'ASC',
		'orderby' => 'name',
		'pad_counts' => false,
	);

	$terms = get_terms( $taxonomy_name, $args );

	// kevinB: add "(none)" item for include exceptions
	$none_obj = (object) array( 'term_taxonomy_id' => 0, 'parent' => 0, 'term_id' => 0, 'name' => __( '(none)', 'pp' ), 'object_id' => 0, 'title' => __( '(none)', 'pp' ), 'menu_item_parent' => 0, 'db_id' => 0 );
	$none_obj->_add_to_top = true;
	$none_obj->label = __( '(none)', 'pp' );
	array_unshift( $terms, $none_obj );
	
	if ( ! $terms || is_wp_error($terms) ) {
		echo '<p>' . __( 'No items.' ) . '</p>';
		return;
	}

	$num_pages = ceil( wp_count_terms( $taxonomy_name , array_merge( $args, array('number' => '', 'offset' => '') ) ) / $per_page );

	$page_links = paginate_links( array(
		'base' => add_query_arg(
			array(
				$taxonomy_name . '-tab' => 'all',
				'paged' => '%#%',
				'item-type' => 'taxonomy',
				'item-object' => $taxonomy_name,
			)
		),
		'format' => '',
		'prev_text' => __('&laquo;'),
		'next_text' => __('&raquo;'),
		'total' => $num_pages,
		'current' => $pagenum
	));

	$db_fields = false;
	if ( $hierarchical = is_taxonomy_hierarchical( $taxonomy_name ) ) {
		$db_fields = array( 'parent' => 'parent', 'id' => 'term_id' );
	}

	$walker = new PP_Walker_Nav_Menu_Checklist( $db_fields );

	$current_tab = 'most-used';
	if ( isset( $_REQUEST[$taxonomy_name . '-tab'] ) && in_array( $_REQUEST[$taxonomy_name . '-tab'], array('all', 'most-used', 'search') ) ) {
		$current_tab = pp_sanitize_key( $_REQUEST[$taxonomy_name . '-tab'] );
	}

	if ( ! empty( $_REQUEST['quick-search-taxonomy-' . $taxonomy_name] ) ) {
		$current_tab = 'search';
	}

	$removed_args = array(
		'action',
		'customlink-tab',
		'edit-menu-item',
		'menu-item',
		'page-tab',
		'_wpnonce',
	);

	?>
	<div id="taxonomy-<?php echo $taxonomy_name; ?>" class="taxonomydiv<?php if ( $hierarchical ) echo ' hierarchical';?>">
		<ul id="taxonomy-<?php echo $taxonomy_name; ?>-tabs" class="taxonomy-tabs add-menu-item-tabs">
			<li <?php echo ( 'most-used' == $current_tab ? ' class="tabs"' : '' ); ?>><a class="nav-tab-link" href="<?php if ( $nav_menu_selected_id ) echo esc_url(add_query_arg($taxonomy_name . '-tab', 'most-used', remove_query_arg($removed_args))); ?>#tabs-panel-<?php echo $taxonomy_name; ?>-pop"><?php _e('Most Used'); ?></a></li>
			<li <?php echo ( 'all' == $current_tab ? ' class="tabs"' : '' ); ?>><a class="nav-tab-link" href="<?php if ( $nav_menu_selected_id ) echo esc_url(add_query_arg($taxonomy_name . '-tab', 'all', remove_query_arg($removed_args))); ?>#tabs-panel-<?php echo $taxonomy_name; ?>-all"><?php _e('View All'); ?></a></li>
			<li <?php echo ( 'search' == $current_tab ? ' class="tabs"' : '' ); ?>><a class="nav-tab-link" href="<?php if ( $nav_menu_selected_id ) echo esc_url(add_query_arg($taxonomy_name . '-tab', 'search', remove_query_arg($removed_args))); ?>#tabs-panel-search-taxonomy-<?php echo $taxonomy_name; ?>"><?php _e('Search'); ?></a></li>
		</ul>

		<div id="tabs-panel-<?php echo $taxonomy_name; ?>-pop" class="tabs-panel <?php
			echo ( 'most-used' == $current_tab ? 'tabs-panel-active' : 'tabs-panel-inactive' );
		?>">
			<ul id="<?php echo $taxonomy_name; ?>checklist-pop" class="categorychecklist form-no-clear" >
				<?php
				$popular_terms = get_terms( $taxonomy_name, array( 'orderby' => 'count', 'order' => 'DESC', 'number' => 10, 'hierarchical' => false ) );
				$args['walker'] = $walker;
				echo walk_nav_menu_tree( array_map('pp_setup_nav_menu_item', $popular_terms), 0, (object) $args );
				?>
			</ul>
		</div><!-- /.tabs-panel -->

		<div id="tabs-panel-<?php echo $taxonomy_name; ?>-all" class="tabs-panel tabs-panel-view-all <?php
			echo ( 'all' == $current_tab ? 'tabs-panel-active' : 'tabs-panel-inactive' );
		?>">
			<?php if ( ! empty( $page_links ) ) : ?>
				<div class="add-menu-item-pagelinks">
					<?php echo $page_links; ?>
				</div>
			<?php endif; ?>
			<ul id="<?php echo $taxonomy_name; ?>checklist" class="list:<?php echo $taxonomy_name?> categorychecklist form-no-clear">
				<?php
				if ( is_taxonomy_hierarchical( $taxonomy_name ) ) {
					if ( defined( 'PP_ITEM_MENU_FORCE_DISPLAY_DEPTH' ) ) {
						$default_depth_display = max( 1, constant('PP_ITEM_MENU_FORCE_DISPLAY_DEPTH') );
					} else {
						require_once( PPC_ABSPATH . '/lib/ancestry_lib_pp.php' );
						
						$max_visible_items = ( defined( 'PP_ITEM_MENU_DEFAULT_MAX_VISIBLE' ) ) ? PP_ITEM_MENU_DEFAULT_MAX_VISIBLE : 50;
						
						for( $default_depth_display=10; $default_depth_display > 1; $default_depth_display-- ) {
							$arr = PP_Ancestry::get_term_descendants( 0, array( 'taxonomy' => $taxonomy_name, 'terms' => $terms, 'max_depth' => $default_depth_display ) );
							
							if ( count( $arr ) <= $max_visible_items ) {
								break;
							}
						}
					}
					
					$args['default_depth_display'] = $default_depth_display;
					$args['hierarchical'] = true;
				}
				
				$args['walker'] = $walker;
				echo walk_nav_menu_tree( array_map('pp_setup_nav_menu_item', $terms), 0, (object) $args );
				?>
			</ul>
			<?php if ( ! empty( $page_links ) ) : ?>
				<div class="add-menu-item-pagelinks">
					<?php echo $page_links; ?>
				</div>
			<?php endif; ?>
		</div><!-- /.tabs-panel -->

		<div class="tabs-panel <?php
			echo ( 'search' == $current_tab ? 'tabs-panel-active' : 'tabs-panel-inactive' );
		?>" id="tabs-panel-search-taxonomy-<?php echo $taxonomy_name; ?>">
			<?php
			if ( isset( $_REQUEST['quick-search-taxonomy-' . $taxonomy_name] ) ) {
				$searched = esc_attr( $_REQUEST['quick-search-taxonomy-' . $taxonomy_name] );
				$search_results = get_terms( $taxonomy_name, array( 'name__like' => $searched, 'fields' => 'all', 'orderby' => 'count', 'order' => 'DESC', 'hierarchical' => false ) );
			} else {
				$searched = '';
				$search_results = array();
			}
			?>
			<p class="quick-search-wrap">
				<input type="search" class="pp-quick-search input-with-default-title" title="<?php esc_attr_e('Search'); ?>" value="<?php echo $searched; ?>" name="quick-search-taxonomy-<?php echo $taxonomy_name; ?>" />
				<img class="waiting" style="display:none" src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" alt="" />
				<?php submit_button( __( 'Search' ), 'quick-search-submit button-secondary hide-if-js', 'submit', false, array( 'id' => 'submit-quick-search-taxonomy-' . $taxonomy_name ) ); ?>
			</p>

			<ul id="<?php echo $taxonomy_name; ?>-search-checklist" class="list:<?php echo $taxonomy_name?> categorychecklist form-no-clear">
			<?php if ( ! empty( $search_results ) && ! is_wp_error( $search_results ) ) : ?>
				<?php
				$args['walker'] = $walker;
				echo walk_nav_menu_tree( array_map('pp_setup_nav_menu_item', $search_results), 0, (object) $args );
				?>
			<?php elseif ( is_wp_error( $search_results ) ) : ?>
				<li><?php echo $search_results->get_error_message(); ?></li>
			<?php elseif ( ! empty( $searched ) ) : ?>
				<li><?php _e('No results found.'); ?></li>
			<?php endif; ?>
			</ul>
		</div><!-- /.tabs-panel -->

		<p class="button-controls">
			<span class="list-controls">
				<a href="<?php
					echo esc_url(add_query_arg(
						array(
							$taxonomy_name . '-tab' => 'all',
							'selectall' => 1,
						),
						remove_query_arg($removed_args)
					));
				?>#taxonomy-<?php echo $taxonomy_name; ?>" class="select-all"><?php _e('Select All'); ?></a>
			</span>

			<span class="add-to-menu">
				<img class="waiting" style="display:none" src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" alt="" />
				<input type="submit"<?php disabled( $nav_menu_selected_id, 0 ); ?> class="button-secondary submit-add-item-exception" value="<?php esc_attr_e('Add Exceptions', 'pp'); ?>" name="add-taxonomy-menu-item" id="submit-taxonomy-<?php echo $taxonomy_name; ?>" />
			</span>
		</p>

	</div><!-- /.taxonomydiv -->
	<?php
}

function _pp_item_menu_search_clause( $search ) {
	if ( ! defined( 'PP_ITEM_MENU_SEARCH_CONTENT' ) )
		$search = str_replace( '.post_content LIKE', '.post_title LIKE', $search );
	
	if ( ! defined( 'PP_ITEM_MENU_SEARCH_EXCERPT' ) )
		$search = str_replace( '.post_excerpt LIKE', '.post_title LIKE', $search );
	
	return $search;
}

/**
 * Prints the appropriate response to a menu quick search.
 *
 * @param array $request The unsanitized request values.
 */
function _pp_ajax_menu_quick_search() {
	$request = $_REQUEST;

	$args = array();
	$type = isset( $request['type'] ) ? $request['type'] : '';
	$query = isset( $request['q'] ) ? $request['q'] : '';

	$args['walker'] = new PP_Walker_Nav_Menu_Checklist;
	$args['is_search_result'] = true;
	
	if ( preg_match('/quick-search-(posttype|taxonomy)-([a-zA-Z_-]*\b)/', $type, $matches) ) {
		if ( 'posttype' == $matches[1] && $type_obj = get_post_type_object( $matches[2] ) ) {
			$args['hierarchical'] = $type_obj->hierarchical;
			
			$status = ( 'attachment' == $matches[2] ) ? 'inherit' : '';
			add_filter( 'posts_search', '_pp_item_menu_search_clause' );
			
			if ( function_exists( '_filter_query_attachment_filenames' ) ) add_filter( 'posts_clauses', '_filter_query_attachment_filenames' );
			
			query_posts(array(
				'posts_per_page' => 999,
				'post_type' => $matches[2],
				's' => $query,
				'orderby' => 'title',
				'order' => 'ASC',
				'post_status' => $status,
			));
			
			remove_filter( 'posts_search', '_pp_item_menu_search_clause' );
			
			require_once( PPC_ABSPATH . '/lib/ancestry_lib_pp.php' );
			
			if ( ! have_posts() )
				return;
			while ( have_posts() ) {
				the_post();
				$var_by_ref = get_the_ID();
				echo walk_nav_menu_tree( array_map('pp_setup_nav_menu_item', array( get_post( $var_by_ref ) ) ), 0, (object) $args );
			}
		} elseif ( 'taxonomy' == $matches[1] ) {
			$terms = get_terms( $matches[2], array(
				'name__like' => $query,
				'hide_empty' => false,
				'number' => 99,
			));

			if ( empty( $terms ) || is_wp_error( $terms ) )
				return;
			foreach( (array) $terms as $term ) {
				echo walk_nav_menu_tree( array_map('pp_setup_nav_menu_item', array( $term ) ), 0, (object) $args );
			}
		}
	}
	
	wp_die();
}

/**
 * Decorates a menu item object with the shared navigation menu item properties.
 *
 * Properties:
 * - ID:               The term_id if the menu item represents a taxonomy term.
 * - db_id:            The DB ID of this item as a nav_menu_item object, if it exists (0 if it doesn't exist).
 * - menu_item_parent: The DB ID of the nav_menu_item that is this item's menu parent, if any. 0 otherwise.
 * - object:           The type of object originally represented, such as "category," "post", or "attachment."
 * - object_id:        The DB ID of the original object this menu item represents, e.g. ID for posts and term_id for categories.
 * - post_parent:      The DB ID of the original object's parent object, if any (0 otherwise).
 * - post_title:       A "no title" label if menu item represents a post that lacks a title.
 * - title:            The title of this menu item.
 * - type:             The family of objects originally represented, such as "post_type" or "taxonomy."
 * - _invalid:         Whether the menu item represents an object that no longer exists.
 *
 * @param object $menu_item The menu item to modify.
 * @return object $menu_item The menu item with standard menu item properties.
 */
function pp_setup_nav_menu_item( $menu_item ) {
	if ( isset( $menu_item->post_type ) ) {
		$menu_item->db_id = 0;
		$menu_item->menu_item_parent = 0;
		$menu_item->object_id = (int) $menu_item->ID;

		if ( '' === $menu_item->post_title ) {
			$menu_item->post_title = sprintf( __( '#%d (no title)' ), $menu_item->ID );
		}

		$menu_item->title = $menu_item->post_title;
		
	} elseif ( isset( $menu_item->taxonomy ) ) {
		$menu_item->db_id = 0;
		$menu_item->menu_item_parent = 0;
		$menu_item->object_id = (int) $menu_item->term_id;
		$menu_item->title = $menu_item->name;
	}
	
	return $menu_item;
}