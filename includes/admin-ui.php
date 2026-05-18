<?php
/**
 * Admin UI v9.3.0
 * ДОБАВЛЕНО: Интернационализация (i18n) строк интерфейса.
 */

if (!defined('ABSPATH')) exit;

function dashd_admin_main_page() {
    global $wpdb;
    
    $stats = [
        'recs' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}dashd_data_records"),
        'cty'  => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}dashd_countries"),
        'ind'  => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}dashd_indicators"),
        'src'  => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}dashd_settings")
    ];
    
    $status = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';
    ?>
    <div class="wrap">
        <div class="dashd-admin-header">
            <h1><?php esc_html_e('Analytics Pro Dashboard', 'dashd-analytics-pro'); ?> <span class="dashd-badge">v<?php echo esc_html((string) DASHD_VERSION); ?></span></h1>
        </div>
        
        <?php if ($status === 'synced'): ?>
            <div class="notice notice-success is-dismissible"><p>✅ <strong><?php esc_html_e('Synchronization complete.', 'dashd-analytics-pro'); ?></strong> <?php esc_html_e('All data is up to date.', 'dashd-analytics-pro'); ?></p></div>
        <?php elseif ($status === 'wiped'): ?>
            <div class="notice notice-error is-dismissible"><p>🗑️ <strong><?php esc_html_e('Data wiped.', 'dashd-analytics-pro'); ?></strong> <?php esc_html_e('All records, snapshots, leads, and sync logs have been permanently deleted.', 'dashd-analytics-pro'); ?></p></div>
        <?php elseif ($status === 'cron_saved'): ?>
            <div class="notice notice-success is-dismissible"><p>⏱️ <strong><?php esc_html_e('Schedule updated.', 'dashd-analytics-pro'); ?></strong></p></div>
        <?php endif; ?>

        <div class="dashd-grid dashd-grid-4">
            <div class="dashd-card" style="text-align: center;">
                <p class="dashd-stat-title"><?php esc_html_e('Total Records', 'dashd-analytics-pro'); ?></p>
                <p class="dashd-stat-value"><?php echo number_format((int) $stats['recs'], 0, '', ' '); ?></p>
            </div>
            <div class="dashd-card" style="text-align: center;">
                <p class="dashd-stat-title"><?php esc_html_e('Countries', 'dashd-analytics-pro'); ?></p>
                <p class="dashd-stat-value"><?php echo number_format((int) $stats['cty'], 0, '', ' '); ?></p>
            </div>
            <div class="dashd-card" style="text-align: center;">
                <p class="dashd-stat-title"><?php esc_html_e('Indicators', 'dashd-analytics-pro'); ?></p>
                <p class="dashd-stat-value"><?php echo number_format((int) $stats['ind'], 0, '', ' '); ?></p>
            </div>
            <div class="dashd-card" style="text-align: center; border-bottom: 3px solid #2271b1;">
                <p class="dashd-stat-title"><?php esc_html_e('Active Sources', 'dashd-analytics-pro'); ?></p>
                <p class="dashd-stat-value highlight"><?php echo number_format((int) $stats['src'], 0, '', ' '); ?></p>
            </div>
        </div>

        <div class="dashd-grid dashd-grid-2">
            <div class="dashd-card dashd-action-card">
                <h3 style="margin-top:0;"><?php esc_html_e('Global Synchronization', 'dashd-analytics-pro'); ?></h3>
                <p style="color:#646970; margin-bottom: 20px;"><?php esc_html_e('Fetch the latest data from all connected remote CSV sources.', 'dashd-analytics-pro'); ?></p>
                <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="dashd-action-card-form">
                    <input type="hidden" name="action" value="dashd_manual_sync">
                    <?php wp_nonce_field('dashd_manual_sync', 'dashd_manual_sync_nonce'); ?>
                    <button type="submit" class="button button-primary" style="display:flex; align-items:center; gap:5px;">
                        <span class="dashicons dashicons-update"></span> <?php esc_html_e('Start Manual Sync', 'dashd-analytics-pro'); ?>
                    </button>
                </form>
            </div>

            <div class="dashd-card dashd-action-card">
                <h3 style="margin-top:0;"><?php esc_html_e('Auto-Synchronization (WP Cron)', 'dashd-analytics-pro'); ?></h3>
                <p style="color:#646970; margin-bottom: 20px;"><?php esc_html_e('Set up automatic daily data fetching from your sources (max once a day).', 'dashd-analytics-pro'); ?></p>
                <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="dashd-action-card-form" style="display:flex; gap:10px; align-items:center;">
                    <input type="hidden" name="action" value="dashd_save_auto_sync">
                    <?php wp_nonce_field('dashd_save_auto_sync', 'dashd_save_auto_sync_nonce'); ?>
                    <select name="auto_sync_status" style="max-width: 200px;">
                        <option value="disabled" <?php selected(get_option('dashd_auto_sync'), 'disabled'); ?>><?php esc_html_e('Disabled', 'dashd-analytics-pro'); ?></option>
                        <option value="enabled" <?php selected(get_option('dashd_auto_sync'), 'enabled'); ?>><?php esc_html_e('Enabled (Daily)', 'dashd-analytics-pro'); ?></option>
                    </select>
                    <button type="submit" class="button button-secondary"><?php esc_html_e('Save Schedule', 'dashd-analytics-pro'); ?></button>
                </form>
            </div>

            <div class="dashd-card danger-zone dashd-action-card">
                <h3 style="margin-top:0; color:#d63638;"><?php esc_html_e('Danger Zone: Wipe Data', 'dashd-analytics-pro'); ?></h3>
                <p style="color:#646970; margin-bottom: 20px;"><?php esc_html_e('This action will permanently delete all records, snapshots, and captured leads.', 'dashd-analytics-pro'); ?></p>
                <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="dashd-action-card-form" onsubmit="return confirm('<?php esc_attr_e('Are you absolutely sure you want to wipe all plugin data (records, snapshots, leads, logs)? This cannot be undone!', 'dashd-analytics-pro'); ?>');">
                    <input type="hidden" name="action" value="dashd_wipe_all_data">
                    <?php wp_nonce_field('dashd_wipe_all_data', 'dashd_wipe_all_data_nonce'); ?>
                    <button type="submit" class="button" style="color:#d63638; border-color:#d63638; display:flex; align-items:center; gap:5px;">
                        <span class="dashicons dashicons-trash"></span> <?php esc_html_e('Wipe All Plugin Data', 'dashd-analytics-pro'); ?>
                    </button>
                </form>
            </div>
        </div>

        <h3 style="margin-top: 40px;"><?php esc_html_e('Recent Sync Logs', 'dashd-analytics-pro'); ?></h3>
        <div class="dashd-table-container">
            <table class="dashd-table" style="width:100%;">
                <thead><tr><th style="text-align:left;"><?php esc_html_e('Date & Time', 'dashd-analytics-pro'); ?></th><th style="text-align:left;"><?php esc_html_e('Status', 'dashd-analytics-pro'); ?></th><th><?php esc_html_e('Added', 'dashd-analytics-pro'); ?></th><th><?php esc_html_e('Updated', 'dashd-analytics-pro'); ?></th><th style="text-align:left;"><?php esc_html_e('Details', 'dashd-analytics-pro'); ?></th></tr></thead>
                <tbody>
                    <?php 
                    $logs = get_option('dashd_sync_logs', []);
                    if (empty($logs)): ?>
                        <tr><td colspan="5" style="text-align:center; padding: 20px; color: #646970;"><?php esc_html_e('No sync logs available yet.', 'dashd-analytics-pro'); ?></td></tr>
                    <?php else: foreach($logs as $l):
                        $status_raw = isset($l['status']) ? (string) $l['status'] : '';
                        $status_class = 'dashd-sync-status dashd-sync-status--other';
                        if ($status_raw === 'Error') {
                            $status_class = 'dashd-sync-status dashd-sync-status--error';
                        } elseif ($status_raw === 'Success') {
                            $status_class = 'dashd-sync-status dashd-sync-status--success';
                        } elseif ($status_raw === 'No Changes') {
                            $status_class = 'dashd-sync-status dashd-sync-status--no-changes';
                        }
                    ?>
                        <tr>
                            <td style="text-align:left;"><strong><?php echo esc_html(function_exists('mysql2date') ? mysql2date('d.m.Y H:i', (string) ($l['time'] ?? ''), true) : wp_date('d.m.Y H:i', strtotime((string) ($l['time'] ?? '')))); ?></strong></td>
                            <td style="text-align:left;" class="<?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_raw); ?></td>
                            <td><?php echo (int)$l['added']; ?></td>
                            <td><?php echo (int)$l['updated']; ?></td>
                            <td style="text-align:left; font-size:11px; color:#646970; white-space:pre-wrap;"><?php echo esc_html($l['log']); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

add_action('admin_post_dashd_save_auto_sync', function() {
    if (function_exists('dashd_enforce_http_method')) {
        dashd_enforce_http_method('POST');
    }

    if (!current_user_can('manage_options')) {
        if (function_exists('dashd_forbidden_response')) {
            dashd_forbidden_response(false);
        }
        wp_die(__('Access denied', 'dashd-analytics-pro'));
    }
    check_admin_referer('dashd_save_auto_sync', 'dashd_save_auto_sync_nonce');
    $auto_sync_raw = isset($_POST['auto_sync_status']) ? wp_unslash((string) $_POST['auto_sync_status']) : 'disabled';
    $auto_sync = sanitize_key($auto_sync_raw);
    if (!in_array($auto_sync, ['enabled', 'disabled'], true)) {
        $auto_sync = 'disabled';
    }
    update_option('dashd_auto_sync', $auto_sync);
    wp_redirect(admin_url('admin.php?page=dashd-main&status=cron_saved'));
    exit;
});

add_action('admin_post_dashd_wipe_all_data', function() {
    if (function_exists('dashd_enforce_http_method')) {
        dashd_enforce_http_method('POST');
    }

    if (!current_user_can('manage_options')) {
        if (function_exists('dashd_forbidden_response')) {
            dashd_forbidden_response(false);
        }
        wp_die(__('Access denied', 'dashd-analytics-pro'));
    }
    check_admin_referer('dashd_wipe_all_data', 'dashd_wipe_all_data_nonce');

    global $wpdb;
    $tables = [
        "{$wpdb->prefix}dashd_data_records",
        "{$wpdb->prefix}dashd_snapshots",
        "{$wpdb->prefix}dashd_leads",
    ];

    foreach ($tables as $table) {
        $exists = (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists !== $table) {
            continue;
        }

        $truncate_result = $wpdb->query("TRUNCATE TABLE {$table}");
        if ($truncate_result === false) {
            // Shared hosting / DB permissions may block TRUNCATE; fall back to DELETE.
            $wpdb->query("DELETE FROM {$table}");
        }
    }

    update_option('dashd_sync_logs', []);
    delete_option('dashd_last_global_sync');
    dashd_clear_all_caches();

    wp_redirect(admin_url('admin.php?page=dashd-main&status=wiped'));
    exit;
});
