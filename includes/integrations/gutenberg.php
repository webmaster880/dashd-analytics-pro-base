<?php
/**
 * Gutenberg Block Integration (No-Build ServerSideRender)
 */

if (!defined('ABSPATH')) exit;

add_action('init', function() {
    register_block_type('dashd/analytics-widget', [
        'render_callback' => 'dashd_render_front_widget',
        'attributes' => [
            'table'    => ['type' => 'string', 'default' => ''],
            'indicators' => ['type' => 'array', 'default' => [], 'items' => ['type' => 'string']],
            'mode'     => ['type' => 'string', 'default' => 'bar'],
            'scale'    => ['type' => 'string', 'default' => 'linear'],
            'bar_orientation' => ['type' => 'string', 'default' => 'horizontal'],
            'bar_stacked' => ['type' => 'string', 'default' => 'true'],
            'gated'    => ['type' => 'string', 'default' => 'false'],
            'show_view_toggle' => ['type' => 'string', 'default' => 'true'],
            'show_scale_toggle' => ['type' => 'string', 'default' => 'true'],
            'show_periods' => ['type' => 'string', 'default' => 'true'],
            'show_data_warnings' => ['type' => 'string', 'default' => 'true'],
            'country_order' => ['type' => 'string', 'default' => ''],
            'period_start' => ['type' => 'string', 'default' => ''],
            'period_end' => ['type' => 'string', 'default' => ''],
            'colors'   => ['type' => 'string', 'default' => '#336DFF, #AF9BE2, #3B82F6, #BEE00F, #7FD3F7']
        ]
    ]);
});

add_action('enqueue_block_editor_assets', function() {
    wp_enqueue_script(
        'dashd-gutenberg-block',
        DASHD_URL . 'assets/gutenberg-block.js',
        ['wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-server-side-render'],
        DASHD_VERSION,
        true
    );

    global $wpdb;
    $sources = $wpdb->get_results("SELECT source_key, source_label FROM {$wpdb->prefix}dashd_settings");
    $indicator_options = function_exists('dashd_integration_get_indicator_options')
        ? dashd_integration_get_indicator_options()
        : [];
    $period_options = function_exists('dashd_integration_get_period_options')
        ? dashd_integration_get_period_options()
        : [];
    $indicators = [];
    foreach ($indicator_options as $token => $label) {
        $indicators[] = [
            'value' => (string) $token,
            'label' => (string) $label,
        ];
    }
    $periods = [];
    foreach ($period_options as $value => $label) {
        $periods[] = [
            'value' => (string) $value,
            'label' => (string) $label,
        ];
    }

    wp_localize_script('dashd-gutenberg-block', 'dashdBlocksInfo', [
        'sources' => $sources,
        'indicators' => $indicators,
        'periods' => $periods,
    ]);
});
