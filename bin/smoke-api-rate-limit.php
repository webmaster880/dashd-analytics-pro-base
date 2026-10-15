<?php
// Lightweight regression test that does not bootstrap or write to WordPress.
define('ABSPATH', __DIR__ . '/../');
$transients = [];
$writes = 0;
function add_action() {}
function get_transient($key) {
    global $transients;
    return $transients[$key] ?? false;
}
function set_transient($key, $value, $ttl) {
    global $transients, $writes;
    $transients[$key] = $value;
    $writes++;
    return true;
}
require_once __DIR__ . '/../includes/analytics-api.php';

$key = 'smoke_rl';
if (dashd_api_rate_limit_exceeded($key, 2, 60)) {
    throw new RuntimeException('First request was blocked.');
}
$reset_at = $transients[$key]['reset_at'];
if (dashd_api_rate_limit_exceeded($key, 2, 60)) {
    throw new RuntimeException('Second request was blocked.');
}
if (!dashd_api_rate_limit_exceeded($key, 2, 60)) {
    throw new RuntimeException('Third request was not blocked.');
}
if ($writes !== 2 || $transients[$key]['reset_at'] !== $reset_at) {
    throw new RuntimeException('Rejected request changed the rate-limit window.');
}
$transients[$key]['reset_at'] = time() - 1;
if (dashd_api_rate_limit_exceeded($key, 2, 60)) {
    throw new RuntimeException('Expired window did not reset.');
}
echo "API rate-limit smoke checks passed.\n";
