<?php
/**
 * Translates tool names across the provider boundary.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Conversation;

/**
 * Both providers constrain tool names to ^[a-zA-Z0-9_-]{1,64}$, so
 * "swishfolio/site-info" is rejected outright — a detail that costs a confusing
 * 400 from the provider rather than anything that looks like a naming problem.
 *
 * The slash is the only illegal character; hyphens are fine. So the mapping
 * replaces just the slash, and reversal splits on the first "__". That is
 * unambiguous only because no tool name contains "__" itself, which is asserted
 * rather than assumed: a future "swishfolio/a__b" would otherwise map back to
 * "swishfolio/a/b" and silently route to a tool that does not exist.
 */
class ToolNameMap
{
    const SEPARATOR = '__';

    /**
     * @param string $name Canonical tool name, e.g. "swishfolio/site-info".
     *
     * @throws \LogicException When the name cannot round-trip.
     *
     * @return string Provider-safe name, e.g. "swishfolio__site-info".
     */
    public static function toProvider($name)
    {
        $name = (string) $name;

        if (strpos($name, self::SEPARATOR) !== false) {
            throw new \LogicException(
                sprintf('Tool name "%s" contains "%s", which the provider mapping reserves.', $name, self::SEPARATOR)
            );
        }

        $mapped = str_replace('/', self::SEPARATOR, $name);

        if (! preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $mapped)) {
            throw new \LogicException(
                sprintf('Tool name "%s" cannot be made provider-safe; it maps to "%s".', $name, $mapped)
            );
        }

        return $mapped;
    }

    /**
     * @param string $name Provider-safe name.
     *
     * @return string Canonical tool name.
     */
    public static function fromProvider($name)
    {
        $parts = explode(self::SEPARATOR, (string) $name, 2);

        return count($parts) === 2 ? $parts[0] . '/' . $parts[1] : (string) $name;
    }
}
