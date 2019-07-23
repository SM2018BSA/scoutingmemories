<?php

if ( ! function_exists( 'clean' ) ) :
function clean($string)
{
    $string = str_replace(' ', '_', $string); // Replaces all spaces with underscores.
    $string = str_replace('&amp;', '_and_', $string); // Replaces all spaces with underscores.
    return preg_replace('/[^A-Za-z0-9\-]/', '_', $string); // Removes special chars.
}
endif;


if ( ! function_exists( 'debug_to_console' ) ) :
function debug_to_console($data)
{
    $output = $data;
    if (is_array($output))
        $output = implode(',', $output);

    echo "<script>console.log( 'Debug Objects: " . $output . "' );</script>";
}
endif;

