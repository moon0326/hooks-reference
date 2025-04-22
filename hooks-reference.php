<?php
/**
 * Hooks Reference
 * 
 * @package     Hooks Reference
 * @author      Moon
 * @copyright   Moon
 * @license     GPL-2.0-or-later
 * 
 * Plugin Name: Hooks Reference
 * Plugin URI: https://github.com/moon0326/wp-hooks-reference
 * Version: 1.0.1
 * Author: Moon K
 * Author URI: https://github.com/moon0326
 * Requires PHP: 7.1
 * Description: A WordPress admin plugin that scans all installed plugins to discover where WordPress hooks are used.
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: hooks-reference
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('HOOKS_REFERENCE_VERSION', '1.0.1');
define('HOOKS_REFERENCE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HOOKS_REFERENCE_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autoloader
require_once HOOKS_REFERENCE_PLUGIN_DIR . 'vendor/autoload.php';

// Initialize the plugin
add_action('plugins_loaded', function() {
    // Initialize the plugin
    if (class_exists('HooksReference\Plugin')) {
        $plugin = new HooksReference\Plugin();
        $plugin->init();
    }
});

// Include WP-CLI command
if (defined('WP_CLI') && WP_CLI) {
    require_once HOOKS_REFERENCE_PLUGIN_DIR . 'includes/class-hooks-ref-cli.php';
} 