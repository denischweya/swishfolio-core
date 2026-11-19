<?php
/**
 * WP-CLI command to re-encrypt credentials stored in the superseded format.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Commands;

use SwishfolioCore\Services\EncryptionService;

/**
 * Rewrites every stored credential into the delimiter-free format.
 *
 * The old format corrupted roughly 1 in 254 credentials, and 96% of those
 * returned a mangled non-empty string rather than failing cleanly. Values that
 * were already corrupted cannot be recovered — the IV is genuinely gone — so
 * this reports them so the owner knows exactly which keys to re-enter, instead
 * of leaving them to fail mysteriously later.
 *
 * ## EXAMPLES
 *
 *     # See what would change, write nothing.
 *     wp swishfolio migrate-credentials --dry-run
 *
 *     # Re-encrypt on the current site.
 *     wp swishfolio migrate-credentials
 *
 *     # Re-encrypt across every site in the network.
 *     wp swishfolio migrate-credentials --network
 */
class MigrateCredentialsCommand
{
    /**
     * Option name => list of credential field paths within it.
     *
     * A path of '*' means every value in the array is a credential.
     */
    private const TARGETS = [
        'swishfolio_ai_credentials'  => ['*'],
        'swish_forms_email_settings' => ['smtp_password'],
        'swish_forms_esp_settings'   => ['*.api_key', '*.api_secret', '*.token'],
    ];

    /**
     * Re-encrypt stored credentials.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Report what would change without writing.
     *
     * [--network]
     * : Run on every site in a multisite network.
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Flags.
     *
     * @return void
     */
    public function __invoke($args, $assoc_args)
    {
        $dryRun  = ! empty($assoc_args['dry-run']);
        $network = ! empty($assoc_args['network']);

        if ($network && ! is_multisite()) {
            \WP_CLI::error('This is not a multisite install.');
        }

        $siteIds = $network ? get_sites(['fields' => 'ids', 'number' => 0]) : [0];
        $totals  = ['migrated' => 0, 'current' => 0, 'unrecoverable' => 0];

        foreach ($siteIds as $siteId) {
            if ($network) {
                switch_to_blog((int) $siteId);
                \WP_CLI::log(sprintf('--- Site %d (%s) ---', $siteId, home_url()));
            }

            $result = $this->migrateSite($dryRun);

            foreach ($totals as $key => $value) {
                $totals[$key] = $value + $result[$key];
            }

            if ($network) {
                restore_current_blog();
            }
        }

        \WP_CLI::log('');

        if ($totals['unrecoverable'] > 0) {
            \WP_CLI::warning(sprintf(
                '%d credential(s) could not be decrypted and must be re-entered by hand. '
                . 'They were corrupted when written, before this fix existed.',
                $totals['unrecoverable']
            ));
        }

        \WP_CLI::success(sprintf(
            '%s%d re-encrypted, %d already current, %d unrecoverable.',
            $dryRun ? 'Dry run: ' : '',
            $totals['migrated'],
            $totals['current'],
            $totals['unrecoverable']
        ));
    }

    /**
     * @param bool $dryRun Whether to write.
     *
     * @return array{migrated:int,current:int,unrecoverable:int}
     */
    private function migrateSite($dryRun)
    {
        $encryption = new EncryptionService();
        $counts     = ['migrated' => 0, 'current' => 0, 'unrecoverable' => 0];

        foreach (self::TARGETS as $option => $paths) {
            $stored = get_option($option, []);

            if (! is_array($stored) || $stored === []) {
                continue;
            }

            $changed = false;

            foreach ($this->resolvePaths($stored, $paths) as $path) {
                $value = $this->valueAt($stored, $path);

                if (! is_string($value) || $value === '') {
                    continue;
                }

                $label = $option . '.' . implode('.', $path);

                if (! $encryption->isLegacy($value)) {
                    $counts['current']++;
                    continue;
                }

                $plain = $encryption->decrypt($value);

                if ($plain === '') {
                    $counts['unrecoverable']++;
                    \WP_CLI::warning(sprintf('Unrecoverable, re-enter by hand: %s', $label));
                    continue;
                }

                $counts['migrated']++;
                \WP_CLI::log(sprintf('%s re-encrypt %s', $dryRun ? '[dry-run]' : '[ok]      ', $label));

                if (! $dryRun) {
                    $stored  = $this->setValueAt($stored, $path, $encryption->encrypt($plain));
                    $changed = true;
                }
            }

            if ($changed) {
                update_option($option, $stored, false);
            }
        }

        return $counts;
    }

    /**
     * Expand '*' wildcards in the configured paths against real data.
     *
     * @param array    $stored Option contents.
     * @param string[] $paths  Configured paths.
     *
     * @return array[] List of concrete path segment arrays.
     */
    private function resolvePaths(array $stored, array $paths)
    {
        $resolved = [];

        foreach ($paths as $path) {
            $segments = explode('.', $path);

            if ($segments === ['*']) {
                foreach (array_keys($stored) as $key) {
                    if (is_string($stored[$key])) {
                        $resolved[] = [$key];
                    }
                }
                continue;
            }

            if ($segments[0] !== '*') {
                $resolved[] = $segments;
                continue;
            }

            // '*.field' — the same field inside every sub-array.
            $tail = array_slice($segments, 1);

            foreach (array_keys($stored) as $key) {
                if (is_array($stored[$key])) {
                    $resolved[] = array_merge([$key], $tail);
                }
            }
        }

        return $resolved;
    }

    /**
     * @param array    $data Source.
     * @param string[] $path Segments.
     *
     * @return mixed|null
     */
    private function valueAt(array $data, array $path)
    {
        $node = $data;

        foreach ($path as $segment) {
            if (! is_array($node) || ! array_key_exists($segment, $node)) {
                return null;
            }

            $node = $node[$segment];
        }

        return $node;
    }

    /**
     * @param array    $data  Source.
     * @param string[] $path  Segments.
     * @param mixed    $value New value.
     *
     * @return array
     */
    private function setValueAt(array $data, array $path, $value)
    {
        $segment = array_shift($path);

        if ($path === []) {
            $data[$segment] = $value;

            return $data;
        }

        $child = isset($data[$segment]) && is_array($data[$segment]) ? $data[$segment] : [];

        $data[$segment] = $this->setValueAt($child, $path, $value);

        return $data;
    }
}
