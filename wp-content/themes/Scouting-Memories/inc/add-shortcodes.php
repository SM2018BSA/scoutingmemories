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
