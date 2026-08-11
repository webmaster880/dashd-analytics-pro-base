<?php
/**
 * Admin UI v9.3.0
 * ДОБАВЛЕНО: Интернационализация (i18n) строк интерфейса.
 */

if (!defined('ABSPATH')) exit;

if (!function_exists('dashd_render_admin_sync_controls')) {
function dashd_render_admin_sync_controls() {
    ?>
    <div class="dashd-grid dashd-grid-2" style="margin-bottom: 24px;">
        <div class="dashd-card dashd-action-card">
            <h3 style="margin-top:0;"><?php esc_html_e('Global Synchronization', 'dashd-analytics-pro'); ?></h3>
            <p style="color:#646970; margin-bottom: 20px;"><?php esc_html_e('Fetch the latest data from all connected remote CSV sources.', 'dashd-analytics-pro'); ?></p>
            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="dashd-action-card-form">
                <input type="hidden" name="action" value="dashd_manual_sync">
                <?php wp_nonce_field('dashd_manual_sync', 'dashd_manual_sync_nonce'); ?>
                <button type="submit" class="button button-primary dashd-admin-action-button">
                    <span class="dashicons dashicons-update"></span> <?php esc_html_e('Start Manual Sync', 'dashd-analytics-pro'); ?>
                </button>
            </form>
        </div>

        <div class="dashd-card dashd-action-card">
            <h3 style="margin-top:0;"><?php esc_html_e('Auto-Synchronization (WP Cron)', 'dashd-analytics-pro'); ?></h3>
            <p style="color:#646970; margin-bottom: 20px;"><?php esc_html_e('Set up automatic daily data fetching from your sources (max once a day).', 'dashd-analytics-pro'); ?></p>
            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="dashd-action-card-form dashd-toolbar-form">
                <input type="hidden" name="action" value="dashd_save_auto_sync">
                <?php wp_nonce_field('dashd_save_auto_sync', 'dashd_save_auto_sync_nonce'); ?>
                <select name="auto_sync_status" style="max-width: 200px;">
                    <option value="disabled" <?php selected(get_option('dashd_auto_sync'), 'disabled'); ?>><?php esc_html_e('Disabled', 'dashd-analytics-pro'); ?></option>
                    <option value="enabled" <?php selected(get_option('dashd_auto_sync'), 'enabled'); ?>><?php esc_html_e('Enabled (Daily)', 'dashd-analytics-pro'); ?></option>
                </select>
                <button type="submit" class="button button-secondary dashd-admin-action-button"><?php esc_html_e('Save Schedule', 'dashd-analytics-pro'); ?></button>
            </form>
        </div>

        <div class="dashd-card danger-zone dashd-action-card">
            <h3 style="margin-top:0; color:#d63638;"><?php esc_html_e('Danger Zone: Wipe Data', 'dashd-analytics-pro'); ?></h3>
            <p style="color:#646970; margin-bottom: 20px;"><?php esc_html_e('This action will permanently delete all records, snapshots, and captured leads.', 'dashd-analytics-pro'); ?></p>
            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="dashd-action-card-form" onsubmit="return confirm('<?php esc_attr_e('Are you absolutely sure you want to wipe all plugin data (records, snapshots, leads, logs)? This cannot be undone!', 'dashd-analytics-pro'); ?>');">
                <input type="hidden" name="action" value="dashd_wipe_all_data">
                <?php wp_nonce_field('dashd_wipe_all_data', 'dashd_wipe_all_data_nonce'); ?>
                <button type="submit" class="button dashd-admin-danger-button">
                    <span class="dashicons dashicons-trash"></span> <?php esc_html_e('Wipe All Plugin Data', 'dashd-analytics-pro'); ?>
                </button>
            </form>
        </div>
    </div>
    <?php
}
}

if (!function_exists('dashd_render_admin_sync_logs_table')) {
function dashd_render_admin_sync_logs_table($with_actions = true) {
    $logs = get_option('dashd_sync_logs', []);
    ?>
    <?php if ($with_actions): ?>
    <div class="dashd-toolbar" style="margin-top: 12px;">
        <div class="dashd-toolbar-group">
            <p class="dashd-toolbar-title"><?php esc_html_e('Logs Actions:', 'dashd-analytics-pro'); ?></p>
            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" style="margin:0;">
                <input type="hidden" name="action" value="dashd_export_sync_logs">
                <?php wp_nonce_field('dashd_export_sync_logs', 'dashd_export_sync_logs_nonce'); ?>
                <button type="submit" class="button button-secondary" style="display:flex; align-items:center; gap:5px;">
                    <span class="dashicons dashicons-download"></span> <?php esc_html_e('Export Sync Logs (CSV)', 'dashd-analytics-pro'); ?>
                </button>
            </form>
        </div>
        <div class="dashd-toolbar-divider"></div>
        <div class="dashd-toolbar-group">
            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" style="margin:0;" onsubmit="return confirm('<?php esc_attr_e('Clear all sync logs? This cannot be undone.', 'dashd-analytics-pro'); ?>');">
                <input type="hidden" name="action" value="dashd_clear_sync_logs">
                <?php wp_nonce_field('dashd_clear_sync_logs', 'dashd_clear_sync_logs_nonce'); ?>
                <button type="submit" class="button" style="display:flex; align-items:center; gap:5px; color:#d63638; border-color:#d63638;">
                    <span class="dashicons dashicons-trash"></span> <?php esc_html_e('Clear Sync Logs', 'dashd-analytics-pro'); ?>
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <h3 style="margin-top: 20px;"><?php esc_html_e('Recent Sync Logs', 'dashd-analytics-pro'); ?></h3>
    <div class="dashd-table-container">
        <table class="dashd-table" style="width:100%;">
            <thead><tr><th style="text-align:left;"><?php esc_html_e('Date & Time', 'dashd-analytics-pro'); ?></th><th style="text-align:left;"><?php esc_html_e('Status', 'dashd-analytics-pro'); ?></th><th><?php esc_html_e('Added', 'dashd-analytics-pro'); ?></th><th><?php esc_html_e('Updated', 'dashd-analytics-pro'); ?></th><th style="text-align:left;"><?php esc_html_e('Details', 'dashd-analytics-pro'); ?></th></tr></thead>
            <tbody>
                <?php if (empty($logs)): ?>
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
    <?php
}
}

function dashd_admin_main_page() {
    global $wpdb;
    
    $stats = [
        'recs' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}dashd_data_records"),
        'cty'  => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}dashd_countries"),
        'ind'  => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}dashd_indicators"),
        'src'  => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}dashd_settings")
    ];
    
    ?>
    <div class="wrap">
        <div class="dashd-admin-header">
            <h1><?php esc_html_e('Analytics Pro Dashboard', 'dashd-analytics-pro'); ?> <span class="dashd-badge">v<?php echo esc_html((string) DASHD_VERSION); ?></span></h1>
        </div>

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

        <div class="dashd-card" style="margin-top: 20px;">
            <h3 style="margin-top:0;"><?php esc_html_e('Operational Controls Moved', 'dashd-analytics-pro'); ?></h3>
            <p style="color:#646970; margin-bottom:12px;">
                <?php esc_html_e('Global synchronization, schedule controls, data wipe actions, and sync logs are now managed in Settings tabs.', 'dashd-analytics-pro'); ?>
            </p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=dashd-settings&tab=sources')); ?>" class="button button-primary">
                <?php esc_html_e('Open Data Sources & Raw Data', 'dashd-analytics-pro'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=dashd-settings&tab=logs')); ?>" class="button button-secondary" style="margin-left:8px;">
                <?php esc_html_e('Open Logs', 'dashd-analytics-pro'); ?>
            </a>
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
    wp_redirect(admin_url('admin.php?page=dashd-settings&tab=sources&status=cron_saved'));
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

    wp_redirect(admin_url('admin.php?page=dashd-settings&tab=sources&status=wiped'));
    exit;
});

add_action('admin_post_dashd_export_sync_logs', function() {
    if (function_exists('dashd_enforce_http_method')) {
        dashd_enforce_http_method('POST');
    }
    if (!current_user_can('manage_options')) {
        if (function_exists('dashd_forbidden_response')) {
            dashd_forbidden_response(false);
        }
        wp_die(__('Access denied', 'dashd-analytics-pro'));
    }
    check_admin_referer('dashd_export_sync_logs', 'dashd_export_sync_logs_nonce');

    $logs = get_option('dashd_sync_logs', []);
    if (!is_array($logs) || empty($logs)) {
        wp_die(__('No sync logs available yet.', 'dashd-analytics-pro'));
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="dashd_sync_logs_' . current_time('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));
    fputcsv($output, ['time', 'status', 'added', 'updated', 'log']);
    foreach ($logs as $log_row) {
        $time = isset($log_row['time']) ? (string) $log_row['time'] : '';
        $status = isset($log_row['status']) ? (string) $log_row['status'] : '';
        $added = isset($log_row['added']) ? (int) $log_row['added'] : 0;
        $updated = isset($log_row['updated']) ? (int) $log_row['updated'] : 0;
        $details = isset($log_row['log']) ? (string) $log_row['log'] : '';
        fputcsv($output, [$time, $status, $added, $updated, $details]);
    }
    fclose($output);
    exit;
});

add_action('admin_post_dashd_clear_sync_logs', function() {
    if (function_exists('dashd_enforce_http_method')) {
        dashd_enforce_http_method('POST');
    }
    if (!current_user_can('manage_options')) {
        if (function_exists('dashd_forbidden_response')) {
            dashd_forbidden_response(false);
        }
        wp_die(__('Access denied', 'dashd-analytics-pro'));
    }
    check_admin_referer('dashd_clear_sync_logs', 'dashd_clear_sync_logs_nonce');

    update_option('dashd_sync_logs', []);
    wp_redirect(admin_url('admin.php?page=dashd-settings&tab=logs&status=logs_cleared'));
    exit;
});
