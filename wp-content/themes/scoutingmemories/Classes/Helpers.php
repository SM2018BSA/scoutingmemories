<?php
if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} else {
    return true;
}


/**
 *
 *     Class Helpers
 *
 *
 *
 */
class Helpers
{


    // Setup up query variables
    //
    // $smp_action
    // $adm_posts_paged

    public function setup_hooks()
    {
        add_action('query_vars', array(&$this, 'add_query_vars_filter'));
        add_filter('frm_include_meta_keys', '__return_true');
    }

    public function add_query_vars_filter($qvars)
    {
        $qvars[] = "adm_posts_paged";
        $qvars[] = "smp_action";
        return $qvars;
    }


    /**
     * @return bool
     */
    public static function check_file_access(): bool
    {
        if (!defined('ABSPATH')) {
            die('You are not allowed to call this page directly.');
        } else {
            return true;
        }
    }


    public static function maybe_json_encode($value)
    {
        if (is_array($value)) {
            $value = wp_json_encode($value);
        }
        return $value;
    }


    /**
     * Gets the request parameter.
     *
     * @param string $key The query parameter
     * @param null $default The default value to return if not found
     *
     * @return string|array The request parameter.
     */
    public static function get_request_parameter(string $key, $default = null): array|string|null
    {
        // If not request set
        if (empty($_REQUEST[$key])) {
            return $default;
        } else {
            $request_key = $_REQUEST[$key];
        }


        // recursively clean all items found
        if (is_array($request_key)) {
            array_walk_recursive($request_key, 'Helpers::clean_all');
            return $request_key;
        } else {
            // Set so process it
            Helpers::clean_all($request_key);
        }

        return $request_key;
    }


    private static function clean_all(&$item)
    {
        $item = urlencode_deep(strip_tags((string)wp_unslash($item)));
    }


    public static function set_query_var(string $key, $value = null)
    {
        return set_query_var($key, $value);
    }


    /**
     * @param $string
     * @return string|null
     */
    public static function clean($string): string|null
    {
        $string = str_replace(' ', '_', $string); // Replaces all spaces with underscores.
        $string = str_replace('(', '_', $string); // Replaces all spaces with underscores.
        $string = str_replace(')', '_', $string); // Replaces all spaces with underscores.
        $string = str_replace('&amp;', '_and_', $string); // Replaces all spaces with underscores.
        return preg_replace('/[^A-Za-z0-9\-]/', '_', $string); // Removes special chars.
    }


    /**
     * @param $data
     */
    public static function debug_to_console($data)
    {
        $output = $data;
        if (is_array($output))
            $output = implode(',', $output);
        echo "<script>console.log( 'Debug Objects: " . $output . "' );</script>";
    }



    // convert an stdClass object into an array
    //
    public static function object_to_array($object): array
    {
        return json_decode(json_encode($object), true);
    }


    //
    //
    public static function unsetValue(array &$array, $value, $strict = TRUE): array
    {
        if (($key = array_search($value, $array, $strict)) !== FALSE) {
            unset($array[$key]);
        }
        return $array;
    }


    public static function doShortCode($short_code)
    {
        return do_shortcode($short_code);
    }

    public static function multiKeyExists($key, array $arr) {

        // is in base array?
        if (array_key_exists($key, $arr)) {
            return true;
        }

        // check arrays contained in this array
        foreach ($arr as $element) {
            if (is_array($element)) {
                if (multiKeyExists($element, $key)) {
                    return true;
                }
            }

        }

        return false;
    }



}