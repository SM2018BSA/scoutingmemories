<?php
/**
 * Plugin Name: WP Users Media
 * Plugin URI: -
 * Description: WP Users Media is a WordPress plugin that displays only the current users media files and attachments in WP Admin.
 * Version: 4.1.0
 * Author: Damir Calusic
 * Author URI: https://www.damircalusic.com/
 * License: GPLv2
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */
/*  Copyright (C) 2019  Damir Calusic (email : damir@damircalusic.com)
	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as 
	published by the Free Software Foundation.
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/** 
 * Define the version of the plugin 
 * 
 * @since 1.0.0
 */
define('WPUSERSMEDIA_VERSION', '4.1.0');

/**
 * Load plugin languages 
 *
 * @since 1.0.0
 */
load_plugin_textdomain('wpusme', false, basename(dirname(__FILE__)).'/languages');

/**
 * Backend scripts and styles
 * 
 * @since 4.1.0
 */
function wpusme_backend_scripts_and_styles(){
    wp_enqueue_style('wpusme-style', plugin_dir_url(__FILE__).'css/wpusme.css', WPUSERSMEDIA_VERSION);
}

/** 
 * Add menu items to the WP Admin menues 
 *	
 * @since 4.1.0
 */
function wpusme_menu(){
	add_action('admin_init', 'register_wpusme_settings');
	add_submenu_page('options-general.php', 'WP Users Media', 'WP Users Media', 'manage_options', 'wpusme_settings_page', 'wpusme_settings_page');

	// Add a shortcut in the main menu
	if(get_option('wpusmesidemenu') == '1'){ 
		add_menu_page('WP Users Media', 'WP Users Media', 'manage_options', __FILE__, 'wpusme_settings_page', 'dashicons-images-alt', 15);
	}
}

/**
 * 	Register option settings for the plugin 
 * 
 * 	@since 2.0.0
 */
function register_wpusme_settings(){
	global $wp_roles;
	$roles = wpusme_check_if_object_isset($wp_roles->get_names());
	
	register_setting('wpusme-settings-group', 'wpusmesidemenu');
	register_setting('wpusme-settings-group', 'wpusmeadminself');
	
	if(is_array($roles)){
		foreach($roles as $role => $name){
			if($role !== 'administrator'){
				register_setting('wpusme-settings-group', 'wpusme'.$role.'self');
			}
		}
	}
}

/** 
 * Display the options/settings page for the site user
 *
 * @since 4.1.0
 */
function wpusme_settings_page(){
	global $wp_roles;
	$roles = wpusme_check_if_object_isset($wp_roles->get_names());
?>
    <form id="wpusme_elements_form" method="post" action="options.php">
        <?php settings_fields('wpusme-settings-group'); ?>
		<div class="wrap">
            <div id="welcome-panel" class="welcome-panel">
                <label id="version"><?php _e('Version','wpusme'); ?> <?php echo WPUSERSMEDIA_VERSION; ?></label>
                <div class="welcome-panel-content">
                    <h1><?php _e('WP Users Media','wpbe'); ?></h1>
                    <p class="about-description"><?php _e('When you change something do not forget to click on this blue Save Changes button below this text.','wpusme'); ?></p>
                    <p class="submit">
                        <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e('Save Changes','wpusme'); ?>">
                        <a class="button button-secondary" href="https://twitter.com/damircalusic/" target="_blank"><?php _e('FOLLOW ON TWITTER','wpusme'); ?></a>
                        <a class="button button-secondary" href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=AJABLMWDF4RR8&source=url" target="_blank"><?php _e('DONATE TO SUPPORT','wpusme'); ?></a>
                    </p>
                </div>
            </div>
		</div>
		<div class="wrap">
            <div class="nav-tab-wrapper wp-clearfix">
                <a href="#" class="nav-tab nav-tab-active" data-id="wpgeneral"><?php _e('General Options','wpusme'); ?></a>
                <a href="#" class="nav-tab" data-id="wproles"><?php _e('User Roles','wpbe'); ?></a>
			</div>
			<table id="wpgeneral" class="widefat striped">
                <tbody>
                <tr>
                    <td colspan="2">
                        <div class="notice notice-info inline"><p><?php _e('General basic options.','wpbe'); ?></p></div>
                    </td>
                </tr>
                <tr>
                    <td><?php _e('Add shortcut for WP Users Media in the sidebar menu.','wpusme'); ?></td>
                    <td>
                        <label class="switch">
							<input id="id1" type="checkbox" name="wpusmesidemenu" value="1" <?php echo checked(1, get_option('wpusmesidemenu'), false); ?> />
                            <span for="id1" class="slider"></span>
                        </label>
                    </td>
				</tr>
				<tr>
                    <td><?php _e('Enable so Admins can only view their own attachments.','wpusme'); ?></td>
                    <td>
                        <label class="switch">
							<input id="id2" type="checkbox" name="wpusmeadminself" value="1" <?php echo checked(1, get_option('wpusmeadminself'), false); ?> />
                            <span for="id2" class="slider"></span>
                        </label>
					</td>
                </tr>
                </tbody>
            </table>
            <table id="wproles" class="widefat striped">
                <tbody>
                <tr>
                    <td colspan="2">
                        <div class="notice notice-info inline"><p><?php _e('Show the own attachments only for the users with following roles listed below. To display own attachments for all roles below just leave them unchecked.','wpusme'); ?></p></div>
                    </td>
				</tr>
				<?php  
					if(is_array($roles)){
						$i == 100;
						foreach($roles as $role => $name){
							if($role !== 'administrator'){
					?>
					<tr>
						<td><?php echo $name; ?></td>
						<td>
							<label class="switch">
								<input id="id<?php echo $i; ?>" type="checkbox" name="wpusme<?php echo $role; ?>self" value="1" <?php echo checked(1, get_option('wpusme'.$role.'self'), false); ?> />
								<span for="id<?php echo $i; ?>" class="slider"></span>
							</label>
						</td>
					</tr>
					<?php $i++;
							}
						}
					}
				?>
                </tbody>
            </table>
		</div>
	</form>
	<script>
        jQuery(document).ready(function($){ 
            $('.nav-tab-wrapper a').click(function(e){ 
                e.preventDefault();
                $('.nav-tab-wrapper a').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                $('table').hide();
                $('table#' + $(this).attr("data-id")).show(); 
            }); 
        });
    </script>
<?php 
}

/**
 * Filter attachments for the specific user
 * 
 * @since 3.0.0 
 */
function wpusme_filter_media_files($wp_query){
	// Make sure the user is logged in first
	if(is_user_logged_in()){
		global $current_user;
		global $wp_roles;
		$wp_query = $wp_query;
		$roles = wpusme_check_if_object_isset($wp_roles->get_names());

		// Check so the $wp_query->query['post_type'] isset and that we are on the attachment page in admin
		if(isset($wp_query->query['post_type']) && (is_admin() && $wp_query->query['post_type'] === 'attachment')){
			
			//  Display the admins attachments only for the admin self.
			if(get_option('wpusmeadminself') == '1'){
				if(current_user_can('manage_options')){
					$wp_query->set('author', $current_user->ID);
				}
			}
			
			// Check if we have checked a role and display attachments only for the users in the role only
			if(!current_user_can('manage_options')){
				$count_roles = 0;
				
				if(is_array($roles)){
					foreach($roles as $role => $name){
						if(get_option('wpusme'.$role.'self') !== null && get_option('wpusme'.$role.'self') == 1){
							$count_roles++;
							
							if($role == $current_user->roles[0]){
								$wp_query->set('author', $current_user->ID);
							}
						}
					}
				}
			
				// Default setting; All users can view only their own attachments except Admin
				if($count_roles == 0){
					$wp_query->set('author', $current_user->ID);
				}
			}
		}
	}
}

/** 
 * Recount attachments for the specific user 
 * 	
 * @since 2.0.0
 */
function wpusme_recount_attachments($counts_in){
	global $wpdb;
	global $current_user;

	$and = wp_post_mime_type_where(''); // Default mime type // AND post_author = {$current_user->ID}
	$count = $wpdb->get_results("SELECT post_mime_type, COUNT(*) AS num_posts FROM $wpdb->posts WHERE post_type = 'attachment' AND post_status != 'trash' AND post_author = {$current_user->ID} $and GROUP BY post_mime_type", ARRAY_A);

	$counts = array();
	
	foreach((array)$count as $row){
		$counts[$row['post_mime_type']] = $row['num_posts'];
	}

	$counts['trash'] = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = 'attachment' AND post_author = {$current_user->ID} AND post_status = 'trash' $and");

	return $counts;
};

/**	
 * Checks if the object isset
 * 	
 * @since 4.0.2 
 */
function wpusme_check_if_object_isset($object){
	return ($object !== null) ? $object : '';
}

// Add Actions
add_action('admin_head', 'wpusme_backend_scripts_and_styles');
add_action('admin_menu', 'wpusme_menu');
add_action('pre_get_posts', 'wpusme_filter_media_files');

/* Add Filters*/
//add_filter('wp_count_attachments', 'wpusme_recount_attachments');
//return apply_filters( 'wp_count_attachments', (object) $counts, $mime_type );