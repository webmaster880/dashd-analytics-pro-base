<?php
require_once dirname(__DIR__) . '/render-helpers.php';

$props = $props ?? [];
echo dashd_yootheme_render_widget_output($props);
