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
        // Real kses keeps HTML comments, and block markup lives in them;
        // strip_tags() would delete every block delimiter.
        $comments = [];
        $html     = preg_replace_callback(
            '/<!--.*?-->/s',
            static function ($match) use (&$comments) {
                $comments[] = $match[0];

                return '%%sfcomment' . (count($comments) - 1) . '%%';
            },
            (string) $html
        );

        $html = strip_tags($html, '<a><b><strong><em><i><br><span><code><mark><s><sub><sup>');

        return preg_replace_callback(
            '/%%sfcomment(\d+)%%/',
            static function ($match) use ($comments) {
                return $comments[(int) $match[1]];
            },
            $html
        );
    }
}

if (! function_exists('do_action')) {
    function do_action($tag, ...$args)
    {
        $GLOBALS['sfcore_test_actions'][] = [$tag, $args];
    }
}

if (! function_exists('apply_filters')) {
    function apply_filters($tag, $value, ...$args)
    {
        // Filters are ignored unless a test registers one in
        // $GLOBALS['sfcore_test_filters'], keyed by tag. Without this a filter
        // is untestable, and several are part of the public contract.
        if (isset($GLOBALS['sfcore_test_filters'][$tag]) && is_callable($GLOBALS['sfcore_test_filters'][$tag])) {
            return call_user_func($GLOBALS['sfcore_test_filters'][$tag], $value, ...$args);
        }

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

// --- Minimal REST stubs, so controllers are unit-testable without WordPress ---

if (! class_exists('WP_Error')) {
    class WP_Error
    {
        public $code;
        public $message;
        public $data;

        public function __construct($code = '', $message = '', $data = [])
        {
            $this->code    = $code;
            $this->message = $message;
            $this->data    = $data;
        }

        public function get_error_code()
        {
            return $this->code;
        }

        public function get_error_message()
        {
            return $this->message;
        }

        public function get_error_data()
        {
            return $this->data;
        }
    }
}

if (! function_exists('rest_ensure_response')) {
    function rest_ensure_response($data)
    {
        return $data;
    }
}

if (! function_exists('register_rest_route')) {
    function register_rest_route($namespace, $route, $args = [])
    {
        if (! isset($GLOBALS['sfcore_test_routes'])) {
            $GLOBALS['sfcore_test_routes'] = [];
        }
        $GLOBALS['sfcore_test_routes'][$namespace . $route] = $args;

        return true;
    }
}

if (! function_exists('current_user_can')) {
    /**
     * Permissive by default so tests that are not about permissions need no
     * setup; set $GLOBALS['sfcore_test_caps'] to an explicit map to restrict.
     *
     * @param string $capability Capability being checked.
     *
     * @return bool
     */
    function current_user_can($capability, ...$args)
    {
        // One mechanism, shared with the generation stubs: true means "allow
        // everything", an array is an allowlist, and "cap:object" entries let a
        // test grant a capability for one specific object only.
        $caps = isset($GLOBALS['sfcore_test_wp']['caps'])
            ? $GLOBALS['sfcore_test_wp']['caps']
            : true;

        if ($caps === true) {
            return true;
        }

        if ($args !== [] && in_array($capability . ':' . (string) $args[0], (array) $caps, true)) {
            return true;
        }

        return in_array($capability, (array) $caps, true);
    }
}

if (! function_exists('is_user_logged_in')) {
    function is_user_logged_in()
    {
        return ! isset($GLOBALS['sfcore_test_logged_in']) || ! empty($GLOBALS['sfcore_test_logged_in']);
    }
}

if (! function_exists('get_current_user_id')) {
    function get_current_user_id()
    {
        return isset($GLOBALS['sfcore_test_user_id']) ? (int) $GLOBALS['sfcore_test_user_id'] : 0;
    }
}

if (! function_exists('sanitize_key')) {
    function sanitize_key($key)
    {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key));
    }
}

if (! function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str)
    {
        return trim(strip_tags((string) $str));
    }
}
