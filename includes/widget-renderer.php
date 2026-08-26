<?php
/**
 * Widget Renderer v9.8.2
 * ДОБАВЛЕНО: Интеграция с плагином WP Dark Mode (динамическое переключение графиков).
 */

if (!defined('ABSPATH')) exit;

if (!function_exists('dashd_resolve_upload_file_path')) {
    function dashd_resolve_upload_file_path($url) {
        if (!is_string($url) || $url === '') {
            return '';
        }

        $uploads = wp_get_upload_dir();
        $baseurl = isset($uploads['baseurl']) ? (string) $uploads['baseurl'] : '';
        $basedir = isset($uploads['basedir']) ? (string) $uploads['basedir'] : '';

        if ($baseurl === '' || $basedir === '') {
            return '';
        }

        $clean_url = strtok($url, '?#');
        if (!is_string($clean_url) || $clean_url === '') {
            return '';
        }

        $relative = '';
        if (strpos($clean_url, $baseurl) === 0) {
            $relative = ltrim((string) substr($clean_url, strlen($baseurl)), '/');
        } else {
            // Fallback: compare only URL paths (handles http/https or host mismatch in local environments).
            $clean_path = (string) wp_parse_url($clean_url, PHP_URL_PATH);
            $base_path = (string) wp_parse_url($baseurl, PHP_URL_PATH);
            if ($clean_path === '' || $base_path === '' || strpos($clean_path, $base_path) !== 0) {
                return '';
            }
            $relative = ltrim((string) substr($clean_path, strlen($base_path)), '/');
        }

        $relative = wp_normalize_path(rawurldecode($relative));
        if ($relative === '') {
            return '';
        }

        $base = trailingslashit(wp_normalize_path($basedir));
        $candidate = wp_normalize_path($base . $relative);

        if (strpos($candidate, $base) !== 0) {
            return '';
        }

        return (is_file($candidate) && is_readable($candidate)) ? $candidate : '';
    }
}

if (!function_exists('dashd_parse_indicator_specs')) {
    /**
     * Parse indicator list tokens.
     * Supported token formats:
     * - source_key:indicator_id
     * - indicator_id
     *
     * @param mixed $raw
     * @return array<int,array{source:string,id:int}>
     */
    function dashd_parse_indicator_specs($raw) {
        $parts = [];
        if (is_array($raw)) {
            foreach ($raw as $item) {
                $item = is_scalar($item) ? (string) $item : '';
                if ($item === '') {
                    continue;
                }
                foreach (explode(',', $item) as $chunk) {
                    $parts[] = trim((string) $chunk);
                }
            }
        } else {
            $raw = is_scalar($raw) ? (string) $raw : '';
            foreach (explode(',', $raw) as $chunk) {
                $parts[] = trim((string) $chunk);
            }
        }

        $specs = [];
        $seen = [];
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            $source = '';
            $id = 0;
            if (preg_match('/^([a-z0-9_\\-]+):(\\d+)$/i', $part, $m) === 1) {
                $source_raw = (string) $m[1];
                $source = function_exists('dashd_normalize_source_key')
                    ? dashd_normalize_source_key($source_raw)
                    : sanitize_key($source_raw);
                $id = (int) $m[2];
            } elseif (preg_match('/^\\d+$/', $part) === 1) {
                $id = (int) $part;
            }

            if ($id <= 0) {
                continue;
            }

            $dedupe = $source . ':' . $id;
            if (isset($seen[$dedupe])) {
                continue;
            }
            $seen[$dedupe] = true;
            $specs[] = ['source' => $source, 'id' => $id];
            if (count($specs) >= 40) {
                break;
            }
        }

        return $specs;
    }
}

if (!function_exists('dashd_get_indicator_source_map')) {
    /**
     * Map indicator IDs to source keys using configured target_source or stored raw records.
     *
     * @param array<int,int> $indicator_ids
     * @return array<int,string>
     */
    function dashd_get_indicator_source_map(array $indicator_ids) {
        global $wpdb;

        $result = [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $indicator_ids), static function($v) {
            return $v > 0;
        })));
        if (empty($ids)) {
            return $result;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        $target_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, target_source FROM {$wpdb->prefix}dashd_indicators WHERE id IN ($placeholders)",
                ...$ids
            )
        );
        if (is_array($target_rows)) {
            foreach ($target_rows as $row) {
                $id = (int) ($row->id ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $src_raw = (string) ($row->target_source ?? '');
                $src = function_exists('dashd_normalize_source_key')
                    ? dashd_normalize_source_key($src_raw)
                    : sanitize_key($src_raw);
                if ($src !== '' && $src !== 'all') {
                    $result[$id] = $src;
                }
            }
        }

        $missing = [];
        foreach ($ids as $id) {
            if (!isset($result[$id])) {
                $missing[] = $id;
            }
        }

        if (!empty($missing)) {
            $sub_placeholders = implode(',', array_fill(0, count($missing), '%d'));
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT indicator_id, MIN(source_key) AS source_key
                     FROM {$wpdb->prefix}dashd_data_records
                     WHERE indicator_id IN ($sub_placeholders)
                     GROUP BY indicator_id",
                    ...$missing
                )
            );
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $id = (int) ($row->indicator_id ?? 0);
                    if ($id <= 0) {
                        continue;
                    }
                    $src_raw = (string) ($row->source_key ?? '');
                    $src = function_exists('dashd_normalize_source_key')
                        ? dashd_normalize_source_key($src_raw)
                        : sanitize_key($src_raw);
                    if ($src !== '') {
                        $result[$id] = $src;
                    }
                }
            }
        }

        return $result;
    }
}

function dashd_render_front_widget($atts) {
    $a = shortcode_atts([
        'table'  => '',
        'indicators' => '',
        'mode'   => 'bar',
        'scale'  => 'linear',
        'colors' => '#336dff,#af9be2,#3b82f6,#bee00f,#7fd3f7',
        'weight' => '3',
        'height' => '420px',
        'gated'  => 'false',
        'show_view_toggle' => 'true',
        'show_scale_toggle' => 'true',
        'show_periods' => 'true',
        'show_data_warnings' => 'true',
        'bar_orientation' => 'horizontal',
        'bar_stacked' => 'true',
        'country_order' => '',
        'period_start' => '',
        'period_end' => '',
    ], $atts);

    $default_colors = ['#336DFF', '#AF9BE2', '#3B82F6', '#BEE00F', '#7FD3F7'];

    $table = function_exists('dashd_normalize_source_key')
        ? dashd_normalize_source_key((string) $a['table'])
        : sanitize_key((string) $a['table']);
    $indicator_specs = dashd_parse_indicator_specs($a['indicators'] ?? '');
    $indicator_ids = array_values(array_unique(array_map(static function($spec) {
        return (int) ($spec['id'] ?? 0);
    }, $indicator_specs)));
    $indicator_source_map = dashd_get_indicator_source_map($indicator_ids);

    if ($table === '' && !empty($indicator_specs)) {
        $first_source = (string) ($indicator_specs[0]['source'] ?? '');
        if ($first_source === '') {
            $first_id = (int) ($indicator_specs[0]['id'] ?? 0);
            $first_source = (string) ($indicator_source_map[$first_id] ?? '');
        }
        if ($first_source !== '') {
            $table = $first_source;
        }
    }
    if ($table === '') {
        $table = 'table1';
    }

    $active_indicator_specs = [];
    foreach ($indicator_specs as $spec) {
        $id = (int) ($spec['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }

        $spec_source = (string) ($spec['source'] ?? '');
        $mapped_source = (string) ($indicator_source_map[$id] ?? '');
        if ($spec_source === '' && $mapped_source !== '') {
            $spec_source = $mapped_source;
        }

        $token = ($spec_source !== '' ? $spec_source . ':' : '') . $id;
        if (!isset($active_indicator_specs[$token])) {
            $active_indicator_specs[$token] = $token;
        }
    }
    $active_indicator_specs = array_values($active_indicator_specs);
    $active_indicator_ids = array_values(array_unique(array_map(static function($token) {
        $token = (string) $token;
        if (strpos($token, ':') !== false) {
            $parts = explode(':', $token);
            return (int) end($parts);
        }
        return (int) $token;
    }, $active_indicator_specs)));

    $mode = in_array((string) $a['mode'], ['bar', 'line', 'donut'], true) ? (string) $a['mode'] : 'bar';
    $scale = in_array((string) $a['scale'], ['linear', 'logarithmic'], true) ? (string) $a['scale'] : 'linear';
    $gated = ((string) $a['gated'] === 'true') ? 'true' : 'false';
    $bool_from_atts = static function($value, $default = true) {
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
    $show_view_toggle = $bool_from_atts($a['show_view_toggle'] ?? 'true', true);
    $show_scale_toggle = $bool_from_atts($a['show_scale_toggle'] ?? 'true', true);
    $show_periods = $bool_from_atts($a['show_periods'] ?? 'true', true);
    $show_data_warnings = $bool_from_atts($a['show_data_warnings'] ?? 'true', true);
    $bar_orientation = strtolower(trim((string) ($a['bar_orientation'] ?? 'horizontal')));
    if (!in_array($bar_orientation, ['horizontal', 'vertical'], true)) {
        $bar_orientation = 'horizontal';
    }
    $bar_stacked = $bool_from_atts($a['bar_stacked'] ?? 'true', true);

    $weight = is_numeric($a['weight']) ? (float) $a['weight'] : 3.0;
    $weight = max(1, min(10, $weight));

    $height = trim((string) $a['height']);
    if (!preg_match('/^\d+(?:\.\d+)?(?:px|em|rem|vh|vw|%)$/', $height)) {
        $height = '420px';
    }

    $colors = [];
    foreach (array_map('trim', explode(',', (string) $a['colors'])) as $color) {
        if (preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $color)) {
            $colors[] = $color;
        }
    }
    if (empty($colors)) {
        $colors = $default_colors;
    }

    $country_order = [];
    foreach (preg_split('/[,\n;]+/', (string) ($a['country_order'] ?? '')) ?: [] as $country_name) {
        $country_name = trim(wp_strip_all_tags((string) $country_name));
        if ($country_name === '') {
            continue;
        }
        $country_order[$country_name] = $country_name;
        if (count($country_order) >= 100) {
            break;
        }
    }
    $country_order = array_values($country_order);

    $normalize_period_bound = static function($value) {
        $value = strtoupper(trim((string) $value));
        if ($value === '') {
            return '';
        }
        if (preg_match('/^(\d{4})[-_\s]?(Q[1-4])$/', $value, $matches) === 1) {
            return sprintf('%d-%s', (int) $matches[1], (string) $matches[2]);
        }
        if (preg_match('/^(Q[1-4])[-_\s]?(\d{4})$/', $value, $matches) === 1) {
            return sprintf('%d-%s', (int) $matches[2], (string) $matches[1]);
        }
        return '';
    };
    $period_start = $normalize_period_bound($a['period_start'] ?? '');
    $period_end = $normalize_period_bound($a['period_end'] ?? '');

    // Умная проверка и подключение локальных скриптов
    // Проверяем самые частые названия (handles), под которыми другие плагины могли загрузить Chart.js
    if (!wp_script_is('chart.js', 'enqueued') && !wp_script_is('chart-js', 'enqueued') && !wp_script_is('chartjs', 'enqueued') && !wp_script_is('chart', 'enqueued')) {
        wp_enqueue_script('dashd-chart-js');
    }

    if (!wp_script_is('html2canvas', 'enqueued') && !wp_script_is('html2canvas-js', 'enqueued')) {
        wp_enqueue_script('dashd-html2canvas');
    }

    if (!wp_script_is('jspdf', 'enqueued') && !wp_script_is('jspdf-js', 'enqueued')) {
        wp_enqueue_script('dashd-jspdf');
    }
    // Guaranteed runtime handle for inline widget bootstrap code.
    wp_enqueue_script('dashd-widget-runtime');

    $uid = uniqid('dw_');
    $lang = defined('ICL_LANGUAGE_CODE') ? ICL_LANGUAGE_CODE : 'en';
    $lang = in_array($lang, ['en', 'uk', 'hy', 'ro', 'ka'], true) ? $lang : 'en';
    $ajax_url = admin_url('admin-ajax.php');

    $show_bar_controls_ui = is_admin();

    $js_config = [
        'key'       => $table,
        'indicatorIds' => $active_indicator_ids,
        'indicatorSpecs' => $active_indicator_specs,
        'lang'      => $lang,
        'colors'    => $colors,
        'weight'    => $weight,
        'ajax'      => $ajax_url,
        'viewMode'  => $mode,
        'scaleMode' => $scale,
        'isGated'   => ($gated === 'true'),
        'showViewToggle' => $show_view_toggle,
        'showScaleToggle' => $show_scale_toggle,
        'showPeriods' => $show_periods,
        'showDataWarnings' => $show_data_warnings,
        'showBarControlsUI' => $show_bar_controls_ui,
        'barOrientation' => $bar_orientation,
        'barStacked' => $bar_stacked,
        'countryOrder' => $country_order,
        'periodStart' => $period_start,
        'periodEnd' => $period_end,
        'leadNonce' => wp_create_nonce('dashd_capture_lead_' . $table),
    ];

    $logo = esc_url_raw((string) get_option('dashd_pdf_logo', ''));
    $logo_width = (int) get_option('dashd_pdf_logo_width', 150);
    $logo_width = max(20, min(1000, $logo_width));
    $signature = nl2br(esc_html(get_option('dashd_pdf_signature', '')));
    $watermark = esc_html(get_option('dashd_pdf_watermark', ''));

    // Безопасная обработка логотипа: trusted uploads + URL rendering for better builder compatibility.
    $logo_html = '';
    if ($logo !== '') {
        $local_path = dashd_resolve_upload_file_path($logo);
        if ($local_path !== '') {
            $logo_html = '<img src="' . esc_url($logo) . '" style="width: ' . esc_attr($logo_width) . 'px; height: auto;" data-dashd-local-logo="1" crossorigin="anonymous" referrerpolicy="no-referrer" loading="eager" decoding="sync">';
        }

        if ($logo_html === '') {
            // External URL fallback.
            $logo_html = '<img src="' . esc_url($logo) . '" style="width: ' . esc_attr($logo_width) . 'px; height: auto;" crossorigin="anonymous" referrerpolicy="no-referrer" loading="eager" decoding="sync">';
        }
    }

    ob_start();
    ?>
    <div id="<?php echo esc_attr($uid); ?>" class="dashd-widget-container uk-margin-large-bottom"
        data-key="<?php echo esc_attr($table); ?>" data-indicators="<?php echo esc_attr(implode(',', $active_indicator_specs)); ?>" data-lang="<?php echo esc_attr($lang); ?>" style="background: #fff; padding: 25px; border-radius: 8px; position: relative;">

        <?php if ($gated === 'true'): ?>
        <div class="dashd-modal-overlay" id="gated-modal-<?php echo esc_attr($uid); ?>" data-html2canvas-ignore="true">
            <div class="dashd-modal-box">
                <span class="dashd-modal-close">&times;</span>
                <h4 style="margin-top:0; font-weight:600; margin-bottom:10px;"><?php esc_html_e('Download Report', 'dashd-analytics-pro'); ?></h4>
                <p style="font-size:13px; color:#666; margin-bottom:20px;"><?php esc_html_e('Please enter your email address to unlock the export functionality.', 'dashd-analytics-pro'); ?></p>
                <input type="text" id="gated-hp-<?php echo esc_attr($uid); ?>" style="display:none !important;" tabindex="-1" autocomplete="off">
                <input type="email" id="gated-email-<?php echo esc_attr($uid); ?>" placeholder="<?php esc_attr_e('your@email.com', 'dashd-analytics-pro'); ?>" style="width:100%; padding:10px; border-radius:6px; border:1px solid #cbd5e1; margin-bottom:5px; box-sizing:border-box;" required>
                <div id="gated-error-<?php echo esc_attr($uid); ?>" style="color:#ef4444; font-size:12px; margin-bottom:15px; display:none;"></div>
                <button class="dashd-ui-btn active-btn" id="gated-submit-<?php echo esc_attr($uid); ?>" style="width:100%; justify-content:center; padding:10px; font-size: 14px;"><?php esc_html_e('Unlock & Download', 'dashd-analytics-pro'); ?></button>
            </div>
        </div>
        <?php endif; ?>

        <?php if($watermark): ?>
        <div class="dashd-pdf-watermark" style="display:none; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg); font-size: 80px; color: rgba(0,0,0,0.05); font-weight: 800; white-space: nowrap; pointer-events: none; z-index: 10;">
            <?php echo $watermark; ?>
        </div>
        <?php endif; ?>

        <?php if($logo_html): ?>
        <div class="dashd-pdf-header" style="display:none; margin-bottom: 25px; text-align: right; border-bottom: 2px solid #f0f0f1; padding-bottom: 15px;">
            <?php echo $logo_html; ?>
        </div>
        <?php endif; ?>

        <div class="dashd-country-btns uk-margin-small-bottom uk-flex uk-flex-wrap" style="gap:5px;justify-content:center;"></div>

        <div class="uk-flex uk-flex-between uk-flex-middle uk-margin-bottom dashd-widget-topbar">
            <h3 class="uk-h4 uk-margin-remove dashd-widget-title" data-default-title="<?php echo esc_attr(__('Analytics Overview', 'dashd-analytics-pro')); ?>"><?php esc_html_e('Analytics Overview', 'dashd-analytics-pro'); ?></h3>
            <div class="uk-flex uk-flex-column dashd-controls-stack" style="align-items: flex-end; gap: 10px;">
                <div class="uk-flex uk-flex-middle dashd-controls-desktop" style="gap:10px;">
                    <div class="dashd-view-selector dashd-toggle-view" data-html2canvas-ignore="true" style="<?php echo !$show_view_toggle ? 'display:none;' : ''; ?>">
                        <div class="dashd-selector-label <?php echo $mode === 'bar' ? 'active' : ''; ?>" data-type="bar"><?php esc_html_e('Bar', 'dashd-analytics-pro'); ?></div>
                        <div class="dashd-selector-label <?php echo $mode === 'line' ? 'active' : ''; ?>" data-type="line"><?php esc_html_e('Line', 'dashd-analytics-pro'); ?></div>
                        <div class="dashd-selector-label <?php echo $mode === 'donut' ? 'active' : ''; ?>" data-type="donut"><?php esc_html_e('Donut', 'dashd-analytics-pro'); ?></div>
                    </div>
                    <div class="dashd-view-selector dashd-toggle-scale" data-html2canvas-ignore="true" style="<?php echo !$show_scale_toggle ? 'display:none;' : ''; ?>">
                        <div class="dashd-selector-label <?php echo $scale === 'linear' ? 'active' : ''; ?>" data-scale="linear"><?php esc_html_e('Lin', 'dashd-analytics-pro'); ?></div>
                        <div class="dashd-selector-label <?php echo $scale === 'logarithmic' ? 'active' : ''; ?>" data-scale="logarithmic"><?php esc_html_e('Log', 'dashd-analytics-pro'); ?></div>
                    </div>
                    <div class="dashd-view-selector dashd-toggle-orientation" data-html2canvas-ignore="true" style="<?php echo $show_bar_controls_ui ? '' : 'display:none;'; ?>">
                        <div class="dashd-selector-label <?php echo $bar_orientation === 'horizontal' ? 'active' : ''; ?>" data-orientation="horizontal"><?php esc_html_e('Horizontal', 'dashd-analytics-pro'); ?></div>
                        <div class="dashd-selector-label <?php echo $bar_orientation === 'vertical' ? 'active' : ''; ?>" data-orientation="vertical"><?php esc_html_e('Vertical', 'dashd-analytics-pro'); ?></div>
                    </div>
                    <div class="dashd-view-selector dashd-toggle-stacked" data-html2canvas-ignore="true" style="<?php echo $show_bar_controls_ui ? '' : 'display:none;'; ?>">
                        <div class="dashd-selector-label <?php echo $bar_stacked ? 'active' : ''; ?>" data-stacked="true"><?php esc_html_e('Stacked', 'dashd-analytics-pro'); ?></div>
                        <div class="dashd-selector-label <?php echo !$bar_stacked ? 'active' : ''; ?>" data-stacked="false"><?php esc_html_e('Normal', 'dashd-analytics-pro'); ?></div>
                    </div>
                </div>
                <div class="dashd-mobile-controls" data-html2canvas-ignore="true">
                    <label class="dashd-mobile-field dashd-mobile-field-view" style="<?php echo !$show_view_toggle ? 'display:none;' : ''; ?>">
                        <span><?php esc_html_e('View', 'dashd-analytics-pro'); ?></span>
                        <select class="dashd-mobile-select dashd-mobile-view">
                            <option value="bar" <?php selected($mode, 'bar'); ?>><?php esc_html_e('Bar', 'dashd-analytics-pro'); ?></option>
                            <option value="line" <?php selected($mode, 'line'); ?>><?php esc_html_e('Line', 'dashd-analytics-pro'); ?></option>
                            <option value="donut" <?php selected($mode, 'donut'); ?>><?php esc_html_e('Donut', 'dashd-analytics-pro'); ?></option>
                        </select>
                    </label>
                    <label class="dashd-mobile-field dashd-mobile-field-scale" style="<?php echo !$show_scale_toggle ? 'display:none;' : ''; ?>">
                        <span><?php esc_html_e('Scale', 'dashd-analytics-pro'); ?></span>
                        <select class="dashd-mobile-select dashd-mobile-scale">
                            <option value="linear" <?php selected($scale, 'linear'); ?>><?php esc_html_e('Linear', 'dashd-analytics-pro'); ?></option>
                            <option value="logarithmic" <?php selected($scale, 'logarithmic'); ?>><?php esc_html_e('Logarithmic', 'dashd-analytics-pro'); ?></option>
                        </select>
                    </label>
                    <label class="dashd-mobile-field dashd-mobile-field-orientation" style="<?php echo $show_bar_controls_ui ? '' : 'display:none;'; ?>">
                        <span><?php esc_html_e('Bars', 'dashd-analytics-pro'); ?></span>
                        <select class="dashd-mobile-select dashd-mobile-orientation">
                            <option value="horizontal" <?php selected($bar_orientation, 'horizontal'); ?>><?php esc_html_e('Horizontal', 'dashd-analytics-pro'); ?></option>
                            <option value="vertical" <?php selected($bar_orientation, 'vertical'); ?>><?php esc_html_e('Vertical', 'dashd-analytics-pro'); ?></option>
                        </select>
                    </label>
                    <label class="dashd-mobile-field dashd-mobile-field-stacked" style="<?php echo $show_bar_controls_ui ? '' : 'display:none;'; ?>">
                        <span><?php esc_html_e('Bar Type', 'dashd-analytics-pro'); ?></span>
                        <select class="dashd-mobile-select dashd-mobile-stacked">
                            <option value="true" <?php selected($bar_stacked, true); ?>><?php esc_html_e('Stacked', 'dashd-analytics-pro'); ?></option>
                            <option value="false" <?php selected($bar_stacked, false); ?>><?php esc_html_e('Normal', 'dashd-analytics-pro'); ?></option>
                        </select>
                    </label>
                    <label class="dashd-mobile-field dashd-mobile-field-year" style="<?php echo !$show_periods ? 'display:none;' : ''; ?>">
                        <span><?php esc_html_e('Year', 'dashd-analytics-pro'); ?></span>
                        <select class="dashd-mobile-select dashd-mobile-year"></select>
                    </label>
                    <label class="dashd-mobile-field dashd-mobile-field-quarter" style="<?php echo !$show_periods ? 'display:none;' : ''; ?>">
                        <span><?php esc_html_e('Quarter', 'dashd-analytics-pro'); ?></span>
                        <select class="dashd-mobile-select dashd-mobile-quarter"></select>
                    </label>
                </div>
                <div class="dashd-periods-wrap uk-flex dashd-period-controls" style="gap:10px;<?php echo !$show_periods ? 'display:none;' : ''; ?>">
                    <div class="dashd-year-btns uk-flex dashd-period-buttons" style="gap:5px;"></div>
                    <div class="dashd-q-btns uk-flex dashd-period-buttons" style="gap:5px;"></div>
                </div>
            </div>
        </div>

        <div class="dashd-chart-box" style="height:<?php echo esc_attr($height); ?>; background:#fff; padding:15px; border-radius:8px; border:1px solid #f0f0f0; position:relative; overflow: hidden;">
            <div class="dashd-loader-overlay" style="display: flex; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.7); z-index: 10; align-items: center; justify-content: center; backdrop-filter: blur(2px); transition: opacity 0.3s ease;">
                <div style="width: 40px; height: 40px; border: 3px solid rgba(30, 135, 240, 0.2); border-radius: 50%; border-top-color: #1e87f0; animation: spin 1s ease-in-out infinite;"></div>
            </div>
            <canvas class="dashd-canvas"></canvas>
        </div>
        <div class="dashd-html-legend" data-html2canvas-ignore="false"></div>
        <div class="dashd-data-quality-warning" style="display:none; margin-top:10px; padding:8px 12px; border:1px solid #fed7aa; border-radius:8px; background:#fff7ed; color:#9a3412; font-size:12px; font-weight:600;"></div>

        <div class="uk-flex uk-flex-between uk-flex-middle uk-margin-top">
            <div style="display: flex; gap: 15px; font-size: 13px;" data-html2canvas-ignore="true">
                <span class="dashd-export-csv" style="cursor:pointer; color:#1e87f0; display:flex; align-items:center; gap:4px; font-weight:600;" title="<?php esc_attr_e('Download Data as CSV', 'dashd-analytics-pro'); ?>">
                    <span class="dashicons dashicons-media-spreadsheet" style="font-size:16px; line-height:1;"></span> CSV
                </span>
                <span class="dashd-export-pdf" style="cursor:pointer; color:#1e87f0; display:flex; align-items:center; gap:4px; font-weight:600;" title="<?php esc_attr_e('Download Dashboard as PDF', 'dashd-analytics-pro'); ?>">
                    <span class="dashicons dashicons-pdf" style="font-size:16px; line-height:1;"></span> PDF
                </span>
            </div>

            <div id="toggle-<?php echo esc_attr($uid); ?>" style="cursor:pointer; user-select:none; display:flex; align-items:center; gap:10px;" data-html2canvas-ignore="true">
                <div class="dashd-hamburger"><span></span><span></span><span></span></div>
                <div style="font-weight: 600; color: #1e87f0;"><?php esc_html_e('View Detailed Table', 'dashd-analytics-pro'); ?></div>
            </div>
        </div>

        <div id="tb-<?php echo esc_attr($uid); ?>" class="dashd-table-wrapper uk-margin-top" style="display: none;">
            <div class="dashd-table-scroll-container">
                <table class="dashd-table">
                    <thead><tr class="dashd-thead"></tr></thead>
                    <tbody class="dashd-tbody"></tbody>
                </table>
            </div>
        </div>

        <div class="dashd-last-sync-time" style="font-size: 11px; color: #94a3b8; text-align: right; margin-top: 10px; font-style: italic;"></div>

        <?php if($signature): ?>
        <div class="dashd-pdf-footer" style="display:none; margin-top: 30px; padding-top: 15px; border-top: 1px solid #e5e5e5; font-size: 11px; color: #646970; text-align: center;">
            <?php echo $signature; ?>
        </div>
        <?php endif; ?>
    </div>

<script data-dashd-widget-boot="1">
(function() {
        const rootId = '<?php echo esc_js($uid); ?>';
        const config = <?php echo wp_json_encode($js_config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const startWidget = () => {
            const root = document.getElementById(rootId);
            if (!root) {
                return false;
            }
            if (root.dataset.dashdInited === '1') {
                return true;
            }
            root.dataset.dashdInited = '1';

        const i18n = {
            allCountries: "<?php echo esc_js(__('All Countries', 'dashd-analytics-pro')); ?>",
            analyticsOverview: "<?php echo esc_js(__('Analytics Overview', 'dashd-analytics-pro')); ?>",
            indicator: "<?php echo esc_js(__('Indicator', 'dashd-analytics-pro')); ?>",
            trend: "<?php echo esc_js(__('Trend', 'dashd-analytics-pro')); ?>",
            total: "<?php echo esc_js(__('Total', 'dashd-analytics-pro')); ?>",
            lastUpdated: "<?php echo esc_js(__('Last updated:', 'dashd-analytics-pro')); ?>",
            wait: "<?php echo esc_js(__('Wait...', 'dashd-analytics-pro')); ?>",
            verifying: "<?php echo esc_js(__('Verifying...', 'dashd-analytics-pro')); ?>",
            unlockAndDownload: "<?php echo esc_js(__('Unlock & Download', 'dashd-analytics-pro')); ?>",
            invalidEmail: "<?php echo esc_js(__('Please enter a valid email address.', 'dashd-analytics-pro')); ?>",
            pdfLibsLoading: "<?php echo esc_js(__('PDF libraries are still loading. Please try again in a second.', 'dashd-analytics-pro')); ?>",
            errorPdf: "<?php echo esc_js(__('Error generating PDF.', 'dashd-analytics-pro')); ?>",
            errorServer: "<?php echo esc_js(__('Server connection error.', 'dashd-analytics-pro')); ?>",
            errorGeneral: "<?php echo esc_js(__('An error occurred.', 'dashd-analytics-pro')); ?>",
            negativeValueWarning: "<?php echo esc_js(__('Some values are negative and should be verified with the data owner.', 'dashd-analytics-pro')); ?>",
            negativeValueTooltip: "<?php echo esc_js(__('Warning: negative value, needs review.', 'dashd-analytics-pro')); ?>",
            donutNegativeFallback: "<?php echo esc_js(__('Donut view is not suitable for negative values. Showing bar view instead.', 'dashd-analytics-pro')); ?>",
            logNegativeFallback: "<?php echo esc_js(__('Log scale is not suitable for negative values. Showing linear scale instead.', 'dashd-analytics-pro')); ?>"
        };

        let chart = null, rawData = null, trendData = null,
            viewMode = config.viewMode, scaleMode = config.scaleMode,
            barOrientation = config.barOrientation || 'horizontal',
            barStacked = Boolean(config.barStacked),
            curCty = i18n.allCountries, curY = null, curQ = null;
        let periodYears = [];
        let periodQuarters = ['Q4', 'Q3', 'Q2', 'Q1'];
        let periodYearQuarterMap = {};

        let sparklines = [];
        const hiddenLegendKeys = new Set();
        const controls = {
            periodsWrap: root.querySelector('.dashd-periods-wrap'),
            yearButtonsBox: root.querySelector('.dashd-year-btns'),
            quarterButtonsBox: root.querySelector('.dashd-q-btns'),
            desktopViewToggle: root.querySelector('.dashd-toggle-view'),
            desktopScaleToggle: root.querySelector('.dashd-toggle-scale'),
            desktopOrientationToggle: root.querySelector('.dashd-toggle-orientation'),
            desktopStackedToggle: root.querySelector('.dashd-toggle-stacked'),
            mobileViewSelect: root.querySelector('.dashd-mobile-view'),
            mobileScaleSelect: root.querySelector('.dashd-mobile-scale'),
            mobileOrientationSelect: root.querySelector('.dashd-mobile-orientation'),
            mobileStackedSelect: root.querySelector('.dashd-mobile-stacked'),
            mobileYearSelect: root.querySelector('.dashd-mobile-year'),
            mobileQuarterSelect: root.querySelector('.dashd-mobile-quarter'),
            mobileFieldView: root.querySelector('.dashd-mobile-field-view'),
            mobileFieldScale: root.querySelector('.dashd-mobile-field-scale'),
            mobileFieldOrientation: root.querySelector('.dashd-mobile-field-orientation'),
            mobileFieldStacked: root.querySelector('.dashd-mobile-field-stacked'),
            mobileFieldYear: root.querySelector('.dashd-mobile-field-year'),
            mobileFieldQuarter: root.querySelector('.dashd-mobile-field-quarter'),
            mobileControlsWrap: root.querySelector('.dashd-mobile-controls')
        };

        const getAvailableQuartersForYear = (year) => {
            const y = String(year ?? '');
            if (!y || !periodYearQuarterMap || typeof periodYearQuarterMap !== 'object') {
                return periodQuarters.slice();
            }
            const available = Array.isArray(periodYearQuarterMap[y])
                ? periodYearQuarterMap[y].map((q) => String(q || '').toUpperCase()).filter(Boolean)
                : [];
            const normalized = periodQuarters.filter((q) => available.includes(q));
            return normalized.length ? normalized : periodQuarters.slice();
        };

        const ensureValidQuarterForYear = () => {
            const available = getAvailableQuartersForYear(curY);
            if (!available.length) {
                curQ = null;
                return;
            }
            const current = String(curQ ?? '').toUpperCase();
            if (!current || !available.includes(current)) {
                curQ = available[0];
            }
        };

        const renderQuarterControlsForCurrentYear = () => {
            const available = getAvailableQuartersForYear(curY);
            const qBox = controls.quarterButtonsBox;
            if (qBox) {
                qBox.innerHTML = periodQuarters.map((q) => {
                    const isAvailable = available.includes(q);
                    const isActive = String(q) === String(curQ);
                    return `<button class="dashd-ui-btn ${isActive ? 'active-btn' : ''}" data-v="${escapeHtml(q)}"${isAvailable ? '' : ' disabled aria-disabled="true" style="opacity:.45;cursor:not-allowed;"'}>${escapeHtml(q)}</button>`;
                }).join('');
                qBox.querySelectorAll('button').forEach((b) => {
                    b.onclick = () => {
                        const nextQuarter = String(b.dataset.v || '');
                        if (!nextQuarter) return;
                        const allowed = getAvailableQuartersForYear(curY);
                        if (!allowed.includes(nextQuarter)) return;
                        curQ = nextQuarter;
                        syncPeriodButtons();
                        syncMobileSelectors();
                        loadData();
                    };
                });
            }

            if (controls.mobileQuarterSelect) {
                controls.mobileQuarterSelect.innerHTML = available
                    .map((q) => `<option value="${escapeHtml(q)}">${escapeHtml(q)}</option>`)
                    .join('');
            }
        };

        const loader = root.querySelector('.dashd-loader-overlay');
        const showLoader = () => { if (loader) { loader.style.display = 'flex'; setTimeout(() => loader.style.opacity = '1', 10); } };
        const hideLoader = () => { if (loader) { loader.style.opacity = '0'; setTimeout(() => loader.style.display = 'none', 300); } };

        const formatNum = (value) => {
            if (value === null || value === undefined) return '';
            const locale = config.lang === 'en' ? 'en-US' : 'ru-RU';
            return new Intl.NumberFormat(locale).format(Number(value));
        };

        const escapeHtml = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

        const DATA_QUALITY_WARNING_COLOR = '#f97316';
        const dataWarningsEnabled = config.showDataWarnings !== false;
        const hasNegativeValues = (data) => {
            if (!data || !Array.isArray(data.datasets)) return false;
            return data.datasets.some((dataset) => {
                const values = Array.isArray(dataset?.data) ? dataset.data : [];
                return values.some((value) => {
                    const num = Number(value);
                    return Number.isFinite(num) && num < 0;
                });
            });
        };
        const isNegativeContextValue = (context) => {
            const raw = Number(context?.raw ?? 0);
            if (Number.isFinite(raw)) return raw < 0;
            const parsed = context?.parsed;
            if (typeof parsed === 'number') return parsed < 0;
            if (parsed && typeof parsed === 'object') {
                const x = Number(parsed.x);
                const y = Number(parsed.y);
                return (Number.isFinite(x) && x < 0) || (Number.isFinite(y) && y < 0);
            }
            return false;
        };
        const resolveDatasetColor = (color, context, fallback = '#1e87f0') => {
            if (typeof color === 'function') {
                try {
                    return color(context);
                } catch (e) {
                    return fallback;
                }
            }
            if (Array.isArray(color)) {
                const idx = Number(context?.dataIndex ?? 0);
                return color[Math.abs(idx) % color.length] || fallback;
            }
            return color || fallback;
        };
        const applyNegativeDatasetWarnings = (dataset, chartMode) => {
            const next = { ...dataset };
            if (!dataWarningsEnabled) {
                return next;
            }
            const existingBorderColor = dataset.borderColor || dataset.backgroundColor || config.colors;

            if (chartMode === 'line') {
                next.pointRadius = (ctx) => isNegativeContextValue(ctx) ? 5 : 3;
                next.pointHoverRadius = (ctx) => isNegativeContextValue(ctx) ? 7 : 5;
                next.pointBorderWidth = (ctx) => isNegativeContextValue(ctx) ? 3 : 1;
                next.pointBorderColor = (ctx) => isNegativeContextValue(ctx) ? DATA_QUALITY_WARNING_COLOR : (dataset.borderColor || '#fff');
                next.pointBackgroundColor = dataset.borderColor || dataset.backgroundColor || DATA_QUALITY_WARNING_COLOR;
                return next;
            }

            if (chartMode === 'bar') {
                next.borderColor = (ctx) => isNegativeContextValue(ctx) ? DATA_QUALITY_WARNING_COLOR : resolveDatasetColor(existingBorderColor, ctx);
                next.borderWidth = (ctx) => isNegativeContextValue(ctx) ? 2 : 0;
                next.hoverBorderColor = (ctx) => isNegativeContextValue(ctx) ? DATA_QUALITY_WARNING_COLOR : resolveDatasetColor(existingBorderColor, ctx);
                next.hoverBorderWidth = (ctx) => isNegativeContextValue(ctx) ? 3 : 1;
                return next;
            }

            return next;
        };
        const updateDataQualityWarning = (hasWarnings, details = []) => {
            const warning = root.querySelector('.dashd-data-quality-warning');
            if (!warning) return;
            if (!dataWarningsEnabled || !hasWarnings) {
                warning.style.display = 'none';
                warning.textContent = '';
                return;
            }

            const parts = [i18n.negativeValueWarning].concat(details.filter(Boolean));
            warning.textContent = parts.join(' ');
            warning.style.display = 'block';
        };
        const renderValueWithWarning = (value) => {
            if (!dataWarningsEnabled) {
                return `<div style="font-size:13px;">${formatNum(value)}</div>`;
            }
            const num = Number(value);
            const isNegative = Number.isFinite(num) && num < 0;
            const valueClass = isNegative ? 'dashd-cell-negative' : '';
            const badge = isNegative
                ? `<div class="dashd-cell-warning-badge">${escapeHtml(i18n.negativeValueTooltip)}</div>`
                : '';
            return `<div class="${valueClass}" style="font-size:13px;">${formatNum(value)}</div>${badge}`;
        };

        const normalizeCountryName = (value) => String(value ?? '')
            .toLowerCase()
            .replace(/\s+/g, ' ')
            .trim();

        const preferredCountryOrder = Array.isArray(config.countryOrder)
            ? config.countryOrder.map((name) => String(name ?? '').trim()).filter(Boolean)
            : [];
        const preferredCountryOrderMap = new Map(
            preferredCountryOrder.map((name, index) => [normalizeCountryName(name), index])
        );

        const sortCountriesByPreference = (countries) => {
            if (!Array.isArray(countries)) return [];
            if (!preferredCountryOrderMap.size) return countries.slice();

            return countries.slice().sort((a, b) => {
                const nameA = String(a ?? '');
                const nameB = String(b ?? '');
                const keyA = normalizeCountryName(nameA);
                const keyB = normalizeCountryName(nameB);
                const posA = preferredCountryOrderMap.has(keyA) ? preferredCountryOrderMap.get(keyA) : Number.POSITIVE_INFINITY;
                const posB = preferredCountryOrderMap.has(keyB) ? preferredCountryOrderMap.get(keyB) : Number.POSITIVE_INFINITY;

                if (posA !== posB) return posA - posB;
                return nameA.localeCompare(nameB, undefined, { sensitivity: 'base' });
            });
        };

        const appendPeriodRangeParams = (url) => {
            let nextUrl = String(url || '');
            if (config.periodStart) {
                nextUrl += `&period_start=${encodeURIComponent(config.periodStart)}`;
            }
            if (config.periodEnd) {
                nextUrl += `&period_end=${encodeURIComponent(config.periodEnd)}`;
            }
            return nextUrl;
        };

        const getCountryFlagUrl = (countryName) => {
            const sources = [
                rawData && rawData.country_flags ? rawData.country_flags : null,
                trendData && trendData.country_flags ? trendData.country_flags : null
            ];
            const directName = String(countryName ?? '').trim();
            const normalizedName = normalizeCountryName(directName);
            for (const source of sources) {
                if (!source || typeof source !== 'object') continue;
                if (source[directName]) return String(source[directName]);
                const matchedKey = Object.keys(source).find((key) => normalizeCountryName(key) === normalizedName);
                if (matchedKey && source[matchedKey]) return String(source[matchedKey]);
            }
            return '';
        };

        const getLegendItemColor = (dataset) => {
            const bg = dataset ? dataset.backgroundColor : '';
            if (Array.isArray(bg)) {
                return String(bg.find(Boolean) || '#94a3b8');
            }
            return String(bg || dataset?.borderColor || '#94a3b8');
        };

        const shouldUseFlagLegend = (chartData) => {
            if (!chartData || !Array.isArray(chartData.datasets)) return false;
            return chartData.datasets.some((dataset) => getCountryFlagUrl(dataset?.label) !== '');
        };

        const renderFlagLegend = (chartData) => {
            const legendBox = root.querySelector('.dashd-html-legend');
            if (!legendBox) return;
            if (!chart || !shouldUseFlagLegend(chartData)) {
                legendBox.innerHTML = '';
                legendBox.style.display = 'none';
                return;
            }

            legendBox.style.display = 'flex';
            legendBox.innerHTML = chartData.datasets.map((dataset, datasetIndex) => {
                const label = String(dataset?.label ?? '');
                const flagUrl = getCountryFlagUrl(label);
                const color = getLegendItemColor(dataset);
                const hiddenClass = chart.isDatasetVisible(datasetIndex) ? '' : ' is-hidden';
                const marker = flagUrl
                    ? `<span class="dashd-legend-flag" style="--dashd-legend-color:${escapeHtml(color)};"><img src="${escapeHtml(flagUrl)}" alt=""></span>`
                    : `<span class="dashd-legend-color" style="background:${escapeHtml(color)};"></span>`;
                return `<button type="button" class="dashd-html-legend-item${hiddenClass}" data-dataset-index="${datasetIndex}">${marker}<span>${escapeHtml(label)}</span></button>`;
            }).join('');

            legendBox.querySelectorAll('.dashd-html-legend-item').forEach((button) => {
                button.addEventListener('click', () => {
                    const datasetIndex = Number(button.dataset.datasetIndex);
                    if (!Number.isInteger(datasetIndex) || !chart?.data?.datasets?.[datasetIndex]) return;
                    const dataset = chart.data.datasets[datasetIndex];
                    const legendKey = dataset._dashdLegendKey || `${viewMode}|${curCty}|${dataset.label || datasetIndex}`;
                    const shouldShow = !chart.isDatasetVisible(datasetIndex);

                    chart.setDatasetVisibility(datasetIndex, shouldShow);
                    if (shouldShow) hiddenLegendKeys.delete(legendKey);
                    else hiddenLegendKeys.add(legendKey);
                    button.classList.toggle('is-hidden', !shouldShow);
                    chart.update();
                });
            });
        };

        const countryColorMap = new Map();
        const hashString = (value) => {
            const input = String(value ?? '').toLowerCase().trim();
            let hash = 0;
            for (let i = 0; i < input.length; i++) {
                hash = ((hash << 5) - hash) + input.charCodeAt(i);
                hash |= 0;
            }
            return Math.abs(hash);
        };
        const getCountryColor = (countryName, fallbackIndex = 0) => {
            const palette = Array.isArray(config.colors) ? config.colors : [];
            if (!palette.length) return '#1e87f0';

            const key = String(countryName ?? '').trim();
            if (key === '') {
                return (palette[Math.abs(fallbackIndex) % palette.length] || '#1e87f0').trim();
            }
            if (countryColorMap.has(key)) {
                return countryColorMap.get(key);
            }

            let index = hashString(key) % palette.length;
            if (palette.length > countryColorMap.size) {
                const used = new Set(Array.from(countryColorMap.values()));
                let guard = 0;
                while (used.has((palette[index] || '').trim()) && guard < palette.length) {
                    index = (index + 1) % palette.length;
                    guard++;
                }
            }

            const color = (palette[index] || palette[Math.abs(fallbackIndex) % palette.length] || '#1e87f0').trim();
            countryColorMap.set(key, color);
            return color;
        };

        const BAR_RADIUS_BY_WIDTH_RATIO = 0.4;
        const BAR_RADIUS_MIN = 2;
        const BAR_RADIUS_MAX = 14;
        const GROUPED_CATEGORY_PERCENTAGE = 0.74;
        const GROUPED_BAR_PERCENTAGE = 0.86;

        const getAdaptiveBarRadius = (ctx) => {
            const chart = ctx?.chart;
            const datasetIndex = Number(ctx?.datasetIndex ?? -1);
            const dataIndex = Number(ctx?.dataIndex ?? -1);
            if (!chart || datasetIndex < 0 || dataIndex < 0) {
                return BAR_RADIUS_MIN;
            }

            const meta = chart.getDatasetMeta(datasetIndex);
            const element = meta?.data?.[dataIndex];
            if (!element) {
                return BAR_RADIUS_MIN;
            }

            // Bind radius to the bar width (column thickness), not value length.
            const indexAxis = String(chart?.options?.indexAxis || 'x');
            const explicitWidth = indexAxis === 'y' ? Number(element.height) : Number(element.width);
            const fallbackWidth = Math.min(
                Number.isFinite(Number(element.width)) ? Number(element.width) : Number.POSITIVE_INFINITY,
                Number.isFinite(Number(element.height)) ? Number(element.height) : Number.POSITIVE_INFINITY
            );
            const barWidth = Number.isFinite(explicitWidth) && explicitWidth > 0
                ? explicitWidth
                : (Number.isFinite(fallbackWidth) && fallbackWidth > 0 ? fallbackWidth : 0);

            if (!barWidth) {
                return BAR_RADIUS_MIN;
            }

            const radius = barWidth * BAR_RADIUS_BY_WIDTH_RATIO;
            return Math.max(BAR_RADIUS_MIN, Math.min(BAR_RADIUS_MAX, radius));
        };

        const getFullBarRadius = () => {
            return (ctx) => {
                const value = Number(ctx?.raw ?? 0);
                if (!Number.isFinite(value) || value === 0) {
                    return 0;
                }
                return getAdaptiveBarRadius(ctx);
            };
        };

        const getStackRadiusByEdge = (orientation, sign, edge, radius) => {
            const isVertical = orientation === 'vertical';
            if (isVertical && sign < 0 && edge === 'start') {
                return { topLeft: radius, bottomLeft: 0, topRight: radius, bottomRight: 0 };
            }
            if (isVertical && sign < 0 && edge === 'end') {
                return { topLeft: 0, bottomLeft: radius, topRight: 0, bottomRight: radius };
            }
            if (isVertical && edge === 'start') {
                return { topLeft: 0, bottomLeft: radius, topRight: 0, bottomRight: radius };
            }
            if (isVertical && edge === 'end') {
                return { topLeft: radius, bottomLeft: 0, topRight: radius, bottomRight: 0 };
            }
            if (sign < 0 && edge === 'start') {
                return { topLeft: 0, bottomLeft: 0, topRight: radius, bottomRight: radius };
            }
            if (sign < 0 && edge === 'end') {
                return { topLeft: radius, bottomLeft: radius, topRight: 0, bottomRight: 0 };
            }
            if (edge === 'start') {
                return { topLeft: radius, bottomLeft: radius, topRight: 0, bottomRight: 0 };
            }
            return { topLeft: 0, bottomLeft: 0, topRight: radius, bottomRight: radius };
        };

        const getStackSegmentRadius = (countryIndex, valuesByCountry, countries, orientation = 'horizontal') => {
            return (ctx) => {
                const pointIndex = Number(ctx?.dataIndex ?? -1);
                if (pointIndex < 0) return 0;

                const country = countries[countryIndex];
                const current = Number(valuesByCountry[country]?.[pointIndex] ?? 0);
                if (!Number.isFinite(current) || current === 0) {
                    return 0;
                }
                const currentSign = current < 0 ? -1 : 1;

                let firstVisible = -1;
                let lastVisible = -1;
                for (let i = 0; i < countries.length; i++) {
                    const cName = countries[i];
                    const v = Number(valuesByCountry[cName]?.[pointIndex] ?? 0);
                    if (!Number.isFinite(v) || v === 0 || (v < 0 ? -1 : 1) !== currentSign) continue;
                    if (firstVisible === -1) firstVisible = i;
                    lastVisible = i;
                }

                if (firstVisible === -1) return 0;
                const radius = getAdaptiveBarRadius(ctx);
                if (firstVisible === lastVisible) return radius;
                if (countryIndex === firstVisible) {
                    return getStackRadiusByEdge(orientation, currentSign, 'start', radius);
                }
                if (countryIndex === lastVisible) {
                    return getStackRadiusByEdge(orientation, currentSign, 'end', radius);
                }
                return 0;
            };
        };

        const getConfiguredIndicatorCount = () => {
            const source = (Array.isArray(config.indicatorSpecs) && config.indicatorSpecs.length)
                ? config.indicatorSpecs
                : (Array.isArray(config.indicatorIds) ? config.indicatorIds : []);
            return new Set(source.map((v) => String(v ?? '').trim()).filter(Boolean)).size;
        };

        const isSingleIndicatorYearMode = () => (viewMode === 'bar' && getConfiguredIndicatorCount() === 1);
        const syncWidgetTitle = () => {
            const titleEl = root.querySelector('.dashd-widget-title');
            if (!titleEl) return;

            let title = String(titleEl.dataset.defaultTitle || i18n.analyticsOverview || 'Analytics Overview');
            if (getConfiguredIndicatorCount() === 1) {
                let source = null;
                if (trendData && trendData.indicators) {
                    source = trendData.indicators;
                } else if (rawData && rawData.indicators) {
                    source = rawData.indicators;
                }
                if (source) {
                    const indicatorNames = Object.keys(source).filter(Boolean);
                    if (indicatorNames.length === 1) {
                        title = indicatorNames[0];
                    }
                }
            }

            titleEl.textContent = title;
        };

        const syncDesktopSelectors = () => {
            root.querySelectorAll('.dashd-toggle-view .dashd-selector-label').forEach((el) => {
                el.classList.toggle('active', el.dataset.type === viewMode);
            });
            root.querySelectorAll('.dashd-toggle-scale .dashd-selector-label').forEach((el) => {
                el.classList.toggle('active', el.dataset.scale === scaleMode);
            });
            root.querySelectorAll('.dashd-toggle-orientation .dashd-selector-label').forEach((el) => {
                el.classList.toggle('active', el.dataset.orientation === barOrientation);
            });
            root.querySelectorAll('.dashd-toggle-stacked .dashd-selector-label').forEach((el) => {
                el.classList.toggle('active', String(el.dataset.stacked) === String(barStacked));
            });
        };

        const syncPeriodButtons = () => {
            if (controls.yearButtonsBox) {
                controls.yearButtonsBox.querySelectorAll('button').forEach((btn) => {
                    btn.classList.toggle('active-btn', String(btn.dataset.v) === String(curY));
                });
            }
            if (controls.quarterButtonsBox) {
                controls.quarterButtonsBox.querySelectorAll('button').forEach((btn) => {
                    btn.classList.toggle('active-btn', String(btn.dataset.v) === String(curQ));
                });
            }
        };

        const syncMobileSelectors = () => {
            if (controls.mobileViewSelect) {
                controls.mobileViewSelect.value = viewMode;
            }
            if (controls.mobileScaleSelect) {
                controls.mobileScaleSelect.value = scaleMode;
            }
            if (controls.mobileOrientationSelect) {
                controls.mobileOrientationSelect.value = barOrientation;
            }
            if (controls.mobileStackedSelect) {
                controls.mobileStackedSelect.value = barStacked ? 'true' : 'false';
            }
            if (controls.mobileYearSelect && curY !== null) {
                controls.mobileYearSelect.value = String(curY);
            }
            if (controls.mobileQuarterSelect && curQ !== null) {
                controls.mobileQuarterSelect.value = String(curQ);
            }
        };

        const syncPeriodControlVisibility = () => {
            const hidePeriods = !Boolean(config.showPeriods) || (viewMode === 'line') || isSingleIndicatorYearMode();
            const yearField = controls.mobileFieldYear;
            const quarterField = controls.mobileFieldQuarter;

            if (controls.periodsWrap) {
                controls.periodsWrap.style.display = hidePeriods ? 'none' : 'flex';
            }
            if (yearField) {
                yearField.style.display = hidePeriods ? 'none' : 'flex';
            }
            if (quarterField) {
                quarterField.style.display = hidePeriods ? 'none' : 'flex';
            }
            if (controls.mobileYearSelect) {
                controls.mobileYearSelect.disabled = hidePeriods || controls.mobileYearSelect.options.length === 0;
            }
            if (controls.mobileQuarterSelect) {
                controls.mobileQuarterSelect.disabled = hidePeriods || controls.mobileQuarterSelect.options.length === 0;
            }

            if (controls.desktopViewToggle) {
                controls.desktopViewToggle.style.display = Boolean(config.showViewToggle) ? '' : 'none';
            }
            if (controls.desktopScaleToggle) {
                controls.desktopScaleToggle.style.display = Boolean(config.showScaleToggle) ? '' : 'none';
            }
            if (controls.mobileFieldView) {
                controls.mobileFieldView.style.display = Boolean(config.showViewToggle) ? 'flex' : 'none';
            }
            if (controls.mobileFieldScale) {
                controls.mobileFieldScale.style.display = Boolean(config.showScaleToggle) ? 'flex' : 'none';
            }

            const showBarControls = Boolean(config.showBarControlsUI) && viewMode === 'bar';
            if (controls.desktopOrientationToggle) {
                controls.desktopOrientationToggle.style.display = showBarControls ? '' : 'none';
            }
            if (controls.desktopStackedToggle) {
                controls.desktopStackedToggle.style.display = showBarControls ? '' : 'none';
            }
            if (controls.mobileFieldOrientation) {
                controls.mobileFieldOrientation.style.display = showBarControls ? 'flex' : 'none';
            }
            if (controls.mobileFieldStacked) {
                controls.mobileFieldStacked.style.display = showBarControls ? 'flex' : 'none';
            }

            const showDesktopControls = Boolean(config.showViewToggle) || Boolean(config.showScaleToggle) || showBarControls;
            const desktopControlsWrap = controls.desktopViewToggle ? controls.desktopViewToggle.parentNode : null;
            if (desktopControlsWrap) {
                desktopControlsWrap.style.display = showDesktopControls ? 'flex' : 'none';
            }

            const showMobileControls = Boolean(config.showViewToggle) || Boolean(config.showScaleToggle) || Boolean(config.showPeriods) || showBarControls;
            if (controls.mobileControlsWrap) {
                controls.mobileControlsWrap.style.display = showMobileControls ? '' : 'none';
            }
        };

        const axisDict = { en: { k: 'Thousands', m: 'Millions', b: 'Billions' }, uk: { k: 'Тисячі', m: 'Мільйони', b: 'Мільярди' }, ru: { k: 'Тысячи', m: 'Миллионы', b: 'Миллиарды' }, hy: { k: 'Հազարներ', m: 'Միլիոններ', b: 'Միլիարդներ' }, ro: { k: 'Mii', m: 'Milioane', b: 'Miliarde' }, ka: { k: 'ათასები', m: 'მილიონები', b: 'მილიარდები' } };

        const wrapAxisLabel = (rawValue) => {
            const text = String(rawValue ?? '').replace(/\s+/g, ' ').trim();
            if (!text) return '';

            const isMobile = window.matchMedia('(max-width: 640px) and (orientation: portrait)').matches;
            const maxCharsPerLine = isMobile ? 16 : 28;
            const maxLines = 3;

            const words = text.split(' ');
            const lines = [];
            let current = '';

            for (let i = 0; i < words.length; i++) {
                const word = words[i];
                const candidate = current ? `${current} ${word}` : word;
                if (candidate.length <= maxCharsPerLine) {
                    current = candidate;
                    continue;
                }

                if (current) {
                    lines.push(current);
                    current = '';
                }

                if (word.length <= maxCharsPerLine) {
                    current = word;
                } else {
                    // Hard-wrap single oversized token.
                    let rest = word;
                    while (rest.length > maxCharsPerLine) {
                        lines.push(rest.slice(0, maxCharsPerLine - 1) + '…');
                        rest = rest.slice(maxCharsPerLine - 1);
                        if (lines.length >= maxLines) break;
                    }
                    if (lines.length >= maxLines) break;
                    current = rest;
                }

                if (lines.length >= maxLines) break;
            }

            if (current && lines.length < maxLines) {
                lines.push(current);
            }

            if (lines.length === 1) {
                return lines[0];
            }

            if (lines.length > maxLines) {
                return lines.slice(0, maxLines);
            }

            return lines;
        };

        const loadData = async () => {
            showLoader();
            syncPeriodControlVisibility();

            let url = `${config.ajax}?action=get_dashd_modern_data&key=${config.key}&lang=${config.lang}`;
            if (Array.isArray(config.indicatorSpecs) && config.indicatorSpecs.length) {
                url += `&indicators=${encodeURIComponent(config.indicatorSpecs.join(','))}`;
            } else if (Array.isArray(config.indicatorIds) && config.indicatorIds.length) {
                url += `&indicators=${encodeURIComponent(config.indicatorIds.join(','))}`;
            }
            url = appendPeriodRangeParams(url);
            if (viewMode === 'line' || isSingleIndicatorYearMode()) {
                url += '&all=true';
            } else {
                if (curY && curQ) url += `&year=${curY}&q=${curQ}`;
            }

            try {
                const res = await fetch(url);
                if (!res.ok) throw new Error(`HTTP Error: ${res.status}`);

                const json = await res.json();

                if (json.success) {
                    if (json.data && Array.isArray(json.data.countries)) {
                        json.data.countries = sortCountriesByPreference(json.data.countries);
                    }
                    if (viewMode === 'line' || isSingleIndicatorYearMode()) trendData = json.data; else rawData = json.data;
                    if (json.data.year) curY = json.data.year;
                    if (json.data.quarter) curQ = json.data.quarter;
                    syncPeriodButtons();
                    syncMobileSelectors();
                    const syncDiv = root.querySelector('.dashd-last-sync-time');
                    if (syncDiv && json.data.last_sync) syncDiv.innerText = `${i18n.lastUpdated} ` + json.data.last_sync;
                    render();
                } else {
                    console.error("DashD API Error:", json);
                    hideLoader();
                }
            } catch (error) {
                console.error("DashD Network/Parse Error in loadData:", error);
                hideLoader();
            }
        };

        const getLineDataForCountry = (indName) => {
            let historyData = new Array(trendData.periods.length).fill(0);
            if (curCty === i18n.allCountries) {
                trendData.countries.forEach(c => {
                    if (trendData.indicators[indName][c]) {
                        trendData.indicators[indName][c].forEach((val, idx) => { historyData[idx] += val; });
                    }
                });
            } else {
                if (trendData.indicators[indName][curCty]) { historyData = trendData.indicators[indName][curCty]; }
            }
            return historyData;
        };

        const buildSingleIndicatorYearData = () => {
            if (!trendData || !trendData.periods || !trendData.indicators) return null;

            const indicatorNames = Object.keys(trendData.indicators || {});
            if (indicatorNames.length !== 1) return null;
            const indicatorName = indicatorNames[0];

            const countries = Array.isArray(trendData.countries) ? trendData.countries : [];
            const periods = Array.isArray(trendData.periods) ? trendData.periods : [];
            if (!countries.length || !periods.length) return null;

            const periodsMeta = Array.isArray(trendData.periods_meta) ? trendData.periods_meta : [];
            const periodKeys = periods.map((_, idx) => idx);
            const periodLabels = periods.map((periodLabel, idx) => {
                const meta = periodsMeta[idx] && typeof periodsMeta[idx] === 'object' ? periodsMeta[idx] : null;
                if (meta && String(meta.type || '') === 'annual' && meta.year) {
                    return String(meta.year);
                }
                if (meta && meta.year && meta.quarter) {
                    return `${String(meta.quarter).toUpperCase()} ${String(meta.year)}`;
                }

                const fallback = String(periodLabel || '').trim();
                const match = fallback.match(/^(Q[1-4])\s+(\d{4})$/i);
                if (match && String(match[1]).toUpperCase() === 'Q4') {
                    return String(match[2]);
                }
                return fallback;
            });

            const valuesByCountry = {};
            countries.forEach((country) => {
                const series = (trendData.indicators[indicatorName] && trendData.indicators[indicatorName][country])
                    ? trendData.indicators[indicatorName][country]
                    : [];
                valuesByCountry[country] = periodKeys.map((periodIdx) => {
                    const raw = Number(series[periodIdx] ?? 0);
                    return Number.isFinite(raw) ? raw : 0;
                });
            });

            return { indicatorName, yearsAsc: periodKeys, yearLabels: periodLabels, countries, valuesByCountry };
        };

        const renderChart = () => {
            if (typeof Chart === 'undefined') {
                hideLoader();
                return;
            }
            if (chart) chart.destroy();
            const htmlLegend = root.querySelector('.dashd-html-legend');
            if (htmlLegend) {
                htmlLegend.innerHTML = '';
                htmlLegend.style.display = 'none';
            }
            const canvas = root.querySelector('.dashd-canvas');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            let d = { labels: [], datasets: [] };

            let maxVal = 0; let y1Max = 0; let useSecondaryAxis = false;
            const isStackedBar = (viewMode === 'bar' && curCty === i18n.allCountries && barStacked);
            const isYearMode = isSingleIndicatorYearMode();
            const isHorizontalYearLayout = isYearMode && barOrientation === 'horizontal';
            const barIndexAxis = viewMode === 'bar'
                ? (isYearMode ? (isHorizontalYearLayout ? 'x' : 'y') : (barOrientation === 'vertical' ? 'x' : 'y'))
                : 'x';
            const isBarVertical = (viewMode === 'bar' && barIndexAxis === 'x');
            const stackedSegmentOrientation = isBarVertical ? 'vertical' : 'horizontal';
            const reverseCategoryAxis = (viewMode === 'bar' && isYearMode && barOrientation === 'vertical');

            if (viewMode === 'line' && trendData) {
                d.labels = trendData.periods;
                const lineMetas = Object.keys(trendData.indicators).map((ind, idx) => {
                    const hData = getLineDataForCountry(ind);
                    const dsMax = Math.max(...hData.map(v => Math.abs(Number(v) || 0)));
                    return { ind, idx, hData, dsMax };
                });

                const secondarySet = new Set();
                const positiveMetas = lineMetas.filter(m => m.dsMax > 0);

                // Stable axis assignment for many line series:
                // split by the largest gap in order-of-magnitude, so similar datasets share one scale.
                if (lineMetas.length > 3 && positiveMetas.length >= 2) {
                    const sorted = [...positiveMetas].sort((a, b) => {
                        if (a.dsMax === b.dsMax) {
                            return String(a.ind).localeCompare(String(b.ind));
                        }
                        return a.dsMax - b.dsMax;
                    });

                    const minMax = sorted[0].dsMax;
                    const maxMax = sorted[sorted.length - 1].dsMax;
                    const spreadRatio = minMax > 0 ? (maxMax / minMax) : 1;

                    let bestGap = 0;
                    let bestSplitIdx = -1;
                    for (let i = 0; i < sorted.length - 1; i++) {
                        const cur = Math.log10(Math.max(sorted[i].dsMax, 1e-12));
                        const next = Math.log10(Math.max(sorted[i + 1].dsMax, 1e-12));
                        const gap = next - cur;
                        if (gap > bestGap) {
                            bestGap = gap;
                            bestSplitIdx = i;
                        }
                    }

                    const shouldSplit = spreadRatio >= 20 && bestGap >= 0.6 && bestSplitIdx >= 0;
                    if (shouldSplit) {
                        const lowCluster = sorted.slice(0, bestSplitIdx + 1);
                        const highCluster = sorted.slice(bestSplitIdx + 1);
                        if (lowCluster.length > 0 && highCluster.length > 0) {
                            lowCluster.forEach(m => secondarySet.add(m.ind));
                        }
                    }
                }

                useSecondaryAxis = secondarySet.size > 0;

                lineMetas.forEach((meta) => {
                    const onSecondary = secondarySet.has(meta.ind);
                    if (onSecondary) {
                        if (meta.dsMax > y1Max) y1Max = meta.dsMax;
                    } else {
                        if (meta.dsMax > maxVal) maxVal = meta.dsMax;
                    }

                    const colorHex = config.colors[meta.idx % config.colors.length].trim();
                    const bgColor = (colorHex.length === 7) ? colorHex + '66' : colorHex;

                    d.datasets.push({
                        label: meta.ind,
                        data: meta.hData,
                        borderColor: colorHex,
                        backgroundColor: bgColor,
                        borderWidth: config.weight,
                        fill: true,
                        tension: 0.3,
                        yAxisID: onSecondary ? 'y1' : 'y'
                    });
                });
            } else if (isSingleIndicatorYearMode() && trendData && trendData.indicators) {
                const annual = buildSingleIndicatorYearData();
                if (annual) {
                    d.labels = annual.yearLabels && annual.yearLabels.length
                        ? annual.yearLabels
                        : annual.yearsAsc.map((y) => String(y));
                    const isStackedYear = (curCty === i18n.allCountries && barStacked);

                    if (isStackedYear) {
                        annual.yearsAsc.forEach((_, yearIdx) => {
                            let sum = 0;
                            annual.countries.forEach((country) => {
                                sum += Number(annual.valuesByCountry[country]?.[yearIdx] || 0);
                            });
                            if (Math.abs(sum) > maxVal) maxVal = Math.abs(sum);
                        });

                        annual.countries.forEach((country, countryIdx) => {
                            d.datasets.push({
                                label: country,
                                data: annual.valuesByCountry[country] || [],
                                backgroundColor: getCountryColor(country, countryIdx),
                                borderRadius: getStackSegmentRadius(countryIdx, annual.valuesByCountry, annual.countries, stackedSegmentOrientation),
                                borderSkipped: false
                            });
                        });
                    } else if (curCty === i18n.allCountries) {
                        annual.countries.forEach((country, countryIdx) => {
                            const vals = annual.valuesByCountry[country] || annual.yearsAsc.map(() => 0);
                            vals.forEach((v) => {
                                if (Math.abs(v) > maxVal) maxVal = Math.abs(v);
                            });
                            d.datasets.push({
                                label: country,
                                data: vals,
                                backgroundColor: getCountryColor(country, countryIdx),
                                borderRadius: getFullBarRadius(),
                                borderSkipped: false,
                                categoryPercentage: GROUPED_CATEGORY_PERCENTAGE,
                                barPercentage: GROUPED_BAR_PERCENTAGE
                            });
                        });
                    } else {
                        const vals = annual.valuesByCountry[curCty] || annual.yearsAsc.map(() => 0);
                        vals.forEach((v) => {
                            if (Math.abs(v) > maxVal) maxVal = Math.abs(v);
                        });
                        d.datasets.push({
                            label: curCty,
                            data: vals,
                            backgroundColor: getCountryColor(curCty, 0),
                            borderRadius: getFullBarRadius(),
                            borderSkipped: false
                        });
                    }
                }
            } else if (rawData && rawData.indicators) {
                const inds = Object.keys(rawData.indicators);
                d.labels = inds;

                if (isStackedBar) {
                    inds.forEach(ind => {
                        let sum = 0;
                        rawData.countries.forEach(c => sum += (rawData.indicators[ind][c] || 0));
                        if (Math.abs(sum) > maxVal) maxVal = Math.abs(sum);
                    });

                    const stackedValuesByCountry = {};
                    rawData.countries.forEach((countryName) => {
                        stackedValuesByCountry[countryName] = inds.map((ind) => Number(rawData.indicators[ind]?.[countryName] || 0));
                    });

                    rawData.countries.forEach((c, i) => {
                        const vals = inds.map(ind => rawData.indicators[ind][c] || 0);
                        d.datasets.push({
                            label: c,
                            data: vals,
                            backgroundColor: getCountryColor(c, i),
                            borderRadius: getStackSegmentRadius(i, stackedValuesByCountry, rawData.countries, stackedSegmentOrientation),
                            borderSkipped: false
                        });
                    });
                } else {
                    const isGroupedCountryBars = (viewMode === 'bar' && curCty === i18n.allCountries);
                    if (isGroupedCountryBars) {
                        rawData.countries.forEach((country, countryIdx) => {
                            const vals = inds.map((ind) => Number(rawData.indicators[ind]?.[country] || 0));
                            vals.forEach((v) => {
                                if (Math.abs(v) > maxVal) maxVal = Math.abs(v);
                            });
                            d.datasets.push({
                                label: country,
                                data: vals,
                                backgroundColor: getCountryColor(country, countryIdx),
                                borderRadius: getFullBarRadius(),
                                borderSkipped: false,
                                categoryPercentage: GROUPED_CATEGORY_PERCENTAGE,
                                barPercentage: GROUPED_BAR_PERCENTAGE
                            });
                        });
                    } else {
                        const vals = inds.map((i) => {
                            const v = curCty === i18n.allCountries
                                ? Object.values(rawData.indicators[i]).reduce((a, b) => a + b, 0)
                                : (rawData.indicators[i][curCty] || 0);
                            if (Math.abs(v) > maxVal) maxVal = Math.abs(v);
                            return v;
                        });
                        const bgColor = (viewMode === 'bar' && curCty !== i18n.allCountries)
                            ? getCountryColor(curCty, 0)
                            : config.colors;
                        const barDataset = { label: curCty, data: vals, backgroundColor: bgColor };
                        if (viewMode === 'bar') {
                            barDataset.borderRadius = getFullBarRadius();
                            barDataset.borderSkipped = false;
                        }
                        d.datasets.push(barDataset);
                    }
                }
            }

            const chartHasNegativeValues = hasNegativeValues(d);
            const effectiveViewMode = (viewMode === 'donut' && chartHasNegativeValues) ? 'bar' : viewMode;
            const effectiveScaleMode = (scaleMode === 'logarithmic' && chartHasNegativeValues) ? 'linear' : scaleMode;
            const effectiveBarIndexAxis = effectiveViewMode === 'bar' ? barIndexAxis : 'x';
            const effectiveIsBarVertical = effectiveViewMode === 'bar' && effectiveBarIndexAxis === 'x';
            const qualityWarningDetails = [];
            if (viewMode === 'donut' && chartHasNegativeValues) {
                qualityWarningDetails.push(i18n.donutNegativeFallback);
            }
            if (scaleMode === 'logarithmic' && chartHasNegativeValues) {
                qualityWarningDetails.push(i18n.logNegativeFallback);
            }
            updateDataQualityWarning(chartHasNegativeValues, qualityWarningDetails);

            d.datasets = d.datasets.map((dataset, datasetIndex) => {
                const labelPart = (typeof dataset.label === 'string' && dataset.label !== '') ? dataset.label : String(datasetIndex);
                const legendKey = `${viewMode}|${curCty}|${labelPart}`;
                const warnedDataset = applyNegativeDatasetWarnings(dataset, effectiveViewMode);
                return {
                    ...warnedDataset,
                    _dashdLegendKey: legendKey,
                    hidden: hiddenLegendKeys.has(legendKey)
                };
            });
            const useFlagLegend = shouldUseFlagLegend(d);

            const locUnits = axisDict[config.lang] || axisDict['en'];
            let unitText = '', div = 1;
            if (maxVal >= 1e9) { unitText = locUnits.b; div = 1e9; }
            else if (maxVal >= 1e6) { unitText = locUnits.m; div = 1e6; }
            else if (maxVal >= 1e3) { unitText = locUnits.k; div = 1e3; }

            const valueAxisConfig = {
                type: effectiveScaleMode, stacked: isStackedBar,
                title: { display: unitText !== '', text: unitText, font: { weight: 'bold', size: 13 }, color: Chart.defaults.color },
                ticks: { callback: function(value) { return formatNum(value / div); } }
            };

            let y1Config = null;
            if (useSecondaryAxis) {
                let unitText1 = '', div1 = 1;
                if (y1Max >= 1e9) { unitText1 = locUnits.b; div1 = 1e9; }
                else if (y1Max >= 1e6) { unitText1 = locUnits.m; div1 = 1e6; }
                else if (y1Max >= 1e3) { unitText1 = locUnits.k; div1 = 1e3; }

                y1Config = {
                    type: effectiveScaleMode, position: 'right', grid: { drawOnChartArea: false },
                    title: { display: unitText1 !== '', text: unitText1, font: { weight: 'bold', size: 12 }, color: Chart.defaults.color },
                    ticks: { color: Chart.defaults.color, callback: function(value) { return formatNum(value / div1); } }
                };
            }

            // 1. ПРОВЕРЯЕМ ТЕМУ ПРЯМО ПЕРЕД ОТРИСОВКОЙ
            const isDark = document.documentElement.classList.contains('wp-dark-mode-active') || document.body.classList.contains('wp-dark-mode-active');
            const textColor = isDark ? '#94a3b8' : '#666';
            const gridColor = isDark ? '#334155' : '#e5e5e5';

            // Применяем цвета темы к нашим умным конфигурациям осей
            valueAxisConfig.grid = { color: gridColor };
            valueAxisConfig.title.color = textColor;
            valueAxisConfig.ticks.color = textColor;

            if (y1Config) {
                y1Config.grid.color = gridColor;
                y1Config.title.color = textColor;
                y1Config.ticks.color = textColor;
            }

            const categoryAxisConfig = {
                stacked: isStackedBar,
                reverse: reverseCategoryAxis,
                grid: { color: gridColor },
                ticks: {
                    color: textColor,
                    autoSkip: true,
                    callback: function(value) {
                        const label = (this && typeof this.getLabelForValue === 'function')
                            ? this.getLabelForValue(value)
                            : value;
                        return wrapAxisLabel(label);
                    }
                }
            };

            // 2. ИНИЦИАЛИЗИРУЕМ ГРАФИК С УЧЕТОМ ВСЕХ НАСТРОЕК (LIN/LOG, ТЫСЯЧИ/МИЛЛИОНЫ И ТЕМА)
            chart = new Chart(ctx, {
                type: effectiveViewMode === 'line' ? 'line' : (effectiveViewMode === 'donut' ? 'doughnut' : 'bar'),
                data: d,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: effectiveViewMode === 'bar' ? effectiveBarIndexAxis : 'x',
                    animation: { onComplete: hideLoader },
                    color: textColor,
                    plugins: {
                        legend: {
                            display: !useFlagLegend,
                            position: 'bottom',
                            labels: {
                                color: textColor,
                                usePointStyle: true,
                                pointStyle: 'circle',
                                boxWidth: 10,
                                boxHeight: 10,
                                generateLabels: function(chartInstance) {
                                    const baseGenerator = Chart.defaults.plugins.legend.labels.generateLabels;
                                    const items = typeof baseGenerator === 'function' ? baseGenerator(chartInstance) : [];
                                    return items.map((item) => {
                                        const ds = chartInstance && chartInstance.data && chartInstance.data.datasets
                                            ? chartInstance.data.datasets[item.datasetIndex]
                                            : null;
                                        if (viewMode === 'line' && ds && ds.yAxisID === 'y1') {
                                            item.text = `${item.text} [Right]`;
                                        }
                                        return item;
                                    });
                                }
                            },
                            onClick: (event, legendItem, legend) => {
                                const chartInstance = legend.chart;
                                const datasetIndex = legendItem.datasetIndex;
                                if (typeof datasetIndex !== 'number') return;

                                const dataset = chartInstance.data.datasets[datasetIndex];
                                if (!dataset) return;

                                const legendKey = dataset._dashdLegendKey || `${viewMode}|${curCty}|${dataset.label || datasetIndex}`;
                                const shouldShow = !chartInstance.isDatasetVisible(datasetIndex);

                                chartInstance.setDatasetVisibility(datasetIndex, shouldShow);
                                if (shouldShow) hiddenLegendKeys.delete(legendKey);
                                else hiddenLegendKeys.add(legendKey);

                                chartInstance.update();
                            }
                        },
                        tooltip: {
                            callbacks: {
	                                label: function(context) {
	                                    let label = context.dataset.label || '';
	                                    if (label) label += ': ';
	                                    label += formatNum(context.raw);
	                                    return label;
	                                },
                                afterLabel: function(context) {
                                    return dataWarningsEnabled && isNegativeContextValue(context) ? i18n.negativeValueTooltip : '';
                                }
	                            }
	                        }
	                    },
	                    scales: effectiveViewMode !== 'donut' ? (effectiveViewMode === 'bar'
	                        ? (effectiveIsBarVertical
	                            ? { x: categoryAxisConfig, y: valueAxisConfig }
	                            : { x: valueAxisConfig, y: categoryAxisConfig })
                        : {
                            x: categoryAxisConfig,
                            y: valueAxisConfig,
                            ...(useSecondaryAxis ? { y1: y1Config } : {})
                        }) : {}
                }
            });
            renderFlagLegend(d);

            // Fallback: never leave transparent loader over canvas interactions.
            setTimeout(hideLoader, 350);
        };

        const renderTable = () => {
            const thead = root.querySelector('.dashd-thead');
            const tbody = root.querySelector('.dashd-tbody');
            if (!thead || !tbody) return;

            sparklines.forEach(c => c.destroy()); sparklines = [];

            if (viewMode === 'line' && trendData && trendData.periods) {
                thead.innerHTML = `<th>${escapeHtml(i18n.indicator)}</th>` + trendData.periods.map(p => `<th>${escapeHtml(p)}</th>`).join('') + `<th style="width:120px;">${escapeHtml(i18n.trend)}</th>`;

                tbody.innerHTML = Object.keys(trendData.indicators).map((ind, i) => {
                    const historyData = getLineDataForCountry(ind);
                    const cells = historyData.map((v, idx) => {
                        let qoqHtml = '';
                        if (idx > 0) {
                            let prev = historyData[idx-1];
                            if (prev !== 0) {
                                let pct = ((v - prev) / Math.abs(prev)) * 100;
                                let color = pct >= 0 ? '#10b981' : '#ef4444';
                                let arrow = pct >= 0 ? '&#9650;' : '&#9660;';
                                let bg = pct >= 0 ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)';
                                if (pct !== 0) {
                                    qoqHtml = `<div style="font-size:10px; color:${color}; background:${bg}; padding: 2px 5px; border-radius: 4px; display:inline-block; margin-top:4px; font-weight:600;">${arrow} ${Math.abs(pct).toFixed(1)}%</div>`;
                                }
                            }
                        }
	                        return `<td>${renderValueWithWarning(v)}${qoqHtml}</td>`;
                    }).join('');

                    const color = config.colors[i % config.colors.length].trim();
                    const indEsc = escapeHtml(ind);
                    const colorEsc = escapeHtml(color);
                    return `<tr><td><strong>${indEsc}</strong></td>${cells}<td><div style="height:35px; width:120px; position:relative; margin: 0 auto;"><canvas class="dashd-sparkline" data-ind="${indEsc}" data-color="${colorEsc}" width="120" height="35" style="width:120px; height:35px; display:block;"></canvas></div></td></tr>`;
                }).join('');

                setTimeout(() => {
                    root.querySelectorAll('.dashd-sparkline').forEach(canvas => {
                        const ind = canvas.dataset.ind; const color = canvas.dataset.color; const hData = getLineDataForCountry(ind);
                        const spChart = new Chart(canvas.getContext('2d'), {
                            type: 'line',
                            data: { labels: trendData.periods, datasets: [{ data: hData, borderColor: color, borderWidth: 2, tension: 0.3, pointRadius: 0, fill: true, backgroundColor: (color.length === 7) ? color + '33' : color }] },
                            options: { responsive: false, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { enabled: false } }, scales: { x: { display: false }, y: { display: false, min: Math.min(...hData) * 0.9, max: Math.max(...hData) * 1.1 } }, animation: false }
                        });
                        sparklines.push(spChart);
                    });
                }, 50);

            } else if (isSingleIndicatorYearMode() && trendData && trendData.indicators) {
                const annual = buildSingleIndicatorYearData();
                if (!annual) {
                    thead.innerHTML = '';
                    tbody.innerHTML = '';
                    return;
                }

                thead.innerHTML = `<th>${escapeHtml(i18n.indicator)}</th>` + annual.countries.map(c => `<th>${escapeHtml(c)}</th>`).join('') + `<th class="dashd-total-col">${escapeHtml(i18n.total)}</th>`;
                tbody.innerHTML = annual.yearsAsc.map((year, rowIdx) => {
                    let rowSum = 0;
                    const cells = annual.countries.map((country) => {
                        const val = Number(annual.valuesByCountry[country]?.[rowIdx] || 0);
                        rowSum += val;
	                        return `<td>${renderValueWithWarning(val)}</td>`;
                    }).join('');
                    const periodLabel = (annual.yearLabels && annual.yearLabels[rowIdx]) ? annual.yearLabels[rowIdx] : String(year);
                    return `<tr><td><strong>${escapeHtml(periodLabel)}</strong></td>${cells}<td class="dashd-total-col">${formatNum(rowSum)}</td></tr>`;
                }).join('');
            } else if (rawData && rawData.indicators) {
                thead.innerHTML = `<th>${escapeHtml(i18n.indicator)}</th>` + rawData.countries.map(c => `<th>${escapeHtml(c)}</th>`).join('') + `<th class="dashd-total-col">${escapeHtml(i18n.total)}</th>`;
                tbody.innerHTML = Object.keys(rawData.indicators).map(ind => {
                    let rowSum = 0;
                    const cells = rawData.countries.map(c => {
                        const cur = rawData.indicators[ind][c] || 0; rowSum += cur;

                        let yoyHtml = '';
                        if (rawData.previous && rawData.previous[ind] && rawData.previous[ind][c] !== undefined) {
                            const prev = rawData.previous[ind][c];
                            if (prev !== 0) {
                                const diff = ((cur - prev) / Math.abs(prev)) * 100;
                                const color = diff >= 0 ? '#10b981' : '#ef4444';
                                const arrow = diff >= 0 ? '▲' : '▼';
                                yoyHtml = `<div style="font-size:10px; color:${color}; font-weight:700; margin-top:4px;">${arrow} ${Math.abs(diff).toFixed(1)}% vs LY</div>`;
                            }
                        }
	                        return `<td>${renderValueWithWarning(cur)}${yoyHtml}</td>`;
                    }).join('');
                    return `<tr><td><strong>${escapeHtml(ind)}</strong></td>${cells}<td class="dashd-total-col">${formatNum(rowSum)}</td></tr>`;
                }).join('');
            }
        };

        const render = () => {
            syncDesktopSelectors();
            syncPeriodControlVisibility();
            syncMobileSelectors();
            syncWidgetTitle();
            const cBox = root.querySelector('.dashd-country-btns'); let cList = [];
            if ((viewMode === 'line' || isSingleIndicatorYearMode()) && trendData && trendData.countries) {
                cList = trendData.countries;
            } else if (viewMode !== 'line' && rawData && rawData.countries) {
                cList = rawData.countries;
            }
            if (cBox && cList.length > 0) {
                cBox.style.display = 'flex';
                cBox.innerHTML = `<button class="dashd-ui-btn dashd-country-btn ${curCty===i18n.allCountries?'active-btn':''}">${escapeHtml(i18n.allCountries)}</button>`;
                cList.forEach(c => { cBox.innerHTML += `<button class="dashd-ui-btn dashd-country-btn ${curCty===c?'active-btn':''}">${escapeHtml(c)}</button>`; });
                cBox.querySelectorAll('button').forEach(b => {
                    b.onclick = () => { curCty = b.innerText; cBox.querySelectorAll('button').forEach(x => x.classList.remove('active-btn')); b.classList.add('active-btn'); renderChart(); renderTable(); };
                });
            } else if (cBox) { cBox.style.display = 'none'; }
            renderChart(); renderTable();
        };

        const bindEvents = () => {
            root.querySelectorAll('.dashd-toggle-view .dashd-selector-label').forEach((el) => {
                el.onclick = function() {
                    const nextMode = String(this.dataset.type || '');
                    if (!nextMode || nextMode === viewMode) return;
                    viewMode = nextMode;
                    syncDesktopSelectors();
                    syncMobileSelectors();
                    loadData();
                };
            });
            root.querySelectorAll('.dashd-toggle-scale .dashd-selector-label').forEach((el) => {
                el.onclick = function() {
                    const nextScale = String(this.dataset.scale || '');
                    if (!nextScale || nextScale === scaleMode) return;
                    scaleMode = nextScale;
                    syncDesktopSelectors();
                    syncMobileSelectors();
                    renderChart();
                };
            });
            root.querySelectorAll('.dashd-toggle-orientation .dashd-selector-label').forEach((el) => {
                el.onclick = function() {
                    const nextOrientation = String(this.dataset.orientation || '');
                    if (!nextOrientation || nextOrientation === barOrientation) return;
                    barOrientation = nextOrientation === 'vertical' ? 'vertical' : 'horizontal';
                    syncDesktopSelectors();
                    syncMobileSelectors();
                    renderChart();
                };
            });
            root.querySelectorAll('.dashd-toggle-stacked .dashd-selector-label').forEach((el) => {
                el.onclick = function() {
                    const next = String(this.dataset.stacked || '');
                    const nextStacked = (next === 'true');
                    if (nextStacked === barStacked) return;
                    barStacked = nextStacked;
                    syncDesktopSelectors();
                    syncMobileSelectors();
                    renderChart();
                };
            });

            if (controls.mobileViewSelect) {
                controls.mobileViewSelect.onchange = function() {
                    const nextMode = String(this.value || '');
                    if (!nextMode || nextMode === viewMode) return;
                    viewMode = nextMode;
                    syncDesktopSelectors();
                    syncMobileSelectors();
                    loadData();
                };
            }
            if (controls.mobileScaleSelect) {
                controls.mobileScaleSelect.onchange = function() {
                    const nextScale = String(this.value || '');
                    if (!nextScale || nextScale === scaleMode) return;
                    scaleMode = nextScale;
                    syncDesktopSelectors();
                    syncMobileSelectors();
                    renderChart();
                };
            }
            if (controls.mobileOrientationSelect) {
                controls.mobileOrientationSelect.onchange = function() {
                    const next = String(this.value || '');
                    const nextOrientation = next === 'vertical' ? 'vertical' : 'horizontal';
                    if (nextOrientation === barOrientation) return;
                    barOrientation = nextOrientation;
                    syncDesktopSelectors();
                    syncMobileSelectors();
                    renderChart();
                };
            }
            if (controls.mobileStackedSelect) {
                controls.mobileStackedSelect.onchange = function() {
                    const nextStacked = String(this.value || '') === 'true';
                    if (nextStacked === barStacked) return;
                    barStacked = nextStacked;
                    syncDesktopSelectors();
                    syncMobileSelectors();
                    renderChart();
                };
            }
            if (controls.mobileYearSelect) {
                controls.mobileYearSelect.onchange = function() {
                    const nextYear = String(this.value || '');
                    if (!nextYear || nextYear === String(curY)) return;
                    curY = nextYear;
                    ensureValidQuarterForYear();
                    renderQuarterControlsForCurrentYear();
                    syncPeriodButtons();
                    syncMobileSelectors();
                    loadData();
                };
            }
            if (controls.mobileQuarterSelect) {
                controls.mobileQuarterSelect.onchange = function() {
                    const nextQuarter = String(this.value || '');
                    if (!nextQuarter || nextQuarter === String(curQ)) return;
                    const allowed = getAvailableQuartersForYear(curY);
                    if (!allowed.includes(nextQuarter)) {
                        ensureValidQuarterForYear();
                        renderQuarterControlsForCurrentYear();
                        syncPeriodButtons();
                        syncMobileSelectors();
                        return;
                    }
                    curQ = nextQuarter;
                    syncPeriodButtons();
                    syncMobileSelectors();
                    loadData();
                };
            }

            const toggleBtn = document.getElementById('toggle-<?php echo esc_js($uid); ?>');
            if (toggleBtn) {
                toggleBtn.onclick = function() {
                    const tb = document.getElementById('tb-<?php echo esc_js($uid); ?>'); const ham = this.querySelector('.dashd-hamburger');
                    if (tb.style.display === 'none') { tb.style.display = 'block'; ham.classList.add('open'); } else { tb.style.display = 'none'; ham.classList.remove('open'); }
                };
            }

            const isGated = Boolean(config.isGated);
            let pendingAction = null;
            const modal = document.getElementById('gated-modal-<?php echo esc_js($uid); ?>');
            const errorDiv = document.getElementById('gated-error-<?php echo esc_js($uid); ?>');
            const emailInput = document.getElementById('gated-email-<?php echo esc_js($uid); ?>');
            const hpInput = document.getElementById('gated-hp-<?php echo esc_js($uid); ?>');
            const submitBtn = document.getElementById('gated-submit-<?php echo esc_js($uid); ?>');

            const execCsv = () => {
                let csv = []; const rows = root.querySelectorAll('.dashd-table tr'); let headerRow = [];
                if (!rows.length || !rows[0]) {
                    console.warn('DashD: CSV export aborted, table is empty.');
                    return;
                }
                const headers = rows[0].querySelectorAll('th');
                if (!headers.length) {
                    console.warn('DashD: CSV export aborted, no headers found.');
                    return;
                }

                const sanitizeCsvCell = (value) => {
                    const raw = String(value ?? '').replace(/\u0000/g, '');
                    const trimmed = raw.trimStart();
                    if (!trimmed) return raw;

                    const normalizedNumeric = trimmed.replace(',', '.');
                    const isNumericCell = /^-?\d+(\.\d+)?$/.test(normalizedNumeric);
                    if (isNumericCell) return raw;

                    if (/^[=+@]/.test(trimmed) || /^[\t\r\n]/.test(trimmed) || /^-/.test(trimmed)) {
                        return `'${raw}`;
                    }

                    return raw;
                };

                for (let j = 0; j < headers.length; j++) {
                    const cell = sanitizeCsvCell(headers[j].innerText.trim()).replace(/"/g, '""');
                    headerRow.push('"' + cell + '"');
                }
                csv.push(headerRow.join(','));

                for (let i = 1; i < rows.length; i++) {
                    let row = [], cols = rows[i].querySelectorAll('td');
                    for (let j = 0; j < cols.length; j++) {
                        let textDiv = cols[j].querySelector('div');
                        let data = textDiv ? textDiv.innerText : cols[j].innerText;
                        if (j > 0) data = data.replace(/\s/g, '');
                        data = sanitizeCsvCell(data).replace(/"/g, '""');
                        row.push('"' + data + '"');
                    }
                    csv.push(row.join(','));
                }
                const blob = new Blob(["\uFEFF" + csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a'); link.href = URL.createObjectURL(blob); link.download = `analytics_export_${new Date().toISOString().slice(0,10)}.csv`; link.click();
            };

            const execPdf = async (btn) => {
                const originalHTML = btn.innerHTML;
                btn.innerHTML = `<span class="dashicons dashicons-update" style="animation: spin 2s linear infinite;"></span> ${escapeHtml(i18n.wait)}`;

                const waitForImages = async (scope) => {
                    if (!scope) return;
                    const images = Array.from(scope.querySelectorAll('img'));
                    if (!images.length) return;

                    await Promise.all(images.map((img) => {
                        if (img.complete && img.naturalWidth > 0) {
                            return Promise.resolve();
                        }
                        return new Promise((resolve) => {
                            let settled = false;
                            const done = () => {
                                if (settled) return;
                                settled = true;
                                resolve();
                            };
                            img.addEventListener('load', done, { once: true });
                            img.addEventListener('error', done, { once: true });
                            setTimeout(done, 2000);
                        });
                    }));
                };

                const prepareSvgImagesForCapture = async (scope) => {
                    if (!scope) return [];
                    const images = Array.from(scope.querySelectorAll('img'));
                    const converted = [];
                    if (!images.length) return converted;

                    const isSvgImageSrc = (src) => {
                        if (!src) return false;
                        return /^data:image\/svg\+xml/i.test(src) || /\.svg(?:[?#].*)?$/i.test(src);
                    };

                    const convertImage = (img) => new Promise((resolve) => {
                        const src = (img.getAttribute('src') || '').trim();
                        if (!isSvgImageSrc(src)) {
                            resolve();
                            return;
                        }

                        const probe = new Image();
                        probe.crossOrigin = 'anonymous';
                        probe.decoding = 'sync';

                        probe.onload = () => {
                            try {
                                const rect = img.getBoundingClientRect();
                                const attrWidth = parseFloat(img.getAttribute('width') || '0');
                                const attrHeight = parseFloat(img.getAttribute('height') || '0');
                                const css = window.getComputedStyle(img);
                                const cssWidth = parseFloat(css.width || '0');
                                const cssHeight = parseFloat(css.height || '0');

                                let width = probe.naturalWidth || Math.round(rect.width) || Math.round(cssWidth) || Math.round(attrWidth);
                                let height = probe.naturalHeight || Math.round(rect.height) || Math.round(cssHeight) || Math.round(attrHeight);

                                if ((!height || height < 2) && width && probe.naturalWidth > 0 && probe.naturalHeight > 0) {
                                    height = Math.round(width * (probe.naturalHeight / probe.naturalWidth));
                                }
                                if ((!width || width < 2) && height && probe.naturalWidth > 0 && probe.naturalHeight > 0) {
                                    width = Math.round(height * (probe.naturalWidth / probe.naturalHeight));
                                }
                                if (!width || width < 2) width = 400;
                                if (!height || height < 2) height = Math.max(120, Math.round(width * 0.3));

                                width = Math.max(1, Math.min(4096, Number(width) || 1));
                                height = Math.max(1, Math.min(4096, Number(height) || 1));

                                const canvas = document.createElement('canvas');
                                canvas.width = width;
                                canvas.height = height;

                                const ctx2d = canvas.getContext('2d');
                                if (!ctx2d) {
                                    resolve();
                                    return;
                                }

                                ctx2d.drawImage(probe, 0, 0, width, height);
                                const pngData = canvas.toDataURL('image/png');
                                if (!pngData) {
                                    resolve();
                                    return;
                                }

                                img.dataset.dashdOrigSrc = src;
                                img.src = pngData;
                                converted.push(img);
                            } catch (e) {
                                // ignore and keep original SVG image
                            }
                            resolve();
                        };

                        probe.onerror = () => resolve();
                        probe.src = src;
                    });

                    await Promise.all(images.map(convertImage));
                    return converted;
                };

                const restoreConvertedSvgImages = (images) => {
                    if (!Array.isArray(images) || !images.length) return;
                    images.forEach((img) => {
                        if (img && img.dataset && img.dataset.dashdOrigSrc) {
                            img.src = img.dataset.dashdOrigSrc;
                            delete img.dataset.dashdOrigSrc;
                        }
                    });
                };

                const prepareSparklineCanvasesForCapture = (scope) => {
                    if (!scope) return [];
                    const canvases = Array.from(scope.querySelectorAll('canvas.dashd-sparkline'));
                    if (!canvases.length) return [];

                    const state = [];
                    canvases.forEach((canvas) => {
                        const parent = canvas.parentNode;
                        if (!parent) return;

                        try {
                            const dataUrl = canvas.toDataURL('image/png');
                            if (dataUrl && dataUrl !== 'data:,') {
                                const img = document.createElement('img');
                                img.src = dataUrl;
                                img.alt = '';
                                img.style.width = '100%';
                                img.style.height = '100%';
                                img.style.display = 'block';
                                img.style.objectFit = 'contain';
                                parent.replaceChild(img, canvas);
                                state.push({ type: 'replaced', parent, canvas, img });
                                return;
                            }
                        } catch (e) {
                            // Tainted/invalid canvas: ignore in html2canvas capture.
                        }

                        canvas.setAttribute('data-html2canvas-ignore', 'true');
                        state.push({ type: 'ignored', canvas });
                    });

                    return state;
                };

                const restoreSparklineCanvasesAfterCapture = (state) => {
                    if (!Array.isArray(state) || !state.length) return;
                    state.forEach((entry) => {
                        if (!entry || !entry.type) return;

                        if (entry.type === 'replaced') {
                            if (entry.parent && entry.img && entry.canvas && entry.img.parentNode === entry.parent) {
                                entry.parent.replaceChild(entry.canvas, entry.img);
                            }
                        } else if (entry.type === 'ignored') {
                            if (entry.canvas && entry.canvas.removeAttribute) {
                                entry.canvas.removeAttribute('data-html2canvas-ignore');
                            }
                        }
                    });
                };

                const buildLinePdfVerticalTable = () => {
                    if (viewMode !== 'line' || !trendData || !trendData.periods || !trendData.indicators) {
                        return null;
                    }

                    const periods = Array.isArray(trendData.periods) ? trendData.periods : [];
                    const indicators = Object.keys(trendData.indicators || {});
                    if (!periods.length || !indicators.length) return null;

                    const cards = indicators.map((ind, idx) => {
                        const rawSeries = getLineDataForCountry(ind);
                        const series = Array.isArray(rawSeries) ? rawSeries : [];
                        const color = (config.colors[idx % config.colors.length] || '#1e87f0').trim();

                        const rows = periods.map((period, periodIndex) => {
                            const curNum = Number(series[periodIndex] ?? 0);
                            const curVal = Number.isFinite(curNum) ? curNum : 0;

                            let trendHtml = '<span style="color:#94a3b8;">-</span>';
                            if (periodIndex > 0) {
                                const prevNum = Number(series[periodIndex - 1] ?? 0);
                                const prevVal = Number.isFinite(prevNum) ? prevNum : 0;
                                if (prevVal !== 0) {
                                    const pct = ((curVal - prevVal) / Math.abs(prevVal)) * 100;
                                    if (Number.isFinite(pct) && pct !== 0) {
                                        const isUp = pct >= 0;
                                        const arrow = isUp ? '&#9650;' : '&#9660;';
                                        const colorHex = isUp ? '#10b981' : '#ef4444';
                                        trendHtml = `<span style="color:${colorHex}; font-weight:700;">${arrow} ${Math.abs(pct).toFixed(1)}%</span>`;
                                    }
                                }
                            }

                            return `<tr>
                                <td style="padding:8px 10px; border-bottom:1px solid #eef2f7; color:#334155; font-size:12px;">${escapeHtml(period)}</td>
                                <td style="padding:8px 10px; border-bottom:1px solid #eef2f7; text-align:right; color:#0f172a; font-size:12px; font-weight:600;">${formatNum(curVal)}</td>
                                <td style="padding:8px 10px; border-bottom:1px solid #eef2f7; text-align:right; font-size:11px;">${trendHtml}</td>
                            </tr>`;
                        }).join('');

                        return `<div style="margin:0; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; background:#fff; break-inside:avoid; page-break-inside:avoid;">
                            <div style="padding:10px 12px; background:#ffffff; border-left:4px solid ${escapeHtml(color)}; font-size:13px; font-weight:700; color:#0f172a;">
                                ${escapeHtml(ind)}
                            </div>
                            <table style="width:100%; border-collapse:collapse; table-layout:fixed;">
                                <thead>
                                    <tr>
                                        <th style="padding:8px 10px; text-align:left; font-size:11px; text-transform:uppercase; letter-spacing:.02em; color:#475569; background:#f8fafc; border-bottom:1px solid #e2e8f0;">Period</th>
                                        <th style="padding:8px 10px; text-align:right; font-size:11px; text-transform:uppercase; letter-spacing:.02em; color:#475569; background:#f8fafc; border-bottom:1px solid #e2e8f0;">Value</th>
                                        <th style="padding:8px 10px; text-align:right; font-size:11px; text-transform:uppercase; letter-spacing:.02em; color:#475569; background:#f8fafc; border-bottom:1px solid #e2e8f0;">QoQ</th>
                                    </tr>
                                </thead>
                                <tbody>${rows}</tbody>
                            </table>
                        </div>`;
                    });

                    const container = document.createElement('div');
                    container.className = 'dashd-temp-pdf-line-vertical uk-margin-top';
                    container.innerHTML = `<div style="margin-top:15px; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; background:#fff;">
                        <div style="padding:10px 12px; background:#f8fafc; border-bottom:1px solid #e2e8f0; font-size:13px; font-weight:700; color:#334155;">
                            Line Data (Full Table View)
                        </div>
                        <div style="padding:10px;">
                            <div style="display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:10px; align-items:start;">
                                ${cards.join('')}
                            </div>
                        </div>
                    </div>`;
                    return container;
                };

                const wm = root.querySelector('.dashd-pdf-watermark');
                const header = root.querySelector('.dashd-pdf-header');
                const footer = root.querySelector('.dashd-pdf-footer');
                const tableWrapper = root.querySelector('.dashd-table-wrapper');
                const tableScroll = root.querySelector('.dashd-table-scroll-container');
                let convertedSvgImages = [];
                let sparklineCanvasState = [];
                let tempLinePdfTable = null;
                const isLinePdfMode = viewMode === 'line';

                const viewToggleNode = root.querySelector('.dashd-toggle-view');
                const viewScaleToggles = viewToggleNode ? viewToggleNode.parentNode : null;
                const pWrap = root.querySelector('.dashd-periods-wrap');
                const cBox = root.querySelector('.dashd-country-btns');

                const origViewScaleDisplay = viewScaleToggles ? viewScaleToggles.style.display : '';
                const origPWrapDisplay = pWrap ? pWrap.style.display : '';
                const origCBoxDisplay = cBox ? cBox.style.display : '';
                const origTableDisplay = tableWrapper ? tableWrapper.style.display : '';
                const origScrollOverflow = tableScroll ? tableScroll.style.overflow : '';
                const origScrollMaxHeight = tableScroll ? tableScroll.style.maxHeight : '';

                if (viewScaleToggles) viewScaleToggles.style.display = 'none';
                if (pWrap) pWrap.style.display = 'none';
                if (cBox) cBox.style.display = 'none';

                if (viewMode !== 'line' && pWrap) {
                    const tempPeriodLabel = document.createElement('div');
                    tempPeriodLabel.className = 'dashd-temp-pdf-label';
                    tempPeriodLabel.innerHTML = `<div style="font-size: 15px; font-weight: 700; color: #334155; background: #f8fafc; padding: 6px 18px; border-radius: 8px; border: 1px solid #e2e8f0; margin-right: 20px; display: inline-block; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">${escapeHtml(curY)} <span style="color: #94a3b8; font-weight: 400;">&nbsp;/&nbsp;</span> ${escapeHtml(curQ)}</div>`;
                    pWrap.parentNode.insertBefore(tempPeriodLabel, pWrap.nextSibling);
                }

                if (cBox) {
                    const tempCountryLabel = document.createElement('div');
                    tempCountryLabel.className = 'dashd-temp-pdf-label';
                    tempCountryLabel.innerHTML = `<div style="font-size: 14px; font-weight: 600; color: #fff; background-color: #1e87f0; padding: 4px 14px; border-radius: 20px; display: inline-block; margin-bottom: 15px;">${escapeHtml(curCty)}</div>`;
                    cBox.parentNode.insertBefore(tempCountryLabel, cBox.nextSibling);
                }

                const now = new Date();
                const dateStr = now.toLocaleDateString() + ' ' + now.toLocaleTimeString();
                let origHeaderDisplay = '';
                let origHeaderHtml = '';
                let origHeaderJustify = '';
                let origHeaderAlign = '';
                let origHeaderTextAlign = '';

                if (header) {
                    origHeaderDisplay = header.style.display;
                    origHeaderHtml = header.innerHTML;
                    origHeaderJustify = header.style.justifyContent;
                    origHeaderAlign = header.style.alignItems;
                    origHeaderTextAlign = header.style.textAlign;

                    header.style.display = 'flex';
                    header.style.justifyContent = 'space-between';
                    header.style.alignItems = 'center';
                    header.style.textAlign = 'left';

                    const headerTitle = document.createElement('div');
                    headerTitle.innerHTML = `
                        <div style="font-size: 18px; font-weight: bold; color: #03045E; margin-bottom: 3px;">EU4Business EBRD Credit Line - Analytics Report</div>
                        <div style="font-size: 11px; color: #666;">Generated on: ${escapeHtml(dateStr)}</div>
                    `;

                    const logoWrap = document.createElement('div');
                    while (header.firstChild) {
                        logoWrap.appendChild(header.firstChild);
                    }

                    header.appendChild(headerTitle);
                    header.appendChild(logoWrap);
                } else {
                    const tempHeader = document.createElement('div');
                    tempHeader.className = 'dashd-temp-pdf-header';
                    tempHeader.innerHTML = `
                        <div style="border-bottom: 2px solid #f0f0f1; padding-bottom: 15px; margin-bottom: 25px;">
                            <div style="font-size: 18px; font-weight: bold; color: #03045E; margin-bottom: 3px;">EU4Business EBRD Credit Line - Analytics Report</div>
                            <div style="font-size: 11px; color: #666;">Generated on: ${escapeHtml(dateStr)}</div>
                        </div>
                    `;
                    root.insertBefore(tempHeader, root.firstChild);
                }

                if(wm) wm.style.display = 'block';
                if(footer) footer.style.display = 'block';
                if(tableWrapper) tableWrapper.style.display = isLinePdfMode ? 'none' : 'block';
                if(tableScroll && !isLinePdfMode) {
                    tableScroll.style.overflow = 'visible';
                    tableScroll.style.maxHeight = 'none';
                }

                if (isLinePdfMode) {
                    tempLinePdfTable = buildLinePdfVerticalTable();
                    if (tempLinePdfTable) {
                        if (tableWrapper && tableWrapper.parentNode) {
                            tableWrapper.parentNode.insertBefore(tempLinePdfTable, tableWrapper.nextSibling);
                        } else {
                            root.appendChild(tempLinePdfTable);
                        }
                    }
                }

                if (!isLinePdfMode) {
                    // Re-render table now that it's visible so sparkline canvases can get proper dimensions.
                    renderTable();
                    await new Promise(r => setTimeout(r, 200));
                }
                convertedSvgImages = await prepareSvgImagesForCapture(root);
                await waitForImages(root);
                sparklineCanvasState = isLinePdfMode ? [] : prepareSparklineCanvasesForCapture(root);

                const cleanupPdfDomState = () => {
                    if(wm) wm.style.display = 'none';
                    if(footer) footer.style.display = 'none';
                    if (header) {
                        header.style.display = origHeaderDisplay;
                        header.innerHTML = origHeaderHtml;
                        header.style.justifyContent = origHeaderJustify;
                        header.style.alignItems = origHeaderAlign;
                        header.style.textAlign = origHeaderTextAlign;
                    } else {
                        const tempH = root.querySelector('.dashd-temp-pdf-header');
                        if (tempH) tempH.remove();
                    }

                    if (viewScaleToggles) viewScaleToggles.style.display = origViewScaleDisplay;
                    if (pWrap) pWrap.style.display = origPWrapDisplay;
                    if (cBox) cBox.style.display = origCBoxDisplay;
                    root.querySelectorAll('.dashd-temp-pdf-label').forEach(el => el.remove());

                    if (tempLinePdfTable && tempLinePdfTable.parentNode) {
                        tempLinePdfTable.parentNode.removeChild(tempLinePdfTable);
                    }

                    if(tableWrapper) tableWrapper.style.display = origTableDisplay;
                    if(tableScroll) {
                        tableScroll.style.overflow = origScrollOverflow;
                        tableScroll.style.maxHeight = origScrollMaxHeight;
                    }
                    restoreSparklineCanvasesAfterCapture(sparklineCanvasState);
                    restoreConvertedSvgImages(convertedSvgImages);
                };

                try {
                    const canvas = await html2canvas(root, {
                        scale: 2,
                        useCORS: true,
                        backgroundColor: '#ffffff',
                        windowWidth: root.scrollWidth,
                        windowHeight: root.scrollHeight,
                        ignoreElements: (el) => {
                            if (!el || !el.tagName) return false;
                            if (el.tagName.toLowerCase() === 'canvas' && el.classList && el.classList.contains('dashd-sparkline')) {
                                return true;
                            }
                            return false;
                        }
                    });

                    cleanupPdfDomState();

                    const imgData = canvas.toDataURL('image/jpeg', 1.0);
                    const { jsPDF } = window.jspdf;

                    const pdfWidth = 210;
                    const pdfHeight = (canvas.height * pdfWidth) / canvas.width;

                    const pdf = new jsPDF({
                        orientation: pdfWidth > pdfHeight ? 'l' : 'p',
                        unit: 'mm',
                        format: [pdfWidth, pdfHeight]
                    });

                    pdf.addImage(imgData, 'JPEG', 0, 0, pdfWidth, pdfHeight);
                    pdf.save(`dashboard_report_${new Date().toISOString().slice(0,10)}.pdf`);
                } catch (e) {
                    console.error(e); alert(i18n.errorPdf);
                    cleanupPdfDomState();
                }
                btn.innerHTML = originalHTML;
            };

            const handleExport = (action, btn) => {
                if (!isGated || localStorage.getItem('dashd_unlocked')) {
                    action === 'csv' ? execCsv() : execPdf(btn);
                } else {
                    pendingAction = action; modal.classList.add('active'); errorDiv.style.display = 'none';
                }
            };

            if (isGated && modal) {
                modal.querySelector('.dashd-modal-close').onclick = () => modal.classList.remove('active');

                submitBtn.onclick = async () => {
                    const em = emailInput.value.trim(); const hp = hpInput.value.trim();
                    if (!em || !em.includes('@')) { errorDiv.innerText = i18n.invalidEmail; errorDiv.style.display = 'block'; return; }

                    submitBtn.innerHTML = `<span class="dashicons dashicons-update" style="animation: spin 1s linear infinite;"></span> ${escapeHtml(i18n.verifying)}`;
                    submitBtn.style.opacity = '0.7'; submitBtn.style.pointerEvents = 'none'; errorDiv.style.display = 'none';

                    const fd = new FormData(); fd.append('action', 'dashd_capture_lead'); fd.append('email', em); fd.append('hp', hp); fd.append('type', pendingAction); fd.append('source', config.key); fd.append('nonce', config.leadNonce || '');

                    try {
                        const res = await fetch(config.ajax, { method: 'POST', body: fd }); const data = await res.json();
                        if (data.success) {
                            localStorage.setItem('dashd_unlocked', '1'); modal.classList.remove('active');
                            pendingAction === 'csv' ? execCsv() : execPdf(root.querySelector('.dashd-export-pdf'));
                        } else {
                            errorDiv.innerText = data.data.msg || i18n.errorGeneral; errorDiv.style.display = 'block';
                        }
                    } catch(e) {
                        errorDiv.innerText = i18n.errorServer; errorDiv.style.display = 'block';
                    }

                    submitBtn.textContent = i18n.unlockAndDownload; submitBtn.style.opacity = '1'; submitBtn.style.pointerEvents = 'auto';
                };
            }

            const csvBtn = root.querySelector('.dashd-export-csv'); if(csvBtn) csvBtn.onclick = () => handleExport('csv', csvBtn);
            const pdfBtn = root.querySelector('.dashd-export-pdf'); if(pdfBtn) pdfBtn.onclick = () => handleExport('pdf', pdfBtn);
        };

        const updateChartColors = (isDark) => {
        	if (typeof Chart === 'undefined') return; // Добавляем защиту: если Chart еще нет, выходим
            Chart.defaults.color = isDark ? '#94a3b8' : '#666';
            Chart.defaults.borderColor = isDark ? '#334155' : '#e5e5e5';
            if (chart) renderChart();
        };

            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.attributeName === 'class') {
                        if (chart) renderChart();
                    }
                });
            });

            const init = async () => {
                showLoader();
                const bodyEl = document.body;
                const initialDark = document.documentElement.classList.contains('wp-dark-mode-active') || (bodyEl && bodyEl.classList.contains('wp-dark-mode-active'));
                updateChartColors(initialDark);

                observer.observe(document.documentElement, { attributes: true });
                if (bodyEl) {
                    observer.observe(bodyEl, { attributes: true });
                }

                let attempts = 0;
                while (typeof Chart === 'undefined' && attempts < 20) { await new Promise(r => setTimeout(r, 100)); attempts++; }
                if (typeof Chart === 'undefined') {
                    console.error('DashD: Chart.js is not available. Widget initialization aborted.');
                    hideLoader();
                    return;
                }

                bindEvents();

                try {
                    let periodsUrl = `${config.ajax}?action=get_dashd_periods_split&key=${config.key}`;
                    if (Array.isArray(config.indicatorSpecs) && config.indicatorSpecs.length) {
                        periodsUrl += `&indicators=${encodeURIComponent(config.indicatorSpecs.join(','))}`;
                    } else if (Array.isArray(config.indicatorIds) && config.indicatorIds.length) {
                        periodsUrl += `&indicators=${encodeURIComponent(config.indicatorIds.join(','))}`;
                    }
                    periodsUrl = appendPeriodRangeParams(periodsUrl);
                    const pRes = await fetch(periodsUrl);
                    if (!pRes.ok) throw new Error(`HTTP Error: ${pRes.status}`);

                    const pJson = await pRes.json();

                    if (pJson.success && pJson.data.years.length) {
                        periodYears = pJson.data.years
                            .slice()
                            .sort((a, b) => Number(b) - Number(a))
                            .map((v) => String(v));
                        periodQuarters = Array.isArray(pJson.data.quarters) && pJson.data.quarters.length
                            ? pJson.data.quarters
                                .slice()
                                .map((v) => String(v || '').toUpperCase())
                                .filter((v) => ['Q1', 'Q2', 'Q3', 'Q4'].includes(v))
                                .sort((a, b) => b.localeCompare(a))
                            : ['Q4', 'Q3', 'Q2', 'Q1'];
                        if (!periodQuarters.length) {
                            periodQuarters = ['Q4', 'Q3', 'Q2', 'Q1'];
                        }

                        const mapFromApi = (pJson.data.year_quarters && typeof pJson.data.year_quarters === 'object')
                            ? pJson.data.year_quarters
                            : {};
                        periodYearQuarterMap = {};
                        periodYears.forEach((year) => {
                            const fromMap = Array.isArray(mapFromApi[year])
                                ? mapFromApi[year].map((v) => String(v || '').toUpperCase())
                                : [];
                            const normalized = periodQuarters.filter((q) => fromMap.includes(q));
                            periodYearQuarterMap[year] = normalized.length ? normalized : periodQuarters.slice();
                        });

                        const latestFromApi = pJson.data.latest && typeof pJson.data.latest === 'object'
                            ? pJson.data.latest
                            : {};
                        const latestYear = String(latestFromApi.year || '');
                        const latestQuarter = String(latestFromApi.quarter || '').toUpperCase();
                        curY = periodYears.includes(latestYear) ? latestYear : (periodYears[0] || null);
                        const availableForCurrent = getAvailableQuartersForYear(curY);
                        curQ = availableForCurrent.includes(latestQuarter)
                            ? latestQuarter
                            : (availableForCurrent[0] || periodQuarters[0] || null);
                        ensureValidQuarterForYear();

                        const yBox = controls.yearButtonsBox;
                        const qBox = controls.quarterButtonsBox;
                        if (yBox && qBox) {
                            yBox.innerHTML = periodYears.map((y) => `<button class="dashd-ui-btn ${String(y)===String(curY)?'active-btn':''}" data-v="${escapeHtml(y)}">${escapeHtml(y)}</button>`).join('');
                            renderQuarterControlsForCurrentYear();

                            yBox.querySelectorAll('button').forEach((b) => {
                                b.onclick = () => {
                                    curY = String(b.dataset.v || '');
                                    if (!curY) return;
                                    ensureValidQuarterForYear();
                                    renderQuarterControlsForCurrentYear();
                                    syncPeriodButtons();
                                    syncMobileSelectors();
                                    loadData();
                                };
                            });
                        }

                        if (controls.mobileYearSelect) {
                            controls.mobileYearSelect.innerHTML = periodYears.map((y) => `<option value="${escapeHtml(y)}">${escapeHtml(y)}</option>`).join('');
                        }
                        renderQuarterControlsForCurrentYear();
                        syncPeriodButtons();
                        syncMobileSelectors();
                        syncPeriodControlVisibility();
                        await loadData();
                    } else {
                        console.warn("DashD: No periods found or API returned success:false");
                        hideLoader();
                    }
                } catch (error) {
                    console.error("DashD Network/Parse Error in init:", error);
                    hideLoader();
                }
            };

            const runInit = () => {
                init().catch((error) => {
                    console.error('DashD Unexpected init error:', error);
                    hideLoader();
                });
            };

            runInit();
            root.addEventListener('dashd-reinit', runInit);

            return true;
        };

        if (!startWidget()) {
            document.addEventListener('DOMContentLoaded', startWidget, { once: true });
            window.addEventListener('load', startWidget, { once: true });
        }
    })();
</script>
    <style>
        @keyframes spin { 100% { transform: rotate(360deg); } }
    </style>
    <?php
    $html = ob_get_clean();

    // YOOtheme and some builders may strip inline <script> tags from element markup.
    // Move widget boot JS into the script queue so it always executes on normal front-end render.
    // Keep inline <script> in AJAX preview responses used by the constructor.
    if (!wp_doing_ajax() && preg_match('#<script[^>]*data-dashd-widget-boot=["\']1["\'][^>]*>\s*(\(function\(\)\s*\{.*?\}\)\(\);\s*)</script>#s', $html, $matches) === 1) {
        $inline_boot_js = trim((string) $matches[1]);
        if ($inline_boot_js !== '') {
            wp_add_inline_script('dashd-widget-runtime', $inline_boot_js, 'after');
        }
        $html = preg_replace('#<script[^>]*data-dashd-widget-boot=["\']1["\'][^>]*>\s*\(function\(\)\s*\{.*?\}\)\(\);\s*</script>#s', '', $html, 1);
    }

    return $html;
}
