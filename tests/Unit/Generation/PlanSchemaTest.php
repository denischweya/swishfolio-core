<?php

namespace SwishfolioCore\Tests\Unit\Generation;

use SwishfolioCore\AI\Generation\PlanSchema;
use SwishfolioCore\Actions\ActionSchema;

class PlanSchemaTest extends GenerationTestCase
{
    public function test_it_shares_one_schema_version_with_the_action_schema()
    {
        $this->assertSame(ActionSchema::SCHEMA_VERSION, PlanSchema::SCHEMA_VERSION);
    }

    public function test_a_site_schema_requires_the_site_block()
    {
        $schema = PlanSchema::build('site');

        $this->assertContains('site', $schema['required']);
        $this->assertContains('pages', $schema['required']);
        $this->assertSame(['frontPage', 'navigation'], $schema['properties']['site']['required']);
    }

    public function test_a_page_schema_omits_the_site_block_and_allows_one_page()
    {
        $schema = PlanSchema::build('page');

        $this->assertNotContains('site', $schema['required']);
        $this->assertArrayNotHasKey('site', $schema['properties']);
        $this->assertSame(1, $schema['properties']['pages']['maxItems']);
    }

    public function test_draft_is_the_only_status_the_schema_offers()
    {
        $schema = PlanSchema::build('site');

        $this->assertSame(
            ['draft'],
            $schema['properties']['pages']['items']['properties']['status']['enum']
        );
    }

    public function test_the_schema_closes_every_object_it_defines()
    {
        $schema  = PlanSchema::build('site');
        $page    = $schema['properties']['pages']['items'];
        $section = $page['properties']['sections']['items'];

        $this->assertFalse($schema['additionalProperties']);
        $this->assertFalse($schema['properties']['site']['additionalProperties']);
        $this->assertFalse($page['additionalProperties']);
        $this->assertFalse($section['additionalProperties']);
    }

    public function test_only_the_two_recorded_conflict_strategies_are_offered()
    {
        $schema = PlanSchema::build('site');

        $this->assertSame(
            ['suffix', 'skip'],
            $schema['properties']['pages']['items']['properties']['onConflict']['enum']
        );
    }

    public function test_the_caps_in_the_schema_match_the_caps_the_validator_enforces()
    {
        $schema = PlanSchema::build('site');

        $this->assertSame(PlanSchema::MAX_PAGES, $schema['properties']['pages']['maxItems']);
        $this->assertSame(
            PlanSchema::MAX_SECTIONS_PER_PAGE,
            $schema['properties']['pages']['items']['properties']['sections']['maxItems']
        );
    }

    public function test_it_encodes_as_json_without_loss()
    {
        $encoded = json_encode(PlanSchema::build('site'));

        $this->assertIsString($encoded);
        $this->assertSame(PlanSchema::build('site'), json_decode($encoded, true));
    }
}
