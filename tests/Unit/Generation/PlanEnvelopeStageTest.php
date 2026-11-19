<?php

namespace SwishfolioCore\Tests\Unit\Generation;

use SwishfolioCore\AI\Generation\Stages\PlanEnvelopeStage;
use SwishfolioCore\Actions\ValidationException;

class PlanEnvelopeStageTest extends GenerationTestCase
{
    private function apply(array $plan, $scope = 'site')
    {
        return (new PlanEnvelopeStage())->process($plan, $this->context($scope));
    }

    private function assertRejected(array $plan, $expectedPath = null, $scope = 'site')
    {
        try {
            $this->apply($plan, $scope);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('plan-envelope', $e->getStage());

            if ($expectedPath !== null) {
                $this->assertSame($expectedPath, $e->getPath());
            }
        }
    }

    public function test_a_well_formed_plan_passes()
    {
        $result = $this->apply($this->plan());

        $this->assertCount(1, $result['pages']);
        $this->assertSame('home', $result['pages'][0]['key']);
        $this->assertSame('draft', $result['pages'][0]['status']);
    }

    public function test_an_unknown_schema_version_is_rejected()
    {
        $this->assertRejected($this->plan(['schemaVersion' => '9.9']), 'schemaVersion');
    }

    public function test_a_plan_with_no_pages_is_rejected()
    {
        $this->assertRejected($this->plan(['pages' => []]), 'pages');
    }

    public function test_too_many_pages_is_rejected()
    {
        $pages = [];

        for ($i = 0; $i < 13; $i++) {
            $pages[] = $this->page(['key' => 'p' . $i, 'slug' => 'p' . $i]);
        }

        $this->assertRejected($this->plan(['pages' => $pages]), 'pages');
    }

    public function test_too_many_sections_on_one_page_is_rejected()
    {
        $sections = array_fill(0, 15, $this->patternSection());

        $this->assertRejected(
            $this->plan(['pages' => [$this->page(['sections' => $sections])]]),
            'pages.0.sections'
        );
    }

    public function test_too_many_sections_in_total_is_rejected()
    {
        $pages = [];

        for ($i = 0; $i < 6; $i++) {
            $pages[] = $this->page([
                'key'      => 'p' . $i,
                'slug'     => 'p' . $i,
                'sections' => array_fill(0, 14, $this->patternSection()),
            ]);
        }

        $this->assertRejected($this->plan(['pages' => $pages]));
    }

    public function test_a_duplicate_page_key_is_rejected()
    {
        $this->assertRejected(
            $this->plan(['pages' => [$this->page(), $this->page(['slug' => 'other'])]]),
            'pages.1.key'
        );
    }

    public function test_a_page_key_that_is_not_slug_safe_is_rejected()
    {
        $this->assertRejected($this->plan(['pages' => [$this->page(['key' => 'Home Page'])]]), 'pages.0.key');
    }

    public function test_a_page_asking_to_be_published_is_rejected()
    {
        $this->assertRejected(
            $this->plan(['pages' => [$this->page(['status' => 'publish'])]]),
            'pages.0.status'
        );
    }

    public function test_status_is_forced_to_draft_when_absent()
    {
        $result = $this->apply($this->plan());

        $this->assertSame('draft', $result['pages'][0]['status']);
    }

    public function test_a_section_naming_both_a_pattern_and_a_block_is_rejected()
    {
        $section = ['pattern' => 'home-01-hero', 'block' => 'swishfolio-core/portfolio'];

        $this->assertRejected(
            $this->plan(['pages' => [$this->page(['sections' => [$section]])]]),
            'pages.0.sections.0'
        );
    }

    public function test_a_section_naming_neither_a_pattern_nor_a_block_is_rejected()
    {
        $this->assertRejected(
            $this->plan(['pages' => [$this->page(['sections' => [['content' => []]]])]]),
            'pages.0.sections.0'
        );
    }

    public function test_unknown_page_keys_are_discarded_rather_than_carried_forward()
    {
        $page = $this->page(['existingPostId' => 4242, 'post_author' => 1]);

        $result = $this->apply($this->plan(['pages' => [$page]]));

        $this->assertArrayNotHasKey('existingPostId', $result['pages'][0]);
        $this->assertArrayNotHasKey('post_author', $result['pages'][0]);
    }

    public function test_unknown_section_keys_are_discarded()
    {
        $section = ['pattern' => 'home-01-hero', 'content' => [], 'innerHTML' => '<script>x</script>'];

        $result = $this->apply($this->plan(['pages' => [$this->page(['sections' => [$section]])]]));

        $this->assertSame(['pattern', 'texts', 'attrs', 'remove', 'clones'], array_keys($result['pages'][0]['sections'][0]));
    }

    public function test_slot_edit_lists_become_id_maps()
    {
        $result  = $this->apply($this->plan());
        $section = $result['pages'][0]['sections'][0];

        $this->assertSame('Built to last', $section['texts']['t0']);
        $this->assertSame('tel:+447700900123', $section['attrs']['a0']);
    }

    public function test_a_plan_for_another_kit_is_rejected()
    {
        $this->assertRejected($this->plan(['kit' => 'beta']), 'kit');
    }

    public function test_the_context_kit_is_carried_forward_even_when_the_plan_omits_it()
    {
        $plan = $this->plan();
        unset($plan['kit']);

        $this->assertSame('alpha', $this->apply($plan)['kit']);
    }

    public function test_a_site_plan_without_a_site_block_is_rejected()
    {
        $plan = $this->plan();
        unset($plan['site']);

        $this->assertRejected($plan, 'site');
    }

    public function test_a_site_plan_without_a_front_page_is_rejected()
    {
        $this->assertRejected($this->plan(['site' => ['navigation' => []]]), 'site.frontPage');
    }

    public function test_a_page_scoped_plan_may_omit_the_site_block()
    {
        $plan = $this->plan();
        unset($plan['site']);

        $result = $this->apply($plan, 'page');

        $this->assertArrayNotHasKey('site', $result);
    }

    public function test_a_page_scoped_plan_with_two_pages_is_rejected()
    {
        $plan = $this->plan(['pages' => [$this->page(), $this->page(['key' => 'two', 'slug' => 'two'])]]);
        unset($plan['site']);

        $this->assertRejected($plan, 'pages', 'page');
    }
}
