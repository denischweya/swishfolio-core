<?php

namespace SwishfolioCore\Tests\Unit\Generation;

use SwishfolioCore\Actions\Handlers\CreatePageHandler;
use SwishfolioCore\Actions\Handlers\ExecutionContext;
use SwishfolioCore\Actions\Handlers\ExecutionException;
use SwishfolioCore\Actions\Handlers\SetFrontPageHandler;
use SwishfolioCore\Actions\Handlers\SetNavigationHandler;
use SwishfolioCore\Actions\Handlers\SetStyleVariationHandler;
use SwishfolioCore\Actions\Handlers\UpdatePageHandler;

class ServerActionHandlerTest extends GenerationTestCase
{
    private function runContext()
    {
        return new ExecutionContext(7);
    }

    private function createAction(array $overrides = [])
    {
        return array_merge([
            'type'     => 'create_page',
            'key'      => 'home',
            'title'    => 'Home',
            'slug'     => 'home',
            'template' => null,
            'content'  => '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->',
        ], $overrides);
    }

    public function test_create_page_writes_a_draft_page_owned_by_the_current_user()
    {
        $context = $this->runContext();
        $handler = new CreatePageHandler();

        $handler->authorize($this->createAction(), $context);
        $result = $handler->execute($this->createAction(), $context);

        $post = $GLOBALS['sfcore_test_wp']['posts'][$result['postId']];

        $this->assertSame('page', $post['post_type']);
        $this->assertSame('draft', $post['post_status']);
        $this->assertSame(7, $post['post_author']);
        $this->assertSame('Home', $post['post_title']);
        $this->assertSame([$result['postId']], $context->getCreatedIds());
        $this->assertSame($result['postId'], $context->getPageId('home'));
    }

    public function test_create_page_ignores_a_status_the_plan_tries_to_smuggle_in()
    {
        $context = $this->runContext();
        $result  = (new CreatePageHandler())->execute(
            $this->createAction(['post_status' => 'publish', 'status' => 'publish']),
            $context
        );

        $this->assertSame('draft', $GLOBALS['sfcore_test_wp']['posts'][$result['postId']]['post_status']);
    }

    public function test_create_page_writes_the_template_meta_when_one_is_given()
    {
        $context = $this->runContext();
        $result  = (new CreatePageHandler())->execute(
            $this->createAction(['template' => 'page-no-title']),
            $context
        );

        $this->assertSame(
            'page-no-title',
            $GLOBALS['sfcore_test_wp']['meta'][$result['postId']]['_wp_page_template']
        );
    }

    public function test_create_page_writes_no_meta_when_no_template_is_given()
    {
        $context = $this->runContext();
        $result  = (new CreatePageHandler())->execute($this->createAction(), $context);

        $this->assertArrayNotHasKey($result['postId'], $GLOBALS['sfcore_test_wp']['meta']);
    }

    public function test_create_page_is_refused_without_the_page_post_types_own_capability()
    {
        $GLOBALS['sfcore_test_wp']['caps'] = ['edit_posts'];

        $this->expectException(ExecutionException::class);
        (new CreatePageHandler())->authorize($this->createAction(), $this->runContext());
    }

    public function test_create_page_surfaces_a_wordpress_failure()
    {
        $GLOBALS['sfcore_test_wp']['insertError'] = 'Database is on fire.';

        try {
            (new CreatePageHandler())->execute($this->createAction(), $this->runContext());
            $this->fail('Expected ExecutionException');
        } catch (ExecutionException $e) {
            $this->assertSame('insert_failed', $e->getReason());
            $this->assertSame('Database is on fire.', $e->getMessage());
        }
    }

    public function test_update_page_refuses_anything_that_is_not_a_page()
    {
        $this->givenPost(50, ['post_type' => 'post']);

        try {
            (new UpdatePageHandler())->authorize(['postId' => 50], $this->runContext());
            $this->fail('Expected ExecutionException');
        } catch (ExecutionException $e) {
            $this->assertSame('not_a_page', $e->getReason());
        }
    }

    public function test_update_page_checks_the_capability_against_that_specific_post()
    {
        $this->givenPost(50);
        $this->givenPost(51);

        $GLOBALS['sfcore_test_wp']['caps'] = ['edit_post:50'];

        $handler = new UpdatePageHandler();

        $handler->authorize(['postId' => 50], $this->runContext());

        $this->expectException(ExecutionException::class);
        $handler->authorize(['postId' => 51], $this->runContext());
    }

    public function test_update_page_replaces_content_without_touching_status()
    {
        $this->givenPost(50, ['post_status' => 'publish', 'post_content' => 'old']);

        (new UpdatePageHandler())->execute(['postId' => 50, 'content' => 'new'], $this->runContext());

        $this->assertSame('new', $GLOBALS['sfcore_test_wp']['posts'][50]['post_content']);
        $this->assertSame('publish', $GLOBALS['sfcore_test_wp']['posts'][50]['post_status']);
    }

    public function test_set_front_page_writes_nothing_while_the_target_is_a_draft()
    {
        $this->givenPost(60, ['post_status' => 'draft']);

        $result = (new SetFrontPageHandler())->execute(['postId' => 60], $this->runContext());

        $this->assertFalse($result['applied']);
        $this->assertTrue($result['deferred']);
        $this->assertSame([], $GLOBALS['sfcore_test_options']);
    }

    public function test_set_front_page_writes_the_options_for_a_published_page()
    {
        $this->givenPost(60, ['post_status' => 'publish']);
        $this->givenPost(61, ['post_status' => 'publish']);

        $result = (new SetFrontPageHandler())->execute(
            ['postId' => 60, 'blogPageId' => 61],
            $this->runContext()
        );

        $this->assertTrue($result['applied']);
        $this->assertSame('page', $GLOBALS['sfcore_test_options']['show_on_front']);
        $this->assertSame(60, $GLOBALS['sfcore_test_options']['page_on_front']);
        $this->assertSame(61, $GLOBALS['sfcore_test_options']['page_for_posts']);
    }

    public function test_set_front_page_needs_manage_options()
    {
        $GLOBALS['sfcore_test_wp']['caps'] = ['edit_pages'];

        $this->expectException(ExecutionException::class);
        (new SetFrontPageHandler())->authorize(['postId' => 60], $this->runContext());
    }

    public function test_set_navigation_creates_a_published_menu_of_navigation_links()
    {
        $context = $this->runContext();

        $result = (new SetNavigationHandler())->execute(
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

        $post = $GLOBALS['sfcore_test_wp']['posts'][$result['postId']];

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
            (new SetNavigationHandler())->execute(['items' => []], $this->runContext());
            $this->fail('Expected ExecutionException');
        } catch (ExecutionException $e) {
            $this->assertSame('empty_menu', $e->getReason());
        }
    }

    public function test_set_navigation_needs_edit_theme_options()
    {
        $GLOBALS['sfcore_test_wp']['caps'] = ['edit_pages'];

        $this->expectException(ExecutionException::class);
        (new SetNavigationHandler())->authorize(['items' => []], $this->runContext());
    }

    public function test_set_style_variation_writes_the_user_global_styles_post()
    {
        $this->givenPost(23, ['post_type' => 'wp_global_styles', 'post_content' => '{}']);

        $handler = $this->styleHandler();
        $result  = $handler->execute(['title' => 'Swiss International'], $this->runContext());

        $stored = json_decode($GLOBALS['sfcore_test_wp']['posts'][23]['post_content'], true);

        $this->assertTrue($result['applied']);
        $this->assertTrue($stored['isGlobalStylesUserThemeJSON']);
        $this->assertSame(3, $stored['version']);
        $this->assertSame('#FF3000', $stored['settings']['color']['palette'][0]['color']);
    }

    public function test_set_style_variation_matches_a_localised_title_by_slug()
    {
        $this->givenPost(23, ['post_type' => 'wp_global_styles', 'post_content' => '{}']);

        $result = $this->styleHandler()->execute(['title' => 'swiss-international'], $this->runContext());

        $this->assertSame('Swiss International', $result['title']);
    }

    public function test_set_style_variation_refuses_a_variation_the_theme_does_not_ship()
    {
        $this->givenPost(23, ['post_type' => 'wp_global_styles', 'post_content' => '{}']);

        try {
            $this->styleHandler()->execute(['title' => 'Bauhaus'], $this->runContext());
            $this->fail('Expected ExecutionException');
        } catch (ExecutionException $e) {
            $this->assertSame('unknown_variation', $e->getReason());
        }
    }

    public function test_set_style_variation_checks_edit_post_on_the_global_styles_post_itself()
    {
        $this->givenPost(23, ['post_type' => 'wp_global_styles']);

        $GLOBALS['sfcore_test_wp']['caps'] = ['edit_theme_options'];

        $this->expectException(ExecutionException::class);
        $this->styleHandler()->authorize(['title' => 'Swiss International'], $this->runContext());
    }

public function test_create_page_stores_block_comment_escapes_byte_for_byte()
    {
        // A design token serialises as var(\u002d\u002dwp\u002d\u002dpreset...).
        // wp_insert_post() unslashes what it is handed, so a handler that forgets
        // to slash silently turns every \u002d into a bare u002d and corrupts the
        // token. This is the regression test for that.
        $content = '<!-- wp:swishfolio-core/portfolio '
            . '{"taxonomyColor":"var(\u002d\u002dwp\u002d\u002dpreset\u002d\u002dcolor\u002d\u002daccent-1)"} /-->';

        $context = $this->runContext();
        $result  = (new CreatePageHandler())->execute($this->createAction(['content' => $content]), $context);

        $this->assertSame($content, $GLOBALS['sfcore_test_wp']['posts'][$result['postId']]['post_content']);
    }

    public function test_update_page_stores_block_comment_escapes_byte_for_byte()
    {
        $this->givenPost(50);

        $content = '<!-- wp:swishfolio-core/latest-posts {"titleColor":"var(\u002d\u002dwp\u002d\u002dpreset\u002d\u002dcolor\u002d\u002dbase)"} /-->';

        (new UpdatePageHandler())->execute(['postId' => 50, 'content' => $content], $this->runContext());

        $this->assertSame($content, $GLOBALS['sfcore_test_wp']['posts'][50]['post_content']);
    }

    public function test_set_style_variation_stores_valid_json()
    {
        $this->givenPost(23, ['post_type' => 'wp_global_styles', 'post_content' => '{}']);

        $this->styleHandler()->execute(['title' => 'Swiss International'], $this->runContext());

        $stored = $GLOBALS['sfcore_test_wp']['posts'][23]['post_content'];

        $this->assertIsArray(json_decode($stored, true), 'Global styles content must stay valid JSON: ' . $stored);
        $this->assertSame(JSON_ERROR_NONE, json_last_error());
    }

    /**
     * @return SetStyleVariationHandler
     */
    private function styleHandler()
    {
        return new SetStyleVariationHandler(
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
}