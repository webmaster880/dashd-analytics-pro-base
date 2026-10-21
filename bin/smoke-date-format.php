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
require ABSPATH . WPINC . '/compat.php';
foreach (['compat-utf8.php', 'utf8.php'] as $utf8_file) {
    if (is_file(ABSPATH . WPINC . '/' . $utf8_file)) require ABSPATH . WPINC . '/' . $utf8_file;
}
require ABSPATH . WPINC . '/functions.php';
require ABSPATH . WPINC . '/formatting.php';
require ABSPATH . WPINC . '/kses.php';
require ABSPATH . WPINC . '/class-wp-error.php';
require ABSPATH . WPINC . '/class-wp-locale.php';
require ABSPATH . WPINC . '/shortcodes.php';

$options = ['timezone_string' => 'Europe/Kyiv', 'date_format' => 'Y-m-d', 'time_format' => 'H:i', 'blog_charset' => 'UTF-8'];
add_filter('pre_option', static function ($value, $name, $default) use (&$options) {
    return $options[$name] ?? $default;
}, 10, 3);

// Only translation loading is simulated; date formatting uses actual WordPress core.
$locale = 'en_US';
$previous_locale = 'en_US';
function __($text, $domain = 'default') {
    return $GLOBALS['locale'] === 'uk' && $text === 'October' ? 'Жовтень' : $text;
}
function _x($text, $context, $domain = 'default') {
    if ($GLOBALS['locale'] === 'uk') {
        if ($context === 'decline months names: on or off') return 'on';
        if ($context === 'genitive' && $text === 'October') return 'Жовтня';
    }
    return __($text, $domain);
}
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
expect_same(dashd_format_update_date($sync, 'j F Y', 'uk'), '1 Жовтня 2026', 'Localized genitive month');
expect_same(get_locale(), 'en_US', 'Locale restored');
expect_same(dashd_format_update_date($sync, 'j F Y', 'ka_GE'), '1 October 2026', 'Unavailable locale fallback');
$original_wp_locale = $wp_locale;
expect_same(dashd_format_update_date($sync, 'd F Y', 'en_US', 'lowercase'), '01 october 2026', 'Lowercase full month');
expect_same(dashd_format_update_date($sync, 'j M Y', 'en_US', 'lowercase'), '1 oct 2026', 'Lowercase short month');
expect_same(dashd_format_update_date($sync, '\\O\\c\\t\\o\\b\\e\\r j F Y', 'en_US', 'lowercase'), 'October 1 october 2026', 'Literal month text keeps its case');
expect_same(dashd_format_update_date($sync, '\\F j F Y', 'en_US', 'lowercase'), 'F 1 october 2026', 'Escaped format token');
expect_same($wp_locale === $original_wp_locale, true, 'Original locale object restored');
expect_same(dashd_format_update_date($sync, 'j F Y', 'uk', 'lowercase'), '1 жовтня 2026', 'Unicode lowercase and genitive month');
expect_same($wp_locale->month, $original_wp_locale->month, 'Month dictionary restored after locale switch');
$options['dashd_last_update_month_case'] = 'lowercase';
expect_same(dashd_format_update_date($sync, 'j F Y'), '1 october 2026', 'Global month case inherited');
expect_same(dashd_format_update_date($sync, 'j F Y', null, 'locale'), '1 October 2026', 'Widget overrides global month case');
expect_same(dashd_format_update_date($sync, 'j F Y', null, 'inherit'), '1 october 2026', 'Explicit inheritance');
$options['dashd_last_update_month_case'] = 'locale';
$_POST['month_case'] = 'lowercase';
expect_same(dashd_read_date_month_case_input(), 'lowercase', 'Valid month case input');
$_POST['month_case'] = ['lowercase'];
expect_same(dashd_read_date_month_case_input() instanceof WP_Error, true, 'Reject invalid month case input');
unset($_POST['month_case']);
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
require $root . '/includes/integrations/dashd_widget/render-helpers.php';
$escaped_format = 'Y-m-d\\TH:i \\[F\\] \\"';
$shortcode = dashd_yootheme_build_shortcode(dashd_yootheme_normalize_widget_props([
    'last_update_format' => $escaped_format,
    'last_update_month_case' => 'lowercase',
]));
preg_match('/^\[dashd_widget (.*)\]$/s', $shortcode, $match);
$attributes = shortcode_parse_atts($match[1]);
expect_same(html_entity_decode($attributes['last_update_format'], ENT_QUOTES | ENT_HTML5, 'UTF-8'), $escaped_format, 'Date format survives shortcode parsing');
expect_same($attributes['last_update_month_case'], 'lowercase', 'Month case survives shortcode parsing');
echo "OK: {$checks} date format checks passed using WordPress core.\n";
