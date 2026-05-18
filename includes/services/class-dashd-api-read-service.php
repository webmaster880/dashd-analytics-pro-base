<?php
/**
 * Read API service for widget data endpoints.
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('DashD_Api_Read_Service')) {
    class DashD_Api_Read_Service {
        /**
         * @param array<string,mixed> $query
         * @return array<string,mixed>|WP_Error
         */
        public static function get_modern_data(array $query) {
            global $wpdb;

            if (!function_exists('dashd_api_public_rate_limit_check') || !dashd_api_public_rate_limit_check('modern_data')) {
                return self::error('rate_limited', 'Too many requests. Please try again later.', 429);
            }

            $key = function_exists('dashd_normalize_source_key')
                ? dashd_normalize_source_key((string) ($query['key'] ?? 'table1'), 'table1')
                : sanitize_key((string) ($query['key'] ?? 'table1'));
            if ($key === '') {
                $key = 'table1';
            }
            if (!function_exists('dashd_api_is_allowed_source') || !dashd_api_is_allowed_source($key)) {
                return self::error('invalid_source', 'Invalid data source.', 400);
            }

            $lang_raw = sanitize_key((string) ($query['lang'] ?? 'en'));
            $lang = in_array($lang_raw, ['en', 'uk', 'hy', 'ro', 'ka'], true) ? $lang_raw : 'en';
            $is_all = isset($query['all']) && $query['all'] === 'true';
            $fy = isset($query['year']) ? (int) $query['year'] : 0;
            $indicator_specs = self::parse_indicator_specs($query['indicators'] ?? '');

            $fq_raw = strtoupper((string) ($query['q'] ?? ''));
            $fq = in_array($fq_raw, ['Q1', 'Q2', 'Q3', 'Q4'], true) ? $fq_raw : '';

            $cache_key = function_exists('dashd_api_public_cache_key')
                ? dashd_api_public_cache_key('modern_data', [
                    'key' => $key,
                    'lang' => $lang,
                    'all' => $is_all ? 1 : 0,
                    'year' => $fy,
                    'q' => $fq,
                    'indicators' => $indicator_specs,
                ])
                : '';
            if ($cache_key !== '') {
                $cached_data = get_transient($cache_key);
                if (is_array($cached_data)) {
                    return $cached_data;
                }
            }

            $col = 'name_' . $lang;
            $last_sync = get_option('dashd_last_global_sync', '');
            $formatted_sync = '--';
            if (!empty($last_sync)) {
                $formatted_sync = function_exists('mysql2date')
                    ? (string) mysql2date('d.m.Y H:i', (string) $last_sync, true)
                    : (string) wp_date('d.m.Y H:i', strtotime((string) $last_sync));
                if ($formatted_sync === '') {
                    $formatted_sync = '--';
                }
            }

            if ($is_all) {
                $max_all_periods = function_exists('dashd_api_limit_int')
                    ? dashd_api_limit_int(apply_filters('dashd_api_all_max_periods', 120, $key), 120, 4, 500)
                    : 120;
                $max_all_rows = function_exists('dashd_api_limit_int')
                    ? dashd_api_limit_int(apply_filters('dashd_api_all_max_rows', 60000, $key), 60000, 1000, 500000)
                    : 60000;
                $max_all_countries = function_exists('dashd_api_limit_int')
                    ? dashd_api_limit_int(apply_filters('dashd_api_all_max_countries', 250, $key), 250, 1, 2000)
                    : 250;
                $max_all_indicators = function_exists('dashd_api_limit_int')
                    ? dashd_api_limit_int(apply_filters('dashd_api_all_max_indicators', 500, $key), 500, 1, 5000)
                    : 500;

                $periods_sql = "SELECT DISTINCT r.data_year, r.data_quarter
                     FROM {$wpdb->prefix}dashd_data_records r
                     WHERE 1=1";
                $periods_args = [];
                if (!empty($indicator_specs)) {
                    [$indicator_sql, $indicator_args] = self::build_indicator_filter_sql($indicator_specs, 'r', $key);
                    if ($indicator_sql !== '') {
                        $periods_sql .= " AND ({$indicator_sql})";
                        $periods_args = array_merge($periods_args, $indicator_args);
                    }
                } else {
                    $periods_sql .= " AND r.source_key=%s";
                    $periods_args[] = $key;
                }
                $periods_sql .= " ORDER BY r.data_year DESC, r.data_quarter DESC LIMIT %d";
                $periods_args[] = $max_all_periods;
                $periods = $wpdb->get_results($wpdb->prepare($periods_sql, ...$periods_args));
                if (is_array($periods) && count($periods) > 1) {
                    $periods = array_reverse($periods);
                }
                $period_labels = array_map(static fn($p) => "{$p->data_quarter} {$p->data_year}", $periods);
                $period_index_map = [];
                foreach ($period_labels as $idx => $period_label) {
                    $period_index_map[$period_label] = $idx;
                }

                $all_sql = "
                    SELECT COALESCE(NULLIF(i.$col,''), i.name_en) as ind, COALESCE(NULLIF(c.$col,''), c.name_en) as cty, r.val, r.data_year, r.data_quarter 
                    FROM {$wpdb->prefix}dashd_data_records r 
                    JOIN {$wpdb->prefix}dashd_indicators i ON r.indicator_id = i.id 
                    JOIN {$wpdb->prefix}dashd_countries c ON r.country_id = c.id
                    WHERE 1=1";
                $all_args = [];
                if (!empty($indicator_specs)) {
                    [$indicator_sql, $indicator_args] = self::build_indicator_filter_sql($indicator_specs, 'r', $key);
                    if ($indicator_sql !== '') {
                        $all_sql .= " AND ({$indicator_sql})";
                        $all_args = array_merge($all_args, $indicator_args);
                    }
                } else {
                    $all_sql .= " AND r.source_key = %s";
                    $all_args[] = $key;
                }
                $all_sql .= " ORDER BY i.sort_order ASC, i.id ASC, c.sort_order ASC, c.id ASC LIMIT %d";
                $all_args[] = $max_all_rows;
                $results = $wpdb->get_results($wpdb->prepare($all_sql, ...$all_args));

                $data = ['periods' => $period_labels, 'countries' => [], 'indicators' => [], 'last_sync' => $formatted_sync];
                $country_set = [];
                $truncated = [
                    'countries' => false,
                    'indicators' => false,
                ];
                foreach ($results as $row) {
                    $ind_label = function_exists('dashd_api_safe_label') ? dashd_api_safe_label($row->ind ?? '') : sanitize_text_field((string) ($row->ind ?? ''));
                    $cty_label = function_exists('dashd_api_safe_label') ? dashd_api_safe_label($row->cty ?? '') : sanitize_text_field((string) ($row->cty ?? ''));
                    if ($ind_label === '' || $cty_label === '') continue;

                    $p_key = "{$row->data_quarter} {$row->data_year}";
                    if (!isset($period_index_map[$p_key])) continue;
                    $p_idx = (int) $period_index_map[$p_key];

                    if (!isset($country_set[$cty_label])) {
                        if (count($country_set) >= $max_all_countries) {
                            $truncated['countries'] = true;
                            continue;
                        }
                        $country_set[$cty_label] = true;
                        $data['countries'][] = $cty_label;
                    }

                    if (!isset($data['indicators'][$ind_label])) {
                        if (count($data['indicators']) >= $max_all_indicators) {
                            $truncated['indicators'] = true;
                            continue;
                        }
                        $data['indicators'][$ind_label] = [];
                    }
                    if (!isset($data['indicators'][$ind_label][$cty_label])) {
                        $data['indicators'][$ind_label][$cty_label] = array_fill(0, count($period_labels), 0);
                    }
                    $data['indicators'][$ind_label][$cty_label][$p_idx] = (float) $row->val;
                }
                if ($truncated['countries'] || $truncated['indicators']) {
                    $data['truncated'] = $truncated;
                }
                self::cache_if_needed($cache_key, $data, 'modern_data');

                return $data;
            }

            if (!$fy || empty($fq)) {
                $last_sql = "SELECT r.data_year, r.data_quarter FROM {$wpdb->prefix}dashd_data_records r WHERE 1=1";
                $last_args = [];
                if (!empty($indicator_specs)) {
                    [$indicator_sql, $indicator_args] = self::build_indicator_filter_sql($indicator_specs, 'r', $key);
                    if ($indicator_sql !== '') {
                        $last_sql .= " AND ({$indicator_sql})";
                        $last_args = array_merge($last_args, $indicator_args);
                    }
                } else {
                    $last_sql .= " AND r.source_key=%s";
                    $last_args[] = $key;
                }
                $last_sql .= " ORDER BY r.data_year DESC, r.data_quarter DESC LIMIT 1";
                $last = $wpdb->get_row($wpdb->prepare($last_sql, ...$last_args));
                if ($last) {
                    $fy = (int) $last->data_year;
                    $fq = (string) $last->data_quarter;
                }
            }

            $max_period_rows = function_exists('dashd_api_limit_int')
                ? dashd_api_limit_int(apply_filters('dashd_api_period_max_rows', 25000, $key, $fy, $fq), 25000, 1000, 300000)
                : 25000;
            $max_period_countries = function_exists('dashd_api_limit_int')
                ? dashd_api_limit_int(apply_filters('dashd_api_period_max_countries', 250, $key, $fy, $fq), 250, 1, 3000)
                : 250;
            $max_period_indicators = function_exists('dashd_api_limit_int')
                ? dashd_api_limit_int(apply_filters('dashd_api_period_max_indicators', 500, $key, $fy, $fq), 500, 1, 5000)
                : 500;

            $current_sql = "
                SELECT COALESCE(NULLIF(i.$col,''), i.name_en) as ind, COALESCE(NULLIF(c.$col,''), c.name_en) as cty, r.val
                FROM {$wpdb->prefix}dashd_data_records r
                JOIN {$wpdb->prefix}dashd_indicators i ON r.indicator_id = i.id
                JOIN {$wpdb->prefix}dashd_countries c ON r.country_id = c.id
                WHERE r.data_year=%d AND r.data_quarter=%s";
            $current_args = [$fy, $fq];
            if (!empty($indicator_specs)) {
                [$indicator_sql, $indicator_args] = self::build_indicator_filter_sql($indicator_specs, 'r', $key);
                if ($indicator_sql !== '') {
                    $current_sql .= " AND ({$indicator_sql})";
                    $current_args = array_merge($current_args, $indicator_args);
                }
            } else {
                $current_sql .= " AND r.source_key=%s";
                $current_args[] = $key;
            }
            $current_sql .= " ORDER BY i.sort_order ASC, i.id ASC, c.sort_order ASC, c.id ASC LIMIT %d";
            $current_args[] = $max_period_rows;
            $current = $wpdb->get_results($wpdb->prepare($current_sql, ...$current_args));

            $prev_year = $fy - 1;
            $previous_sql = "
                SELECT COALESCE(NULLIF(i.$col,''), i.name_en) as ind, COALESCE(NULLIF(c.$col,''), c.name_en) as cty, r.val
                FROM {$wpdb->prefix}dashd_data_records r
                JOIN {$wpdb->prefix}dashd_indicators i ON r.indicator_id = i.id
                JOIN {$wpdb->prefix}dashd_countries c ON r.country_id = c.id
                WHERE r.data_year=%d AND r.data_quarter=%s";
            $previous_args = [$prev_year, $fq];
            if (!empty($indicator_specs)) {
                [$indicator_sql, $indicator_args] = self::build_indicator_filter_sql($indicator_specs, 'r', $key);
                if ($indicator_sql !== '') {
                    $previous_sql .= " AND ({$indicator_sql})";
                    $previous_args = array_merge($previous_args, $indicator_args);
                }
            } else {
                $previous_sql .= " AND r.source_key=%s";
                $previous_args[] = $key;
            }
            $previous_sql .= " ORDER BY i.sort_order ASC, i.id ASC, c.sort_order ASC, c.id ASC LIMIT %d";
            $previous_args[] = $max_period_rows;
            $previous = $wpdb->get_results($wpdb->prepare($previous_sql, ...$previous_args));

            $country_set = [];
            $truncated = [
                'countries' => false,
                'indicators' => false,
            ];

            $data = [
                'countries' => [],
                'indicators' => [],
                'previous' => [],
                'last_sync' => $formatted_sync,
                'year' => $fy,
                'quarter' => $fq,
            ];

            foreach ($current as $row) {
                $ind_label = function_exists('dashd_api_safe_label') ? dashd_api_safe_label($row->ind ?? '') : sanitize_text_field((string) ($row->ind ?? ''));
                $cty_label = function_exists('dashd_api_safe_label') ? dashd_api_safe_label($row->cty ?? '') : sanitize_text_field((string) ($row->cty ?? ''));
                if ($ind_label === '' || $cty_label === '') continue;

                if (!isset($country_set[$cty_label])) {
                    if (count($country_set) >= $max_period_countries) {
                        $truncated['countries'] = true;
                        continue;
                    }
                    $country_set[$cty_label] = true;
                    $data['countries'][] = $cty_label;
                }

                if (!isset($data['indicators'][$ind_label])) {
                    if (count($data['indicators']) >= $max_period_indicators) {
                        $truncated['indicators'] = true;
                        continue;
                    }
                    $data['indicators'][$ind_label] = [];
                }

                $data['indicators'][$ind_label][$cty_label] = (float) $row->val;
            }

            foreach ($previous as $row) {
                $ind_label = function_exists('dashd_api_safe_label') ? dashd_api_safe_label($row->ind ?? '') : sanitize_text_field((string) ($row->ind ?? ''));
                $cty_label = function_exists('dashd_api_safe_label') ? dashd_api_safe_label($row->cty ?? '') : sanitize_text_field((string) ($row->cty ?? ''));
                if ($ind_label === '' || $cty_label === '') continue;
                if (!isset($country_set[$cty_label])) continue;
                if (!isset($data['indicators'][$ind_label])) continue;
                if (!isset($data['previous'][$ind_label])) {
                    $data['previous'][$ind_label] = [];
                }
                $data['previous'][$ind_label][$cty_label] = (float) $row->val;
            }

            if ($truncated['countries'] || $truncated['indicators']) {
                $data['truncated'] = $truncated;
            }

            self::cache_if_needed($cache_key, $data, 'modern_data');

            return $data;
        }

        /**
         * @param array<string,mixed> $query
         * @return array<string,mixed>|WP_Error
         */
        public static function get_periods_split(array $query) {
            global $wpdb;

            if (!function_exists('dashd_api_public_rate_limit_check') || !dashd_api_public_rate_limit_check('periods_split')) {
                return self::error('rate_limited', 'Too many requests. Please try again later.', 429);
            }

            $key = function_exists('dashd_normalize_source_key')
                ? dashd_normalize_source_key((string) ($query['key'] ?? 'table1'), 'table1')
                : sanitize_key((string) ($query['key'] ?? 'table1'));
            $indicator_specs = self::parse_indicator_specs($query['indicators'] ?? '');
            if ($key === '') {
                $key = 'table1';
            }
            if (!function_exists('dashd_api_is_allowed_source') || !dashd_api_is_allowed_source($key)) {
                return self::error('invalid_source', 'Invalid data source.', 400);
            }

            $cache_key = function_exists('dashd_api_public_cache_key')
                ? dashd_api_public_cache_key('periods_split', ['key' => $key, 'indicators' => $indicator_specs])
                : '';
            if ($cache_key !== '') {
                $cached_data = get_transient($cache_key);
                if (is_array($cached_data)) {
                    return $cached_data;
                }
            }

            $max_years = function_exists('dashd_api_limit_int')
                ? dashd_api_limit_int(apply_filters('dashd_api_periods_max_years', 80, $key), 80, 4, 500)
                : 80;
            $years_sql = "SELECT DISTINCT r.data_year
                 FROM {$wpdb->prefix}dashd_data_records r
                 WHERE 1=1";
            $years_args = [];
            if (!empty($indicator_specs)) {
                [$indicator_sql, $indicator_args] = self::build_indicator_filter_sql($indicator_specs, 'r', $key);
                if ($indicator_sql !== '') {
                    $years_sql .= " AND ({$indicator_sql})";
                    $years_args = array_merge($years_args, $indicator_args);
                }
            } else {
                $years_sql .= " AND r.source_key = %s";
                $years_args[] = $key;
            }
            $years_sql .= " ORDER BY r.data_year DESC LIMIT %d";
            $years_args[] = $max_years;
            $years = $wpdb->get_col($wpdb->prepare($years_sql, ...$years_args));
            $quarters = ['Q4', 'Q3', 'Q2', 'Q1'];
            $data = ['years' => $years, 'quarters' => $quarters];

            self::cache_if_needed($cache_key, $data, 'periods_split');

            return $data;
        }

        /**
         * @param array<string,mixed> $data
         */
        private static function cache_if_needed($cache_key, array $data, $bucket) {
            if (!is_string($cache_key) || $cache_key === '') {
                return;
            }
            $ttl = function_exists('dashd_api_public_cache_ttl')
                ? dashd_api_public_cache_ttl((string) $bucket)
                : 0;
            if ($ttl > 0) {
                set_transient($cache_key, $data, $ttl);
            }
        }

        /**
         * @return WP_Error
         */
        private static function error($code, $message, $status) {
            return new WP_Error((string) $code, (string) $message, ['status' => (int) $status]);
        }

        /**
         * @param mixed $raw
         * @return array<int,array{source:string,id:int}>
         */
        private static function parse_indicator_specs($raw) {
            $parts = [];
            if (is_array($raw)) {
                foreach ($raw as $item) {
                    $parts[] = is_scalar($item) ? (string) $item : '';
                }
            } else {
                $parts[] = is_scalar($raw) ? (string) $raw : '';
            }

            $specs = [];
            $seen = [];
            foreach ($parts as $part) {
                foreach (explode(',', $part) as $chunk) {
                    $chunk = trim((string) $chunk);
                    if ($chunk === '') {
                        continue;
                    }

                    $source = '';
                    $id = 0;
                    if (preg_match('/^([a-z0-9_\\-]+):(\\d+)$/i', $chunk, $m) === 1) {
                        $source_raw = (string) $m[1];
                        $source = function_exists('dashd_normalize_source_key')
                            ? dashd_normalize_source_key($source_raw)
                            : sanitize_key($source_raw);
                        $id = (int) $m[2];
                    } elseif (preg_match('/^\\d+$/', $chunk) === 1) {
                        $id = (int) $chunk;
                    }

                    if ($id <= 0) {
                        continue;
                    }

                    if ($source !== '' && function_exists('dashd_api_is_allowed_source') && !dashd_api_is_allowed_source($source)) {
                        continue;
                    }

                    $token = ($source !== '' ? $source : '_') . ':' . $id;
                    if (isset($seen[$token])) {
                        continue;
                    }
                    $seen[$token] = true;
                    $specs[] = ['source' => $source, 'id' => $id];
                }
            }

            if (count($specs) > 40) {
                $specs = array_slice($specs, 0, 40);
            }

            return $specs;
        }

        /**
         * Build SQL filter for selected indicators.
         *
         * @param array<int,array{source:string,id:int}> $specs
         * @return array{0:string,1:array<int,mixed>}
         */
        private static function build_indicator_filter_sql(array $specs, $alias, $fallback_source = '') {
            $alias = trim((string) $alias);
            if ($alias === '') {
                $alias = 'r';
            }

            $fallback_source = function_exists('dashd_normalize_source_key')
                ? dashd_normalize_source_key((string) $fallback_source)
                : sanitize_key((string) $fallback_source);

            $clauses = [];
            $args = [];
            foreach ($specs as $spec) {
                $id = (int) ($spec['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }

                $source = (string) ($spec['source'] ?? '');
                if ($source !== '') {
                    $clauses[] = "({$alias}.source_key=%s AND {$alias}.indicator_id=%d)";
                    $args[] = $source;
                    $args[] = $id;
                    continue;
                }

                if ($fallback_source !== '') {
                    $clauses[] = "({$alias}.source_key=%s AND {$alias}.indicator_id=%d)";
                    $args[] = $fallback_source;
                    $args[] = $id;
                    continue;
                }

                $clauses[] = "({$alias}.indicator_id=%d)";
                $args[] = $id;
            }

            if (empty($clauses)) {
                return ['', []];
            }

            return [implode(' OR ', $clauses), $args];
        }
    }
}
