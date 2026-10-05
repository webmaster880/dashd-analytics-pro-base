<?php
/** Verify source upserts without touching a WordPress database. */
if (PHP_SAPI !== 'cli') exit;

define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');

function sanitize_key($value) { return strtolower((string) $value); }
function dashd_sync_normalize_quarter($quarter, $default = 'Q1') {
    return in_array($quarter, ['Q1', 'Q2', 'Q3', 'Q4'], true) ? $quarter : $default;
}

class DashD_Test_Wpdb {
    public $prefix = 'wp_';
    public $insert_id = 0;
    public $rows = [];
    public $writes = 0;

    public function prepare($query, ...$args) { return [$query, $args]; }

    public function get_results($prepared, $output) {
        $source = $prepared[1][0];
        return array_values(array_filter($this->rows, static function ($row) use ($source) {
            return $row['source_key'] === $source;
        }));
    }

    public function update($table, $data, $where) {
        $id = (int) $where['id'];
        if (!isset($this->rows[$id])) return false;
        $this->rows[$id] = array_merge($this->rows[$id], $data);
        $this->writes++;
        return 1;
    }

    public function insert($table, $data) {
        $this->insert_id++;
        $this->rows[$this->insert_id] = array_merge(['id' => $this->insert_id], $data);
        $this->writes++;
        return 1;
    }
}

function expect_same($actual, $expected, $label) {
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL: {$label}: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

require dirname(__DIR__) . '/includes/services/class-dashd-sync-source-record-store.php';

$wpdb = new DashD_Test_Wpdb();
$initial = new DashD_Sync_Source_Record_Store('table1', '2026-10-05 09:15:00');
expect_same($initial->upsert_record(1, 2, 2026, 'Q1', 10.5), 'inserted', 'new record');
expect_same($wpdb->rows[1]['record_date'], '2026-10-05 09:15:00', 'initial timestamp');
expect_same($initial->upsert_record(1, 2, 2026, 'Q1', 10.5), '', 'duplicate in same payload');

$repeat = new DashD_Sync_Source_Record_Store('table1', '2026-10-06 10:30:00');
expect_same($repeat->upsert_record(1, 2, 2026, 'Q1', 10.5), '', 'unchanged next day');
expect_same($wpdb->rows[1]['record_date'], '2026-10-05 09:15:00', 'unchanged timestamp');
expect_same($wpdb->writes, 1, 'unchanged rows are not written');

expect_same($repeat->upsert_record(1, 2, 2026, 'Q1', 11.5), 'updated', 'changed value');
expect_same($wpdb->rows[1]['record_date'], '2026-10-06 10:30:00', 'changed timestamp');
expect_same($repeat->upsert_record(1, 2, 2026, 'Q1', 11.5), '', 'unchanged after update');
expect_same($wpdb->writes, 2, 'only real changes are written');

echo "OK: source records retain their timestamp until val changes.\n";
