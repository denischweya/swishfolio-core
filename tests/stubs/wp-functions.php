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
