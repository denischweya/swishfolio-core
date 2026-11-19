<?php

namespace SwishfolioCore\Tests\Unit\Blocks;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\Blocks\BlockCapability;

class BlockCapabilityTest extends TestCase
{
    private function manifest(array $overrides = [])
    {
        return array_merge([
            'version'      => 1,
            'tier'         => 'full',
            'purpose'      => 'hero',
            'summary'      => 'A hero.',
            'capabilities' => [
                'content' => ['heading' => ['type' => 'text', 'maxLength' => 120]],
                'style'   => ['backgroundColor' => ['type' => 'token', 'tokenSet' => 'color']],
            ],
        ], $overrides);
    }

    public function test_fields_are_flattened_across_groups_and_tagged_with_group()
    {
        $cap = BlockCapability::fromArray('swishfolio-core/hero', $this->manifest());

        $this->assertSame(['heading', 'backgroundColor'], array_keys($cap->getFields()));
        $this->assertSame('content', $cap->getField('heading')->getGroup());
        $this->assertSame('style', $cap->getField('backgroundColor')->getGroup());
    }

    public function test_metadata_is_exposed()
    {
        $cap = BlockCapability::fromArray('swishfolio-core/hero', $this->manifest());

        $this->assertSame('swishfolio-core/hero', $cap->getBlockName());
        $this->assertSame('full', $cap->getTier());
        $this->assertSame('hero', $cap->getPurpose());
        $this->assertSame('A hero.', $cap->getSummary());
    }

    public function test_unlisted_attribute_returns_null()
    {
        $cap = BlockCapability::fromArray('swishfolio-core/hero', $this->manifest());

        $this->assertNull($cap->getField('decoShape1Color'));
    }

    public function test_unsupported_manifest_version_is_rejected()
    {
        $this->expectException(\InvalidArgumentException::class);
        BlockCapability::fromArray('x/y', $this->manifest(['version' => 99]));
    }

    public function test_invalid_tier_is_rejected()
    {
        $this->expectException(\InvalidArgumentException::class);
        BlockCapability::fromArray('x/y', $this->manifest(['tier' => 'partial']));
    }

    public function test_children_constraints_are_exposed()
    {
        $cap = BlockCapability::fromArray('swishfolio-core/testimonials', $this->manifest([
            'children' => ['allowed' => ['swishfolio-core/testimonial-card'], 'min' => 1, 'max' => 12],
        ]));

        $this->assertSame(['swishfolio-core/testimonial-card'], $cap->getAllowedChildren());
        $this->assertSame(1, $cap->getChildMin());
        $this->assertSame(12, $cap->getChildMax());
    }

    public function test_children_default_to_empty_when_absent()
    {
        $cap = BlockCapability::fromArray('swishfolio-core/hero', $this->manifest());

        $this->assertSame([], $cap->getAllowedChildren());
        $this->assertNull($cap->getChildMin());
        $this->assertNull($cap->getChildMax());
    }
}
