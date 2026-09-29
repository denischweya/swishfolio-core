<?php
/**
 * Minimal WordPress function stubs for unit tests.
 *
 * Each is guarded so the real WordPress function wins when the integration
 * suite loads the WP test bootstrap instead.
 *
 * @package SwishfolioCore
 */

if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field($str)
    {
        return trim(strip_tags((string) $str));
    }
}

if (! function_exists('esc_url_raw')) {
    function esc_url_raw($url)
    {
        $url    = trim((string) $url);
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if ($url !== '' && $scheme !== '' && ! in_array($scheme, ['http', 'https', 'mailto', 'tel'], true)) {
            return '';
        }

        return $url;
    }
}

if (! function_exists('wp_kses_post')) {
    function wp_kses_post($html)
    {
        return strip_tags((string) $html, '<a><b><strong><em><i><br><span><code><mark><s><sub><sup>');
    }
}

if (! function_exists('apply_filters')) {
    function apply_filters($tag, $value)
    {
        return $value;
    }
}

if (! function_exists('get_transient')) {
    function get_transient($key)
    {
        return isset($GLOBALS['sfcore_test_transients'][$key])
            ? $GLOBALS['sfcore_test_transients'][$key]
            : false;
    }
}

if (! function_exists('set_transient')) {
    function set_transient($key, $value, $expiry = 0)
    {
        if (! isset($GLOBALS['sfcore_test_transients'])) {
            $GLOBALS['sfcore_test_transients'] = [];
        }
        $GLOBALS['sfcore_test_transients'][$key] = $value;

        return true;
    }
}

if (! function_exists('__')) {
    function __($text, $domain = null)
    {
        return $text;
    }
}

/**
 * Minimal WP_Error stand-in for provider transport-failure tests.
 */
if (! class_exists('WP_Error_Stub')) {
    class WP_Error_Stub
    {
        public $message;

        public function __construct($message)
        {
            $this->message = $message;
        }

        public function get_error_message()
        {
            return $this->message;
        }
    }
}

if (! function_exists('is_wp_error')) {
    function is_wp_error($thing)
    {
        return $thing instanceof \WP_Error_Stub || $thing instanceof \WP_Error;
    }
}

if (! function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response)
    {
        return isset($response['response']['code']) ? (int) $response['response']['code'] : 0;
    }
}

if (! function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response)
    {
        return isset($response['body']) ? $response['body'] : '';
    }
}

if (! function_exists('get_option')) {
    function get_option($key, $default = false)
    {
        return isset($GLOBALS['sfcore_test_options'][$key])
            ? $GLOBALS['sfcore_test_options'][$key]
            : $default;
    }
}

if (! function_exists('update_option')) {
    function update_option($key, $value, $autoload = null)
    {
        if (! isset($GLOBALS['sfcore_test_options'])) {
            $GLOBALS['sfcore_test_options'] = [];
        }
        $GLOBALS['sfcore_test_options'][$key] = $value;

        return true;
    }
}
