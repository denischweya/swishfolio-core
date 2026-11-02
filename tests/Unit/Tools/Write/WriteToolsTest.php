<?php

namespace SwishfolioCore\Tests\Unit\Tools\Write;

use SwishfolioCore\Tests\Unit\Tools\ToolsTestCase;
use SwishfolioCore\Tools\Journal\JournalEntry;
use SwishfolioCore\Tools\ToolError;
use SwishfolioCore\Tools\ToolException;
use SwishfolioCore\Tools\Write\CreateContentTool;
use SwishfolioCore\Tools\Write\SetFrontPageTool;
use SwishfolioCore\Tools\Write\SetNavigationTool;
use SwishfolioCore\Tools\Write\SetStyleVariationTool;
use SwishfolioCore\Tools\Write\UpdateContentTool;

/**
 * Ported from ServerActionHandlerTest when the five handlers became tools. The
 * assertions are the originals; what changed is the class under test, and the
 * four error-reason assertions that now read a unified ToolError code
 * ("write_failed", "target_missing") instead of the per-handler slug each
 * handler invented ("insert_failed", "not_a_page", "empty_menu",
 * "unknown_variation"). The refusals happen at the same points, for the same
 * reasons, with the same messages.
 *
 * The journal assertions are new: they are the behaviour the migration added.
 */
class WriteToolsTest extends ToolsTestCase
{
    private function createInput(array $overrides = [])
    {
        return array_merge(
            [
                'key'     => 'home',
                'title'   => 'Home',
                'slug'    => 'home',
                'content' => '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->',
            ],
            $overrides
        );
    }

    private function styleTool()
    {
        return new SetStyleVariationTool(
            $this->journal,
            function () {
                return [
                    ['title' => 'Default', 'version' => 3],
                    [
                        'title'    => 'Swiss International',
                        'version'  => 3,
                        'settings' => ['color' => ['palette' => [['slug' => 'accent-1', 'color' => '#FF3000']]]],
                        'styles'   => ['color' => ['background' => 'var:preset|color|base']],
                    ],
                ];
            },
            function () {
                return 23;
            }
        );
    }

    public function test_create_page_writes_a_draft_page_owned_by_the_current_user()
    {
        $context = $this->context();
        $tool    = new CreateContentTool($this->journal);

        $tool->authorize($this->createInput(), $context);
        $result = $tool->execute($this->createInput(), $context);

        $post = $this->post($result['postId']);

        $this->assertSame('page', $post['post_type']);
        $this->assertSame('draft', $post['post_status']);
        $this->assertSame(7, $post['post_author']);
        $this->assertSame('Home', $post['post_title']);
        $this->assertSame([$result['postId']], $context->getCreatedIds());
        $this->assertSame($result['postId'], $context->getPageId('home'));
    }

    public function test_create_page_ignores_a_status_the_plan_tries_to_smuggle_in()
    {
        $result = (new CreateContentTool($this->journal))->execute(
            $this->createInput(['post_status' => 'publish', 'status' => 'publish']),
            $this->context()
        );

        $this->assertSame('draft', $this->post($result['postId'])['post_status']);
    }

    public function test_create_page_writes_the_template_meta_when_one_is_given()
    {
        $result = (new CreateContentTool($this->journal))->execute(
            $this->createInput(['template' => 'page-no-title']),
            $this->context()
        );

        $this->assertSame(
            'page-no-title',
            $GLOBALS['sfcore_test_wp']['meta'][$result['postId']]['_wp_page_template']
        );
    }

    public function test_create_page_writes_no_meta_when_no_template_is_given()
    {
        $result = (new CreateContentTool($this->journal))->execute($this->createInput(), $this->context());

        $this->assertArrayNotHasKey($result['postId'], $GLOBALS['sfcore_test_wp']['meta']);
    }

    public function test_create_page_is_refused_without_the_page_post_types_own_capability()
    {
        $GLOBALS['sfcore_test_wp']['caps'] = ['edit_posts'];

        $this->expectException(ToolException::class);
        (new CreateContentTool($this->journal))->authorize($this->createInput(), $this->context());
    }

    public function test_create_page_surfaces_a_wordpress_failure()
    {
        $GLOBALS['sfcore_test_wp']['insertError'] = 'Database is on fire.';

        try {
            (new CreateContentTool($this->journal))->execute($this->createInput(), $this->context());
            $this->fail('Expected ToolException');
        } catch (ToolException $e) {
            $this->assertSame(ToolError::WRITE_FAILED, $e->getError()->getCode());
            $this->assertSame('Database is on fire.', $e->getMessage());
        }
    }

    public function test_create_page_records_the_new_id_as_its_undo()
    {
        $result = (new CreateContentTool($this->journal))->execute($this->createInput(), $this->context());

        $targets = $this->repository->ofType(JournalEntry::TARGET_POST);

        $this->assertCount(1, $targets);
        $this->assertSame(['created' => $result['postId']], $targets[0]->getPrior());
    }

    public function test_update_content_refuses_a_type_outside_the_closed_set()
    {
        // Widened from pages to pages, posts and projects — but no further. A
        // navigation menu, a revision or an attachment is not editable content,
        // and accepting one here would let a model rewrite site plumbing.
        foreach (['wp_navigation', 'wp_global_styles', 'attachment', 'revision'] as $type) {
            $this->givenPost(50, ['post_type' => $type]);

            try {
                (new UpdateContentTool($this->journal))->authorize(['postId' => 50], $this->context());
                $this->fail('Accepted ' . $type);
            } catch (ToolException $e) {
                $this->assertSame(ToolError::TARGET_MISSING, $e->getError()->getCode());
            }
        }
    }

    public function test_update_content_accepts_posts_and_projects_as_well_as_pages()
    {
        foreach (['page', 'post', 'sfcore_project'] as $index => $type) {
            $postId = 70 + $index;

            $this->givenPost($postId, ['post_type' => $type, 'post_status' => 'draft']);

            (new UpdateContentTool($this->journal))->execute(
                ['postId' => $postId, 'content' => 'new body'],
                $this->context()
            );

            $this->assertSame('new body', $this->post($postId)['post_content'], $type . ' was not updated.');
        }
    }

    public function test_create_content_creates_the_type_it_was_asked_for()
    {
        foreach (['page', 'post', 'sfcore_project'] as $type) {
            $result = (new CreateContentTool($this->journal))->execute(
                $this->createInput(['postType' => $type, 'key' => 'k-' . $type]),
                $this->context()
            );

            $this->assertSame($type, $this->post($result['postId'])['post_type']);
            $this->assertSame('draft', $this->post($result['postId'])['post_status'], 'Still never published.');
        }
    }

    public function test_create_content_defaults_to_a_page_so_generation_needs_no_change()
    {
        $result = (new CreateContentTool($this->journal))->execute($this->createInput(), $this->context());

        $this->assertSame('page', $this->post($result['postId'])['post_type']);
    }

    public function test_a_template_is_written_only_for_a_page()
    {
        // Templates are a page concept; storing one on a project would be meta
        // the theme never reads.
        $result = (new CreateContentTool($this->journal))->execute(
            $this->createInput(['postType' => 'sfcore_project', 'template' => 'page-no-title']),
            $this->context()
        );

        $this->assertNull($result['template']);
        $this->assertArrayNotHasKey($result['postId'], $GLOBALS['sfcore_test_wp']['meta']);
    }

    public function test_update_page_checks_the_capability_against_that_specific_post()
    {
        $this->givenPost(50, ['post_status' => 'draft']);
        $this->givenPost(51, ['post_status' => 'draft']);

        $GLOBALS['sfcore_test_wp']['caps'] = ['edit_post:50'];

        $tool = new UpdateContentTool($this->journal);

        $tool->authorize(['postId' => 50], $this->context());

        $this->expectException(ToolException::class);
        $tool->authorize(['postId' => 51], $this->context());
    }

    public function test_update_page_replaces_content_without_touching_status()
    {
        $this->givenPost(50, ['post_status' => 'publish', 'post_content' => 'old']);

        (new UpdateContentTool($this->journal))->execute(
            ['postId' => 50, 'content' => 'new'],
            $this->context(true)
        );

        $this->assertSame('new', $this->post(50)['post_content']);
        $this->assertSame('publish', $this->post(50)['post_status']);
    }

    public function test_update_page_demands_confirmation_only_when_the_target_is_published()
    {
        $this->givenPost(50, ['post_status' => 'draft']);
        $this->givenPost(51, ['post_status' => 'publish']);

        $tool = new UpdateContentTool($this->journal);

        $tool->authorize(['postId' => 50], $this->context());

        try {
            $tool->authorize(['postId' => 51], $this->context());
            $this->fail('Expected ToolException');
        } catch (ToolException $e) {
            $this->assertSame(ToolError::CONFIRM_REQUIRED, $e->getError()->getCode());
        }
    }

    public function test_previewing_an_update_to_a_published_page_asks_for_confirmation_rather_than_failing()
    {
        // update-page is the one tool whose enforced side-effect class depends
        // on its target, and it reports that from authorize(). A preview that
        // let the refusal through would make "change my published About page"
        // an error instead of a prompt.
        $this->givenPost(50, ['post_status' => 'publish', 'post_title' => 'About']);

        $runner = $this->runner([new UpdateContentTool($this->journal)]);

        $preview = $runner->preview(
            [['name' => 'swishfolio/update-content', 'input' => ['postId' => 50, 'content' => 'new']]],
            $this->context()
        );

        $this->assertTrue($preview['requiresConfirmation']);
        $this->assertTrue($preview['previews'][0]['requiresConfirmation']);
        $this->assertStringContainsString('About', $preview['previews'][0]['summary']);
        $this->assertSame('publish', $this->post(50)['post_status'], 'A preview must change nothing.');
        $this->assertSame('', $this->post(50)['post_content']);
    }

    public function test_previewing_an_update_to_a_draft_needs_no_confirmation()
    {
        $this->givenPost(50, ['post_status' => 'draft']);

        $preview = $this->runner([new UpdateContentTool($this->journal)])->preview(
            [['name' => 'swishfolio/update-content', 'input' => ['postId' => 50, 'content' => 'new']]],
            $this->context()
        );

        $this->assertFalse($preview['requiresConfirmation']);
    }

    public function test_a_preview_still_refuses_a_call_the_user_may_never_make()
    {
        $this->givenPost(50, ['post_status' => 'draft']);

        $GLOBALS['sfcore_test_wp']['caps'] = ['edit_posts'];

        $this->expectException(ToolException::class);

        $this->runner([new UpdateContentTool($this->journal)])->preview(
            [['name' => 'swishfolio/update-content', 'input' => ['postId' => 50, 'content' => 'new']]],
            $this->context()
        );
    }

    public function test_a_confirmed_token_then_lets_the_published_update_through()
    {
        $this->givenPost(50, ['post_status' => 'publish', 'post_content' => 'before']);

        $runner = $this->runner([new UpdateContentTool($this->journal)]);
        $calls  = [['name' => 'swishfolio/update-content', 'input' => ['postId' => 50, 'content' => 'after']]];

        $token = $runner->preview($calls, $this->context())['confirmToken'];

        $runner->runPlan($calls, $this->context(), $token);

        $this->assertSame('after', $this->post(50)['post_content']);
    }

    public function test_update_page_captures_a_revision_before_overwriting()
    {
        $this->givenPost(50, ['post_status' => 'draft', 'post_content' => 'old']);

        (new UpdateContentTool($this->journal))->execute(
            ['postId' => 50, 'content' => 'new'],
            $this->context()
        );

        $prior    = $this->repository->ofType(JournalEntry::TARGET_POST)[0]->getPrior();
        $revision = $GLOBALS['sfcore_test_wp']['revisions'][$prior['revision']];

        $this->assertSame('old', $revision['post_content']);
    }

    public function test_set_front_page_writes_nothing_while_the_target_is_a_draft()
    {
        $this->givenPost(60, ['post_status' => 'draft']);

        $result = (new SetFrontPageTool($this->journal))->execute(['postId' => 60], $this->context(true));

        $this->assertFalse($result['applied']);
        $this->assertTrue($result['deferred']);
        $this->assertSame([], $GLOBALS['sfcore_test_options']);
        $this->assertSame([], $this->repository->rows, 'A deferred call has nothing to undo.');
    }

    public function test_set_front_page_writes_the_options_for_a_published_page()
    {
        $this->givenPost(60, ['post_status' => 'publish']);
        $this->givenPost(61, ['post_status' => 'publish']);

        $result = (new SetFrontPageTool($this->journal))->execute(
            ['postId' => 60, 'blogPageId' => 61],
            $this->context(true)
        );

        $this->assertTrue($result['applied']);
        $this->assertSame('page', $GLOBALS['sfcore_test_options']['show_on_front']);
        $this->assertSame(60, $GLOBALS['sfcore_test_options']['page_on_front']);
        $this->assertSame(61, $GLOBALS['sfcore_test_options']['page_for_posts']);
    }

    public function test_set_front_page_records_each_option_it_touched_separately()
    {
        $this->givenPost(60, ['post_status' => 'publish']);
        $this->givenPost(61, ['post_status' => 'publish']);

        (new SetFrontPageTool($this->journal))->execute(
            ['postId' => 60, 'blogPageId' => 61],
            $this->context(true)
        );

        $targets = $this->repository->ofType(JournalEntry::TARGET_OPTION);
        $names   = array_map(
            static function (JournalEntry $entry) {
                return $entry->getTargetId();
            },
            $targets
        );

        $this->assertSame(['show_on_front', 'page_on_front', 'page_for_posts'], $names);
    }

    public function test_set_front_page_needs_manage_options()
    {
        $GLOBALS['sfcore_test_wp']['caps'] = ['edit_pages'];

        $this->expectException(ToolException::class);
        (new SetFrontPageTool($this->journal))->authorize(['postId' => 60], $this->context());
    }

    public function test_set_navigation_creates_a_published_menu_of_navigation_links()
    {
        $context = $this->context(true);

        $result = (new SetNavigationTool($this->journal))->execute(
            [
                'title' => 'Acme',
                'items' => [
                    ['label' => 'Home', 'url' => 'https://example.test/home/', 'postId' => 60],
                    ['label' => 'Work', 'url' => 'https://example.test/work/'],
                    ['label' => '', 'url' => 'https://example.test/dropped/'],
                ],
            ],
            $context
        );

        $post = $this->post($result['postId']);

        $this->assertSame('wp_navigation', $post['post_type']);
        $this->assertSame('publish', $post['post_status']);
        $this->assertSame(2, $result['items']);
        $this->assertStringContainsString('"kind":"post-type"', $post['post_content']);
        $this->assertStringContainsString('wp:navigation-link', $post['post_content']);
        $this->assertStringNotContainsString('dropped', $post['post_content']);
        $this->assertSame([$result['postId']], $context->getCreatedIds());
    }

    public function test_set_navigation_refuses_an_empty_menu()
    {
        try {
            (new SetNavigationTool($this->journal))->execute(['items' => []], $this->context(true));
            $this->fail('Expected ToolException');
        } catch (ToolException $e) {
            $this->assertSame(ToolError::WRITE_FAILED, $e->getError()->getCode());
        }
    }

    public function test_set_navigation_needs_edit_theme_options()
    {
        $GLOBALS['sfcore_test_wp']['caps'] = ['edit_pages'];

        $this->expectException(ToolException::class);
        (new SetNavigationTool($this->journal))->authorize(['items' => []], $this->context());
    }

    public function test_set_style_variation_writes_the_user_global_styles_post()
    {
        $this->givenPost(23, ['post_type' => 'wp_global_styles', 'post_content' => '{}']);

        $result = $this->styleTool()->execute(['title' => 'Swiss International'], $this->context(true));

        $stored = json_decode($this->post(23)['post_content'], true);

        $this->assertTrue($result['applied']);
        $this->assertTrue($stored['isGlobalStylesUserThemeJSON']);
        $this->assertSame(3, $stored['version']);
        $this->assertSame('#FF3000', $stored['settings']['color']['palette'][0]['color']);
    }

    public function test_set_style_variation_matches_a_localised_title_by_slug()
    {
        $this->givenPost(23, ['post_type' => 'wp_global_styles', 'post_content' => '{}']);

        $result = $this->styleTool()->execute(['title' => 'swiss-international'], $this->context(true));

        $this->assertSame('Swiss International', $result['title']);
    }

    public function test_set_style_variation_refuses_a_variation_the_theme_does_not_ship()
    {
        $this->givenPost(23, ['post_type' => 'wp_global_styles', 'post_content' => '{}']);

        try {
            $this->styleTool()->execute(['title' => 'Bauhaus'], $this->context(true));
            $this->fail('Expected ToolException');
        } catch (ToolException $e) {
            $this->assertSame(ToolError::TARGET_MISSING, $e->getError()->getCode());
            $this->assertSame(['Default', 'Swiss International'], $e->getError()->getDetails()['available']);
        }
    }

    public function test_set_style_variation_checks_edit_post_on_the_global_styles_post_itself()
    {
        $this->givenPost(23, ['post_type' => 'wp_global_styles']);

        $GLOBALS['sfcore_test_wp']['caps'] = ['edit_theme_options'];

        $this->expectException(ToolException::class);
        $this->styleTool()->authorize(['title' => 'Swiss International'], $this->context());
    }

    public function test_set_style_variation_is_now_reversible()
    {
        $this->givenPost(23, ['post_type' => 'wp_global_styles', 'post_content' => '{"version":3}']);

        $this->styleTool()->execute(['title' => 'Swiss International'], $this->context(true));

        $prior = $this->repository->ofType(JournalEntry::TARGET_POST)[0]->getPrior();

        $this->assertSame(
            '{"version":3}',
            $GLOBALS['sfcore_test_wp']['revisions'][$prior['revision']]['post_content']
        );
    }

    public function test_create_page_stores_block_comment_escapes_byte_for_byte()
    {
        // A design token serialises as var(--wp--preset...).
        // wp_insert_post() unslashes what it is handed, so a tool that forgets
        // to slash silently turns every - into a bare u002d and corrupts
        // the token. This is the regression test for that.
        $content = '<!-- wp:swishfolio-core/portfolio '
            . '{"taxonomyColor":"var(--wp--preset--color--accent-1)"} /-->';

        $result = (new CreateContentTool($this->journal))->execute(
            $this->createInput(['content' => $content]),
            $this->context()
        );

        $this->assertSame($content, $this->post($result['postId'])['post_content']);
    }

    public function test_update_page_stores_block_comment_escapes_byte_for_byte()
    {
        $this->givenPost(50, ['post_status' => 'draft']);

        $content = '<!-- wp:swishfolio-core/latest-posts {"titleColor":"var(--wp--preset--color--base)"} /-->';

        (new UpdateContentTool($this->journal))->execute(
            ['postId' => 50, 'content' => $content],
            $this->context()
        );

        $this->assertSame($content, $this->post(50)['post_content']);
    }

    public function test_set_style_variation_stores_valid_json()
    {
        $this->givenPost(23, ['post_type' => 'wp_global_styles', 'post_content' => '{}']);

        $this->styleTool()->execute(['title' => 'Swiss International'], $this->context(true));

        $stored = $this->post(23)['post_content'];

        $this->assertIsArray(json_decode($stored, true), 'Global styles content must stay valid JSON: ' . $stored);
        $this->assertSame(JSON_ERROR_NONE, json_last_error());
    }
}
