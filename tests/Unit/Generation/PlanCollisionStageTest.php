<?php

namespace SwishfolioCore\Tests\Unit\Generation;

use SwishfolioCore\AI\Generation\Stages\PlanCollisionStage;
use SwishfolioCore\AI\Generation\Stages\PlanEnvelopeStage;
use SwishfolioCore\Actions\ValidationException;

class PlanCollisionStageTest extends GenerationTestCase
{
    private function apply(array $plan, array $existingSlugs = [])
    {
        $context = $this->context('site', $existingSlugs);
        $plan    = (new PlanEnvelopeStage())->process($plan, $context);

        return (new PlanCollisionStage())->process($plan, $context);
    }

    public function test_a_free_slug_needs_no_strategy()
    {
        $result = $this->apply($this->plan());

        $this->assertNull($result['pages'][0]['onConflict']);
        $this->assertNull($result['pages'][0]['existingPostId']);
    }

    public function test_a_taken_slug_without_a_strategy_is_rejected()
    {
        try {
            $this->apply($this->plan(), ['home' => 42]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('plan-collision', $e->getStage());
            $this->assertSame('pages.0.onConflict', $e->getPath());
        }
    }

    public function test_suffix_keeps_the_page_and_does_not_reuse_the_existing_one()
    {
        $result = $this->apply(
            $this->plan(['pages' => [$this->page(['onConflict' => 'suffix'])]]),
            ['home' => 42]
        );

        $this->assertSame('suffix', $result['pages'][0]['onConflict']);
        $this->assertNull($result['pages'][0]['existingPostId']);
    }

    public function test_skip_records_the_existing_post_so_navigation_can_point_at_it()
    {
        $result = $this->apply(
            $this->plan(['pages' => [$this->page(['onConflict' => 'skip'])]]),
            ['home' => 42]
        );

        $this->assertSame('skip', $result['pages'][0]['onConflict']);
        $this->assertSame(42, $result['pages'][0]['existingPostId']);
    }

    public function test_an_unknown_strategy_is_rejected()
    {
        try {
            $this->apply(
                $this->plan(['pages' => [$this->page(['onConflict' => 'overwrite'])]]),
                ['home' => 42]
            );
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('plan-collision', $e->getStage());
        }
    }

    public function test_two_pages_wanting_the_same_slug_are_rejected()
    {
        $plan = $this->plan([
            'pages' => [$this->page(), $this->page(['key' => 'second'])],
        ]);

        try {
            $this->apply($plan);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('plan-collision', $e->getStage());
            $this->assertSame('pages.1.slug', $e->getPath());
        }
    }
}
