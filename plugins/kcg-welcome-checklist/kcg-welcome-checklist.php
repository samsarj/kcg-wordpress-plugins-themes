<?php
/**
 * Plugin Name: KCG Welcome Checklist
 * Description: Life group welcome checklist with autosave and weekly reset
 * Version: 2.0.0
 * Author: Sam Sarjudeen
 * Author URI: https://github.com/samsarj/
 * Plugin URI: https://github.com/samsarj/kcg-welcome-checklist
 * GitHub Plugin URI: https://github.com/samsarj/kcg-welcome-checklist
 * Primary Branch: main
 * Text Domain: kcg-welcome-checklist
 * License: GPL v2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'KCG_CHECKLIST_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'KCG_CHECKLIST_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include helper functions
require_once KCG_CHECKLIST_PLUGIN_DIR . 'includes/functions.php';
require_once KCG_CHECKLIST_PLUGIN_DIR . 'includes/post-type.php';
require_once KCG_CHECKLIST_PLUGIN_DIR . 'includes/admin-filters.php';
require_once KCG_CHECKLIST_PLUGIN_DIR . 'includes/elvanto.php';
require_once KCG_CHECKLIST_PLUGIN_DIR . 'includes/realtime.php';

// Register activation/deactivation hooks
register_activation_hook( __FILE__, 'kcg_checklist_activate' );
register_deactivation_hook( __FILE__, 'kcg_checklist_deactivate' );

// Note: Item initialization is handled in kcg_checklist_get_items() only when needed

// Add admin settings menu

// Register AJAX actions for logged-in users
add_action( 'wp_ajax_kcg_checklist_toggle', 'kcg_checklist_toggle_item' );
add_action( 'wp_ajax_kcg_checklist_get_status', 'kcg_checklist_get_status' );
add_action( 'wp_ajax_kcg_checklist_get_members', 'kcg_checklist_get_members' );
add_action( 'wp_ajax_kcg_checklist_update_volunteer', 'kcg_checklist_update_volunteer' );
add_action( 'wp_ajax_kcg_checklist_remove_volunteer', 'kcg_checklist_remove_volunteer' );
add_action( 'wp_ajax_kcg_checklist_refresh_cache', 'kcg_checklist_refresh_all_caches' );
add_action( 'wp_ajax_kcg_checklist_get_updates', 'kcg_checklist_get_updates' );

// Register AJAX actions for non-logged-in users (public access)
add_action( 'wp_ajax_nopriv_kcg_checklist_toggle', 'kcg_checklist_toggle_item' );
add_action( 'wp_ajax_nopriv_kcg_checklist_get_status', 'kcg_checklist_get_status' );
add_action( 'wp_ajax_nopriv_kcg_checklist_get_members', 'kcg_checklist_get_members' );
add_action( 'wp_ajax_nopriv_kcg_checklist_update_volunteer', 'kcg_checklist_update_volunteer' );
add_action( 'wp_ajax_nopriv_kcg_checklist_remove_volunteer', 'kcg_checklist_remove_volunteer' );
add_action( 'wp_ajax_nopriv_kcg_checklist_get_updates', 'kcg_checklist_get_updates' );

// Register weekly reset hook
add_action( 'kcg_checklist_weekly_reset', 'kcg_checklist_do_weekly_reset' );

// Add shortcodes for frontend access
add_shortcode( 'kcg_next_sunday', 'kcg_next_sunday_shortcode' );
add_shortcode( 'kcg_welcome_checklist', 'kcg_checklist_shortcode' );
add_shortcode( 'kcg_checklist_status', 'kcg_checklist_status_shortcode' );
add_action( 'wp_enqueue_scripts', 'kcg_checklist_enqueue_frontend_assets' );

/**
 * Shortcode for next Sunday service date
 */
function kcg_next_sunday_shortcode( $atts ) {
    ob_start();
    include KCG_CHECKLIST_PLUGIN_DIR . 'templates/next-sunday.php';
    return ob_get_clean();
}

/**
 * Shortcode for frontend checklist
 */
function kcg_checklist_shortcode( $atts ) {
    ob_start();
    include KCG_CHECKLIST_PLUGIN_DIR . 'templates/frontend-page.php';
    return ob_get_clean();
}

/**
 * Shortcode for checklist status/footer
 */
function kcg_checklist_status_shortcode( $atts ) {
    ob_start();
    include KCG_CHECKLIST_PLUGIN_DIR . 'templates/checklist-status.php';
    return ob_get_clean();
}

/**
 * Enqueue frontend assets
 */
function kcg_checklist_enqueue_frontend_assets() {
    
    wp_enqueue_style(
        'kcg-checklist-frontend-styles',
        KCG_CHECKLIST_PLUGIN_URL . 'assets/frontend-styles.css',
        [],
        '1.0.0'
    );
    
    wp_enqueue_script(
        'kcg-checklist-frontend-script',
        KCG_CHECKLIST_PLUGIN_URL . 'assets/frontend-script.js',
        ['jquery'],
        '1.0.0',
        true
    );
    
    wp_localize_script(
        'kcg-checklist-frontend-script',
        'kcgChecklist',
        [
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'  => wp_create_nonce( 'kcg_checklist_nonce' ),
        ]
    );
}
