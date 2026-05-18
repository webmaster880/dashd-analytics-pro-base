<?php
/**
 * Sync source record store.
 * Loads existing source records, prepares snapshots and upserts rows.
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('DashD_Sync_Source_Record_Store')) {
    class DashD_Sync_Source_Record_Store {
        /** @var string */
        private $source_key = '';

        /** @var string */
        private $sync_date = '';

        /** @var array<int,array<string,mixed>> */
        private $existing_rows = [];

        /** @var array<string,int> */
        private $source_records_map = [];

        public function __construct($source_key, $sync_date) {
            $this->source_key = function_exists('dashd_normalize_source_key')
                ? dashd_normalize_source_key((string) $source_key)
                : sanitize_key((string) $source_key);
            $this->sync_date = (string) $sync_date;
            $this->load_existing_rows();
        }

        private function load_existing_rows() {
            global $wpdb;

            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, indicator_id, country_id, data_year, data_quarter
                 FROM {$wpdb->prefix}dashd_data_records
                 WHERE source_key=%s",
                $this->source_key
            ), ARRAY_A);

            $this->existing_rows = is_array($rows) ? $rows : [];
            $this->source_records_map = [];

            foreach ($this->existing_rows as $row) {
                $key = self::record_cache_key(
                    (int) ($row['indicator_id'] ?? 0),
                    (int) ($row['country_id'] ?? 0),
                    (int) ($row['data_year'] ?? 0),
                    (string) ($row['data_quarter'] ?? 'Q1')
                );
                $id = (int) ($row['id'] ?? 0);
                if ($id > 0) {
                    $this->source_records_map[$key] = $id;
                }
            }
        }

        private static function record_cache_key($indicator_id, $country_id, $year, $quarter) {
            return implode('|', [
                (int) $indicator_id,
                (int) $country_id,
                (int) $year,
                dashd_sync_normalize_quarter((string) $quarter, 'Q1')
            ]);
        }

        public function get_existing_count() {
            return count($this->existing_rows);
        }

        public function prepare_snapshot($snapshot_max_records, $snapshot_max_bytes) {
            $existing_count = $this->get_existing_count();
            $snapshot_dump = '';
            $notice = '';

            if ($existing_count <= 0) {
                return [
                    'records_count' => 0,
                    'dump' => '',
                    'notice' => '',
                ];
            }

            if ((int) $snapshot_max_records > 0 && $existing_count <= (int) $snapshot_max_records) {
                $snapshot_source_rows = $this->load_rows_for_snapshot();
                if (!is_array($snapshot_source_rows)) {
                    $snapshot_source_rows = [];
                }
                $snapshot_rows = [];
                foreach ($snapshot_source_rows as $existing_row) {
                    $snapshot_rows[] = [
                        'indicator_id' => (int) ($existing_row['indicator_id'] ?? 0),
                        'country_id' => (int) ($existing_row['country_id'] ?? 0),
                        'val' => (float) ($existing_row['val'] ?? 0),
                        'data_year' => (int) ($existing_row['data_year'] ?? 0),
                        'data_quarter' => dashd_sync_normalize_quarter((string) ($existing_row['data_quarter'] ?? 'Q1'), 'Q1'),
                    ];
                }

                $snapshot_dump = wp_json_encode([
                    'meta' => ['mode' => 'full', 'records_count' => $existing_count],
                    'rows' => $snapshot_rows,
                ]);

                if (!is_string($snapshot_dump) || strlen($snapshot_dump) > (int) $snapshot_max_bytes) {
                    $snapshot_dump = wp_json_encode([
                        'meta' => [
                            'mode' => 'summary',
                            'records_count' => $existing_count,
                            'reason' => 'snapshot_size_limit_exceeded',
                        ],
                    ]);
                    $notice = 'size_limit';
                }
            } else {
                $snapshot_dump = wp_json_encode([
                    'meta' => [
                        'mode' => 'summary',
                        'records_count' => $existing_count,
                        'reason' => ((int) $snapshot_max_records > 0)
                            ? 'snapshot_record_limit_exceeded'
                            : 'snapshot_storage_disabled',
                    ],
                ]);
                $notice = 'record_limit';
            }

            if (!is_string($snapshot_dump) || $snapshot_dump === '') {
                $snapshot_dump = '{"meta":{"mode":"summary","reason":"snapshot_encode_failed"}}';
            }

            return [
                'records_count' => $existing_count,
                'dump' => $snapshot_dump,
                'notice' => $notice,
            ];
        }

        /**
         * @return array<int,array<string,mixed>>
         */
        private function load_rows_for_snapshot() {
            global $wpdb;

            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT indicator_id, country_id, val, data_year, data_quarter
                 FROM {$wpdb->prefix}dashd_data_records
                 WHERE source_key=%s",
                $this->source_key
            ), ARRAY_A);

            return is_array($rows) ? $rows : [];
        }

        public function save_snapshot($records_count, $snapshot_dump) {
            global $wpdb;

            return $wpdb->insert("{$wpdb->prefix}dashd_snapshots", [
                'source_key'    => $this->source_key,
                'sync_date'     => current_time('mysql'),
                'records_count' => (int) $records_count,
                'data_dump'     => (string) $snapshot_dump
            ]);
        }

        public function upsert_record($indicator_id, $country_id, $year, $quarter, $value) {
            global $wpdb;

            $indicator_id = (int) $indicator_id;
            $country_id = (int) $country_id;
            $year = (int) $year;
            $quarter = dashd_sync_normalize_quarter((string) $quarter, 'Q1');
            $value = (float) $value;

            if ($indicator_id <= 0 || $country_id <= 0 || $year <= 0 || $quarter === '') {
                return '';
            }

            $key = self::record_cache_key($indicator_id, $country_id, $year, $quarter);
            if (isset($this->source_records_map[$key]) && (int) $this->source_records_map[$key] > 0) {
                $updated = $wpdb->update(
                    "{$wpdb->prefix}dashd_data_records",
                    ['val' => $value, 'record_date' => $this->sync_date],
                    ['id' => (int) $this->source_records_map[$key]]
                );
                if ($updated === false) {
                    return '';
                }
                if ((int) $updated > 0) {
                    return 'updated';
                }
                return '';
            }

            $inserted = $wpdb->insert("{$wpdb->prefix}dashd_data_records", [
                'source_key' => $this->source_key,
                'indicator_id' => $indicator_id,
                'country_id' => $country_id,
                'val' => $value,
                'data_year' => $year,
                'data_quarter' => $quarter,
                'record_date' => $this->sync_date
            ]);
            if ($inserted !== false && (int) $wpdb->insert_id > 0) {
                $this->source_records_map[$key] = (int) $wpdb->insert_id;
                return 'inserted';
            }

            return '';
        }
    }
}
