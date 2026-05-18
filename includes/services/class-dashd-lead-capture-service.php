<?php
/**
 * Lead capture service.
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('DashD_Lead_Capture_Service')) {
    class DashD_Lead_Capture_Service {
        /**
         * @return array{email:string,hp:string,type:string,source:string,nonce:string}
         */
        public static function sanitize_input(array $post) {
            $email = sanitize_email((string) wp_unslash((string) ($post['email'] ?? '')));
            $email = strtolower(trim($email));
            $hp = sanitize_text_field((string) wp_unslash((string) ($post['hp'] ?? '')));
            $type = strtolower(trim(sanitize_text_field((string) wp_unslash((string) ($post['type'] ?? '')))));
            $source = function_exists('dashd_normalize_source_key')
                ? dashd_normalize_source_key((string) wp_unslash((string) ($post['source'] ?? 'table1')), 'table1')
                : sanitize_key((string) wp_unslash((string) ($post['source'] ?? 'table1')));
            if ($source === '') {
                $source = 'table1';
            }
            $nonce = sanitize_text_field((string) wp_unslash((string) ($post['nonce'] ?? '')));

            return [
                'email' => $email,
                'hp' => $hp,
                'type' => $type,
                'source' => $source,
                'nonce' => $nonce,
            ];
        }

        /**
         * @param array{email:string,hp:string,type:string,source:string,nonce:string} $input
         * @return true|WP_Error
         */
        public static function validate(array $input) {
            $source = (string) ($input['source'] ?? 'table1');
            $nonce = (string) ($input['nonce'] ?? '');
            $email = (string) ($input['email'] ?? '');
            $type = (string) ($input['type'] ?? '');
            $hp = (string) ($input['hp'] ?? '');

            if ($nonce === '' || !wp_verify_nonce($nonce, 'dashd_capture_lead_' . $source)) {
                return self::error('invalid_nonce', 'Security check failed. Please refresh the page and try again.', 403);
            }

            if (!function_exists('dashd_api_is_allowed_source') || !dashd_api_is_allowed_source($source)) {
                return self::error('invalid_source', 'Invalid data source.', 400);
            }

            if ($hp !== '') {
                return self::error('bot_detected', 'Bot activity detected.', 400);
            }

            if (!is_email($email)) {
                return self::error('invalid_email', 'Please enter a valid email address.', 400);
            }

            if (!in_array($type, ['csv', 'pdf'], true)) {
                return self::error('invalid_type', 'Invalid download type.', 400);
            }

            $client_ip = function_exists('dashd_api_get_client_ip')
                ? dashd_api_get_client_ip()
                : '0.0.0.0';
            $ip_key = 'dashd_lead_rl_ip_' . md5((string) $client_ip);
            $email_key = 'dashd_lead_rl_em_' . md5(strtolower($email));

            if (function_exists('dashd_api_rate_limit_exceeded') && dashd_api_rate_limit_exceeded($ip_key, 12, 10 * MINUTE_IN_SECONDS)) {
                return self::error('ip_rate_limited', 'Too many requests. Please try again later.', 429);
            }

            if (function_exists('dashd_api_rate_limit_exceeded') && dashd_api_rate_limit_exceeded($email_key, 4, HOUR_IN_SECONDS)) {
                return self::error('email_rate_limited', 'Too many attempts for this email. Please try later.', 429);
            }

            $domain = (string) substr((string) strrchr($email, '@'), 1);
            $disposable = [
                'mailinator.com',
                'temp-mail.org',
                '10minutemail.com',
                'guerrillamail.com',
                'yopmail.com',
                'dropmail.me',
                'tempmail.com',
                'throwawaymail.com',
                'trashmail.com',
            ];
            if (in_array(strtolower($domain), $disposable, true)) {
                return self::error('disposable_email', 'Disposable emails are not allowed.', 400);
            }

            if (function_exists('dashd_api_is_valid_email_domain') && !dashd_api_is_valid_email_domain($domain)) {
                return self::error('invalid_domain', 'Invalid email domain.', 400);
            }

            return true;
        }

        /**
         * @param array{email:string,hp:string,type:string,source:string,nonce:string} $input
         * @return array{deduplicated:bool}|WP_Error
         */
        public static function capture(array $input) {
            $email = (string) $input['email'];
            $source = (string) $input['source'];
            $download_type = strtoupper((string) $input['type']);
            $time_now = current_time('mysql');

            $since = wp_date('Y-m-d H:i:s', current_time('timestamp') - DAY_IN_SECONDS);
            $existing_recent = class_exists('DashD_Lead_Repository')
                ? DashD_Lead_Repository::find_recent_id($email, $download_type, $source, $since)
                : 0;
            if ($existing_recent > 0) {
                return ['deduplicated' => true];
            }

            $insert_id = class_exists('DashD_Lead_Repository')
                ? DashD_Lead_Repository::insert($email, $download_type, $source, $time_now)
                : 0;
            if ($insert_id <= 0) {
                return self::error('db_insert_failed', 'Unable to save lead right now.', 500);
            }

            if (class_exists('DashD_Lead_Notifier_Service')) {
                DashD_Lead_Notifier_Service::send_crm_webhook($email, $download_type, $source, $time_now);
            }

            return ['deduplicated' => false];
        }

        /**
         * @return WP_Error
         */
        private static function error($code, $message, $status = 400) {
            return new WP_Error(
                (string) $code,
                (string) $message,
                ['status' => (int) $status]
            );
        }
    }
}
