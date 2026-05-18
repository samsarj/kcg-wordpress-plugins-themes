<?php
/**
 * Elvanto API integration
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'ELVANTO_API_BASE', 'https://api.elvanto.com/v1' );

/**
 * Get Elvanto API key from the API provider plugin
 */
function kcg_checklist_get_api_key() {
    // Check if the API provider plugin is active
    if ( ! class_exists( 'KCG_Elvanto_API_Registry' ) ) {
        return '';
    }
    
    // Get the API key from the provider plugin
    return KCG_Elvanto_API_Registry::get_api_key();
}

/**
 * Make an Elvanto API request
 */
function kcg_checklist_api_request( $endpoint, $method = 'GET', $data = [] ) {
    $api_key = kcg_checklist_get_api_key();
    
    if ( ! $api_key ) {
        return new WP_Error( 'no_api_key', 'Elvanto API key not configured' );
    }
    
    $url = ELVANTO_API_BASE . $endpoint . '.json';
    
    $args = [
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode( $api_key . ':x' ),
            'Content-Type'  => 'application/json',
        ],
        'timeout' => 15,
    ];
    
    if ( 'POST' === $method ) {
        $args['method'] = 'POST';
        $args['body']   = wp_json_encode( $data );
    }
    
    $response = wp_remote_request( $url, $args );
    
    if ( is_wp_error( $response ) ) {
        return $response;
    }
    
    $body = wp_remote_retrieve_body( $response );
    $decoded = json_decode( $body, true );
    
    return $decoded;
}

/**
 * Get all people from Elvanto directly
 */
function kcg_checklist_get_elvanto_members() {
    $response = kcg_checklist_api_request( '/people/getAll' );
    
    if ( is_wp_error( $response ) ) {
        return [];
    }
    
    if ( ! isset( $response['people']['person'] ) ) {
        return [];
    }
    
    $people = $response['people']['person'];
    
    // Ensure it's always an array of people
    if ( isset( $people['id'] ) ) {
        $people = [ $people ];
    }

    // Filter out inactive, archived, or deceased members
    $people = array_filter( $people, function( $person ) {
        $is_contact = isset( $person['contact'] ) ? (int) $person['contact'] : 0;
        $is_archived = isset( $person['archived'] ) ? (int) $person['archived'] : 0;
        $is_deceased = isset( $person['deceased'] ) ? (int) $person['deceased'] : 0;
        
        return $is_contact === 0 && $is_archived === 0 && $is_deceased === 0;
    });

    // Ensure we have valid firstname/lastname data
    $people = array_filter( $people, function( $person ) {
        return isset( $person['firstname'] ) && isset( $person['lastname'] );
    });

    // Sort by display name (preferred_name or firstname)
    usort( $people, function( $a, $b ) {
        $name_a = kcg_checklist_format_person_name( $a );
        $name_b = kcg_checklist_format_person_name( $b );
        return strcasecmp( $name_a, $name_b );
    });

    return $people;
}

/**
 * Cache Elvanto members in WordPress transients
 */
function kcg_checklist_refresh_members_cache() {
    $members = kcg_checklist_get_elvanto_members();
    
    if ( ! is_wp_error( $members ) && ! empty( $members ) ) {
        set_transient( 'kcg_checklist_members_cache', $members, 12 * HOUR_IN_SECONDS );
        return true;
    }
    
    return false;
}

/**
 * Get cached members, refresh if needed
 */
function kcg_checklist_get_cached_members() {
    $cached = get_transient( 'kcg_checklist_members_cache' );
    
    if ( false === $cached ) {
        kcg_checklist_refresh_members_cache();
        $cached = get_transient( 'kcg_checklist_members_cache' );
    }
    
    return $cached ? $cached : [];
}

/**
 * Format a person's name, preferring their preferred_name if available
 * 
 * @param array $person Person data with firstname, preferred_name, and lastname
 * @return string The formatted name
 */
function kcg_checklist_format_person_name( $person ) {
    $firstname = isset( $person['firstname'] ) ? $person['firstname'] : '';
    $preferred_name = isset( $person['preferred_name'] ) ? $person['preferred_name'] : '';
    $lastname = isset( $person['lastname'] ) ? $person['lastname'] : '';
    
    // Use preferred_name if available, otherwise use firstname
    $display_name = ! empty( $preferred_name ) ? $preferred_name : $firstname;
    
    if ( $display_name && $lastname ) {
        return trim( $display_name . ' ' . $lastname );
    }
    
    return trim( $display_name . ' ' . $lastname );
}

/**
 * Get next Sunday timestamp based on current date
 */
function kcg_checklist_get_next_sunday() {
    $today = current_time( 'timestamp' );
    $day_of_week = date( 'w', $today );
    
    // Calculate days until next Sunday (0 = Sunday)
    if ( 0 === (int) $day_of_week ) {
        // Today is Sunday, so next Sunday is 7 days away
        $days_until_sunday = 7;
    } else {
        $days_until_sunday = 7 - (int) $day_of_week;
    }
    
    return $today + ( $days_until_sunday * DAY_IN_SECONDS );
}