<?php
/**
 * Helper functions for KCG Elvanto Service Views.
 *
 * These helpers expose programmatic access to the provider-backed service data.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('kcg_elvanto_no_service_dates_message')) {
    function kcg_elvanto_no_service_dates_message($service_type) {
        $service_type = trim((string) $service_type);
        if ('' === $service_type) {
            return 'No dates are currently scheduled.';
        }

        return 'No ' . $service_type . ' dates are currently scheduled.';
    }
}

/**
 * Get services by type, sorted by date.
 *
 * @param string $service_type Service type name to filter by ('' for all)
 * @param int $limit Number of services to return (0 for all)
 * @return array
 */
if (!function_exists('kcg_get_services_by_type')) {
    function kcg_get_services_by_type($service_type, $limit = 10) {
        if (!class_exists('KCG_Elvanto_Cache')) {
            return array();
        }

        $requested_service_type = (string) $service_type;
        $filtered_services = array();

        foreach (KCG_Elvanto_Cache::get_services() as $service) {
            if (!is_array($service)) {
                continue;
            }

            $event_service_type = (string) ($service['service_type']['name'] ?? $service['service_type'] ?? '');
            $service_name = (string) ($service['name'] ?? '');

            if ($requested_service_type === '' || $event_service_type === $requested_service_type || $service_name === $requested_service_type) {
                $filtered_services[] = $service;
            }
        }

        usort($filtered_services, function($a, $b) {
            return (KCG_Elvanto_Datetime::timestamp($a['date'] ?? '') ?? 0) <=> (KCG_Elvanto_Datetime::timestamp($b['date'] ?? '') ?? 0);
        });

        return $limit > 0 ? array_slice($filtered_services, 0, $limit) : $filtered_services;
    }
}

/**
 * Get the next service matching a type. The provider only fetches future services,
 * so the earliest match is the next one.
 *
 * @param string $service_type Service type name to filter by
 * @return array|false Service data or false if none found
 */
if (!function_exists('kcg_get_next_service_by_type')) {
    function kcg_get_next_service_by_type($service_type) {
        if ('' === (string) $service_type || !class_exists('KCG_Elvanto_Cache')) {
            return false;
        }

        $matches = array_filter(
            KCG_Elvanto_Cache::get_services(),
            function($service) use ($service_type) {
                return is_array($service)
                    && (string) ($service['service_type']['name'] ?? '') === (string) $service_type
                    && null !== KCG_Elvanto_Datetime::timestamp($service['date'] ?? '');
            }
        );

        if (empty($matches)) {
            return false;
        }

        usort($matches, function($a, $b) {
            return KCG_Elvanto_Datetime::timestamp($a['date']) <=> KCG_Elvanto_Datetime::timestamp($b['date']);
        });

        return $matches[0];
    }
}
