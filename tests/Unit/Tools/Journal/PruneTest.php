<?php

namespace SwishfolioCore\Tests\Unit\Tools\Journal;

use SwishfolioCore\Admin\AuditLogPage;
use SwishfolioCore\Tests\Unit\Tools\ToolsTestCase;
use SwishfolioCore\Tools\Journal\JournalEntry;
use SwishfolioCore\Tools\ToolRegistry;
use SwishfolioCore\Tools\ToolRunner;

/**
 * Pruning is the one place rows are removed, and "append-only log" and "rows get
 * deleted on a schedule" only coexist because the deletion is time-based bulk
 * expiry rather than editing of records. That makes the retention boundary worth
 * a test: an off-by-one here silently destroys the ability to undo.
 */
class PruneTest extends ToolsTestCase
{
    private function page()
    {
        if (! defined('DAY_IN_SECONDS')) {
            define('DAY_IN_SECONDS', 86400);
        }

        $registry = ToolRegistry::withDefaultTools($this->journal, $this->blocks());

        return new AuditLogPage($this->repository, new ToolRunner($registry, $this->journal));
    }

    /**
     * @param string $createdAt MySQL datetime.
     *
     * @return void
     */
    private function givenRowAt($createdAt)
    {
        $this->repository->append(
            new JournalEntry(
                [
                    'run_id'      => 'run' . md5($createdAt),
                    'seq'         => 1,
                    'tool'        => 'test/tool',
                    'target_type' => JournalEntry::TARGET_OPTION,
                    'target_id'   => 'sfcore_thing',
                    'created_at'  => $createdAt,
                ]
            )
        );
    }

    public function test_rows_past_the_retention_window_are_expired()
    {
        $this->givenRowAt(gmdate('Y-m-d H:i:s', time() - (200 * 86400)));
        $this->givenRowAt(gmdate('Y-m-d H:i:s', time() - (91 * 86400)));

        $this->page()->prune();

        $this->assertSame([], $this->repository->rows);
    }

    public function test_rows_inside_the_window_are_kept()
    {
        $this->givenRowAt(gmdate('Y-m-d H:i:s', time() - (89 * 86400)));
        $this->givenRowAt(gmdate('Y-m-d H:i:s', time()));

        $this->page()->prune();

        $this->assertCount(2, $this->repository->rows);
    }

    public function test_a_retention_of_zero_keeps_everything_forever()
    {
        $this->givenRowAt(gmdate('Y-m-d H:i:s', time() - (3650 * 86400)));

        $GLOBALS['sfcore_test_filters']['swishfolio_ai_journal_retention_days'] = static function () {
            return 0;
        };

        $this->page()->prune();

        unset($GLOBALS['sfcore_test_filters']['swishfolio_ai_journal_retention_days']);

        $this->assertCount(1, $this->repository->rows, 'A retention of 0 must mean "never prune", not "prune all".');
    }

    public function test_the_retention_window_is_filterable()
    {
        $this->givenRowAt(gmdate('Y-m-d H:i:s', time() - (10 * 86400)));

        $GLOBALS['sfcore_test_filters']['swishfolio_ai_journal_retention_days'] = static function () {
            return 7;
        };

        $this->page()->prune();

        unset($GLOBALS['sfcore_test_filters']['swishfolio_ai_journal_retention_days']);

        $this->assertSame([], $this->repository->rows);
    }
}
