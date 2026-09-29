<?php

namespace SwishfolioCore\Tests\Unit\Generation;

class PatternLibraryTest extends GenerationTestCase
{
    public function test_it_discovers_every_well_formed_sidecar()
    {
        $ids = array_keys($this->library()->all());

        $this->assertContains('alpha-hero', $ids);
        $this->assertContains('alpha-page', $ids);
        $this->assertContains('needs-media', $ids);
        $this->assertContains('swiss-only', $ids);
    }

    public function test_a_sidecar_naming_neither_ref_nor_file_is_dropped_rather_than_fatal()
    {
        $library = $this->library();

        $this->assertNull($library->get('broken'));
        $this->assertNotSame([], $library->all());
    }

    public function test_slots_are_parsed_with_their_constraints()
    {
        $slot = $this->library()->get('alpha-hero')->getSlot('label');

        $this->assertSame('{{slot:label}}', $slot->getToken());
        $this->assertTrue($slot->isInComment());
        $this->assertSame(30, $slot->getMaxLength());
    }

    public function test_a_slot_token_defaults_to_its_name()
    {
        $slot = $this->library()->get('alpha-hero')->getSlot('headline');

        $this->assertSame('{{slot:headline}}', $slot->getToken());
        $this->assertFalse($slot->isInComment());
    }

    public function test_markup_comes_from_the_sidecar_directory_for_file_units()
    {
        $markup = $this->library()->markup('alpha-hero');

        $this->assertStringContainsString('{{slot:headline}}', $markup);
        $this->assertStringContainsString('wp:group', $markup);
    }

    public function test_markup_comes_from_the_pattern_registry_for_ref_units()
    {
        $this->assertSame(self::FAKE_PATTERN_MARKUP, $this->library()->markup('alpha-page'));
    }

    public function test_an_unknown_unit_raises_rather_than_composing_nothing()
    {
        $this->expectException(\RuntimeException::class);
        $this->library()->markup('no-such-unit');
    }

    public function test_an_unregistered_pattern_reference_raises()
    {
        $library = new \SwishfolioCore\AI\Generation\PatternLibrary(
            [$this->fixtureDir()],
            function ($ref) {
                return null;
            }
        );

        $this->expectException(\RuntimeException::class);
        $library->markup('alpha-page');
    }

    public function test_usable_excludes_units_that_need_images()
    {
        $this->assertArrayNotHasKey('needs-media', $this->library()->usable('section'));
    }

    public function test_usable_excludes_a_variation_specific_unit_under_another_variation()
    {
        $this->assertArrayNotHasKey('swiss-only', $this->library()->usable('section', 'Neo-Brutalist'));
        $this->assertArrayNotHasKey('swiss-only', $this->library()->usable('section'));
    }

    public function test_usable_includes_a_variation_specific_unit_under_its_own_variation()
    {
        $this->assertArrayHasKey('swiss-only', $this->library()->usable('section', 'Swiss International'));
    }

    public function test_usable_separates_page_units_from_section_units()
    {
        $this->assertArrayHasKey('alpha-page', $this->library()->usable('page'));
        $this->assertArrayNotHasKey('alpha-hero', $this->library()->usable('page'));
        $this->assertArrayHasKey('alpha-hero', $this->library()->usable('section'));
    }

    public function test_the_promptable_description_never_carries_markup()
    {
        $described = $this->library()->describe('section');
        $encoded   = json_encode($described);

        $this->assertStringNotContainsString('wp:group', $encoded);
        $this->assertStringNotContainsString('{{slot:', $encoded);
        $this->assertSame('hero', $described['alpha-hero']['role']);
        $this->assertArrayHasKey('headline', $described['alpha-hero']['slots']);
    }

    public function test_discovery_is_cached_in_a_transient_keyed_per_directory_set()
    {
        $this->library()->all();

        $keys = array_keys($GLOBALS['sfcore_test_transients']);
        $ours = [];

        foreach ($keys as $key) {
            if (strpos($key, 'sfcore_generation_units_') === 0) {
                $ours[] = $key;
            }
        }

        $this->assertCount(1, $ours);

        $this->shippedLibrary()->all();

        $shipped = [];

        foreach (array_keys($GLOBALS['sfcore_test_transients']) as $key) {
            if (strpos($key, 'sfcore_generation_units_') === 0) {
                $shipped[] = $key;
            }
        }

        $this->assertCount(2, $shipped, 'A second directory set must not reuse the first cache entry.');
    }
}
