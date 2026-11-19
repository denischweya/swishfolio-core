<?php
/**
 * Clears a site's AI history: usage log, journal, onboarding record, jobs.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI;

use SwishfolioCore\Tools\Journal\JournalTable;

/**
 * A customer site is a copy of a template site (Ultimate Multisite clones its
 * tables), so without this it starts with the template's AI usage log, undo
 * journal, onboarding record and job records. Run on the new site right after
 * the copy (wu_duplicate_site, see swishfolio-extra), and on template sites to
 * clean them. AI settings, credentials and content are never touched.
 */
class AIDataReset
{
    /** Options holding AI history. */
    const OPTIONS = [UsageLogger::OPTION, 'swish_onboarding'];

    /** Transient prefixes holding AI runs: jobs, run facts, staged outlines, undo, rate limits. */
    const TRANSIENTS = ['sfcore_job_', 'sfcore_gen_meta_', 'sfcore_staged_', 'sfcore_ai_gen_run_', 'sfcore_ai_gen_rate_'];

    /**
     * On the current site.
     *
     * @return array {options: int, transients: int, journal: int} What was removed.
     */
    public static function run()
    {
        global $wpdb;

        $out = ['options' => 0, 'transients' => 0, 'journal' => 0];

        foreach (self::OPTIONS as $option) {
            $out['options'] += delete_option($option) ? 1 : 0;
        }

        foreach (self::TRANSIENTS as $prefix) {
            foreach (['_transient_', '_transient_timeout_'] as $kind) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- bulk clean of this site's own rows.
                $out['transients'] += (int) $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    $wpdb->esc_like($kind . $prefix) . '%'
                ));
            }
        }

        $table = JournalTable::name();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- prefix-derived table name.
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
            $out['journal'] = (int) $wpdb->query("DELETE FROM {$table}"); // phpcs:ignore WordPress.DB
        }

        // Rows went behind the options API's back: drop its cached copies.
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete('notoptions', 'options');

        return $out;
    }

    /**
     * On another site of the network.
     *
     * @param int $blogId Site ID.
     *
     * @return array|null run() output, or null when there is no such site.
     */
    public static function forSite($blogId)
    {
        $blogId = (int) $blogId;

        if ($blogId <= 0 || ! function_exists('get_site') || ! get_site($blogId)) {
            return null;
        }

        switch_to_blog($blogId);
        try {
            return self::run();
        } finally {
            restore_current_blog();
        }
    }
}
