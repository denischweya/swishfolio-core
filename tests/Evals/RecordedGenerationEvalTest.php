<?php

namespace SwishfolioCore\Tests\Evals;

use SwishfolioCore\Actions\ActionExecutor;
use SwishfolioCore\AI\Generation\BlockComposer;
use SwishfolioCore\AI\Generation\PlanExecutor;
use SwishfolioCore\AI\Generation\PlanSchema;
use SwishfolioCore\Tests\Evals\Support\BlockMarkup;
use SwishfolioCore\Tests\Evals\Support\Corpus;
use SwishfolioCore\Tests\Evals\Support\EvalTestCase;
use SwishfolioCore\Tests\Evals\Support\RecordedProvider;
use SwishfolioCore\Tests\Support\InMemoryJournalRepository;
use SwishfolioCore\Tools\Journal\MutationJournal;
use SwishfolioCore\Tools\ToolContext;
use SwishfolioCore\Tools\ToolRegistry;
use SwishfolioCore\Tools\ToolRunner;
use SwishfolioCore\Tools\Write\CreateContentTool;
use SwishfolioCore\Tools\Write\SetFrontPageTool;
use SwishfolioCore\Tools\Write\SetNavigationTool;
use SwishfolioCore\Tools\Write\UpdateContentTool;

/**
 * The generation half of the evaluation suite, in recorded mode.
 *
 * Generation is the flow where PHP does write, so this goes one step further than
 * the editing evals: it validates the plan, composes the markup, and then runs the
 * plan through the real executor against a fake WordPress, because "the actions
 * were valid" and "what landed in the database is what was composed" are
 * different claims and only the second one is what a user sees.
 *
 * The strongest assertion here is the parse and re-serialise of composed content.
 * Composition is a string substitution into shipped markup, and a slot value that
 * breaks an attribute's JSON or leaves a delimiter unbalanced produces a page that
 * looks correct in the file and is a validation error in the editor.
 */
class RecordedGenerationEvalTest extends EvalTestCase
{
    const EVAL_USER_ID = 7;

    /**
     * @return array
     */
    public function corpus()
    {
        return Corpus::planCases();
    }

    /**
     * @dataProvider corpus
     *
     * @param array $case Corpus case.
     */
    public function test_every_unit_block_and_value_the_model_chose_is_available($case)
    {
        $this->assertNoViolations(
            $this->planAudit($case)->auditProposal((array) $case['response']),
            $case
        );
    }

    /**
     * @dataProvider corpus
     *
     * @param array $case Corpus case.
     */
    public function test_the_whole_plan_pipeline_accepts_the_plan($case)
    {
        $result = $this->runPlan($case, new RecordedProvider((array) $case['response']));

        $this->assertCount(
            count((array) $case['response']['pages']),
            $result['validated']['pages'],
            'Validation must not silently drop pages.'
        );

        foreach ($result['validated']['pages'] as $index => $page) {
            $this->assertSame(
                PlanSchema::STATUS,
                $page['status'],
                sprintf('pages.%d: a generated page is always a draft.', $index)
            );
            $this->assertNotSame('', $page['slug'], sprintf('pages.%d: slug is empty after sanitising.', $index));
        }
    }

    /**
     * The catalogue is the only thing the planner has to choose from, and it is
     * built from the shipped units rather than written into the prompt by hand.
     * A unit that stops being described is a unit the model can never pick.
     *
     * @dataProvider corpus
     *
     * @param array $case Corpus case.
     */
    public function test_the_prompt_offers_every_unit_the_plan_actually_uses($case)
    {
        $result = $this->runPlan($case, new RecordedProvider((array) $case['response']));
        $prompt = $result['request']->getSystemPrompt();

        $this->assertStringContainsString((string) $case['instruction'], $result['request']->getUserPrompt());

        foreach ((array) $case['response']['pages'] as $page) {
            foreach ((array) $page['sections'] as $section) {
                $named  = isset($section['pattern']) ? (string) $section['pattern'] : (string) $section['block'];
                $needle = isset($section['pattern']) ? '## ' . $named . ' |' : '"' . $named . '"';

                $this->assertStringContainsString(
                    $needle,
                    $prompt,
                    sprintf('The plan uses "%s" but the catalogue in the prompt never offered it.', $named)
                );
            }
        }

        foreach ($this->tokens()->getSlugs('color') as $slug) {
            $this->assertStringContainsString(
                '"' . $slug . '"',
                $prompt,
                sprintf('Colour token "%s" is in theme.json but absent from the prompt.', $slug)
            );
        }
    }

    /**
     * @dataProvider corpus
     *
     * @param array $case Corpus case.
     */
    public function test_design_tokens_are_expanded_to_the_value_a_block_stores($case)
    {
        $result = $this->runPlan($case, new RecordedProvider((array) $case['response']));

        $this->assertNoViolations(
            $this->planAudit($case)->auditTokenExpansion((array) $case['response'], $result['validated']),
            $case
        );
    }

    /**
     * @dataProvider corpus
     *
     * @param array $case Corpus case.
     */
    public function test_every_composed_page_is_parseable_block_markup($case)
    {
        $result   = $this->runPlan($case, new RecordedProvider((array) $case['response']));
        $composed = $this->compose($result['validated']);

        $this->assertNoViolations(
            $this->planAudit($case)->auditComposedContent($result['validated'], $composed),
            $case
        );
    }

    /**
     * @dataProvider corpus
     *
     * @param array $case Corpus case.
     */
    public function test_executing_the_plan_writes_only_drafts_owned_by_the_requesting_user($case)
    {
        $result = $this->runPlan($case, new RecordedProvider((array) $case['response']));

        $this->seedExistingPages($case);

        $report = $this->executor($case)->execute($result['validated'], self::EVAL_USER_ID, $this->approvedContext());
        $posts  = $GLOBALS['sfcore_test_wp']['posts'];

        $this->assertNotSame([], $report['createdIds'], 'The plan wrote nothing at all.');

        foreach ($report['createdIds'] as $postId) {
            $post = $posts[$postId];

            $this->assertSame(
                self::EVAL_USER_ID,
                (int) $post['post_author'],
                sprintf('Post %d was not attributed to the requesting user.', $postId)
            );

            if ($post['post_type'] === 'wp_navigation') {
                continue;
            }

            $this->assertSame('page', $post['post_type'], sprintf('Post %d is not a page.', $postId));
            $this->assertSame(
                PlanSchema::STATUS,
                $post['post_status'],
                sprintf('Post %d was written as "%s" rather than a draft.', $postId, $post['post_status'])
            );
        }
    }

    /**
     * @dataProvider corpus
     *
     * @param array $case Corpus case.
     */
    public function test_the_markup_stored_by_execution_still_parses_and_resolves_every_link($case)
    {
        $result = $this->runPlan($case, new RecordedProvider((array) $case['response']));

        $this->seedExistingPages($case);

        $report = $this->executor($case)->execute($result['validated'], self::EVAL_USER_ID, $this->approvedContext());
        $posts  = $GLOBALS['sfcore_test_wp']['posts'];

        foreach ($report['pages'] as $page) {
            if ($page['postId'] === null || $page['reused']) {
                continue;
            }

            $content = (string) $posts[$page['postId']]['post_content'];

            $this->assertDoesNotMatchRegularExpression(
                '/\{\{(kit-[a-z-]+|link|nav)[:}]/',
                $content,
                sprintf('Page "%s" was stored with an unresolved kit placeholder.', $page['key'])
            );
            $this->assertStringNotContainsString(
                '{{page:',
                $content,
                sprintf('Page "%s" was stored with an unresolved internal link.', $page['key'])
            );

            $trip = BlockMarkup::roundTrip($content);

            $this->assertTrue(
                $trip['identical'],
                sprintf(
                    'Page "%s" does not survive a parse and re-serialise; first byte differs at offset %s.',
                    $page['key'],
                    var_export($trip['diffAt'], true)
                )
            );
        }
    }

    /**
     * A plan that chose "skip" said it wanted the existing page left alone. Being
     * reused is not the same as being rewritten, and the difference is somebody's
     * published page.
     *
     * @dataProvider corpus
     *
     * @param array $case Corpus case.
     */
    public function test_a_page_the_plan_chose_to_skip_is_left_exactly_as_it_was($case)
    {
        $result   = $this->runPlan($case, new RecordedProvider((array) $case['response']));
        $reused   = [];

        foreach ($result['validated']['pages'] as $page) {
            if (! empty($page['existingPostId'])) {
                $reused[(int) $page['existingPostId']] = true;
            }
        }

        if ($reused === []) {
            $this->assertTrue(true, 'This case reuses no existing page.');

            return;
        }

        $this->seedExistingPages($case);

        $before = $GLOBALS['sfcore_test_wp']['posts'];

        $this->executor($case)->execute($result['validated'], self::EVAL_USER_ID, $this->approvedContext());

        foreach (array_keys($reused) as $postId) {
            $this->assertSame(
                $before[$postId],
                $GLOBALS['sfcore_test_wp']['posts'][$postId],
                sprintf('Post %d was chosen to be skipped but execution changed it.', $postId)
            );
        }
    }

    /**
     * The style variation tool reads the theme's variation list and the user
     * global styles post, neither of which exists without WordPress, so both are
     * injected — exactly as the plan executor's own unit test does.
     *
     * @return PlanExecutor
     */
    private function executor(array $case)
    {
        $journal = new MutationJournal(new InMemoryJournalRepository());

        return new PlanExecutor(
            new BlockComposer($this->kitFor($case)),
            new ActionExecutor(new ToolRunner(new ToolRegistry([
                new CreateContentTool($journal),
                new UpdateContentTool($journal),
                new SetFrontPageTool($journal),
                new SetNavigationTool($journal),
            ]), $journal))
        );
    }

    /**
     * A generation run carries the owner's approval of the plan, which the three
     * site-level tools require.
     *
     * @return ToolContext
     */
    private function approvedContext()
    {
        $context = ToolContext::forUser(self::EVAL_USER_ID);

        $context->setConfirmed(true);

        return $context;
    }

    /**
     * @param array $case Corpus case.
     *
     * @return void
     */
    private function seedExistingPages($case)
    {
        $brief = isset($case['brief']) ? (array) $case['brief'] : [];
        $slugs = isset($brief['existingSlugs']) ? (array) $brief['existingSlugs'] : [];

        foreach ($slugs as $slug => $postId) {
            $GLOBALS['sfcore_test_wp']['posts'][(int) $postId] = [
                'ID'           => (int) $postId,
                'post_type'    => 'page',
                'post_status'  => 'publish',
                'post_title'   => ucfirst((string) $slug),
                'post_name'    => (string) $slug,
                'post_content' => "<!-- wp:paragraph -->\n<p>Content the site owner already wrote.</p>\n<!-- /wp:paragraph -->",
                'post_author'  => 1,
            ];
        }

        // The global styles post the style-variation handler writes to.
        $GLOBALS['sfcore_test_wp']['posts'][23] = [
            'ID'           => 23,
            'post_type'    => 'wp_global_styles',
            'post_status'  => 'publish',
            'post_title'   => 'Custom Styles',
            'post_name'    => 'wp-global-styles-swishfolio',
            'post_content' => '{"version":3,"isGlobalStylesUserThemeJSON":true}',
            'post_author'  => 1,
        ];
    }
}
