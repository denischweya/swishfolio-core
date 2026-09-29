<?php
/**
 * A standalone WordPress block-markup parser and serialiser for the eval suite.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tests\Evals\Support;

/**
 * "The result parses as valid block markup" cannot be asserted with a regex over
 * delimiters: unbalanced nesting, a malformed attribute JSON object and a token
 * left in an attribute all produce markup that looks fine to `grep` and breaks in
 * the editor. So this parses properly, and then proves the parse was faithful by
 * serialising it back and demanding the bytes match.
 *
 * The serialiser is core's algorithm, strtr escape map included, because a block
 * comment's attribute JSON escapes "--", "<", ">", "&" and a backslash, and those
 * escapes are exactly where composed content has been seen to corrupt. A
 * round-trip that is byte-identical proves both that the parse understood the
 * markup and that every attribute value survives storage unchanged.
 *
 * This is not a WordPress dependency: the unit suite has no `parse_blocks()`, and
 * borrowing one would make the eval suite untestable without a WordPress install.
 */
final class BlockMarkup
{
    /**
     * Core's block delimiter grammar, narrowed to what a delimiter can be.
     */
    const DELIMITER = '/<!--\s+(?P<closer>\/)?wp:(?P<ns>[a-z][a-z0-9_-]*\/)?(?P<name>[a-z][a-z0-9_-]*)\s+(?P<attrs>\{(?:(?!\}\s+\/?-->).)*\}\s+)?(?P<void>\/)?-->/s';

    /**
     * @param string $markup Block markup.
     *
     * @throws \RuntimeException When the markup is not parseable as blocks.
     *
     * @return array Parsed block list, core's shape.
     */
    public static function parse($markup)
    {
        $markup = (string) $markup;
        $index  = 0;
        $cursor = 0;

        return self::parseLevel($markup, self::tokenize($markup), $index, $cursor, null);
    }

    /**
     * @param array $blocks Parsed block list.
     *
     * @return string
     */
    public static function serialize(array $blocks)
    {
        $markup = '';

        foreach ($blocks as $block) {
            $markup .= self::serializeBlock($block);
        }

        return $markup;
    }

    /**
     * Parse, serialise, and report whether the bytes survived.
     *
     * @param string $markup Block markup.
     *
     * @throws \RuntimeException When the markup does not parse.
     *
     * @return array ['blocks' => array, 'serialized' => string, 'identical' => bool, 'diffAt' => int|null]
     */
    public static function roundTrip($markup)
    {
        $markup     = (string) $markup;
        $blocks     = self::parse($markup);
        $serialized = self::serialize($blocks);

        return [
            'blocks'     => $blocks,
            'serialized' => $serialized,
            'identical'  => $serialized === $markup,
            'diffAt'     => $serialized === $markup ? null : self::firstDifference($markup, $serialized),
        ];
    }

    /**
     * Every block name in the tree, in document order, freeform chunks excluded.
     *
     * @param array $blocks Parsed block list.
     *
     * @return string[]
     */
    public static function blockNames(array $blocks)
    {
        $names = [];

        foreach ($blocks as $block) {
            if ($block['blockName'] !== null) {
                $names[] = $block['blockName'];
            }

            foreach (self::blockNames($block['innerBlocks']) as $name) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Every attribute map in the tree, keyed by the block that carries it.
     *
     * @param array $blocks Parsed block list.
     *
     * @return array List of ['blockName' => string, 'attrs' => array].
     */
    public static function attributeSets(array $blocks)
    {
        $sets = [];

        foreach ($blocks as $block) {
            if ($block['blockName'] !== null) {
                $sets[] = ['blockName' => $block['blockName'], 'attrs' => $block['attrs']];
            }

            foreach (self::attributeSets($block['innerBlocks']) as $set) {
                $sets[] = $set;
            }
        }

        return $sets;
    }

    /**
     * @param string $markup Block markup.
     *
     * @return array Delimiter tokens in document order.
     */
    private static function tokenize($markup)
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($markup);

        while ($offset < $length && preg_match(self::DELIMITER, $markup, $matches, PREG_OFFSET_CAPTURE, $offset)) {
            $namespace = self::captured($matches, 'ns');

            $tokens[] = [
                'start'  => $matches[0][1],
                'length' => strlen($matches[0][0]),
                'closer' => self::captured($matches, 'closer') !== '',
                'name'   => ($namespace === '' ? 'core/' : $namespace) . $matches['name'][0],
                'attrs'  => trim(self::captured($matches, 'attrs')),
                'void'   => self::captured($matches, 'void') !== '',
            ];

            $offset = $matches[0][1] + strlen($matches[0][0]);
        }

        return $tokens;
    }

    /**
     * @param array  $matches preg_match result with offset capture.
     * @param string $group   Named group.
     *
     * @return string
     */
    private static function captured(array $matches, $group)
    {
        if (! isset($matches[$group]) || $matches[$group][1] === -1) {
            return '';
        }

        return (string) $matches[$group][0];
    }

    /**
     * @param string $markup    Full markup.
     * @param array  $tokens    Delimiter tokens.
     * @param int    $index     Token cursor, by reference.
     * @param int    $cursor    Byte cursor, by reference.
     * @param string $closeName Block this level must close with, or null at root.
     *
     * @throws \RuntimeException When delimiters do not balance or attributes do not decode.
     *
     * @return array
     */
    private static function parseLevel($markup, array $tokens, &$index, &$cursor, $closeName)
    {
        $blocks = [];
        $count  = count($tokens);

        while ($index < $count) {
            $token = $tokens[$index];
            $text  = substr($markup, $cursor, $token['start'] - $cursor);

            if ($token['closer']) {
                if ($closeName === null) {
                    throw new \RuntimeException(
                        sprintf('Closing delimiter for "%s" with no block open.', $token['name'])
                    );
                }

                if ($token['name'] !== $closeName) {
                    throw new \RuntimeException(
                        sprintf('Block "%s" is closed by "%s".', $closeName, $token['name'])
                    );
                }

                if ($text !== '') {
                    $blocks[] = self::freeform($text);
                }

                $cursor = $token['start'] + $token['length'];
                $index++;

                return $blocks;
            }

            if ($text !== '') {
                $blocks[] = self::freeform($text);
            }

            $cursor = $token['start'] + $token['length'];
            $index++;

            $attributes = [];

            if ($token['attrs'] !== '') {
                $attributes = json_decode($token['attrs'], true);

                if (! is_array($attributes)) {
                    throw new \RuntimeException(
                        sprintf('Block "%s" has attribute JSON that does not decode.', $token['name'])
                    );
                }
            }

            if ($token['void']) {
                $blocks[] = self::branch($token['name'], $attributes, []);
                continue;
            }

            $children = self::parseLevel($markup, $tokens, $index, $cursor, $token['name']);
            $blocks[] = self::branch($token['name'], $attributes, $children);
        }

        if ($closeName !== null) {
            throw new \RuntimeException(sprintf('Block "%s" is never closed.', $closeName));
        }

        $trailing = substr($markup, $cursor);

        if ($trailing !== '') {
            $blocks[] = self::freeform($trailing);
            $cursor   = strlen($markup);
        }

        return $blocks;
    }

    /**
     * @param string $text Freeform HTML between delimiters.
     *
     * @return array
     */
    private static function freeform($text)
    {
        return [
            'blockName'    => null,
            'attrs'        => [],
            'innerBlocks'  => [],
            'innerHTML'    => $text,
            'innerContent' => [$text],
        ];
    }

    /**
     * @param string $name       Block name.
     * @param array  $attributes Decoded attributes.
     * @param array  $children   Mixed list of freeform chunks and blocks.
     *
     * @return array
     */
    private static function branch($name, array $attributes, array $children)
    {
        $innerContent = [];
        $innerBlocks  = [];
        $innerHTML    = '';

        foreach ($children as $child) {
            if ($child['blockName'] === null) {
                $innerContent[] = $child['innerHTML'];
                $innerHTML     .= $child['innerHTML'];
                continue;
            }

            $innerContent[] = null;
            $innerBlocks[]  = $child;
        }

        return [
            'blockName'    => $name,
            'attrs'        => $attributes,
            'innerBlocks'  => $innerBlocks,
            'innerHTML'    => $innerHTML,
            'innerContent' => $innerContent,
        ];
    }

    /**
     * @param array $block Parsed block.
     *
     * @return string
     */
    private static function serializeBlock(array $block)
    {
        if ($block['blockName'] === null) {
            return $block['innerHTML'];
        }

        $content = '';
        $index   = 0;

        foreach ($block['innerContent'] as $chunk) {
            $content .= is_string($chunk) ? $chunk : self::serializeBlock($block['innerBlocks'][$index++]);
        }

        $name = strpos($block['blockName'], 'core/') === 0
            ? substr($block['blockName'], 5)
            : $block['blockName'];

        $attributes = $block['attrs'] === []
            ? ''
            : self::serializeAttributes($block['attrs']) . ' ';

        if ($content === '') {
            return sprintf('<!-- wp:%s %s/-->', $name, $attributes);
        }

        return sprintf('<!-- wp:%s %s-->%s<!-- /wp:%s -->', $name, $attributes, $content, $name);
    }

    /**
     * Core's serialize_block_attributes(), escape map included.
     *
     * @param array $attributes Attribute map.
     *
     * @return string
     */
    public static function serializeAttributes(array $attributes)
    {
        return strtr(
            (string) json_encode($attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            [
                '\\\\' => '\\u005c',
                '--'   => '\\u002d\\u002d',
                '<'    => '\\u003c',
                '>'    => '\\u003e',
                '&'    => '\\u0026',
                '\\"'  => '\\u0022',
            ]
        );
    }

    /**
     * @param string $expected Original markup.
     * @param string $actual   Re-serialised markup.
     *
     * @return int Byte offset of the first difference.
     */
    private static function firstDifference($expected, $actual)
    {
        $shortest = min(strlen($expected), strlen($actual));

        for ($offset = 0; $offset < $shortest; $offset++) {
            if ($expected[$offset] !== $actual[$offset]) {
                return $offset;
            }
        }

        return $shortest;
    }
}
