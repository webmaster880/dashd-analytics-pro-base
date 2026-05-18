<?php
/**
 * Analytics API v10.0.9
 * Поддержка YoY для реляционной структуры + Gated Content с антиспамом.
 */

if (!defined('ABSPATH')) exit;

add_action('wp_ajax_get_dashd_modern_data', 'dashd_api_get_modern_data');
add_action('wp_ajax_nopriv_get_dashd_modern_data', 'dashd_api_get_modern_data');
add_action('wp_ajax_get_dashd_periods_split', 'dashd_api_get_periods_split');
add_action('wp_ajax_nopriv_get_dashd_periods_split', 'dashd_api_get_periods_split');
add_action('wp_ajax_dashd_capture_lead', 'dashd_handle_capture_lead');
add_action('wp_ajax_nopriv_dashd_capture_lead', 'dashd_handle_capture_lead');

function dashd_api_require_http_method($expected = 'GET') {
    $expected = strtoupper(trim((string) $expected));
    if (!in_array($expected, ['GET', 'POST'], true)) {
        $expected = 'GET';
    }

    if (function_exists('dashd_enforce_http_method')) {
        dashd_enforce_http_method($expected, true);
        return;
    }

    $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';
    if ($method !== $expected) {
        wp_send_json_error(['msg' => __('Invalid request method.', 'dashd-analytics-pro')], 405);
    }
}

function dashd_api_safe_label($value) {
    $label = wp_strip_all_tags((string) $value);
    $label = sanitize_text_field($label);
    return trim($label);
}

function dashd_api_limit_int($value, $default, $min = 0, $max = PHP_INT_MAX) {
    $value = is_numeric($value) ? (int) $value : (int) $default;
    if ($value < (int) $min) {
        return (int) $min;
    }
    if ($value > (int) $max) {
        return (int) $max;
    }
    return $value;
}

function dashd_api_get_client_ip() {
    $is_public_ip = static function($ip) {
        $ip = trim((string) $ip);
        if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        if (function_exists('dashd_is_public_ip')) {
            return (bool) dashd_is_public_ip($ip);
        }
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    };

    $remote = !empty($_SERVER['REMOTE_ADDR']) ? trim((string) $_SERVER['REMOTE_ADDR']) : '';
    $remote_valid = (bool) filter_var($remote, FILTER_VALIDATE_IP);
    if (!$remote_valid) {
        $remote = '0.0.0.0';
    }

    // Security-first default: do not trust user-controllable forwarded headers.
    // If site runs behind a trusted reverse proxy/CDN, enable with filter:
    // add_filter('dashd_trust_forwarded_ip_headers', '__return_true');
    $trust_forwarded = (bool) apply_filters('dashd_trust_forwarded_ip_headers', false, $remote);
    if (!$trust_forwarded) {
        return $remote;
    }

    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $cf_ip = trim((string) $_SERVER['HTTP_CF_CONNECTING_IP']);
        if ($is_public_ip($cf_ip)) {
            return $cf_ip;
        }
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
        foreach ($parts as $part) {
            $ip = trim((string) $part);
            if ($is_public_ip($ip)) {
                return $ip;
            }
        }
    }

    return $remote;
}

function dashd_api_rate_limit_exceeded($key, $limit, $window_seconds) {
    $bucket = get_transient($key);
    if (!is_array($bucket) || !isset($bucket['count'])) {
        $bucket = ['count' => 0];
    }

    $bucket['count'] = (int) $bucket['count'] + 1;
    set_transient($key, $bucket, (int) $window_seconds);

    return $bucket['count'] > (int) $limit;
}

function dashd_api_public_rate_limit_check($bucket = 'public') {
    $bucket = sanitize_key((string) $bucket);
    if ($bucket === '') {
        $bucket = 'public';
    }

    $limit = (int) apply_filters('dashd_api_public_rate_limit_per_window', 120, $bucket);
    $window = (int) apply_filters('dashd_api_public_rate_limit_window_seconds', MINUTE_IN_SECONDS, $bucket);

    if ($limit <= 0 || $window <= 0) {
        return true;
    }

    $client_ip = dashd_api_get_client_ip();
    $rl_key = 'dashd_pub_rl_' . $bucket . '_' . md5($client_ip);

    return !dashd_api_rate_limit_exceeded($rl_key, $limit, $window);
}

function dashd_api_public_cache_ttl($bucket = 'public') {
    $bucket = sanitize_key((string) $bucket);
    if ($bucket === '') {
        $bucket = 'public';
    }

    $ttl = (int) apply_filters('dashd_api_public_cache_ttl', 60, $bucket);
    return max(0, $ttl);
}

function dashd_api_public_cache_key($bucket, array $args = []) {
    $bucket = sanitize_key((string) $bucket);
    if ($bucket === '') {
        $bucket = 'public';
    }

    $cache_ver = (string) get_option('dashd_cache_ver', '0');
    $payload = wp_json_encode([$cache_ver, $args], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($payload) || $payload === '') {
        $payload = serialize([$cache_ver, $args]);
    }

    return 'dashd_api_' . $bucket . '_' . md5($payload);
}

function dashd_api_is_valid_email_domain($domain) {
    $domain = strtolower(trim((string) $domain));
    if ($domain === '' || strlen($domain) > 253) {
        return false;
    }

    // Ignore root-dot style.
    $domain = rtrim($domain, '.');
    if ($domain === '') {
        return false;
    }

    // Normalize IDN to ASCII when intl extension is available.
    if (function_exists('idn_to_ascii') && preg_match('/[^\x20-\x7E]/', $domain)) {
        $ascii = @idn_to_ascii($domain);
        if (is_string($ascii) && $ascii !== '') {
            $domain = strtolower($ascii);
        } else {
            return false;
        }
    }

    if (strpos($domain, '.') === false) {
        return false;
    }

    // Fast syntax-only validation to avoid blocking DNS lookups in public AJAX.
    // Accepts standard ASCII host labels (including punycode TLD labels).
    if (!preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])$/', $domain)) {
        return false;
    }

    $labels = explode('.', $domain);
    $tld = (string) end($labels);
    if (strlen($tld) < 2 || strlen($tld) > 63) {
        return false;
    }
    // TLD must not be numeric-only.
    if (preg_match('/^[0-9]+$/', $tld) === 1) {
        return false;
    }

    return true;
}

function dashd_api_get_allowed_source_map() {
    global $wpdb;
    static $allowed = null;

    if (is_array($allowed)) {
        return $allowed;
    }

    $cache_ver = (string) get_option('dashd_cache_ver', '0');
    $cache_key = 'dashd_api_allowed_sources_' . md5($cache_ver);
    $cached = get_transient($cache_key);
    if (is_array($cached)) {
        $allowed = $cached;
        return $allowed;
    }

    $allowed = [];

    $settings_keys = $wpdb->get_col("SELECT source_key FROM {$wpdb->prefix}dashd_settings");
    if (is_array($settings_keys)) {
        foreach ($settings_keys as $key) {
            $normalized = function_exists('dashd_normalize_source_key')
                ? dashd_normalize_source_key((string) $key)
                : sanitize_key((string) $key);
            if ($normalized !== '') {
                $allowed[$normalized] = true;
            }
        }
    }

    // Backward compatibility: if source has data records, treat it as known.
    $record_keys = $wpdb->get_col("SELECT DISTINCT source_key FROM {$wpdb->prefix}dashd_data_records");
    if (is_array($record_keys)) {
        foreach ($record_keys as $key) {
            $normalized = function_exists('dashd_normalize_source_key')
                ? dashd_normalize_source_key((string) $key)
                : sanitize_key((string) $key);
            if ($normalized !== '') {
                $allowed[$normalized] = true;
            }
        }
    }

    set_transient($cache_key, $allowed, 5 * MINUTE_IN_SECONDS);
    return $allowed;
}

function dashd_api_is_allowed_source($source) {
    $source = function_exists('dashd_normalize_source_key')
        ? dashd_normalize_source_key((string) $source)
        : sanitize_key((string) $source);
    if ($source === '') {
        return false;
    }

    // Escape hatch for custom setups.
    $allow_unknown = (bool) apply_filters('dashd_api_allow_unknown_source', false, $source);
    if ($allow_unknown) {
        return true;
    }

    $allowed = dashd_api_get_allowed_source_map();
    return isset($allowed[$source]);
}

function dashd_api_get_modern_data() {
    if (function_exists('dashd_api_require_http_method')) {
        dashd_api_require_http_method('GET');
    }

    if (!class_exists('DashD_Api_Read_Service')) {
        wp_send_json_error(['msg' => 'Read service is unavailable.'], 500);
    }

    $query = isset($_GET) && is_array($_GET) ? wp_unslash($_GET) : [];
    $result = DashD_Api_Read_Service::get_modern_data($query);
    if (is_wp_error($result)) {
        $error_data = $result->get_error_data();
        $status = (int) (is_array($error_data) ? ($error_data['status'] ?? 0) : 0);
        if ($status <= 0) {
            $status = 400;
        }
        wp_send_json_error(['msg' => $result->get_error_message()], $status);
    }

    wp_send_json_success($result);
}

function dashd_map_yoy_results($results) {
    $map = [];
    if (!$results) return $map;
    foreach ($results as $row) {
        $ind_label = dashd_api_safe_label($row->ind ?? '');
        $cty_label = dashd_api_safe_label($row->cty ?? '');
        if ($ind_label === '' || $cty_label === '') continue;
        if (!isset($map[$ind_label])) $map[$ind_label] = [];
        $map[$ind_label][$cty_label] = (float)$row->val;
    }
    return $map;
}

function dashd_api_get_periods_split() {
    if (function_exists('dashd_api_require_http_method')) {
        dashd_api_require_http_method('GET');
    }

    if (!class_exists('DashD_Api_Read_Service')) {
        wp_send_json_error(['msg' => 'Read service is unavailable.'], 500);
    }

    $query = isset($_GET) && is_array($_GET) ? wp_unslash($_GET) : [];
    $result = DashD_Api_Read_Service::get_periods_split($query);
    if (is_wp_error($result)) {
        $error_data = $result->get_error_data();
        $status = (int) (is_array($error_data) ? ($error_data['status'] ?? 0) : 0);
        if ($status <= 0) {
            $status = 400;
        }
        wp_send_json_error(['msg' => $result->get_error_message()], $status);
    }

    wp_send_json_success($result);
}

function dashd_handle_capture_lead() {
    if (function_exists('dashd_api_require_http_method')) {
        dashd_api_require_http_method('POST');
    }

    if (!class_exists('DashD_Lead_Capture_Service')) {
        wp_send_json_error(['msg' => 'Lead service is unavailable.'], 500);
    }

    $post = isset($_POST) && is_array($_POST) ? wp_unslash($_POST) : [];
    $input = DashD_Lead_Capture_Service::sanitize_input($post);
    $valid = DashD_Lead_Capture_Service::validate($input);
    if (is_wp_error($valid)) {
        $error_data = $valid->get_error_data();
        $status = (int) (is_array($error_data) ? ($error_data['status'] ?? 0) : 0);
        if ($status <= 0) {
            $status = 400;
        }
        wp_send_json_error(['msg' => $valid->get_error_message()], $status);
    }

    $result = DashD_Lead_Capture_Service::capture($input);
    if (is_wp_error($result)) {
        $error_data = $result->get_error_data();
        $status = (int) (is_array($error_data) ? ($error_data['status'] ?? 0) : 0);
        if ($status <= 0) {
            $status = 500;
        }
        wp_send_json_error(['msg' => $result->get_error_message()], $status);
    }

    wp_send_json_success();
}
