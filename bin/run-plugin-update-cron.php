<?php
/** WP-CLI eval-file helper: execute only due plugin update checks. */
if (!defined('WP_CLI') || !WP_CLI || !defined('ABSPATH')) exit;

require_once ABSPATH . 'wp-includes/update.php';
// Core does not register this action in a Multisite subsite context.
add_action('wp_update_plugins', 'wp_update_plugins');
if (!wp_next_scheduled('wp_update_plugins')) {
    $scheduled = wp_schedule_event(time(), 'twicedaily', 'wp_update_plugins', [], true);
    if (is_wp_error($scheduled)) {
        WP_CLI::error($scheduled->get_error_message());
    }
}
WP_CLI::log(gmdate('c') . ' Checking due plugin update events.');
WP_CLI::runcommand('cron event run wp_update_plugins --due-now', ['launch' => false]);
