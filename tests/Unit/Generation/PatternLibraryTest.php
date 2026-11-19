<?php

namespace SwishfolioCore\Tests\Unit\Generation;

use SwishfolioCore\AI\Generation\Kit;
use SwishfolioCore\AI\Generation\PatternUnit;

class PatternLibraryTest extends GenerationTestCase
{
    public function test_it_discovers_every_version_2_kit_and_ignores_older_ones()
    {
        $kits = $this->library()->kits();

        $this->assertSame(['alpha', 'beta'], array_keys($kits));
        $this->assertContainsOnlyInstancesOf(Kit::class, $kits);
        $this->assertNull($this->library()->kit('old'));
    }

    public function test_a_kit_is_got_by_slug()
    {
        $kit = $this->library()->kit('alpha');

        $this->assertSame('Alpha', $kit->getName());
        $this->assertSame('creative', $kit->getIndustry());
        $this->assertSame(['home', 'contact'], $kit->getPageKeys());
        $this->assertSame('page-no-title', $kit->getPageTemplate());
        $this->assertNull($this->library()->kit('nope'));
    }

    public function test_a_malformed_unit_is_dropped_rather_than_fatal()
    {
        $kit = $this->kit();

        $this->assertNull($kit->get('broken'));
        $this->assertInstanceOf(PatternUnit::class, $kit->get('home-01-hero'));
        $this->assertSame(['home-01-hero', 'contact-01-cta'], array_keys($kit->units('section')));
    }

    public function test_only_section_units_can_be_named_by_a_plan()
    {
        $this->assertNotNull($this->kit()->section('home-01-hero'));
        $this->assertNull($this->kit()->section('header'));
        $this->assertSame('header', $this->kit()->header()->getId());
        $this->assertNull($this->kit()->footer());
    }

    public function test_markup_comes_from_the_kit_directory()
    {
        $this->assertStringContainsString('<h1 class="wp-block-heading">Demo headline</h1>', $this->kit()->markup('home-01-hero'));
    }

    public function test_an_unknown_unit_raises_rather_than_composing_nothing()
    {
        $this->expectException(\RuntimeException::class);

        $this->kit()->markup('nope');
    }

    public function test_required_slots_are_the_ones_holding_demo_copy()
    {
        $this->assertSame(['t0', 't1', 't2', 't3', 't4', 'a0', 'a1', 't5'], $this->kit()->requiredSlots('home-01-hero'));
        $this->assertSame(['t0'], $this->kit()->requiredSlots('contact-01-cta'), 'An ordinal like "01" is decoration.');
    }

    public function test_the_catalogue_lists_pages_units_and_outlines_but_never_markup()
    {
        $catalogue = $this->kit()->catalogue();

        $this->assertStringContainsString('- home "Home" (home): home-01-hero', $catalogue);
        $this->assertStringContainsString('## home-01-hero | role: hero | suits: home', $catalogue);
        $this->assertStringContainsString('t0 <h1>: Demo headline', $catalogue);
        $this->assertStringContainsString('1 placeholder photo', $catalogue);
        $this->assertStringNotContainsString('<!-- wp:', $catalogue);
        $this->assertStringNotContainsString('## header', $catalogue, 'Parts belong to the design, not the plan.');
    }

    public function test_the_matching_description_carries_no_markup()
    {
        $described = $this->library()->describeKits();

        $this->assertSame(['hero', 'cta'], $described['alpha']['roles']);
        $this->assertSame(2, $described['alpha']['sections']);
        $this->assertStringNotContainsString('<', json_encode($described));
    }

    public function test_a_kit_is_detected_from_its_css_fingerprint_or_its_variation()
    {
        $library = $this->library();

        $this->assertSame('alpha', $library->detect('.x{} .al-hero{color:red}'));
        $this->assertSame('beta', $library->detect('', 'Terminal'));
        $this->assertNull($library->detect('.other{}', 'Default'));
    }

    public function test_resolve_prefers_the_requested_kit_then_the_applied_one()
    {
        $library = $this->library();

        $this->assertSame('beta', $library->resolve('beta')->getSlug());

        $GLOBALS['sfcore_test_options']['swish_kit'] = ['slug' => 'alpha'];

        $this->assertSame('alpha', $library->resolve('')->getSlug());
        $this->assertSame('alpha', $library->resolve('unknown')->getSlug(), 'An unknown request falls back to the current kit.');
    }

    public function test_resolve_falls_back_to_a_kit_when_the_site_has_none()
    {
        $this->assertSame('alpha', $this->library()->resolve('')->getSlug());
    }
}
