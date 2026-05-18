<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$pluginFile = $root . '/dashd-analytics-pro.php';

if (!is_readable($pluginFile)) {
    fwrite(STDERR, "Plugin file not found: {$pluginFile}\n");
    exit(1);
}

$level = $argv[1] ?? 'patch';
$level = strtolower(trim((string) $level));

if (!in_array($level, ['patch', 'minor', 'major'], true)) {
    fwrite(STDERR, "Usage: php bin/bump-version.php [patch|minor|major]\n");
    exit(1);
}

$content = file_get_contents($pluginFile);
if ($content === false) {
    fwrite(STDERR, "Unable to read plugin file.\n");
    exit(1);
}

if (!preg_match('/^\s*\*\s*Version:\s*([0-9]+)\.([0-9]+)\.([0-9]+)/m', $content, $matches)) {
    fwrite(STDERR, "Unable to locate plugin header version.\n");
    exit(1);
}

$major = (int) $matches[1];
$minor = (int) $matches[2];
$patch = (int) $matches[3];

switch ($level) {
    case 'major':
        $major++;
        $minor = 0;
        $patch = 0;
        break;
    case 'minor':
        $minor++;
        $patch = 0;
        break;
    case 'patch':
    default:
        $patch++;
        break;
}

$newVersion = sprintf('%d.%d.%d', $major, $minor, $patch);

$content = preg_replace(
    '/^(\s*\*\s*Version:\s*)([0-9]+\.[0-9]+\.[0-9]+)/m',
    '${1}' . $newVersion,
    $content,
    1,
    $headerReplacements
);

$content = preg_replace(
    "/define\\('DASHD_VERSION',\\s*'([0-9]+\\.[0-9]+\\.[0-9]+)'\\);/",
    "define('DASHD_VERSION', '" . $newVersion . "');",
    $content,
    1,
    $constReplacements
);

if (!is_string($content)) {
    fwrite(STDERR, "Failed to update version strings.\n");
    exit(1);
}

if ($headerReplacements !== 1) {
    fwrite(STDERR, "Failed to update plugin header version.\n");
    exit(1);
}

if ($constReplacements !== 1) {
    fwrite(STDERR, "Failed to update DASHD_VERSION constant.\n");
    exit(1);
}

$result = file_put_contents($pluginFile, $content);
if ($result === false) {
    fwrite(STDERR, "Unable to write plugin file.\n");
    exit(1);
}

fwrite(STDOUT, "Version updated to {$newVersion}.\n");
