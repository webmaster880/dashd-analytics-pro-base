<?php
/** Run against WordPress core without loading the site or writing to its database. */
if (PHP_SAPI !== 'cli') exit;
$root = dirname(__DIR__);
$wp_root = $argv[1] ?? dirname($root, 3);
if (!is_file($wp_root . '/wp-includes/functions.php')) {
    fwrite(STDERR, "Usage: php bin/smoke-date-format.php /path/to/wordpress\n");
    exit(1);
}
define('ABSPATH', rtrim($wp_root, '/') . '/');
define('WPINC', 'wp-includes');
require ABSPATH . WPINC . '/plugin.php';
require ABSPATH . WPINC . '/functions.php';
require ABSPATH . WPINC . '/formatting.php';
require ABSPATH . WPINC . '/class-wp-error.php';
require ABSPATH . WPINC . '/class-wp-locale.php';

$options = ['timezone_string' => 'Europe/Kyiv', 'date_format' => 'Y-m-d', 'time_format' => 'H:i'];
add_filter('pre_option', static function ($value, $name, $default) use (&$options) {
    return $options[$name] ?? $default;
}, 10, 3);

// Only translation loading is simulated; date formatting uses actual WordPress core.
$locale = 'en_US';
$previous_locale = 'en_US';
function __($text, $domain = 'default') {
    return $GLOBALS['locale'] === 'uk' && $text === 'October' ? 'Жовтень' : $text;
}
function _x($text, $context, $domain = 'default') { return __($text, $domain); }
function get_locale() { return $GLOBALS['locale']; }
function switch_to_locale($locale) {
    if (!in_array($locale, ['en_US', 'uk'], true)) return false;
    $GLOBALS['previous_locale'] = $GLOBALS['locale'];
    $GLOBALS['locale'] = $locale;
    $GLOBALS['wp_locale'] = new WP_Locale();
    return true;
}
function restore_previous_locale() {
    $GLOBALS['locale'] = $GLOBALS['previous_locale'];
    $GLOBALS['wp_locale'] = new WP_Locale();
}
$wp_locale = new WP_Locale();
require $root . '/includes/date-format.php';
require $root . '/includes/admin-display-settings.php';

$checks = 0;
function expect_same($actual, $expected, $label) {
    $GLOBALS['checks']++;
    if ($actual !== $expected) {
        fwrite(STDERR, 'FAIL: ' . $label . ': ' . var_export($actual, true) . "\n");
        exit(1);
    }
}
$sync = '2026-10-01 14:30:00';
foreach ([
    'd.m.Y H:i' => '01.10.2026 14:30',
    'd.m.Y' => '01.10.2026',
    'j F Y' => '1 October 2026',
    'd F Y' => '01 October 2026',
    'j M Y' => '1 Oct 2026',
    'F j, Y' => 'October 1, 2026',
    'Y-m-d' => '2026-10-01',
    'j F Y, H:i' => '1 October 2026, 14:30',
    'Y-m-d\TH:i' => '2026-10-01T14:30',
] as $format => $expected) {
    expect_same(dashd_format_update_date($sync, $format), $expected, $format);
}
expect_same(dashd_format_update_date($sync), '01.10.2026 14:30', 'Existing default');
$options['dashd_last_update_format'] = 'j F Y';
expect_same(dashd_format_update_date($sync), '1 October 2026', 'Saved custom format');
$options['dashd_last_update_format'] = '';
expect_same(dashd_format_update_date($sync), '2026-10-01 14:30', 'WordPress fallback');
expect_same(dashd_format_update_date($sync, 'H:i P'), '14:30 +03:00', 'Summer timezone without double offset');
expect_same(dashd_format_update_date('2026-12-01 14:30:00', 'H:i P'), '14:30 +02:00', 'Winter timezone');
expect_same(dashd_format_update_date($sync, 'j F Y', 'uk'), '1 Жовтень 2026', 'Localized month');
expect_same(get_locale(), 'en_US', 'Locale restored');
expect_same(dashd_format_update_date($sync, 'j F Y', 'ka_GE'), '1 October 2026', 'Unavailable locale fallback');
foreach (['', '0000-00-00 00:00:00', 'invalid', '2026-02-30 12:00:00'] as $invalid) {
    expect_same(dashd_format_update_date($invalid), '--', 'Invalid sync time');
}
foreach (['', 'j F Y', 'Y-m-d\TH:i', 'Y \\\\'] as $valid) {
    $_POST['date_format'] = wp_slash($valid);
    expect_same(dashd_read_date_format_input(), $valid, 'Input preserves escaping');
}
foreach ([[], '<script>', "Y\nF", str_repeat('Y', 101), 'Y\\'] as $invalid) {
    $_POST['date_format'] = wp_slash($invalid);
    expect_same(dashd_read_date_format_input() instanceof WP_Error, true, 'Invalid input rejected');
}
echo "OK: {$checks} date format checks passed using WordPress core.\n";
