<?php
/**
 * Real-time updates via Server-Sent Events (SSE)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handle SSE stream for real-time updates
 */
function kcg_checklist_sse_stream() {
    // Set headers for Server-Sent Events
    header( 'Content-Type: text/event-stream' );
    header( 'Cache-Control: no-cache' );
    header( 'Connection: keep-alive' );
    header( 'X-Accel-Buffering: no' );
    header( 'Access-Control-Allow-Origin: *' );
    
    // Verify nonce
    if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], 'kcg_checklist_sse_nonce' ) ) {
        echo "event: error\n";
        echo "data: Invalid nonce\n\n";
        wp_die();
    }
    
    // Get the current week key
    $current_week_key = kcg_checklist_get_week_key();
    $week_data_key = 'kcg_checklist_week_' . $current_week_key;
    
    // Store the initial data hash
    $last_hash = md5( wp_json_encode( get_option( $week_data_key, [] ) ) );
    
    // Keep the connection alive and check for updates
    $timeout = 0;
    $max_timeout = 3600; // 1 hour
    
    while ( $timeout < $max_timeout ) {
        // Check if there are new updates
        $current_data = get_option( $week_data_key, [] );
        $current_hash = md5( wp_json_encode( $current_data ) );
        
        if ( $current_hash !== $last_hash ) {
            // Send update to client
            echo "event: update\n";
            echo "data: " . wp_json_encode( $current_data ) . "\n\n";
            $last_hash = $current_hash;
        }
        
        // Flush output to send data immediately
        if ( function_exists( 'flush' ) ) {
            flush();
        }
        
        // Sleep for a short interval before checking again
        sleep( 2 );
        $timeout += 2;
    }
    
    // Close the connection after timeout
    echo "event: close\n";
    echo "data: Connection closed\n\n";
    wp_die();
}

/**
 * Register SSE endpoint
 */
function kcg_checklist_register_sse_endpoint() {
    // Hook into wp_loaded to register a custom endpoint
    add_rewrite_rule(
        '^kcg-checklist-sse/?$',
        'index.php?kcg_checklist_sse=1',
        'top'
    );
    
    add_filter( 'query_vars', function( $vars ) {
        $vars[] = 'kcg_checklist_sse';
        return $vars;
    } );
    
    add_action( 'template_redirect', function() {
        if ( get_query_var( 'kcg_checklist_sse' ) ) {
            kcg_checklist_sse_stream();
        }
    } );
}
add_action( 'wp_loaded', 'kcg_checklist_register_sse_endpoint' );

/**
 * Broadcast update to all clients via transient
 */
function kcg_checklist_broadcast_update( $item_id, $value ) {
    // Store the update event in a transient for all clients to fetch
    $updates_key = 'kcg_checklist_updates_' . kcg_checklist_get_week_key();
    $updates = get_transient( $updates_key );
    
    if ( ! is_array( $updates ) ) {
        $updates = [];
    }
    
    $updates[] = [
        'item_id' => $item_id,
        'value' => $value,
        'timestamp' => time(),
    ];
    
    // Keep only recent updates (last 100)
    if ( count( $updates ) > 100 ) {
        $updates = array_slice( $updates, -100 );
    }
    
    // Store for 1 hour
    set_transient( $updates_key, $updates, 3600 );
}

/**
 * Get recent updates for polling fallback
 */
function kcg_checklist_get_recent_updates( $since_timestamp = 0 ) {
    $updates_key = 'kcg_checklist_updates_' . kcg_checklist_get_week_key();
    $updates = get_transient( $updates_key );
    
    if ( ! is_array( $updates ) ) {
        return [];
    }
    
    // Filter updates since the given timestamp
    if ( $since_timestamp > 0 ) {
        $updates = array_filter( $updates, function( $update ) use ( $since_timestamp ) {
            return $update['timestamp'] > $since_timestamp;
        } );
    }
    
    return array_values( $updates );
}

/**
 * AJAX handler to get recent updates
 */
function kcg_checklist_get_updates() {
    // Verify nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'kcg_checklist_nonce' ) ) {
        wp_send_json_error( 'Invalid nonce' );
    }
    
    $since_timestamp = isset( $_POST['since'] ) ? intval( $_POST['since'] ) : 0;
    
    $updates = kcg_checklist_get_recent_updates( $since_timestamp );
    
    wp_send_json_success( [
        'updates' => $updates,
        'current_time' => time(),
    ] );
}
