<?php
/**
 * API functionality for Elvanto Swiper Plugin
 *
 * @package ElvantoSwiper
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Elvanto_Swiper_API {
    
    /**
     * Fetch events from the shared provider cache.
     *
     * This plugin does not own upstream API refreshes; it reads the data the provider has already cached.
     */
    public function fetch_events() {
        if (!class_exists('KCG_Elvanto_Cache')) {
            return false;
        }

        return !empty($this->get_events());
    }
    
    /**
     * Merge events and services data intelligently
     */
    private function merge_events_and_services($events_data, $services_data, &$debug_info) {
        $merged = [];
        $service_ids_seen = [];
        
        // Ensure we have arrays to work with
        if (!is_array($events_data)) {
            $events_data = [];
        }
        if (!is_array($services_data)) {
            $services_data = [];
        }
        
        // Create a lookup map of event colors by ID
        $event_colors = [];
        foreach ($events_data as $event) {
            if (is_array($event) && isset($event['id']) && isset($event['color'])) {
                $event_colors[$event['id']] = $event['color'];
            }
        }
        
        // First, convert services to event format and add them
        foreach ($services_data as $service) {
            if (!is_array($service)) {
                continue; // Skip if service is not an array
            }
            $converted_event = $this->convert_service_to_event($service);
            if ($converted_event) {
                // Apply color from matching event if available
                if (isset($event_colors[$service['id']])) {
                    $converted_event['color'] = $event_colors[$service['id']];
                } else {
                    // Fallback color for services without matching events
                    $converted_event['color'] = '#2e7d32'; // Default green for services
                }
                
                $merged[] = $converted_event;
                if (isset($service['id'])) {
                    $service_ids_seen[] = $service['id'];
                }
            }
        }
        
        // Then add regular events that don't have a corresponding service
        foreach ($events_data as $event) {
            if (!is_array($event) || !isset($event['id'])) {
                continue; // Skip if event is not an array or has no ID
            }
            // Check if this event ID matches any service ID we've already processed
            if (!in_array($event['id'], $service_ids_seen)) {
                // Standardize event mapping:
                // name -> title
                // description -> description
                // where -> location
                // start_date -> date (date only) + time (time only)
                // url -> link_info
                // picture -> picture
                // color -> color
                // id -> id
                // all_day -> all_day
                // register_url -> link_register
                
                $standardized_event = [
                    'id' => $event['id'],
                    'source' => 'event'
                ];
                
                // Map title
                if (!empty($event['name'])) {
                    $standardized_event['title'] = $event['name'];
                }
                
                // Map description
                if (!empty($event['description'])) {
                    $standardized_event['description'] = $event['description'];
                }
                
                // Map location (from where field)
                if (!empty($event['where'])) {
                    $standardized_event['location'] = $event['where'];
                }
                
                // Elvanto supplies the service time in UTC; convert once to the display timezone before storing local fields.
                if (!empty($event['start_date'])) {
                    if (strpos($event['start_date'], ' ') !== false) {
                        $timezone = function_exists('kcg_elvanto_display_timezone') ? kcg_elvanto_display_timezone() : wp_timezone();
                        $source_dt = DateTime::createFromFormat('Y-m-d H:i:s', $event['start_date'], new DateTimeZone('UTC'));
                        if ($source_dt instanceof DateTime) {
                            $source_dt = $source_dt->setTimezone($timezone);
                            $standardized_event['date'] = $source_dt->format('Y-m-d');
                            $standardized_event['time'] = $source_dt->format('H:i:s');
                        } else {
                            $standardized_event['date'] = $event['start_date'];
                        }
                    } else {
                        $standardized_event['date'] = $event['start_date'];
                    }
                }
                
                // Map link_info from url
                if (!empty($event['url'])) {
                    $standardized_event['link_info'] = $event['url'];
                }
                
                // Map picture
                if (!empty($event['picture'])) {
                    $standardized_event['picture'] = $event['picture'];
                }
                
                // Map color
                if (!empty($event['color'])) {
                    $standardized_event['color'] = $event['color'];
                }
                
                // Map all_day
                if (isset($event['all_day'])) {
                    $standardized_event['all_day'] = $event['all_day'];
                }
                
                // Map link_register from register_url
                if (!empty($event['register_url'])) {
                    $standardized_event['link_register'] = $event['register_url'];
                }
                
                $merged[] = $standardized_event;
            }
        }
        
        // Safely extract event IDs
        $event_ids = [];
        foreach ($events_data as $event) {
            if (is_array($event) && isset($event['id'])) {
                $event_ids[] = $event['id'];
            }
        }
        
        $debug_info['merge_stats'] = [
            'services_converted' => count($service_ids_seen),
            'regular_events_added' => count($events_data) - count($service_ids_seen),
            'total_merged' => count($merged),
            'service_ids_seen' => $service_ids_seen,
            'event_ids_from_events' => $event_ids,
            'colors_applied_to_services' => array_intersect_key($event_colors, array_flip($service_ids_seen)),
            'total_event_colors' => count($event_colors)
        ];
        
        // Sort by date - handle both datetime and separate date formats
        usort($merged, function($a, $b) {
            // Try to get a comparable timestamp
            $date_a = $a['date'] ?? $a['start_date'] ?? '1970-01-01';
            $date_b = $b['date'] ?? $b['start_date'] ?? '1970-01-01';
            
            // If it's already a datetime, use it directly, otherwise combine date and time
            $timestamp_a = strtotime($date_a);
            $timestamp_b = strtotime($date_b);
            
            if (isset($a['time']) && !strpos($date_a, ':')) {
                $timestamp_a = strtotime($date_a . ' ' . $a['time']);
            }
            if (isset($b['time']) && !strpos($date_b, ':')) {
                $timestamp_b = strtotime($date_b . ' ' . $b['time']);
            }
            
            return $timestamp_a - $timestamp_b;
        });
        
        return $merged;
    }
    
    /**
     * Convert service data to event format
     */
    private function convert_service_to_event($service) {
        // Ensure we have a valid service array
        if (!is_array($service) || !isset($service['id'])) {
            return false;
        }
        
        // Service to Event mapping:
        // id -> id
        // name -> title
        // series_name -> subtitle
        // date -> date (date only) + time (time only)
        // picture -> picture (prioritized over events)
        // location.name -> location
        // determined link -> link_info
        
        $event = [
            'id' => $service['id'],
            'source' => 'service'
        ];
        
        // Map title (name field)
        if (!empty($service['name'])) {
            $event['title'] = $service['name'];
        }
        
        // Map subtitle (series_name field)
        if (!empty($service['series_name'])) {
            $event['subtitle'] = $service['series_name'];
        }
        
        // Convert the UTC wall time Elvanto gives to the church's display timezone once and only once.
        $service_date = $service['date'] ?? '';
        if (!empty($service_date)) {
            if (strpos($service_date, ' ') !== false) {
                $timezone = function_exists('kcg_elvanto_display_timezone') ? kcg_elvanto_display_timezone() : wp_timezone();
                $source_dt = DateTime::createFromFormat('Y-m-d H:i:s', $service_date, new DateTimeZone('UTC'));
                if ($source_dt instanceof DateTime) {
                    $source_dt = $source_dt->setTimezone($timezone);
                    $event['date'] = $source_dt->format('Y-m-d');
                    $event['time'] = $source_dt->format('H:i:s');
                } else {
                    $event['date'] = $service_date;
                }
            } else {
                // Date only
                $event['date'] = $service_date;
            }
        }
        
        // Map picture (services take priority over events)
        if (!empty($service['picture'])) {
            $event['picture'] = $service['picture'];
        }
        
        // Map location from service location object
        if (!empty($service['location']['name'])) {
            $event['location'] = $service['location']['name'];
        }
        
        // Map description if available
        if (!empty($service['description'])) {
            $event['description'] = $service['description'];
        }
        
        // Determine link_info for services based on configuration
        $link_info = $this->get_service_link_info($service);
        if ($link_info) {
            $event['link_info'] = $link_info;
        }
        
        return $event;
    }
    
    /**
     * Get the appropriate link_info for a service based on configuration
     */
    private function get_service_link_info($service) {
        $service_links = elvanto_swiper_parse_service_links();
        
        // Try to determine the service type from various possible fields
        $service_type = null;
        
        // Check service_data for service type information
        if (!empty($service['service_type']['name'])) {
            $service_type = $service['service_type']['name'];
        } elseif (!empty($service['series_name'])) {
            $service_type = $service['series_name'];
        }
        
        // If we found a service type, look for a matching link using the raw Elvanto service type value.
        if ($service_type) {
            if (isset($service_links[(string) $service_type])) {
                return $service_links[(string) $service_type];
            }
        }
        
        // If no specific match found, try some common fallbacks using title.
        if (!empty($service['name'])) {
            $title = (string) $service['name'];
            foreach ($service_links as $configured_type => $url) {
                if (strpos($title, (string) $configured_type) !== false) {
                    return $url;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Get events for display from the provider cache.
     */
    public function get_events() {
        if (!class_exists('KCG_Elvanto_Cache')) {
            return array();
        }

        $events_data = KCG_Elvanto_Cache::get_events();
        $services_data = KCG_Elvanto_Cache::get_services();

        if (empty($events_data) && empty($services_data)) {
            return array();
        }

        $debug_info = array();
        $merged = $this->merge_events_and_services($events_data, $services_data, $debug_info);
        return $merged;
    }
}
