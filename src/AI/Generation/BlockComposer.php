<?php
/**
 * Turns a validated page plan into block markup.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Generation;

/**
 * A composing serializer, never a synthesising one.
 *
 * PHP cannot produce the save markup of a static block: serialize_block() builds
 * content from innerContent and innerBlocks only, and there is no PHP equivalent
 * of a block's JavaScript save(). A hand-built swishfolio-core/hero therefore
 * serialises to an empty self-closing comment and renders to nothing.
 *
 * So this class only ever emits markup it did not invent:
 *
 *  - a pattern section is the unit's own markup with its slot tokens replaced,
 *    a plain str_replace on the raw string with no HTML surgery at all;
 *  - a block section is a dynamic block, whose every attribute lives in the
 *    comment and whose markup is produced at render time.
 */
class BlockComposer
{
    /** @var PatternLibrary */
    private $library;

    public function __construct(PatternLibrary $library)
    {
        $this->library = $library;
    }

    /**
     * @param array $sections Validated section list from one plan page.
     *
     * @throws \RuntimeException When a unit cannot be resolved or a token is left behind.
     *
     * @return string post_content.
     */
    public function compose(array $sections)
    {
        $parts = [];

        foreach ($sections as $section) {
            if (isset($section['pattern'])) {
                $parts[] = $this->composePattern(
                    (string) $section['pattern'],
                    isset($section['content']) ? (array) $section['content'] : []
                );
                continue;
            }

            if (isset($section['block'])) {
                $parts[] = $this->composeBlock(
                    (string) $section['block'],
                    isset($section['attributes']) ? (array) $section['attributes'] : []
                );
            }
        }

        return implode("\n\n", $parts);
    }

    /**
     * @param string $unitId  Generation unit id.
     * @param array  $content Slot name => value.
     *
     * @throws \RuntimeException When a declared token is missing from the markup.
     *
     * @return string
     */
    public function composePattern($unitId, array $content)
    {
        $unit   = $this->library->get($unitId);
        $markup = $this->library->markup($unitId);

        if ($unit === null) {
            throw new \RuntimeException(sprintf('Unknown generation unit "%s".', $unitId));
        }

        $search  = [];
        $replace = [];

        foreach ($unit->getSlots() as $name => $slot) {
            $value = array_key_exists($name, $content) ? (string) $content[$name] : $slot->getDefault();

            $search[]  = $slot->getToken();
            $replace[] = $value;
        }

        $filled = $search === [] ? $markup : str_replace($search, $replace, $markup);

        if (strpos($filled, '{{slot:') !== false) {
            throw new \RuntimeException(
                sprintf('Generation unit "%s" left an unfilled slot token in its markup.', $unitId)
            );
        }

        return trim($filled);
    }

    /**
     * @param string $blockName  Dynamic block name.
     * @param array  $attributes Validated attributes.
     *
     * @return string
     */
    public function composeBlock($blockName, array $attributes)
    {
        return serialize_block([
            'blockName'    => $blockName,
            'attrs'        => $attributes,
            'innerBlocks'  => [],
            'innerHTML'    => '',
            'innerContent' => [],
        ]);
    }
}
