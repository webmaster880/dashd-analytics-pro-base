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
            'gated'    => ['type' => 'string', 'default' => 'false'],
            'colors'   => ['type' => 'string', 'default' => '#E5D6FF, #E3F263, #336DFF, #8b5cf6, #58595B']
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
    $indicators = [];
    foreach ($indicator_options as $token => $label) {
        $indicators[] = [
            'value' => (string) $token,
            'label' => (string) $label,
        ];
    }

    wp_localize_script('dashd-gutenberg-block', 'dashdBlocksInfo', [
        'sources' => $sources,
        'indicators' => $indicators,
    ]);
});
