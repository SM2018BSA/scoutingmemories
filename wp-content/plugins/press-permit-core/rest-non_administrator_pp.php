<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class PP_Core_REST {
	// As of 4.8, WP does not trigger a REST capability check for viewing single public posts
	public static function flt_confirm_rest_readable( $rest_response, $handler, $request ) {
		// we are only concerned about read access here
		if ( ! is_wp_error( $rest_response ) && in_array( $request->get_method(), array( WP_REST_Server::READABLE, 'GET' ) ) ) {
			$controller_class = get_class( $handler['callback'][0] );
			
			if ( 'WP_REST_Posts_Controller' == $controller_class ) {
				$is_posts_controller = true;
			} else {
				foreach( pp_get_enabled_post_types( array( 'show_in_rest' => true ), 'object' ) as $type_obj ) {
					if ( isset( $type_obj->rest_controller_class ) && ( $controller_class == $type_obj->rest_controller_class ) ) {
						$is_posts_controller = true;
						break;
					}
				}
			}
		}
		
		if ( ! empty( $is_posts_controller ) ) {
			// back post type and ID out of path because WP_REST_Posts_Controller does not expose them
			$arr_path = explode( '/', $request->get_route() );
			
			$post_id = array_pop( $arr_path );
			
			if ( $post_id && is_numeric( $post_id ) ) {
				$rest_base = array_pop( $arr_path );
				
				if ( pp_get_enabled_post_types( array( 'rest_base' => $rest_base ) ) ) {
					if ( $post_status_obj = get_post_status_object( get_post_field( 'post_status', $post_id ) ) ) {
						if ( $post_status_obj->public && ! current_user_can( 'read_post', $post_id ) ) { 
							return new WP_Error( 'rest_forbidden', __( "Sorry, you are not allowed to do that." ), array( 'status' => 403 ) ); 
						}
					}
				}
			}
		}
		
		return $rest_response;
	}
}