<?php
/**
 * Sync Engine v10.0.7
 * ДОБАВЛЕНО: JSON парсер, POST/Headers Auth, Снапшоты (History Tracking), 
 * Калькулятор Индикаторов (Time Shift & Strict Match), Алерты об аномалиях.
 * ИСПРАВЛЕНО: Защита смещений времени (::-1Y) от математического парсера.
 */

if (!defined('ABSPATH')) exit;

if (!function_exists('dashd_sync_limit_int')) {
    function dashd_sync_limit_int($value, $default, $min = 0, $max = PHP_INT_MAX) {
        $value = is_numeric($value) ? (int) $value : (int) $default;
        if ($value < (int) $min) {
            return (int) $min;
        }
        if ($value > (int) $max) {
            return (int) $max;
        }
        return $value;
    }
}

if (!function_exists('dashd_sync_normalize_quarter')) {
    function dashd_sync_normalize_quarter($quarter, $default = 'Q1') {
        $q = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) $quarter));
        return in_array($q, ['Q1', 'Q2', 'Q3', 'Q4'], true) ? $q : $default;
    }
}

function dashd_sync_repository($manual = false) {
    global $wpdb;
    $sources = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}dashd_settings");
    $sync_date = current_time('Y-m-d');
    $log = "";
    $total_added = 0;
    $total_updated = 0;
    $anomalies = [];
    $skipped_anomalies = 0;

    $default_response_limit = (defined('MB_IN_BYTES') ? MB_IN_BYTES : 1048576) * 2;
    $http_timeout = dashd_sync_limit_int(apply_filters('dashd_sync_http_timeout', 45, $manual), 45, 5, 180);
    $response_limit_bytes = dashd_sync_limit_int(
        apply_filters('dashd_sync_response_size_limit', $default_response_limit, $manual),
        $default_response_limit,
        0,
        $default_response_limit * 10
    );
    $max_json_rows = dashd_sync_limit_int(apply_filters('dashd_sync_max_json_rows', 12000, $manual), 12000, 100, 500000);
    $max_csv_rows = dashd_sync_limit_int(apply_filters('dashd_sync_max_csv_rows', 20000, $manual), 20000, 100, 500000);
    $max_csv_columns = dashd_sync_limit_int(apply_filters('dashd_sync_max_csv_columns', 300, $manual), 300, 5, 2000);
    $snapshot_max_records = dashd_sync_limit_int(apply_filters('dashd_sync_snapshot_max_records', 5000, $manual), 5000, 0, 500000);
    $snapshot_max_bytes = dashd_sync_limit_int(
        apply_filters('dashd_sync_snapshot_max_bytes', $default_response_limit, $manual),
        $default_response_limit,
        1024,
        $default_response_limit * 10
    );
    $max_anomalies_buffer = dashd_sync_limit_int(apply_filters('dashd_sync_max_anomalies_buffer', 250, $manual), 250, 10, 5000);
    $anomaly_ratio_threshold = (float) apply_filters('dashd_sync_anomaly_ratio_threshold', 3.0, $manual);
    if (!is_finite($anomaly_ratio_threshold) || $anomaly_ratio_threshold <= 0) {
        $anomaly_ratio_threshold = 3.0;
    }
    $append_anomaly = static function ($message) use (&$anomalies, &$skipped_anomalies, $max_anomalies_buffer) {
        $message = trim((string) $message);
        if ($message === '') {
            return;
        }
        if (count($anomalies) < $max_anomalies_buffer) {
            $anomalies[] = $message;
        } else {
            $skipped_anomalies++;
        }
    };
    $period_value_cache = [];
    $cache_period_key = static function ($source_key, $indicator_id, $country_id, $year, $quarter) {
        $normalized_source = function_exists('dashd_normalize_source_key')
            ? dashd_normalize_source_key((string) $source_key)
            : sanitize_key((string) $source_key);
        return implode('|', [
            $normalized_source,
            (int) $indicator_id,
            (int) $country_id,
            (int) $year,
            dashd_sync_normalize_quarter((string) $quarter, 'Q1'),
        ]);
    };
    $get_prev_period = static function ($year, $quarter) {
        $year = (int) $year;
        $quarter = dashd_sync_normalize_quarter((string) $quarter, 'Q1');
        switch ($quarter) {
            case 'Q4':
                return ['y' => $year, 'q' => 'Q3'];
            case 'Q3':
                return ['y' => $year, 'q' => 'Q2'];
            case 'Q2':
                return ['y' => $year, 'q' => 'Q1'];
            case 'Q1':
            default:
                return ['y' => $year - 1, 'q' => 'Q4'];
        }
    };
    $get_period_value = static function ($source_key, $indicator_id, $country_id, $year, $quarter) use (&$period_value_cache, $cache_period_key, $wpdb) {
        $key = $cache_period_key($source_key, $indicator_id, $country_id, $year, $quarter);
        if (array_key_exists($key, $period_value_cache)) {
            return $period_value_cache[$key];
        }

        $val = $wpdb->get_var($wpdb->prepare(
            "SELECT val FROM {$wpdb->prefix}dashd_data_records
             WHERE source_key=%s AND indicator_id=%d AND country_id=%d AND data_year=%d AND data_quarter=%s
             LIMIT 1",
            $source_key,
            (int) $indicator_id,
            (int) $country_id,
            (int) $year,
            dashd_sync_normalize_quarter((string) $quarter, 'Q1')
        ));

        $period_value_cache[$key] = is_null($val) ? null : (float) $val;
        return $period_value_cache[$key];
    };
    $set_period_value = static function ($source_key, $indicator_id, $country_id, $year, $quarter, $value) use (&$period_value_cache, $cache_period_key) {
        $key = $cache_period_key($source_key, $indicator_id, $country_id, $year, $quarter);
        $period_value_cache[$key] = (float) $value;
    };
    $detect_nearest_period_anomaly = static function ($source_key, $indicator_id, $country_id, $year, $quarter, $current_value, $indicator_name, $country_name) use ($get_prev_period, $get_period_value, $append_anomaly, $anomaly_ratio_threshold) {
        $current = (float) $current_value;
        if ($current <= 0) {
            return;
        }

        $prev = $get_prev_period($year, $quarter);
        $prev_value = $get_period_value($source_key, $indicator_id, $country_id, $prev['y'], $prev['q']);
        if ($prev_value === null) {
            return;
        }

        $previous = (float) $prev_value;
        if ($previous <= 0) {
            return;
        }

        $ratio = abs(($current - $previous) / $previous);
        if ($ratio >= $anomaly_ratio_threshold) {
            $append_anomaly(sprintf(
                'Spike in %s (%s): %s ➔ %s (%s %d → %s %d)',
                (string) $indicator_name,
                (string) $country_name,
                $previous,
                $current,
                $prev['q'],
                (int) $prev['y'],
                dashd_sync_normalize_quarter((string) $quarter, 'Q1'),
                (int) $year
            ));
        }
    };

    if (!$sources) return;

    $dictionary_service = new DashD_Sync_Dictionary_Service();

    foreach ($sources as $src) {
        $source_key = function_exists('dashd_normalize_source_key')
            ? dashd_normalize_source_key((string) ($src->source_key ?? ''))
            : sanitize_key((string) ($src->source_key ?? ''));
        if ($source_key === '') {
            $log .= "❌ [unknown]: Invalid source key\n";
            continue;
        }

        $source_url = function_exists('dashd_sanitize_source_url')
            ? dashd_sanitize_source_url((string) ($src->source_url ?? ''))
            : esc_url_raw((string) ($src->source_url ?? ''), ['http', 'https']);
        if ($source_url === '') {
            $log .= "❌ {$source_key}: Invalid or unsafe source URL\n";
            continue;
        }

        $source_type = function_exists('dashd_normalize_source_type')
            ? dashd_normalize_source_type((string) ($src->source_type ?? 'csv'))
            : 'csv';

        // =========================================================
        // 1. СОЗДАНИЕ СНАПШОТА (History Tracking)
        // Делаем резервную копию сырых данных ПЕРЕД синхронизацией
        // =========================================================
        $record_store = new DashD_Sync_Source_Record_Store($source_key, $sync_date);
        $existing_count = $record_store->get_existing_count();

        if ($existing_count > 0) {
            $snapshot = $record_store->prepare_snapshot($snapshot_max_records, $snapshot_max_bytes);
            $snapshot_notice = (string) ($snapshot['notice'] ?? '');
            if ($snapshot_notice === 'size_limit') {
                $log .= "⚠️ {$source_key}: Snapshot stored as summary (size limit)\n";
            } elseif ($snapshot_notice === 'record_limit') {
                $log .= "⚠️ {$source_key}: Snapshot stored as summary (record limit)\n";
            }

            $record_store->save_snapshot(
                (int) ($snapshot['records_count'] ?? $existing_count),
                (string) ($snapshot['dump'] ?? '')
            );
        }

        // =========================================================
        // 2. АВТОРИЗАЦИЯ И МЕТОД ЗАПРОСА (POST/GET/Headers)
        // =========================================================
        $api_method = function_exists('dashd_normalize_http_method')
            ? dashd_normalize_http_method((string) ($src->api_method ?? 'GET'))
            : 'GET';
        $api_headers = ['Accept' => 'application/json'];
        
        if (!empty($src->api_headers)) {
            $safe_headers_json = function_exists('dashd_sanitize_source_headers')
                ? dashd_sanitize_source_headers((string) $src->api_headers)
                : '';
            $custom_headers = json_decode($safe_headers_json, true);
            if (is_array($custom_headers)) {
                $api_headers = array_merge($api_headers, $custom_headers);
            }
        }

        $sslverify = (bool) apply_filters('dashd_sync_sslverify', true, $src);

        $args = [
            'method'    => $api_method,
            'timeout'   => $http_timeout,
            'sslverify' => $sslverify,
            'redirection' => 3,
            'headers'   => $api_headers
        ];
        if ($response_limit_bytes > 0) {
            $args['limit_response_size'] = $response_limit_bytes;
        }
        
        // Default security posture: use safe HTTP API.
        // If private source URLs are explicitly enabled via filter, allow regular remote request.
        $allow_private_source = (bool) apply_filters('dashd_allow_private_source_urls', false, $source_url);
        if ($allow_private_source) {
            $res = wp_remote_request($source_url, $args);
        } else {
            $args['reject_unsafe_urls'] = true;
            $res = wp_safe_remote_request($source_url, $args);
        }
        
        if (is_wp_error($res)) {
            $log .= "❌ {$source_key}: Connection error (" . $res->get_error_message() . ")\n";
            continue;
        }

        $response_code = (int) wp_remote_retrieve_response_code($res);
        if ($response_code < 200 || $response_code >= 400) {
            $log .= "❌ {$source_key}: HTTP {$response_code}\n";
            continue;
        }

        $raw_body = wp_remote_retrieve_body($res);
        if (!is_string($raw_body)) {
            $raw_body = '';
        }
        if ($response_limit_bytes > 0 && strlen($raw_body) >= $response_limit_bytes) {
            $log .= "❌ {$source_key}: Response too large or truncated (limit {$response_limit_bytes} bytes)\n";
            continue;
        }

        $body = trim(preg_replace('/^[\xEF\xBB\xBF\xFE\xFF]+/', '', $raw_body));
        if ($body === '') {
            $log .= "❌ {$source_key}: Empty response body\n";
            continue;
        }

        // =========================================================
        // ВЕТКА 1: ОБРАБОТКА JSON (REST API)
        // =========================================================
        if ($source_type === 'json') {
            $data = json_decode($body, true, 512, JSON_BIGINT_AS_STRING);
            if (!is_array($data)) {
                $log .= "❌ {$source_key}: Invalid JSON response\n";
                continue;
            }

            // Support single-object JSON payload by wrapping it into an array.
            if (isset($data['indicator']) && isset($data['country'])) {
                $data = [$data];
            }

            if (array_values($data) !== $data) {
                $log .= "❌ {$source_key}: Unsupported JSON structure (expected list)\n";
                continue;
            }

            if (count($data) > $max_json_rows) {
                $log .= "❌ {$source_key}: JSON payload too large (" . count($data) . " rows > {$max_json_rows})\n";
                continue;
            }

            foreach ($data as $row) {
                $ind_name = sanitize_text_field($row['indicator'] ?? '');
                $cty_name = sanitize_text_field($row['country'] ?? '');
                $val      = (float)str_replace([' ', ','], ['', '.'], $row['value'] ?? 0);
                $year     = (int)preg_replace('/[^0-9]/', '', $row['year'] ?? 0);
                $quarter  = dashd_sync_normalize_quarter($row['quarter'] ?? 'Q1', 'Q1');

                if (empty($ind_name) || empty($cty_name) || !$year) continue;
                if ($year < 1900 || $year > 2200) continue;

                $iid = $dictionary_service->get_indicator_id($ind_name);
                $cid = $dictionary_service->get_country_id($cty_name);
                if ($iid <= 0 || $cid <= 0) continue;

                $detect_nearest_period_anomaly($source_key, $iid, $cid, $year, $quarter, $val, $ind_name, $cty_name);

                $op = $record_store->upsert_record($iid, $cid, $year, $quarter, $val);
                if ($op === 'updated') {
                    $total_updated++;
                } elseif ($op === 'inserted') {
                    $total_added++;
                }
                $set_period_value($source_key, $iid, $cid, $year, $quarter, $val);
            }
            $log .= "✅ {$source_key}: JSON OK\n";

        } 
        // =========================================================
        // ВЕТКА 2: ОБРАБОТКА CSV (Google Sheets)
        // =========================================================
        else {
            $lines = explode("\n", str_replace("\r", "", $body));
            if (count($lines) < 4) {
                $log .= "❌ {$source_key}: CSV has insufficient rows\n";
                continue;
            }

            $row_cty = str_getcsv(array_shift($lines));
            $row_yr  = str_getcsv(array_shift($lines));
            $row_qr  = str_getcsv(array_shift($lines));
            if (!is_array($row_cty) || !is_array($row_yr) || !is_array($row_qr)) {
                $log .= "❌ {$source_key}: CSV header rows are invalid\n";
                continue;
            }

            if (count($lines) > $max_csv_rows) {
                $log .= "⚠️ {$source_key}: CSV truncated to {$max_csv_rows} rows for safety\n";
                $lines = array_slice($lines, 0, $max_csv_rows);
            }

            if (count($row_cty) > ($max_csv_columns + 1)) {
                $log .= "⚠️ {$source_key}: CSV columns truncated to {$max_csv_columns}\n";
                $row_cty = array_slice($row_cty, 0, $max_csv_columns + 1);
                $row_yr = array_slice($row_yr, 0, $max_csv_columns + 1);
                $row_qr = array_slice($row_qr, 0, $max_csv_columns + 1);
            }

            $column_map = [];
            for ($i = 1; $i < count($row_cty); $i++) {
                $c_name = sanitize_text_field($row_cty[$i] ?? '');
                $c_name = trim(wp_strip_all_tags($c_name));
                if (empty($c_name)) continue;

                $cid = $dictionary_service->get_country_id($c_name);
                $y = (int)preg_replace('/[^0-9]/', '', $row_yr[$i]);
                if ($y < 1900 || $y > 2200) continue;
                $q = dashd_sync_normalize_quarter((string)($row_qr[$i] ?? 'Q1'), 'Q1');
                $cid = (int) $cid;
                if ($cid <= 0) continue;
                $column_map[$i] = ['cid' => $cid, 'cty' => $c_name, 'y' => $y, 'q' => $q];
            }

            foreach ($lines as $line) {
                if (trim((string) $line) === '') {
                    continue;
                }
                $vals = str_getcsv($line);
                if (!is_array($vals)) {
                    continue;
                }
                $ind_name = sanitize_text_field($vals[0] ?? '');
                $ind_name = trim(wp_strip_all_tags($ind_name));
                if (empty($ind_name)) continue;

                $iid = $dictionary_service->get_indicator_id($ind_name);
                if ($iid <= 0) continue;

                foreach ($column_map as $idx => $meta) {
                    if (!isset($vals[$idx])) {
                        continue;
                    }
                    $val = (float)str_replace([' ', ','], ['', '.'], $vals[$idx] ?? 0);
                    $detect_nearest_period_anomaly($source_key, $iid, $meta['cid'], $meta['y'], $meta['q'], $val, $ind_name, (string) ($meta['cty'] ?? ('Country ID ' . (int) $meta['cid'])));
                    
                    $op = $record_store->upsert_record($iid, $meta['cid'], $meta['y'], $meta['q'], $val);
                    if ($op === 'updated') {
                        $total_updated++;
                    } elseif ($op === 'inserted') {
                        $total_added++;
                    }
                    $set_period_value($source_key, $iid, $meta['cid'], $meta['y'], $meta['q'], $val);
                }
            }
            $log .= "✅ {$source_key}: CSV OK\n";
        }
    }

    // =========================================================
    // ГЛОБАЛЬНЫЙ РАСЧЕТ ИНДИКАТОРОВ (Машина времени)
    // Запускаем один раз после того, как все таблицы обновлены
    // =========================================================
    dashd_process_calculated_indicators($sync_date);

    if (!empty($anomalies)) {
        $log .= "\n⚠️ Detected Anomalies (>300%):\n" . implode("\n", array_slice($anomalies, 0, 5));
        if (count($anomalies) > 5) $log .= "\n... and " . (count($anomalies) - 5) . " more.";
        if ($skipped_anomalies > 0) $log .= "\n... and {$skipped_anomalies} more anomalies were not stored in memory.";
        if (function_exists('dashd_send_anomaly_alert')) dashd_send_anomaly_alert($anomalies);
    }

    $status = 'Success';
    if (strpos($log, '❌') !== false) $status = 'Error';
    elseif ($total_added == 0 && $total_updated == 0) $status = 'No Changes';

    $logs = get_option('dashd_sync_logs', []);
    array_unshift($logs, ['time' => current_time('mysql'), 'status' => $status, 'added' => $total_added, 'updated' => $total_updated, 'log' => $log]);
    update_option('dashd_sync_logs', array_slice($logs, 0, 15)); 
    update_option('dashd_last_global_sync', current_time('mysql'));

    dashd_clear_all_caches();
    dashd_notify("Sync Results:\nNew: $total_added\nUpdated: $total_updated\n\n$log");
}

// =========================================================
// ДВИЖОК РАСЧЕТНЫХ ИНДИКАТОРОВ (v5 - Time Shift & Strict Match)
// Формат: IndID : CountryID : Offset (e.g. 5::-1Y или 5:2:-1Q)
// =========================================================
function dashd_process_calculated_indicators($sync_date) {
    global $wpdb;
    $sync_date_raw = (string) $sync_date;
    $sync_date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $sync_date_raw) === 1
        ? $sync_date_raw
        : current_time('Y-m-d');

    $max_calc_indicators = dashd_sync_limit_int(
        apply_filters('dashd_calc_max_indicators', 200),
        200,
        1,
        5000
    );
    $max_calc_periods = dashd_sync_limit_int(
        apply_filters('dashd_calc_max_periods', 20000),
        20000,
        100,
        500000
    );
    $max_target_sources = dashd_sync_limit_int(
        apply_filters('dashd_calc_max_target_sources', 50),
        50,
        1,
        1000
    );

    // Получаем все активные таблицы
    $active_sources_raw = $wpdb->get_col("SELECT source_key FROM {$wpdb->prefix}dashd_settings");
    $active_source_map = [];
    if (is_array($active_sources_raw)) {
        foreach ($active_sources_raw as $source_key) {
            $normalized_source = function_exists('dashd_normalize_source_key')
                ? dashd_normalize_source_key((string) $source_key)
                : sanitize_key((string) $source_key);
            if ($normalized_source !== '') {
                $active_source_map[$normalized_source] = true;
            }
        }
    }
    if (empty($active_source_map)) {
        $active_source_map['calculated_data'] = true;
    }
    $active_sources = array_keys($active_source_map);
    if (count($active_sources) > $max_target_sources) {
        $active_sources = array_slice($active_sources, 0, $max_target_sources);
    }

    $calc_inds = $wpdb->get_results($wpdb->prepare(
        "SELECT id, formula, target_source
         FROM {$wpdb->prefix}dashd_indicators
         WHERE is_calculated = 1
         ORDER BY id ASC
         LIMIT %d",
        $max_calc_indicators
    ));
    if (empty($calc_inds)) return;

    // Берем абсолютно все комбинации Год+Квартал+Страна, которые есть в БД
    $periods = $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT data_year, data_quarter, country_id
         FROM {$wpdb->prefix}dashd_data_records
         ORDER BY data_year DESC, data_quarter DESC, country_id ASC
         LIMIT %d",
        $max_calc_periods
    ));
    if (empty($periods)) {
        return;
    }

    $period_country_map = [];
    foreach ($periods as $p) {
        $cid = isset($p->country_id) ? (int) $p->country_id : 0;
        if ($cid > 0) {
            $period_country_map[$cid] = true;
        }
    }
    $period_country_ids = array_keys($period_country_map);
    if (empty($period_country_ids)) {
        return;
    }

    foreach ($calc_inds as $ci) {
        $formula_raw = function_exists('dashd_normalize_calc_formula')
            ? dashd_normalize_calc_formula($ci->formula)
            : trim((string) $ci->formula);
        if ($formula_raw === '') {
            continue;
        }
        
        // Определяем, куда сохранять
        $target_source_raw = function_exists('dashd_normalize_source_key')
            ? dashd_normalize_source_key((string) ($ci->target_source ?? 'all'))
            : sanitize_key((string) ($ci->target_source ?? 'all'));
        if ($target_source_raw === '' || $target_source_raw === 'all') {
            $target_sources = $active_sources;
        } elseif (isset($active_source_map[$target_source_raw])) {
            $target_sources = [$target_source_raw];
        } else {
            $target_sources = $active_sources;
        }

        // 1. ЗАЩИТА СМЕЩЕНИЙ ВРЕМЕНИ
        // Прячем знаки + и - внутри смещений (например, ::-1Y), чтобы парсер не принял их за математику
        $formula_protected = preg_replace('/:\s*-/', ':~minus~', (string) $formula_raw);
        $formula_protected = preg_replace('/:\s*\+/', ':~plus~', (string) $formula_protected);

        $operator = 'none';
        if (strpos($formula_protected, '+') !== false) $operator = '+';
        elseif (strpos($formula_protected, '-') !== false) $operator = '-';
        elseif (strpos($formula_protected, '*') !== false) $operator = '*';
        elseif (strpos($formula_protected, '/') !== false) $operator = '/';

        // 2. РАЗБИВАЕМ ФОРМУЛУ И ВОЗВРАЩАЕМ ЗНАКИ НА МЕСТО
        if ($operator !== 'none') {
            $parts = explode($operator, $formula_protected, 2);
            $parts[0] = str_replace([':~minus~', ':~plus~'], [':-', ':+'], $parts[0]);
            $parts[1] = str_replace([':~minus~', ':~plus~'], [':-', ':+'], $parts[1]);
        } else {
            $parts = [str_replace([':~minus~', ':~plus~'], [':-', ':+'], $formula_protected)];
        }

        // 3. УМНЫЙ ПАРСЕР ОПЕРАНДОВ (Машина времени)
        $parse_operand = function($op_string, $default_cty, $current_y, $current_q) {
            $e = explode(':', trim($op_string));
            $ind = (int) ($e[0] ?? 0);
            $cty = (isset($e[1]) && $e[1] !== '') ? (int) $e[1] : (int) $default_cty;
            if ($ind <= 0 || $cty <= 0) {
                return null;
            }
            
            $target_y = (int) $current_y;
            $target_q = dashd_sync_normalize_quarter((string) $current_q, 'Q1');

            // Сдвиг по времени
            if (isset($e[2]) && $e[2] !== '') {
                $offset = strtoupper(trim($e[2]));
                if (strpos($offset, 'Y') !== false) {
                    $target_y += (int) str_replace('Y', '', $offset); // Сдвиг по годам
                } elseif (strpos($offset, 'Q') !== false) {
                    $q_offset = (int) str_replace('Q', '', $offset); // Сдвиг по кварталам
                    $q_num = (int) str_replace('Q', '', $target_q);
                    $total_q = $target_y * 4 + ($q_num - 1) + $q_offset;
                    $target_y = (int) floor($total_q / 4);
                    $new_q_num = (($total_q % 4) + 4) % 4 + 1;
                    $target_q = 'Q' . $new_q_num;
                }
            }

            if ($target_y < 1900 || $target_y > 2200) {
                return null;
            }
            $target_q = dashd_sync_normalize_quarter($target_q, '');
            if ($target_q === '') {
                return null;
            }

            return ['ind' => $ind, 'cty' => $cty, 'y' => $target_y, 'q' => $target_q];
        };

        $extract_operand_requirements = static function ($op_string, array $all_period_country_ids) {
            $e = explode(':', trim((string) $op_string));
            $ind = (int) ($e[0] ?? 0);
            if ($ind <= 0) {
                return null;
            }

            if (isset($e[1]) && $e[1] !== '') {
                $cty = (int) $e[1];
                if ($cty <= 0) {
                    return null;
                }
                return ['ind' => $ind, 'countries' => [$cty]];
            }

            return ['ind' => $ind, 'countries' => $all_period_country_ids];
        };

        $required_indicator_map = [];
        $required_country_map = [];
        foreach ($parts as $part) {
            $requirements = $extract_operand_requirements($part, $period_country_ids);
            if (!is_array($requirements)) {
                continue 2;
            }

            $required_indicator_map[(int) $requirements['ind']] = true;
            foreach ((array) $requirements['countries'] as $country_id) {
                $country_id = (int) $country_id;
                if ($country_id > 0) {
                    $required_country_map[$country_id] = true;
                }
            }
        }

        $required_indicators = array_keys($required_indicator_map);
        $required_countries = array_keys($required_country_map);
        if (empty($required_indicators) || empty($required_countries)) {
            continue;
        }

        $ind_placeholders = implode(',', array_fill(0, count($required_indicators), '%d'));
        $cty_placeholders = implode(',', array_fill(0, count($required_countries), '%d'));
        $value_args = array_merge($required_indicators, $required_countries);

        $values_sql = "SELECT indicator_id, country_id, data_year, data_quarter, SUM(val) AS val
                       FROM {$wpdb->prefix}dashd_data_records
                       WHERE indicator_id IN ({$ind_placeholders})
                         AND country_id IN ({$cty_placeholders})
                       GROUP BY indicator_id, country_id, data_year, data_quarter";
        $value_rows = $wpdb->get_results($wpdb->prepare($values_sql, $value_args));

        $value_map = [];
        if (is_array($value_rows)) {
            foreach ($value_rows as $row) {
                $map_key = implode('|', [
                    (int) ($row->indicator_id ?? 0),
                    (int) ($row->country_id ?? 0),
                    (int) ($row->data_year ?? 0),
                    dashd_sync_normalize_quarter((string) ($row->data_quarter ?? 'Q1'), 'Q1'),
                ]);
                $value_map[$map_key] = (float) ($row->val ?? 0);
            }
        }

        $get_formula_value = static function ($indicator_id, $country_id, $year, $quarter) use (&$value_map) {
            $map_key = implode('|', [
                (int) $indicator_id,
                (int) $country_id,
                (int) $year,
                dashd_sync_normalize_quarter((string) $quarter, 'Q1'),
            ]);
            return array_key_exists($map_key, $value_map) ? (float) $value_map[$map_key] : 0.0;
        };

        $existing_map = [];
        if (!empty($target_sources)) {
            $src_placeholders = implode(',', array_fill(0, count($target_sources), '%s'));
            $existing_sql = "SELECT id, source_key, country_id, data_year, data_quarter, val
                             FROM {$wpdb->prefix}dashd_data_records
                             WHERE indicator_id = %d
                               AND source_key IN ({$src_placeholders})";
            $existing_args = array_merge([(int) $ci->id], $target_sources);
            $existing_rows = $wpdb->get_results($wpdb->prepare($existing_sql, $existing_args));

            if (is_array($existing_rows)) {
                foreach ($existing_rows as $erow) {
                    $row_key = implode('|', [
                        (string) ($erow->source_key ?? ''),
                        (int) ($erow->country_id ?? 0),
                        (int) ($erow->data_year ?? 0),
                        dashd_sync_normalize_quarter((string) ($erow->data_quarter ?? 'Q1'), 'Q1'),
                    ]);
                    $existing_map[$row_key] = [
                        'id' => (int) ($erow->id ?? 0),
                        'val' => (float) ($erow->val ?? 0),
                    ];
                }
            }
        }

        // Запускаем перебор по КАЖДОМУ кварталу и году
        foreach ($periods as $p) {
            $opA = $parse_operand($parts[0], $p->country_id, $p->data_year, $p->data_quarter);
            if (!is_array($opA)) {
                continue;
            }
            
            // Получаем данные строго за вычисленный период из in-memory карты.
            $val_A = $get_formula_value($opA['ind'], $opA['cty'], $opA['y'], $opA['q']);

            $result_val = $val_A;

            if ($operator !== 'none' && count($parts) == 2) {
                $opB = $parse_operand($parts[1], $p->country_id, $p->data_year, $p->data_quarter);
                if (!is_array($opB)) {
                    continue;
                }
                $val_B = $get_formula_value($opB['ind'], $opB['cty'], $opB['y'], $opB['q']);

                switch ($operator) {
                    case '+': $result_val = $val_A + $val_B; break;
                    case '-': $result_val = $val_A - $val_B; break;
                    case '*': $result_val = $val_A * $val_B; break;
                    case '/': $result_val = ($val_B != 0) ? ($val_A / $val_B) : 0; break;
                }
            }

            // Записываем результат В ТОТ ЖЕ квартал и год (куда указывает текущий цикл $p)
            foreach ($target_sources as $t_source) {
                $row_key = implode('|', [
                    (string) $t_source,
                    (int) $p->country_id,
                    (int) $p->data_year,
                    dashd_sync_normalize_quarter((string) $p->data_quarter, 'Q1'),
                ]);

                if (isset($existing_map[$row_key]) && (int) $existing_map[$row_key]['id'] > 0) {
                    $existing_id = (int) $existing_map[$row_key]['id'];
                    $existing_val = (float) $existing_map[$row_key]['val'];
                    if (abs($existing_val - (float) $result_val) < 0.0000001) {
                        continue;
                    }
                    $wpdb->update("{$wpdb->prefix}dashd_data_records", ['val' => $result_val, 'record_date' => $sync_date], ['id' => $existing_id]);
                    $existing_map[$row_key]['val'] = (float) $result_val;
                } else {
                    $wpdb->insert("{$wpdb->prefix}dashd_data_records", [
                        'source_key' => $t_source, 'indicator_id' => $ci->id, 'country_id' => $p->country_id,
                        'val' => $result_val, 'data_year' => $p->data_year, 'data_quarter' => $p->data_quarter, 'record_date' => $sync_date
                    ]);
                    $insert_id = (int) $wpdb->insert_id;
                    if ($insert_id > 0) {
                        $existing_map[$row_key] = ['id' => $insert_id, 'val' => (float) $result_val];
                    }
                }
            }
        }
    }
}
