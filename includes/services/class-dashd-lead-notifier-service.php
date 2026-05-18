<?php
/**
 * Lead notifications integration service.
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('DashD_Lead_Notifier_Service')) {
    class DashD_Lead_Notifier_Service {
        public static function send_crm_webhook($email, $download_type, $source, $time_now) {
            $crm_raw = function_exists('dashd_get_sensitive_setting')
                ? dashd_get_sensitive_setting('dashd_crm_webhook', 'DASHD_CRM_WEBHOOK')
                : (string) get_option('dashd_crm_webhook', '');
            $crm_webhook = function_exists('dashd_sanitize_webhook_url')
                ? dashd_sanitize_webhook_url($crm_raw)
                : trim((string) $crm_raw);

            if ($crm_webhook === '' || !wp_http_validate_url($crm_webhook)) {
                return;
            }

            $payload = [
                'email' => (string) $email,
                'download_type' => (string) $download_type,
                'widget_source' => (string) $source,
                'timestamp' => (string) $time_now,
            ];

            wp_safe_remote_post($crm_webhook, [
                'blocking' => false,
                'timeout' => 5,
                'redirection' => 2,
                'reject_unsafe_urls' => true,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => wp_json_encode($payload),
            ]);
        }
    }
}
