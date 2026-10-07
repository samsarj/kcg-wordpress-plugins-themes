<?php
/**
 * Tracks recognized email-editor contexts and allows send integrations to opt in
 * to email-safe, fully inline-styled shortcode markup through a filter.
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

        // MailPoet editor integration; sending workers should use the explicit filter below.
        // MailPoet 3 exposes the MailPoet\API\API class; use that to avoid hard dependency.
        if (class_exists('MailPoet\\API\\API') || defined('MAILPOET_VERSION')) {
            add_action('admin_init', array(__CLASS__, 'maybe_start_mailpoet_admin'));
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

    public static function is_email()
    {
        // Send workers can opt in only around actual newsletter rendering.
        return (bool) apply_filters('kcg_elvanto_is_email', self::$active);
    }
}
