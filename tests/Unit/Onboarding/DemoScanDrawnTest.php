<?php

namespace SwishfolioCore\Tests\Unit\Onboarding;

require_once SFCORE_PLUGIN_DIR . 'tests/Unit/Tools/wp-tools-stubs.php';

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\Generation\PatternLibrary;
use SwishfolioCore\AI\Onboarding\DemoScan;

/**
 * The leftover scan reads words drawn in SVG graphics and readable
 * attributes, and neutralises what has no owner equivalent.
 */
class DemoScanDrawnTest extends TestCase
{
    public function test_drawn_ring_text_is_found_and_becomes_the_business_name()
    {
        $scan   = new DemoScan(['drawn' => ['Open Tue–Sun · Reservations · Walk-ins at the counter ·']]);
        $markup = '<a class="lu-badge"><svg><text><textPath href="#r" textLength="380">Open Tue–Sun · Reservations · Walk-ins at the counter · </textPath></text></svg></a>';

        $this->assertNotEmpty($scan->scan(DemoScan::visible($markup)));

        $out = $scan->neutralise($markup, 'Grind Lagos');
        $this->assertStringContainsString('textLength="380">Grind Lagos · Grind Lagos', $out);
        $this->assertSame([], $scan->scan(DemoScan::visible($out)));
    }

    public function test_demo_places_and_people_are_cut_from_alt_text()
    {
        $scan = new DemoScan(['places' => ['Austin', 'Rotterdam'], 'people' => ['Tomás Valadares']]);
        $out  = $scan->neutralise('<img src="a.jpg" alt="Inside the office in Austin"><img alt="Chef Tomás Valadares plating a course"><img alt="A packaging system for a Rotterdam roaster"><p>We moved from Austin.</p>', 'Grind');

        $this->assertStringContainsString('alt="Inside the office"', $out);
        $this->assertStringContainsString('alt="Chef plating a course"', $out);
        $this->assertStringContainsString('alt="A packaging system for a roaster"', $out);
        // Running copy is reported, never cut.
        $this->assertStringContainsString('<p>We moved from Austin.</p>', $out);
    }

    /**
     * Every kit: once all its text and attribute slots are rewritten, no demo
     * detail is left anywhere in a unit except image alt text, which the
     * build's finish step neutralises.
     */
    public function test_no_kit_hides_demo_details_outside_its_slots()
    {
        $left = [];
        foreach (PatternLibrary::shipped()->kits() as $kit) {
            $scan = new DemoScan($kit->getDemo());
            foreach (['section', 'header', 'footer', 'page'] as $scope) {
                foreach ($kit->units($scope) as $unit) {
                    $md    = $kit->markup($unit->getId());
                    $slots = $kit->slots($unit->getId())->slots();
                    uasort($slots, static function ($a, $b) {
                        return $b['start'] - $a['start'];
                    });
                    foreach ($slots as $slot) {
                        if ($slot['type'] !== 'image') {
                            $md = substr($md, 0, $slot['start']) . 'X' . substr($md, $slot['end']);
                        }
                    }
                    $md = $scan->neutralise((string) preg_replace('/\{\{[^}]*\}\}/', '', $md), 'Owner Co');
                    foreach ($scan->scan(DemoScan::visible($md)) as $hit) {
                        $left[] = $kit->getSlug() . ' ' . $unit->getId() . ': ' . $hit['text'];
                    }
                }
            }
        }

        $this->assertSame([], $left);
    }
}
