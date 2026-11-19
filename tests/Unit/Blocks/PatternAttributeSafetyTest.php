<?php

namespace SwishfolioCore\Tests\Unit\Blocks;

use PHPUnit\Framework\TestCase;

/**
 * Guards theme patterns against a class of silent breakage: a block comment
 * whose attribute JSON cannot produce the classes serialized beside it, which
 * makes Gutenberg flag "unexpected or invalid content" the moment it is inserted.
 *
 * The concrete trap is a block that declares BOTH attributes.align (its own
 * meaning, e.g. text alignment) and supports.align (WordPress's layout width).
 * WordPress writes the layout value into the same attribute, so a pattern
 * setting "align" gets a class derived from the layout value instead.
 */
class PatternAttributeSafetyTest extends TestCase
{
    const THEME_DIR = SFCORE_PLUGIN_DIR . '../../themes/swishfolio/';

    /**
     * @return array Block slug => block.json data, for blocks in this plugin.
     */
    private function blocks()
    {
        $blocks = [];

        foreach ((array) glob(SFCORE_PLUGIN_DIR . 'blocks/*/block.json') as $file) {
            $data = json_decode((string) file_get_contents($file), true);

            if (is_array($data) && isset($data['name'])) {
                $blocks[$data['name']] = $data;
            }
        }

        return $blocks;
    }

    private function patternFiles()
    {
        return (array) glob(self::THEME_DIR . 'patterns/*.php');
    }

    public function test_the_theme_is_present_so_this_guard_is_not_silently_vacuous()
    {
        $this->assertNotSame([], $this->patternFiles(), 'No theme patterns found — the guard would pass trivially.');
        $this->assertNotSame([], $this->blocks());
    }

    /**
     * A block whose own attribute name collides with a WordPress block support
     * cannot be set safely from a pattern.
     */
    public function test_no_pattern_sets_an_attribute_that_collides_with_a_block_support()
    {
        $colliding = [];

        foreach ($this->blocks() as $name => $data) {
            $attributes = isset($data['attributes']) ? array_keys($data['attributes']) : [];
            $supports   = isset($data['supports']) ? array_keys((array) $data['supports']) : [];

            foreach (array_intersect($attributes, $supports) as $attribute) {
                $colliding[$name][] = $attribute;
            }
        }

        $offenders = [];

        foreach ($this->patternFiles() as $file) {
            $markup = (string) file_get_contents($file);

            foreach ($colliding as $blockName => $attributes) {
                foreach ($attributes as $attribute) {
                    $pattern = '/<!--\s*wp:' . preg_quote($blockName, '/') . '\s*\{[^}]*"' . preg_quote($attribute, '/') . '"\s*:/';

                    if (preg_match($pattern, $markup)) {
                        $offenders[] = basename($file) . ' sets ' . $blockName . '.' . $attribute;
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "A pattern sets an attribute that WordPress's block supports also write, so the "
            . "serialized classes cannot match the attribute JSON: " . implode('; ', $offenders)
        );
    }
}
