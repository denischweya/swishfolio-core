<?php

namespace SwishfolioCore\Tests\Unit\Tools\Read;

use SwishfolioCore\Tests\Unit\Tools\ToolsTestCase;
use SwishfolioCore\Tools\Read\ReadPageTool;
use SwishfolioCore\Tools\ToolError;
use SwishfolioCore\Tools\ToolException;

class ReadPageToolTest extends ToolsTestCase
{
    private function tool()
    {
        return new ReadPageTool($this->blocks());
    }

    private function read($postId, array $extra = [])
    {
        return $this->tool()->execute(array_merge(['postId' => $postId], $extra), $this->context());
    }

    public function test_it_returns_the_attributes_the_manifest_declares()
    {
        $this->givenPost(10, [
            'post_content' => '<!-- wp:swishfolio-core/heading {"heading":"Hello","headingTag":"h2"} /-->',
        ]);

        $blocks = $this->read(10)['blocks'];

        $this->assertSame('swishfolio-core/heading', $blocks[0]['name']);
        $this->assertSame('Hello', $blocks[0]['attributes']['heading']);
        $this->assertSame('h2', $blocks[0]['attributes']['headingTag']);
    }

    public function test_an_attribute_the_manifest_does_not_declare_is_withheld()
    {
        // The manifest is simultaneously the AI's description and the
        // validator's allowlist, so returning an attribute outside it would
        // invite an edit the validator then rejects.
        $this->givenPost(10, [
            'post_content' => '<!-- wp:swishfolio-core/heading {"heading":"Hi","sfcoreInternalFlag":true} /-->',
        ]);

        $attributes = $this->read(10)['blocks'][0]['attributes'];

        $this->assertArrayHasKey('heading', $attributes);
        $this->assertArrayNotHasKey('sfcoreInternalFlag', $attributes);
    }

    public function test_a_block_with_no_manifest_is_opaque_with_its_children_kept()
    {
        $this->givenPost(10, [
            'post_content' => '<!-- wp:core/some-unknown-block -->'
                . '<!-- wp:swishfolio-core/heading {"heading":"Inside"} /-->'
                . '<!-- /wp:core/some-unknown-block -->',
        ]);

        $node = $this->read(10)['blocks'][0];

        $this->assertTrue($node['opaque']);
        $this->assertArrayNotHasKey('attributes', $node);
        $this->assertSame('swishfolio-core/heading', $node['children'][0]['name']);
    }

    public function test_every_block_carries_an_index_path_that_addresses_it()
    {
        $this->givenPost(10, [
            'post_content' => '<!-- wp:swishfolio-core/heading {"heading":"A"} /-->'
                . '<!-- wp:swishfolio-core/features -->'
                . '<!-- wp:swishfolio-core/feature-card {"title":"One"} /-->'
                . '<!-- wp:swishfolio-core/feature-card {"title":"Two"} /-->'
                . '<!-- /wp:swishfolio-core/features -->',
        ]);

        $blocks = $this->read(10)['blocks'];

        $this->assertSame([0], $blocks[0]['path']);
        $this->assertSame([1], $blocks[1]['path']);
        $this->assertSame([1, 0], $blocks[1]['children'][0]['path']);
        $this->assertSame([1, 1], $blocks[1]['children'][1]['path']);
    }

    public function test_whitespace_between_blocks_does_not_shift_the_paths()
    {
        // parse_blocks() emits a nameless node for the newlines between blocks.
        // Counting one would make every path after it point at the wrong block.
        $this->givenPost(10, [
            'post_content' => "<!-- wp:swishfolio-core/heading {\"heading\":\"A\"} /-->\n\n"
                . "<!-- wp:swishfolio-core/heading {\"heading\":\"B\"} /-->",
        ]);

        $blocks = $this->read(10)['blocks'];

        $this->assertCount(2, $blocks);
        $this->assertSame([1], $blocks[1]['path']);
        $this->assertSame('B', $blocks[1]['attributes']['heading']);
    }

    public function test_the_node_ceiling_truncates_rather_than_returning_everything()
    {
        $content = str_repeat('<!-- wp:swishfolio-core/heading {"heading":"x"} /-->', 10);

        $this->givenPost(10, ['post_content' => $content]);

        $result = $this->read(10, ['maxNodes' => 4]);

        $this->assertTrue($result['truncated']);
        $this->assertCount(4, $result['blocks']);
        $this->assertSame(4, $result['nodeCount']);
    }

    public function test_the_depth_ceiling_reports_how_many_children_it_did_not_descend_into()
    {
        $this->givenPost(10, [
            'post_content' => '<!-- wp:swishfolio-core/features -->'
                . '<!-- wp:swishfolio-core/feature-card {"title":"One"} /-->'
                . '<!-- wp:swishfolio-core/feature-card {"title":"Two"} /-->'
                . '<!-- /wp:swishfolio-core/features -->',
        ]);

        $result = $this->read(10, ['maxDepth' => 1]);

        $this->assertTrue($result['truncated']);
        $this->assertSame(2, $result['blocks'][0]['childrenOmitted']);
        $this->assertArrayNotHasKey('children', $result['blocks'][0]);
    }

    public function test_a_missing_post_is_reported_as_a_missing_target()
    {
        try {
            $this->read(999999);
            $this->fail('Expected ToolException');
        } catch (ToolException $e) {
            $this->assertSame(ToolError::TARGET_MISSING, $e->getError()->getCode());
        }
    }

    public function test_it_checks_edit_post_on_the_specific_page()
    {
        $this->givenPost(10);
        $this->givenPost(11);

        $GLOBALS['sfcore_test_wp']['caps'] = ['edit_posts', 'edit_post:10'];

        $tool = $this->tool();

        $tool->authorize(['postId' => 10], $this->context());

        $this->expectException(ToolException::class);
        $tool->authorize(['postId' => 11], $this->context());
    }

    public function test_it_reports_the_page_status_and_type_alongside_the_tree()
    {
        $this->givenPost(10, ['post_status' => 'draft', 'post_title' => 'Draft page', 'post_content' => '']);

        $result = $this->read(10);

        $this->assertSame('draft', $result['status']);
        $this->assertSame('page', $result['postType']);
        $this->assertSame('Draft page', $result['title']);
        $this->assertSame([], $result['blocks']);
    }
}
