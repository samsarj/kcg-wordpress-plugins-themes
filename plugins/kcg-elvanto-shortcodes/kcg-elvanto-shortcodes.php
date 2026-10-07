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
require_once KCG_ELVANTO_SHORTCODES_PATH . 'includes/shared/class-kcg-elvanto-email-context.php';
require_once KCG_ELVANTO_SHORTCODES_PATH . 'includes/shared/class-kcg-elvanto-event-card.php';
KCG_Elvanto_Email_Context::init();
require_once KCG_ELVANTO_SHORTCODES_PATH . 'includes/shortcodes/event-swiper/class-kcg-event-swiper-shortcode.php';
require_once KCG_ELVANTO_SHORTCODES_PATH . 'includes/shortcodes/next-on/class-kcg-next-on-shortcode.php';
require_once KCG_ELVANTO_SHORTCODES_PATH . 'includes/shortcodes/next-on/class-kcg-next-on-card-shortcode.php';
require_once KCG_ELVANTO_SHORTCODES_PATH . 'includes/shortcodes/preaching-table/class-kcg-preaching-table-shortcode.php';
require_once KCG_ELVANTO_SHORTCODES_PATH . 'includes/admin/class-kcg-elvanto-shortcodes-admin.php';

class KCG_Elvanto_Shortcodes {

    public function __construct() {
        // After the provider (priority 10) so its classes exist.
        add_action('plugins_loaded', array($this, 'init'), 20);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_shortcode_assets'));
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

    public function enqueue_shortcode_assets() {
        global $wp_query;

        $content = array();
        if (isset($wp_query->posts) && is_array($wp_query->posts)) {
            $content = $wp_query->posts;
        }
        $queried_object = get_queried_object();
        if ($queried_object instanceof WP_Post) {
            $content[] = $queried_object;
        }

        $shortcodes = array();
        foreach ($content as $post) {
            if (!($post instanceof WP_Post)) {
                continue;
            }
            foreach (array('next-on', 'next-on-card', 'next-on-card-swiper', 'kcg_preaching_table') as $shortcode) {
                if (has_shortcode($post->post_content, $shortcode)) {
                    $shortcodes[$shortcode] = true;
                }
            }
        }

        if (isset($shortcodes['next-on'])) {
            $path = KCG_ELVANTO_SHORTCODES_PATH . 'includes/shortcodes/next-on/next-on.css';
            wp_enqueue_style(
                'kcg-next-on',
                plugins_url('includes/shortcodes/next-on/next-on.css', __FILE__),
                array(),
                filemtime($path)
            );
        }

        if (isset($shortcodes['next-on-card']) || isset($shortcodes['next-on-card-swiper'])) {
            KCG_Elvanto_Event_Card::enqueue_assets();
        }

        if (isset($shortcodes['next-on-card-swiper'])) {
            $swiper_path = KCG_ELVANTO_SHORTCODES_PATH . 'includes/shortcodes/event-swiper/';
            $swiper_url = plugins_url('includes/shortcodes/event-swiper/', __FILE__);
            wp_enqueue_style(
                'swiper-css',
                'https://cdn.jsdelivr.net/npm/swiper@12/swiper-bundle.min.css'
            );
            wp_enqueue_style(
                'elvanto-swiper-css',
                $swiper_url . 'elvanto-swiper.css',
                array(),
                filemtime($swiper_path . 'elvanto-swiper.css')
            );
            wp_enqueue_script(
                'swiper-js',
                'https://cdn.jsdelivr.net/npm/swiper@12/swiper-bundle.min.js',
                array(),
                null,
                true
            );
            wp_enqueue_script(
                'elvanto-swiper-js',
                $swiper_url . 'elvanto-swiper.js',
                array('swiper-js'),
                filemtime($swiper_path . 'elvanto-swiper.js'),
                true
            );
        }

        if (isset($shortcodes['kcg_preaching_table'])) {
            $table_path = KCG_ELVANTO_SHORTCODES_PATH . 'includes/shortcodes/preaching-table/';
            $table_url = plugins_url('includes/shortcodes/preaching-table/', __FILE__);
            wp_enqueue_style(
                'kcg-preaching-table',
                $table_url . 'preaching-table.css',
                array(),
                filemtime($table_path . 'preaching-table.css')
            );
            wp_enqueue_script(
                'kcg-preaching-table',
                $table_url . 'preaching-table.js',
                array(),
                filemtime($table_path . 'preaching-table.js'),
                true
            );
        }
    }

    public function missing_provider_notice() {
        echo '<div class="notice notice-error"><p><strong>KCG Elvanto Shortcodes</strong> requires an up-to-date <strong>KCG Elvanto API Provider</strong> plugin to be installed and activated.</p></div>';
    }
}

register_activation_hook(__FILE__, array('KCG_Elvanto_Shortcodes', 'activate'));
new KCG_Elvanto_Shortcodes();
