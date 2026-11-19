<?php
/**
 * Optional provider capability: hand out raw HTTP requests so several can run at once.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Contracts;

use SwishfolioCore\AI\AIRequest;
use SwishfolioCore\AI\AIResponse;

/**
 * generate() sends one request and waits. The staged site plan writes every
 * page at the same time, so it needs the request as data (URL, headers, body)
 * to put several on one curl_multi handle, and a way to turn each decoded
 * reply back into an AIResponse exactly as generate() would.
 *
 * Only providers whose endpoint is fixed implement this: the parallel
 * transport does not go through wp_safe_remote_post(), so a provider that
 * lets a site choose its own base URL must not offer it.
 */
interface ParallelRequestsInterface
{
    /**
     * @param AIRequest $request Request.
     *
     * @return array{url: string, headers: array, body: string}
     */
    public function httpRequest(AIRequest $request);

    /**
     * @param AIRequest $request Request that produced the reply.
     * @param array     $decoded Decoded HTTP response body.
     *
     * @throws \SwishfolioCore\AI\ProviderException When the reply is unusable.
     *
     * @return AIResponse
     */
    public function httpResponse(AIRequest $request, array $decoded);

    /**
     * @param string $raw    Raw error body.
     * @param int    $status HTTP status.
     *
     * @return string Safe error message.
     */
    public function httpError($raw, $status);
}
