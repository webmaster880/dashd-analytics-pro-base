<?php
/**
 * Plugin Name: DashD Analytics Pro Engine
 * Description: Реляционная система. Добавлена поддержка локализации (.mo/.po файлов).
 * Version: 11.7.22
 * Text Domain: dashd-analytics-pro
 * Domain Path: 
 * Author: Yury Vdovychenko
 * Author URI: https://toyidea.info/
 * Text Domain: impact-dashboard
 * Requires at least: 6.2
 * Requires PHP: 8.0
 *
 * @package DashD_Analytics_Pro
 */

if (!defined('ABSPATH')) exit;

define('DASHD_VERSION', '11.7.22');
define('DASHD_DB_SCHEMA_VERSION', '11.0.6');
define('DASHD_PATH', plugin_dir_path(__FILE__));
define('DASHD_URL', plugin_dir_url(__FILE__));

// Загрузка локализации
add_action('plugins_loaded', 'dashd_load_textdomain');
function dashd_load_textdomain() {
    load_plugin_textdomain('dashd-analytics-pro', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

if (!function_exists('dashd_is_public_ip')) {
    function dashd_is_public_ip($ip) {
        if (!is_string($ip) || $ip === '') {
            return false;
        }
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }
}

if (!function_exists('dashd_resolve_host_ips')) {
    /**
     * Resolve host to IPv4/IPv6 list (A and AAAA records).
     *
     * @return string[]
     */
    function dashd_resolve_host_ips($host) {
        $host = trim((string) $host);
        if ($host === '') {
            return [];
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ips = [];

        if (function_exists('dns_get_record')) {
            $a_records = @dns_get_record($host, DNS_A);
            if (is_array($a_records)) {
                foreach ($a_records as $record) {
                    if (!empty($record['ip']) && filter_var($record['ip'], FILTER_VALIDATE_IP)) {
                        $ips[] = (string) $record['ip'];
                    }
                }
            }

            if (defined('DNS_AAAA')) {
                $aaaa_records = @dns_get_record($host, DNS_AAAA);
                if (is_array($aaaa_records)) {
                    foreach ($aaaa_records as $record) {
                        if (!empty($record['ipv6']) && filter_var($record['ipv6'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                            $ips[] = (string) $record['ipv6'];
                        }
                    }
                }
            }
        }

        // Fallback for environments without dns_get_record / AAAA support.
        if (empty($ips) && function_exists('gethostbynamel')) {
            $resolved = @gethostbynamel($host);
            if (is_array($resolved)) {
                foreach ($resolved as $ip) {
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        $ips[] = (string) $ip;
                    }
                }
            }
        }

        return array_values(array_unique($ips));
    }
}

if (!function_exists('dashd_str_ends_with')) {
    function dashd_str_ends_with($haystack, $needle) {
        $haystack = (string) $haystack;
        $needle = (string) $needle;
        if ($needle === '') {
            return true;
        }
        $needle_len = strlen($needle);
        if ($needle_len > strlen($haystack)) {
            return false;
        }
        return substr($haystack, -$needle_len) === $needle;
    }
}

if (!function_exists('dashd_sanitize_webhook_url')) {
    /**
     * Allow only safe external HTTP(S) webhook URLs and block local/private targets.
     */
    function dashd_sanitize_webhook_url($url) {
        if (!is_scalar($url)) {
            return '';
        }

        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        $sanitized = esc_url_raw($url, ['http', 'https']);
        if ($sanitized === '' || !wp_http_validate_url($sanitized)) {
            return '';
        }

        $parts = wp_parse_url($sanitized);
        if (!is_array($parts)) {
            return '';
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || !in_array($scheme, ['http', 'https'], true)) {
            return '';
        }

        // Deny obvious local/internal hostnames.
        $blocked_suffixes = [
            '.localhost',
            '.local',
            '.internal',
            '.lan',
            '.home',
            '.test'
        ];
        if ($host === 'localhost') {
            return '';
        }
        foreach ($blocked_suffixes as $suffix) {
            if (dashd_str_ends_with($host, $suffix)) {
                return '';
            }
        }

        // Block direct private/reserved IPs.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return dashd_is_public_ip($host) ? $sanitized : '';
        }

        // Resolve DNS (A/AAAA) and deny hosts that resolve to private/reserved ranges.
        $resolved = function_exists('dashd_resolve_host_ips') ? dashd_resolve_host_ips($host) : [];
        if (empty($resolved)) {
            return '';
        }
        foreach ($resolved as $ip) {
            if (!dashd_is_public_ip($ip)) {
                return '';
            }
        }

        return $sanitized;
    }
}

if (!function_exists('dashd_normalize_source_type')) {
    function dashd_normalize_source_type($value) {
        $value = strtolower(trim((string) $value));
        return in_array($value, ['csv', 'json'], true) ? $value : 'csv';
    }
}

if (!function_exists('dashd_normalize_http_method')) {
    function dashd_normalize_http_method($value) {
        $value = strtoupper(trim((string) $value));
        return in_array($value, ['GET', 'POST'], true) ? $value : 'GET';
    }
}

if (!function_exists('dashd_enforce_http_method')) {
    /**
     * Enforce request HTTP method for sensitive actions.
     *
     * @param string $expected Expected method (GET/POST)
     * @param bool   $is_ajax  Whether this is an AJAX endpoint.
     */
    function dashd_enforce_http_method($expected = 'POST', $is_ajax = false) {
        $expected = function_exists('dashd_normalize_http_method')
            ? dashd_normalize_http_method($expected)
            : strtoupper(trim((string) $expected));

        if ($expected === '') {
            $expected = 'POST';
        }

        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';
        if ($method === $expected) {
            return true;
        }

        if ($is_ajax && function_exists('wp_send_json_error')) {
            wp_send_json_error(['msg' => __('Invalid request method.', 'dashd-analytics-pro')], 405);
        }

        wp_die(
            esc_html__('Invalid request method.', 'dashd-analytics-pro'),
            '',
            ['response' => 405]
        );
    }
}

if (!function_exists('dashd_forbidden_response')) {
    /**
     * Return a unified 403 response for unauthorized actions.
     *
     * @param bool $is_ajax Whether endpoint is AJAX.
     */
    function dashd_forbidden_response($is_ajax = false) {
        $message = __('Access denied.', 'dashd-analytics-pro');

        if ($is_ajax && function_exists('wp_send_json_error')) {
            wp_send_json_error(['msg' => $message], 403);
        }

        wp_die(
            esc_html($message),
            '',
            ['response' => 403]
        );
    }
}

if (!function_exists('dashd_normalize_source_key')) {
    /**
     * Normalize source key to DB-safe format (dashd_settings.source_key varchar(50)).
     */
    function dashd_normalize_source_key($value, $default = '') {
        $key = sanitize_key((string) $value);
        if ($key === '') {
            $key = sanitize_key((string) $default);
        }
        if ($key === '') {
            return '';
        }
        if (strlen($key) > 50) {
            $key = substr($key, 0, 50);
        }
        return $key;
    }
}

if (!function_exists('dashd_sanitize_source_headers')) {
    /**
     * Accept JSON object with safe HTTP headers only.
     */
    function dashd_sanitize_source_headers($raw_headers) {
        if (!is_scalar($raw_headers)) {
            return '';
        }

        $raw_headers = trim((string) $raw_headers);
        if ($raw_headers === '' || strlen($raw_headers) > 8000) {
            return '';
        }

        $decoded = json_decode($raw_headers, true);
        if (!is_array($decoded) || array_values($decoded) === $decoded) {
            return '';
        }

        $blocked_header_names = ['host', 'content-length', 'transfer-encoding', 'connection', 'expect'];
        $safe = [];

        foreach ($decoded as $name => $value) {
            $name = trim((string) $name);
            if ($name === '' || strlen($name) > 64) {
                continue;
            }
            if (preg_match('/^[A-Za-z0-9\-]+$/', $name) !== 1) {
                continue;
            }
            if (in_array(strtolower($name), $blocked_header_names, true)) {
                continue;
            }
            if (is_array($value) || is_object($value)) {
                continue;
            }

            $value = (string) $value;
            $value = preg_replace('/[\r\n]+/', ' ', $value);
            if (!is_string($value)) {
                continue;
            }
            $value = trim($value);
            if ($value === '' || strlen($value) > 2000) {
                continue;
            }

            $safe[$name] = $value;
            if (count($safe) >= 20) {
                break;
            }
        }

        if (empty($safe)) {
            return '';
        }

        $json = wp_json_encode($safe, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return is_string($json) ? $json : '';
    }
}

if (!function_exists('dashd_sanitize_source_url')) {
    /**
     * Sanitize remote data source URL. By default, private/local targets are blocked.
     * Use filter `dashd_allow_private_source_urls` to allow local/private URLs.
     */
    function dashd_sanitize_source_url($url) {
        if (!is_scalar($url)) {
            return '';
        }

        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        $sanitized = esc_url_raw($url, ['http', 'https']);
        if ($sanitized === '' || !wp_http_validate_url($sanitized)) {
            return '';
        }

        $allow_private = (bool) apply_filters('dashd_allow_private_source_urls', false, $sanitized);
        if ($allow_private) {
            return $sanitized;
        }

        return function_exists('dashd_sanitize_webhook_url')
            ? dashd_sanitize_webhook_url($sanitized)
            : $sanitized;
    }
}

if (!function_exists('dashd_normalize_calc_formula')) {
    /**
     * Validate and normalize calculated indicator formula.
     * Supported format:
     *   IndID[:CountryID][:Offset] [operator IndID[:CountryID][:Offset]]
     * Examples:
     *   5
     *   5::-1Y
     *   5:2:-1Q
     *   5::-1Y+7
     *
     * Returns normalized compact uppercase formula or empty string when invalid.
     */
    function dashd_normalize_calc_formula($formula) {
        if (!is_scalar($formula)) {
            return '';
        }

        $formula = strtoupper(trim((string) $formula));
        if ($formula === '' || strlen($formula) > 96) {
            return '';
        }

        $formula = preg_replace('/\s+/', '', $formula);
        if (!is_string($formula) || $formula === '') {
            return '';
        }

        $operator_pos = null;
        $operator = '';
        $len = strlen($formula);
        for ($i = 0; $i < $len; $i++) {
            $char = $formula[$i];
            if (!in_array($char, ['+', '-', '*', '/'], true)) {
                continue;
            }

            // '+'/'-' right after ':' belongs to time offset (e.g. ::-1Y).
            $prev_char = ($i > 0) ? $formula[$i - 1] : '';
            if ($prev_char === ':') {
                continue;
            }

            if ($operator_pos !== null) {
                return '';
            }

            $operator_pos = $i;
            $operator = $char;
        }

        $operand_pattern = '/^([1-9][0-9]{0,5})(?::([1-9][0-9]{0,5})?)?(?::([+-]?[0-9]{1,3})([YQ]))?$/';
        $is_operand_valid = static function ($operand) use ($operand_pattern) {
            $operand = (string) $operand;
            if ($operand === '') {
                return false;
            }
            if (preg_match($operand_pattern, $operand, $m) !== 1) {
                return false;
            }

            $offset_num = isset($m[3]) ? (int) $m[3] : 0;
            $offset_unit = isset($m[4]) ? (string) $m[4] : '';

            if ($offset_unit === 'Y' && abs($offset_num) > 30) {
                return false;
            }
            if ($offset_unit === 'Q' && abs($offset_num) > 120) {
                return false;
            }

            return true;
        };

        if ($operator_pos === null) {
            return $is_operand_valid($formula) ? $formula : '';
        }

        $left = substr($formula, 0, $operator_pos);
        $right = substr($formula, $operator_pos + 1);
        if (!$is_operand_valid($left) || !$is_operand_valid($right)) {
            return '';
        }

        return $left . $operator . $right;
    }
}

if (!function_exists('dashd_get_sensitive_setting')) {
    /**
     * Read sensitive setting with priority: wp-config constant -> DB option.
     */
    function dashd_get_sensitive_setting($option_name, $constant_name = '') {
        $option_name = sanitize_key((string) $option_name);
        $constant_name = trim((string) $constant_name);

        $raw = '';
        if ($constant_name !== '' && defined($constant_name)) {
            $const_val = constant($constant_name);
            $raw = is_scalar($const_val) ? (string) $const_val : '';
        } else {
            $opt_val = get_option($option_name, '');
            $raw = is_scalar($opt_val) ? (string) $opt_val : '';
        }

        return trim($raw);
    }
}

if (!function_exists('dashd_update_sensitive_setting')) {
    /**
     * Persist sensitive setting and force autoload=no to reduce accidental exposure.
     */
    function dashd_update_sensitive_setting($option_name, $value) {
        global $wpdb;

        $option_name = sanitize_key((string) $option_name);
        if ($option_name === '') {
            return false;
        }

        $value = is_scalar($value) ? (string) $value : '';

        if (get_option($option_name, null) === null) {
            add_option($option_name, $value, '', 'no');
        } else {
            update_option($option_name, $value, false);
        }

        if (isset($wpdb->options) && is_string($wpdb->options) && $wpdb->options !== '') {
            $wpdb->update($wpdb->options, ['autoload' => 'no'], ['option_name' => $option_name], ['%s'], ['%s']);
        }

        return true;
    }
}

require_once DASHD_PATH . 'includes/database.php';
require_once DASHD_PATH . 'includes/repositories/class-dashd-lead-repository.php';
require_once DASHD_PATH . 'includes/services/class-dashd-api-read-service.php';
require_once DASHD_PATH . 'includes/services/class-dashd-lead-notifier-service.php';
require_once DASHD_PATH . 'includes/services/class-dashd-lead-capture-service.php';
require_once DASHD_PATH . 'includes/services/class-dashd-sync-dictionary-service.php';
require_once DASHD_PATH . 'includes/services/class-dashd-sync-source-record-store.php';
require_once DASHD_PATH . 'includes/sync-engine.php';
require_once DASHD_PATH . 'includes/admin-ui.php';
require_once DASHD_PATH . 'includes/admin-settings.php';
require_once DASHD_PATH . 'includes/integration-helpers.php';
require_once DASHD_PATH . 'includes/admin-constructor.php';
require_once DASHD_PATH . 'includes/analytics-api.php';
require_once DASHD_PATH . 'includes/widget-renderer.php';

add_shortcode('dashd_widget', 'dashd_render_front_widget');
register_activation_hook(__FILE__, 'dashd_init_analytical_db');

add_action('admin_init', function() {
    $admin = get_role('administrator');
    if ($admin && !$admin->has_cap('dashd_manage_data')) {
        $admin->add_cap('dashd_manage_data');
        add_role('dashd_analyst', 'Analytics Manager', [
            'read' => true,
            'dashd_manage_data' => true
        ]);
    }
});

if (!wp_next_scheduled('dashd_daily_sync_event')) {
    wp_schedule_event(time(), 'daily', 'dashd_daily_sync_event');
}
add_action('dashd_daily_sync_event', function() {
    if (get_option('dashd_auto_sync') === 'enabled') dashd_sync_repository(false);
});
register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('dashd_daily_sync_event');
});

// =========================================================
// РЕГИСТРАЦИЯ И ПОДКЛЮЧЕНИЕ СКРИПТОВ
// =========================================================

// 1. Регистрируем наши локальные скрипты глобально (но пока не загружаем на страницу)
add_action('init', function() {
    wp_register_script('dashd-chart-js', DASHD_URL . 'assets/vendors/chart.min.js', [], '4.4.0', true);
    wp_register_script('dashd-html2canvas', DASHD_URL . 'assets/vendors/html2canvas.min.js', [], '1.4.1', true);
    wp_register_script('dashd-jspdf', DASHD_URL . 'assets/vendors/jspdf.umd.min.js', [], '2.5.1', true);
    wp_register_script('dashd-widget-runtime', false, ['dashd-jspdf'], DASHD_VERSION, true);
});

// 2. Скрипты и стили для админки
add_action('admin_enqueue_scripts', function() {
    wp_enqueue_style('dashd-style', DASHD_URL . 'assets/style.css', [], DASHD_VERSION);
    
    // В админке (для конструктора) нам нужен только Chart.js
    if (!wp_script_is('chart-js', 'enqueued') && !wp_script_is('chartjs', 'enqueued')) {
        wp_enqueue_script('dashd-chart-js');
    }
    
    if (isset($_GET['page']) && $_GET['page'] === 'dashd-settings') wp_enqueue_media();
});

// 3. Стили для фронтенда (скрипты будут загружаться только внутри шорткода)
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('dashd-style', DASHD_URL . 'assets/style.css', [], DASHD_VERSION);
});

add_action('admin_menu', function() {
    add_menu_page(__('Analytics Pro', 'dashd-analytics-pro'), __('Analytics Pro', 'dashd-analytics-pro'), 'manage_options', 'dashd-main', 'dashd_admin_main_page', 'dashicons-chart-bar', 25);
    add_submenu_page('dashd-main', __('Widget Constructor', 'dashd-analytics-pro'), __('Constructor 🎨', 'dashd-analytics-pro'), 'manage_options', 'dashd-constructor', 'dashd_admin_constructor_page');
    add_submenu_page('dashd-main', __('Settings', 'dashd-analytics-pro'), __('Settings', 'dashd-analytics-pro'), 'manage_options', 'dashd-settings', 'dashd_admin_settings_page');
});

add_action('wp_ajax_dashd_render_preview', function() {
    if (function_exists('dashd_enforce_http_method')) {
        dashd_enforce_http_method('POST', true);
    }

    if (!current_user_can('manage_options')) {
        if (function_exists('dashd_forbidden_response')) {
            dashd_forbidden_response(true);
        }
        wp_die();
    }
    check_ajax_referer('dashd_render_preview', 'nonce');

    $raw_shortcode = isset($_POST['shortcode']) ? wp_unslash((string) $_POST['shortcode']) : '';
    if (!preg_match('/^\s*\[dashd_widget\s*([^\]]*)\]\s*$/i', $raw_shortcode, $m)) {
        wp_die(
            esc_html__('Invalid preview request.', 'dashd-analytics-pro'),
            '',
            ['response' => 400]
        );
    }

    $parsed_atts = shortcode_parse_atts((string) ($m[1] ?? ''));
    if (!is_array($parsed_atts)) {
        $parsed_atts = [];
    }

    $table = function_exists('dashd_normalize_source_key')
        ? dashd_normalize_source_key((string) ($parsed_atts['table'] ?? ''))
        : sanitize_key((string) ($parsed_atts['table'] ?? ''));
    $indicator_specs = [];
    $indicators_raw = is_scalar($parsed_atts['indicators'] ?? '') ? (string) $parsed_atts['indicators'] : '';
    foreach (preg_split('/\s*,\s*/', $indicators_raw, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
        $token = trim((string) $token);
        if ($token === '') {
            continue;
        }
        if (preg_match('/^[a-z0-9_\-]+:\d+$/i', $token) !== 1 && preg_match('/^\d+$/', $token) !== 1) {
            continue;
        }
        $indicator_specs[$token] = $token;
        if (count($indicator_specs) >= 40) {
            break;
        }
    }
    $safe_indicators = implode(',', array_values($indicator_specs));

    if ($table === '' && !empty($indicator_specs)) {
        $first = (string) reset($indicator_specs);
        if (preg_match('/^([a-z0-9_\-]+):\d+$/i', $first, $m) === 1) {
            $source_from_indicator = function_exists('dashd_normalize_source_key')
                ? dashd_normalize_source_key((string) $m[1])
                : sanitize_key((string) $m[1]);
            if ($source_from_indicator !== '') {
                $table = $source_from_indicator;
            }
        }
    }
    if ($table === '') {
        $table = 'table1';
    }

    $mode = strtolower(trim((string) ($parsed_atts['mode'] ?? 'bar')));
    if (!in_array($mode, ['bar', 'line', 'donut'], true)) {
        $mode = 'bar';
    }

    $scale = strtolower(trim((string) ($parsed_atts['scale'] ?? 'linear')));
    if (!in_array($scale, ['linear', 'logarithmic'], true)) {
        $scale = 'linear';
    }

    $gated = ((string) ($parsed_atts['gated'] ?? 'false') === 'true') ? 'true' : 'false';
    $bool_like = static function($value, $default = true) {
        if (is_bool($value)) {
            return $value;
        }
        $raw = strtolower(trim((string) $value));
        if ($raw === '') {
            return (bool) $default;
        }
        if (in_array($raw, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($raw, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }
        return (bool) $default;
    };
    $show_view_toggle = $bool_like($parsed_atts['show_view_toggle'] ?? 'true', true) ? 'true' : 'false';
    $show_scale_toggle = $bool_like($parsed_atts['show_scale_toggle'] ?? 'true', true) ? 'true' : 'false';
    $show_periods = $bool_like($parsed_atts['show_periods'] ?? 'true', true) ? 'true' : 'false';
    $bar_orientation = strtolower(trim((string) ($parsed_atts['bar_orientation'] ?? 'horizontal')));
    if (!in_array($bar_orientation, ['horizontal', 'vertical'], true)) {
        $bar_orientation = 'horizontal';
    }
    $bar_stacked = $bool_like($parsed_atts['bar_stacked'] ?? 'true', true) ? 'true' : 'false';
    $country_order = [];
    foreach (preg_split('/[,\n;]+/', (string) ($parsed_atts['country_order'] ?? '')) ?: [] as $country_name) {
        $country_name = trim(wp_strip_all_tags((string) $country_name));
        if ($country_name === '') {
            continue;
        }
        $country_order[$country_name] = $country_name;
        if (count($country_order) >= 100) {
            break;
        }
    }
    $country_order = implode(', ', array_values($country_order));

    $safe_colors = [];
    $colors_raw = is_scalar($parsed_atts['colors'] ?? '') ? (string) $parsed_atts['colors'] : '';
    foreach (array_map('trim', explode(',', $colors_raw)) as $color) {
        if (preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $color)) {
            $safe_colors[] = strtoupper($color);
        }
    }
    if (empty($safe_colors)) {
        $safe_colors = ['#336DFF', '#AF9BE2', '#3B82F6', '#BEE00F', '#7FD3F7'];
    }

    $preview_atts = [
        'table'  => $table,
        'indicators' => $safe_indicators,
        'mode'   => $mode,
        'scale'  => $scale,
        'gated'  => $gated,
        'show_view_toggle' => $show_view_toggle,
        'show_scale_toggle' => $show_scale_toggle,
        'show_periods' => $show_periods,
        'bar_orientation' => $bar_orientation,
        'bar_stacked' => $bar_stacked,
        'country_order' => $country_order,
        'colors' => implode(', ', $safe_colors),
    ];

    if (function_exists('dashd_render_front_widget')) {
        echo dashd_render_front_widget($preview_atts);
    }

    wp_die();
});

add_action('admin_post_dashd_manual_sync', function() {
    if (function_exists('dashd_enforce_http_method')) {
        dashd_enforce_http_method('POST');
    }

    if (!current_user_can('manage_options')) {
        if (function_exists('dashd_forbidden_response')) {
            dashd_forbidden_response(false);
        }
        wp_die('Access denied');
    }
    check_admin_referer('dashd_manual_sync', 'dashd_manual_sync_nonce');
    dashd_sync_repository(true);
    wp_redirect(admin_url('admin.php?page=dashd-settings&tab=sources&status=synced'));
    exit;
});

function dashd_notify($message, $is_error = false) {
    // Priority order: wp-config constants -> DB options.
    $token = function_exists('dashd_get_sensitive_setting')
        ? dashd_get_sensitive_setting('dashd_telegram_bot_token', 'DASHD_TELEGRAM_BOT_TOKEN')
        : (defined('DASHD_TELEGRAM_BOT_TOKEN') ? DASHD_TELEGRAM_BOT_TOKEN : get_option('dashd_telegram_bot_token', ''));
    $chat_id = function_exists('dashd_get_sensitive_setting')
        ? dashd_get_sensitive_setting('dashd_telegram_chat_id', 'DASHD_TELEGRAM_CHAT_ID')
        : (defined('DASHD_TELEGRAM_CHAT_ID') ? DASHD_TELEGRAM_CHAT_ID : get_option('dashd_telegram_chat_id', ''));

    $token = is_string($token) ? trim($token) : '';
    $chat_id = is_scalar($chat_id) ? trim((string) $chat_id) : '';

    // If Telegram is not configured, silently skip notifications.
    if ($token === '' || $chat_id === '') {
        return false;
    }

    $prefix = $is_error ? "⚠️ ERROR: " : "✅ SYNC: ";
    $full_message = $prefix . wp_strip_all_tags((string) $message) . "\nSite: " . get_bloginfo('url');

    $response = wp_safe_remote_post("https://api.telegram.org/bot{$token}/sendMessage", [
        'blocking' => false,
        'timeout'  => 5,
        'reject_unsafe_urls' => true,
        'body'     => [
            'chat_id' => $chat_id,
            'text'    => $full_message
        ]
    ]);

    return !is_wp_error($response);
}

// НОВАЯ ФУНКЦИЯ: Специальный алерт для аномалий
function dashd_send_anomaly_alert($anomalies) {
    if (empty($anomalies)) return;

    $message = "🚨 *URGENT: Data Anomalies Detected (>300%)* 🚨\n\n";
    $message .= implode("\n", array_slice($anomalies, 0, 10)); // Показываем до 10 штук
    if (count($anomalies) > 10) {
        $message .= "\n... and " . (count($anomalies) - 10) . " more.";
    }
    $message .= "\n\nPlease review the source data immediately.";

    // 1. Отправляем в Telegram (используем твою старую функцию, флаг true для ошибки)
    if (function_exists('dashd_notify')) {
        dashd_notify($message, true);
    }

    // 2. Отправляем в Slack/Discord, если вебхук настроен в админке
    $slack_webhook = function_exists('dashd_sanitize_webhook_url')
        ? dashd_sanitize_webhook_url((string) (function_exists('dashd_get_sensitive_setting')
            ? dashd_get_sensitive_setting('dashd_slack_webhook', 'DASHD_SLACK_WEBHOOK')
            : get_option('dashd_slack_webhook', '')))
        : trim((string) (function_exists('dashd_get_sensitive_setting')
            ? dashd_get_sensitive_setting('dashd_slack_webhook', 'DASHD_SLACK_WEBHOOK')
            : get_option('dashd_slack_webhook', '')));

    if ($slack_webhook !== '') {
        wp_safe_remote_post($slack_webhook, [
            'blocking' => false, // Асинхронно
            'timeout'  => 5,
            'redirection' => 2,
            'reject_unsafe_urls' => true,
            'headers'  => ['Content-Type' => 'application/json'],
            'body'     => wp_json_encode(['text' => $message])
        ]);
    }
}

function dashd_clear_all_caches() {
    update_option('dashd_cache_ver', time());
}

// =========================================================
// 1. РАЗРЕШАЕМ ЗАГРУЗКУ SVG (Только для Администраторов)
// =========================================================
add_filter('upload_mimes', function($mimes) {
    if (current_user_can('manage_options')) {
        $mimes['svg'] = 'image/svg+xml';
    }
    return $mimes;
});

if (!function_exists('dashd_svg_upload_prefilter')) {
    /**
     * Basic SVG hardening on upload: block common active-content vectors.
     */
    function dashd_svg_upload_prefilter($file) {
        $name = isset($file['name']) ? (string) $file['name'] : '';
        $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        if ($ext !== 'svg') {
            return $file;
        }

        if (!current_user_can('manage_options')) {
            $file['error'] = __('SVG upload is allowed for administrators only.', 'dashd-analytics-pro');
            return $file;
        }

        $tmp = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
        if ($tmp === '' || !is_readable($tmp)) {
            $file['error'] = __('Unable to validate SVG file.', 'dashd-analytics-pro');
            return $file;
        }

        $max_bytes = (int) apply_filters('dashd_svg_max_upload_bytes', 512000); // 500 KB
        if ($max_bytes < 1024) {
            $max_bytes = 1024;
        }

        $size = @filesize($tmp);
        if (is_int($size) && $size > $max_bytes) {
            $file['error'] = __('SVG file is too large.', 'dashd-analytics-pro');
            return $file;
        }

        $content = @file_get_contents($tmp, false, null, 0, $max_bytes + 1);
        if (!is_string($content) || $content === '') {
            $file['error'] = __('SVG file is empty or unreadable.', 'dashd-analytics-pro');
            return $file;
        }

        if (strlen($content) > $max_bytes) {
            $file['error'] = __('SVG file is too large.', 'dashd-analytics-pro');
            return $file;
        }

        if (stripos($content, '<svg') === false) {
            $file['error'] = __('Invalid SVG markup.', 'dashd-analytics-pro');
            return $file;
        }

        // Block active content and XML entity vectors commonly used in SVG XSS payloads.
        $dangerous_pattern = '/<script\b|<foreignObject\b|<iframe\b|<object\b|<embed\b|<!ENTITY|<!DOCTYPE|on[a-z]+\s*=|xlink:href\s*=\s*["\']\s*javascript:|href\s*=\s*["\']\s*javascript:/i';
        if (preg_match($dangerous_pattern, $content) === 1) {
            $file['error'] = __('Unsafe SVG content detected. Please upload a sanitized SVG.', 'dashd-analytics-pro');
            return $file;
        }

        return $file;
    }
}

add_filter('wp_handle_upload_prefilter', 'dashd_svg_upload_prefilter');

// =========================================================
// 2. АВТОМАТИЧЕСКИЙ КОПИРАЙТ ВО ФРОНТЕНДЕ
// =========================================================
add_action('wp_footer', function() {
    // Выводим скрипт только если мы не в админке
    if (!is_admin()) {
        ?>
        <script>
        (function() {
            var noteId = 'dashd-powered-by-note';

            var placePoweredByNote = function() {
                // Привязываем подпись строго к нашим виджетам.
                var widgets = document.querySelectorAll('.dashd-widget-container');
                if (!widgets.length) {
                    return;
                }

                var lastWidget = widgets[widgets.length - 1];
                var note = document.getElementById(noteId);

                if (!note) {
                    note = document.createElement('div');
                    note.id = noteId;
                    note.style.cssText = 'text-align:right;font-size:11px;color:#a7aaad;margin-top:20px;font-family:sans-serif;';
                    note.innerHTML = 'Powered by <strong>DashD Analytics Pro v<?php echo DASHD_VERSION; ?></strong> &copy; <?php echo current_time("Y"); ?> All rights reserved.';
                }

                if (lastWidget.nextElementSibling !== note) {
                    lastWidget.insertAdjacentElement('afterend', note);
                }
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', placePoweredByNote, { once: true });
            } else {
                placePoweredByNote();
            }
        })();
        </script>
        <?php
    }
});

// =========================================================
// ИНТЕГРАЦИИ (GUTENBERG, ELEMENTOR, YOOTHEME)
// =========================================================

require_once DASHD_PATH . 'includes/integrations/gutenberg.php';
require_once DASHD_PATH . 'includes/integrations/elementor.php';
require_once DASHD_PATH . 'includes/integrations/yootheme.php';
