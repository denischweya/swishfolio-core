<?php

namespace SwishfolioCore\Tests\Support;

use SwishfolioCore\Tools\Journal\JournalEntry;
use SwishfolioCore\Tools\Journal\JournalRepositoryInterface;

/**
 * The journal in an array.
 *
 * The unit suite runs against stubbed WordPress functions rather than a real
 * WordPress test harness, so $wpdb is not available to stub meaningfully. This
 * is the reason JournalRepositoryInterface exists.
 */
class InMemoryJournalRepository implements JournalRepositoryInterface
{
    /** @var JournalEntry[] Keyed by id. */
    public $rows = [];

    /** @var int */
    private $nextId = 1;

    /** @var bool When true, append() reports failure. */
    public $failAppend = false;

    public function append(JournalEntry $entry)
    {
        if ($this->failAppend) {
            return 0;
        }

        $id = $this->nextId++;

        $this->rows[$id] = $entry->with(['id' => $id]);

        return $id;
    }

    public function markReverted($id, $error = '')
    {
        if (! isset($this->rows[(int) $id])) {
            return;
        }

        $this->rows[(int) $id] = $this->rows[(int) $id]->with(
            [
                'reverted_at'  => '2026-11-02 12:00:00',
                'revert_error' => '' === (string) $error ? null : (string) $error,
            ]
        );
    }

    public function findRun($runId)
    {
        $found = [];

        foreach ($this->rows as $row) {
            if ($row->getRunId() === (string) $runId) {
                $found[] = $row;
            }
        }

        usort(
            $found,
            static function (JournalEntry $a, JournalEntry $b) {
                return $a->getSeq() - $b->getSeq();
            }
        );

        return $found;
    }

    public function recentRuns($limit = 20, $offset = 0)
    {
        $runs = [];

        foreach ($this->rows as $row) {
            $runs[$row->getRunId()] = ($runs[$row->getRunId()] ?? 0) + 1;
        }

        $out = [];

        foreach ($runs as $runId => $count) {
            $out[] = ['run_id' => $runId, 'calls' => $count];
        }

        return array_slice($out, (int) $offset, (int) $limit);
    }

    public function pruneBefore($before)
    {
        $removed = 0;

        foreach ($this->rows as $id => $row) {
            if ((string) $row->get('created_at') < (string) $before) {
                unset($this->rows[$id]);
                ++$removed;
            }
        }

        return $removed;
    }

    /**
     * @param string $targetType JournalEntry::TARGET_* constant.
     *
     * @return JournalEntry[]
     */
    public function ofType($targetType)
    {
        $found = [];

        foreach ($this->rows as $row) {
            if ($row->getTargetType() === $targetType) {
                $found[] = $row;
            }
        }

        return $found;
    }
}
