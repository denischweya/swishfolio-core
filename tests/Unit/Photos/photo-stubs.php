<?php
/**
 * WordPress HTTP stubs for the photo sourcing tests.
 *
 * wp_safe_remote_get() behaves like WordPress's for one thing only: the
 * pre_http_request filter short-circuits it. Anything a test did not answer
 * fails like a dead network, so no test can reach a real API.
 *
 * @package SwishfolioCore
 */

if (! function_exists('wp_safe_remote_get')) {
    function wp_safe_remote_get($url, $args = [])
    {
        $GLOBALS['sfcore_test_http'][] = ['url' => (string) $url, 'args' => $args];
        $pre = apply_filters('pre_http_request', false, $args, $url);

        return $pre !== false ? $pre : new \WP_Error('http_request_failed', 'No network in unit tests.');
    }
}

if (! function_exists('wp_remote_retrieve_header')) {
    function wp_remote_retrieve_header($response, $header)
    {
        return isset($response['headers'][strtolower($header)]) ? $response['headers'][strtolower($header)] : '';
    }
}

if (! function_exists('wp_json_encode')) {
    function wp_json_encode($data, $flags = 0, $depth = 512)
    {
        return json_encode($data, $flags, $depth);
    }
}

if (! function_exists('esc_url')) {
    function esc_url($url)
    {
        return htmlspecialchars((string) $url, ENT_QUOTES);
    }
}

if (! function_exists('esc_html')) {
    function esc_html($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES);
    }
}

if (! function_exists('esc_html__')) {
    function esc_html__($text, $domain = null)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES);
    }
}
