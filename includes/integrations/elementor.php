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

                $this->add_control('colors', [
                    'label' => __('Color Palette (HEX comma separated)', 'dashd-analytics-pro'),
                    'type' => \Elementor\Controls_Manager::TEXT,
                    'default' => '#E5D6FF, #E3F263, #336DFF, #8b5cf6, #58595B',
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

                $gated = (!empty($settings['gated']) && (string) $settings['gated'] === 'true') ? 'true' : 'false';
                $show_view_toggle = (!empty($settings['show_view_toggle']) && (string) $settings['show_view_toggle'] === 'true') ? 'true' : 'false';
                $show_scale_toggle = (!empty($settings['show_scale_toggle']) && (string) $settings['show_scale_toggle'] === 'true') ? 'true' : 'false';
                $show_periods = (!empty($settings['show_periods']) && (string) $settings['show_periods'] === 'true') ? 'true' : 'false';

                $colors = self::sanitize_palette((string) ($settings['colors'] ?? ''));
                if ($colors === '') {
                    $colors = '#E5D6FF, #E3F263, #336DFF, #8B5CF6, #58595B';
                }
                $shortcode = '[dashd_widget ';
                if ($indicators_csv !== '') {
                    $shortcode .= sprintf('indicators="%s" ', esc_attr($indicators_csv));
                }
                $shortcode .= sprintf(
                    'table="%s" mode="%s" scale="%s" gated="%s" show_view_toggle="%s" show_scale_toggle="%s" show_periods="%s" colors="%s"]',
                    esc_attr($table),
                    esc_attr($mode),
                    esc_attr($scale),
                    esc_attr($gated),
                    esc_attr($show_view_toggle),
                    esc_attr($show_scale_toggle),
                    esc_attr($show_periods),
                    esc_attr($colors)
                );
                echo do_shortcode($shortcode);
            }
        }
    }

    // Регистрируем виджет
    $widgets_manager->register(new DashD_Elementor_Widget());
    $registered = true;
});
