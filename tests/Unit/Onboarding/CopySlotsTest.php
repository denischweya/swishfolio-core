<?php

namespace SwishfolioCore\Tests\Unit\Onboarding;

require_once SFCORE_PLUGIN_DIR . 'tests/Unit/Tools/wp-tools-stubs.php';

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\Onboarding\CopySlots;
use SwishfolioCore\AI\Onboarding\TemplateCustomiser;
use SwishfolioCore\REST\CustomiseController;
use SwishfolioCore\Tools\Write\SetDesignTokensTool;

/**
 * Template customisation rewrites copy in place; the design must come through
 * byte for byte everywhere the edits do not name.
 */
class CopySlotsTest extends TestCase
{
    const PAGE = <<<'HTML'
<!-- wp:group {"className":"x-hero"} -->
<div class="wp-block-group x-hero"><!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Comfort, <span class="x-hot">fixed fast</span>.</h1>
<!-- /wp:heading -->

<!-- wp:html -->
<a class="x-phone" href="tel:+13035550148" aria-label="Call Northwind"><svg viewBox="0 0 24 24"><path d="M1 1h2"/></svg><span>(303) 555-0148</span></a>
<ul class="x-list"><li class="x-item"><b>Repairs</b> same day</li><li class="x-item"><b>Installs</b> in a week</li><li class="x-item"><b>Plans</b> from $9</li></ul>
<!-- /wp:html -->

<!-- wp:image {"id":12,"sizeSlug":"large"} -->
<figure class="wp-block-image size-large"><img src="https://old.test/hero.jpg" alt="Old house" class="wp-image-12"/></figure>
<!-- /wp:image --></div>
<!-- /wp:group -->

<!-- wp:swishfolio-core/hero {"title":"Custom"} -->
<div class="wp-block-swishfolio-core-hero"><h2>Custom</h2></div>
<!-- /wp:swishfolio-core/hero -->
HTML;

    private function slots($content = self::PAGE)
    {
        return new CopySlots($content, static function ($html) {
            return strip_tags($html, '<b><span><em><a>');
        });
    }

    private function idFor(CopySlots $slots, $type, $needle)
    {
        foreach ($slots->slots() as $id => $slot) {
            $value = isset($slot['value']) ? $slot['value'] : '';
            if ($slot['type'] === $type && strpos($value, $needle) !== false) {
                return $id;
            }
        }
        $this->fail("No $type slot containing $needle");
    }

    public function testDifferentHtmlBlocksInAGridAreNotRemovableUnits()
    {
        $grid = "<!-- wp:group {\"className\":\"g\"} -->\n<div class=\"wp-block-group g\"><!-- wp:group {\"className\":\"g-grid\"} -->\n<div class=\"wp-block-group g-grid\">"
            . "<!-- wp:html -->\n<div class=\"score\"><p>4.9 from 2,312 reviews</p></div>\n<!-- /wp:html -->\n\n"
            . "<!-- wp:html -->\n<div class=\"quotes\"><blockquote class=\"q\"><p>Great</p></blockquote><blockquote class=\"q\"><p>Fast</p></blockquote></div>\n<!-- /wp:html -->"
            . "</div>\n<!-- /wp:group --></div>\n<!-- /wp:group -->";
        $labels = array_column($this->slots($grid)->units(), 'label');

        $this->assertNotContains('html', $labels);
        $this->assertSame(2, count(array_keys($labels, '<blockquote.q>', true)));
    }

    public function testPhoneLinkWhoseLabelIsATextSlotStillExposesItsHref()
    {
        $html  = "<!-- wp:html -->\n<div class=\"c\"><p>Call us</p><a class=\"btn\" href=\"tel:+13035550148\">(303) 555-0148</a></div>\n<!-- /wp:html -->";
        $slots = $this->slots($html);
        $href  = null;
        $label = null;
        foreach ($slots->slots() as $id => $slot) {
            if ($slot['type'] === 'attr' && $slot['attr'] === 'href') {
                $href = $id;
            }
            if ($slot['value'] === '(303) 555-0148') {
                $label = $id;
            }
        }

        $this->assertNotNull($href);
        $this->assertNotNull($label);
        $out = $slots->apply(['texts' => [$label => '0117 496 0123'], 'attrs' => [$href => 'tel:+441174960123']]);
        $this->assertStringContainsString('<a class="btn" href="tel:+441174960123">0117 496 0123</a>', $out);
    }

    public function testNoEditsIsByteIdentical()
    {
        $this->assertSame(self::PAGE, $this->slots()->apply([]));
    }

    public function testFindsTextAttrsAndImagesButNotInsideForeignBlocksOrSvg()
    {
        $slots = $this->slots();
        $all   = $slots->slots();
        $texts = array_filter($all, function ($s) {
            return $s['type'] === 'text';
        });

        $values = array_map(function ($s) {
            return $s['value'];
        }, $texts);

        $this->assertContains('Comfort, <span class="x-hot">fixed fast</span>.', $values);
        $this->assertContains('(303) 555-0148', $values);
        $this->assertContains('<b>Repairs</b> same day', $values);
        $this->assertNotContains('Custom', $values, 'Static custom blocks keep text in their attributes too.');

        $this->idFor($slots, 'attr', 'tel:+13035550148');
        $this->idFor($slots, 'attr', 'Call Northwind');
        $this->idFor($slots, 'image', 'hero.jpg');
        $this->assertStringContainsString('section group.x-hero', $slots->outline());
    }

    public function testTextEditKeepsEverythingElse()
    {
        $slots = $this->slots();
        $id    = $this->idFor($slots, 'text', 'Comfort');
        $out   = $slots->apply(['texts' => [$id => 'Warm homes, <span class="x-hot">sorted</span>.<script>x()</script>']]);

        $this->assertStringContainsString('<h1 class="wp-block-heading">Warm homes, <span class="x-hot">sorted</span>.x()</h1>', $out);
        $this->assertStringNotContainsString('<script>', $out);
        $this->assertStringContainsString('<svg viewBox="0 0 24 24"><path d="M1 1h2"/></svg>', $out, 'Icons survive untouched.');
        $this->assertSame(strlen(self::PAGE) - strlen('Comfort, <span class="x-hot">fixed fast</span>.') + strlen('Warm homes, <span class="x-hot">sorted</span>.x()'), strlen($out));
    }

    public function testEmptiedTextKeepsOriginal()
    {
        $slots = $this->slots();
        $id    = $this->idFor($slots, 'text', 'Comfort');

        $this->assertSame(self::PAGE, $slots->apply(['texts' => [$id => '  <b></b> ']]));
    }

    public function testTelHrefOnlyAcceptsPhoneLinks()
    {
        $slots = $this->slots();
        $id    = $this->idFor($slots, 'attr', 'tel:');

        $this->assertStringContainsString('href="tel:+447700900123"', $slots->apply(['attrs' => [$id => 'tel:+44 7700 900123']]));
        $this->assertSame(self::PAGE, $slots->apply(['attrs' => [$id => 'javascript:alert(1)']]));
    }

    public function testRemoveAndCloneRepeatedItems()
    {
        $slots = $this->slots();
        $units = [];
        foreach ($slots->units() as $id => $unit) {
            if ($unit['label'] === '<li.x-item>') {
                $units[] = $id;
            }
        }
        $this->assertCount(3, $units);

        $repairs = $this->idFor($slots, 'text', 'Repairs');
        $out     = $slots->apply([
            'remove' => [$units[2]],
            'clones' => [['unit' => $units[0], 'texts' => [$repairs => '<b>Boilers</b> serviced']]],
            'texts'  => [$repairs => '<b>Kitchens</b> fitted'],
        ]);

        $this->assertStringContainsString('<li class="x-item"><b>Kitchens</b> fitted</li><li class="x-item"><b>Boilers</b> serviced</li><li class="x-item"><b>Installs</b> in a week</li></ul>', $out);
        $this->assertStringNotContainsString('Plans', $out);
    }

    public function testImageSwapUpdatesTagAndBlockComment()
    {
        $slots = $this->slots();
        $id    = $this->idFor($slots, 'image', 'hero.jpg');
        $out   = $slots->apply(['images' => [$id => ['id' => 55, 'url' => 'https://new.test/me.jpg', 'alt' => 'Our van']]]);

        $this->assertStringContainsString('<!-- wp:image {"id":55,"sizeSlug":"large"} -->', $out);
        $this->assertStringContainsString('<img src="https://new.test/me.jpg" alt="Our van" class="wp-image-55"/>', $out);
    }

    public function testAppendGoesAfterATopLevelSectionOnly()
    {
        $slots = $this->slots();
        $top   = null;
        foreach ($slots->units() as $id => $unit) {
            if ($unit['top']) {
                $top = $id;
                break;
            }
        }

        $out = $slots->apply(['append' => [['after' => $top, 'markup' => '<!-- wp:paragraph --><p>FAQ</p><!-- /wp:paragraph -->']]]);
        $this->assertStringContainsString("<!-- /wp:group -->\n\n<!-- wp:paragraph --><p>FAQ</p>", $out);
    }

    public function testEditsNormalisationDropsUnknownImages()
    {
        $edits = TemplateCustomiser::edits([
            'texts'  => [['id' => 't1', 'html' => 'Hi']],
            'images' => [['id' => 'i0', 'attachmentId' => 9, 'alt' => 'x'], ['id' => 'i1', 'attachmentId' => 10, 'alt' => 'y']],
            'remove' => ['u2'],
            'clones' => [],
            'append' => [],
            'attrs'  => [],
        ], [10]);

        $this->assertSame(['t1' => 'Hi'], $edits['texts']);
        $this->assertSame(['i1' => ['attachmentId' => 10, 'alt' => 'y']], $edits['images']);
        $this->assertSame(['u2'], $edits['remove']);
    }

    public function testBuilderOperationsApplyToSections()
    {
        $out = CustomiseController::applyOperations(['A', 'B', 'C'], [
            ['op' => 'replace', 'index' => 1, 'markup' => 'B2'],
            ['op' => 'insert_after', 'index' => 0, 'markup' => 'G'],
            ['op' => 'remove', 'index' => 2, 'markup' => ''],
        ]);

        $this->assertSame("A\n\nG\n\nB2", $out);
    }

    public function testDesignTokensSwapColoursEverywhereAndWriteFontBlockOnce()
    {
        $config = [
            'version'  => 3,
            'settings' => [],
            'styles'   => [
                'elements' => ['button' => ['color' => ['background' => '#FF5A1F']]],
                'css'      => ':root{--hot:#ff5a1f;--wash:rgba(255,90,31,.2)}',
            ],
        ];
        $palette = [['slug' => 'accent-1', 'name' => 'Accent', 'color' => '#FF5A1F'], ['slug' => 'base', 'name' => 'Base', 'color' => '#FFFFFF']];
        $fonts   = ['Platypi' => 'var(--wp--preset--font-family--platypi)'];

        $first = SetDesignTokensTool::transform($config, ['colorSwaps' => [['from' => '#FF5A1F', 'to' => '#1B2A4A']], 'headingFont' => 'Platypi', 'bodyFont' => 'Nope'], $palette, $fonts);
        $c     = $first['config'];

        $this->assertSame('#1b2a4a', $c['styles']['elements']['button']['color']['background']);
        $this->assertStringContainsString('--hot:#1b2a4a', $c['styles']['css']);
        $this->assertStringContainsString('rgba(27,42,74,.2)', $c['styles']['css']);
        $this->assertSame('#1b2a4a', $c['settings']['color']['palette']['theme'][0]['color'], 'A theme palette colour gains a user override.');
        $this->assertSame('#FFFFFF', $c['settings']['color']['palette']['theme'][1]['color']);
        $this->assertSame('var:preset|font-family|platypi', $c['styles']['elements']['heading']['typography']['fontFamily']);
        $this->assertArrayNotHasKey('typography', $c['styles'], 'An unknown font is ignored.');

        $second = SetDesignTokensTool::transform($c, ['headingFont' => 'Platypi'], $palette, $fonts);
        $this->assertSame(1, substr_count($second['config']['styles']['css'], SetDesignTokensTool::MARK_OPEN));
    }
}
