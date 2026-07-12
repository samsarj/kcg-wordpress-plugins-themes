<?php

namespace KCG\ElvantoMailPoetSync;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin bootstrap and scheduler for the Elvanto → MailPoet sync.
 *
 * This class handles activation hooks, cron scheduling, and admin initialization.
 */
class Plugin {

    public static function activate() {
        if (!self::is_provider_available()) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die('KCG Elvanto MailPoet Sync requires the KCG Elvanto API Provider plugin to be installed and activated.');
        }

        if (!wp_next_scheduled('kcg_elvanto_mailpoet_sync_cron')) {
            wp_schedule_event(time(), 'daily', 'kcg_elvanto_mailpoet_sync_cron');
        }
    }

    public static function deactivate() {
        $timestamp = wp_next_scheduled('kcg_elvanto_mailpoet_sync_cron');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'kcg_elvanto_mailpoet_sync_cron');
        }
    }

    public static function init() {
        new self();
    }

    public function __construct() {
        add_action('kcg_elvanto_mailpoet_sync_cron', array($this, 'run_scheduled_sync'));

        if (is_admin()) {
            new Admin();
        }
    }

    public function run_scheduled_sync() {
        Syncer::run(array('trigger' => 'cron'));
    }

    public static function is_provider_available() {
        return class_exists('KCG_Elvanto_API_Registry') && \KCG_Elvanto_API_Registry::has_api_key();
    }
}
