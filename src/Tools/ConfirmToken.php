<?php
/**
 * Proof that the user saw the plan being executed.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools;

/**
 * The gate has to distinguish "the user approved this" from "the model asserted
 * that the user approved this". A boolean in the request cannot: model output
 * reaches the runner through the same payload.
 *
 * So the token is keyed on the exact plan, the user, and an issue time, and is
 * signed with wp_hash — which uses the site's salts. The UI can echo a token it
 * was handed with a preview; model output cannot manufacture one, and a token
 * for one plan does not approve a different plan.
 */
class ConfirmToken
{
    /** Seconds a token stays valid. */
    const TTL = 900;

    /**
     * @param array $calls  Planned calls, each ['name' => string, 'input' => array].
     * @param int   $userId User the token is issued to.
     *
     * @return string
     */
    public static function issue(array $calls, $userId)
    {
        $issuedAt = time();

        return $issuedAt . '.' . self::sign($calls, $userId, $issuedAt);
    }

    /**
     * @param string $token  Token from the request.
     * @param array  $calls  Calls being executed.
     * @param int    $userId Current user.
     *
     * @return bool
     */
    public static function verify($token, array $calls, $userId)
    {
        $parts = explode('.', (string) $token, 2);

        if (count($parts) !== 2 || ! ctype_digit($parts[0])) {
            return false;
        }

        $issuedAt = (int) $parts[0];

        if ($issuedAt > time() + 60 || $issuedAt < time() - self::TTL) {
            return false;
        }

        return hash_equals(self::sign($calls, $userId, $issuedAt), $parts[1]);
    }

    /**
     * Canonicalises the plan before signing, so key order in the request cannot
     * make a token for the same plan verify differently.
     *
     * @param array $calls    Planned calls.
     * @param int   $userId   User the token is for.
     * @param int   $issuedAt Issue timestamp.
     *
     * @return string
     */
    private static function sign(array $calls, $userId, $issuedAt)
    {
        $canonical = [];

        foreach ($calls as $call) {
            $name  = isset($call['name']) ? (string) $call['name'] : '';
            $input = isset($call['input']) && is_array($call['input']) ? $call['input'] : [];

            $canonical[] = [$name, self::sortDeep($input)];
        }

        // The site is part of what was approved: wp_hash() is network-wide, so
        // without it a token from one site would approve the same calls on
        // another site the same user belongs to.
        $siteId = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 0;

        return wp_hash(wp_json_encode([$canonical, (int) $userId, (int) $issuedAt, $siteId]));
    }

    /**
     * @param array $value Array to sort recursively by key.
     *
     * @return array
     */
    private static function sortDeep(array $value)
    {
        ksort($value);

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::sortDeep($item);
            }
        }

        return $value;
    }
}
