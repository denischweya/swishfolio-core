<?php
/**
 * The only class in this suite that is allowed to open a socket.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tests\Evals\Support;

use SwishfolioCore\AI\Providers\OpenAICompatibleProvider;

/**
 * The production providers speak to the network through wp_remote_post(), which
 * does not exist outside WordPress. AbstractAIProvider puts that call behind a
 * single protected seam precisely so a caller can substitute one, and this
 * substitutes a cURL request — so the eval suite can reach a real provider from a
 * plain PHPUnit run without a WordPress bootstrap, and without the unit stubs
 * ever gaining a function that could make a request by accident.
 *
 * Everything above the seam is the shipped provider: the same request body, the
 * same JSON Schema enforcement, the same retry and error mapping. What is not
 * exercised is WordPress's own HTTP layer, which is stated plainly rather than
 * implied.
 *
 * Never instantiated unless LiveMode::isEnabled() is true. The endpoint comes
 * from the operator's own environment and the body is JSON built by the shipped
 * provider; no shell is involved at any point.
 */
final class LiveEvalProvider extends OpenAICompatibleProvider
{
    /** @var int */
    private $timeout;

    /**
     * @param array $credentials api_key and base_url.
     * @param int   $timeout     Seconds.
     */
    public function __construct(array $credentials, $timeout = 120)
    {
        $this->setCredentials($credentials);
        $this->timeout = (int) $timeout;
    }

    public function getSlug()
    {
        return 'live-eval';
    }

    /**
     * @param string $url  Endpoint.
     * @param array  $args Request arguments in wp_remote_post shape.
     *
     * @return array|\WP_Error_Stub
     */
    protected function post($url, array $args)
    {
        if (! function_exists('curl_init')) {
            return new \WP_Error_Stub('The cURL extension is required for live provider evals.');
        }

        $headers = [];

        foreach ((array) (isset($args['headers']) ? $args['headers'] : []) as $name => $value) {
            $headers[] = $name . ': ' . $value;
        }

        $handle = curl_init($url);

        curl_setopt($handle, CURLOPT_POST, true);
        curl_setopt($handle, CURLOPT_POSTFIELDS, isset($args['body']) ? $args['body'] : '');
        curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_TIMEOUT, $this->timeout);

        $body   = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error  = curl_error($handle);

        curl_close($handle);

        if ($body === false) {
            return new \WP_Error_Stub($error === '' ? 'The provider could not be reached.' : $error);
        }

        return [
            'response' => ['code' => $status],
            'body'     => (string) $body,
        ];
    }
}
