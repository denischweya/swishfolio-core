<?php
/**
 * Shared HTTP behaviour for AI providers.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Providers;

use SwishfolioCore\AI\Contracts\AIProviderInterface;
use SwishfolioCore\AI\ProviderException;

/**
 * Wraps wp_remote_post() exactly as AbstractEmailProvider wraps
 * wp_remote_request(). No SDK is vendored: this plugin keeps zero runtime
 * Composer dependencies, and vendored HTTP clients are the classic source of
 * WordPress plugin conflicts.
 */
abstract class AbstractAIProvider implements AIProviderInterface
{
    const MAX_ATTEMPTS = 3;
    const TIMEOUT      = 60;

    /** @var array */
    protected $credentials = [];

    /** @var string|null */
    protected $lastError;

    public function setCredentials(array $credentials)
    {
        $this->credentials = $credentials;
    }

    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * @param string $key     Credential name.
     * @param string $default Fallback.
     *
     * @return string
     */
    protected function credential($key, $default = '')
    {
        return isset($this->credentials[$key]) && $this->credentials[$key] !== ''
            ? (string) $this->credentials[$key]
            : $default;
    }

    /**
     * The single HTTP seam. Tests override this; nothing else calls
     * wp_remote_post() directly.
     *
     * @param string $url  Endpoint.
     * @param array  $args wp_remote_post arguments.
     *
     * @return array|\WP_Error
     */
    protected function post($url, array $args)
    {
        return wp_remote_post($url, $args);
    }

    /**
     * @param int $attempt Zero-based attempt number.
     *
     * @return void
     */
    protected function backoff($attempt)
    {
        usleep((int) (250000 * pow(2, $attempt)));
    }

    /**
     * POST JSON, retrying transient failures.
     *
     * @param string $url     Endpoint.
     * @param array  $body    Payload.
     * @param array  $headers Extra headers.
     *
     * @throws ProviderException On unrecoverable failure.
     *
     * @return array Decoded response body.
     */
    protected function request($url, array $body, array $headers = [])
    {
        $this->lastError = null;
        $lastStatus      = 0;

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $response = $this->post($url, [
                'timeout' => self::TIMEOUT,
                'headers' => array_merge(['Content-Type' => 'application/json'], $headers),
                'body'    => wp_json_encode_compat($body),
            ]);

            if (is_wp_error($response)) {
                $this->lastError = $response->get_error_message();
                $lastStatus      = 0;

                $this->backoff($attempt);
                continue;
            }

            $status = wp_remote_retrieve_response_code($response);
            $raw    = wp_remote_retrieve_body($response);

            if ($status >= 200 && $status < 300) {
                $decoded = json_decode($raw, true);

                if (! is_array($decoded)) {
                    $this->lastError = 'Provider returned a non-JSON body.';

                    throw new ProviderException($this->lastError, $status);
                }

                return $decoded;
            }

            $this->lastError = $this->extractError($raw, $status);
            $lastStatus      = $status;

            // 429 and 5xx are worth another go; 4xx is not.
            if ($status !== 429 && $status < 500) {
                throw new ProviderException($this->lastError, $status);
            }

            $this->backoff($attempt);
        }

        throw new ProviderException(
            $this->lastError === null ? 'The AI provider did not respond.' : $this->lastError,
            $lastStatus
        );
    }

    /**
     * @param string $raw    Raw response body.
     * @param int    $status HTTP status.
     *
     * @return string
     */
    protected function extractError($raw, $status)
    {
        $decoded = json_decode($raw, true);

        if (is_array($decoded) && isset($decoded['error']['message'])) {
            return (string) $decoded['error']['message'];
        }

        return sprintf('The AI provider returned HTTP %d.', (int) $status);
    }
}

if (! function_exists('SwishfolioCore\AI\Providers\wp_json_encode_compat')) {
    /**
     * wp_json_encode() when WordPress is loaded, json_encode() under unit tests.
     *
     * @param mixed $data Data to encode.
     *
     * @return string
     */
    function wp_json_encode_compat($data)
    {
        return function_exists('wp_json_encode') ? wp_json_encode($data) : json_encode($data);
    }
}
