<?php
/**
 * Plugin Name: Wisdom Rain EPUB Reader (WRER Engine)
 * Plugin URI: https://wisdomrain.com
 * Description: A minimalist multilingual EPUB reader system built for Wisdom Rain Platform. Includes Reader management, Categories, and EPUB.js frontend renderer.
 * Version: 1.0.0
 * Author: Wisdom Rain Dev Team
 * Requires PHP: 8.1
 * Requires at least: 6.0
 * License: GPLv2 or later
 * Text Domain: wrer
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants.
define('WRER_VERSION', '1.0.0');
define('WRER_PATH', plugin_dir_path(__FILE__));
define('WRER_URL', plugin_dir_url(__FILE__));

// Autoload core classes.
spl_autoload_register(function ($class) {
    if (strpos($class, 'WRER_') === 0) {
        $file = WRER_PATH . 'includes/' . strtolower(str_replace('_', '-', $class)) . '.php';
        if (file_exists($file)) {
            include_once $file;
        }
    }
});

// Load translations.
add_action('plugins_loaded', function () {
    load_plugin_textdomain('wrer', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

// Initialize admin functionality.
add_action('plugins_loaded', function () {
    if (is_admin()) {
        new WRER_Admin();
        new WRER_Admin_Readers();
    }
});
