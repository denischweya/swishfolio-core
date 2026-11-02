<?php
/**
 * The journal's table name and schema.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools\Journal;

/**
 * One table per site, by prefix.
 *
 * $wpdb->prefix is already site-scoped on multisite, so tenant isolation needs
 * no site_id column and no filtering discipline at the query layer — a site
 * physically cannot read another site's journal.
 */
class JournalTable
{
    const VERSION_OPTION = 'swishfolio_ai_journal_version';
    const VERSION        = '1';

    /**
     * @return string Prefixed table name.
     */
    public static function name()
    {
        global $wpdb;

        return $wpdb->prefix . 'swishfolio_ai_journal';
    }

    /**
     * Creates or upgrades the table when the stored version is behind.
     *
     * @return void
     */
    public static function install()
    {
        if (get_option(self::VERSION_OPTION) === self::VERSION) {
            return;
        }

        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table   = self::name();
        $collate = $wpdb->get_charset_collate();

        dbDelta(
            "CREATE TABLE {$table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                run_id char(32) NOT NULL DEFAULT '',
                seq smallint(5) unsigned NOT NULL DEFAULT 0,
                user_id bigint(20) unsigned NOT NULL DEFAULT 0,
                tool varchar(100) NOT NULL DEFAULT '',
                side_effect varchar(20) NOT NULL DEFAULT '',
                summary varchar(300) NOT NULL DEFAULT '',
                input longtext NULL,
                target_type varchar(20) NOT NULL DEFAULT 'call',
                target_id varchar(191) NOT NULL DEFAULT '',
                prior longtext NULL,
                result varchar(20) NOT NULL DEFAULT 'ok',
                error_code varchar(50) NULL,
                reverted_at datetime NULL,
                revert_error text NULL,
                created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                PRIMARY KEY  (id),
                KEY run_seq (run_id, seq),
                KEY created_at (created_at)
            ) {$collate};"
        );

        update_option(self::VERSION_OPTION, self::VERSION, false);
    }
}
