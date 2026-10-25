<?php
/** Run with wp --skip-plugins --skip-themes eval-file; uses session-only temporary tables. */
if (!defined('WP_CLI') || !WP_CLI) exit;
if (class_exists('DashD_Api_Read_Service')) {
    WP_CLI::error('Run with --skip-plugins --skip-themes so the staged service can be loaded.');
}
if (!function_exists('dashd_api_is_allowed_source')) {
    function dashd_api_is_allowed_source($source) { return in_array($source, ['table1', 'table2'], true); }
    function dashd_api_public_rate_limit_check($bucket) { return true; }
    function dashd_api_public_cache_key($bucket, array $args = []) { return ''; }
}
require dirname(__DIR__) . '/includes/date-format.php';
require dirname(__DIR__) . '/includes/services/class-dashd-api-read-service.php';

global $wpdb;
$original_prefix = $wpdb->prefix;
$fixture_prefix = 'dashd_date_smoke_' . bin2hex(random_bytes(6)) . '_';
$tables = [];
$checks = 0;
$expect = static function ($actual, $expected, $label) use (&$checks) {
    $checks++;
    if ($actual !== $expected) throw new RuntimeException($label . ': ' . var_export($actual, true));
};
$read = static function (array $query = []) {
    $result = DashD_Api_Read_Service::get_modern_data(array_merge([
        'key' => 'table1', 'indicators' => 'table1:1', 'lang' => 'en',
        'last_update_format' => 'Y-m-d H:i:s', 'last_update_month_case' => 'locale',
    ], $query));
    if (is_wp_error($result)) throw new RuntimeException($result->get_error_message());
    return $result;
};

try {
    foreach ([
        'dashd_indicators' => 'id int PRIMARY KEY, name_en varchar(100), sort_order int DEFAULT 0',
        'dashd_countries' => 'id int PRIMARY KEY, name_en varchar(100), flag_url varchar(255), sort_order int DEFAULT 0',
        'dashd_data_records' => 'id int AUTO_INCREMENT PRIMARY KEY, source_key varchar(32), indicator_id int, country_id int, data_year int, data_quarter varchar(2), val decimal(20,4), record_date datetime NULL',
    ] as $suffix => $definition) {
        $table = $fixture_prefix . $suffix;
        if ($wpdb->query("CREATE TEMPORARY TABLE {$table} ({$definition})") === false) throw new RuntimeException($wpdb->last_error);
        $tables[] = $table;
    }
    $wpdb->prefix = $fixture_prefix;
    foreach ([1 => 'Primary', 2 => 'Other', 3 => 'Undated'] as $id => $name) {
        if (!$wpdb->insert($fixture_prefix . 'dashd_indicators', ['id' => $id, 'name_en' => $name])) throw new RuntimeException($wpdb->last_error);
    }
    foreach ([1 => 'Ukraine', 2 => 'Georgia'] as $id => $name) {
        if (!$wpdb->insert($fixture_prefix . 'dashd_countries', ['id' => $id, 'name_en' => $name, 'flag_url' => ''])) throw new RuntimeException($wpdb->last_error);
    }
    foreach ([
        ['table1', 1, 1, 2025, 'Q4', 100, '2026-01-02 09:00:00'],
        ['table1', 1, 1, 2026, 'Q1', 10, '2026-02-01 10:00:00'],
        ['table1', 1, 2, 2026, 'Q1', 20, '2026-02-02 11:00:00'],
        ['table1', 1, 1, 2026, 'Q2', 30, '2026-03-01 12:00:00'],
        ['table1', 1, 1, 2026, 'Q4', 40, '2026-04-01 13:00:00'],
        ['table1', 2, 1, 2026, 'Q1', 999, '2026-12-01 14:00:00'],
        ['table2', 1, 1, 2026, 'Q1', 888, '2026-12-02 15:00:00'],
        ['table1', 3, 1, 2026, 'Q1', 0, null],
    ] as $row) {
        $values = array_combine(['source_key', 'indicator_id', 'country_id', 'data_year', 'data_quarter', 'val', 'record_date'], $row);
        if (!$wpdb->insert($fixture_prefix . 'dashd_data_records', $values)) throw new RuntimeException($wpdb->last_error);
    }

    $history = $read(['all' => 'true']);
    $expect($history['last_updated_at'], '2026-04-01 13:00:00', 'History excludes other indicators and sources');
    $expect($history['last_updated_by_country']['Georgia'], '2026-02-02 11:00:00', 'Country-specific date');
    $quarter = $read(['year' => 2026, 'q' => 'Q1']);
    $expect($quarter['last_sync'], '2026-02-02 11:00:00', 'Quarter excludes later quarters');
    $expect($quarter['last_updated_by_country']['Ukraine'], '2026-02-01 10:00:00', 'Initial load date');
    $annual = $read(['year' => 2026, 'q' => 'Q4']);
    $expect($annual['last_sync'], '2026-04-01 13:00:00', 'Annual date includes all summed quarters');
    $expect($annual['indicators']['Primary']['Ukraine'], 80.0, 'Annual values remain unchanged');
    $window = $read(['all' => 'true', 'period_start' => '2026-Q1', 'period_end' => '2026-Q2']);
    $expect($window['last_updated_at'], '2026-03-01 12:00:00', 'Selected date range');
    $old = $read(['year' => 2025, 'q' => 'Q4']);
    $expect($old['last_sync'], '2026-01-02 09:00:00', 'Historical period ignores newer records');
    $empty = $read(['year' => 2024, 'q' => 'Q4']);
    $expect($empty['last_sync'], '--', 'No global sync fallback for empty data');
    $unknown = $read(['indicators' => 'table1:3', 'all' => 'true']);
    $expect($unknown['last_sync'], '--', 'Unknown record date is not replaced by sync run time');
    $multiple = $read(['indicators' => 'table1:1,table1:2', 'all' => 'true']);
    $expect($multiple['last_updated_at'], '2026-12-01 14:00:00', 'Multiple selected indicators');
    $other_source = $read(['key' => 'table2', 'indicators' => 'table2:1', 'all' => 'true']);
    $expect($other_source['last_updated_at'], '2026-12-02 15:00:00', 'Selected source');
    $changed = $wpdb->update($fixture_prefix . 'dashd_data_records', [
        'val' => 11, 'record_date' => '2026-10-25 12:00:00',
    ], ['source_key' => 'table1', 'indicator_id' => 1, 'country_id' => 1, 'data_year' => 2026, 'data_quarter' => 'Q1']);
    if ($changed !== 1) throw new RuntimeException('Fixture update failed.');
    $changed_data = $read(['year' => 2026, 'q' => 'Q1']);
    $expect($changed_data['last_sync'], '2026-10-25 12:00:00', 'Changed record advances date');
    $expect($changed_data['last_updated_by_country']['Georgia'], '2026-02-02 11:00:00', 'Other country keeps its date');
    echo "OK: {$checks} widget update-date checks passed using temporary tables.\n";
} finally {
    $wpdb->prefix = $original_prefix;
    foreach ($tables as $table) $wpdb->query("DROP TEMPORARY TABLE IF EXISTS {$table}");
}
