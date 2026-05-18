<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$elementFile = $root . '/includes/integrations/dashd_widget/element.php';
$helperFile = $root . '/includes/integrations/dashd_widget/render-helpers.php';
$templateFile = $root . '/includes/integrations/dashd_widget/templates/template.php';
$contentFile = $root . '/includes/integrations/dashd_widget/templates/content.php';

function fail(string $message): void {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

foreach ([$elementFile, $helperFile, $templateFile, $contentFile] as $file) {
    if (!is_readable($file)) {
        fail("Required file is not readable: {$file}");
    }
}

$element = file_get_contents($elementFile);
if (!is_string($element) || $element === '') {
    fail('Unable to read element.php.');
}

if (strpos($element, "'title' => 'Advanced'") === false) {
    fail("Advanced tab is missing in element fieldset.");
}

if (strpos($element, "'fields' => ['name', 'status', 'source', 'id', 'class', 'attributes', 'css', 'transform']") === false) {
    fail('Advanced field order/content does not match expected baseline.');
}

if (strpos($element, "'label' => 'Name'") === false || strpos($element, "'label' => 'CSS'") === false) {
    fail('Expected Advanced field labels are missing.');
}

require_once $helperFile;

if (!function_exists('dashd_yootheme_normalize_widget_props') || !function_exists('dashd_yootheme_build_shortcode')) {
    fail('Render helper functions are not available.');
}

$normalized = dashd_yootheme_normalize_widget_props([
    'table' => '../Table 1<script>',
    'mode' => 'pie',
    'scale' => 'weird',
    'gated' => 1,
    'colors' => '#fff, garbage, 112233',
    'colors_custom' => ' #abc , red , #123456 ',
    'custom_color_1' => '#010203',
    'custom_color_2' => '#AABBCC',
]);

if (($normalized['mode'] ?? '') !== 'bar') {
    fail('Mode validation failed (expected fallback to bar).');
}
if (($normalized['scale'] ?? '') !== 'linear') {
    fail('Scale validation failed (expected fallback to linear).');
}
if (($normalized['gated'] ?? '') !== 'true') {
    fail('Gated normalization failed.');
}
if (($normalized['table'] ?? '') === '' || str_contains(($normalized['table'] ?? ''), '<')) {
    fail('Table key sanitation failed.');
}
if (($normalized['colors'] ?? '') !== '#010203, #AABBCC') {
    fail('Custom picker palette normalization failed.');
}

$shortcode = dashd_yootheme_build_shortcode($normalized);
if (!is_string($shortcode) || strpos($shortcode, '[dashd_widget ') !== 0) {
    fail('Shortcode build failed.');
}
if (strpos($shortcode, 'mode="bar"') === false || strpos($shortcode, 'scale="linear"') === false) {
    fail('Shortcode does not contain normalized mode/scale.');
}

$template = file_get_contents($templateFile);
$content = file_get_contents($contentFile);
if (!is_string($template) || !is_string($content)) {
    fail('Unable to read template/content files.');
}

if (strpos($template, 'dashd_yootheme_render_widget_output') === false || strpos($content, 'dashd_yootheme_render_widget_output') === false) {
    fail('template.php/content.php must use shared render helper.');
}

fwrite(STDOUT, "OK: YOOtheme integration smoke checks passed.\n");
