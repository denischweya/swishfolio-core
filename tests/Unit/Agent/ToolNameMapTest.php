<?php

namespace SwishfolioCore\Tests\Unit\Agent;

use SwishfolioCore\AI\Conversation\ToolNameMap;
use SwishfolioCore\Tests\Unit\Tools\ToolsTestCase;
use SwishfolioCore\Tools\ToolRegistry;

/**
 * Both providers constrain tool names to ^[a-zA-Z0-9_-]{1,64}$, so every
 * registered name is illegal as-is. Getting this wrong costs a provider 400 that
 * says nothing about naming.
 */
class ToolNameMapTest extends ToolsTestCase
{
    public function test_every_registered_tool_name_survives_the_round_trip()
    {
        $registry = ToolRegistry::withDefaultTools($this->journal, $this->blocks());

        foreach ($registry->names() as $name) {
            $provider = ToolNameMap::toProvider($name);

            $this->assertMatchesRegularExpression(
                '/^[a-zA-Z0-9_-]{1,64}$/',
                $provider,
                $name . ' maps to something a provider will reject.'
            );
            $this->assertSame($name, ToolNameMap::fromProvider($provider), $name . ' does not round-trip.');
        }
    }

    public function test_the_slash_is_the_only_thing_replaced()
    {
        $this->assertSame('swishfolio__site-info', ToolNameMap::toProvider('swishfolio/site-info'));
    }

    public function test_a_name_containing_the_separator_is_refused_rather_than_mangled()
    {
        // "swishfolio/a__b" would map back to "swishfolio/a/b" and route to a
        // tool that does not exist, so the ambiguity is refused at the source.
        $this->expectException(\LogicException::class);

        ToolNameMap::toProvider('swishfolio/a__b');
    }

    public function test_an_unmappable_name_is_refused()
    {
        $this->expectException(\LogicException::class);

        ToolNameMap::toProvider('swishfolio/has spaces');
    }

    public function test_a_name_without_a_separator_comes_back_unchanged()
    {
        $this->assertSame('bare', ToolNameMap::fromProvider('bare'));
    }
}
