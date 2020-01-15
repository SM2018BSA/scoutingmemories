<?php




if (!function_exists('my_user_meta')) :
    function my_user_meta($atts, $content = null) {
        return esc_html(get_user_meta(get_current_user_id(), $atts['key'], true));
    }
    add_shortcode('my_user_meta', 'my_user_meta');
endif;



if (!function_exists('my_site_url')) :
    function my_site_url($atts = null, $content = null) {
        return site_url();
    }
    add_shortcode('site_url', 'my_site_url');
endif;




if (!function_exists('state_name')) :
function state_name($atts = [], $content =null) {

   $state 	 = get_field_val( AAP_STATES_FID, $content);


  /// if ( strlen($state) > 1 )

//        echo '<pre> state: ';
//        var_dump($content);
//        echo '</pre>';


    $content = $state;


    return $content;
}
add_shortcode('state_name','state_name');
endif;