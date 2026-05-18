<?php
/**
 * Lead repository (DB access layer).
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('DashD_Lead_Repository')) {
    class DashD_Lead_Repository {
        /**
         * Find recent lead id for deduplication window.
         */
        public static function find_recent_id($email, $download_type, $source, $since) {
            global $wpdb;

            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}dashd_leads
                 WHERE email = %s AND download_type = %s AND widget_source = %s AND created_at >= %s
                 ORDER BY id DESC LIMIT 1",
                (string) $email,
                (string) $download_type,
                (string) $source,
                (string) $since
            ));
        }

        /**
         * Insert new lead row.
         *
         * @return int Insert id or 0 when failed.
         */
        public static function insert($email, $download_type, $source, $created_at) {
            global $wpdb;

            $inserted = $wpdb->insert("{$wpdb->prefix}dashd_leads", [
                'email' => (string) $email,
                'download_type' => (string) $download_type,
                'widget_source' => (string) $source,
                'created_at' => (string) $created_at,
            ]);

            if ($inserted === false) {
                return 0;
            }

            return (int) $wpdb->insert_id;
        }
    }
}
