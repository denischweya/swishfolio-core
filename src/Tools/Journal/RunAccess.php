<?php
/**
 * Who may see which AI runs.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools\Journal;

/**
 * Site managers see every run on their site; everyone else (authors,
 * contributors, editors) sees only the runs they made themselves. A run's
 * summaries quote AI copy, search terms and post titles, so they are as
 * private as the prompt that produced them.
 */
final class RunAccess
{
    /**
     * @return int 0 for "all runs", otherwise the only user id whose runs are visible.
     */
    public static function scopeUserId()
    {
        return current_user_can('manage_options') ? 0 : (int) get_current_user_id();
    }

    /**
     * @param JournalEntry[] $entries One run's entries.
     *
     * @return JournalEntry[] The entries, or [] when the current user may not see this run.
     */
    public static function filter(array $entries)
    {
        $userId = self::scopeUserId();

        if (0 === $userId) {
            return $entries;
        }

        foreach ($entries as $entry) {
            if ((int) $entry->get('user_id') !== $userId) {
                return [];
            }
        }

        return $entries;
    }
}
