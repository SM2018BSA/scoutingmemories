<?php
global $pp_constants;
$pp_constants = array();

$type = 'filtering-switches';
$consts = array(
'PP_RESTRICTION_PRIORITY' => 						__( "Force exclusions ('Not these' / 'Blocked') to take priority over additions ('Also these' / 'Enabled')", 'pp' ),
'PP_GROUP_RESTRICTIONS' => 							__( "'Not these' / 'Blocked' exceptions (mod_type='exclude') can be applied to custom-defined groups", 'pp' ),
'PP_ALL_ANON_ROLES' => 								__( "Supplemental roles assignment available for {All} and {Anonymous} metagroups", 'pp' ),
'PP_ALL_ANON_FULL_EXCEPTIONS' => 					__( "Allow the {All} and {Anonymous} metagroups to be granted reading exceptions for private content", 'pp' ),
'PP_EDIT_EXCEPTIONS_ALLOW_DELETION' => 				__( "PRO: Users who have an editing exception for a post or attachment can also delete it", 'pp' ),
'PP_EDIT_EXCEPTIONS_ALLOW_ATTACHMENT_DELETION' => 	__( "PRO: Users who have an editing exception for an attachment can also delete it", 'pp' ),
'PP_DISABLE_QUERYFILTERS' => 						__( "Don't apply any content restrictions", 'pp' ),
'PP_ALLOW_UNFILTERED_FRONT' => 						__( "Disable front end filtering if logged user is a content administrator (normally filter to force inclusion of readable private posts in get_pages() listing, post counts, etc.", 'pp' ),
'PP_UNFILTERED_FRONT' => 							__( "Disable front end filtering for all users (subject to limitation by PP_UNFILTERED_FRONT_TYPES)", 'pp' ),
'PP_UNFILTERED_FRONT_TYPES' => 						__( "Comma-separated list of post types to limit the effect of PP_UNFILTERED_FRONT and apply_filters( 'pp_skip_cap_filtering' )", 'pp' ),
'PP_NO_ADDITIONAL_ACCESS' => 						__( "'Also these' / 'Enabled' exceptions (mod_type='additional') are not applied (and cannot be assigned)", 'pp' ),
'PP_POST_NO_EXCEPTIONS' =>	 						__( "Don't assign or apply exceptions for the 'post' type", 'pp' ),
'PP_PAGE_NO_EXCEPTIONS' => 							__( "Don't assign or apply exceptions for the 'page' type", 'pp' ),
'PP_MEDIA_NO_EXCEPTIONS' => 						__( "Don't assign or apply exceptions for the 'media' type", 'pp' ),
'PP_MY_CUSTOM_TYPE_NO_EXCEPTIONS' => 				__( "Don't assign or apply exceptions for the specified custom post type", 'pp' ),
);
foreach( $consts as $k => $v ) $pp_constants[$k] = (object) array( 'descript' => $v, 'type' => $type );

$type = 'front-end';
$consts = array(
'PP_FUTURE_POSTS_BLOGROLL' => 		__( "Include scheduled posts in the posts query if user can edit them", 'pp' ),
'PP_UNFILTERED_TERM_COUNTS' => 		__( "Don't filter term post counts in get_terms() call", 'pp' ),
'PP_DISABLE_NAV_MENU_FILTER' => 	__( "Leave unreadable posts on WP Navigation Menus", 'pp' ),
'PP_NAV_MENU_SHOW_EMPTY_TERMS' => 	__( "Leave terms with no readable posts on WP Navigation Menus", 'pp' ),
);
foreach( $consts as $k => $v ) $pp_constants[$k] = (object) array( 'descript' => $v, 'type' => $type );

$type = 'get-pages';
$consts = array(
'PP_GET_PAGES_PRIORITY' => 		__( "Filter priority for 'get_pages' filter (default: 1)", 'pp' ),
'PP_SUPPRESS_PRIVATE_PAGES' => 	__( "Don't include readable private pages in the Pages widget or other wp_list_pages() / get_pages() results	", 'pp' ),
'PPC_FORCE_PAGE_REMAP' => 		__( "If some pages have been suppressed from get_pages() results, change child pages' corresponding post_parent values to a visible ancestor", 'pp' ),
'PPC_NO_PAGE_REMAP' => 			__( "Never modify the post_parent value in the get_pages() result set, even if some pages have been suppressed", 'pp' ),
'PP_GET_PAGES_LEAN' => 			__( "For performance, change the get_pages() database query to return only a subset of fields, excluding post_content", 'pp' ),
'PP_TEASER_HIDE_PAGE_LISTING' =>__( "PRO: Don't apply content teaser to get_pages() results (leave unreadable posts hidden)", 'pp' ),
);
foreach( $consts as $k => $v ) $pp_constants[$k] = (object) array( 'descript' => $v, 'type' => $type );

$type = 'get-terms';
$consts = array(
'PPC_FORCE_TERM_REMAP' => 	__( "If some terms have been suppressed from get_terms() results, change child terms' corresponding parent values to a visible ancestor", 'pp' ),
'PPC_NO_TERM_REMAP' => 		__( "Never modify the parent value in the get_terms() result set, even if some terms have been suppressed", 'pp' ),
);
foreach( $consts as $k => $v ) $pp_constants[$k] = (object) array( 'descript' => $v, 'type' => $type );

$type = 'media';
$consts = array(
'PP_MEDIA_LIB_UNFILTERED' => 		__( "Leave Media Library with normal access criteria based on user's role capabilities ", 'pp' ),
);
foreach( $consts as $k => $v ) $pp_constants[$k] = (object) array( 'descript' => $v, 'type' => $type );

$type = 'admin';
$consts = array(
'PP_USERS_UI_GROUP_FILTER_LINK' => 	__( "On Users listing, PP groups in custom column are list filter links instead of group edit links", 'pp' ),
'PP_ADMIN_READONLY_LISTABLE' => 	__( "Unlock Permissions > Settings > Core > Admin Back End > 'Hide non-editable posts'", 'pp' ),
'PP_UPLOADS_FORCE_FILTERING' => 	__( "Within the async-upload.php script, filtering author's retrieval of the attachment they just uploaded", 'pp' ),
'PP_NO_COMMENT_FILTERING' => 		__( "Don't filter comment display or moderation within wp-admin", 'pp' ),
);
foreach( $consts as $k => $v ) $pp_constants[$k] = (object) array( 'descript' => $v, 'type' => $type );

$type = 'permissions-admin';
$consts = array(
'PP_DISABLE_BULK_ROLES' => "",
'PP_FORCE_EXCEPTION_OVERWRITE' => 	__( "If propagating exceptions are assigned to a page branch, overwrite any explicitly assigned exceptions in sub-pages", 'pp' ),
'PP_EXCEPTIONS_MAX_INSERT_ROWS' => 	__( "Max number of exceptions to insert in a single database query (default 1000)", 'pp' ),
'PP_DISABLE_MENU_TWEAK' => 			__( "Don't tweak the admin menu indexes to position Permissions menu under Users", 'pp' ),
'PP_FORCE_USERS_MENU' => 			__( "Don't add a Permissions menu. Instead, add menu items to the Users and Settings menus.", 'pp' ),
);
foreach( $consts as $k => $v ) $pp_constants[$k] = (object) array( 'descript' => $v, 'type' => $type );

$type = 'permission-groups-ui';
$consts = array(
'PP_GROUPS_CAPTION' => 					__( "Customize 'Permission Groups' caption", 'pp' ),
'GROUPS_CAPTION_RS' => 					__( "Customize 'Permission Groups' caption on user profile", 'pp' ),
'PP_GROUPS_HINT' => 					__( "Customize description under 'Permission Groups' caption ", 'pp' ),
'PP_ITEM_MENU_PER_PAGE' => 				__( "Max number of non-hierarchical posts / terms to display at one time (per page)", 'pp' ),
'PP_ITEM_MENU_HIERARCHICAL_PER_PAGE' => __( "Max number of hierarchical posts / terms to display at one time (per page)", 'pp' ),
'PP_ITEM_MENU_FORCE_DISPLAY_DEPTH' => 	__( "Disable auto-determination of how many levels of page tree to make visble by default. Instead, use specified value.", 'pp' ),
'PP_ITEM_MENU_DEFAULT_MAX_VISIBLE' => 	__( "Target number of visible pages/terms, used for auto-determination of number of visible levels", 'pp' ),
'PP_ITEM_MENU_SEARCH_CONTENT' => 		__( "Make search function on the post selection metabox look at post content", 'pp' ),
'PP_ITEM_MENU_SEARCH_EXCERPT' => 		__( "Make search function on the post selection metabox look at post excerpt", 'pp' ),
);
foreach( $consts as $k => $v ) $pp_constants[$k] = (object) array( 'descript' => $v, 'type' => $type );

$type = 'user-selection';
$consts = array(
'PP_USER_LASTNAME_SEARCH' => 		__( "Search by last name instead of display name", 'pp' ),
'PP_USER_SEARCH_FIELD' => 			__( "User field to search by default", 'pp' ),
'PP_USER_SEARCH_META_FIELDS' => 	__( "User meta fields selectable for search (comma-separated)", 'pp' ),
'PP_USER_SEARCH_NUMERIC_FIELDS' => 	__( "User meta fields which should be treated as numeric (comma-separated)", 'pp' ),
'PP_USER_SEARCH_BOOLEAN_FIELDS' => 	__( "User meta fields which should be treated as boolean (comma-separated)", 'pp' ),
'PP_USER_RESULTS_DISPLAY_NAME' => 	__( "Use display name for search results instead of user_login", 'pp' ),
);
foreach( $consts as $k => $v ) $pp_constants[$k] = (object) array( 'descript' => $v, 'type' => $type );

$type = 'user-sync';
$consts = array(
'PP_SKIP_USER_SYNC' => 				__( "Don't auto-assign role metagroups for all users. Instead, assign per-user at first login.", 'pp' ),
'PP_AUTODELETE_ROLE_METAGROUPS' => 	__( "When synchronizing role metagroups to currently defined WP roles, don't delete groups for previously defined WP roles.", 'pp' ),
);
foreach( $consts as $k => $v ) $pp_constants[$k] = (object) array( 'descript' => $v, 'type' => $type );

$type = 'force-pp-settings';
$consts = array(
'PP_FORCE_DYNAMIC_ROLES' => __( "Force detection of WP user roles which are appended dynamically but not stored to the WP database.", 'pp' ),
);
foreach( $consts as $k => $v ) $pp_constants[$k] = (object) array( 'descript' => $v, 'type' => $type );

$type = 'perf';
$consts = array(
'PP_NO_FRONTEND_ADMIN' => 			__( "To save memory on front end access, don't register any filters related to content editing", 'pp' ),
'PP_NO_ATTACHMENT_COMMENTS' => 		__( "Attached media do not have any comments, so don't append clauses to comment queries for them", 'pp' ),
'PP_LEAN_PAGE_LISTING' => 			__( "Reduce overhead of pages query (in get_pages() and wp-admin) by defaulting fields to a set list that does not include post_content ", 'pp' ),
'PP_LEAN_POST_LISTING' => 			__( "Reduce overhead of wp-admin posts query by defaulting fields to a set list that does not include post_content ", 'pp' ),
'PP_LEAN_MEDIA_LISTING' => 			__( "Reduce overhead of wp-admin Media query by defaulting fields to a set list that does not include post_content ", 'pp' ),
'PP_LEAN_MY_CUSTOM_TYPE_LISTING' => __( "Reduce overhead of wp-admin query for specified custom post type by defaulting fields to a set list that does not include post_content ", 'pp' ),
);
foreach( $consts as $k => $v ) $pp_constants[$k] = (object) array( 'descript' => $v, 'type' => $type );

$type = 'wp-compat';
$consts = array(
'PP_UNFILTERED_PAGE_URI' => __( "Don't restore pre-4.4 behavior of not requiring 'publish' status for inclusion in page uri hierarchy", 'pp' ),
);
foreach( $consts as $k => $v ) $pp_constants[$k] = (object) array( 'descript' => $v, 'type' => $type );


if ( defined( "RVY_VERSION" ) ) {
$type = 'third-party';
$consts = array(
'SCOPER_DEFAULT_MONITOR_GROUPS' => "",
'PP_DEFAULT_MONITOR_GROUPS' => "",
);
foreach( $consts as $k => $v ) $pp_constants[$k] = (object) array( 'descript' => $v, 'type' => $type );
}

$type = 'support';
$consts = array(
'PPI_LEGACY_UPLOAD' => "",
'PPI_ERROR_LOG_UPLOAD_LIMIT' => "",
);
foreach( $consts as $k => $v ) $pp_constants[$k] = (object) array( 'descript' => $v, 'type' => $type, 'suppress_display' => true );

$type = 'debug-dev';
$consts = array(
'PP_DEBUG' => "",
'PP_DEBUG_LOGFILE' => "",
'PP_MEMORY_LOG' => "",
'AGP_NO_USAGE_MSG' => "",
'PP_DEBUG_ACTIVATE_KEY' => "",
'PP_DEBUG_DEACTIVATE_KEY' => "",
'PP_DEBUG_UPDATE_CHECK_PPC' => "",
'PP_DEBUG_EXT_INFO' => "",
'PP_DEBUG_CHANGELOG_PPC' => "",
'PP_DEBUG_CONFIG_CHECK' => "",
'PP_DEBUG_CONFIG_UPLOAD' => "",
'PP_FORCE_PPCOM_INFO' => "",
'PP_DISABLE_CAP_CACHE' => "",
'PP_FILTER_JSON_REST' => "",
'PP_DISABLE_UNFILTERED_TYPES_CLAUSE' => __( "Development use only (suppresses post_status = 'publish' clause for unfiltered post types with anonymous user)", 'pp' ),
'PP_RETAIN_PUBLISH_FILTER' => 			__( "Development use only (on front end, do not replace 'post_status = 'publish'' clause with filtered equivalent)", 'pp' ),
'PP_GET_TERMS_SHORTCUT' => "",
'PP_LEGACY_HTTP_REDIRECT' => "",
'PP_AGENTS_CAPTION_LIMIT' => "",
'PP_AGENTS_EMSIZE_THRESHOLD' => "",
'PP_UI_EMS_PER_CHARACTER' => "",
);
foreach( $consts as $k => $v ) $pp_constants[$k] = (object) array( 'descript' => $v, 'type' => $type, 'suppress_display' => true );

$type = 'status';
$consts = array(
'PP_MULTISITE' => "",
'INIT_ACTION_DONE_PP' => "",
'PP_ENABLE_QUERYFILTERS' => "",
);
foreach( $consts as $k => $v ) $pp_constants[$k] = (object) array( 'descript' => $v, 'type' => $type, 'suppress_display' => true );


$pp_constants = apply_filters( 'pp_constants', $pp_constants );

global $pp_constants_by_type;
$pp_constants_by_type = array();

global $pp_constant_types;
$pp_constant_types = array();
foreach( $pp_constants as $name => $const ) {
	if ( empty( $const->suppress_display ) ) {
		if ( ! isset( $pp_constant_types[ $const->type ] ) ) {
			$pp_constant_types[ $const->type ] = ucwords( str_replace( '-', ' ', $const->type ) );
			
			foreach( array( '-' => ' ', 'Pp' => 'PP', 'Wp' => 'WP', 'Ui' => 'UI' ) as $find => $repl )
				$pp_constant_types[ $const->type ] = ucwords( str_replace( $find, $repl, $pp_constant_types[ $const->type ] ) );
		}
		
		if ( ! isset( $pp_constants_by_type[ $const->type ] ) ) $pp_constants_by_type[ $const->type ] = array();
		
		$pp_constants_by_type[ $const->type ][]= $name;
	}
}
$pp_constant_types = apply_filters( 'pp_constant_types', $pp_constant_types );
