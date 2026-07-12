<?php

namespace KCG\ElvantoMailPoetSync;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin UI for the MailPoet sync plugin.
 *
 * Adds a submenu item under the KCG Elvanto parent menu and renders manual sync status.
 */
class Admin {

    private const OPTION_KEY = 'kcg_elvanto_mailpoet_sync_status';

    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_post_kcg_elvanto_mailpoet_sync_now', array($this, 'handle_manual_sync'));
    }

    public function add_settings_page() {
        add_submenu_page(
            'kcg-elvanto-api',
            'Elvanto MailPoet Sync',
            'MailPoet Sync',
            'manage_options',
            'kcg-elvanto-mailpoet-sync',
            array($this, 'render_settings_page')
        );
    }

    public function handle_manual_sync() {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions to run the sync.');
        }

        check_admin_referer('kcg_elvanto_mailpoet_sync_run');

        $result = Syncer::run(array('trigger' => 'manual'));
        $redirect_url = add_query_arg(array(
            'page' => 'kcg-elvanto-mailpoet-sync',
            'sync' => 'completed',
        ), admin_url('admin.php'));

        wp_safe_redirect($redirect_url);
        exit;
    }

    public function render_settings_page() {
        $status = get_option(self::OPTION_KEY, array());
        $connection_status = $this->get_connection_status();

        ?>
        <div class="wrap">
            <h1>Elvanto MailPoet Sync</h1>

            <div style="max-width: 900px;">
                <div style="margin-bottom: 24px; padding: 18px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
                    <h2 style="margin-top: 0;">Connection status</h2>
                    <p>
                        <strong>Elvanto API Provider:</strong>
                        <?php echo $this->format_status($connection_status['elvanto'], 'Active', 'Inactive'); ?>
                    </p>
                    <p>
                        <strong>MailPoet:</strong>
                        <?php echo $this->format_status($connection_status['mailpoet'], 'Available', 'Unavailable'); ?>
                    </p>
                </div>

                <div style="margin-bottom: 24px; padding: 18px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
                    <h2 style="margin-top: 0;">Last sync</h2>
                    <table class="widefat" style="max-width: 600px;">
                        <tbody>
                            <tr>
                                <th style="width: 260px;">Last run</th>
                                <td><?php echo esc_html($status['last_run'] ?? 'Never'); ?></td>
                            </tr>
                            <tr>
                                <th>Processed</th>
                                <td><?php echo esc_html($status['processed'] ?? 0); ?></td>
                            </tr>
                            <tr>
                                <th>Created</th>
                                <td><?php echo esc_html($status['created'] ?? 0); ?></td>
                            </tr>
                            <tr>
                                <th>Updated</th>
                                <td><?php echo esc_html($status['updated'] ?? 0); ?></td>
                            </tr>
                            <tr>
                                <th>Skipped</th>
                                <td><?php echo esc_html($status['skipped'] ?? 0); ?></td>
                            </tr>
                            <tr>
                                <th>Errors</th>
                                <td><?php echo esc_html(count($status['errors'] ?? array())); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div style="margin-bottom: 24px; padding: 18px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
                    <h2 style="margin-top: 0;">Sync control</h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('kcg_elvanto_mailpoet_sync_run'); ?>
                        <input type="hidden" name="action" value="kcg_elvanto_mailpoet_sync_now">
                        <button type="submit" class="button button-primary">Run Sync Now</button>
                    </form>
                </div>

                <?php if (!empty($status['errors'])) : ?>
                    <div style="margin-bottom: 24px; padding: 18px; background: #fff8f5; border: 1px solid #dc3545; border-radius: 4px;">
                        <h2 style="margin-top: 0;">Recent errors</h2>
                        <ul style="margin: 0; padding-left: 20px;">
                            <?php foreach ($status['errors'] as $error) : ?>
                                <li><?php echo esc_html($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div style="padding: 18px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; color: #555;">
                    <p><strong>Notes:</strong> This plugin synchronises active Elvanto people into MailPoet subscribers. It preserves MailPoet unsubscribe state and avoids creating WordPress users.</p>
                </div>
            </div>
        </div>
        <?php
    }

    private function get_connection_status() {
        return array(
            'elvanto' => Plugin::is_provider_available(),
            'mailpoet' => MailPoetAdapter::is_available(),
        );
    }

    private function format_status($is_active, $positive_label, $negative_label) {
        if ($is_active) {
            return sprintf('<span style="color: #007cba;"><strong>%s</strong></span>', esc_html($positive_label));
        }

        return sprintf('<span style="color: #a00;"><strong>%s</strong></span>', esc_html($negative_label));
    }
}
