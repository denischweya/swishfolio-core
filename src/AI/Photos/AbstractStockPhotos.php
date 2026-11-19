<?php
/**
 * Shared HTTP for the stock photo providers.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Photos;

abstract class AbstractStockPhotos implements StockPhotoProvider
{
    /** @var string */
    protected $key;

    /** @var string */
    protected $error = '';

    /**
     * @param string $key API key (never logged, never put in a URL).
     */
    public function __construct($key)
    {
        $this->key = (string) $key;
    }

    public function lastError()
    {
        return $this->error;
    }

    /**
     * Canonical orientation for this library.
     *
     * @param string $orientation landscape|portrait|square.
     *
     * @return string
     */
    abstract protected function orientation($orientation);

    /**
     * GET a JSON endpoint.
     *
     * @param string $url     URL (no key in it).
     * @param array  $headers Headers (the key goes here).
     * @param int    $timeout Seconds.
     *
     * @return array|null Decoded body, or null with $this->error set.
     */
    protected function getJson($url, array $headers, $timeout)
    {
        $response = wp_safe_remote_get($url, ['timeout' => max(1, (int) $timeout), 'headers' => $headers, 'redirection' => 2]);

        if (is_wp_error($response)) {
            // Transport messages carry the URL at most, never the headers.
            $this->error = $this->slug() . ': ' . $response->get_error_message();

            return null;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            $this->error = $this->slug() . ': HTTP ' . $status . ($status === 401 || $status === 403 ? ' (check the API key)' : ($status === 429 ? ' (rate limit reached)' : ''));

            return null;
        }

        $decoded = json_decode((string) wp_remote_retrieve_body($response), true);
        if (! is_array($decoded)) {
            $this->error = $this->slug() . ': not JSON';

            return null;
        }

        return $decoded;
    }

    /**
     * @param mixed $value Value.
     *
     * @return string Trimmed string ('' for non-scalars).
     */
    protected static function str($value)
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * Append query args to a URL that may already have some.
     *
     * @param string $url  URL.
     * @param array  $args Args.
     *
     * @return string
     */
    protected static function withArgs($url, array $args)
    {
        if ($url === '') {
            return '';
        }

        return $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query($args, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Set query args on a URL, replacing any of the same name it already has.
     *
     * @param string $url  URL.
     * @param array  $args Args.
     *
     * @return string
     */
    protected static function withArgsReplaced($url, array $args)
    {
        if ($url === '') {
            return '';
        }

        $parts = explode('?', $url, 2);
        $query = [];
        if (isset($parts[1])) {
            parse_str($parts[1], $query);
        }

        return $parts[0] . '?' . http_build_query(array_merge($query, $args), '', '&', PHP_QUERY_RFC3986);
    }
}
