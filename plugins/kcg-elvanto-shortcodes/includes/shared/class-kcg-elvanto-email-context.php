<?php
/**
 * Detects when shortcodes are being rendered inside a MailPoet email (block email editor),
 * so they can output email-safe, fully inline-styled markup instead of the web version.
 */

if (!defined('ABSPATH')) {
    exit;
}

class KCG_Elvanto_Email_Context
{
    private static $active = false;

    public static function init()
    {
        // WooCommerce email editor hooks (existing integration)
        add_action('woocommerce_email_editor_render_start', array(__CLASS__, 'start'));
        // Both fire once the content has been rendered, so they mark the end of the email render.
        add_filter('woocommerce_email_content_renderer_styles', array(__CLASS__, 'end'));
        add_filter('woocommerce_email_renderer_styles', array(__CLASS__, 'end'));

        // MailPoet integration: detect common MailPoet admin/editor contexts and cron sends.
        // MailPoet 3 exposes the MailPoet\API\API class; use that to avoid hard dependency.
        if (class_exists('MailPoet\\API\\API') || defined('MAILPOET_VERSION')) {
            // When editing or previewing in admin (MailPoet editor pages include "mailpoet" in the page query arg).
            add_action('admin_init', array(__CLASS__, 'maybe_start_mailpoet_admin'));
            // When MailPoet sends via cron/worker, mark email context during that request.
            add_action('init', array(__CLASS__, 'maybe_start_mailpoet_send'));
        }
    }

    public static function start()
    {
        self::$active = true;
    }

    public static function end($passthrough)
    {
        self::$active = false;
        return $passthrough;
    }

    /**
     * Heuristic: if on a MailPoet admin page (editor/preview) mark the context as email.
     */
    public static function maybe_start_mailpoet_admin()
    {
        if (!class_exists('MailPoet\\API\\API') && !defined('MAILPOET_VERSION')) {
            return;
        }

        if (isset($_GET['page']) && stripos((string) $_GET['page'], 'mailpoet') !== false) {
            self::start();
            return;
        }

        // Some MailPoet editors use a post-like interface; try to detect common post types.
        if (isset($_GET['post']) && is_numeric($_GET['post'])) {
            $post_id = (int) $_GET['post'];
            $pt = get_post_type($post_id);
            if ($pt && stripos($pt, 'mailpoet') !== false) {
                self::start();
                return;
            }
        }
    }

    /**
     * Heuristic: when MailPoet is sending (cron or similar) mark context as email so shortcodes render email-safe markup.
     */
    public static function maybe_start_mailpoet_send()
    {
        if (!class_exists('MailPoet\\API\\API') && !defined('MAILPOET_VERSION')) {
            return;
        }

        // If WordPress is running a cron job and MailPoet is present, assume this is an email render/send context.
        if (defined('DOING_CRON') && DOING_CRON) {
            self::start();
            return;
        }

        // If REST request from MailPoet editor or AJAX preview, try to detect common parameters.
        if (wp_doing_ajax() && isset($_REQUEST['action']) && stripos((string) $_REQUEST['action'], 'mailpoet') !== false) {
            self::start();
            return;
        }
    }

    public static function is_email()
    {
        return (bool) apply_filters('kcg_elvanto_is_email', self::$active);
    }
}
