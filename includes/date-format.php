<?php
/** Shared date presentation for widgets and the settings preview. */
if (!defined('ABSPATH')) exit;

function dashd_validate_date_format($format) {
    if (!is_string($format)) {
        return new WP_Error('invalid_format');
    }
    $format = trim($format);
    $trailing_escapes = strlen($format) - strlen(rtrim($format, '\\'));
    if (strlen($format) > 100 || preg_match('/[\x00-\x1F\x7F<>]/', $format) || $trailing_escapes % 2 !== 0) {
        return new WP_Error('invalid_format');
    }
    return $format;
}

function dashd_last_update_month_case($case = null) {
    if ($case === null || $case === '' || $case === 'inherit') {
        $case = get_option('dashd_last_update_month_case', 'locale');
    }
    return $case === 'lowercase' ? 'lowercase' : 'locale';
}

function dashd_last_update_format($format = null) {
    if ($format === null) {
        $format = get_option('dashd_last_update_format', 'd.m.Y H:i');
    }
    if ($format === '') {
        $format = trim(get_option('date_format', 'F j, Y') . ' ' . get_option('time_format', 'g:i a'));
    }
    return (string) $format;
}

function dashd_date_locale($lang) {
    $locale = get_locale();
    if (substr($locale, 0, 2) === $lang) {
        return $locale;
    }
    $locales = ['en' => 'en_US', 'uk' => 'uk', 'hy' => 'hy', 'ro' => 'ro_RO', 'ka' => 'ka_GE'];
    return $locales[$lang] ?? $locale;
}

function dashd_format_update_date($mysql_date, $format = null, $locale = null, $month_case = null) {
    global $wp_locale;
    // Sync times are stored as local WordPress time, not UTC.
    $timezone = wp_timezone();
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', (string) $mysql_date, $timezone);
    if (!$date || $date->format('Y-m-d H:i:s') !== (string) $mysql_date) {
        return '--';
    }
    $switched = $locale && $locale !== get_locale() ? switch_to_locale($locale) : false;
    $original_wp_locale = $wp_locale;
    try {
        if (dashd_last_update_month_case($month_case) === 'lowercase' && is_object($wp_locale)) {
            // Clone the month dictionaries so wp_date keeps grammatical forms and literal text.
            $wp_locale = clone $wp_locale;
            $lower = static function ($value) {
                return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
            };
            $wp_locale->month = array_map($lower, $wp_locale->month);
            $wp_locale->month_genitive = array_map($lower, $wp_locale->month_genitive);
            $abbreviations = [];
            foreach ($wp_locale->month_abbrev as $name => $abbreviation) {
                $abbreviations[$lower($name)] = $lower($abbreviation);
            }
            $wp_locale->month_abbrev = $abbreviations;
        }
        return (string) wp_date(dashd_last_update_format($format), $date->getTimestamp(), $timezone);
    } finally {
        $wp_locale = $original_wp_locale;
        if ($switched) {
            restore_previous_locale();
        }
    }
}
