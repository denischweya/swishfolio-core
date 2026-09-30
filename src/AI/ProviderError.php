<?php
/**
 * Translates provider failures into advice a site owner can act on.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI;

/**
 * Under BYOK the commonest failures are account problems, not plugin bugs: no
 * credit, a revoked key, a rate limit. Passing the provider's raw sentence
 * through tells the user what happened but not what to do, and leaks endpoint
 * detail. Each case here names the next action.
 *
 * The raw message is preserved as `detail` so it can sit behind a disclosure.
 */
class ProviderError
{
    const NO_CREDIT     = 'no_credit';
    const BAD_KEY       = 'bad_key';
    const RATE_LIMITED  = 'rate_limited';
    const BAD_MODEL     = 'bad_model';
    const PROVIDER_DOWN = 'provider_down';
    const NETWORK       = 'network';
    const UNKNOWN       = 'unknown';

    /**
     * @param int    $status HTTP status, or 0 for a transport failure.
     * @param string $raw    The provider's own message.
     *
     * @return array{code:string,message:string,detail:string}
     */
    public static function guidance($status, $raw)
    {
        $status = (int) $status;
        $raw    = (string) $raw;
        $text   = strtolower($raw);

        // Billing first: providers report it as 400 or 429 depending on vendor,
        // so the message matters more than the status.
        if (self::mentions($text, ['credit balance', 'insufficient_quota', 'exceeded your current quota', 'billing details', 'insufficient funds'])) {
            return self::make(
                self::NO_CREDIT,
                __('Your AI provider account has no credit left. Add credit or a payment method in your provider dashboard, then try again — this is a billing matter with them, not a problem with your key or this site.', 'swishfolio-core'),
                $raw
            );
        }

        if ($status === 0) {
            return self::make(
                self::NETWORK,
                __('This site could not reach your AI provider. Check the server\'s outbound connection and any firewall, then try again.', 'swishfolio-core'),
                $raw
            );
        }

        if ($status === 401 || $status === 403) {
            return self::make(
                self::BAD_KEY,
                __('Your AI provider rejected the API key. Check it was copied in full and is still active in your provider dashboard, then reconnect.', 'swishfolio-core'),
                $raw
            );
        }

        if ($status === 429) {
            return self::make(
                self::RATE_LIMITED,
                __('Your AI provider is rate limiting this site. Wait a moment and try again; if it persists, check the rate limits on your provider account.', 'swishfolio-core'),
                $raw
            );
        }

        if ($status === 404 || self::mentions($text, ['model', 'not_found_error'])) {
            return self::make(
                self::BAD_MODEL,
                __('Your AI provider does not offer the selected model to this account. Choose a different model on the Swish AI screen.', 'swishfolio-core'),
                $raw
            );
        }

        if ($status >= 500) {
            return self::make(
                self::PROVIDER_DOWN,
                __('Your AI provider is having trouble right now. Nothing was changed — try again shortly.', 'swishfolio-core'),
                $raw
            );
        }

        return self::make(
            self::UNKNOWN,
            __('AI couldn\'t complete this request. Nothing was changed.', 'swishfolio-core'),
            $raw
        );
    }

    /**
     * @param string   $text     Lowercased message.
     * @param string[] $needles  Fragments to look for.
     *
     * @return bool
     */
    private static function mentions($text, array $needles)
    {
        foreach ($needles as $needle) {
            if (strpos($text, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $code    Guidance code.
     * @param string $message User-facing sentence.
     * @param string $detail  Raw provider message.
     *
     * @return array{code:string,message:string,detail:string}
     */
    private static function make($code, $message, $detail)
    {
        return ['code' => $code, 'message' => $message, 'detail' => $detail];
    }
}
