<?php
/**
 * Plugin Name: KCG Service Views
 * Description: Shared Elvanto service views, including the preaching table and next-on service outputs.
 * Version: 1.0.1
 * Author: Sam Sarjudeen
 * Author URI: https://github.com/samsarj
 * Plugin URI: https://github.com/samsarj/kcg-service-views
 * GitHub Plugin URI: https://github.com/samsarj/kcg-service-views
 * Primary Branch: main
 * Text Domain: kcg-service-views
 * Requires Plugins: kcg-elvanto-api-provider
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('KCG_SERVICE_VIEWS_VERSION', '1.0.0');
define('KCG_SERVICE_VIEWS_PATH', plugin_dir_path(__FILE__));
define('KCG_SERVICE_VIEWS_URL', plugin_dir_url(__FILE__));

if (!function_exists('kcg_elvanto_parse_service_datetime')) {
    require_once KCG_SERVICE_VIEWS_PATH . 'includes/helpers.php';
}

// Include class files
if (!class_exists('KCG_Elvanto_Preaching_Display')) {
    require_once KCG_SERVICE_VIEWS_PATH . 'includes/class-kcg-elvanto-preaching-display.php';
}

if (!class_exists('KCG_Elvanto_Next_On_Display')) {
    require_once KCG_SERVICE_VIEWS_PATH . 'includes/class-kcg-elvanto-next-on-display.php';
}

if (!class_exists('KCG_Elvanto_Preaching_Admin')) {
    require_once KCG_SERVICE_VIEWS_PATH . 'includes/class-kcg-elvanto-preaching-admin.php';
}

// Initialize the plugin
class KCG_Service_Views {
    
    private static $instance = null;
    
    public function __construct() {
        self::$instance = $this;
        
        // Initialize after the provider plugin has loaded so the registry class is available.
        add_action('plugins_loaded', array($this, 'init'), 20);
    }

    /**
     * Register activation and deactivation hooks.
     */
    public static function bootstrap() {
        register_activation_hook(__FILE__, array('KCG_Service_Views', 'activate'));
        register_deactivation_hook(__FILE__, array('KCG_Service_Views', 'deactivate'));
    }
    
    public static function get_instance() {
        return self::$instance;
    }
    
    /**
     * Plugin activation
     */
    public static function activate() {
        // Ensure the provider is active before scheduling.
        if (!class_exists('KCG_Elvanto_API_Registry')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die('KCG Service Views requires the KCG Elvanto API Provider plugin to be installed and active.');
        }

    }
    
    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        // No local fetch cron is used; the provider owns refresh scheduling.
    }
    
    /**
     * Initialize the plugin
     */
    public function init() {
        // Register the shortcodes regardless of plugin load order so they stay available.
        // The render methods themselves will safely return empty output if the provider data is not ready.
        $display = new KCG_Elvanto_Preaching_Display();
        $display->register_service_shortcodes();

        $next_on = new KCG_Elvanto_Next_On_Display();
        $next_on->register_shortcodes();

        if (!$this->is_api_provider_active()) {
            add_action('admin_notices', array($this, 'missing_api_provider_notice'));
        }
        
        // Initialize admin interface
        if (is_admin()) {
            new KCG_Elvanto_Preaching_Admin();
        }
        
        // Enqueue styles and scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    /**
     * Check if the API Provider plugin is active
     */
    private function is_api_provider_active() {
        return class_exists('KCG_Elvanto_API_Registry');
    }
    
    /**
     * Display admin notice if API provider is missing
     */
    public function missing_api_provider_notice() {
        ?>
        <div class="notice notice-error is-dismissible">
            <p><strong>KCG Service Views</strong> requires the <strong>KCG Elvanto API Provider</strong> plugin to be installed and activated.</p>
        </div>
        <?php
    }
    
    /**
     * Enqueue plugin styles and scripts
     */
    public function enqueue_assets() {
        wp_enqueue_style(
            'kcg-service-views',
            KCG_SERVICE_VIEWS_URL . 'includes/assets/preaching-table.css',
            array(),
            filemtime(KCG_SERVICE_VIEWS_PATH . 'includes/assets/preaching-table.css')
        );

        wp_enqueue_style(
            'kcg-service-views-next-on',
            KCG_SERVICE_VIEWS_URL . 'includes/assets/next-on.css',
            array(),
            filemtime(KCG_SERVICE_VIEWS_PATH . 'includes/assets/next-on.css')
        );
        
        wp_enqueue_script(
            'kcg-service-views',
            KCG_SERVICE_VIEWS_URL . 'includes/assets/preaching-table.js',
            array(),
            filemtime(KCG_SERVICE_VIEWS_PATH . 'includes/assets/preaching-table.js'),
            true
        );
    }
}

// Register activation/deactivation handlers and instantiate the plugin
KCG_Service_Views::bootstrap();
new KCG_Service_Views();
