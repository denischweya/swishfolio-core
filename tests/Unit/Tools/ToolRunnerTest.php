<?php

namespace SwishfolioCore\Tests\Unit\Tools;

use SwishfolioCore\Tools\Journal\JournalEntry;
use SwishfolioCore\Tools\ToolContext;
use SwishfolioCore\Tools\ToolError;
use SwishfolioCore\Tools\ToolException;
use SwishfolioCore\Tools\ToolInterface;

/**
 * The pipeline tests, including the three that used to live on ActionExecutor —
 * authorize-before-execute, a refusal stopping the run, and results in order.
 * The pipeline moved here, so its tests did.
 */
class ToolRunnerTest extends ToolRunnerTestFixtures
{
    public function test_an_unknown_tool_is_refused_and_lists_what_exists()
    {
        try {
            $this->runner([new SpyTool()])->run('swishfolio/nope', [], $this->context());
            $this->fail('Expected ToolException');
        } catch (ToolException $e) {
            $this->assertSame(ToolError::TOOL_NOT_FOUND, $e->getError()->getCode());
            $this->assertSame(['test/spy'], $e->getError()->getDetails()['available']);
        }
    }

    public function test_every_tool_is_authorized_before_it_runs()
    {
        $spy = new SpyTool();

        $this->runner([$spy])->run('test/spy', [], $this->context());

        $this->assertSame(['authorize', 'execute'], $spy->calls);
    }

    public function test_a_refused_tool_never_reaches_execute()
    {
        try {
            $this->runner([new RefusingTool()])->run('test/refuse', [], $this->context());
            $this->fail('Expected ToolException');
        } catch (ToolException $e) {
            $this->assertSame(ToolError::FORBIDDEN, $e->getError()->getCode());
        }
    }

    public function test_input_is_validated_before_authorization()
    {
        $spy = new SpyTool();

        try {
            $this->runner([$spy])->run('test/spy', ['unknown' => 1], $this->context());
            $this->fail('Expected ToolException');
        } catch (ToolException $e) {
            $this->assertSame(ToolError::SCHEMA_INVALID, $e->getError()->getCode());
            $this->assertSame('unknown', $e->getError()->getDetails()['field']);
            $this->assertSame([], $spy->calls, 'Authorization must not run on invalid input.');
        }
    }

    public function test_a_published_write_without_confirmation_is_refused_and_writes_nothing()
    {
        $tool = new PublishedWriteTool($this->journal);

        try {
            $this->runner([$tool])->run('test/published', [], $this->context());
            $this->fail('Expected ToolException');
        } catch (ToolException $e) {
            $this->assertSame(ToolError::CONFIRM_REQUIRED, $e->getError()->getCode());
        }

        $this->assertSame([], $this->repository->rows, 'A refused call must leave no journal rows.');
        $this->assertSame([], $GLOBALS['sfcore_test_options']);
    }

    public function test_a_published_write_proceeds_once_confirmed()
    {
        $this->runner([new PublishedWriteTool($this->journal)])
            ->run('test/published', [], $this->context(true));

        $this->assertSame('yes', $GLOBALS['sfcore_test_options']['sfcore_test_flag']);
    }

    public function test_a_read_tool_needs_no_confirmation_and_is_not_journalled()
    {
        $result = $this->runner([new SpyTool()])->run('test/spy', [], $this->context());

        $this->assertSame(['ok' => true], $result);
        $this->assertSame([], $this->repository->rows, 'Reads are never journalled.');
    }

    public function test_a_successful_mutation_is_journalled_as_one_call_row()
    {
        $this->runner([new PublishedWriteTool($this->journal)])
            ->run('test/published', [], $this->context(true));

        $calls = $this->repository->ofType(JournalEntry::TARGET_CALL);

        $this->assertCount(1, $calls);
        $this->assertSame('test/published', $calls[0]->getTool());
        $this->assertSame(JournalEntry::RESULT_OK, $calls[0]->getResult());
    }

    public function test_the_call_row_sorts_ahead_of_the_targets_it_wrote()
    {
        $this->runner([new PublishedWriteTool($this->journal)])
            ->run('test/published', [], $this->context(true));

        $rows = $this->repository->findRun('run0000000000000000000000000001');

        $this->assertSame(JournalEntry::TARGET_CALL, $rows[0]->getTargetType());
        $this->assertSame(JournalEntry::TARGET_OPTION, $rows[1]->getTargetType());
    }

    public function test_a_failed_mutation_is_journalled_with_its_error_code()
    {
        try {
            $this->runner([new FailingWriteTool($this->journal)])
                ->run('test/failing', [], $this->context(true));
            $this->fail('Expected ToolException');
        } catch (ToolException $e) {
            $this->assertSame(ToolError::WRITE_FAILED, $e->getError()->getCode());
        }

        $calls = $this->repository->ofType(JournalEntry::TARGET_CALL);

        $this->assertCount(1, $calls);
        $this->assertSame(JournalEntry::RESULT_ERROR, $calls[0]->getResult());
        $this->assertSame(ToolError::WRITE_FAILED, $calls[0]->getErrorCode());
    }

    public function test_a_plan_runs_its_calls_in_order()
    {
        $spy = new SpyTool();

        $results = $this->runner([$spy])->runPlan(
            [
                ['name' => 'test/spy', 'input' => []],
                ['name' => 'test/spy', 'input' => []],
            ],
            $this->context()
        );

        $this->assertCount(2, $results);
        $this->assertSame('test/spy', $results[0]['name']);
        $this->assertSame(['authorize', 'execute', 'authorize', 'execute'], $spy->calls);
    }

    public function test_a_valid_token_confirms_the_plan_it_was_issued_for()
    {
        $calls  = [['name' => 'test/published', 'input' => []]];
        $runner = $this->runner([new PublishedWriteTool($this->journal)]);

        $token = $runner->preview($calls, $this->context())['confirmToken'];

        $runner->runPlan($calls, $this->context(), $token);

        $this->assertSame('yes', $GLOBALS['sfcore_test_options']['sfcore_test_flag']);
    }

    public function test_a_token_issued_for_one_plan_does_not_approve_another()
    {
        $runner = $this->runner([new PublishedWriteTool($this->journal)]);

        $token = $runner->preview([['name' => 'test/published', 'input' => ['note' => 'a']]], $this->context())['confirmToken'];

        $this->expectException(ToolException::class);

        $runner->runPlan([['name' => 'test/published', 'input' => ['note' => 'b']]], $this->context(), $token);
    }

    public function test_a_preview_describes_each_call_and_flags_the_ones_needing_confirmation()
    {
        $preview = $this->runner([new SpyTool(), new PublishedWriteTool($this->journal)])->preview(
            [
                ['name' => 'test/spy', 'input' => []],
                ['name' => 'test/published', 'input' => []],
            ],
            $this->context()
        );

        $this->assertTrue($preview['requiresConfirmation']);
        $this->assertFalse($preview['previews'][0]['requiresConfirmation']);
        $this->assertTrue($preview['previews'][1]['requiresConfirmation']);
    }

    public function test_a_preview_refuses_a_call_the_gate_would_refuse_anyway()
    {
        $this->expectException(ToolException::class);

        $this->runner([new RefusingTool()])->preview(
            [['name' => 'test/refuse', 'input' => []]],
            $this->context()
        );
    }
}
