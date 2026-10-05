<?php
/** Isolated updater regressions: mocked HTTP/cache, real WordPress filter dispatch. */
if (PHP_SAPI !== 'cli') exit;
$root = dirname(__DIR__);
$wp_root = $argv[1] ?? dirname($root, 3);
if (!is_file($wp_root . '/wp-includes/plugin.php')) {
    fwrite(STDERR, "Usage: php bin/smoke-github-updater.php /path/to/wordpress\n");
    exit(1);
}
define('ABSPATH', rtrim($wp_root, '/') . '/');
define('WP_PLUGIN_DIR', dirname($root));
define('WPMU_PLUGIN_DIR', dirname($root) . '/mu-plugins');
define('DASHD_FILE', $root . '/dashd-analytics-pro.php');
define('DASHD_VERSION', '11.9.18');
define('DASHD_GITHUB_REPO', 'example/plugin');
define('HOUR_IN_SECONDS', 3600);
require ABSPATH . 'wp-includes/plugin.php';
require ABSPATH . 'wp-includes/class-wp-error.php';
$wp_plugin_paths = [];
function __($s, $domain = '') { return $s; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_normalize_path($file) { return $file; }
function get_transient($key) { return $GLOBALS['cache'][$key] ?? false; }
function set_transient($key, $value, $ttl) { $GLOBALS['cache'][$key] = $value; }
function delete_transient($key) { unset($GLOBALS['cache'][$key]); }
function get_option($key, $default = false) { return $default; }
function get_site_transient($key) { return $GLOBALS['updates']; }
function set_site_transient($key, $value) {
    $GLOBALS['updates'] = apply_filters('pre_set_site_transient_' . $key, $value);
}
function wp_clean_plugins_cache($clear) { if ($clear) throw new RuntimeException('Global update cache must be preserved'); }
function wp_parse_url($url, $component) { return parse_url($url, $component); }
function wp_remote_retrieve_response_code($r) { return $r['response']['code']; }
function wp_remote_retrieve_body($r) { return $r['body']; }
function wp_remote_get($url, $args) { $GLOBALS['github_requests']++; return $GLOBALS['github_response']; }
function wp_update_plugins($stats = []) {
    if (!$stats) throw new RuntimeException('Manual check must bypass the core timeout');
    if ($GLOBALS['core_response'] !== null) {
        do_action('http_api_debug', $GLOBALS['core_response'], 'response', 'Test', [], 'https://api.wordpress.org/plugins/update-check/1.1/');
    }
    $GLOBALS['updates'] = apply_filters('pre_set_site_transient_update_plugins', $GLOBALS['updates']);
}
require $root . '/includes/github-updater.php';
dashd_register_github_updater(DASHD_FILE, DASHD_VERSION);

function response($code, $body) { return ['response' => ['code' => $code], 'body' => json_encode($body)]; }
function reset_case($latest = '11.9.18') {
    $GLOBALS['cache'] = [];
    $GLOBALS['github_requests'] = 0;
    $GLOBALS['github_response'] = response(200, ['tag_name' => 'v' . $latest, 'zipball_url' => 'https://example.com/plugin.zip']);
    $GLOBALS['core_response'] = response(200, ['plugins' => [], 'no_update' => []]);
    $GLOBALS['updates'] = (object) ['checked' => ['other/plugin.php' => '1.0'], 'response' => ['other/plugin.php' => (object) ['new_version' => '2.0']]];
}
$checks = 0;
function expect($condition, $label) {
    $GLOBALS['checks']++;
    if (!$condition) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
}
reset_case();
$r = dashd_github_updater_check_now();
expect(is_array($r) && $r['latest'] === DASHD_VERSION && !$r['dashd_update_available'], 'Already current is explicit');
expect($r['updates_count'] === 1, 'Other plugin update preserved');
expect(!has_action('http_api_debug'), 'Observer removed');
$before = $github_requests;
dashd_github_updater_check_now();
expect($github_requests === $before + 1, 'Manual check bypasses cached release');
reset_case('11.9.19');
$r = dashd_github_updater_check_now();
expect($r['dashd_update_available'] && $r['updates_count'] === 2, 'New release registered alongside other updates');
reset_case();
$github_response = response(403, ['message' => 'Rate limit']);
$r = dashd_github_updater_check_now();
expect(is_wp_error($r) && strpos($r->get_error_message(), '403') !== false, 'GitHub HTTP failure surfaced');
expect(isset($updates->response['other/plugin.php']), 'GitHub error preserves other updates');
reset_case();
$github_response = new WP_Error('http_request_failed', 'Connection timed out');
expect(is_wp_error(dashd_github_updater_check_now()), 'GitHub network error surfaced');
foreach ([new WP_Error('http_request_failed', 'Connection timed out'), response(403, []), response(200, ['unexpected' => true]), null] as $failure) {
    reset_case();
    $core_response = $failure;
    expect(is_wp_error(dashd_github_updater_check_now()), 'Core failure cannot report success');
    expect(!has_action('http_api_debug'), 'Observer removed after failure');
}
reset_case();
add_filter('dashd_github_updater_enabled', '__return_false');
function __return_false() { return false; }
expect(is_wp_error(dashd_github_updater_check_now()), 'Disabled updater cannot report success');
remove_filter('dashd_github_updater_enabled', '__return_false');
reset_case('11.9.19');
$updates->last_checked = 123;
do_action('wp_update_plugins');
expect(isset($updates->response[plugin_basename(DASHD_FILE)]), 'Subsite cron discovers GitHub update without core callback');
expect(isset($updates->response['other/plugin.php']), 'Scheduled check preserves other plugins');
expect($updates->last_checked === 123, 'GitHub-only check preserves core check timestamp');
expect($github_requests === 1, 'Scheduled check fetches uncached release');
do_action('wp_update_plugins');
expect($github_requests === 1, 'Scheduled check respects GitHub TTL cache');
reset_case();
$original = clone $updates;
$github_response = new WP_Error('http_request_failed', 'Connection timed out');
do_action('wp_update_plugins');
expect($updates == $original, 'Scheduled failure leaves global cache intact');
reset_case('11.9.19');
$updates = false;
do_action('wp_update_plugins');
expect(isset($updates->response[plugin_basename(DASHD_FILE)]), 'Scheduled check works with missing core cache');
echo "OK: {$checks} updater checks passed.\n";
