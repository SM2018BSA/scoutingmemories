<?php
class PP_AdminHelp {
	public static function register_contextual_help() {
		$screen_obj = get_current_screen();
		if ( is_object($screen_obj) )
			$screen = $screen_obj->id;
		else
			return;
		
		if ( strpos( $screen, 'pp-' ) ) {
			$match = array();
			if ( ! preg_match( "/admin_page_pp-[^@]*-*/", $screen, $match ) )
				if ( ! preg_match( "/_page_pp-[^@]*-*/", $screen, $match ) )
					preg_match( "/pp-[^@]*-*/", $screen, $match );

			if ( $match )
				if ( $pos = strpos( $match[0], 'pp-' ) ) {
					$link_section = substr( $match[0], $pos + strlen('pp-') );
					$link_section = str_replace( '_t', '', $link_section );	
				}
					
		} elseif ( in_array( $screen_obj->base, array( 'post', 'page', 'upload', 'users', 'edit-tags', 'edit' ) ) ) {
			$link_section = $screen_obj->base;
		}
		
		if ( ! empty($link_section) ) {
			$screen_obj->add_help_tab( array( 
			   'id' => 'pp',            //unique id for the tab
			   'title' => __('Press Permit Help', 'pp'),      //unique visible title for the tab
			   'content' => '',  //actual help text
			   'callback' => array( 'PP_AdminHelp', '_pp_show_contextual_help' ), //optional function to callback
			) );
		}
	}
	
	public static function _pp_show_contextual_help() {
		$help = '';

		$opt_val = pp_get_option( 'support_key' );
		
		if ( ! is_array($opt_val) || count($opt_val) < 2 ) {
			$activated = false;
			$expired = false;
		} else {
			$activated = ( 1 == $opt_val[0] );
			$expired = ( -1 == $opt_val[0] );
		}
		
		if ( ! empty( $expired ) ) :?>
			<div class="activating"><span class="pp-key-wrap pp-key-expired">
			<?php printf( __( 'Your support key has expired. For priority support and Pro extensions, <a href="%s" target="_blank">please renew</a>', 'pp' ), 'admin.php?page=pp-settings&amp;pp_renewal=1' );?>
			</span></div>
		<?php elseif ( empty( $activated ) ) :?>
			<div class="activating"><p><span class="pp-key-wrap pp-key-expired">
			<?php printf( __( 'For priority support and Pro extensions, <a href="%1$s">activate your support key</a>', 'pp' ), 'admin.php?page=pp-settings' );?>
			</span></p>
			<p><span class="pp-key-wrap pp-key-expired">
			<?php printf( __( 'If you need a key, <a href="%s" target="_blank">Explore Pro packages</a>', 'pp' ), 'https://presspermit.com/purchase/' );?>
			</span></p></div>
		<?php endif;
		
		$help .= '<ul><li>' . sprintf(__('%1$s Press Permit Documentation%2$s', 'pp'), "<a href='https://presspermit.com/docs/' target='_blank'>", '</a>') . '</li>';
		
		if ( ! empty( $expired ) || empty( $activated ) ) {
			$help .= '<li>' . sprintf(__('%1$s Submit a Help Ticket%2$s', 'pp'), "<a href='admin.php?page=pp-settings&amp;pp_help_ticket=1' target='_blank'>", '</a></li>');
		} else {
			$help .= '<li>' . sprintf(__('%1$s Submit a Help Ticket (with config data upload)%2$s *', 'pp'), "<a href='admin.php?page=pp-settings&amp;pp_help_ticket=1' target='_blank'>", '</a>') . '</li>';
		}
		
		$help .= '</ul>';
		
		if ( empty( $expired ) && ! empty( $activated ) ) {
			$help .= '<div>';
			$help .= __( '* to control which configuration data is uploaded, see Permissions > Settings > Install > Help', 'pp' );
			$help .= '</div>';
		} else {
			$help .= '<p></p>';
		}
		
		echo $help;
	}
}
