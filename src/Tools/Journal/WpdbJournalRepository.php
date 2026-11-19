<?php
/**
 * Journal storage on $wpdb.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools\Journal;

/**
 * A custom table rather than post meta or an option, for three reasons: rows
 * need ordering and indexed lookup by run, an option holding an append-only log
 * grows until it breaks autoload, and "append-only" has to be enforceable at the
 * storage boundary rather than by convention.
 */
class WpdbJournalRepository implements JournalRepositoryInterface
{
    /**
     * @param JournalEntry $entry Row to append.
     *
     * @return int
     */
    public function append(JournalEntry $entry)
    {
        global $wpdb;

        $row = $entry->toRow();

        if ('' === (string) $row['created_at']) {
            $row['created_at'] = current_time('mysql', true);
        }

        $inserted = $wpdb->insert(JournalTable::name(), $row);

        return $inserted ? (int) $wpdb->insert_id : 0;
    }

    /**
     * @param int    $id    Row ID.
     * @param string $error Revert failure message.
     *
     * @return void
     */
    public function markReverted($id, $error = '')
    {
        global $wpdb;

        $wpdb->update(
            JournalTable::name(),
            [
                'reverted_at'  => current_time('mysql', true),
                'revert_error' => '' === (string) $error ? null : (string) $error,
            ],
            ['id' => (int) $id]
        );
    }

    /**
     * @param string $runId Run identifier.
     *
     * @return JournalEntry[]
     */
    public function findRun($runId)
    {
        global $wpdb;

        $table = JournalTable::name();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is prefix-derived.
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} WHERE run_id = %s ORDER BY seq ASC", (string) $runId),
            ARRAY_A
        );

        return array_map(
            static function ($row) {
                return new JournalEntry($row);
            },
            is_array($rows) ? $rows : []
        );
    }

    /**
     * @param int $limit  Maximum runs.
     * @param int $offset Runs to skip.
     * @param int $userId Only runs by this user; 0 for every user.
     *
     * @return array[]
     */
    public function recentRuns($limit = 20, $offset = 0, $userId = 0)
    {
        global $wpdb;

        $table = JournalTable::name();
        $where = (int) $userId > 0 ? $wpdb->prepare('WHERE user_id = %d', (int) $userId) : '';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is prefix-derived.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT run_id,
                        MIN(created_at) AS started_at,
                        MAX(user_id) AS user_id,
                        SUM(CASE WHEN target_type = 'call' THEN 1 ELSE 0 END) AS calls,
                        SUM(CASE WHEN target_type <> 'call' THEN 1 ELSE 0 END) AS targets,
                        SUM(CASE WHEN target_type <> 'call' AND reverted_at IS NOT NULL THEN 1 ELSE 0 END) AS reverted,
                        SUM(CASE WHEN result = 'error' THEN 1 ELSE 0 END) AS errors
                   FROM {$table}
                  {$where}
                  GROUP BY run_id
                  ORDER BY started_at DESC
                  LIMIT %d OFFSET %d",
                (int) $limit,
                (int) $offset
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param string $before MySQL datetime.
     *
     * @return int
     */
    public function pruneBefore($before)
    {
        global $wpdb;

        $table = JournalTable::name();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is prefix-derived.
        $deleted = $wpdb->query(
            $wpdb->prepare("DELETE FROM {$table} WHERE created_at < %s", (string) $before)
        );

        return (int) $deleted;
    }
}
