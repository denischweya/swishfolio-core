<?php

namespace SwishfolioCore\Tests\Unit\Tools;

use SwishfolioCore\Tools\InputValidator;
use SwishfolioCore\Tools\SchemaViolation;

class InputValidatorTest extends ToolsTestCase
{
    private function schema()
    {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => ['postId'],
            'properties'           => [
                'postId' => ['type' => 'integer', 'minimum' => 1],
                'title'  => ['type' => 'string', 'maxLength' => 5],
                'mode'   => ['type' => 'string', 'enum' => ['fast', 'slow'], 'default' => 'fast'],
                'live'   => ['type' => 'boolean'],
                'items'  => [
                    'type'  => 'array',
                    'items' => [
                        'type'                 => 'object',
                        'additionalProperties' => false,
                        'required'             => ['label'],
                        'properties'           => ['label' => ['type' => 'string']],
                    ],
                ],
            ],
        ];
    }

    private function validate(array $input)
    {
        return (new InputValidator())->validate($this->schema(), $input);
    }

    public function test_a_missing_required_field_names_itself()
    {
        try {
            $this->validate([]);
            $this->fail('Expected SchemaViolation');
        } catch (SchemaViolation $e) {
            $this->assertSame('postId', $e->getField());
        }
    }

    public function test_an_unknown_field_is_refused_because_schemas_are_closed()
    {
        try {
            $this->validate(['postId' => 1, 'colour' => 'red']);
            $this->fail('Expected SchemaViolation');
        } catch (SchemaViolation $e) {
            $this->assertSame('colour', $e->getField());
        }
    }

    public function test_defaults_are_applied_for_absent_fields()
    {
        $this->assertSame('fast', $this->validate(['postId' => 1])['mode']);
    }

    public function test_an_absent_field_without_a_default_stays_absent()
    {
        $this->assertArrayNotHasKey('title', $this->validate(['postId' => 1]));
    }

    public function test_a_numeric_string_is_coerced_to_an_integer()
    {
        $this->assertSame(12, $this->validate(['postId' => '12'])['postId']);
    }

    public function test_one_and_zero_are_accepted_as_booleans()
    {
        $this->assertTrue($this->validate(['postId' => 1, 'live' => 1])['live']);
        $this->assertFalse($this->validate(['postId' => 1, 'live' => 0])['live']);
    }

    public function test_an_object_where_a_scalar_belongs_is_not_coerced()
    {
        $this->expectException(SchemaViolation::class);
        $this->validate(['postId' => ['nested' => true]]);
    }

    public function test_minimum_is_enforced()
    {
        $this->expectException(SchemaViolation::class);
        $this->validate(['postId' => 0]);
    }

    public function test_max_length_is_enforced()
    {
        $this->expectException(SchemaViolation::class);
        $this->validate(['postId' => 1, 'title' => 'far too long']);
    }

    public function test_an_enum_outside_its_values_is_refused()
    {
        $this->expectException(SchemaViolation::class);
        $this->validate(['postId' => 1, 'mode' => 'medium']);
    }

    public function test_a_nested_field_path_is_reported_with_its_index()
    {
        try {
            $this->validate(['postId' => 1, 'items' => [['label' => 'ok'], ['label' => 'x', 'url' => 'y']]]);
            $this->fail('Expected SchemaViolation');
        } catch (SchemaViolation $e) {
            $this->assertSame('items[1].url', $e->getField());
        }
    }

    public function test_a_keyed_array_is_not_a_list()
    {
        try {
            $this->validate(['postId' => 1, 'items' => ['first' => ['label' => 'x']]]);
            $this->fail('Expected SchemaViolation');
        } catch (SchemaViolation $e) {
            $this->assertSame('items', $e->getField());
        }
    }

    public function test_an_unsupported_keyword_is_a_programming_error_not_a_silent_pass()
    {
        $this->expectException(\LogicException::class);

        (new InputValidator())->validate(
            [
                'type'       => 'object',
                'properties' => ['x' => ['type' => 'string', 'pattern' => '/^a/']],
            ],
            ['x' => 'b']
        );
    }
}
