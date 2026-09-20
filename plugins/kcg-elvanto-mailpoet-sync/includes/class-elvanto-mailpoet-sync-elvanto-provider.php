<?php

namespace KCG\ElvantoMailPoetSync;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Elvanto people provider wrapper.
 *
 * Uses the shared KCG Elvanto API client to fetch people and filters out inactive/contact/archived/deceased records.
 */
class ElvantoProvider {

    public static function is_available() {
        if (!class_exists('KCG_Elvanto_API_Registry') || !method_exists('KCG_Elvanto_API_Registry', 'has_api_key')) {
            return false;
        }

        return (bool) \KCG_Elvanto_API_Registry::has_api_key();
    }

    public static function fetch_active_people() {
        if (!self::is_available()) {
            return new \WP_Error('elvanto_unavailable', 'Elvanto API Provider is not available or not configured.');
        }

        if (!class_exists('KCG_Elvanto_Cache') || !method_exists('KCG_Elvanto_Cache', 'get_people')) {
            return new \WP_Error('elvanto_cache_unavailable', 'Elvanto cache provider is not available.');
        }

        // All upstream Elvanto calls live in the provider layer. This plugin only reads the
        // provider's raw cached people payload and applies mailpoet-specific filtering.
        $people = \KCG_Elvanto_Cache::get_people();
        if (is_wp_error($people)) {
            return $people;
        }

        if (!is_array($people)) {
            return array();
        }

        return array_values(array_filter($people, function ($person) {
            $email = isset($person['email']) ? trim($person['email']) : '';
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return false;
            }

            if (isset($person['status']) && strcasecmp(trim($person['status']), 'Active') !== 0) {
                return false;
            }

            if (isset($person['contact']) && (int) $person['contact'] !== 0) {
                return false;
            }

            if (isset($person['archived']) && (int) $person['archived'] !== 0) {
                return false;
            }

            if (isset($person['deceased']) && (int) $person['deceased'] !== 0) {
                return false;
            }

            return true;
        }));
    }
}
