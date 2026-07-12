<?php
/**
 * Plugin Name: KCG Elvanto MailPoet Sync
 * Description: Synchronises active Elvanto people into MailPoet subscribers using the KCG Elvanto API Provider.
 * Author: Sam Sarjudeen
 * Text Domain: kcg-elvanto-mailpoet-sync
 * Requires Plugins: kcg-elvanto-api-provider
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('KCG_ELVANTO_MAILPOET_SYNC_PATH')) {
    define('KCG_ELVANTO_MAILPOET_SYNC_PATH', plugin_dir_path(__FILE__));
}

if (!defined('KCG_ELVANTO_MAILPOET_SYNC_URL')) {
    define('KCG_ELVANTO_MAILPOET_SYNC_URL', plugin_dir_url(__FILE__));
}

if (!defined('KCG_ELVANTO_MAILPOET_SYNC_VERSION')) {
    define('KCG_ELVANTO_MAILPOET_SYNC_VERSION', '0.1.0');
}

require_once KCG_ELVANTO_MAILPOET_SYNC_PATH . 'includes/class-elvanto-mailpoet-sync.php';
require_once KCG_ELVANTO_MAILPOET_SYNC_PATH . 'includes/class-elvanto-mailpoet-sync-admin.php';
require_once KCG_ELVANTO_MAILPOET_SYNC_PATH . 'includes/class-elvanto-mailpoet-sync-syncer.php';
require_once KCG_ELVANTO_MAILPOET_SYNC_PATH . 'includes/class-elvanto-mailpoet-sync-mailpoet-adapter.php';
require_once KCG_ELVANTO_MAILPOET_SYNC_PATH . 'includes/class-elvanto-mailpoet-sync-elvanto-provider.php';

register_activation_hook(__FILE__, array('KCG\\ElvantoMailPoetSync\\Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('KCG\\ElvantoMailPoetSync\\Plugin', 'deactivate'));

add_action('plugins_loaded', array('KCG\\ElvantoMailPoetSync\\Plugin', 'init'), 5);
