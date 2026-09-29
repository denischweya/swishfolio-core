<?php

namespace SwishfolioCore\Tests\Unit\Actions;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\Actions\ActionSchema;

class ActionSchemaTest extends TestCase
{
    public function test_schema_requires_version_summary_and_actions()
    {
        $schema = ActionSchema::build();

        $this->assertSame(['schemaVersion', 'summary', 'actions'], $schema['required']);
        $this->assertFalse($schema['additionalProperties']);
    }

    public function test_schema_version_is_pinned_to_the_supported_version()
    {
        $schema = ActionSchema::build();

        $this->assertSame([ActionSchema::SCHEMA_VERSION], $schema['properties']['schemaVersion']['enum']);
        $this->assertSame('1.0', ActionSchema::SCHEMA_VERSION);
    }

    public function test_action_type_enum_matches_supported_types()
    {
        $schema = ActionSchema::build();
        $items  = $schema['properties']['actions']['items'];

        $this->assertSame(ActionSchema::TYPES, $items['properties']['type']['enum']);
    }

    public function test_changes_stays_an_open_object_because_keys_are_block_specific()
    {
        $schema  = ActionSchema::build();
        $changes = $schema['properties']['actions']['items']['properties']['changes'];

        $this->assertSame('object', $changes['type']);
    }

    public function test_action_count_is_capped_in_the_schema()
    {
        $schema = ActionSchema::build(10);

        $this->assertSame(10, $schema['properties']['actions']['maxItems']);
    }
}
