<?php

namespace SwishfolioCore\Tests\Unit\Generation;

use SwishfolioCore\AI\Generation\BlockComposer;

/**
 * Every kit seed/extract-patterns.php shipped loads, and its units can be
 * composed: files exist, image placeholders have assets, links name the kit's
 * own pages, and the copy the planner sees carries no em dash.
 */
class KitConformanceTest extends GenerationTestCase
{
    public function test_every_template_has_a_kit()
    {
        $this->assertGreaterThanOrEqual(16, count($this->shippedLibrary()->kits()));
    }

    public function test_every_kit_is_complete_and_composable()
    {
        foreach ($this->shippedLibrary()->kits() as $slug => $kit) {
            $keys     = $kit->getPageKeys();
            $sections = $kit->units('section');

            $this->assertNotEmpty($sections, $slug);
            $this->assertContains($kit->getFrontPage(), $keys, $slug);
            $this->assertNotEmpty($kit->getDesignFile(), $slug . ' design.json');

            foreach ($kit->getPages() as $page) {
                foreach ($page['sections'] as $unitId) {
                    // A form tangled with a script is held back on purpose (Kit::usable()).
                    $held = $kit->get($unitId) !== null && ! $kit->usable($kit->get($unitId));
                    $this->assertTrue($held || $kit->section($unitId) !== null, $slug . ' page ' . $page['key'] . ' names ' . $unitId);
                }
            }

            $raw      = json_decode((string) file_get_contents($kit->getDir() . '/kit.json'), true);
            $business = preg_split('/\s+/', (string) $raw['demo']['business'])[0];

            foreach ($kit->units() as $unitId => $unit) {
                $where  = $slug . '/' . $unitId;
                $markup = $kit->markup($unitId);

                $this->assertStringNotContainsString('—', $unit->getSummary(), $where);
                $this->assertDoesNotMatchRegularExpression('/\{\{(art|page|slot)[:}-]/', $markup, $where . ' keeps a demo placeholder');
                $this->assertDoesNotMatchRegularExpression('/wp-image-\d|"id":\d/', $markup, $where . ' keeps a demo attachment id');
                $this->assertDoesNotMatchRegularExpression('/(href|action)="(https?:\/\/|\/[^"#\/])/', $markup, $where . ' links into the demo site');

                preg_match_all('/\balt="([^"]*)"/', $markup, $alts);
                foreach ($alts[1] as $alt) {
                    $this->assertStringNotContainsString($business, $alt, $where . ' image alt names the demo business');
                }

                preg_match_all('/\{\{link:([a-z0-9-]+)\}\}/', $markup, $links);
                foreach ($links[1] as $key) {
                    $this->assertContains($key, $keys, $where . ' links to an unknown page');
                }

                preg_match_all('/\{\{kit-image:([a-z0-9_-]+)\}\}/', $markup, $images);
                foreach ($images[1] as $name) {
                    $this->assertNotNull($kit->assetUrl($name), $where . ' image ' . $name);
                    $this->assertFileExists(SFCORE_PLUGIN_DIR . 'assets/kits/' . $slug . '/' . $kit->getAssets()[$name]['file'], $where . ' image ' . $name);
                }

                if ($unit->getScope() === 'section') {
                    $composed = (new BlockComposer($kit))->composePattern($unitId, []);
                    $this->assertStringNotContainsString('{{', $composed, $where);
                }
            }
        }
    }
}
