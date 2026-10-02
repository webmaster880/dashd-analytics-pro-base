<?php
/** Shared date presentation for widgets and the settings preview. */
if (!defined('ABSPATH')) exit;

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

function dashd_format_update_date($mysql_date, $format = null, $locale = null) {
    // Sync times are stored as local WordPress time, not UTC.
    $timezone = wp_timezone();
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', (string) $mysql_date, $timezone);
    if (!$date || $date->format('Y-m-d H:i:s') !== (string) $mysql_date) {
        return '--';
    }
    $switched = $locale && $locale !== get_locale() ? switch_to_locale($locale) : false;
    try {
        return (string) wp_date(dashd_last_update_format($format), $date->getTimestamp(), $timezone);
    } finally {
        if ($switched) {
            restore_previous_locale();
        }
    }
}
