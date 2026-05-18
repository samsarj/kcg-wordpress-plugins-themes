<?php
/**
 * Helper functions for Elvanto Swiper Plugin
 *
 * @package ElvantoSwiper
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Format event dates for display
 */
function elvanto_swiper_format_event_dates($start_date_string, $end_date_string) {
    // Create DateTime objects for both start and end dates
    $start_date = new DateTime($start_date_string, new DateTimeZone('UTC'));
    $end_date = new DateTime($end_date_string, new DateTimeZone('UTC'));

    // Convert to BST if needed (London timezone)
    $start_date->setTimezone(new DateTimeZone('Europe/London'));
    $end_date->setTimezone(new DateTimeZone('Europe/London'));

    // Format the dates as desired for display
    return [
        'start' => $start_date->format('D jS M | g:ia'), // Example: "Tue 1st Oct | 6:30pm"
        'end' => $end_date->format('g:ia') // Example: "8:15pm"
    ];
}

/**
 * Get the best available image for an event
 */
function elvanto_swiper_get_event_image($event) {
    // Try event picture first
    if (!empty($event['picture']) && filter_var($event['picture'], FILTER_VALIDATE_URL)) {
        return esc_url($event['picture']);
    }
    
    // Try to extract image from description
    if (!empty($event['description'])) {
        $pattern = '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i';
        if (preg_match($pattern, $event['description'], $matches)) {
            $img_url = $matches[1];
            if (filter_var($img_url, FILTER_VALIDATE_URL)) {
                return esc_url($img_url);
            }
        }
    }
    
    // Check if this is a service and try service-specific image logic
    if (isset($event['source']) && $event['source'] === 'service') {
        // Try different service image paths
        if (!empty($event['series']['picture']['original'])) {
            return esc_url($event['series']['picture']['original']);
        } elseif (!empty($event['series']['picture']['medium'])) {
            return esc_url($event['series']['picture']['medium']);
        } elseif (!empty($event['series']['picture']['small'])) {
            return esc_url($event['series']['picture']['small']);
        } elseif (!empty($event['series']['image'])) {
            return esc_url($event['series']['image']);
        }
    }
    
    // Default fallback
    return 'https://cdn.elvanto.eu/img/default-event-avatar.svg';
}

/**
 * Parse service links configuration and return as array
 * Note: This uses the new API provider option name for consistency
 */
function elvanto_swiper_parse_service_links() {
    // Try to use the new option name first, fall back to old one for migration
    $service_links_option = get_option('kcg_elvanto_api_service_links', '');
    
    if (empty($service_links_option)) {
        // Fall back to old option name for backward compatibility
        $service_links_option = get_option('elvanto_swiper_service_links', '');
    }
    
    $service_links = [];
    
    if (!empty($service_links_option)) {
        $lines = explode("\n", $service_links_option);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            
            $parts = explode('|', $line, 2);
            if (count($parts) === 2) {
                $service_type = trim($parts[0]);
                $url = trim($parts[1]);
                
                if (!empty($service_type) && !empty($url) && filter_var($url, FILTER_VALIDATE_URL)) {
                    $service_links[strtolower($service_type)] = $url;
                }
            }
        }
    }
    
    return $service_links;
}

/**
 * Get the appropriate "More Info" URL for an event
 */
function elvanto_swiper_get_more_info_url($event) {
    // For regular events, use the link_info (standardized) or legacy url/register_url fields
    if (!isset($event['source']) || $event['source'] === 'event') {
        if (!empty($event['link_info']) && filter_var($event['link_info'], FILTER_VALIDATE_URL)) {
            return esc_url($event['link_info']);
        } elseif (!empty($event['url']) && filter_var($event['url'], FILTER_VALIDATE_URL)) {
            return esc_url($event['url']);
        } elseif (!empty($event['register_url']) && filter_var($event['register_url'], FILTER_VALIDATE_URL)) {
            return esc_url($event['register_url']);
        }
        return null;
    }
    
    // For services, look up the custom link based on service type
    if ($event['source'] === 'service') {
        $service_links = elvanto_swiper_parse_service_links();
        
        // Try to determine the service type from various possible fields
        $service_type = null;
        
        // Check service_data for service type information
        if (!empty($event['service_data']['service_type'])) {
            $service_type = $event['service_data']['service_type'];
        } elseif (!empty($event['service_data']['series_name'])) {
            $service_type = $event['service_data']['series_name'];
        } elseif (!empty($event['subtitle'])) {
            // Use the standardized subtitle field (mapped from series_name)
            $service_type = $event['subtitle'];
        }
        
        // If we found a service type, look for a matching link
        if ($service_type) {
            $service_type_lower = strtolower(trim($service_type));
            if (isset($service_links[$service_type_lower])) {
                return esc_url($service_links[$service_type_lower]);
            }
        }
        
        // If no specific match found, try some common fallbacks using title
        $title_lower = strtolower($event['title'] ?? $event['name'] ?? '');
        foreach ($service_links as $configured_type => $url) {
            if (strpos($title_lower, $configured_type) !== false) {
                return esc_url($url);
            }
        }
    }
    
    return null;
}
