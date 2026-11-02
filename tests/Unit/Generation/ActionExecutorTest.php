<?php

namespace SwishfolioCore\Tests\Unit\Generation;

require_once __DIR__ . '/../Tools/wp-tools-stubs.php';

use SwishfolioCore\Actions\ActionExecutor;
use SwishfolioCore\Actions\Handlers\ExecutionContext;
use SwishfolioCore\Actions\Handlers\ExecutionException;
use SwishfolioCore\Tests\Support\InMemoryJournalRepository;
use SwishfolioCore\Tools\Journal\MutationJournal;
use SwishfolioCore\Tools\ToolContext;
use SwishfolioCore\Tools\ToolRegistry;
use SwishfolioCore\Tools\ToolRunner;
use SwishfolioCore\Tools\Write\CreatePageTool;
use SwishfolioCore\Tools\Write\SetFrontPageTool;
use SwishfolioCore\Tools\Write\SetNavigationTool;
use SwishfolioCore\Tools\Write\SetStyleVariationTool;
use SwishfolioCore\Tools\Write\UpdatePageTool;

/**
 * ActionExecutor is now a façade over ToolRunner, so what is tested here is the
 * translation it owns: plan action types to tool names, plan payloads to tool
 * input, and ToolException back to ExecutionException so PlanExecutor and
 * GenerationController keep the error shape they already handle.
 *
 * The pipeline tests that used to be here — authorize before execute, a refusal
 * stopping the run, results in order — moved to ToolRunnerTest with the
 * pipeline itself.
 */
class ActionExecutorTest extends GenerationTestCase
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

        return new ActionExecutor(
            new ToolRunner(
                new ToolRegistry(
                    [
                        new CreatePageTool($journal),
                        new UpdatePageTool($journal),
                        new SetFrontPageTool($journal),
                        new SetNavigationTool($journal),
                        new SetStyleVariationTool($journal),
                    ]
                ),
                $journal
            )
        );
    }

    private function toolContext($confirmed = true)
    {
        $context = new ToolContext(7, 1, 'run0000000000000000000000000002');

        $context->setConfirmed($confirmed);

        return $context;
    }

    public function test_it_registers_exactly_the_five_server_side_action_types()
    {
        $types = $this->executor()->types();

        sort($types);

        $this->assertSame(
            ['create_page', 'set_front_page', 'set_navigation', 'set_style_variation', 'update_page'],
            $types
        );
    }

    public function test_it_reports_which_action_types_it_supports()
    {
        $executor = $this->executor();

        $this->assertTrue($executor->supports('create_page'));
        $this->assertFalse($executor->supports('update_template'));
    }

    public function test_an_unknown_action_type_is_refused()
    {
        try {
            $this->executor()->execute([['type' => 'update_template']], $this->toolContext());
            $this->fail('Expected ExecutionException');
        } catch (ExecutionException $e) {
            $this->assertSame('unknown_action', $e->getReason());
            $this->assertSame('update_template', $e->getActionType());
        }
    }

    public function test_a_run_without_a_tool_context_cannot_be_journalled_so_it_is_refused()
    {
        $this->expectException(\LogicException::class);

        $this->executor()->execute([['type' => 'create_page']], new ExecutionContext(7));
    }

    public function test_a_null_optional_key_is_treated_as_absent_rather_than_as_invalid()
    {
        // PlanExecutor emits 'template' => null for every page without one, and
        // tool schemas are closed with template typed as a string. Without the
        // null-stripping in the façade, every untemplated page would fail
        // validation instead of being created.
        $context = $this->toolContext();

        $this->executor()->execute(
            [
                [
                    'type'     => 'create_page',
                    'key'      => 'home',
                    'title'    => 'Home',
                    'slug'     => 'home',
                    'template' => null,
                    'content'  => 'body',
                ],
            ],
            $context
        );

        $this->assertCount(1, $context->getCreatedIds());
    }

    public function test_a_tool_failure_is_reported_as_an_execution_exception_with_what_was_written()
    {
        $context = $this->toolContext();

        $this->executor()->execute(
            [
                ['type' => 'create_page', 'key' => 'home', 'title' => 'Home', 'slug' => 'home', 'content' => 'a'],
            ],
            $context
        );

        $created = $context->getCreatedIds();

        try {
            $this->executor()->execute([['type' => 'update_page', 'postId' => 999999, 'content' => 'x']], $context);
            $this->fail('Expected ExecutionException');
        } catch (ExecutionException $e) {
            $this->assertSame('update_page', $e->getActionType());
            $this->assertSame('target_missing', $e->getReason());
            $this->assertSame($created, $e->getCreatedIds());
        }
    }

    public function test_results_are_collected_in_execution_order()
    {
        $context = $this->executor()->execute(
            [
                ['type' => 'create_page', 'key' => 'a', 'title' => 'A', 'slug' => 'a', 'content' => 'a'],
                ['type' => 'create_page', 'key' => 'b', 'title' => 'B', 'slug' => 'b', 'content' => 'b'],
            ],
            $this->toolContext()
        );

        $this->assertCount(2, $context->getResults());
        $this->assertSame('create_page', $context->getResults()[0]['type']);
        $this->assertSame('a', $context->getResults()[0]['key']);
        $this->assertSame('b', $context->getResults()[1]['key']);
    }

    public function test_every_write_a_run_makes_is_journalled_under_one_run_id()
    {
        $this->executor()->execute(
            [
                ['type' => 'create_page', 'key' => 'a', 'title' => 'A', 'slug' => 'a', 'content' => 'a'],
                ['type' => 'create_page', 'key' => 'b', 'title' => 'B', 'slug' => 'b', 'content' => 'b'],
            ],
            $this->toolContext()
        );

        $this->assertCount(4, $this->repository->findRun('run0000000000000000000000000002'));
    }
}
