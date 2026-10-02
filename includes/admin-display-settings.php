<?php
/** Widget display settings and authenticated date preview. */
if (!defined('ABSPATH')) exit;

function dashd_read_date_format_input() {
    if (!isset($_POST['date_format']) || !is_string($_POST['date_format'])) {
        return new WP_Error('invalid_format');
    }
    $format = trim(wp_unslash($_POST['date_format']));
    $trailing_escapes = strlen($format) - strlen(rtrim($format, '\\'));
    if (strlen($format) > 100 || preg_match('/[\x00-\x1F\x7F<>]/', $format) || $trailing_escapes % 2 !== 0) {
        return new WP_Error('invalid_format');
    }
    return $format;
}

add_action('admin_post_dashd_save_display_settings', 'dashd_save_display_settings');
function dashd_save_display_settings() {
    dashd_enforce_http_method('POST');
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Access denied', 'dashd-analytics-pro'), '', ['response' => 403]);
    }
    check_admin_referer('dashd_display_settings', 'dashd_display_nonce');
    $format = dashd_read_date_format_input();
    $status = 'display_invalid';
    if (!is_wp_error($format)) {
        $saved = update_option('dashd_last_update_format', $format, false);
        $status = $saved || get_option('dashd_last_update_format') === $format ? 'display_saved' : 'display_failed';
        if ($status === 'display_saved') {
            dashd_clear_all_caches();
        }
    }
    wp_safe_redirect(admin_url('admin.php?page=dashd-settings&tab=display&status=' . $status));
    exit;
}

add_action('wp_ajax_dashd_preview_update_date', 'dashd_preview_update_date');
function dashd_preview_update_date() {
    dashd_enforce_http_method('POST', true);
    if (!current_user_can('manage_options')) {
        wp_send_json_error([], 403);
    }
    check_ajax_referer('dashd_display_settings', 'nonce');
    $format = dashd_read_date_format_input();
    if (is_wp_error($format)) {
        wp_send_json_error([], 400);
    }
    wp_send_json_success(['preview' => dashd_format_update_date(current_time('mysql'), $format)]);
}

add_action('admin_enqueue_scripts', function () {
    if (($_GET['page'] ?? '') === 'dashd-settings' && ($_GET['tab'] ?? '') === 'display') {
        wp_enqueue_script('dashd-display-settings', DASHD_URL . 'assets/admin-display-settings.js', [], DASHD_VERSION, true);
    }
});

function dashd_render_display_tab() {
    $format = (string) get_option('dashd_last_update_format', 'd.m.Y H:i');
    $presets = ['', 'd.m.Y H:i', 'd.m.Y', 'j F Y', 'd F Y', 'j M Y', 'F j, Y', 'Y-m-d', 'j F Y, H:i'];
    $preset = in_array($format, $presets, true) ? $format : 'custom';
    $now = current_time('mysql');
    ?>
    <div class="dashd-card dashd-date-settings">
        <h3><?php esc_html_e('Last updated: date format', 'dashd-analytics-pro'); ?></h3>
        <p><?php esc_html_e('Choose how the last data update is displayed in all charts and PDF reports.', 'dashd-analytics-pro'); ?></p>
        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" id="dashd-date-format-form"
              data-preview-error="<?php esc_attr_e('Preview unavailable. Check the format or try again.', 'dashd-analytics-pro'); ?>">
            <input type="hidden" name="action" value="dashd_save_display_settings">
            <?php wp_nonce_field('dashd_display_settings', 'dashd_display_nonce'); ?>
            <div class="dashd-date-field">
                <label for="dashd-date-preset"><?php esc_html_e('Date format', 'dashd-analytics-pro'); ?></label>
                <select id="dashd-date-preset">
                    <?php foreach ($presets as $value): ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($preset, $value); ?>><?php echo esc_html($value === '' ? __('WordPress date and time settings', 'dashd-analytics-pro') : $value . ' (' . dashd_format_update_date($now, $value) . ')'); ?></option>
                    <?php endforeach; ?>
                    <option value="custom" <?php selected($preset, 'custom'); ?>><?php esc_html_e('Custom format', 'dashd-analytics-pro'); ?></option>
                </select>
            </div>
            <div class="dashd-date-field">
                <label for="dashd-date-format"><?php esc_html_e('Format template', 'dashd-analytics-pro'); ?></label>
                <input type="text" class="regular-text" id="dashd-date-format" name="date_format" maxlength="100" value="<?php echo esc_attr($format); ?>" placeholder="j F Y" aria-describedby="dashd-date-help">
                <p class="description" id="dashd-date-help"><?php esc_html_e('j: day; d: day with leading zero; F: full month; M: short month; Y: year; H:i: hours and minutes. Leave empty to use WordPress settings.', 'dashd-analytics-pro'); ?></p>
            </div>
            <div class="dashd-date-preview">
                <strong><?php esc_html_e('Preview:', 'dashd-analytics-pro'); ?></strong>
                <output id="dashd-date-preview" aria-live="polite"><?php echo esc_html(dashd_format_update_date($now, $format)); ?></output>
            </div>
            <p class="description"><?php esc_html_e('The preview uses today\'s date and your admin language. Charts use the page language when its WordPress language pack is installed. All times use the WordPress timezone.', 'dashd-analytics-pro'); ?></p>
            <p><button type="submit" class="button button-primary dashd-admin-action-button"><?php esc_html_e('Save date format', 'dashd-analytics-pro'); ?></button></p>
        </form>
    </div>
    <?php
}
