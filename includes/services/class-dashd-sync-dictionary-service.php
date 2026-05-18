<?php
/**
 * Sync dictionary service.
 * Caches indicator/country ids and resolves missing names on demand.
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('DashD_Sync_Dictionary_Service')) {
    class DashD_Sync_Dictionary_Service {
        /** @var array<string,int> */
        private $indicator_id_map = [];

        /** @var array<string,int> */
        private $country_id_map = [];

        public function __construct() {
            global $wpdb;

            $existing_indicators = $wpdb->get_results("SELECT id, name_en FROM {$wpdb->prefix}dashd_indicators", ARRAY_A);
            if (is_array($existing_indicators)) {
                foreach ($existing_indicators as $row) {
                    $name = trim((string) ($row['name_en'] ?? ''));
                    $id = (int) ($row['id'] ?? 0);
                    if ($name !== '' && $id > 0) {
                        $this->indicator_id_map[$name] = $id;
                    }
                }
            }

            $existing_countries = $wpdb->get_results("SELECT id, name_en FROM {$wpdb->prefix}dashd_countries", ARRAY_A);
            if (is_array($existing_countries)) {
                foreach ($existing_countries as $row) {
                    $name = trim((string) ($row['name_en'] ?? ''));
                    $id = (int) ($row['id'] ?? 0);
                    if ($name !== '' && $id > 0) {
                        $this->country_id_map[$name] = $id;
                    }
                }
            }
        }

        public function get_indicator_id($name) {
            global $wpdb;

            $name = trim((string) $name);
            if ($name === '') {
                return 0;
            }

            if (isset($this->indicator_id_map[$name])) {
                return (int) $this->indicator_id_map[$name];
            }

            $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->prefix}dashd_indicators (name_en) VALUES (%s)", $name));
            $id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}dashd_indicators WHERE name_en = %s", $name));
            if ($id > 0) {
                $this->indicator_id_map[$name] = $id;
            }

            return $id;
        }

        public function get_country_id($name) {
            global $wpdb;

            $name = trim((string) $name);
            if ($name === '') {
                return 0;
            }

            if (isset($this->country_id_map[$name])) {
                return (int) $this->country_id_map[$name];
            }

            $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->prefix}dashd_countries (name_en) VALUES (%s)", $name));
            $id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}dashd_countries WHERE name_en = %s", $name));
            if ($id > 0) {
                $this->country_id_map[$name] = $id;
            }

            return $id;
        }
    }
}
