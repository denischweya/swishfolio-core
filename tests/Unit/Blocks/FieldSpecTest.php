<?php

namespace SwishfolioCore\Tests\Unit\Blocks;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\Blocks\FieldSpec;

class FieldSpecTest extends TestCase
{
    public function test_enum_spec_is_detected_without_explicit_type()
    {
        $spec = FieldSpec::fromArray('contentAlign', ['enum' => ['left', 'center'], 'group' => 'layout']);

        $this->assertSame('enum', $spec->getType());
        $this->assertSame(['left', 'center'], $spec->getEnum());
        $this->assertSame('layout', $spec->getGroup());
    }

    public function test_token_spec_carries_token_set()
    {
        $spec = FieldSpec::fromArray('backgroundColor', ['type' => 'token', 'tokenSet' => 'color']);

        $this->assertSame('token', $spec->getType());
        $this->assertSame('color', $spec->getTokenSet());
    }

    public function test_number_spec_carries_bounds()
    {
        $spec = FieldSpec::fromArray('minHeight', ['type' => 'number', 'min' => 320, 'max' => 1000]);

        $this->assertSame(320.0, $spec->getMin());
        $this->assertSame(1000.0, $spec->getMax());
    }

    public function test_text_spec_carries_max_length_and_role()
    {
        $spec = FieldSpec::fromArray('heading', ['type' => 'text', 'maxLength' => 120, 'role' => 'headline']);

        $this->assertSame(120, $spec->getMaxLength());
        $this->assertSame('headline', $spec->getRole());
    }

    public function test_group_defaults_to_style_when_absent()
    {
        $spec = FieldSpec::fromArray('x', ['type' => 'text']);

        $this->assertSame('style', $spec->getGroup());
    }

    public function test_boolean_spec_is_supported()
    {
        $spec = FieldSpec::fromArray('showDate', ['type' => 'boolean', 'group' => 'layout']);

        $this->assertSame('boolean', $spec->getType());
    }

    public function test_unknown_type_is_rejected()
    {
        $this->expectException(\InvalidArgumentException::class);
        FieldSpec::fromArray('x', ['type' => 'wormhole']);
    }

    public function test_spec_without_type_or_enum_is_rejected()
    {
        $this->expectException(\InvalidArgumentException::class);
        FieldSpec::fromArray('x', ['maxLength' => 10]);
    }

    public function test_token_without_token_set_is_rejected()
    {
        $this->expectException(\InvalidArgumentException::class);
        FieldSpec::fromArray('x', ['type' => 'token']);
    }

    public function test_enum_with_empty_list_is_rejected()
    {
        $this->expectException(\InvalidArgumentException::class);
        FieldSpec::fromArray('x', ['enum' => []]);
    }
}
