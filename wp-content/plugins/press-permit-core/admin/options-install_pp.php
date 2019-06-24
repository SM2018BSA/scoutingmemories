<?php
class PP_Options_Install {
	function __construct() {
		add_filter( 'pp_option_tabs', array(&$this, 'option_tabs'), 0 );
		add_filter( 'pp_section_captions', array(&$this, 'section_captions' ) );
		add_filter( 'pp_option_captions', array(&$this, 'option_captions' ) );
		add_filter( 'pp_option_sections', array(&$this, 'option_sections' ) );

		add_action( 'pp_install_options_pre_ui', array( &$this, 'options_pre_ui' ) );
		add_action( 'pp_install_options_ui', array( &$this, 'options_ui' ) );
		
		//add_action( 'admin_print_footer_scripts', array( &$this, 'footer_js' ) );
	}
	
	function option_tabs( $tabs ) {
		$tabs['install'] = __( 'Install', 'pp' );
		return $tabs;
	}

	function section_captions( $sections ) {
		$new = array(
			'key' =>			__('Support Key', 'pp'),
			'version' =>		__('Version', 'pp'),
			'extensions' =>		__('Extensions', 'pp'),
			'beta_updates' =>	__('Beta Updates', 'pp'),
			'help' =>			_pp_('Help'),
		);

		$key = 'install';
		$sections[$key] = ( isset($sections[$key]) ) ? array_merge( $sections[$key], $new ) : $new;
		return $sections;
	}

	function option_captions( $captions ) {
		$opt = array( 
			'key' => __('settings', 'pp'),
			'ppcom_update_info' => __('Receive update info from presspermit.com', 'pp'),
			'beta_updates' => __('Receive beta version updates for extensions', 'pp'),
			'help' => __('settings', 'pp'),
		);
		
		return array_merge($captions, $opt);
	}

	function option_sections( $sections ) {
		$new = array(
			'key' => array( 'support_key' ),
			'beta_updates' => array( 'beta_updates' ),
			'help' => array( 'no_option' ),
			'extensions' => array( 'no_option' ),
			'version' => array( 'ppcom_update_info' ),
		);
		
		$key = 'install';
		$sections[$key] = ( isset($sections[$key]) ) ? array_merge( $sections[$key], $new ) : $new;
		return $sections;
	}
	
	function options_pre_ui() {
		/*
		global $pp_options_ui;
		
		if ( $pp_options_ui->display_hints ) {
			echo '<div class="pp-optionhint">';
			_e("These <strong>optional</strong> settings allow advanced users to adjust Press Permit's sphere of influence. For most installations, the default settings are fine.", 'pp');
			echo '</div>';
		}
		*/
		
		if ( isset($_REQUEST['pp_config_uploaded']) && empty($_POST) ) : ?>
		<div id="message" class="updated"><p>
		<strong><?php _e('Configuration data was uploaded.', 'pp'); ?>&nbsp;</strong>
		</p></div>
		<?php elseif ( isset($_REQUEST['pp_config_no_change']) && empty($_POST) ) : ?>
		<div id="message" class="updated error"><p>
		<strong><?php _e('Configuration data is unchanged since last upload.', 'pp'); ?>&nbsp;</strong>
		</p></div>
		<?php elseif ( isset($_REQUEST['pp_config_failed']) && empty($_POST) ) : ?>
		<div id="message" class="error"><p>
		<strong><?php _e('Configuration data could not be uploaded.', 'pp'); ?>&nbsp;</strong>
		</p></div>
		<?php endif;
		
		if ( isset($_REQUEST['pp_refresh_done']) && empty($_POST) ) : ?>
		<div id="message" class="updated"><p>
		<strong><?php _e('Version info was refreshed.', 'pp'); ?>&nbsp;</strong>
		</p></div>
		<?php endif;
	}
	
	function options_ui() {
		global $pp_options_ui;
		$ui = $pp_options_ui; // shorten syntax
		$tab = 'install';
		
		$ppcom_connect = pp_update_info_enabled();
		$use_network_admin = pp_use_network_updates();
		$suppress_updates = $use_network_admin && ! is_super_admin();
		
		$section = 'key';									// --- UPDATE KEY SECTION ---
		if ( ! empty( $ui->form_options[$tab][$section] ) && ! $suppress_updates ) : ?>
			<tr><td scope="row" colspan="2"><span style="font-weight:bold;vertical-align:top"><?php echo $ui->section_captions[$tab][$section]; ?></span>
			<?php
				global $activated;
			
				if ( $ppcom_connect ) {
					require_once( dirname(__FILE__).'/plugin_pp.php' );
					PP_Plugin_Status::get_version_info();
				}
				
				$id = 'support_key';
				$opt_val = pp_get_option( $id );
				
				if ( ! is_array($opt_val) || count($opt_val) < 2 ) {
					$activated = false;
					$expired = false;
					$key = '';
				} else {
					$activated = ( 1 == $opt_val[0] );
					$expired = ( -1 == $opt_val[0] );
					$key = $opt_val[1];
				}
				
				if ( isset( $opt_val['expire_date_gmt'] ) ) {
					$expire_days = intval( ( strtotime($opt_val['expire_date_gmt']) - time() ) / 86400 );
				}

				if ( $expired ) {
					$class = 'activating';
					$is_err = true;
					$msg = sprintf( __( 'Your support key has expired. For continued priority support, <a href="%s">please renew</a>.', 'pp' ), 'admin.php?page=pp-settings&amp;pp_renewal=1' );
				} elseif ( ! empty( $opt_val['expire_date_gmt'] ) ) {
					$class = 'activating';
					if ( $expire_days < 30 ) $is_err = true;
					if ( $expire_days < 1 )
						$msg = sprintf( __( 'Your support key will expire today. For updates to extension plugins and priority support, <a href="%s">please renew</a>.', 'pp' ), $expire_days, 'admin.php?page=pp-settings&amp;pp_renewal=1' );
					elseif ( $expire_days < 30 )
						$msg = sprintf( _n( 'Your support key will expire in %d day. For updates to extension plugins and priority support, <a href="%s">please renew</a>.', 'Your support key (for plugin updates) will expire in %d days. For updates to extension plugins and priority support, <a href="%s">please renew</a>.', $expire_days, 'pp' ), $expire_days, 'admin.php?page=pp-settings&amp;pp_renewal=1' );
					else
						$class = "activating hidden";
				} elseif ( ! $activated ) {
					$class = 'activating';
					$msg = sprintf( __( 'Activate your support key for Press Permit extensions and priority support. Available at <a href="%s">presspermit.com</a>.', 'pp' ), 'https://presspermit.com/' . 'purchase/' );
				
					$msg = sprintf( __( 'For priority support and Pro extensions, activate your support key. If you need a key, <a href="%s" target="_blank">Explore Pro packages</a>.', 'pp' ), 'https://presspermit.com/purchase/' );
				} else {
					$class = "activating hidden";
					$msg = '';
				}	
				?>
				
					<?php if ( $expired && ( ! empty($key) ) ) : ?>
						<span class="pp-key-normal">
						<span class="pp-key-expired"><?php _e("Key Expired", 'pp') ?></span>
						<input name="<?php echo($id);?>" type="text" id="<?php echo($id);?>" style="display:none" />
						<button type="button" id="activation-button" name="activation-button" class="button-secondary"><?php _e('Deactivate Key','pp'); ?></button>
					<?php else : ?>
						<span class="pp-key-normal">
						<?php if ( $activated ) : ?>
						<span class="pp-key-active"><?php _e("Key Activated", 'pp') ?></span>
						<?php endif ?>
						<input name="<?php echo($id);?>" type="text" id="<?php echo($id);?>" maxlength="40" <?php echo ($activated)?' style="display:none"':''; ?> />
						<button type="button" id="activation-button" name="activation-button" class="button-secondary"><?php echo ( ! $activated)?__('Activate Key','pp'):__('Deactivate Key','pp'); ?></button>
					<?php endif; ?>
					
					<img id="pp_support_waiting" class="waiting" style="display:none;position:relative" src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) )?>" alt="" />

					<span class="pp-key-refresh">
					&nbsp;&nbsp;<a href="admin.php?page=pp-settings&amp;pp_refresh_updates=1"><?php _e('refresh status', 'pp');?></a>
					&nbsp;&bull;&nbsp;&nbsp;<a href="https://presspermit.com/purchase/account" target="_blank"><?php _e('review your key(s)', 'pp');?></a>
					</span>
					
				</span>
				
					<?php if ( $activated ) : ?>
						<?php if ( $expired ) : ?>
						<div class="pp-key-hint-expired">
						<span class="pp-key-expired pp-key-warning"> <?php _e('note: Renewal does not require deactivation. If you do deactivate, re-entry of the support key will be required.', 'pp'); ?></span>
						</div>
						<?php elseif( pp_get_option( 'display_hints' ) ) :?>
						<div class="pp-key-hint">
						<span class="pp-subtext"> <?php _e('note: If you deactive, re-entry of the support key will be required for re-activation.', 'pp'); ?></span>
						<?php endif;?>
					</div>
						
					<?php elseif ( ! $expired ) : ?>
					<div class="pp-key-hint">
					<span class="pp-subtext"> <?php _e('note: Your site URL and version info will be sent to presspermit.com', 'pp'); ?></span>
					</div>
					<?php endif ?>
				
				<div id="activation-status" class="<?php echo $class?>"><?php echo $msg;?></div><div class="pp-settings-caption" style="display:none;"><a href="<?php echo admin_url('admin.php?page=pp-settings');?>"><?php _e('reload extension info', 'pp');?></a></div>
				
				<?php if ( ! empty($is_err) ) : ?>
				<div id="activation-error" class="error"><?php echo $msg;?></div>
				<?php endif; ?>
				
				<?php 
				if ( ! $activated || $expired ) {
					require_once( dirname(__FILE__).'/pro-promo_pp.php' );
				}
				?>
			</td></tr>
		<?php 
			do_action( 'pp_support_key_ui' );
			self::footer_js( $activated, $expired );		
		endif; // any options accessable in this section

		
		$section = 'version';								// --- VERSION SECTION ---
		if ( ! empty( $ui->form_options[$tab][$section] ) ) :
			?>
			<tr>
			<th scope="row"><?php echo $ui->section_captions[$tab][$section]; ?></th>
			<td>
			
			<?php
			if ( $ppcom_connect )
				$update_info = pp_get_all_updates_info( ! empty($_REQUEST['pp_refresh_updates']) );
			else
				$update_info = array();
				
			$info_link = '';
			
			if ( ! $suppress_updates ) {
				$wp_plugin_updates = get_site_transient( 'update_plugins' );
				if ( $wp_plugin_updates && isset( $wp_plugin_updates->response[PPC_BASENAME] ) && ! empty($wp_plugin_updates->response[PPC_BASENAME]->new_version) && version_compare( $wp_plugin_updates->response[PPC_BASENAME]->new_version, PPC_VERSION, '>' ) ) {
					$slug = 'press-permit-core';
			
					$_url = "plugin-install.php?tab=plugin-information&plugin=$slug&section=changelog&TB_iframe=true&width=600&height=800";
					$info_url = ( $use_network_admin ) ? network_admin_url($_url) : admin_url($_url);
					$info_link =  "<span class='update-message'> &bull; <a href='$info_url' class='thickbox'>" . sprintf( _pp_('%s&nbsp;details', 'pp'), $wp_plugin_updates->response[PPC_BASENAME]->new_version ) . '</a></span>';
				}
			}
			
			?>
			<p>
			<?php printf( __( 'Press Permit Core Version: %1$s %2$s', 'pp'), PPC_VERSION, $info_link );?>
			<br />
			<span style="display:none"><?php printf( __( "Database Schema Version: %s", 'pp'), PPC_DB_VERSION);?><br /></span>
			<span class="publishpress"><?php printf( __( '- part of the %1$sPublishPress%2$s family of professional publishing tools', 'pp'), '<a href="https://publishpress.com/" target="_blank">', '</a>' );?></span>
			</p>
			
			<br />
			<?php 
			global $wp_version;
			printf( __( "WordPress Version: %s", 'pp'), $wp_version );
			?>
			<br />
			<?php printf( __( "PHP Version: %s", 'pp'), phpversion() );?>
			<br />
			<?php if ( empty($activated) && empty($expired) && ! defined( 'PP_FORCE_PPCOM_INFO' ) ) :?>
				<div class="pp-settings-caption">
				<?php
				$hint = __( 'Periodically query presspermit.com for available extensions. Your version info will be sent.', 'pp' );
				$ui->option_checkbox( 'ppcom_update_info', $tab, $section, $hint );
				?>
				</div>
			<?php endif;?>
			
			</td></tr>
		<?php endif; // any options accessable in this section
		
		
		$section = 'extensions';								// --- EXTENSIONS SECTION ---
		if ( ! empty( $ui->form_options[$tab][$section] ) ) : ?>
			<tr><th scope="row"><?php echo $ui->section_captions[$tab][$section]; if( $ppcom_connect ) echo '&nbsp;&nbsp;&bull;&nbsp;&nbsp;<a href="admin.php?page=pp-settings&amp;pp_refresh_updates=1">' . __('refresh', 'pp') . '</a>';?></th><td>
			<?php
			global $pp_extensions;
			
			$missing = $inactive = array();
			
			if ( pp_get_option( 'display_hints' ) && $ppcom_connect ) {
				$ext_info = pp_get_extension_info( ! empty($_REQUEST['pp_refresh_done']) );
				$ext_info->blurb['capability-manager-enhanced'] = __( 'Create your own WP roles or modify the capabilities defined for any WP Role.', 'pp' );
				$ext_info->descript['capability-manager-enhanced'] = __( 'Create your own WP roles or modify the capabilities defined for any WP Role. Not necessary for all installations, but PP interop is particularly important for bbPress and BuddyPress installations.', 'pp' );
			}
			
			if ( ! empty($ext_info) && ( empty($ext_info->blurb) || empty( $ext_info->descript) ) )
				unset($ext_info);
			
			if ( $missing = array_diff_key( $update_info, $pp_extensions ) ) {
				unset( $missing['press-permit-core'] );
			
				foreach( array_keys($missing) as $slug ) {
					if ( 0 === validate_plugin( "$slug/$slug.php" ) ) {
						unset( $missing[$slug] );
						$inactive[$slug] = true;
					}
				}
			}

			ksort($pp_extensions);
			if ( $pp_extensions ) :
				$change_log_caption = __( '<strong>Change Log</strong> (since your current version)', 'pp' );
			?>
				<h4 style="margin-top:0"><?php _e('Active Extensions:', 'pp');?></h4>
				<table class="pp-extensions">
				<?php foreach( $pp_extensions as $slug => $plugin_info ) :
					$info_link = '';
					$update_link = '';
					$alert = '';
					
					if ( isset($update_info[$slug]) && version_compare( $update_info[$slug]['new_version'], $plugin_info->version, '>' ) ) {
						$_url = "plugin-install.php?tab=plugin-information&plugin=$slug&TB_iframe=true&width=600&height=800";
						$info_url = ( $use_network_admin ) ? network_admin_url($_url) : admin_url($_url);
						$info_link =  "<span class='update-message'> &bull; <a href='$info_url' class='thickbox' title='$change_log_caption'>" . sprintf( _pp_('%s&nbsp;details', 'pp'), $update_info[$slug]['new_version'] ) . '</a></span>';
						
						if ( ! $suppress_updates ) {
							$style = ( $activated ) ? '' : "style='display:none'";
							$url = pp_plugin_update_url( $plugin_info->basename, $slug ) . '&pp_install=1&TB_iframe=true&height=400';
							$update_link =  "<span class='pp-update-link' $style> &bull; <a href='$url' class='thickbox' target='_blank'>" . _pp_('update&nbsp;now', 'pp') . '</a></span>';
							$alert = ( ! empty($update_info[$slug]['alert']) ) ? " &bull; <span class='pp-red'>{$update_info[$slug]['alert']}</span>" : '';
						}
					}
				?>
				<tr>
				<td <?php if( $alert ) echo 'colspan="2"';?>><?php echo __($plugin_info->label) . ' <span class="pp-gray">' . $plugin_info->version . "</span> $info_link $update_link $alert"?></td>
				<?php if ( ! empty($ext_info) && ! $alert ) :?>
				<td>
					<?php if ( isset( $ext_info->blurb[$slug] ) ) :?>
					<span class="pp-ext-info" title="<?php if ( isset($ext_info->descript[$slug]) ) echo esc_attr($ext_info->descript[$slug]);?>"><?php echo $ext_info->blurb[$slug];?></span>
					<?php endif; ?>
				</td>
				<?php endif;?>
				</tr>
				<?php endforeach; ?>
				</table>
			<?php 
			endif;

			if ( ! defined( 'CAPSMAN_ENH_VERSION' ) ) {
				if ( 0 === validate_plugin( 'capability-manager-enhanced/capsman-enhanced.php' ) || 0 === validate_plugin( 'capsman-enhanced/capsman-enhanced.php' ) )
					$inactive['capability-manager-enhanced'] = true;
				else
					$missing['capability-manager-enhanced'] = true;
			}
			
			ksort($inactive);
			if ( $inactive ) :
			?>
			<h4><?php $url = ( PP_MULTISITE ) ? 'network/plugins.php/' : 'plugins.php'; printf( __('%1$sInactive Extensions%2$s:', 'pp'), "<a href='$url'>", '</a>');?></h4>
			<table class="pp-extensions">
			<?php foreach( array_keys($inactive) as $slug ) :?>
			<tr>
			<td><?php echo pp_pretty_slug( $slug );?></td>
			<?php if ( ! empty($ext_info) ) :?>
				<td>
					<?php if ( isset( $ext_info->blurb[$slug] ) ) :?>
					<span class="pp-ext-info" title="<?php if ( isset($ext_info->descript[$slug]) ) echo esc_attr($ext_info->descript[$slug]);?>"><?php echo $ext_info->blurb[$slug];?></span>
					<?php endif; ?>
				</td>
			<?php endif;?>
			</tr>
			<?php endforeach; ?>
			</table>
			<?php 
			endif;
			
			ksort($missing);
			if ( $missing ) :
			?>
			<h4><?php _e('Available Pro Extensions:', 'pp');?></h4>
			<table class="pp-extensions">
			<?php foreach( array_keys($missing) as $slug ) :
				if ( $need_supplemental_key = isset($update_info[$slug]['key_type']) && ( 'pp' != $update_info[$slug]['key_type'] ) )
					$any_supplemental_key = true;
			
				if ( $activated && isset($update_info[$slug]) && ! $need_supplemental_key && ! $suppress_updates ) {
					$_url = "update.php?action=$slug&amp;plugin=$slug&pp_install=1&TB_iframe=true&height=400";
					$install_url = ( $use_network_admin ) ? network_admin_url($_url) : admin_url($_url);
					$url = wp_nonce_url($install_url, "{$slug}_$slug");
					$install_link =  "<span> &bull; <a href='$url' class='thickbox' target='_blank'>" . _pp_('install', 'pp') . '</a></span>';
				} else {
					$install_link = '';
				}
			?>
			<tr>
			<td>
			<?php 
			if ( ! empty($update_info[$slug]) )
				echo "<a href='https://presspermit.com/extensions/$slug'>" . pp_pretty_slug($slug) . '</a>' . $install_link;
			else {
				$caption = ucwords( str_replace( '-', ' ', $slug ) );
				echo '<span class="plugins update-message"><a href="' . pp_plugin_info_url($slug) . '" class="thickbox" title=" ' . $caption . '">' . str_replace( ' ', '&nbsp;', $caption ) . '</a></span>';
			}
			?></td>
			<?php if ( ! empty($ext_info) ) :?>
				<td>
					<?php if ( isset( $ext_info->blurb[$slug] ) ) :?>
					<span class="pp-ext-info" title="<?php if ( isset($ext_info->descript[$slug]) ) echo esc_attr($ext_info->descript[$slug]);?>"><?php echo $ext_info->blurb[$slug];?></span>
					<?php endif; ?>
				</td>
			<?php endif;?>
			</tr>
			<?php endforeach; ?>
			</table>
			<p style="padding-left:15px;">
			<?php 
			
			if ( ! $activated ) {
				echo '<span class="pp-red">' . __( 'To enable one-click installation and update of extensions, please activate your Press Permit Pro support key above.', 'pp' ) . '<span>';
			} elseif( ! empty($any_supplemental_key) ) {
				printf( __( 'Visit %1$spresspermit.com%2$s for further information on obtaining and installing extensions.', 'pp' ), '<a href="https://presspermit.com">', '</a>' );
			}
			?>
			</p>
			
			<?php elseif ( ! pp_update_info_enabled() ) :?>
				<p class="pp-feature-list-caption"><strong><?php _e('Press Permit Pro extensions supply:', 'pp' );?></strong></p>
				<ul class="pp-bullet-list">
				<li><?php printf( __( '%1$sContent-specific editing permissions, with PublishPress, Edit Flow and Revisionary support%2$s', 'pp' ), '<a href="https://presspermit.com/extensions/pp-collaborative-editing">', '</a>' );?></li>
				<li><?php printf( __( '%1$sCustom Post Statuses (for visibility or moderation)%2$s', 'pp' ), '<a href="https://presspermit.com/extensions/pp-custom-post-statuses">', '</a>' );?></li>
				<li><?php printf( __( '%1$sCustomize bbPress forum access%2$s', 'pp' ), '<a href="https://presspermit.com/extensions/pp-compatibility">', '</a>' );?></li>
				<li><?php printf( __( '%1$sFile URL filtering%2$s', 'pp' ), '<a href="https://presspermit.com/extensions/pp-file-url-filter">', '</a>' );?></li>
				<li><?php printf( __( '%1$sRole Scoper import script%2$s', 'pp' ), '<a href="https://presspermit.com/extensions/pp-import">', '</a>' );?></li>
				<li><?php printf( __( '%1$s...and more%2$s', 'pp' ), '<a href="https://presspermit.com/extensions/">', '</a>' );?></li>
				</ul>
				<p>
				<?php printf( __( 'For updated availability, enable the "update info" option above or visit %1$spresspermit.com%2$s.', 'pp'), '<a href="https://presspermit.com/extensions/">', '</a>' );?></p>
			<?php 
			endif;

			?>
			<?php if ( ! empty($ext_info) && pp_get_option( 'display_hints' ) ) : ?>
			<p><span class="pp-subtext">
			<?php _e( 'Note: Hover over descriptions for more detail. Press Permit extensions can also be maintained on the Plugins screen.', 'pp' ); ?>
			</span></p>
			<?php endif; ?>
			
			</td></tr>
			<?php	
		endif; // any options accessable in this section
		
		
		$section = 'help';									// --- HELP SECTION ---
		if ( ! empty( $ui->form_options[$tab][$section] ) ) : ?>
			<tr><th scope="row"><?php echo $ui->section_captions[$tab][$section]; ?></th><td>
			<?php
			
			if ( $activated ) {
				?>
				<ul class="pp-support-list">
				<li><a href='https://presspermit.com/docs/' target='pp_doc'><?php _e('Press Permit Documentation', 'pp');?></a></li>

				<li class="pp-support-forum"><a href="admin.php?page=pp-settings&amp;pp_help_ticket=1" target="pp_help_ticket"><?php _e('Submit a Help Ticket', 'pp');?></a> <strong>*</strong></li>

				<li class="upload-config"><a href="admin.php?page=pp-settings&amp;pp_upload_config=1"><?php _e('Upload site configuration to presspermit.com now', 'pp');?></a> <strong>*</strong> 
				<img id="pp_upload_waiting" class="waiting" style="display:none;position:relative" src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) )?>" alt="" />
				</li>
				</ul>
				
				<div id="pp_config_upload_caption"><strong>
				<?php printf( __( '%s Site configuration data selected below will be uploaded to presspermit.com:', 'pp' ), '<strong>* </strong>' );?>
				</strong></div>
				
				<div id="pp_config_upload_wrap">
				<?php
				$ok = (array) pp_get_option( 'support_data' );
				$ok['ver'] = 1;
				
				$ui->all_options []= 'support_data';
				
				$avail = array( 
					'ver' => __('Version info for server, WP, bbPress, BuddyPress, PP and all extensions', 'pp'),
					'pp_options' => __( 'PP Settings and related WP Settings', 'pp'),
					'theme' => __('Theme name, version and status', 'pp'),
					'active_plugins' => __('Activated plugins list', 'pp'),
					'installed_plugins' => __('Inactive plugins list', 'pp'),
					'wp_roles_types' => __('WordPress Roles, Capabilities, Post Types, Taxonomies and Post Statuses', 'pp'),
					'pp_permissions' => __( 'Role Assignments and Exceptions', 'pp'),
					'pp_groups' => __( 'Group definitions', 'pp'),
					'pp_group_members' => __( 'Group Membership (id only)', 'pp'),
					'pp_imports' => __('Role Scoper / PP 1.x Configuration and PP Import Results', 'pp'),
					'post_data' => __('Post id, status, author id, parent id, and term ids and taxonomy name (when support accessed from post or term edit form)', 'pp'),
					'error_log' => __('PHP Error Log (recent entries, no absolute paths)', 'pp'),
				);
				
				$ok['ver'] = true;
				$ok['pp_options'] = true;
				$ok['error_log'] = true;
				
				?>
				<div class="support_data">
				<?php
				foreach( $avail as $key => $caption ) :
					$id = 'support_data_' . $key;
					$disabled = ( in_array( $key, array( 'ver', 'pp_options', 'error_log' ) ) ) ? 'disabled="disabled"' : '';
				?>
					<div>
					<label for="<?php echo $id;?>"><input type="checkbox" id="<?php echo $id;?>" name="support_data[<?php echo $key;?>]" value="1" <?php echo $disabled; checked('1', ! empty($ok[$key]), true);?> /> <?php echo $caption;?></label>
					</div>
				<?php 
				endforeach;
				?>
				</div>
				
				<div>
				<label for="pp_support_data_all"><input type="checkbox" id="pp_support_data_all" value="1" /> <?php _e('(all)', 'pp');?></label>
				</div>
				
				<div id="pp_config_upload_subtext">
				<?php _e( '<strong>note:</strong> user data, absolute paths, database prefix, post title, post content and post excerpt are <strong>never</strong> uploaded', 'pp' );?>
				</div>
				
				</div>
				<?php
			} else {
				?>
				<div>
				<?php _e( 'Purchase of a support key enables access to the following resources:', 'pp' );?>
				</div>
	
				<ul class="pp-support-list pp-bullet-list">
				<li><?php _e('Priority support through our help ticket system', 'pp');?></a></li>
				<li><?php _e('Optional uploading of your site configuration to assist troubleshooting', 'pp');?></li>
				</ul>
				<?php
			}
			
			if ( version_compare( PPC_VERSION, '1.0', '<' ) ) {
				echo '<div>';
				_e( 'Note that these resources may be unavailable or incomplete during the project\'s initial beta phase.', 'pp' );
				echo '</div>';
			}
			
			?>
			</td></tr>
		<?php 
			
		endif; // any options accessable in this section
		
		
		if ( ! empty($activated) ) {
			$section = 'beta_updates';								// --- BETA UPDATES SECTION ---
			if ( ! empty( $ui->form_options[$tab][$section] ) && ! $suppress_updates && $ppcom_connect ) : ?>
				<tr><th scope="row"><?php echo $ui->section_captions[$tab][$section]; ?></th><td>
				<?php
				if ( preg_match( "/dev|alpha|beta|rc/i", PPC_VERSION ) && ( version_compare( PPC_VERSION, '0.9', '>' ) ) )
					$hint = __( 'If you have already received a beta update and want to switch back to the current production version, switch off this option and click Update. Then look for an update prompt in the Plugins list.', 'pp' );
				else
					$hint = '';
				
				$ui->option_checkbox( 'beta_updates', $tab, $section, $hint );
				?>
				</td></tr>
				<?php	
			endif; // any options accessable in this section
		}
	} // end function options_ui()

	function footer_js( $activated, $expired ) {
		$vars = array( 
			'activated' => ( $activated || ! empty($expired) ) ? true : false,
			'expired' => ! empty($expired),
			'activateCaption' => __('Activate Key','pp'),
			'deactivateCaption' => __('Deactivate Key','pp'),
			'connectingCaption' => __('Connecting to presspermit.com server...','pp'),
			'noConnectCaption' => __('The request could not be processed due to a connection failure.','pp'),
			'noEntryCaption' => __('Please enter the support key shown on your order receipt.','pp'),
			'errCaption' => __('An unidentified error occurred.','pp'),
			'keyStatus' => json_encode( array( 
				'0' => __('The key has been deactivated.','pp'),
				'1' => __('The key has been activated.','pp'),
				'-1' => __('The key has expired.','pp'),
				'-100' => __('An unknown activation error occurred.','pp'),
				'-101' => __('The key provided is not valid. Please double-check your entry.','pp'),
				'-102' => __('This site is not valid to activate the key.','pp'),
				'-103' => __('The key provided could not be validated by presspermit.com.','pp'),
				'-104' => __('The key provided is already active on another site.','pp'),
				'-105' => __('The key has already been activated on the allowed number of sites.','pp'),
				'-200' => __('An unknown deactivation error occurred.','pp'),
				'-201' => __('Unable to deactivate because the provided key is not valid.','pp'),
				'-202' => __('This site is not valid to deactivate the key.','pp'),
				'-203' => __('The key provided could not be validated by presspermit.com.','pp'),
				'-204' => __('The key provided is not active on the specified site.','pp'),
			) ),
			'activateURL' => wp_nonce_url( admin_url(''), 'wp_ajax_pp_activate_key' ),
			'deactivateURL' => wp_nonce_url( admin_url(''), 'wp_ajax_pp_deactivate_key' ),
			'refreshURL' => wp_nonce_url( admin_url(''), 'wp_ajax_pp_refresh_version' ),
			'activationHelp' => sprintf( __( 'If this is incorrect, <a href="%s">request activation help</a>.', 'pp' ), 'https://presspermit.com/' . 'activation/' ),
			'supportOptChanged' => __('Please save settings before uploading site configuration.', 'pp'),
		);
		
		wp_localize_script( 'pp_settings', 'ppSettings', $vars );
	}
}

function pp_use_network_updates() {
	return PP_MULTISITE && ( is_network_admin() || pp_is_network_activated(PPC_BASENAME) || pp_is_mu_plugin(PPC_FILE) );
}

function pp_plugin_update_url( $plugin_file, $action='upgrade-plugin' ) {
	$_url = "update.php?action=$action&amp;plugin=$plugin_file";
	$url = ( pp_use_network_updates() ) ? network_admin_url($_url) : admin_url($_url);
	$url = wp_nonce_url($url, "{$action}_$plugin_file");
	return $url;
}
