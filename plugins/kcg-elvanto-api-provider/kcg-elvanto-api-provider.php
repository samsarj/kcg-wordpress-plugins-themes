<?php
/**
 * Plugin Name: KCG Elvanto API Provider
 * Description: Provides API key storage for Elvanto integration.
 * Version: 1.1.0
 * Author: Sam Sarjudeen
 * Author URI: https://github.com/samsarj
 * Plugin URI: https://github.com/samsarj/kcg-elvanto-api-provider
 * GitHub Plugin URI: https://github.com/samsarj/kcg-elvanto-api-provider
 * Primary Branch: main
 * Text Domain: kcg-elvanto-api-provider
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registry for KCG Elvanto API
 */
class KCG_Elvanto_API_Registry {
    
    public static function get_api_key() {
        return get_option('kcg_elvanto_api_key');
    }
    
    public static function set_api_key($key) {
        return update_option('kcg_elvanto_api_key', sanitize_text_field($key));
    }
    
    public static function has_api_key() {
        return !empty(self::get_api_key());
    }

    public static function is_available() {
        return class_exists('KCG_Elvanto_API_Registry') && self::has_api_key();
    }
}

// Load shared API client
require_once(plugin_dir_path(__FILE__) . 'includes/class-kcg-elvanto-api-client.php');
require_once(plugin_dir_path(__FILE__) . 'includes/class-kcg-elvanto-cache.php');

// Load admin functionality
require_once(plugin_dir_path(__FILE__) . 'includes/class-kcg-elvanto-api-admin.php');

// Register activation and deactivation hooks
register_activation_hook(__FILE__, function() {
    KCG_Elvanto_Cache::activate();
});

register_deactivation_hook(__FILE__, function() {
    KCG_Elvanto_Cache::deactivate();
});

// Ensure the shared cache scheduler is active across the site.
add_action('plugins_loaded', function() {
    if ( class_exists('KCG_Elvanto_Cache') ) {
        KCG_Elvanto_Cache::init();
    }

    if (is_admin()) {
        new KCG_Elvanto_API_Admin();
    }
});
