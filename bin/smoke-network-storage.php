<?php
/** WP-CLI verification after network storage activation. Pass "seed" to compare with the initial copy. */
if (!defined('WP_CLI') || !WP_CLI) exit;
if (!function_exists('dashd_network_owner_id') || !($owner_id = dashd_network_owner_id())) {
    WP_CLI::error('Network storage must be enabled.');
}

global $wpdb;
$owner_prefix = $wpdb->get_blog_prefix($owner_id);
if (($args[0] ?? '') === 'seed') {
    foreach (dashd_network_tables() as $suffix) {
        $source = $owner_prefix . 'dashd_' . $suffix;
        $target = dashd_network_table($suffix);
        $original = $wpdb->get_results("SELECT * FROM {$source} ORDER BY id", ARRAY_A);
        if ($wpdb->last_error) WP_CLI::error('Could not read seed: ' . $suffix);
        $shared = $wpdb->get_results("SELECT * FROM {$target} ORDER BY id", ARRAY_A);
        if ($wpdb->last_error || $shared !== $original) WP_CLI::error('Shared copy differs from seed: ' . $suffix);
    }
}

$admin = get_user_by('login', get_super_admins()[0] ?? '');
if (!$admin) WP_CLI::error('A network administrator is required for the capability check.');
$source_key = (string) $wpdb->get_var('SELECT source_key FROM ' . dashd_table('data_records') . ' ORDER BY id LIMIT 1');
if ($source_key === '') WP_CLI::error('The shared dataset is empty.');
$baseline = null;
$tested = 0;
foreach (get_sites(['network_id' => get_current_network_id(), 'number' => 0, 'fields' => 'ids']) as $id) {
    switch_to_blog((int) $id);
    try {
        if (dashd_table('data_records') !== dashd_network_table('data_records')
            || dashd_table('leads') !== $wpdb->prefix . 'dashd_leads') {
            throw new RuntimeException('Unexpected table scope on site ' . $id);
        }
        $query = ['key' => $source_key, 'all' => 'true', 'lang' => 'en', 'last_update_format' => 'Y-m-d H:i:s', 'last_update_month_case' => 'locale'];
        $result = DashD_Api_Read_Service::get_modern_data($query);
        if (is_wp_error($result) || empty($result['indicators']) || empty($result['last_updated_at'])) {
            throw new RuntimeException('No shared chart data on site ' . $id);
        }
        $baseline = $baseline ?? $result;
        if ($baseline !== $result) throw new RuntimeException('Chart data differs on site ' . $id);
        wp_set_current_user(0);
        if (dashd_can_manage_shared_data()) throw new RuntimeException('Anonymous user can manage shared data.');
        wp_set_current_user($admin->ID);
        if (dashd_can_manage_shared_data() !== ((int) $id === $owner_id)) {
            throw new RuntimeException('Wrong administration site: ' . $id);
        }
        if ((int) $id !== $owner_id && dashd_sync_repository() !== false) {
            throw new RuntimeException('A consumer site attempted synchronization.');
        }
        $tested++;
    } finally {
        restore_current_blog();
    }
}
WP_CLI::success("Verified shared chart data, local lead tables and management scope across {$tested} sites.");
