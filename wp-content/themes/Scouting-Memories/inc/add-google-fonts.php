<?php
if ( ! function_exists( 'add_google_fonts' ) ) :
function add_google_fonts()
{
    wp_enqueue_style('custom-google-fonts', 'https://fonts.googleapis.com/css?family=Roboto+Slab', false);

} add_action('wp_enqueue_scripts', 'add_google_fonts');

endif;

