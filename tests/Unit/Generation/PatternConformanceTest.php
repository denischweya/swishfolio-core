<?php

namespace SwishfolioCore\Tests\Unit\Generation;

use SwishfolioCore\AI\Generation\BlockComposer;
use SwishfolioCore\AI\Generation\PatternUnit;

/**
 * The generation counterpart of ManifestConformanceTest: it proves the shipped
 * library is internally consistent, so a new unit cannot be added in a shape that
 * silently produces a broken page.
 *
 * The invariant that matters most is the one in the design's decisive constraint:
 * PHP cannot run a static block's JavaScript save(), so a block comment with no
 * markup renders to nothing. A generation unit containing one would produce a
 * page that looks right in the editor and is blank on the site.
 */
class PatternConformanceTest extends GenerationTestCase
{
    /** @var string[] */
    private static $shippedIds = [
        'swiss-hero',
        'swiss-services-trio',
        'swiss-statement',
        'swiss-contact-cta',
        'swiss-about-intro',
        'page-swiss-homepage',
        'page-swiss-about',
        'page-swiss-services',
        'page-swiss-contact',
    ];

    public function test_every_shipped_sidecar_parses()
    {
        $library = $this->shippedLibrary();

        foreach (self::$shippedIds as $id) {
            $this->assertInstanceOf(
                PatternUnit::class,
                $library->get($id),
                sprintf('Generation unit "%s" did not parse.', $id)
            );
        }

        // Every sidecar on disk must have parsed. Pinning an exact total would
        // break each time a pattern is added; a silently-dropped sidecar
        // (malformed JSON, bad scope) is the failure worth catching.
        $sidecars = (array) glob(SFCORE_PLUGIN_DIR . 'src/AI/Generation/Patterns/*.json');

        $this->assertCount(
            count($sidecars),
            $library->all(),
            'A sidecar on disk failed to parse into a unit and was silently skipped.'
        );

        $this->assertGreaterThanOrEqual(count(self::$shippedIds), count($library->all()));
    }

    public function test_no_shipped_unit_needs_media()
    {
        foreach ($this->shippedLibrary()->all() as $id => $unit) {
            $this->assertSame(
                0,
                $unit->getRequiresMedia(),
                sprintf('Unit "%s" needs media, which generation cannot supply.', $id)
            );
        }
    }

    public function test_every_shipped_unit_declares_itself_kses_stable()
    {
        foreach ($this->shippedLibrary()->all() as $id => $unit) {
            $this->assertTrue(
                $unit->isKsesStable(),
                sprintf('Unit "%s" is not marked KSES stable, so it must not ship.', $id)
            );
        }
    }

    public function test_every_declared_slot_token_occurs_in_its_markup()
    {
        $library = $this->shippedLibrary();

        foreach ($this->fileUnits() as $id => $unit) {
            $markup = $library->markup($id);

            foreach ($unit->getSlots() as $name => $slot) {
                $this->assertStringContainsString(
                    $slot->getToken(),
                    $markup,
                    sprintf('Unit "%s" declares slot "%s" but its token is not in the markup.', $id, $name)
                );
            }
        }
    }

    public function test_every_token_in_markup_is_a_declared_slot()
    {
        $library = $this->shippedLibrary();

        foreach ($this->fileUnits() as $id => $unit) {
            preg_match_all('/\{\{slot:([a-zA-Z0-9]+)\}\}/', $library->markup($id), $matches);

            foreach (array_unique($matches[1]) as $name) {
                $this->assertNotNull(
                    $unit->getSlot($name),
                    sprintf('Unit "%s" markup uses token "%s" which no slot declares.', $id, $name)
                );
            }
        }
    }

    public function test_every_slot_default_fits_its_own_maximum_length()
    {
        foreach ($this->shippedLibrary()->all() as $id => $unit) {
            foreach ($unit->getSlots() as $name => $slot) {
                $max = $slot->getMaxLength();

                if ($max === null) {
                    continue;
                }

                $this->assertLessThanOrEqual(
                    $max,
                    strlen($slot->getDefault()),
                    sprintf('Unit "%s" slot "%s" has a default longer than its own maxLength.', $id, $name)
                );
            }
        }
    }

    public function test_composing_with_defaults_leaves_no_token_behind()
    {
        $composer = new BlockComposer($this->shippedLibrary());

        foreach ($this->fileUnits() as $id => $unit) {
            $composed = $composer->composePattern($id, []);

            $this->assertStringNotContainsString('{{slot:', $composed, sprintf('Unit "%s" left a token.', $id));
            $this->assertNotSame('', trim($composed));
        }
    }

    public function test_no_file_unit_contains_an_attribute_only_plugin_block()
    {
        $library = $this->shippedLibrary();

        foreach ($this->fileUnits() as $id => $unit) {
            $this->assertSame(
                [],
                $this->staticSelfClosingBlocks($library->markup($id)),
                sprintf(
                    'Unit "%s" serialises a static Swishfolio block from attributes alone, which renders to nothing.',
                    $id
                )
            );
        }
    }

    public function test_no_file_unit_carries_inline_svg_or_script()
    {
        $library = $this->shippedLibrary();

        foreach ($this->fileUnits() as $id => $unit) {
            $markup = $library->markup($id);

            $this->assertStringNotContainsString('<svg', $markup, sprintf('Unit "%s" has inline SVG, which KSES strips.', $id));
            $this->assertStringNotContainsString('<script', $markup, sprintf('Unit "%s" has a script tag.', $id));
        }
    }

    public function test_every_file_unit_has_balanced_block_delimiters()
    {
        $library = $this->shippedLibrary();

        foreach ($this->fileUnits() as $id => $unit) {
            $markup = $library->markup($id);

            preg_match_all('/<!-- wp:[^\n]*?-->/', $markup, $opening);
            preg_match_all('/<!-- \/wp:[^\n]*?-->/', $markup, $closing);

            $paired = 0;

            foreach ($opening[0] as $delimiter) {
                if (substr($delimiter, -5) !== '/-->') {
                    $paired++;
                }
            }

            $this->assertSame(
                $paired,
                count($closing[0]),
                sprintf('Unit "%s" has unbalanced block delimiters.', $id)
            );
        }
    }

    public function test_every_referenced_theme_pattern_exists_and_is_composable()
    {
        $patternDir = dirname(SFCORE_PLUGIN_DIR, 2) . '/themes/swishfolio/patterns';

        if (! is_dir($patternDir)) {
            $this->markTestSkipped('The Swishfolio theme is not checked out beside this plugin.');
        }

        $slugs = [];

        foreach ((array) glob($patternDir . '/*.php') as $file) {
            $contents = (string) file_get_contents($file);

            if (preg_match('/^\s*\*\s*Slug:\s*(\S+)/m', $contents, $matches)) {
                $slugs[$matches[1]] = $contents;
            }
        }

        foreach ($this->shippedLibrary()->all() as $id => $unit) {
            if ($unit->getRef() === null) {
                continue;
            }

            $this->assertArrayHasKey(
                $unit->getRef(),
                $slugs,
                sprintf('Unit "%s" references pattern "%s", which the theme does not declare.', $id, $unit->getRef())
            );

            $this->assertSame(
                [],
                $this->staticSelfClosingBlocks($slugs[$unit->getRef()]),
                sprintf(
                    'Pattern "%s" serialises a static Swishfolio block from attributes alone, so it renders to nothing when written as post content.',
                    $unit->getRef()
                )
            );
        }
    }

    /**
     * @return \SwishfolioCore\AI\Generation\PatternUnit[]
     */
    private function fileUnits()
    {
        $units = [];

        foreach ($this->shippedLibrary()->all() as $id => $unit) {
            if ($unit->getFile() !== null) {
                $units[$id] = $unit;
            }
        }

        return $units;
    }

    /**
     * Block slugs that WordPress renders in PHP, so a self-closing block
     * comment is the correct serialization for them rather than a defect.
     *
     * Derived from block metadata, not hardcoded: a block is dynamic when it
     * declares a `render` file or ships no save.js at all.
     *
     * @return string[] Full block names.
     */
    private function dynamicBlockNames()
    {
        $dynamic = [];

        foreach ((array) glob(SFCORE_PLUGIN_DIR . 'blocks/*/block.json') as $file) {
            $dir  = dirname($file);
            $data = json_decode((string) file_get_contents($file), true);

            if (! is_array($data) || ! isset($data['name'])) {
                continue;
            }

            if (! empty($data['render']) || ! file_exists($dir . '/src/save.js')) {
                $dynamic[] = $data['name'];
            }
        }

        return $dynamic;
    }

    /**
     * Self-closing STATIC Swishfolio blocks in some markup.
     *
     * A static block serialized from attributes alone renders to nothing,
     * because its markup lives in save.js and was never written out. A
     * self-closing DYNAMIC block is correct and must not be reported.
     *
     * @param string $markup Block markup.
     *
     * @return string[] Offending block names.
     */
    private function staticSelfClosingBlocks($markup)
    {
        $dynamic  = $this->dynamicBlockNames();
        $offenders = [];

        if (preg_match_all('/<!--\s*wp:(swishfolio-core\/[a-z0-9-]+)[^>]*?\/-->/', $markup, $matches)) {
            foreach ($matches[1] as $blockName) {
                if (! in_array($blockName, $dynamic, true)) {
                    $offenders[] = $blockName;
                }
            }
        }

        return array_values(array_unique($offenders));
    }
}
