<?php
/**
 * Shared HTTP behaviour for AI providers.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Providers;

use SwishfolioCore\AI\Contracts\AIProviderInterface;
use SwishfolioCore\AI\Conversation\ConversationRequest;
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
    const TIMEOUT      = 120;

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
     * Providers opt in. A third-party provider registered through the
     * swishfolio_ai_providers filter predates tool calling and would otherwise
     * be handed a conversation it cannot answer.
     *
     * @return bool
     */
    public function supportsTools()
    {
        return false;
    }

    /**
     * @param ConversationRequest $request Conversation and tools.
     *
     * @throws ProviderException Always, unless a subclass overrides this.
     *
     * @return \SwishfolioCore\AI\Conversation\ConversationReply
     */
    public function converse(ConversationRequest $request)
    {
        $this->lastError = sprintf(
            /* translators: %s: provider label. */
            __('%s cannot run tool-based conversations.', 'swishfolio-core'),
            $this->getLabel()
        );

        throw new ProviderException($this->lastError, 0);
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
        // Safe variant: a site can point a provider at its own base URL, and
        // that must never reach loopback, private or cloud-metadata addresses.
        return wp_safe_remote_post($url, $args);
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
     * Whether a transport error is the whole-request timeout firing while the
     * provider was still working (cURL 28 "Operation timed out"), as opposed
     * to failing to connect at all.
     *
     * Such a request is not retried: the same request gets the same time limit,
     * so a reply that was too long to finish once times out again, and each
     * try is minutes of waiting and output tokens the provider still bills.
     * Three tries of a 300-second site plan kept the onboarding wizard on
     * "Building your site" for 15 minutes before it could show an error.
     *
     * @param string $message Transport error message.
     *
     * @return bool
     */
    public static function isReplyTimeout($message)
    {
        $message = (string) $message;

        return stripos($message, 'Operation timed out') !== false
            || (stripos($message, 'cURL error 28') !== false && stripos($message, 'bytes received') !== false);
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
                /**
                 * Filters the AI request HTTP timeout in seconds.
                 *
                 * Reasoning models routinely take longer than WordPress's
                 * usual 5-second default, and longer than the 60s this used
                 * to allow.
                 *
                 * @param int $timeout Seconds.
                 */
                'timeout' => (int) apply_filters('swishfolio_ai_http_timeout', self::TIMEOUT),
                'headers' => array_merge(['Content-Type' => 'application/json'], $headers),
                'body'    => wp_json_encode_compat($body),
            ]);

            if (is_wp_error($response)) {
                $this->lastError = $response->get_error_message();
                $lastStatus      = 0;

                if (self::isReplyTimeout($this->lastError)) {
                    throw new ProviderException($this->lastError, 0);
                }

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

                /**
                 * Fires after every successful provider HTTP response.
                 *
                 * Lets a hosting layer meter real token usage in one place for
                 * every route (edit, plan, extract, agent). The payload carries
                 * no credentials.
                 *
                 * @param array  $decoded Decoded response body.
                 * @param string $slug    Provider slug.
                 */
                if (function_exists('do_action')) {
                    do_action('swishfolio_ai_response', $decoded, $this->getSlug());
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
            return self::redactSecrets((string) $decoded['error']['message']);
        }

        return sprintf('The AI provider returned HTTP %d.', (int) $status);
    }

    /**
     * Provider messages reach the browser, and with a shared network key a
     * 401 such as "Incorrect API key provided: sk-...abcd" would show every
     * tenant a fragment of that key. Anything key-shaped is masked.
     *
     * @param string $message Provider error message.
     *
     * @return string
     */
    public static function redactSecrets($message)
    {
        $message = (string) preg_replace('/\b(?:sk|rk|pk)-[A-Za-z0-9_\-*.]{4,}/', '[redacted]', $message);
        $message = (string) preg_replace('/(api key provided:\s*)\S+/i', '$1[redacted]', $message);

        return (string) preg_replace('/\b[A-Za-z0-9_\-]{32,}\b/', '[redacted]', $message);
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
