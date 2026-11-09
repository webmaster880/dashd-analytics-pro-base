<?php
/** Optional shared analytical storage for a WordPress network. */
if (!defined('ABSPATH')) exit;

function dashd_network_storage_config() {
    if (!function_exists('is_multisite') || !is_multisite()) return [];
    $config = get_network_option(get_current_network_id(), 'dashd_network_storage', []);
    return is_array($config) ? $config : [];
}

function dashd_network_owner_id() {
    $config = dashd_network_storage_config();
    return !empty($config['enabled']) ? (int) ($config['owner_id'] ?? 0) : 0;
}

function dashd_network_tables() {
    return ['settings', 'countries', 'indicators', 'data_records', 'snapshots'];
}

function dashd_network_table($suffix) {
    global $wpdb;
    return $wpdb->base_prefix . 'dashd_net_' . (int) get_current_network_id() . '_' . $suffix;
}

function dashd_table($suffix) {
    global $wpdb;
    if (in_array($suffix, dashd_network_tables(), true) && dashd_network_owner_id()) {
        return dashd_network_table($suffix);
    }
    return $wpdb->prefix . 'dashd_' . $suffix;
}

function dashd_data_option($name, $default = false) {
    return dashd_network_owner_id()
        ? get_network_option(get_current_network_id(), $name, $default)
        : get_option($name, $default);
}

function dashd_update_data_option($name, $value) {
    return dashd_network_owner_id()
        ? update_network_option(get_current_network_id(), $name, $value)
        : update_option($name, $value);
}

function dashd_is_data_owner_site() {
    $owner = dashd_network_owner_id();
    return !$owner || $owner === get_current_blog_id();
}

function dashd_sync_notifications_enabled() {
    return !dashd_network_owner_id() || (bool) dashd_data_option('dashd_network_sync_notifications', false);
}

function dashd_can_manage_shared_data() {
    return !dashd_network_owner_id()
        || (dashd_is_data_owner_site() && current_user_can('manage_network_options'));
}

function dashd_require_shared_data_access($ajax = false) {
    if (!dashd_can_manage_shared_data()) {
        dashd_forbidden_response($ajax);
    }
}

function dashd_shared_data_timezone() {
    $owner = dashd_network_owner_id();
    if (!$owner || $owner === get_current_blog_id()) return wp_timezone();
    switch_to_blog($owner);
    try {
        return wp_timezone();
    } finally {
        restore_current_blog();
    }
}

function dashd_network_sync_lock_name() {
    global $wpdb;
    return 'dashd:' . md5(DB_NAME . ':' . $wpdb->base_prefix . ':' . get_current_network_id());
}

function dashd_network_sync_lock() {
    global $wpdb;
    return (string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', dashd_network_sync_lock_name())) === '1';
}

function dashd_network_sync_unlock() {
    global $wpdb;
    $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', dashd_network_sync_lock_name()));
}

function dashd_register_shared_data_guards() {
    foreach ([
        'dashd_add_source', 'dashd_update_source', 'dashd_delete_source',
        'dashd_export_csv', 'dashd_import_csv', 'dashd_export_raw_data', 'dashd_import_raw_data',
        'dashd_manual_sync', 'dashd_save_auto_sync', 'dashd_export_sync_logs', 'dashd_clear_sync_logs',
    ] as $action) {
        add_action('admin_post_' . $action, 'dashd_require_shared_data_access', 0, 0);
    }
    foreach (['dashd_update_raw_value', 'dashd_delete_raw_records', 'dashd_add_raw_record'] as $action) {
        add_action('wp_ajax_' . $action, static function () {
            dashd_require_shared_data_access(true);
        }, 0);
    }
}
