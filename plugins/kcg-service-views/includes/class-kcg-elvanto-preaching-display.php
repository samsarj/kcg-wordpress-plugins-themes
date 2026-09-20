<?php
/**
 * Display functionality for KCG Elvanto Service Views.
 *
 * @package KCGElvantoServiceViews
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('kcg_elvanto_parse_service_datetime')) {
    require_once __DIR__ . '/helpers.php';
}

class KCG_Elvanto_Preaching_Display {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Register service-view shortcodes
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    public function register_service_shortcodes() {
        add_shortcode('kcg_preaching_table', array($this, 'render_preaching_table'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route(
            'kcg-elvanto/v1',
            '/preaching-services',
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_preaching_services'),
                'permission_callback' => '__return_true',
            )
        );
    }
    
    /**
     * Get preaching services from the provider cache.
     */
    public function get_preaching_services($request) {
        if (!class_exists('KCG_Elvanto_Cache')) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'message' => 'The Elvanto API provider cache is not available.',
                    'services' => array(),
                    'count' => 0,
                ),
                503
            );
        }

        $services = KCG_Elvanto_Cache::get_services();

        return new WP_REST_Response(
            array(
                'success' => true,
                'message' => 'Services retrieved successfully.',
                'services' => $services,
                'count' => count($services),
            ),
            200
        );
    }
    
    /**
     * Filter events by service type using the provider-backed cache payload.
     */
    private function filter_by_service_type($events, $service_type) {
        if (!is_array($events)) {
            return array();
        }

        $filtered_services = array();
        $requested_service_type = (string) $service_type;

        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            if ($requested_service_type === '') {
                $filtered_services[] = $event;
                continue;
            }

            $event_service_type = (string) ($event['service_type']['name'] ?? ($event['service_type'] ?? ''));
            if ($event_service_type !== $requested_service_type) {
                continue;
            }

            $date = $event['date'] ?? $event['start_date'] ?? null;
            if ($date && !isset($event['formatted_date'])) {
                $service_datetime = kcg_elvanto_parse_service_datetime($date);
                if ($service_datetime) {
                    $event['formatted_date'] = $service_datetime->format('D jS M');
                }
            }

            $filtered_services[] = $event;
        }

        usort($filtered_services, function($a, $b) {
            $date_a = kcg_elvanto_parse_service_datetime($a['date'] ?? $a['start_date'] ?? '1970-01-01');
            $date_b = kcg_elvanto_parse_service_datetime($b['date'] ?? $b['start_date'] ?? '1970-01-01');
            $ts_a = $date_a ? $date_a->getTimestamp() : 0;
            $ts_b = $date_b ? $date_b->getTimestamp() : 0;
            return $ts_a <=> $ts_b;
        });
        
        return $filtered_services;
    }
    

    /**
     * Render the preaching table shortcode
     */
    public function render_preaching_table($atts = array()) {
        $atts = shortcode_atts(
            array(
                'service_type' => '',
                'limit' => 10,
                'show_time' => 'no',
                'show_location' => 'no',
                'show_description' => 'no',
                'class' => 'kcg-preaching-table'
            ),
            $atts,
            'kcg_preaching_table'
        );

        if (!class_exists('KCG_Elvanto_Cache')) {
            return '<div class="kcg-preaching-error">The Elvanto API provider is not available.</div>';
        }

        $events = KCG_Elvanto_Cache::get_services();

        if (empty($events)) {
            return '<div class="kcg-preaching-error">No services are available in the provider cache yet.</div>';
        }

        if (!empty($atts['service_type'])) {
            $filtered_services = $this->filter_by_service_type($events, $atts['service_type']);
        } else {
            $filtered_services = $events;
        }

        $limit = intval($atts['limit']);
        if ($limit > 0) {
            $filtered_services = array_slice($filtered_services, 0, $limit);
        }

        return $this->build_table_html($filtered_services, $atts);
    }
    
    /**
     * Extract the service series label from either the API field or a normalized alias.
     */
    private function get_service_series_name($service) {
        if (!is_array($service)) {
            return 'N/A';
        }

        $candidates = array(
            $service['series_name'] ?? null,
            $service['series'] ?? null,
            $service['series_name']['name'] ?? null,
            $service['series']['name'] ?? null,
            $service['service_type']['name'] ?? null,
            $service['subtitle'] ?? null,
            $service['name'] ?? null,
        );

        foreach ($candidates as $candidate) {
            $value = $this->extract_display_name($candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return 'N/A';
    }

    /**
     * Recursively extract a readable string from nested Elvanto payloads.
     */
    private function extract_display_name($value) {
        if (is_string($value)) {
            $trimmed = trim($value);
            return $trimmed !== '' ? $trimmed : '';
        }

        if (is_array($value)) {
            foreach (array('name', 'title', 'display_name', 'preferred_name', 'fullname', 'label', 'series_name', 'service_name') as $key) {
                if (isset($value[$key])) {
                    $nested = $this->extract_display_name($value[$key]);
                    if ($nested !== '') {
                        return $nested;
                    }
                }
            }

            foreach ($value as $item) {
                $nested = $this->extract_display_name($item);
                if ($nested !== '') {
                    return $nested;
                }
            }
        }

        return '';
    }

    /**
     * Extract the preacher name from the volunteers payload.
     */
    private function get_service_preacher_name($service) {
        if (!is_array($service)) {
            return 'TBD';
        }

        $candidates = array(
            $service['preacher'] ?? null,
            $service['speaker'] ?? null,
            $service['leader'] ?? null,
            $service['volunteers'] ?? null,
        );

        foreach ($candidates as $candidate) {
            $value = $this->extract_person_name($candidate);
            if ($value !== '') {
                return $value;
            }
        }

        if (!isset($service['volunteers']['plan'])) {
            return 'TBD';
        }

        $plans = is_array($service['volunteers']['plan'])
            ? $service['volunteers']['plan']
            : array($service['volunteers']['plan']);

        foreach ($plans as $plan) {
            if (!is_array($plan) || !isset($plan['positions']['position'])) {
                continue;
            }

            $positions = is_array($plan['positions']['position'])
                ? $plan['positions']['position']
                : array($plan['positions']['position']);

            foreach ($positions as $position) {
                if (!is_array($position)) {
                    continue;
                }

                $position_name = $position['position_name'] ?? '';
                if (
                    stripos($position_name, 'preaching') === false &&
                    stripos($position_name, 'leading') === false &&
                    stripos($position_name, 'preach') === false
                ) {
                    continue;
                }

                if (isset($position['volunteers']['volunteer'])) {
                    $volunteers = is_array($position['volunteers']['volunteer'])
                        ? $position['volunteers']['volunteer']
                        : array($position['volunteers']['volunteer']);

                    foreach ($volunteers as $volunteer) {
                        if (!is_array($volunteer)) {
                            continue;
                        }

                        $person = $volunteer['person'] ?? array();
                        if (!is_array($person)) {
                            continue;
                        }

                        $firstname = isset($person['firstname']) ? trim((string) $person['firstname']) : '';
                        $lastname = isset($person['lastname']) ? trim((string) $person['lastname']) : '';

                        if (!empty($firstname) || !empty($lastname)) {
                            return trim($firstname . ' ' . $lastname);
                        }
                    }
                }
            }
        }

        return 'TBD';
    }

    /**
     * Pull the first person name from nested volunteer arrays.
     */
    private function extract_person_name($value) {
        if (is_string($value)) {
            $trimmed = trim($value);
            return $trimmed !== '' ? $trimmed : '';
        }

        if (is_array($value)) {
            foreach (array('name', 'display_name', 'preferred_name', 'firstname', 'lastname', 'full_name') as $key) {
                if (isset($value[$key])) {
                    $nested = $this->extract_person_name($value[$key]);
                    if ($nested !== '') {
                        return $nested;
                    }
                }
            }

            if (isset($value['person']) && is_array($value['person'])) {
                $first = $this->extract_person_name($value['person']);
                if ($first !== '') {
                    return $first;
                }
            }

            if (isset($value['volunteer']) || isset($value['volunteers']) || isset($value['plan']) || isset($value['positions'])) {
                foreach ($value as $item) {
                    $nested = $this->extract_person_name($item);
                    if ($nested !== '') {
                        return $nested;
                    }
                }
            }
        }

        return '';
    }

    /**
     * Build the HTML table
     */
    private function build_table_html($services, $atts) {
        if (empty($services)) {
            $filter_text = !empty($atts['service_type']) ? ' matching "' . esc_html($atts['service_type']) . '"' : '';
            return '<div class="kcg-preaching-no-services">No services found' . $filter_text . '.</div>';
        }
        
        $class = esc_attr($atts['class']);
        
        $html = '<div class="kcg-card ' . $class . '-wrapper">';
        $html .= '<table class="' . $class . '">';
        
        // Table header
        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th><h5>Date</h5></th>';
        $html .= '<th><h5>Name/Passage</h5></th>';
        $html .= '<th><h5>Speaker</h5></th>';
        $html .= '</tr>';
        $html .= '</thead>';
        
        // Table body
        $html .= '<tbody>';
        
        foreach ($services as $service) {
            $html .= '<tr>';
            
            // Date column
            $date_display = isset($service['formatted_date']) ? $service['formatted_date'] : $service['date'];
            $html .= '<td class="date-cell">' . esc_html($date_display) . '</td>';
            
            // Series column
            $series = $this->get_service_series_name($service);
            $html .= '<td class="series-cell">' . esc_html($series) . '</td>';
            
            // Preacher column
            $preacher = $this->get_service_preacher_name($service);
            $html .= '<td class="preacher-cell">' . esc_html($preacher) . '</td>';
            
            $html .= '</tr>';
        }
        
        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';
        
        return $html;
    }
}
