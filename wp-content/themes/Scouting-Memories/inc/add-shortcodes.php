<?php


if (!function_exists('my_user_meta')) :
    add_shortcode('my_user_meta', function ($atts, $content = null) {
        return esc_html(get_user_meta(get_current_user_id(), $atts['key'], true));
    });
endif;

if (!function_exists('my_site_url')) :
    add_shortcode('site_url', function ($atts = null, $content = null) {
        return site_url();
    });
endif;
