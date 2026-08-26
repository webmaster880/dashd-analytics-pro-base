<?php
/**
 * Database Schema
 */

if (!defined('ABSPATH')) exit;

function dashd_get_schema_target_version() {
    return defined('DASHD_DB_SCHEMA_VERSION') ? DASHD_DB_SCHEMA_VERSION : '1.0.0';
}

function dashd_table_exists($table_name) {
    global $wpdb;
    $found = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
    return $found === $table_name;
}

function dashd_ensure_required_columns() {
    global $wpdb;

    $countries_table = "{$wpdb->prefix}dashd_countries";
    $indicators_table = "{$wpdb->prefix}dashd_indicators";

    $required_columns = [
        $countries_table => [
            'flag_url' => "ALTER TABLE {$countries_table} ADD COLUMN flag_url varchar(2048) DEFAULT '' NOT NULL",
            'sort_order' => "ALTER TABLE {$countries_table} ADD COLUMN sort_order int(11) DEFAULT 0 NOT NULL",
        ],
        $indicators_table => [
            'is_calculated' => "ALTER TABLE {$indicators_table} ADD COLUMN is_calculated tinyint(1) DEFAULT 0 NOT NULL",
            'formula' => "ALTER TABLE {$indicators_table} ADD COLUMN formula varchar(255) DEFAULT '' NOT NULL",
            'target_source' => "ALTER TABLE {$indicators_table} ADD COLUMN target_source varchar(50) DEFAULT 'all' NOT NULL",
            'sort_order' => "ALTER TABLE {$indicators_table} ADD COLUMN sort_order int(11) DEFAULT 0 NOT NULL",
        ],
    ];

    foreach ($required_columns as $table_name => $columns) {
        if (!dashd_table_exists($table_name)) {
            continue;
        }

        foreach ($columns as $column_name => $alter_sql) {
            $column_exists = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table_name} LIKE %s", $column_name));
            if (!$column_exists) {
                $wpdb->query($alter_sql);
            }
        }
    }
}

function dashd_init_analytical_db() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql_settings = "CREATE TABLE {$wpdb->prefix}dashd_settings (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        source_key varchar(50) NOT NULL,
        source_label varchar(255) NOT NULL,
        source_url text NOT NULL,
        source_type varchar(20) DEFAULT 'csv' NOT NULL,
        api_method varchar(10) DEFAULT 'GET' NOT NULL,
        api_headers text DEFAULT '' NOT NULL,
        sync_interval varchar(20) DEFAULT 'daily',
        last_sync datetime DEFAULT '0000-00-00 00:00:00',
        PRIMARY KEY  (id),
        UNIQUE KEY source_key (source_key)
    ) $charset_collate;";

    $sql_countries = "CREATE TABLE {$wpdb->prefix}dashd_countries (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name_en varchar(255) NOT NULL,
        name_uk varchar(255) DEFAULT NULL,
        name_hy varchar(255) DEFAULT NULL,
        name_ro varchar(255) DEFAULT NULL,
        name_ka varchar(255) DEFAULT NULL,
        flag_url varchar(2048) DEFAULT '' NOT NULL,
        sort_order int(11) DEFAULT 0 NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY name_en (name_en),
        KEY sort_order (sort_order)
    ) $charset_collate;";

    $sql_indicators = "CREATE TABLE {$wpdb->prefix}dashd_indicators (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name_en varchar(255) NOT NULL,
        name_uk varchar(255) DEFAULT NULL,
        name_hy varchar(255) DEFAULT NULL,
        name_ro varchar(255) DEFAULT NULL,
        name_ka varchar(255) DEFAULT NULL,
        is_calculated tinyint(1) DEFAULT 0 NOT NULL,
        formula varchar(255) DEFAULT '' NOT NULL,
        target_source varchar(50) DEFAULT 'all' NOT NULL,
        sort_order int(11) DEFAULT 0 NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY name_en (name_en),
        KEY is_calculated (is_calculated),
        KEY target_source (target_source),
        KEY sort_order (sort_order)
    ) $charset_collate;";

    $sql_records = "CREATE TABLE {$wpdb->prefix}dashd_data_records (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        source_key varchar(50) NOT NULL,
        indicator_id mediumint(9) NOT NULL,
        country_id mediumint(9) NOT NULL,
        val double DEFAULT 0 NOT NULL,
        data_year int(4) NOT NULL,
        data_quarter varchar(10) NOT NULL,
        record_date date NOT NULL,
        PRIMARY KEY  (id),
        KEY source_key (source_key),
        KEY period (data_year, data_quarter),
        KEY source_indicator_country_period (source_key, indicator_id, country_id, data_year, data_quarter),
        KEY indicator_country_period (indicator_id, country_id, data_year, data_quarter)
    ) $charset_collate;";

    $sql_leads = "CREATE TABLE {$wpdb->prefix}dashd_leads (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        email varchar(255) NOT NULL,
        download_type varchar(50) DEFAULT NULL,
        widget_source varchar(255) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY email (email)
    ) $charset_collate;";

    $sql_snapshots = "CREATE TABLE {$wpdb->prefix}dashd_snapshots (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        source_key varchar(50) NOT NULL,
        sync_date datetime NOT NULL,
        records_count int(11) NOT NULL,
        data_dump longtext NOT NULL,
        PRIMARY KEY  (id),
        KEY source_key (source_key)
    ) $charset_collate;";

    dbDelta($sql_settings);
    dbDelta($sql_countries);
    dbDelta($sql_indicators);
    dbDelta($sql_records);
    dbDelta($sql_leads);
    dbDelta($sql_snapshots);

    dashd_ensure_required_columns();
    update_option('dashd_db_schema_version', dashd_get_schema_target_version());
}

function dashd_maybe_upgrade_db_schema() {
    $installed_version = (string) get_option('dashd_db_schema_version', '0.0.0');
    $target_version = dashd_get_schema_target_version();

    if (version_compare($installed_version, $target_version, '<')) {
        dashd_init_analytical_db();
    }
}

add_action('plugins_loaded', 'dashd_maybe_upgrade_db_schema', 30);
