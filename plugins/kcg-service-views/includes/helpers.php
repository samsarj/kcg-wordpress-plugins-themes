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

/**
 * Get services by type programmatically
 * 
 * @param string $service_type Service type/title to filter by
 * @param int $limit Number of services to return
 * @return array Array of service data
 * 
 * Usage:
 *   $services = kcg_get_services_by_type('Sunday', 5);
 *   foreach ($services as $service) {
 *       echo $service['title'] . ' - ' . $service['formatted_date'];
 *   }
 */
if (!function_exists('kcg_elvanto_display_timezone')) {
    function kcg_elvanto_display_timezone() {
        $site_timezone = function_exists('wp_timezone') ? wp_timezone() : null;
        if ($site_timezone instanceof DateTimeZone && $site_timezone->getName() !== 'UTC') {
            return $site_timezone;
        }

        return new DateTimeZone('Europe/London');
    }
}

if (!function_exists('kcg_elvanto_parse_service_datetime')) {
    function kcg_elvanto_parse_service_datetime($raw_value) {
        $raw_value = trim((string) $raw_value);
        if ($raw_value === '') {
            return null;
        }

        $display_timezone = kcg_elvanto_display_timezone();
        $formats = array(
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y-m-d\TH:i:s',
            'Y-m-d\TH:i',
            'Y-m-d',
        );

        foreach ($formats as $format) {
            $utc_datetime = DateTime::createFromFormat($format, $raw_value, new DateTimeZone('UTC'));
            if ($utc_datetime instanceof DateTime) {
                $errors = DateTime::getLastErrors();
                if ($errors && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) {
                    continue;
                }
                return $utc_datetime->setTimezone($display_timezone);
            }
        }

        $timestamp = strtotime($raw_value);
        if (false === $timestamp) {
            return null;
        }

        $utc_datetime = new DateTime('@' . $timestamp, new DateTimeZone('UTC'));
        return $utc_datetime->setTimezone($display_timezone);
    }
}

if (!function_exists('kcg_get_services_by_type')) {
    function kcg_get_services_by_type($service_type, $limit = 10) {
        if (!class_exists('KCG_Elvanto_Cache')) {
            return array();
        }

        $services = KCG_Elvanto_Cache::get_services();
        $filtered_services = array();
        $requested_service_type = (string) $service_type;

        foreach ($services as $service) {
            if (!is_array($service)) {
                continue;
            }

            $event_service_type = (string) ($service['service_type']['name'] ?? $service['service_type'] ?? '');
            $service_name = (string) ($service['name'] ?? '');

            if ($requested_service_type === '') {
                $filtered_services[] = $service;
                continue;
            }

            if ($event_service_type === $requested_service_type || $service_name === $requested_service_type) {
                $filtered_services[] = $service;
            }
        }

        usort($filtered_services, function($a, $b) {
            $date_a = kcg_elvanto_parse_service_datetime($a['date'] ?? $a['start_date'] ?? '1970-01-01');
            $date_b = kcg_elvanto_parse_service_datetime($b['date'] ?? $b['start_date'] ?? '1970-01-01');
            $ts_a = $date_a ? $date_a->getTimestamp() : 0;
            $ts_b = $date_b ? $date_b->getTimestamp() : 0;
            return $ts_a <=> $ts_b;
        });

        return $limit > 0 ? array_slice($filtered_services, 0, $limit) : $filtered_services;
    }
}

/**
 * Get the next upcoming service matching a type
 * 
 * @param string $service_type Service type/title to filter by
 * @return array|false Service data or false if no services found
 * 
 * Usage:
 *   $next_service = kcg_get_next_service_by_type('Sunday');
 *   if ($next_service) {
 *       echo 'Next Service: ' . $next_service['title'];
 *   }
 */
if (!function_exists('kcg_get_next_service_by_type')) {
    function kcg_get_next_service_by_type($service_type) {
        if (!class_exists('KCG_Elvanto_Cache')) {
            return false;
        }

        $services = KCG_Elvanto_Cache::get_services();
        if (empty($services)) {
            return false;
        }

        $requested_service_type = (string) $service_type;
        if ('' === $requested_service_type) {
            return false;
        }

        $matches = array();
        $now = current_time('timestamp');

        foreach ($services as $service) {
            if (!is_array($service)) {
                continue;
            }

            $date = isset($service['date']) ? (string) $service['date'] : '';
            $service_datetime = $date !== '' ? kcg_elvanto_parse_service_datetime($date) : null;
            $service_timestamp = $service_datetime ? $service_datetime->getTimestamp() : false;
            if ('' === $date || false === $service_timestamp || $service_timestamp < $now) {
                continue;
            }

            $service_type_name = (string) ($service['service_type']['name'] ?? '');
            if ($service_type_name === $requested_service_type) {
                $matches[] = $service;
            }
        }

        if (empty($matches)) {
            return false;
        }

        usort($matches, function($a, $b) {
            $time_a = kcg_elvanto_parse_service_datetime($a['date'] ?? '');
            $time_b = kcg_elvanto_parse_service_datetime($b['date'] ?? '');
            $ts_a = $time_a ? $time_a->getTimestamp() : 0;
            $ts_b = $time_b ? $time_b->getTimestamp() : 0;
            return $ts_a <=> $ts_b;
        });

        return $matches[0];
    }
}

