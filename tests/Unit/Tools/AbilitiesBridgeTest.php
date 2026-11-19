<?php

namespace SwishfolioCore\Tests\Unit\Tools;

use SwishfolioCore\Tools\AbilitiesBridge;
use SwishfolioCore\Tools\ToolRegistry;
use SwishfolioCore\Tools\ToolRunner;

/**
 * wp_register_ability() does not exist on the 6.7 floor, so the definitions are
 * asserted directly rather than by registering them. That is the whole value of
 * the bridge in this slice: the shape is proven before anything depends on it.
 */
class AbilitiesBridgeTest extends ToolsTestCase
{
    private function bridge()
    {
        $registry = ToolRegistry::withDefaultTools($this->journal, $this->blocks());

        return new AbilitiesBridge($registry, new ToolRunner($registry, $this->journal));
    }

    public function test_every_tool_produces_a_well_formed_ability_definition()
    {
        $bridge   = $this->bridge();
        $registry = ToolRegistry::withDefaultTools($this->journal, $this->blocks());

        foreach ($registry->all() as $name => $tool) {
            $definition = $bridge->definitionFor($tool);

            $this->assertNotSame('', $definition['label'], $name);
            $this->assertNotSame('', $definition['description'], $name);
            $this->assertSame('object', $definition['input_schema']['type'], $name);
            $this->assertIsCallable($definition['permission_callback'], $name);
            $this->assertIsCallable($definition['execute_callback'], $name);
        }
    }

    public function test_nothing_is_exposed_to_rest_or_to_mcp_clients()
    {
        $bridge   = $this->bridge();
        $registry = ToolRegistry::withDefaultTools($this->journal, $this->blocks());

        foreach ($registry->all() as $name => $tool) {
            $meta = $bridge->definitionFor($tool)['meta'];

            $this->assertFalse($meta['show_in_rest'], $name . ' must not be served by core REST yet.');
            $this->assertFalse($meta['mcp']['public'], $name . ' must not be served to MCP clients yet.');
        }
    }

    public function test_the_side_effect_travels_with_the_ability()
    {
        $registry = ToolRegistry::withDefaultTools($this->journal, $this->blocks());

        $definition = $this->bridge()->definitionFor($registry->get('swishfolio/set-front-page'));

        $this->assertSame('write_published', $definition['meta']['side_effect']);
    }

    public function test_a_permission_callback_reports_a_refusal_as_a_wp_error()
    {
        $GLOBALS['sfcore_test_wp']['caps'] = ['read'];

        $registry = ToolRegistry::withDefaultTools($this->journal, $this->blocks());

        $callback = $this->bridge()->definitionFor($registry->get('swishfolio/site-info'))['permission_callback'];

        $this->assertInstanceOf(\WP_Error::class, $callback([]));
    }

    public function test_a_permission_callback_allows_a_user_who_may_act()
    {
        $registry = ToolRegistry::withDefaultTools($this->journal, $this->blocks());

        $callback = $this->bridge()->definitionFor($registry->get('swishfolio/site-info'))['permission_callback'];

        $this->assertTrue($callback([]));
    }

    public function test_an_ability_call_goes_through_the_runner_so_the_gate_still_applies()
    {
        $this->givenPost(60, ['post_status' => 'publish']);

        $registry = ToolRegistry::withDefaultTools($this->journal, $this->blocks());

        $execute = $this->bridge()->definitionFor($registry->get('swishfolio/set-front-page'))['execute_callback'];

        $result = $execute(['postId' => 60]);

        $this->assertInstanceOf(\WP_Error::class, $result, 'An unconfirmed published write must be refused.');
        $this->assertSame('confirm_required', $result->get_error_code());
        $this->assertSame([], $GLOBALS['sfcore_test_options']);
    }

    public function test_the_bridge_reports_the_api_is_absent_on_this_floor()
    {
        $this->assertSame(function_exists('wp_register_ability'), AbilitiesBridge::isAvailable());
    }

    public function test_registering_without_the_api_is_a_no_op_rather_than_a_crash()
    {
        $this->bridge()->register();

        $this->assertTrue(true, 'register() must not fatal when the Abilities API is absent.');
    }
}
