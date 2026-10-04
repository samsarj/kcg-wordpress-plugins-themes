<?php
/**
 * Plugin Name: KCG Elvanto Shortcodes
 * Description: Display shortcodes (event swiper, next-on, preaching table) built on the Elvanto API Provider's merged_events data.
 * Version: 2.0.0
 * Author: Sam Sarjudeen
 * Author URI: https://github.com/samsarj
 * Text Domain: kcg-elvanto-shortcodes
 * Requires Plugins: kcg-elvanto-api-provider
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

define('KCG_ELVANTO_SHORTCODES_VERSION', '2.0.0');
define('KCG_ELVANTO_SHORTCODES_PATH', plugin_dir_path(__FILE__));

require_once KCG_ELVANTO_SHORTCODES_PATH . 'includes/class-kcg-elvanto-event-query.php';
require_once KCG_ELVANTO_SHORTCODES_PATH . 'includes/shared/class-kcg-elvanto-event-card.php';
require_once KCG_ELVANTO_SHORTCODES_PATH . 'includes/shortcodes/event-swiper/class-kcg-event-swiper-shortcode.php';
require_once KCG_ELVANTO_SHORTCODES_PATH . 'includes/shortcodes/next-on/class-kcg-next-on-shortcode.php';
require_once KCG_ELVANTO_SHORTCODES_PATH . 'includes/shortcodes/next-on/class-kcg-next-on-card-shortcode.php';
require_once KCG_ELVANTO_SHORTCODES_PATH . 'includes/shortcodes/preaching-table/class-kcg-preaching-table-shortcode.php';
require_once KCG_ELVANTO_SHORTCODES_PATH . 'includes/admin/class-kcg-elvanto-shortcodes-admin.php';

class KCG_Elvanto_Shortcodes {

    public function __construct() {
        // After the provider (priority 10) so its classes exist.
        add_action('plugins_loaded', array($this, 'init'), 20);
    }

    public static function activate() {
        if (!class_exists('KCG_Elvanto_API_Registry')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die('KCG Elvanto Shortcodes requires the KCG Elvanto API Provider plugin to be installed and active.');
        }
    }

    public function init() {
        if (!class_exists('KCG_Elvanto_Event_Merger')) {
            add_action('admin_notices', array($this, 'missing_provider_notice'));
            return;
        }

        (new KCG_Elvanto_Event_Swiper_Shortcode())->register();
        (new KCG_Elvanto_Next_On_Shortcode())->register();
        (new KCG_Elvanto_Next_On_Card_Shortcode())->register();
        (new KCG_Elvanto_Preaching_Table_Shortcode())->register();

        if (is_admin()) {
            new KCG_Elvanto_Shortcodes_Admin();
        }
    }

    public function missing_provider_notice() {
        echo '<div class="notice notice-error"><p><strong>KCG Elvanto Shortcodes</strong> requires an up-to-date <strong>KCG Elvanto API Provider</strong> plugin to be installed and activated.</p></div>';
    }
}

register_activation_hook(__FILE__, array('KCG_Elvanto_Shortcodes', 'activate'));
new KCG_Elvanto_Shortcodes();
