<?php
/**
 * Runs a set of AI requests concurrently where the provider allows it.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Generation\Staged;

use SwishfolioCore\AI\AIRequest;
use SwishfolioCore\AI\Contracts\AIProviderInterface;
use SwishfolioCore\AI\Contracts\ParallelRequestsInterface;
use SwishfolioCore\AI\ProviderException;

/**
 * Every finished request is reported as (key, AIResponse|null,
 * ProviderException|null, decoded body|null, seconds). The decoded body is
 * what the metering layer saw through swishfolio_ai_response, so a caller that
 * rejects the reply afterwards can void exactly that usage
 * (swishfolio_ai_usage_void) and the owner pays only for pages that are kept.
 *
 * Providers without ParallelRequestsInterface (or a PHP without curl) run the
 * same requests one after the other through generate(): slower, same result.
 */
class ParallelRunner
{
    /** @var AIProviderInterface */
    private $provider;

    /** @var CurlMultiTransport|object|null */
    private $transport;

    /**
     * @param AIProviderInterface $provider  Provider.
     * @param object|null         $transport Anything with send() like CurlMultiTransport; null picks one.
     */
    public function __construct(AIProviderInterface $provider, $transport = null)
    {
        $this->provider  = $provider;
        $this->transport = $transport;

        if ($this->transport === null && $provider instanceof ParallelRequestsInterface && CurlMultiTransport::available()) {
            $this->transport = new CurlMultiTransport();
        }
    }

    /**
     * @return bool Whether requests really run side by side.
     */
    public function isParallel()
    {
        return $this->transport !== null && $this->provider instanceof ParallelRequestsInterface;
    }

    /**
     * @param AIRequest[] $requests key => request.
     * @param callable    $onDone   fn($key, $response, $error, $decoded, $seconds).
     * @param int         $timeout  Seconds per request.
     * @param float       $stagger  Seconds the first request runs alone (cache warm-up).
     *
     * @return void
     */
    public function run(array $requests, callable $onDone, $timeout, $stagger = 0.0)
    {
        if ($this->isParallel()) {
            $this->runParallel($requests, $onDone, $timeout, $stagger);

            return;
        }

        $this->runSequential($requests, $onDone, $timeout);
    }

    /**
     * @param AIRequest[] $requests Requests.
     * @param callable    $onDone   Completion callback.
     * @param int         $timeout  Seconds per request.
     * @param float       $stagger  Warm-up seconds.
     *
     * @return void
     */
    private function runParallel(array $requests, callable $onDone, $timeout, $stagger)
    {
        $http    = [];
        $started = microtime(true);

        foreach ($requests as $key => $request) {
            $http[$key] = $this->provider->httpRequest($request);
        }

        $this->transport->send($http, (int) $timeout, (float) $stagger, function ($key, $status, $body, $error) use ($requests, $onDone, $started) {
            $seconds = round(microtime(true) - $started, 1);
            $request = $requests[$key];

            if ($error !== '' || $status < 200 || $status >= 300) {
                $message = $error !== ''
                    ? $error
                    : $this->provider->httpError($body, $status);

                call_user_func($onDone, $key, null, new ProviderException($message, (int) $status), null, $seconds);

                return;
            }

            $decoded = json_decode($body, true);

            if (! is_array($decoded)) {
                call_user_func($onDone, $key, null, new ProviderException('Provider returned a non-JSON body.', (int) $status), null, $seconds);

                return;
            }

            // The same metering seam generate() fires, once per HTTP response.
            if (function_exists('do_action')) {
                do_action('swishfolio_ai_response', $decoded, $this->provider->getSlug());
            }

            try {
                $response = $this->provider->httpResponse($request, $decoded);
            } catch (ProviderException $e) {
                call_user_func($onDone, $key, null, $e, $decoded, $seconds);

                return;
            }

            call_user_func($onDone, $key, $response, null, $decoded, $seconds);
        });
    }

    /**
     * @param AIRequest[] $requests Requests.
     * @param callable    $onDone   Completion callback.
     * @param int         $timeout  Seconds per request.
     *
     * @return void
     */
    private function runSequential(array $requests, callable $onDone, $timeout)
    {
        $seen   = null;
        $listen = function ($decoded) use (&$seen) {
            $seen = (array) $decoded;
        };
        $limit  = function () use ($timeout) {
            return (int) $timeout;
        };

        if (function_exists('add_action')) {
            add_action('swishfolio_ai_response', $listen, 1, 1);
            add_filter('swishfolio_ai_http_timeout', $limit, 20);
        }

        try {
            foreach ($requests as $key => $request) {
                $seen    = null;
                $started = microtime(true);

                try {
                    $response = $this->provider->generate($request);
                    call_user_func($onDone, $key, $response, null, $seen, round(microtime(true) - $started, 1));
                } catch (ProviderException $e) {
                    call_user_func($onDone, $key, null, $e, $seen, round(microtime(true) - $started, 1));
                }
            }
        } finally {
            if (function_exists('remove_action')) {
                remove_action('swishfolio_ai_response', $listen, 1);
                remove_filter('swishfolio_ai_http_timeout', $limit, 20);
            }
        }
    }
}
