<?php
/**
 * Shared render helpers for DashD YOOtheme element templates.
 */

if (!defined('ABSPATH') && PHP_SAPI !== 'cli') {
    return;
}

if (!function_exists('dashd_yootheme_escape_attr')) {
    /**
     * Escape attribute value both in WP and CLI contexts.
     */
    function dashd_yootheme_escape_attr($value) {
        $value = is_scalar($value) ? (string) $value : '';
        if (function_exists('esc_attr')) {
            return esc_attr($value);
        }

        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('dashd_yootheme_sanitize_key')) {
    /**
     * Normalize source key safely with WP fallback for CLI tests.
     */
    function dashd_yootheme_sanitize_key($value, $fallback = 'table1') {
        $value = is_scalar($value) ? (string) $value : '';

        if (function_exists('dashd_normalize_source_key')) {
            $normalized = (string) dashd_normalize_source_key($value, $fallback);
            return $normalized !== '' ? $normalized : $fallback;
        }

        if (function_exists('sanitize_key')) {
            $normalized = (string) sanitize_key($value);
            return $normalized !== '' ? $normalized : $fallback;
        }

        $normalized = strtolower($value);
        $normalized = preg_replace('/[^a-z0-9_\-]/', '', $normalized);
        if (!is_string($normalized) || $normalized === '') {
            return $fallback;
        }

        return $normalized;
    }
}

if (!function_exists('dashd_yootheme_normalize_palette')) {
    /**
     * Keep only valid hex colors (3/4/6/8) and normalize with # prefix.
     */
    function dashd_yootheme_normalize_palette($raw_palette) {
        $raw_palette = is_scalar($raw_palette) ? (string) $raw_palette : '';
        $parts = preg_split('/\s*,\s*/', trim($raw_palette), -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($parts)) {
            return '';
        }

        $colors = [];
        foreach ($parts as $item) {
            $item = trim((string) $item);
            if ($item === '') {
                continue;
            }

            if (preg_match('/^#?(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $item) !== 1) {
                continue;
            }

            if ($item[0] !== '#') {
                $item = '#' . $item;
            }

            $colors[] = strtoupper($item);
            if (count($colors) >= 12) {
                break;
            }
        }

        return implode(', ', $colors);
    }
}

if (!function_exists('dashd_yootheme_normalize_mode')) {
    function dashd_yootheme_normalize_mode($value) {
        $value = strtolower(trim((string) $value));
        return in_array($value, ['bar', 'line', 'donut'], true) ? $value : 'bar';
    }
}

if (!function_exists('dashd_yootheme_normalize_scale')) {
    function dashd_yootheme_normalize_scale($value) {
        $value = strtolower(trim((string) $value));
        return in_array($value, ['linear', 'logarithmic'], true) ? $value : 'linear';
    }
}

if (!function_exists('dashd_yootheme_normalize_bar_orientation')) {
    function dashd_yootheme_normalize_bar_orientation($value) {
        $value = strtolower(trim((string) $value));
        return in_array($value, ['horizontal', 'vertical'], true) ? $value : 'horizontal';
    }
}

if (!function_exists('dashd_yootheme_normalize_gated')) {
    function dashd_yootheme_normalize_gated($value) {
        return !empty($value) ? 'true' : 'false';
    }
}

if (!function_exists('dashd_yootheme_normalize_toggle')) {
    function dashd_yootheme_normalize_toggle($value, $default = true) {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $raw = strtolower(trim((string) $value));
        if ($raw === '') {
            return $default ? 'true' : 'false';
        }
        if (in_array($raw, ['1', 'true', 'yes', 'on'], true)) {
            return 'true';
        }
        if (in_array($raw, ['0', 'false', 'no', 'off'], true)) {
            return 'false';
        }

        return !empty($value) ? 'true' : ($default ? 'true' : 'false');
    }
}

if (!function_exists('dashd_yootheme_extract_custom_palette')) {
    /**
     * Build custom palette from 5 color picker fields.
     * Falls back to legacy comma-separated colors_custom field.
     *
     * @param array<string,mixed> $props
     */
    function dashd_yootheme_extract_custom_palette(array $props) {
        $picker_colors = [];

        for ($i = 1; $i <= 5; $i++) {
            $raw = $props['custom_color_' . $i] ?? '';
            $normalized = dashd_yootheme_normalize_palette($raw);
            if ($normalized === '') {
                continue;
            }

            // color field should hold one color, keep the first token defensively.
            $first = explode(',', $normalized)[0] ?? '';
            $first = trim((string) $first);
            if ($first !== '') {
                $picker_colors[] = $first;
            }
        }

        if (!empty($picker_colors)) {
            return implode(', ', $picker_colors);
        }

        // Backward compatibility with previous text input format.
        return dashd_yootheme_normalize_palette($props['colors_custom'] ?? '');
    }
}

if (!function_exists('dashd_yootheme_normalize_country_order')) {
    /**
     * Normalize comma/newline separated country names.
     */
    function dashd_yootheme_normalize_country_order($value) {
        $value = is_scalar($value) ? (string) $value : '';
        $parts = preg_split('/[,\n;]+/', $value, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($parts)) {
            return '';
        }

        $items = [];
        foreach ($parts as $part) {
            $name = trim(strip_tags((string) $part));
            if ($name === '') {
                continue;
            }
            $items[$name] = $name;
            if (count($items) >= 100) {
                break;
            }
        }

        return implode(', ', array_values($items));
    }
}

if (!function_exists('dashd_yootheme_normalize_period_bound')) {
    /**
     * Normalize period bound to YYYY-QN or return an empty string.
     */
    function dashd_yootheme_normalize_period_bound($value) {
        $value = strtoupper(trim((string) (is_scalar($value) ? $value : '')));
        if ($value === '') {
            return '';
        }

        if (preg_match('/^(\d{4})[-_\s]?(Q[1-4])$/', $value, $matches) === 1) {
            return sprintf('%d-%s', (int) $matches[1], (string) $matches[2]);
        }

        if (preg_match('/^(Q[1-4])[-_\s]?(\d{4})$/', $value, $matches) === 1) {
            return sprintf('%d-%s', (int) $matches[2], (string) $matches[1]);
        }

        return '';
    }
}

if (!function_exists('dashd_yootheme_normalize_widget_props')) {
    /**
     * Return strictly validated widget props used for shortcode rendering.
     *
     * @param mixed $props
     * @return array<string,string>
     */
    function dashd_yootheme_normalize_widget_props($props) {
        $props = is_array($props) ? $props : [];

        $table = dashd_yootheme_sanitize_key($props['table'] ?? 'table1', 'table1');
        $indicator_tokens = [];
        $raw_indicators = $props['indicators'] ?? [];
        if (!is_array($raw_indicators)) {
            $raw_indicators = [$raw_indicators];
        }
        foreach ($raw_indicators as $raw_indicator) {
            $raw_indicator = is_scalar($raw_indicator) ? (string) $raw_indicator : '';
            foreach (preg_split('/\\s*,\\s*/', $raw_indicator, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
                $token = trim((string) $token);
                if ($token === '') {
                    continue;
                }
                if (preg_match('/^[a-z0-9_\\-]+:\\d+$/i', $token) !== 1 && preg_match('/^\\d+$/', $token) !== 1) {
                    continue;
                }
                $indicator_tokens[$token] = $token;
                if (count($indicator_tokens) >= 40) {
                    break 2;
                }
            }
        }
        $indicators = implode(',', array_values($indicator_tokens));
        $mode = dashd_yootheme_normalize_mode($props['mode'] ?? 'bar');
        $scale = dashd_yootheme_normalize_scale($props['scale'] ?? 'linear');
        $gated = dashd_yootheme_normalize_gated($props['gated'] ?? false);
        $show_view_toggle = dashd_yootheme_normalize_toggle($props['show_view_toggle'] ?? true, true);
        $show_scale_toggle = dashd_yootheme_normalize_toggle($props['show_scale_toggle'] ?? true, true);
        $show_periods = dashd_yootheme_normalize_toggle($props['show_periods'] ?? true, true);
        $show_data_warnings = dashd_yootheme_normalize_toggle($props['show_data_warnings'] ?? true, true);
        $bar_orientation = dashd_yootheme_normalize_bar_orientation($props['bar_orientation'] ?? 'horizontal');
        $bar_stacked = dashd_yootheme_normalize_toggle($props['bar_stacked'] ?? true, true);
        $period_start = dashd_yootheme_normalize_period_bound($props['period_start'] ?? '');
        $period_end = dashd_yootheme_normalize_period_bound($props['period_end'] ?? '');

        $preset = dashd_yootheme_normalize_palette($props['colors'] ?? '#336DFF, #AF9BE2, #3B82F6, #BEE00F, #7FD3F7');
        $custom = dashd_yootheme_extract_custom_palette($props);
        $colors = $custom !== '' ? $custom : $preset;
        if ($colors === '') {
            $colors = '#336DFF, #AF9BE2, #3B82F6, #BEE00F, #7FD3F7';
        }
        $country_order = dashd_yootheme_normalize_country_order($props['country_order'] ?? '');

        return [
            'table' => $table,
            'indicators' => $indicators,
            'mode' => $mode,
            'scale' => $scale,
            'gated' => $gated,
            'show_view_toggle' => $show_view_toggle,
            'show_scale_toggle' => $show_scale_toggle,
            'show_periods' => $show_periods,
            'show_data_warnings' => $show_data_warnings,
            'bar_orientation' => $bar_orientation,
            'bar_stacked' => $bar_stacked,
            'period_start' => $period_start,
            'period_end' => $period_end,
            'country_order' => $country_order,
            'colors' => $colors,
        ];
    }
}

if (!function_exists('dashd_yootheme_build_shortcode')) {
    /**
     * Build strict shortcode string from normalized props.
     *
     * @param array<string,string> $normalized
     */
    function dashd_yootheme_build_shortcode(array $normalized) {
        return sprintf(
            '[dashd_widget table="%s" indicators="%s" mode="%s" scale="%s" gated="%s" show_view_toggle="%s" show_scale_toggle="%s" show_periods="%s" show_data_warnings="%s" bar_orientation="%s" bar_stacked="%s" period_start="%s" period_end="%s" country_order="%s" colors="%s"]',
            dashd_yootheme_escape_attr($normalized['table'] ?? 'table1'),
            dashd_yootheme_escape_attr($normalized['indicators'] ?? ''),
            dashd_yootheme_escape_attr($normalized['mode'] ?? 'bar'),
            dashd_yootheme_escape_attr($normalized['scale'] ?? 'linear'),
            dashd_yootheme_escape_attr($normalized['gated'] ?? 'false'),
            dashd_yootheme_escape_attr($normalized['show_view_toggle'] ?? 'true'),
            dashd_yootheme_escape_attr($normalized['show_scale_toggle'] ?? 'true'),
            dashd_yootheme_escape_attr($normalized['show_periods'] ?? 'true'),
            dashd_yootheme_escape_attr($normalized['show_data_warnings'] ?? 'true'),
            dashd_yootheme_escape_attr($normalized['bar_orientation'] ?? 'horizontal'),
            dashd_yootheme_escape_attr($normalized['bar_stacked'] ?? 'true'),
            dashd_yootheme_escape_attr($normalized['period_start'] ?? ''),
            dashd_yootheme_escape_attr($normalized['period_end'] ?? ''),
            dashd_yootheme_escape_attr($normalized['country_order'] ?? ''),
            dashd_yootheme_escape_attr($normalized['colors'] ?? '')
        );
    }
}

if (!function_exists('dashd_yootheme_render_widget_output')) {
    /**
     * Render widget output through shortcode.
     *
     * @param mixed $props
     */
    function dashd_yootheme_render_widget_output($props) {
        $normalized = dashd_yootheme_normalize_widget_props($props);
        $shortcode = dashd_yootheme_build_shortcode($normalized);

        if (function_exists('do_shortcode')) {
            return (string) do_shortcode($shortcode);
        }

        return $shortcode;
    }
}
