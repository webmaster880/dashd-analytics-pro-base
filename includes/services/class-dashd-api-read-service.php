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
                    'period_model' => 'annual_q4_or_quarters_v1',
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
                $display_periods = self::build_display_periods($periods, 'asc');
                $period_labels = array_map(static fn($p) => (string) $p['label'], $display_periods);
                $period_index_map = [];
                $periods_meta = [];
                foreach ($display_periods as $idx => $period) {
                    $source_keys = isset($period['source_keys']) && is_array($period['source_keys'])
                        ? $period['source_keys']
                        : [(string) ($period['source_key'] ?? '')];
                    foreach ($source_keys as $source_key) {
                        $source_key = (string) $source_key;
                        if ($source_key !== '') {
                            $period_index_map[$source_key] = $idx;
                        }
                    }
                    $periods_meta[] = [
                        'label' => (string) $period['label'],
                        'type' => (string) $period['type'],
                        'year' => (int) $period['year'],
                        'quarter' => (string) $period['quarter'],
                    ];
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

                $data = [
                    'periods' => $period_labels,
                    'periods_meta' => $periods_meta,
                    'countries' => [],
                    'indicators' => [],
                    'last_sync' => $formatted_sync,
                ];
                $country_set = [];
                $truncated = [
                    'countries' => false,
                    'indicators' => false,
                ];
                foreach ($results as $row) {
                    $ind_label = function_exists('dashd_api_safe_label') ? dashd_api_safe_label($row->ind ?? '') : sanitize_text_field((string) ($row->ind ?? ''));
                    $cty_label = function_exists('dashd_api_safe_label') ? dashd_api_safe_label($row->cty ?? '') : sanitize_text_field((string) ($row->cty ?? ''));
                    if ($ind_label === '' || $cty_label === '') continue;

                    $p_key = self::period_source_key((int) $row->data_year, (string) $row->data_quarter);
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
                    $data['indicators'][$ind_label][$cty_label][$p_idx] += (float) $row->val;
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

            $current_quarters = self::resolve_period_query_quarters($key, $indicator_specs, $fy, $fq);
            $current_quarter_placeholders = implode(', ', array_fill(0, count($current_quarters), '%s'));

            $current_sql = "
                SELECT COALESCE(NULLIF(i.$col,''), i.name_en) as ind, COALESCE(NULLIF(c.$col,''), c.name_en) as cty, SUM(r.val) as val
                FROM {$wpdb->prefix}dashd_data_records r
                JOIN {$wpdb->prefix}dashd_indicators i ON r.indicator_id = i.id
                JOIN {$wpdb->prefix}dashd_countries c ON r.country_id = c.id
                WHERE r.data_year=%d AND r.data_quarter IN ({$current_quarter_placeholders})";
            $current_args = array_merge([$fy], $current_quarters);
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
            $current_sql .= " GROUP BY ind, cty, i.sort_order, i.id, c.sort_order, c.id ORDER BY i.sort_order ASC, i.id ASC, c.sort_order ASC, c.id ASC LIMIT %d";
            $current_args[] = $max_period_rows;
            $current = $wpdb->get_results($wpdb->prepare($current_sql, ...$current_args));

            $prev_year = $fy - 1;
            $previous_quarters = self::resolve_period_query_quarters($key, $indicator_specs, $prev_year, $fq);
            $previous_quarter_placeholders = implode(', ', array_fill(0, count($previous_quarters), '%s'));
            $previous_sql = "
                SELECT COALESCE(NULLIF(i.$col,''), i.name_en) as ind, COALESCE(NULLIF(c.$col,''), c.name_en) as cty, SUM(r.val) as val
                FROM {$wpdb->prefix}dashd_data_records r
                JOIN {$wpdb->prefix}dashd_indicators i ON r.indicator_id = i.id
                JOIN {$wpdb->prefix}dashd_countries c ON r.country_id = c.id
                WHERE r.data_year=%d AND r.data_quarter IN ({$previous_quarter_placeholders})";
            $previous_args = array_merge([$prev_year], $previous_quarters);
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
            $previous_sql .= " GROUP BY ind, cty, i.sort_order, i.id, c.sort_order, c.id ORDER BY i.sort_order ASC, i.id ASC, c.sort_order ASC, c.id ASC LIMIT %d";
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
                ? dashd_api_public_cache_key('periods_split', [
                    'key' => $key,
                    'indicators' => $indicator_specs,
                    'period_model' => 'annual_q4_or_quarters_v1',
                ])
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
                $periods_sql .= " AND r.source_key = %s";
                $periods_args[] = $key;
            }
            $periods_sql .= " ORDER BY r.data_year DESC, r.data_quarter DESC";
            $period_rows = $wpdb->get_results($wpdb->prepare($periods_sql, ...$periods_args));

            $year_quarters = [];
            $latest = ['year' => null, 'quarter' => null];
            foreach ($period_rows as $row) {
                $year = (string) ($row->data_year ?? '');
                $quarter = self::normalize_quarter((string) ($row->data_quarter ?? ''));
                if ($year === '' || $quarter === '') {
                    continue;
                }
                if (!isset($year_quarters[$year])) {
                    $year_quarters[$year] = [];
                }
                if (!in_array($quarter, $year_quarters[$year], true)) {
                    $year_quarters[$year][] = $quarter;
                }
            }

            foreach ($year_quarters as $year => $q_list) {
                if (in_array('Q4', $q_list, true)) {
                    $year_quarters[$year] = ['Q4'];
                    continue;
                }
                usort($q_list, static function ($a, $b) {
                    return self::quarter_rank((string) $b) <=> self::quarter_rank((string) $a);
                });
                $year_quarters[$year] = array_values($q_list);
            }

            $display_periods_desc = self::build_display_periods($period_rows, 'desc');
            if (!empty($display_periods_desc[0])) {
                $latest = [
                    'year' => (string) $display_periods_desc[0]['year'],
                    'quarter' => (string) $display_periods_desc[0]['quarter'],
                ];
            }

            $data = [
                'years' => $years,
                'quarters' => $quarters,
                'year_quarters' => $year_quarters,
                'latest' => $latest,
                'period_model' => 'annual_q4_or_quarters',
            ];

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

        /**
         * Convert raw quarter rows into chart/display periods.
         * A year with Q4 is considered completed and all available quarters are summed into one annual point.
         *
         * @param array<int,object> $period_rows
         * @param string            $direction
         * @return array<int,array<string,mixed>>
         */
        private static function build_display_periods($period_rows, $direction = 'asc') {
            $by_year = [];
            foreach ((array) $period_rows as $row) {
                $year = (int) ($row->data_year ?? 0);
                $quarter = self::normalize_quarter((string) ($row->data_quarter ?? ''));
                if ($year <= 0 || $quarter === '') {
                    continue;
                }
                if (!isset($by_year[$year])) {
                    $by_year[$year] = [];
                }
                $by_year[$year][$quarter] = true;
            }

            $years = array_keys($by_year);
            sort($years, SORT_NUMERIC);
            if ($direction === 'desc') {
                $years = array_reverse($years);
            }

            $display_periods = [];
            foreach ($years as $year) {
                $quarters = array_keys($by_year[$year]);
                usort($quarters, static function ($a, $b) use ($direction) {
                    $rank_a = self::quarter_rank((string) $a);
                    $rank_b = self::quarter_rank((string) $b);
                    return $direction === 'desc' ? ($rank_b <=> $rank_a) : ($rank_a <=> $rank_b);
                });

                if (isset($by_year[$year]['Q4'])) {
                    $source_keys = [];
                    foreach ($quarters as $quarter) {
                        $source_keys[] = self::period_source_key((int) $year, (string) $quarter);
                    }
                    $display_periods[] = [
                        'label' => (string) $year,
                        'type' => 'annual',
                        'year' => (int) $year,
                        'quarter' => 'Q4',
                        'source_key' => self::period_source_key((int) $year, 'Q4'),
                        'source_keys' => $source_keys,
                    ];
                    continue;
                }

                foreach ($quarters as $quarter) {
                    $display_periods[] = [
                        'label' => $quarter . ' ' . $year,
                        'type' => 'quarter',
                        'year' => (int) $year,
                        'quarter' => (string) $quarter,
                        'source_key' => self::period_source_key((int) $year, (string) $quarter),
                        'source_keys' => [self::period_source_key((int) $year, (string) $quarter)],
                    ];
                }
            }

            return $display_periods;
        }

        /**
         * Resolve raw quarters that should be aggregated for a requested display period.
         *
         * @param string                         $key
         * @param array<int,array{source:string,id:int}> $indicator_specs
         * @param int                            $year
         * @param string                         $quarter
         * @return array<int,string>
         */
        private static function resolve_period_query_quarters($key, array $indicator_specs, $year, $quarter) {
            global $wpdb;

            $quarter = self::normalize_quarter((string) $quarter);
            if ($quarter === '') {
                $quarter = 'Q4';
            }
            $year = (int) $year;
            if ($year <= 0 || $quarter !== 'Q4') {
                return [$quarter];
            }

            $sql = "SELECT DISTINCT r.data_quarter
                FROM {$wpdb->prefix}dashd_data_records r
                WHERE r.data_year = %d";
            $args = [$year];

            if (!empty($indicator_specs)) {
                [$indicator_sql, $indicator_args] = self::build_indicator_filter_sql($indicator_specs, 'r', $key);
                if ($indicator_sql !== '') {
                    $sql .= " AND ({$indicator_sql})";
                    $args = array_merge($args, $indicator_args);
                }
            } else {
                $sql .= " AND r.source_key = %s";
                $args[] = $key;
            }

            $rows = $wpdb->get_col($wpdb->prepare($sql, ...$args));
            $quarters = [];
            foreach ((array) $rows as $row_quarter) {
                $normalized = self::normalize_quarter((string) $row_quarter);
                if ($normalized !== '' && !in_array($normalized, $quarters, true)) {
                    $quarters[] = $normalized;
                }
            }

            if (!in_array('Q4', $quarters, true)) {
                return [$quarter];
            }

            usort($quarters, static function ($a, $b) {
                return self::quarter_rank((string) $a) <=> self::quarter_rank((string) $b);
            });

            return !empty($quarters) ? array_values($quarters) : [$quarter];
        }

        /**
         * @param int    $year
         * @param string $quarter
         * @return string
         */
        private static function period_source_key($year, $quarter) {
            $quarter = self::normalize_quarter((string) $quarter);
            return $quarter !== '' ? $quarter . ' ' . (int) $year : '';
        }

        /**
         * @param string $quarter
         * @return string
         */
        private static function normalize_quarter($quarter) {
            $quarter = strtoupper(trim((string) $quarter));
            return in_array($quarter, ['Q1', 'Q2', 'Q3', 'Q4'], true) ? $quarter : '';
        }

        /**
         * @param string $quarter
         * @return int
         */
        private static function quarter_rank($quarter) {
            $rank = ['Q1' => 1, 'Q2' => 2, 'Q3' => 3, 'Q4' => 4];
            $quarter = self::normalize_quarter((string) $quarter);
            return $rank[$quarter] ?? 0;
        }
    }
}
