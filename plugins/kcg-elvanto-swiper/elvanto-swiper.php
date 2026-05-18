<?php
/**
 * Plugin Name: KCG Elvanto Event Swiper
 * Description: A plugin to display events from Elvanto using a Swiper carousel.
 * Version: 2.7.0
 * Author: Sam Sarjudeen
 * Author URI: https://github.com/samsarj
 * Plugin URI: https://github.com/samsarj/kcg-elvanto-swiper
 * GitHub Plugin URI: https://github.com/samsarj/kcg-elvanto-swiper
 * Primary Branch: main
 * Text Domain: kcg-elvanto-swiper
 * Requires Plugins: kcg-elvanto-api-provider
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ELVANTO_SWIPER_VERSION', '2.7.0');
define('ELVANTO_SWIPER_PATH', plugin_dir_path(__FILE__));
define('ELVANTO_SWIPER_URL', plugin_dir_url(__FILE__));

// Include class files
require_once ELVANTO_SWIPER_PATH . 'includes/helpers.php';
require_once ELVANTO_SWIPER_PATH . 'includes/class-elvanto-swiper-api.php';
require_once ELVANTO_SWIPER_PATH . 'includes/class-elvanto-swiper-admin.php';
require_once ELVANTO_SWIPER_PATH . 'includes/class-elvanto-swiper-display.php';

// Initialize the plugin
class Elvanto_Swiper {
    
    private $admin;
    private $display;
    
    public function __construct() {
        // Wait for the API provider to be loaded
        add_action('plugins_loaded', array($this, 'init_display'));

        // Register the cron hook to fetch events
        add_action('elvanto_swiper_fetch_events_hook', array($this, 'run_fetch_events_cron'));
    }

    /**
     * Register activation and deactivation hooks.
     */
    public static function bootstrap() {
        register_activation_hook(__FILE__, array('Elvanto_Swiper', 'activate'));
        register_deactivation_hook(__FILE__, array('Elvanto_Swiper', 'deactivate'));
    }
    
    /**
     * Initialize display after API provider is loaded
     */
    public function init_display() {
        // Check if the API provider is available
        if (!class_exists('KCG_Elvanto_API_Registry')) {
            add_action('admin_notices', array($this, 'show_missing_provider_notice'));
            return;
        }
        
        // Initialize both admin and display
        $this->admin = new Elvanto_Swiper_Admin();
        $this->display = new Elvanto_Swiper_Display();
    }
    
    /**
     * Show notice if API provider is missing
     */
    public function show_missing_provider_notice() {
        ?>
        <div class="notice notice-error is-dismissible">
            <p><?php esc_html_e('Elvanto Swiper requires the KCG Elvanto API Provider plugin to be installed and activated.', 'elvanto-swiper'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Run the cron job to fetch events
     */
    public function run_fetch_events_cron() {
        // Check if API provider is available
        if (!class_exists('KCG_Elvanto_API_Registry')) {
            error_log('Elvanto Swiper: KCG_Elvanto_API_Registry not available for cron execution');
            return;
        }
        
        // Load the API class and fetch events
        require_once plugin_dir_path(__FILE__) . 'includes/class-elvanto-swiper-api.php';
        $api = new Elvanto_Swiper_API();
        $success = $api->fetch_events();
        
        // Update the last refresh timestamp and status
        update_option('elvanto_swiper_last_refresh', current_time('mysql'));
        update_option('elvanto_swiper_last_refresh_status', $success ? 'success' : 'failed');
        
        if ($success) {
            error_log('Elvanto Swiper: Cron job executed - events refreshed');
        } else {
            error_log('Elvanto Swiper: Cron job executed - fetch failed, previous cache preserved');
        }
    }
    
    /**
     * Plugin activation
     */
    public static function activate() {
        if (!class_exists('KCG_Elvanto_API_Registry')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die('KCG Elvanto Swiper requires the KCG Elvanto API Provider plugin to be installed and active.');
        }

        if (!wp_next_scheduled('elvanto_swiper_fetch_events_hook')) {
            wp_schedule_event(time(), 'hourly', 'elvanto_swiper_fetch_events_hook');
        }
    }
    
    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        $timestamp = wp_next_scheduled('elvanto_swiper_fetch_events_hook');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'elvanto_swiper_fetch_events_hook');
        }
    }
}

// Register activation/deactivation handlers and initialize the plugin
Elvanto_Swiper::bootstrap();
new Elvanto_Swiper();
