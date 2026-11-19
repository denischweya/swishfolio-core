<?php

namespace SwishfolioCore\Tests\Unit\Generation;

use SwishfolioCore\AI\Generation\PlanValidator;
use SwishfolioCore\Actions\ValidationException;

class PlanValidatorTest extends GenerationTestCase
{
    private function validator()
    {
        return PlanValidator::withDefaultStages();
    }

    public function test_a_valid_plan_comes_back_normalised()
    {
        $plan = $this->validator()->validate($this->plan(), $this->context());

        $this->assertSame('draft', $plan['pages'][0]['status']);
        $this->assertSame('home', $plan['pages'][0]['slug']);
        $this->assertNull($plan['pages'][0]['existingPostId']);
        $this->assertSame('Built to last', $plan['pages'][0]['sections'][0]['texts']['t0']);
    }

    public function test_one_bad_page_rejects_the_whole_plan()
    {
        $plan = $this->plan([
            'pages' => [
                $this->page(),
                $this->page(['key' => 'work', 'slug' => 'work', 'sections' => [$this->patternSection([], 'ghost')]]),
            ],
            'site'  => ['frontPage' => 'home', 'navigation' => ['home', 'work']],
        ]);

        $this->expectException(ValidationException::class);
        $this->validator()->validate($plan, $this->context());
    }

    public function test_stages_run_in_declared_order()
    {
        // Fails the envelope (bad version), the reference stage (unknown unit)
        // and the slot stage (undeclared slot) at once. Envelope must win.
        $plan = $this->plan([
            'schemaVersion' => '9.9',
            'pages'         => [$this->page(['sections' => [$this->patternSection(['texts' => [['id' => 't99', 'html' => 'x']]], 'ghost')]])],
        ]);

        try {
            $this->validator()->validate($plan, $this->context());
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('plan-envelope', $e->getStage());
        }
    }

    public function test_reference_runs_before_slot()
    {
        $plan = $this->plan([
            'pages' => [$this->page(['sections' => [$this->patternSection(['texts' => [['id' => 't99', 'html' => 'x']]], 'ghost')]])],
        ]);

        try {
            $this->validator()->validate($plan, $this->context());
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('plan-reference', $e->getStage());
        }
    }

    public function test_slot_runs_before_value()
    {
        $plan = $this->plan([
            'pages' => [$this->page(['sections' => [$this->patternSection(['texts' => [['id' => 't99', 'html' => str_repeat('x', 1500)]]])]])],
        ]);

        try {
            $this->validator()->validate($plan, $this->context());
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('plan-slot', $e->getStage());
        }
    }

    public function test_value_runs_before_collision()
    {
        $plan = $this->plan([
            'pages' => [$this->page(['sections' => [$this->patternSection(['texts' => [['id' => 't0', 'html' => str_repeat('x', 1500)]]])]])],
        ]);

        try {
            $this->validator()->validate($plan, $this->context('site', ['home' => 42]));
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('plan-value', $e->getStage());
        }
    }

    public function test_the_same_plan_validates_identically_twice()
    {
        $once  = $this->validator()->validate($this->plan(), $this->context());
        $twice = $this->validator()->validate($once, $this->context());

        $this->assertSame($once, $twice);
    }

    public function test_a_plan_echoed_back_cannot_smuggle_an_existing_post_id()
    {
        $plan = $this->validator()->validate($this->plan(), $this->context());

        $plan['pages'][0]['existingPostId'] = 999;

        $revalidated = $this->validator()->validate($plan, $this->context());

        $this->assertNull($revalidated['pages'][0]['existingPostId']);
    }

    public function test_a_plan_echoed_back_cannot_smuggle_a_published_status()
    {
        $plan                       = $this->validator()->validate($this->plan(), $this->context());
        $plan['pages'][0]['status'] = 'publish';

        $this->expectException(ValidationException::class);
        $this->validator()->validate($plan, $this->context());
    }

    public function test_a_page_scoped_plan_validates_without_a_site_block()
    {
        $plan = $this->plan();
        unset($plan['site']);

        $validated = $this->validator()->validate($plan, $this->context('page'));

        $this->assertArrayNotHasKey('site', $validated);
        $this->assertCount(1, $validated['pages']);
    }

    public function test_a_plan_leaving_demo_copy_is_refused_by_the_coverage_stage()
    {
        $section = $this->patternSection(['texts' => [['id' => 't0', 'html' => 'Built to last']]]);
        $plan    = $this->plan(['pages' => [$this->page(['sections' => [$section]])]]);

        try {
            $this->validator()->validate($plan, $this->context());
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('plan-coverage', $e->getStage());
            $this->assertStringContainsString('home-01-hero t1', $e->getMessage());
        }

        $partial = PlanValidator::withDefaultStages(false)->validate($plan, $this->context());
        $this->assertSame('Built to last', $partial['pages'][0]['sections'][0]['texts']['t0']);
    }

    public function test_demo_copy_inside_a_removed_unit_does_not_count()
    {
        $texts   = array_values(array_filter($this->heroTexts(), function ($pair) {
            return $pair['id'] !== 't3';
        }));
        $section = $this->patternSection(['texts' => $texts, 'remove' => ['u2']]);
        $plan    = $this->validator()->validate($this->plan(['pages' => [$this->page(['sections' => [$section]])]]), $this->context());

        $this->assertSame(['u2'], $plan['pages'][0]['sections'][0]['remove']);
    }
}
