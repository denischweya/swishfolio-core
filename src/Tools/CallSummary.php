<?php
/**
 * Human-readable description of a tool call.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools;

/**
 * Derived rather than authored, so no tool has to implement a preview() method
 * that would then be the one thing a new tool forgets.
 *
 * Post IDs are resolved to titles because "Set the front page — page: 12" asks
 * the user to approve a number. The same string is the audit log's summary, so
 * the log reads as what happened rather than as a payload dump.
 */
class CallSummary
{
    /** Input keys that name a post. */
    const POST_KEYS = ['postId', 'pageId', 'id', 'blogPageId', 'parentId'];

    /**
     * @param ToolInterface $tool  Tool being called.
     * @param array         $input Validated input.
     *
     * @return string
     */
    public static function describe(ToolInterface $tool, array $input)
    {
        $parts = [];

        foreach ($input as $key => $value) {
            $rendered = self::renderValue($key, $value);

            if (null !== $rendered) {
                $parts[] = $key . ': ' . $rendered;
            }
        }

        if ($parts === []) {
            return $tool->getTitle();
        }

        return $tool->getTitle() . ' — ' . implode(', ', $parts);
    }

    /**
     * @param string $key   Input key.
     * @param mixed  $value Input value.
     *
     * @return string|null Null when the value should not be shown.
     */
    private static function renderValue($key, $value)
    {
        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        if (is_int($value) || is_float($value)) {
            return in_array($key, self::POST_KEYS, true) ? self::renderPost((int) $value) : (string) $value;
        }

        if (is_string($value)) {
            if ('' === $value) {
                return null;
            }

            return mb_strlen($value) > 60 ? '"' . mb_substr($value, 0, 59) . '…"' : '"' . $value . '"';
        }

        if (is_array($value)) {
            return sprintf('%d items', count($value));
        }

        return null;
    }

    /**
     * @param int $postId Post ID.
     *
     * @return string
     */
    private static function renderPost($postId)
    {
        if ($postId <= 0) {
            return (string) $postId;
        }

        $title = function_exists('get_the_title') ? (string) get_the_title($postId) : '';

        return '' === $title ? '#' . $postId : sprintf('"%s" (#%d)', $title, $postId);
    }
}
