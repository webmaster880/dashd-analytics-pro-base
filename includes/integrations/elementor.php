<?php
/**
 * Elementor Integration Widget
 */

if (!defined('ABSPATH')) exit;

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
        $rows = $wpdb->get_results("SELECT source_key, source_label FROM {$wpdb->prefix}dashd_settings");
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

// ИСПРАВЛЕНИЕ: Ждем инициализации виджетов Elementor
add_action('elementor/widgets/register', function($widgets_manager) {
    static $registered = false;
    if ($registered) {
        return;
    }
    
    // Защитная проверка: если класс Elementor не найден (плагин отключен), просто выходим
    if (!class_exists('\Elementor\Widget_Base')) {
        return;
    }

    // Объявляем класс только если уверены, что Elementor работает
    if (!class_exists('DashD_Elementor_Widget')) {
        class DashD_Elementor_Widget extends \Elementor\Widget_Base {

            private static function sanitize_palette($raw_palette) {
                $raw_palette = is_scalar($raw_palette) ? (string) $raw_palette : '';
                $parts = preg_split('/\s*,\s*/', trim($raw_palette), -1, PREG_SPLIT_NO_EMPTY);
                if (!is_array($parts)) {
                    return '';
                }

                $colors = [];
                foreach ($parts as $item) {
                    $item = trim((string) $item);
                    if ($item === '') {
                        continue;
                    }
                    if (preg_match('/^#?(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $item) !== 1) {
                        continue;
                    }
                    if ($item[0] !== '#') {
                        $item = '#' . $item;
                    }
                    $colors[] = strtoupper($item);
                    if (count($colors) >= 12) {
                        break;
                    }
                }

                return implode(', ', $colors);
            }

            public function get_name() { return 'dashd_analytics_widget'; }
            public function get_title() { return __('Analytics Pro', 'dashd-analytics-pro'); }
            public function get_icon() { return 'eicon-chart-bar'; }
            public function get_categories() { return ['general']; }

            protected function register_controls() {
                $opts = function_exists('dashd_integration_get_source_options')
                    ? dashd_integration_get_source_options()
                    : ['table1' => 'Default Table (table1)'];
                $indicator_opts = function_exists('dashd_integration_get_indicator_options')
                    ? dashd_integration_get_indicator_options()
                    : [];
                $period_opts = function_exists('dashd_integration_get_period_options')
                    ? dashd_integration_get_period_options()
                    : [];

                $this->start_controls_section('content_section', [
                    'label' => __('Dashboard Settings', 'dashd-analytics-pro'),
                    'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
                ]);

                $this->add_control('indicators', [
                    'label' => __('Indicators (Data Source)', 'dashd-analytics-pro'),
                    'type' => \Elementor\Controls_Manager::SELECT2,
                    'multiple' => true,
                    'options' => $indicator_opts,
                    'label_block' => true,
                    'description' => __('Select one or more indicators. Source is resolved from indicator tokens.', 'dashd-analytics-pro'),
                ]);

                $this->add_control('table', [
                    'label' => __('Legacy Source (fallback)', 'dashd-analytics-pro'),
                    'type' => \Elementor\Controls_Manager::SELECT,
                    'options' => $opts,
                    'default' => !empty($opts) ? array_key_first($opts) : 'table1',
                    'separator' => 'before',
                ]);

                $this->add_control('mode', [
                    'label' => __('Default View Mode', 'dashd-analytics-pro'),
                    'type' => \Elementor\Controls_Manager::SELECT,
                    'options' => ['bar' => 'Bar', 'line' => 'Line', 'donut' => 'Donut'],
                    'default' => 'bar',
                ]);

                $this->add_control('scale', [
                    'label' => __('Axis Scale', 'dashd-analytics-pro'),
                    'type' => \Elementor\Controls_Manager::SELECT,
                    'options' => ['linear' => 'Linear', 'logarithmic' => 'Logarithmic'],
                    'default' => 'linear',
                ]);

                $this->add_control('bar_orientation', [
                    'label' => __('Bar Orientation', 'dashd-analytics-pro'),
                    'type' => \Elementor\Controls_Manager::SELECT,
                    'options' => ['horizontal' => 'Horizontal', 'vertical' => 'Vertical'],
                    'default' => 'horizontal',
                ]);

                $this->add_control('bar_stacked', [
                    'label' => __('Bar Type', 'dashd-analytics-pro'),
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'return_value' => 'true',
                    'default' => 'true',
                    'label_on' => __('Stacked', 'dashd-analytics-pro'),
                    'label_off' => __('Normal', 'dashd-analytics-pro'),
                ]);

                $this->add_control('period_start', [
                    'label' => __('Period Start', 'dashd-analytics-pro'),
                    'type' => \Elementor\Controls_Manager::SELECT,
                    'options' => array_merge(['' => __('Start: All', 'dashd-analytics-pro')], $period_opts),
                    'default' => '',
                    'description' => __('Optional lower bound for chart periods.', 'dashd-analytics-pro'),
                    'separator' => 'before',
                ]);

                $this->add_control('period_end', [
                    'label' => __('Period End', 'dashd-analytics-pro'),
                    'type' => \Elementor\Controls_Manager::SELECT,
                    'options' => array_merge(['' => __('End: All', 'dashd-analytics-pro')], $period_opts),
                    'default' => '',
                    'description' => __('Optional upper bound for chart periods.', 'dashd-analytics-pro'),
                ]);

                $this->add_control('gated', [
                    'label' => __('Gated Content (Require Email)', 'dashd-analytics-pro'),
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'return_value' => 'true',
                    'default' => 'false',
                ]);

                $this->add_control('show_view_toggle', [
                    'label' => __('Show Bar/Line/Donut Switch', 'dashd-analytics-pro'),
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'return_value' => 'true',
                    'default' => 'true',
                ]);

                $this->add_control('show_scale_toggle', [
                    'label' => __('Show Lin/Log Switch', 'dashd-analytics-pro'),
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'return_value' => 'true',
                    'default' => 'true',
                ]);

                $this->add_control('show_periods', [
                    'label' => __('Show Year/Quarter Controls', 'dashd-analytics-pro'),
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'return_value' => 'true',
                    'default' => 'true',
                ]);

                $this->add_control('show_data_warnings', [
                    'label' => __('Show Data Quality Warnings', 'dashd-analytics-pro'),
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'return_value' => 'true',
                    'default' => 'true',
                    'description' => __('Show warnings for negative or incorrect values on charts and tables.', 'dashd-analytics-pro'),
                ]);

                $this->add_control('country_order', [
                    'label' => __('Country Display Order', 'dashd-analytics-pro'),
                    'type' => \Elementor\Controls_Manager::TEXT,
                    'default' => '',
                    'description' => __('Optional comma-separated names (e.g. Ukraine, Moldova, Georgia, Armenia).', 'dashd-analytics-pro'),
                ]);

                $this->add_control('colors', [
                    'label' => __('Color Palette (HEX comma separated)', 'dashd-analytics-pro'),
                    'type' => \Elementor\Controls_Manager::TEXT,
                    'default' => '#336DFF, #AF9BE2, #3B82F6, #BEE00F, #7FD3F7',
                ]);

                $this->end_controls_section();
            }

            protected function render() {
                $settings = $this->get_settings_for_display();

                $table_raw = (string) ($settings['table'] ?? 'table1');
                $table = function_exists('dashd_normalize_source_key')
                    ? dashd_normalize_source_key($table_raw, 'table1')
                    : sanitize_key($table_raw);
                if ($table === '') {
                    $table = 'table1';
                }
                $indicator_tokens = [];
                $indicator_setting = $settings['indicators'] ?? [];
                if (is_array($indicator_setting)) {
                    foreach ($indicator_setting as $token) {
                        $token = is_scalar($token) ? trim((string) $token) : '';
                        if ($token === '') {
                            continue;
                        }
                        if (preg_match('/^[a-z0-9_\\-]+:\\d+$/i', $token) === 1 || preg_match('/^\\d+$/', $token) === 1) {
                            $indicator_tokens[] = $token;
                        }
                    }
                } elseif (is_scalar($indicator_setting)) {
                    $raw = trim((string) $indicator_setting);
                    if ($raw !== '') {
                        $indicator_tokens[] = $raw;
                    }
                }
                $indicator_tokens = array_values(array_unique($indicator_tokens));
                $indicators_csv = implode(',', $indicator_tokens);

                $mode = strtolower(trim((string) ($settings['mode'] ?? 'bar')));
                if (!in_array($mode, ['bar', 'line', 'donut'], true)) {
                    $mode = 'bar';
                }

                $scale = strtolower(trim((string) ($settings['scale'] ?? 'linear')));
                if (!in_array($scale, ['linear', 'logarithmic'], true)) {
                    $scale = 'linear';
                }
                $bar_orientation = strtolower(trim((string) ($settings['bar_orientation'] ?? 'horizontal')));
                if (!in_array($bar_orientation, ['horizontal', 'vertical'], true)) {
                    $bar_orientation = 'horizontal';
                }
                $bar_stacked = (!empty($settings['bar_stacked']) && (string) $settings['bar_stacked'] === 'true') ? 'true' : 'false';
                $country_order = self::sanitize_country_order((string) ($settings['country_order'] ?? ''));
                $period_start = self::sanitize_period_bound($settings['period_start'] ?? '');
                $period_end = self::sanitize_period_bound($settings['period_end'] ?? '');

                $gated = (!empty($settings['gated']) && (string) $settings['gated'] === 'true') ? 'true' : 'false';
                $show_view_toggle = (!empty($settings['show_view_toggle']) && (string) $settings['show_view_toggle'] === 'true') ? 'true' : 'false';
                $show_scale_toggle = (!empty($settings['show_scale_toggle']) && (string) $settings['show_scale_toggle'] === 'true') ? 'true' : 'false';
                $show_periods = (!empty($settings['show_periods']) && (string) $settings['show_periods'] === 'true') ? 'true' : 'false';
                $show_data_warnings = (!array_key_exists('show_data_warnings', $settings) || (string) $settings['show_data_warnings'] === 'true') ? 'true' : 'false';

                $colors = self::sanitize_palette((string) ($settings['colors'] ?? ''));
                if ($colors === '') {
                    $colors = '#336DFF, #AF9BE2, #3B82F6, #BEE00F, #7FD3F7';
                }
                $shortcode = '[dashd_widget ';
                if ($indicators_csv !== '') {
                    $shortcode .= sprintf('indicators="%s" ', esc_attr($indicators_csv));
                }
                $shortcode .= sprintf(
                    'table="%s" mode="%s" scale="%s" bar_orientation="%s" bar_stacked="%s" period_start="%s" period_end="%s" gated="%s" show_view_toggle="%s" show_scale_toggle="%s" show_periods="%s" show_data_warnings="%s" country_order="%s" colors="%s"]',
                    esc_attr($table),
                    esc_attr($mode),
                    esc_attr($scale),
                    esc_attr($bar_orientation),
                    esc_attr($bar_stacked),
                    esc_attr($period_start),
                    esc_attr($period_end),
                    esc_attr($gated),
                    esc_attr($show_view_toggle),
                    esc_attr($show_scale_toggle),
                    esc_attr($show_periods),
                    esc_attr($show_data_warnings),
                    esc_attr($country_order),
                    esc_attr($colors)
                );
                echo do_shortcode($shortcode);
            }

            protected static function sanitize_country_order($raw) {
                $raw = is_scalar($raw) ? (string) $raw : '';
                $items = preg_split('/[,\n;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
                if (!is_array($items)) {
                    return '';
                }

                $out = [];
                foreach ($items as $item) {
                    $name = trim(wp_strip_all_tags((string) $item));
                    if ($name === '') {
                        continue;
                    }
                    $out[$name] = $name;
                    if (count($out) >= 100) {
                        break;
                    }
                }

                return implode(', ', array_values($out));
            }

            protected static function sanitize_period_bound($raw) {
                $raw = strtoupper(trim((string) (is_scalar($raw) ? $raw : '')));
                if ($raw === '') {
                    return '';
                }
                if (preg_match('/^(\d{4})[-_\s]?(Q[1-4])$/', $raw, $matches) === 1) {
                    return sprintf('%d-%s', (int) $matches[1], (string) $matches[2]);
                }
                if (preg_match('/^(Q[1-4])[-_\s]?(\d{4})$/', $raw, $matches) === 1) {
                    return sprintf('%d-%s', (int) $matches[2], (string) $matches[1]);
                }
                return '';
            }
        }
    }

    // Регистрируем виджет
    $widgets_manager->register(new DashD_Elementor_Widget());
    $registered = true;
});
