<?php
/**
 * YOOtheme Pro Integration Loader
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('dashd_try_load_yootheme_module')) {
    function dashd_try_load_yootheme_module() {
        static $loaded = false;
        if ($loaded) {
            return;
        }

        if (!class_exists('YOOtheme\Application')) {
            return;
        }

        $module = __DIR__ . '/yootheme-module.php';
        if (!is_readable($module)) {
            return;
        }

        try {
            $app = \YOOtheme\Application::getInstance();
            if (!is_object($app) || !method_exists($app, 'load')) {
                return;
            }

            $app->load($module);
            $loaded = true;
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[DashD] YOOtheme module load failed: ' . $e->getMessage());
            }
        }
    }
}

// Primary hook for theme integrations.
add_action('after_setup_theme', 'dashd_try_load_yootheme_module', 100);
// Fallback hook in case YOOtheme loads later than expected.
add_action('init', 'dashd_try_load_yootheme_module', 20);
