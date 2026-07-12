<?php

namespace KCG\ElvantoMailPoetSync;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Core sync loop for loading Elvanto people and writing MailPoet subscribers.
 *
 * Tracks status counters and error details for display in the admin UI.
 */
class Syncer {

    private const OPTION_KEY = 'kcg_elvanto_mailpoet_sync_status';

    public static function run(array $context = array()) {
        $status = array(
            'last_run' => current_time('mysql'),
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => array(),
            'trigger' => $context['trigger'] ?? 'manual',
        );

        if (!ElvantoProvider::is_available()) {
            $status['errors'][] = 'Elvanto provider is not available or not configured.';
            self::save_status($status);
            return $status;
        }

        if (!MailPoetAdapter::is_available()) {
            $status['errors'][] = 'MailPoet is not available in this WordPress installation.';
            self::save_status($status);
            return $status;
        }

        $people = ElvantoProvider::fetch_active_people();

        if (is_wp_error($people)) {
            $status['errors'][] = $people->get_error_message();
            self::save_status($status);
            return $status;
        }

        if (!is_array($people)) {
            $status['errors'][] = 'Unexpected response from Elvanto provider.';
            self::save_status($status);
            return $status;
        }

        foreach ($people as $person) {
            $status['processed']++;

            $result = self::process_person($person);
            if (is_wp_error($result)) {
                $status['errors'][] = $result->get_error_message();
                continue;
            }

            if ($result === 'created') {
                $status['created']++;
            } elseif ($result === 'updated') {
                $status['updated']++;
            } elseif ($result === 'skipped') {
                $status['skipped']++;
            }
        }

        self::save_status($status);
        return $status;
    }

    private static function process_person(array $person) {
        $email = isset($person['email']) ? trim($person['email']) : '';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new \WP_Error('invalid_person', 'Skipped person with missing or invalid email address.');
        }

        $first_name = '';
        if (isset($person['firstname'])) {
            $first_name = trim($person['firstname']);
        } elseif (isset($person['preferred_name'])) {
            $first_name = trim($person['preferred_name']);
        } elseif (isset($person['first_name'])) {
            $first_name = trim($person['first_name']);
        }

        $last_name = '';
        if (isset($person['lastname'])) {
            $last_name = trim($person['lastname']);
        } elseif (isset($person['last_name'])) {
            $last_name = trim($person['last_name']);
        }

        $subscriber_data = array(
            'email' => $email,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'status' => 'subscribed',
        );

        $existing = MailPoetAdapter::find_by_email($subscriber_data['email']);

        if ($existing) {
            $current_status = MailPoetAdapter::get_subscriber_status($existing);
            if ($current_status !== null) {
                $subscriber_data['status'] = $current_status;
            }

            $updated = MailPoetAdapter::update_subscriber($existing, $subscriber_data);
            if (is_wp_error($updated)) {
                return $updated;
            }

            return 'updated';
        }

        $created = MailPoetAdapter::create_subscriber($subscriber_data);
        if (is_wp_error($created)) {
            return $created;
        }

        return 'created';
    }

    public static function get_last_status() {
        return get_option(self::OPTION_KEY, array());
    }

    private static function save_status(array $status) {
        update_option(self::OPTION_KEY, $status);
    }
}
