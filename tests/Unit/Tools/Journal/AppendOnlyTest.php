<?php

namespace SwishfolioCore\Tests\Unit\Tools\Journal;

use SwishfolioCore\Tests\Unit\Tools\ToolsTestCase;
use SwishfolioCore\Tools\Journal\JournalRepositoryInterface;
use SwishfolioCore\Tools\Journal\WpdbJournalRepository;

/**
 * The audit log's one real guarantee is that nothing can edit what a row says
 * happened. That is enforced by the repository having no method that could, so
 * the test is a check on the interface's shape rather than on behaviour: a
 * future update() would pass every behavioural test ever written and quietly
 * remove the guarantee.
 */
class AppendOnlyTest extends ToolsTestCase
{
    public function test_the_repository_exposes_no_way_to_alter_a_recorded_row()
    {
        $methods = get_class_methods(JournalRepositoryInterface::class);

        sort($methods);

        $this->assertSame(
            ['append', 'findRun', 'markReverted', 'pruneBefore', 'recentRuns'],
            $methods,
            'A new method here needs justifying: append-only is enforced by this list.'
        );
    }

    public function test_mark_reverted_touches_only_the_revert_columns()
    {
        $context = $this->context();

        $this->journal->updateOption($context, 'test/tool', 'sfcore_thing', 'new', 'Set the thing');

        // Writing through the journal directly produces the target row alone;
        // the call row is the runner's doing.
        $target = $this->repository->findRun($context->getRunId())[0];
        $before = $target->toRow();

        $this->repository->markReverted($target->getId(), 'it broke');

        $after = $this->repository->findRun($context->getRunId())[0]->toRow();

        foreach ($before as $column => $value) {
            if (in_array($column, ['reverted_at', 'revert_error'], true)) {
                continue;
            }

            $this->assertSame($value, $after[$column], sprintf('Column "%s" must not change.', $column));
        }

        $this->assertSame('it broke', $after['revert_error']);
    }

    public function test_the_live_repository_implements_the_interface_it_claims()
    {
        $this->assertInstanceOf(JournalRepositoryInterface::class, new WpdbJournalRepository());
    }
}
