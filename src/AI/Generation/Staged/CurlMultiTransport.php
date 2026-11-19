<?php
/**
 * Sends several provider requests at once over one curl_multi handle.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Generation\Staged;

/**
 * The page calls of a staged site plan are independent, so they run side by
 * side: the slowest page, not the sum of all pages, sets the wait. Each request
 * gets its own timeout, and each one is reported the moment it finishes so the
 * wizard can tick that page off while the others are still writing.
 *
 * $stagger holds back all but the first request for that many seconds (the
 * staged planner passes 0: it warms the provider's cache with a finished
 * call first, which is the only thing that reliably lets parallel requests
 * share it).
 *
 * Only used with providers implementing ParallelRequestsInterface, whose
 * endpoints are fixed (the request does not go through wp_safe_remote_post).
 */
class CurlMultiTransport
{
    /**
     * @return bool Whether this PHP can run it.
     */
    public static function available()
    {
        return function_exists('curl_multi_init') && function_exists('curl_init');
    }

    /**
     * @param array    $requests   key => {url, headers, body}.
     * @param int      $timeout    Seconds each request may take.
     * @param float    $stagger    Seconds before the second and later requests start.
     * @param callable $onComplete fn(string $key, int $status, string $body, string $error).
     *
     * @return void
     */
    public function send(array $requests, $timeout, $stagger, callable $onComplete)
    {
        if ($requests === []) {
            return;
        }

        $multi   = curl_multi_init();
        $handles = [];
        $pending = $requests;
        $start   = microtime(true);

        $add = function ($key, array $request) use ($multi, &$handles, $timeout) {
            $headers = [];

            foreach ((array) $request['headers'] as $name => $value) {
                $headers[] = $name . ': ' . $value;
            }

            $handle = curl_init((string) $request['url']);
            curl_setopt_array($handle, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => (string) $request['body'],
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => (int) $timeout,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
                CURLOPT_FOLLOWLOCATION => false,
            ]);

            if (defined('ABSPATH') && defined('WPINC') && is_readable(ABSPATH . WPINC . '/certificates/ca-bundle.crt')) {
                curl_setopt($handle, CURLOPT_CAINFO, ABSPATH . WPINC . '/certificates/ca-bundle.crt');
            }

            curl_multi_add_handle($multi, $handle);
            $handles[(int) $handle] = [(string) $key, $handle];
        };

        $firstKey = array_keys($pending)[0];
        $add($firstKey, $pending[$firstKey]);
        unset($pending[$firstKey]);

        do {
            if ($pending !== [] && microtime(true) - $start >= (float) $stagger) {
                foreach ($pending as $key => $request) {
                    $add($key, $request);
                }
                $pending = [];
            }

            curl_multi_exec($multi, $running);

            while (($info = curl_multi_info_read($multi)) !== false) {
                $handle = $info['handle'];
                $id     = (int) $handle;

                if (! isset($handles[$id])) {
                    continue;
                }

                $key    = $handles[$id][0];
                $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
                $body   = (string) curl_multi_getcontent($handle);
                $error  = $info['result'] === CURLE_OK ? '' : (string) curl_error($handle);

                curl_multi_remove_handle($multi, $handle);
                curl_close($handle);
                unset($handles[$id]);

                call_user_func($onComplete, $key, $status, $body, $error);
            }

            if ($handles !== [] || $pending !== []) {
                if (curl_multi_select($multi, 0.5) === -1) {
                    usleep(100000);
                }
            }
        } while ($handles !== [] || $pending !== []);

        curl_multi_close($multi);
    }
}
