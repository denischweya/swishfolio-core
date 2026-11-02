<?php

namespace SwishfolioCore\Tests\Unit\Tools\Read;

use SwishfolioCore\Tests\Unit\Tools\ToolsTestCase;
use SwishfolioCore\Tools\Read\GetBlockCapabilitiesTool;
use SwishfolioCore\Tools\Read\ListContentTool;
use SwishfolioCore\Tools\Read\ListMediaTool;
use SwishfolioCore\Tools\Read\ReadPageTool;
use SwishfolioCore\Tools\Read\SiteInfoTool;
use SwishfolioCore\Tools\ToolException;
use SwishfolioCore\Tools\ToolInterface;

class ReadToolsTest extends ToolsTestCase
{
    private function readTools()
    {
        return [
            new SiteInfoTool(
                function () {
                    return [['title' => 'Default'], ['title' => 'Swiss International']];
                }
            ),
            new ListContentTool(),
            new ReadPageTool($this->blocks()),
            new ListMediaTool(),
            new GetBlockCapabilitiesTool($this->blocks()),
        ];
    }

    public function test_every_read_tool_declares_itself_as_a_read()
    {
        foreach ($this->readTools() as $tool) {
            $this->assertSame(
                ToolInterface::EFFECT_READ,
                $tool->getSideEffect(),
                $tool->getName() . ' must be a read.'
            );
        }
    }

    public function test_every_read_tool_needs_edit_posts()
    {
        $GLOBALS['sfcore_test_wp']['caps'] = ['read'];

        foreach ($this->readTools() as $tool) {
            try {
                $tool->authorize(['postId' => 1, 'blocks' => []], $this->context());
                $this->fail($tool->getName() . ' allowed a subscriber.');
            } catch (ToolException $e) {
                $this->assertSame('forbidden', $e->getError()->getCode());
            }
        }
    }

    public function test_every_tool_has_a_closed_schema_and_a_description_the_model_can_choose_by()
    {
        foreach ($this->readTools() as $tool) {
            $schema = $tool->getInputSchema();

            $this->assertSame('object', $schema['type'], $tool->getName());
            $this->assertFalse($schema['additionalProperties'], $tool->getName() . ' must be closed.');
            $this->assertGreaterThan(
                60,
                strlen($tool->getDescription()),
                $tool->getName() . ' needs a description substantial enough to choose by.'
            );
        }
    }

    public function test_site_info_reports_the_shape_of_the_site()
    {
        $this->givenPost(1, ['post_status' => 'publish', 'post_title' => 'Home']);
        $this->givenPost(2, ['post_status' => 'draft']);

        $GLOBALS['sfcore_test_options']['show_on_front'] = 'page';
        $GLOBALS['sfcore_test_options']['page_on_front'] = 1;

        $info = $this->readTools()[0]->execute([], $this->context());

        $this->assertSame('Acme Studio', $info['name']);
        $this->assertSame('en_GB', $info['locale']);
        $this->assertSame(['Default', 'Swiss International'], $info['styleVariations']);
        $this->assertSame(1, $info['frontPage']['frontPageId']);
        $this->assertSame('Home', $info['frontPage']['frontPage']);
        $this->assertSame(1, $info['counts']['page']['publish']);
        $this->assertSame(1, $info['counts']['page']['draft']);
        $this->assertSame(1, $info['siteId']);
    }

    public function test_list_content_pages_with_a_cursor_and_stops_at_the_end()
    {
        for ($id = 1; $id <= 5; $id++) {
            $this->givenPost($id, ['post_title' => 'Page ' . $id]);
        }

        $tool = new ListContentTool();

        $first = $tool->execute(['limit' => 2, 'offset' => 0], $this->context());

        $this->assertCount(2, $first['items']);
        $this->assertSame(5, $first['total']);
        $this->assertSame(2, $first['nextOffset']);

        $last = $tool->execute(['limit' => 2, 'offset' => 4], $this->context());

        $this->assertCount(1, $last['items']);
        $this->assertNull($last['nextOffset'], 'The final page must not offer another.');
    }

    public function test_list_content_caps_the_limit_through_its_schema()
    {
        $schema = (new ListContentTool())->getInputSchema();

        $this->assertSame(ListContentTool::MAX_LIMIT, $schema['properties']['limit']['maximum']);
        $this->assertSame(ListContentTool::DEFAULT_LIMIT, $schema['properties']['limit']['default']);
    }

    public function test_list_content_shortens_a_long_excerpt()
    {
        $this->givenPost(1, ['post_content' => str_repeat('word ', 200)]);

        $items = (new ListContentTool())->execute([], $this->context())['items'];

        $this->assertLessThanOrEqual(ListContentTool::EXCERPT_LENGTH, mb_strlen($items[0]['excerpt']));
    }

    public function test_block_capabilities_returns_an_index_when_asked_for_nothing_specific()
    {
        $result = (new GetBlockCapabilitiesTool($this->blocks()))->execute([], $this->context());

        $this->assertArrayHasKey('index', $result);
        $this->assertArrayNotHasKey('blocks', $result);
        $this->assertGreaterThan(20, count($result['index']), 'Every manifest should be indexed.');
        $this->assertArrayHasKey('purpose', $result['index'][0]);
        $this->assertArrayNotHasKey('attributes', $result['index'][0], 'The index must stay cheap.');
    }

    public function test_block_capabilities_returns_full_detail_for_the_blocks_named()
    {
        $result = (new GetBlockCapabilitiesTool($this->blocks()))->execute(
            ['blocks' => ['swishfolio-core/hero']],
            $this->context()
        );

        $hero = $result['blocks']['swishfolio-core/hero'];

        $this->assertArrayHasKey('attributes', $hero);
        $this->assertNotEmpty($hero['attributes']);
        $this->assertArrayHasKey('children', $hero);
    }

    public function test_block_capabilities_names_what_it_did_not_recognise()
    {
        $result = (new GetBlockCapabilitiesTool($this->blocks()))->execute(
            ['blocks' => ['swishfolio-core/hero', 'acme/invented']],
            $this->context()
        );

        $this->assertSame(['acme/invented'], $result['unknown']);
        $this->assertArrayHasKey('swishfolio-core/hero', $result['blocks']);
    }

    public function test_list_media_describes_each_attachment_well_enough_to_use()
    {
        $GLOBALS['sfcore_test_wp']['posts'][40] = [
            'ID'             => 40,
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'post_title'     => 'Studio shot',
            'post_mime_type' => 'image/png',
            'post_content'   => '',
            'post_name'      => 'studio',
            'post_author'    => 1,
        ];

        $items = (new ListMediaTool())->execute([], $this->context())['items'];

        $this->assertSame(40, $items[0]['id']);
        $this->assertSame('image/png', $items[0]['mimeType']);
        $this->assertSame(1200, $items[0]['width']);
        $this->assertStringContainsString('40.png', $items[0]['url']);
    }
}
