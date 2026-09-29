<?php
/**
 * Uninstall routine.
 *
 * Removes this plugin's own settings. It deliberately does NOT delete content
 * the site owner created: form entries and generated pages are their data, not
 * ours, and silently destroying them on deactivation-then-delete would be
 * indefensible. docs/PRIVACY.md documents how to remove them deliberately.
 *
 * @package SwishfolioCore
 */

if (! defined('ABSPATH') || ! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Options this plugin owns. Credentials are encrypted at rest, so removing
 * these also removes the stored AI and ESP keys.
 *
 * @var string[]
 */
$sfcore_options = [
    'swishfolio_ai_settings',
    'swishfolio_ai_usage',
    'swish_forms_email_settings',
    'swish_forms_esp_settings',
    'swishfolio_styles_cache_cleared_v2',
];

/**
 * Delete this plugin's options for the current site.
 *
 * @param string[] $options Option names.
 *
 * @return void
 */
function sfcore_uninstall_delete_options(array $options)
{
    foreach ($options as $option) {
        delete_option($option);
    }

    // Version-keyed caches; the suffix varies, so clear by pattern.
    global $wpdb;

    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $wpdb->esc_like('_transient_sfcore_ai_manifests_') . '%',
            $wpdb->esc_like('_transient_sfcore_generation_units_') . '%'
        )
    );

    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $wpdb->esc_like('_transient_timeout_sfcore_ai_manifests_') . '%',
            $wpdb->esc_like('_transient_timeout_sfcore_generation_units_') . '%'
        )
    );
}

// Options are per-site, so a multisite install needs every site visited.
if (is_multisite()) {
    $sfcore_sites = get_sites(['fields' => 'ids', 'number' => 0]);

    foreach ($sfcore_sites as $sfcore_site_id) {
        switch_to_blog((int) $sfcore_site_id);
        sfcore_uninstall_delete_options($sfcore_options);
        restore_current_blog();
    }
} else {
    sfcore_uninstall_delete_options($sfcore_options);
}
