<?php

namespace KCG\ElvantoMailPoetSync;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Wrapper for the MailPoet API.
 *
 * Provides subscriber lookup, creation, and update logic in a simplified email-first flow.
 */
class MailPoetAdapter {

    public static function is_available() {
        return self::get_api() !== null;
    }

    public static function find_by_email($email) {
        if (empty($email)) {
            return null;
        }

        $api = self::get_api();
        if (!$api) {
            return null;
        }

        try {
            return $api->getSubscriber($email);
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function create_subscriber(array $data) {
        $payload = self::build_payload($data, true);
        $api = self::get_api();

        if (!$api || !method_exists($api, 'addSubscriber')) {
            return new \WP_Error('mailpoet_create_failed', 'Unable to create MailPoet subscriber because no compatible MailPoet API was detected.');
        }

        try {
            $subscriber = $api->addSubscriber($payload);
        } catch (\Exception $e) {
            return new \WP_Error('mailpoet_create_failed', $e->getMessage());
        }

        return $subscriber;
    }

    public static function update_subscriber($subscriber, array $data) {
        $subscriber_id = self::get_subscriber_id($subscriber);
        if (empty($subscriber_id)) {
            return new \WP_Error('mailpoet_update_failed', 'Subscriber ID could not be determined.');
        }

        $payload = self::build_payload($data, false);
        $api = self::get_api();

        if ($api && method_exists($api, 'updateSubscriber')) {
            return $api->updateSubscriber($subscriber_id, $payload);
        }

        if ($api && method_exists($api, 'addSubscriber') && isset($payload['email'])) {
            return $api->addSubscriber($payload);
        }

        return new \WP_Error('mailpoet_update_failed', 'Unable to update MailPoet subscriber because no compatible MailPoet API was detected.');
    }

    public static function get_subscriber_id($subscriber) {
        if (is_object($subscriber)) {
            if (isset($subscriber->id)) {
                return $subscriber->id;
            }

            if (method_exists($subscriber, 'getId')) {
                return $subscriber->getId();
            }
        }

        if (is_array($subscriber) && isset($subscriber['id'])) {
            return $subscriber['id'];
        }

        return null;
    }

    public static function get_subscriber_status($subscriber) {
        if (is_object($subscriber)) {
            if (isset($subscriber->status)) {
                return $subscriber->status;
            }

            if (method_exists($subscriber, 'getStatus')) {
                return $subscriber->getStatus();
            }
        }

        if (is_array($subscriber) && isset($subscriber['status'])) {
            return $subscriber['status'];
        }

        return null;
    }

    private static function build_payload(array $data, bool $is_new) {
        $payload = array(
            'email' => $data['email'] ?? '',
            'first_name' => $data['first_name'] ?? '',
            'last_name' => $data['last_name'] ?? '',
        );

        if ($is_new && isset($data['status'])) {
            $payload['status'] = $data['status'];
        }

        return $payload;
    }

    private static function get_api() {
        if (!class_exists('MailPoet\\API\\API')) {
            return null;
        }

        $api_class = 'MailPoet\\API\\API';
        if (!method_exists($api_class, 'MP')) {
            return null;
        }

        return call_user_func(array($api_class, 'MP'), 'v1');
    }
}
