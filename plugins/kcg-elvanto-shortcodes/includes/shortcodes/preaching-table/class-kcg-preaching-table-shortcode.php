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

/**
 * [kcg_preaching_table] shortcode: a sortable table of upcoming services with their speaker.
 *
 * Examples:
 *   [kcg_preaching_table service_type="Sunday Service" limit="12"]
 *   [kcg_preaching_table type="Sunday Service"]
 *   [kcg_preaching_table calendar="Youth" source="all"]
 *
 * Defaults to services only (source="service") because the speaker comes from the service plan.
 */
class KCG_Elvanto_Preaching_Table_Shortcode {

    public function register() {
        add_shortcode('kcg_preaching_table', array($this, 'render'));
    }

    public function render($atts = array()) {
        $atts = shortcode_atts(
            array_merge(
                KCG_Elvanto_Event_Query::filter_defaults('service'),
                array(
                    'class' => 'kcg-preaching-table',
                )
            ),
            $atts,
            'kcg_preaching_table'
        );

        if (!class_exists('KCG_Elvanto_Cache')) {
            return '<div class="kcg-preaching-error">The Elvanto API provider is not available.</div>';
        }

        return $this->build_table_html(KCG_Elvanto_Event_Query::get($atts), $atts);
    }

    /**
     * Extract the service series label from either the API field or a normalized alias.
     */
    private function get_service_series_name($service) {
        if (!is_array($service)) {
            return 'N/A';
        }

        $candidates = array(
            $service['subtitle'] ?? null,
            $service['title'] ?? null,
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

        if (isset($service['preacher']) && is_string($service['preacher'])) {
            return '' !== trim($service['preacher']) ? trim($service['preacher']) : 'TBD';
        }

        $candidates = array($service['volunteers'] ?? null);

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
            return '<div class="kcg-preaching-no-services">' . esc_html(KCG_Elvanto_Event_Query::no_results_message($atts)) . '</div>';
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
            $service_datetime = KCG_Elvanto_Event_Query::datetime($service);
            $date_display = $service_datetime
                ? wp_date(get_option('date_format'), $service_datetime->getTimestamp(), $service_datetime->getTimezone())
                : ($service['date'] ?? '');
            $sort_date = $service_datetime ? (string) $service_datetime->getTimestamp() : '';
            $html .= '<td class="date-cell" data-sort-value="' . esc_attr($sort_date) . '">' . esc_html($date_display) . '</td>';
            
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
