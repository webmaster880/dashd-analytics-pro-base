<?php
/**
 * Content template for DashD YOOtheme element.
 *
 * Keeps the same functional output path as render template through shared helper.
 */
require_once dirname(__DIR__) . '/render-helpers.php';

$props = $props ?? [];
echo dashd_yootheme_render_widget_output($props);
