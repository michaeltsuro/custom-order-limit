<?php
/*
 * Plugin Name:       Custom Order Limit
 * Plugin URI:        https://www.michaeltsuro.com/plugins/
 * Description:       Elevate your online store with a WooCommerce plugin that limits user orders per category. This tool automatically disables checkout when a user exceeds the maximum order limit within a set timeframe.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      8.0
 * Author:            Takudzwa Michael Tsuro
 * Author URI:        https://www.michaeltsuro.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://www.michaeltsuro.com/plugins/
 * Text Domain:       custom-order-limit
 * Domain Path:       /languages
 *
 * WC requires at least: 7.8
 * WC tested up to:      8.6.1
 * @package BetaDev\CustomOrderLimit
 */

// Minimum PHP version check.
if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
    deactivate_plugins( plugin_basename( __FILE__ ) );
    wp_die( __('Custom Order Limit requires PHP 8.0 or higher.', 'custom-order-limit') );
}

// Constants for paths and URLs.
define( 'CUSTOM_ORDER_LIMIT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CUSTOM_ORDER_LIMIT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Autoloader.
spl_autoload_register(function ($class) {
    $namespaceMap = [
        'BetaDev\\CustomOrderLimit\\' => 'src/',
    ];

    foreach ($namespaceMap as $namespacePrefix => $directory) {
        if (0 === strncmp($namespacePrefix, $class, strlen($namespacePrefix))) {
            // Convert namespace separator to directory separator
            $classPath = str_replace('\\', DIRECTORY_SEPARATOR, $class);

            // Build file path using the namespace mapping
            $file = CUSTOM_ORDER_LIMIT_PLUGIN_DIR . $directory . $classPath . '.php';

            if (is_readable($file)) {
                include_once $file;
            }
        }
    }
});

// Initialize the plugin.
try {
    add_action('init', function () {
        // Minimum WooCommerce version check.
        if (!class_exists('WooCommerce') || version_compare(WC()->version, '7.8', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('Custom Order Limit requires WooCommerce 7.8 or higher.', 'custom-order-limit'));
        }

        $limiter = new \BetaDev\CustomOrderLimit\CustomOrderLimit();
        $admin   = new \BetaDev\CustomOrderLimit\Admin($limiter);

        // Initialize hooks.
        $admin->init();
    });
} catch (Exception $e) {
    error_log($e->getMessage());
}


