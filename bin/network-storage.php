<?php
/** WP-CLI: eval-file bin/network-storage.php plan|prepare|enable|disable OWNER_ID */
if (!defined('WP_CLI') || !WP_CLI) exit;
if (!is_multisite() || !function_exists('dashd_network_table')) {
    WP_CLI::error('Load DashD on the target Multisite installation first.');
}

global $wpdb;
$command = $args[0] ?? 'plan';
$owner_id = (int) ($args[1] ?? 0);
$network_id = (int) get_current_network_id();
$owner = get_site($owner_id);
if (!$owner_id || !$owner || (int) $owner->network_id !== $network_id || $owner->deleted || $owner->archived || $owner->spam) {
    WP_CLI::error('Specify an active authoritative site ID from this network.');
}

$config = dashd_network_storage_config();
$owner_prefix = $wpdb->get_blog_prefix($owner_id);
$exists = static function ($table) use ($wpdb) {
    return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;
};
$read_map = static function ($table, $key, $label) use ($wpdb, $exists) {
    if (!$exists($table)) return [];
    $rows = $wpdb->get_results("SELECT {$key}, {$label} FROM {$table}", ARRAY_N);
    if ($wpdb->last_error) throw new RuntimeException('Could not read ' . $table);
    return array_column($rows, 1, 0);
};
$audit = static function () use ($wpdb, $exists, $read_map, $owner_prefix, $network_id) {
    foreach (dashd_network_tables() as $suffix) {
        if (!$exists($owner_prefix . 'dashd_' . $suffix)) {
            throw new RuntimeException('The authoritative site is missing ' . $suffix . '.');
        }
    }
    $references = [
        'settings' => ['source_key', 'source_label'],
        'countries' => ['id', 'name_en'],
        'indicators' => ['id', 'name_en'],
    ];
    $canonical = [];
    foreach ($references as $suffix => [$key, $label]) {
        $canonical[$suffix] = $read_map($owner_prefix . 'dashd_' . $suffix, $key, $label);
    }
    $report = [];
    foreach (get_sites(['network_id' => $network_id, 'number' => 0]) as $site) {
        $prefix = $wpdb->get_blog_prefix((int) $site->blog_id);
        $conflicts = [];
        foreach ($references as $suffix => [$key, $label]) {
            foreach ($read_map($prefix . 'dashd_' . $suffix, $key, $label) as $id => $name) {
                if (($canonical[$suffix][$id] ?? null) !== $name) $conflicts[] = $suffix . ':' . $id;
            }
        }
        $table = $prefix . 'dashd_data_records';
        $report[] = [
            'site_id' => (int) $site->blog_id,
            'local_records' => $exists($table) ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}") : 0,
            'conflicts' => $conflicts,
        ];
    }
    return $report;
};

if ($command === 'plan') {
    try {
        WP_CLI::line(wp_json_encode($audit(), JSON_PRETTY_PRINT));
    } catch (Throwable $error) {
        WP_CLI::error($error->getMessage());
    }
    return;
}
if (!in_array($command, ['prepare', 'enable', 'disable'], true)) WP_CLI::error('Unknown command.');
if (!dashd_network_sync_lock()) WP_CLI::error('A network data operation is already running.');

$created = [];
$failure = null;
try {
    // Reload after acquiring the lock so concurrent commands cannot overwrite the state.
    $config = dashd_network_storage_config();
    if ($command === 'prepare') {
        if ($config) throw new RuntimeException('Storage has already been prepared. Existing data will not be overwritten.');
        $report = $audit();
        foreach ($report as $site) {
            if ($site['conflicts']) throw new RuntimeException('Resolve saved identifiers on site ' . $site['site_id'] . ': ' . implode(', ', $site['conflicts']));
        }
        foreach (dashd_network_tables() as $suffix) {
            $source = $owner_prefix . 'dashd_' . $suffix;
            $target = dashd_network_table($suffix);
            if ($exists($target)) throw new RuntimeException('Refusing to overwrite ' . $target);
            $status = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s', $source));
            if (!$status || strcasecmp($status->Engine, 'InnoDB') !== 0) throw new RuntimeException('Expected an InnoDB source: ' . $source);
            if ($wpdb->query("CREATE TABLE {$target} LIKE {$source}") === false) throw new RuntimeException('Could not create ' . $target);
            $created[] = $target;
        }
        if ($wpdb->query('START TRANSACTION WITH CONSISTENT SNAPSHOT') === false) throw new RuntimeException('Could not start copy transaction.');
        $counts = [];
        foreach (dashd_network_tables() as $suffix) {
            $source = $owner_prefix . 'dashd_' . $suffix;
            $target = dashd_network_table($suffix);
            $copied = $wpdb->query("INSERT INTO {$target} SELECT * FROM {$source}");
            $expected = $wpdb->get_var("SELECT COUNT(*) FROM {$source}");
            if ($copied === false || $expected === null || $copied !== (int) $expected) {
                throw new RuntimeException('Copy verification failed for ' . $suffix);
            }
            $counts[$suffix] = $copied;
        }
        if (!$counts['data_records'] || !$counts['settings']) throw new RuntimeException('The authoritative dataset is empty.');
        if ($wpdb->query('COMMIT') === false) throw new RuntimeException('Could not commit the copy.');
        update_network_option($network_id, 'dashd_auto_sync', 'disabled');
        update_network_option($network_id, 'dashd_network_sync_notifications', false);
        if (get_network_option($network_id, 'dashd_auto_sync') !== 'disabled'
            || get_network_option($network_id, 'dashd_network_sync_notifications')) {
            throw new RuntimeException('Could not disable automatic synchronization and notifications.');
        }
        $prepared = ['owner_id' => $owner_id, 'enabled' => false, 'schema' => dashd_get_schema_target_version()];
        if (!update_network_option($network_id, 'dashd_network_storage', $prepared)) {
            throw new RuntimeException('Could not save the prepared storage configuration.');
        }
        $created = [];
        WP_CLI::line(wp_json_encode($counts, JSON_PRETTY_PRINT));
    } else {
        if ((int) ($config['owner_id'] ?? 0) !== $owner_id) throw new RuntimeException('Prepare storage for this owner first.');
        if ($command === 'enable') {
            if (($config['schema'] ?? '') !== dashd_get_schema_target_version()) throw new RuntimeException('Shared schema version does not match this plugin.');
            foreach (dashd_network_tables() as $suffix) {
                if (!$exists(dashd_network_table($suffix))) throw new RuntimeException('Shared table is missing: ' . $suffix);
            }
        }
        $config['enabled'] = $command === 'enable';
        update_network_option($network_id, 'dashd_cache_ver', wp_generate_uuid4());
        update_network_option($network_id, 'dashd_network_storage', $config);
        if (dashd_network_storage_config() !== $config) throw new RuntimeException('Could not save the network mode.');
        if ($config['enabled']) {
            foreach (get_sites(['network_id' => $network_id, 'number' => 0, 'fields' => 'ids']) as $site_id) {
                if ((int) $site_id === $owner_id) continue;
                switch_to_blog((int) $site_id);
                try {
                    if (wp_clear_scheduled_hook('dashd_daily_sync_event') === false) {
                        WP_CLI::warning('Could not remove the old schedule on site ' . (int) $site_id . '; its callback cannot run shared sync.');
                    }
                } finally {
                    restore_current_blog();
                }
            }
        }
    }
} catch (Throwable $error) {
    $failure = $error->getMessage();
    if ($created) {
        $wpdb->query('ROLLBACK');
        foreach ($created as $table) $wpdb->query("DROP TABLE {$table}");
    }
} finally {
    dashd_network_sync_unlock();
}
if ($failure !== null) WP_CLI::error($failure);
WP_CLI::success('Network storage ' . $command . ' completed.');
