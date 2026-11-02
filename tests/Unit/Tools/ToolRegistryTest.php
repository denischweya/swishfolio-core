<?php

namespace SwishfolioCore\Tests\Unit\Tools;

use SwishfolioCore\Tools\ToolInterface;
use SwishfolioCore\Tools\ToolRegistry;

class ToolRegistryTest extends ToolsTestCase
{
    private function registry()
    {
        return ToolRegistry::withDefaultTools($this->journal, $this->blocks());
    }

    public function test_it_registers_the_whole_default_tool_set()
    {
        $names = $this->registry()->names();

        sort($names);

        $this->assertSame(
            [
                'swishfolio/create-page',
                'swishfolio/get-block-capabilities',
                'swishfolio/get-global-styles',
                'swishfolio/list-content',
                'swishfolio/list-media',
                'swishfolio/read-page',
                'swishfolio/rollback-run',
                'swishfolio/set-alt-text',
                'swishfolio/set-front-page',
                'swishfolio/set-navigation',
                'swishfolio/set-style-variation',
                'swishfolio/site-info',
                'swishfolio/update-page',
                'swishfolio/upload-media',
            ],
            $names
        );
    }

    public function test_an_unregistered_name_resolves_to_nothing_rather_than_erroring()
    {
        $this->assertFalse($this->registry()->has('swishfolio/delete-everything'));
        $this->assertNull($this->registry()->get('swishfolio/delete-everything'));
    }

    public function test_every_tool_declares_a_known_side_effect()
    {
        $known = [
            ToolInterface::EFFECT_READ,
            ToolInterface::EFFECT_WRITE_DRAFT,
            ToolInterface::EFFECT_WRITE_PUBLISHED,
            ToolInterface::EFFECT_DESTRUCTIVE,
        ];

        foreach ($this->registry()->all() as $name => $tool) {
            $this->assertContains($tool->getSideEffect(), $known, $name . ' declares an unknown side effect.');
        }
    }

    public function test_every_tool_schema_is_a_closed_object()
    {
        foreach ($this->registry()->all() as $name => $tool) {
            $schema = $tool->getInputSchema();

            $this->assertSame('object', $schema['type'], $name);
            $this->assertFalse(
                isset($schema['additionalProperties']) ? $schema['additionalProperties'] : false,
                $name . ' must not accept unknown fields.'
            );
        }
    }

    public function test_the_description_handed_to_the_model_carries_what_it_needs_to_choose()
    {
        foreach ($this->registry()->describe() as $described) {
            $this->assertArrayHasKey('name', $described);
            $this->assertArrayHasKey('description', $described);
            $this->assertArrayHasKey('sideEffect', $described);
            $this->assertArrayHasKey('inputSchema', $described);
            $this->assertNotSame('', $described['title']);
        }
    }

    public function test_nothing_but_a_tool_can_be_filtered_in()
    {
        // The filter is how the hosted product and third parties add tools, so
        // it has to be hostile to anything that is not one: a non-tool reaching
        // the runner would bypass the schema and the permission callback.
        $GLOBALS['sfcore_test_filters']['swishfolio_ai_tools'] = static function ($tools) {
            $tools[] = new \stdClass();

            return $tools;
        };

        $registry = $this->registry();

        unset($GLOBALS['sfcore_test_filters']['swishfolio_ai_tools']);

        foreach ($registry->all() as $tool) {
            $this->assertInstanceOf(ToolInterface::class, $tool);
        }
    }
}
