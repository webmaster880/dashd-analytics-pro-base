<?php
/**
 * Shared helper functions for external builder integrations.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('dashd_integration_get_source_options')) {
    /**
     * Return source options for builder integrations.
     *
     * @return array<string,string>
     */
    function dashd_integration_get_source_options() {
        global $wpdb;
        static $cached = null;

        if (is_array($cached)) {
            return $cached;
        }

        $cached = [];
        $rows = $wpdb->get_results("SELECT source_key, source_label FROM {$wpdb->prefix}dashd_settings ORDER BY id ASC");
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $raw_key = (string) ($row->source_key ?? '');
                $source_key = function_exists('dashd_normalize_source_key')
                    ? dashd_normalize_source_key($raw_key)
                    : sanitize_key($raw_key);
                if ($source_key === '') {
                    continue;
                }

                $source_label = sanitize_text_field((string) ($row->source_label ?? ''));
                if ($source_label === '') {
                    $source_label = $source_key;
                }

                $cached[$source_key] = $source_label . ' (' . $source_key . ')';
            }
        }

        if (empty($cached)) {
            $cached['table1'] = 'Default Table (table1)';
        }

        return $cached;
    }
}

if (!function_exists('dashd_integration_get_indicator_options')) {
    /**
     * Build indicator options mapped as token => human label.
     * Token format: source_key:indicator_id
     *
     * @return array<string,string>
     */
    function dashd_integration_get_indicator_options() {
        global $wpdb;
        static $cached = null;

        if (is_array($cached)) {
            return $cached;
        }

        $cached = [];
        $source_options = dashd_integration_get_source_options();
        $allowed_sources = array_fill_keys(array_keys($source_options), true);
        $fallback_source = (string) (array_key_first($source_options) ?: 'table1');

        $source_map = [];
        $map_rows = $wpdb->get_results(
            "SELECT indicator_id, MIN(source_key) AS source_key FROM {$wpdb->prefix}dashd_data_records GROUP BY indicator_id"
        );
        if (is_array($map_rows)) {
            foreach ($map_rows as $row) {
                $id = (int) ($row->indicator_id ?? 0);
                if ($id <= 0) {
                    continue;
                }

                $src_raw = (string) ($row->source_key ?? '');
                $src = function_exists('dashd_normalize_source_key')
                    ? dashd_normalize_source_key($src_raw)
                    : sanitize_key($src_raw);
                if ($src !== '') {
                    $source_map[$id] = $src;
                }
            }
        }

        $rows = $wpdb->get_results(
            "SELECT id, name_en, target_source, sort_order FROM {$wpdb->prefix}dashd_indicators ORDER BY sort_order ASC, id ASC"
        );
        if (!is_array($rows)) {
            return $cached;
        }

        foreach ($rows as $row) {
            $id = (int) ($row->id ?? 0);
            if ($id <= 0) {
                continue;
            }

            $name = sanitize_text_field((string) ($row->name_en ?? ''));
            if ($name === '') {
                $name = 'Indicator #' . $id;
            }

            $target_source_raw = (string) ($row->target_source ?? '');
            $target_source = function_exists('dashd_normalize_source_key')
                ? dashd_normalize_source_key($target_source_raw)
                : sanitize_key($target_source_raw);

            $resolved_source = '';
            if ($target_source !== '' && $target_source !== 'all' && isset($allowed_sources[$target_source])) {
                $resolved_source = $target_source;
            } elseif (!empty($source_map[$id]) && isset($allowed_sources[$source_map[$id]])) {
                $resolved_source = (string) $source_map[$id];
            } else {
                $resolved_source = $fallback_source;
            }

            $token = $resolved_source . ':' . $id;
            $cached[$token] = sprintf('%s (%s)', $name, $resolved_source);
        }

        return $cached;
    }
}
