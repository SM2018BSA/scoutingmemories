<?php
/**
 * ScoutingMemories functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package ScoutingMemories
 */

if ( ! defined( '_S_VERSION' ) ) {
    // Replace the version number of the theme on each release.
    define( '_S_VERSION', '1.0.0' );
}

// hide the admin bar on the front end when NOT logged in!
add_filter('show_admin_bar', '__return_false');





require_once get_template_directory() . '/inc/formidable_constants.php';

require_once get_template_directory() . '/Classes/CurrentUser.php';
require_once get_template_directory() . '/Classes/AdvancedCustomField.php';
require_once get_template_directory() . '/Classes/Theme.php';
require_once get_template_directory() . '/Classes/Entry.php';
require_once get_template_directory() . '/Classes/View.php';
require_once get_template_directory() . '/Classes/Form.php';
require_once get_template_directory() . '/Classes/SearchForm.php';
require_once get_template_directory() . '/Classes/Taxonomy.php';
require_once get_template_directory() . '/Classes/NewUserEntry.php';
require_once get_template_directory() . '/Classes/StateEntry.php';
require_once get_template_directory() . '/Classes/CouncilEntry.php';
require_once get_template_directory() . '/Classes/CouncilView.php';
require_once get_template_directory() . '/Classes/LodgeView.php';
require_once get_template_directory() . '/Classes/LodgeEntry.php';
require_once get_template_directory() . '/Classes/CampView.php';
require_once get_template_directory() . '/Classes/CampEntry.php';




require_once get_template_directory() . '/Classes/Query.php';
require_once get_template_directory() . '/Classes/Indexing.php';
require_once get_template_directory() . '/Classes/AppHelper.php';
require_once get_template_directory() . '/Classes/Database.php';

require_once get_template_directory() . '/Classes/PostEntry.php';
require_once get_template_directory() . '/Classes/Post.php';



//$FrmEntryMeta = new FrmEntryMeta;
//$FrmAppHelper = new FrmAppHelper;
//$FrmDb = new FrmDb;


global $theme;

$theme = new Theme;
$theme->setup_hooks();
$theme->addNavMenus([
    'primary' => 'Primary Menu',
]);








if (!function_exists('debug_to_console')) :
	function debug_to_console($data)
	{
		$output = $data;
		if (is_array($output))
			$output = implode(',', $output);
		echo "<script>console.log( 'Debug Objects: " . $output . "' );</script>";
	}
endif;





if (!function_exists('get_request_parameter')) :
/**
 * Gets the request parameter.
 *
 * @param      string  $key      The query parameter
 * @param      string  $default  The default value to return if not found
 *
 * @return     string  The request parameter.
 */
function get_request_parameter( $key, $default = null ) {
	// If not request set
	if ( ! isset( $_REQUEST[ $key ] ) || empty( $_REQUEST[ $key ] ) ) {
		return $default;
	}

	// Set so process it
	return strip_tags( (string) wp_unslash( $_REQUEST[ $key ] ) );
}
endif;




//Debugging utilities
if (!function_exists('clean')) :
	function clean($string)
	{
		$string = str_replace(' ', '_', $string); // Replaces all spaces with underscores.
		$string = str_replace('(', '_', $string); // Replaces all spaces with underscores.
		$string = str_replace(')', '_', $string); // Replaces all spaces with underscores.
		$string = str_replace('&amp;', '_and_', $string); // Replaces all spaces with underscores.
		return preg_replace('/[^A-Za-z0-9\-]/', '_', $string); // Removes special chars.
	}
endif;













 // load formidable field hooks
$the_user      = new CurrentUser();

$new_user     = new NewUserEntry();
$new_user->setup_hooks();

$council       = new CouncilEntry();
$council->setup_hooks();

$lodge         = new LodgeEntry();
$lodge->setup_hooks();

$camp          = new CampEntry();
$camp->setup_hooks();

$addPost       = new PostEntry(0 , $the_user);
$addPost->setup_hooks();

$councilsView = new CouncilView (ALL_COUNCILS_VIEWID);
$councilsView->setup_hooks();

$lodgesView   = new LodgeView (ALL_LODGES_VIEWID);
$lodgesView->setup_hooks();

$campsView    = new CampView (ALL_CAMPS_VIEWID);
$campsView->setup_hooks();;


$search_form = new SearchForm();
$search_form->setup_hooks();




















/**
 * Custom template tags for this theme.
 */
require get_template_directory() . '/inc/template-tags.php';

/**
 * Functions which enhance the theme by hooking into WordPress.
 */
require get_template_directory() . '/inc/template-functions.php';


/**
 * Proper ob_end_flush() for all levels
 *
 * This replaces the WordPress `wp_ob_end_flush_all()` function
 * with a replacement that doesn't cause PHP notices.
 * https://www.kevinleary.net/wordpress-ob_end_flush-error-fix/
 */
remove_action( 'shutdown', 'wp_ob_end_flush_all', 1 );
add_action( 'shutdown', function() {
	while ( @ob_end_flush() );
} );
