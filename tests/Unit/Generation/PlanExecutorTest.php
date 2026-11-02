<?php

namespace SwishfolioCore\Tests\Unit\Generation;

require_once __DIR__ . '/../Tools/wp-tools-stubs.php';

use SwishfolioCore\Actions\ActionExecutor;
use SwishfolioCore\Actions\Handlers\ExecutionException;
use SwishfolioCore\AI\Generation\BlockComposer;
use SwishfolioCore\AI\Generation\PlanExecutor;
use SwishfolioCore\AI\Generation\PlanValidator;
use SwishfolioCore\Tests\Support\InMemoryJournalRepository;
use SwishfolioCore\Tools\Journal\MutationJournal;
use SwishfolioCore\Tools\ToolContext;
use SwishfolioCore\Tools\ToolRegistry;
use SwishfolioCore\Tools\ToolRunner;
use SwishfolioCore\Tools\Write\CreateContentTool;
use SwishfolioCore\Tools\Write\SetFrontPageTool;
use SwishfolioCore\Tools\Write\SetNavigationTool;
use SwishfolioCore\Tools\Write\SetStyleVariationTool;
use SwishfolioCore\Tools\Write\UpdateContentTool;

class PlanExecutorTest extends GenerationTestCase
{
    /** @var InMemoryJournalRepository */
    private $repository;

    protected function setUp(): void
    {
        parent::setUp();

        sfcore_test_tools_reset();

        $this->repository = new InMemoryJournalRepository();
    }

    private function executor()
    {
        $journal = new MutationJournal($this->repository);

        return new PlanExecutor(
            new BlockComposer($this->library()),
            new ActionExecutor(
                new ToolRunner(
                    new ToolRegistry([
                        new CreateContentTool($journal),
                        new UpdateContentTool($journal),
                        new SetFrontPageTool($journal),
                        new SetNavigationTool($journal),
                        new SetStyleVariationTool(
                            $journal,
                            function () {
                                return [['title' => 'Swiss International', 'version' => 3, 'styles' => []]];
                            },
                            function () {
                                return 23;
                            }
                        ),
                    ]),
                    $journal
                )
            )
        );
    }

    /**
     * A generation run carries the user's approval of the plan, which the three
     * site-level tools require: they publish a menu, repoint the front page and
     * reskin the site.
     *
     * @return ToolContext
     */
    private function approvedContext()
    {
        $context = ToolContext::forUser(7);

        $context->setConfirmed(true);

        return $context;
    }

    /**
     * @param array $plan Raw plan.
     * @param array $existingSlugs slug => post ID.
     *
     * @return array
     */
    private function runPlan(array $plan, array $existingSlugs = [])
    {
        $validated = PlanValidator::withDefaultStages()->validate($plan, $this->context('site', $existingSlugs));

        return $this->executor()->execute($validated, 7, $this->approvedContext());
    }

    private function twoPagePlan(array $overrides = [])
    {
        return $this->plan(array_merge([
            'pages' => [
                $this->page(),
                $this->page([
                    'key'      => 'about',
                    'title'    => 'About',
                    'slug'     => 'about',
                    'sections' => [$this->patternSection(['headline' => 'Who we are'])],
                ]),
            ],
            'site' => [
                'title'      => 'Acme',
                'frontPage'  => 'home',
                'navigation' => ['home', 'about'],
            ],
        ], $overrides));
    }

    public function test_it_creates_every_page_as_a_draft_and_reports_their_ids()
    {
        $report = $this->runPlan($this->twoPagePlan());

        $this->assertCount(2, $report['pages']);
        $this->assertCount(3, $report['createdIds'], 'Two pages plus one navigation menu.');

        foreach ($report['pages'] as $page) {
            $this->assertTrue($page['created']);
            $this->assertSame('draft', $GLOBALS['sfcore_test_wp']['posts'][$page['postId']]['post_status']);
        }
    }

    public function test_page_content_is_real_block_markup()
    {
        $report  = $this->runPlan($this->twoPagePlan());
        $content = $GLOBALS['sfcore_test_wp']['posts'][$report['pages'][0]['postId']]['post_content'];

        $this->assertStringContainsString('<!-- wp:group', $content);
        $this->assertStringContainsString('Built to last', $content);
        $this->assertStringNotContainsString('{{slot:', $content);
    }

    public function test_an_internal_page_link_becomes_a_permalink_in_a_second_pass()
    {
        $plan = $this->twoPagePlan();

        $plan['pages'][0]['sections'] = [
            $this->patternSection(['headline' => 'Hello', 'ctaUrl' => '{{page:about}}']),
        ];

        $report  = $this->runPlan($plan);
        $homeId  = $report['pages'][0]['postId'];
        $aboutId = $report['pages'][1]['postId'];
        $content = $GLOBALS['sfcore_test_wp']['posts'][$homeId]['post_content'];

        $this->assertStringNotContainsString('{{page:', $content);
        $this->assertStringContainsString('href="https://example.test/about/"', $content);
        $this->assertGreaterThan(0, $aboutId);
    }

    public function test_a_page_with_no_internal_link_is_never_updated_twice()
    {
        $report = $this->runPlan($this->twoPagePlan());
        $types  = [];

        foreach ($report['actions'] as $action) {
            $types[] = $action['type'];
        }

        $this->assertNotContains('update_page', $types);
    }

    public function test_the_front_page_is_deferred_because_generated_pages_are_drafts()
    {
        $report = $this->runPlan($this->twoPagePlan());
        $front  = null;

        foreach ($report['actions'] as $action) {
            if ($action['type'] === 'set_front_page') {
                $front = $action;
            }
        }

        $this->assertNotNull($front);
        $this->assertFalse($front['applied']);
        $this->assertTrue($front['deferred']);
        $this->assertSame([], $GLOBALS['sfcore_test_options']);
    }

    public function test_navigation_is_built_from_page_titles_and_permalinks()
    {
        $report = $this->runPlan($this->twoPagePlan());
        $menu   = null;

        foreach ($report['actions'] as $action) {
            if ($action['type'] === 'set_navigation') {
                $menu = $action;
            }
        }

        $this->assertNotNull($menu);
        $this->assertSame(2, $menu['items']);

        $content = $GLOBALS['sfcore_test_wp']['posts'][$menu['postId']]['post_content'];

        $this->assertStringContainsString('"label":"Home"', $content);
        $this->assertStringContainsString('"label":"About"', $content);
        $this->assertStringContainsString('https://example.test/about/', $content);
    }

    public function test_skip_reuses_the_existing_page_and_creates_nothing_for_it()
    {
        $this->givenPost(42, ['post_name' => 'about', 'post_title' => 'About', 'post_status' => 'publish']);

        $plan                              = $this->twoPagePlan();
        $plan['pages'][1]['onConflict']    = 'skip';

        $report = $this->runPlan($plan, ['about' => 42]);

        $this->assertSame(42, $report['pages'][1]['postId']);
        $this->assertTrue($report['pages'][1]['reused']);
        $this->assertFalse($report['pages'][1]['created']);
        $this->assertNotContains(42, $report['createdIds']);
        $this->assertSame('', $GLOBALS['sfcore_test_wp']['posts'][42]['post_content'], 'The existing page is untouched.');
    }

    /**
     * Regression: a page the plan chose to SKIP must never be rewritten, even
     * when its own composed content carries an internal {{page:...}} link.
     *
     * The link-resolution pass had no existingPostId guard, so it emitted an
     * update_page for the one page the site owner asked us to leave alone —
     * destroying whatever they had published there.
     */
    public function test_a_skipped_page_is_never_overwritten_by_the_link_resolution_pass()
    {
        $this->givenPost(42, [
            'post_name'    => 'about',
            'post_title'   => 'About',
            'post_status'  => 'publish',
            'post_content' => '<!-- wp:paragraph --><p>Hand-written copy the owner keeps.</p><!-- /wp:paragraph -->',
        ]);

        $plan                           = $this->twoPagePlan();
        $plan['pages'][1]['onConflict'] = 'skip';
        // The skipped page links back to home, so the relink pass reaches it.
        $plan['pages'][1]['sections']   = [
            $this->patternSection(['headline' => 'About us', 'ctaUrl' => '{{page:home}}']),
        ];

        $report = $this->runPlan($plan, ['about' => 42]);

        $this->assertSame(
            '<!-- wp:paragraph --><p>Hand-written copy the owner keeps.</p><!-- /wp:paragraph -->',
            $GLOBALS['sfcore_test_wp']['posts'][42]['post_content'],
            'A skipped page must keep its original content.'
        );

        foreach ($report['actions'] as $action) {
            if ($action['type'] === 'update_page') {
                $this->assertNotSame(42, $action['postId'], 'No update_page may target a skipped page.');
            }
        }
    }

    public function test_suffix_lets_wordpress_uniquify_the_slug_rather_than_overwriting()
    {
        $this->givenPost(42, ['post_name' => 'about', 'post_status' => 'publish']);

        $plan                           = $this->twoPagePlan();
        $plan['pages'][1]['onConflict'] = 'suffix';

        $report = $this->runPlan($plan, ['about' => 42]);

        $this->assertNotSame(42, $report['pages'][1]['postId']);
        $this->assertSame('about-2', $GLOBALS['sfcore_test_wp']['posts'][$report['pages'][1]['postId']]['post_name']);
    }

    public function test_a_style_variation_is_applied_after_the_pages_exist()
    {
        $this->givenPost(23, ['post_type' => 'wp_global_styles', 'post_content' => '{}']);

        $plan                           = $this->twoPagePlan();
        $plan['site']['styleVariation'] = 'Swiss International';

        $report = $this->runPlan($plan);
        $types  = [];

        foreach ($report['actions'] as $action) {
            $types[] = $action['type'];
        }

        $this->assertSame('set_style_variation', end($types));
        $this->assertStringContainsString('isGlobalStylesUserThemeJSON', $GLOBALS['sfcore_test_wp']['posts'][23]['post_content']);
    }

    public function test_a_run_records_what_it_created_so_it_can_be_undone()
    {
        $report = $this->runPlan($this->twoPagePlan());
        $stored = get_transient('sfcore_ai_gen_run_7');

        $this->assertSame($report['createdIds'], $stored['ids']);
    }

    public function test_a_failure_part_way_through_still_records_what_was_created()
    {
        $GLOBALS['sfcore_test_wp']['caps'] = ['edit_pages', 'edit_page', 'edit_post'];

        try {
            $this->runPlan($this->twoPagePlan());
            $this->fail('Expected ExecutionException');
        } catch (ExecutionException $e) {
            $this->assertSame('set_navigation', $e->getActionType());
            $this->assertCount(2, $e->getCreatedIds());
        }

        $stored = get_transient('sfcore_ai_gen_run_7');

        $this->assertCount(2, $stored['ids']);
    }

    public function test_a_page_scoped_plan_needs_no_site_actions()
    {
        $plan = $this->plan();
        unset($plan['site']);

        $validated = PlanValidator::withDefaultStages()->validate($plan, $this->context('page'));
        $report    = $this->executor()->execute($validated, 7, $this->approvedContext());

        $this->assertCount(1, $report['createdIds']);
        $this->assertCount(1, $report['actions']);
        $this->assertSame('create_page', $report['actions'][0]['type']);
    }
}
