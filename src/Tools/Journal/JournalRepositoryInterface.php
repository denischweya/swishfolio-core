<?php
/**
 * Storage for the AI journal.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools\Journal;

/**
 * Append-only by construction: there is no update() and no delete().
 *
 * markReverted() is the one mutation, and it only ever sets the two revert
 * columns on a row — it cannot alter what the row says happened. pruneBefore()
 * is bulk expiry of aged rows, not editing of individual records; an unbounded
 * table on shared hosting is a real failure mode, so the alternative is worse.
 *
 * The interface exists because the unit suite runs against stubbed WP functions
 * rather than a WordPress test harness, so $wpdb cannot be stubbed meaningfully.
 * Tests use an in-memory implementation. Same reasoning as CredentialStoreInterface.
 */
interface JournalRepositoryInterface
{
    /**
     * @param JournalEntry $entry Row to append.
     *
     * @return int Inserted row ID, 0 on failure.
     */
    public function append(JournalEntry $entry);

    /**
     * @param int    $id    Row ID.
     * @param string $error Revert failure message, '' when the revert succeeded.
     *
     * @return void
     */
    public function markReverted($id, $error = '');

    /**
     * @param string $runId Run identifier.
     *
     * @return JournalEntry[] Ordered by seq ascending.
     */
    public function findRun($runId);

    /**
     * @param int $limit  Maximum runs.
     * @param int $offset Runs to skip.
     * @param int $userId Only runs by this user; 0 for every user.
     *
     * @return array[] One row per run: run_id, started_at, calls, tools, user_id, reverted.
     */
    public function recentRuns($limit = 20, $offset = 0, $userId = 0);

    /**
     * @param string $before MySQL datetime; rows created before it are removed.
     *
     * @return int Rows removed.
     */
    public function pruneBefore($before);
}
