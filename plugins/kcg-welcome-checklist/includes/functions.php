<?php
/**
 * Helper functions for the checklist plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Activation hook
 */
function kcg_checklist_activate() {
    // Create default sections
    kcg_checklist_create_default_sections();
    
    // Initialize settings with defaults
    if ( ! get_option( 'kcg_checklist_reset_day' ) ) {
        update_option( 'kcg_checklist_reset_day', 'tuesday' );
    }
    if ( ! get_option( 'kcg_checklist_reset_hour' ) ) {
        update_option( 'kcg_checklist_reset_hour', '0' );
    }
    
    // Initialize current week's data
    kcg_checklist_init_week_data();
    
    // Clear any existing scheduled event
    wp_clear_scheduled_hook( 'kcg_checklist_weekly_reset' );
    
    // Schedule the weekly reset for the configured day and time
    kcg_checklist_schedule_reset();
}

/**
 * Deactivation hook
 */
function kcg_checklist_deactivate() {
    wp_clear_scheduled_hook( 'kcg_checklist_weekly_reset' );
}


/**
 * Initialize current week's data
 */
function kcg_checklist_init_week_data() {
    $current_week_key = kcg_checklist_get_week_key();
    $week_data_key    = 'kcg_checklist_week_' . $current_week_key;
    
    if ( ! get_option( $week_data_key ) ) {
        // Get items from post type and create initial week data
        $items = kcg_checklist_get_items();
        $week_data = [];
        
        foreach ( $items as $item ) {
            $week_data[ $item['id'] ] = false;
        }
        
        update_option( $week_data_key, $week_data );
    }
}

/**
 * Get the current week's key based on next Sunday
 * Format: YYYY-MM-DD (the Sunday date)
 */
function kcg_checklist_get_week_key() {
    $time = current_time( 'timestamp' );
    
    // Get current day of week (0 = Sunday, 1 = Monday, ..., 6 = Saturday)
    $day_of_week = (int) date( 'w', $time );
    
    // Calculate days until next Sunday
    if ( $day_of_week == 0 ) {
        // If today is Sunday, the next Sunday is 7 days away
        $days_until_sunday = 7;
    } else {
        // Otherwise, it's 7 - current day of week
        $days_until_sunday = 7 - $day_of_week;
    }
    
    // Get the next Sunday timestamp
    $sunday_timestamp = $time + ( $days_until_sunday * DAY_IN_SECONDS );
    
    // Format as YYYY-MM-DD
    return date( 'Y-m-d', $sunday_timestamp );
}

/**
 * Toggle a checklist item
 */
function kcg_checklist_toggle_item() {
    // Verify nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'kcg_checklist_nonce' ) ) {
        wp_send_json_error( 'Invalid nonce' );
    }
    
    // Get parameters
    $item_id = isset( $_POST['item_id'] ) ? sanitize_text_field( $_POST['item_id'] ) : '';
    
    if ( ! $item_id ) {
        wp_send_json_error( 'No item ID provided' );
    }
    
    $current_week_key = kcg_checklist_get_week_key();
    $week_data_key    = 'kcg_checklist_week_' . $current_week_key;
    
    // Get current week data
    $week_data = get_option( $week_data_key, [] );
    
    // Check if this item has volunteer data (array/object with 'id' key)
    $current_value = isset( $week_data[ $item_id ] ) ? $week_data[ $item_id ] : false;
    $has_volunteer_data = is_array( $current_value ) && isset( $current_value['id'] );
    
    if ( $has_volunteer_data ) {
        // For volunteer roles, toggle the checked state but preserve the volunteer data
        // Add a 'checked' flag to the volunteer data
        $week_data[ $item_id ]['checked'] = ! isset( $week_data[ $item_id ]['checked'] ) ? true : ! $week_data[ $item_id ]['checked'];
    } else {
        // Toggle the item as a boolean
        $week_data[ $item_id ] = ! isset( $week_data[ $item_id ] ) ? false : ! $week_data[ $item_id ];
    }
    
    // Save the data
    update_option( $week_data_key, $week_data );
    
    // Broadcast the update to all clients
    $current_value = $has_volunteer_data ? $week_data[ $item_id ]['checked'] : $week_data[ $item_id ];
    kcg_checklist_broadcast_update( $item_id, $current_value );
    
    wp_send_json_success( [
        'item_id' => $item_id,
        'checked' => $current_value,
        'message' => 'Item updated',
    ] );
}

/**
 * Get current checklist status
 */
function kcg_checklist_get_status() {
    // Verify nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'kcg_checklist_nonce' ) ) {
        wp_send_json_error( 'Invalid nonce' );
    }
    
    $current_week_key = kcg_checklist_get_week_key();
    $week_data_key    = 'kcg_checklist_week_' . $current_week_key;
    
    // Initialize week data if needed
    kcg_checklist_init_week_data();
    
    $week_data = get_option( $week_data_key, [] );
    
    wp_send_json_success( [
        'week_key' => $current_week_key,
        'data'     => $week_data,
    ] );
}

/**
 * Get checklist items
 */
function kcg_checklist_get_items() {
    // Query all checklist item posts and sort by order meta
    $args = array(
        'post_type'      => 'kcg_checklist_item',
        'posts_per_page' => -1,
        'orderby'        => 'meta_value_num',
        'meta_key'       => '_kcg_checklist_order',
        'order'          => 'ASC',
    );
    
    $posts = get_posts( $args );
    
    if ( empty( $posts ) ) {
        // No items exist - return empty array
        return array();
    }

    $items = [];
    
    foreach ( $posts as $post ) {
        $terms = wp_get_post_terms( $post->ID, 'kcg_checklist_section', array( 'fields' => 'slugs' ) );
        $section = ! empty( $terms ) ? $terms[0] : 'before_gathering';
        
        $items[] = array(
            'id'          => $post->post_name, // Use post slug as ID
            'title'       => $post->post_title,
            'description' => $post->post_content, // Use post_content for description
            'section'     => $section,
            'order'       => intval( get_post_meta( $post->ID, '_kcg_checklist_order', true ) ),
            'post_id'     => $post->ID,
        );
    }
    
    // Group items by section (already sorted by order from database)
    $items_by_section = [];
    foreach ( $items as $item ) {
        if ( ! isset( $items_by_section[ $item['section'] ] ) ) {
            $items_by_section[ $item['section'] ] = [];
        }
        $items_by_section[ $item['section'] ][] = $item;
    }
    
    // Merge sections in order
    $section_order = array( 'before_sunday', 'before_gathering', 'during_gathering', 'after_gathering' );
    $items = [];
    
    foreach ( $section_order as $section ) {
        if ( isset( $items_by_section[ $section ] ) ) {
            
            // Add to final items array
            $items = array_merge( $items, $items_by_section[ $section ] );
        }
    }
    
    // Remove temporary fields
    foreach ( $items as &$item ) {
        unset( $item['order'] );
    }
    
    return $items;
}

/**
 * Get current week's checklist data
 */
function kcg_checklist_get_week_data() {
    $current_week_key = kcg_checklist_get_week_key();
    $week_data_key    = 'kcg_checklist_week_' . $current_week_key;
    
    kcg_checklist_init_week_data();
    
    return get_option( $week_data_key, [] );
}

/**
 * Format week key for display
 */
function kcg_checklist_format_week( $week_key ) {
    // Parse the week key (format: YYYY-MM-DD for Sunday date)
    if ( preg_match( '/(\d{4})-(\d{2})-(\d{2})/', $week_key, $matches ) ) {
        $year = $matches[1];
        $month = $matches[2];
        $day = $matches[3];
        
        $timestamp = strtotime( "$year-$month-$day" );
        
        // Format as: 25th January
        return date( 'jS F', $timestamp );
    }
    
    return $week_key;
}

/**
 * Get Elvanto members via AJAX (cached)
 */
function kcg_checklist_get_members() {
    // Verify nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'kcg_checklist_nonce' ) ) {
        wp_send_json_error( 'Invalid nonce' );
    }
    
    // Check if Elvanto provider is active
    if ( ! class_exists( 'KCG_Elvanto_API_Registry' ) ) {
        wp_send_json_error( 'Elvanto API provider plugin not active' );
    }
    
    $api_key = KCG_Elvanto_API_Registry::get_api_key();
    
    if ( ! $api_key ) {
        wp_send_json_error( 'Elvanto API key not configured' );
    }
    
    // Fetch members from cache (updates cache if expired)
    $members = kcg_checklist_get_cached_members();
    
    if ( empty( $members ) ) {
        // Log debug info
        error_log( 'KCG Checklist: No members found - filtering may be too strict' );
        wp_send_json_error( 'No members found' );
    }
    
    wp_send_json_success( [
        'members' => $members,
    ] );
}

/**
 * Schedule the weekly reset at the configured day and time
 */
function kcg_checklist_schedule_reset() {
    $reset_day = get_option( 'kcg_checklist_reset_day', 'tuesday' );
    $reset_hour = (int) get_option( 'kcg_checklist_reset_hour', 0 );
    
    // Days: monday=1, tuesday=2, wednesday=3, thursday=4, friday=5, saturday=6, sunday=0
    $day_map = [
        'sunday' => 0,
        'monday' => 1,
        'tuesday' => 2,
        'wednesday' => 3,
        'thursday' => 4,
        'friday' => 5,
        'saturday' => 6,
    ];
    
    $target_day = $day_map[ $reset_day ] ?? 2; // Default to Tuesday
    
    // Calculate next occurrence
    $now = current_time( 'timestamp' );
    $today = (int) date( 'w', $now );
    $current_hour = (int) date( 'H', $now );
    
    // Days to add
    $days_to_add = ( $target_day - $today + 7 ) % 7;
    
    // If it's the target day but past the hour, schedule for next week
    if ( $days_to_add === 0 && $current_hour >= $reset_hour ) {
        $days_to_add = 7;
    }
    
    // Schedule at the target day and hour
    $next_reset = $now + ( $days_to_add * DAY_IN_SECONDS );
    $next_reset = strtotime( date( 'Y-m-d', $next_reset ) . ' ' . $reset_hour . ':00:00' );
    
    wp_schedule_event( $next_reset, 'weekly', 'kcg_checklist_weekly_reset' );
}

function kcg_checklist_do_weekly_reset() {
    // Get next Sunday's key
    $time = current_time( 'timestamp' );
    $day_of_week = (int) date( 'w', $time );
    
    if ( $day_of_week == 0 ) {
        $days_until_sunday = 7;
    } else {
        $days_until_sunday = 7 - $day_of_week;
    }
    
    $sunday_timestamp = $time + ( $days_until_sunday * DAY_IN_SECONDS );
    $next_week_key = date( 'Y-m-d', $sunday_timestamp );
    
    $week_data_key = 'kcg_checklist_week_' . $next_week_key;
    
    // Initialize new week's data with all items unchecked
    $items = kcg_checklist_get_items();
    $week_data = [];
    
    foreach ( $items as $item ) {
        $week_data[ $item['id'] ] = false;
    }
    
    update_option( $week_data_key, $week_data );
    
    // Refresh caches for new week
    kcg_checklist_refresh_members_cache();
}

/**
 * AJAX handler to save items from code textarea
 */
function kcg_checklist_save_items_code() {
    // Verify user capabilities
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Insufficient permissions', 403 );
    }
    
    // Verify nonce
    $nonce = isset( $_POST['kcg_checklist_nonce'] ) ? sanitize_text_field( $_POST['kcg_checklist_nonce'] ) : '';
    if ( ! $nonce || ! wp_verify_nonce( $nonce, 'kcg_checklist_nonce' ) ) {
        wp_send_json_error( 'Security verification failed', 403 );
    }
    
    $items_code = isset( $_POST['items_code'] ) ? wp_unslash( $_POST['items_code'] ) : '';
    
    if ( empty( $items_code ) ) {
        wp_send_json_error( 'Items code cannot be empty' );
    }
    
    // Parse JSON
    $items = json_decode( $items_code, true );
    
    if ( json_last_error() !== JSON_ERROR_NONE ) {
        wp_send_json_error( 'Invalid JSON: ' . json_last_error_msg() );
    }
    
    if ( ! is_array( $items ) ) {
        wp_send_json_error( 'Items code must evaluate to an array' );
    }
    
    // Validate items structure
    foreach ( $items as $item ) {
        if ( ! is_array( $item ) ) {
            wp_send_json_error( 'Each item must be an array' );
        }
        
        $required_fields = [ 'id', 'title', 'description', 'section' ];
        foreach ( $required_fields as $field ) {
            if ( ! isset( $item[ $field ] ) ) {
                wp_send_json_error( "Missing required field: $field" );
            }
        }
    }
    
    // Save items
    update_option( 'kcg_checklist_items', $items );
    
    wp_send_json_success( array(
        'message' => 'Items saved successfully',
        'count'   => count( $items ),
    ) );
}
add_action( 'wp_ajax_kcg_checklist_save_items_code', 'kcg_checklist_save_items_code' );

/**
 * AJAX handler to reset items to default
 */
function kcg_checklist_reset_to_default() {
    // Verify user capabilities
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Insufficient permissions', 403 );
    }
    
    // Verify nonce
    $nonce = isset( $_POST['kcg_checklist_nonce'] ) ? sanitize_text_field( $_POST['kcg_checklist_nonce'] ) : '';
    if ( ! $nonce || ! wp_verify_nonce( $nonce, 'kcg_checklist_nonce' ) ) {
        wp_send_json_error( 'Security verification failed', 403 );
    }
    
    $items = kcg_checklist_get_items();
    
    // Reset current week's checklist
    $current_week_key = kcg_checklist_get_week_key();
    $week_data_key = 'kcg_checklist_week_' . $current_week_key;
    
    $week_data = [];
    foreach ( $items as $item ) {
        $week_data[ $item['id'] ] = false;
    }
    
    update_option( $week_data_key, $week_data );
    
    wp_send_json_success( array(
        'message' => 'Reset to default successfully',
        'count'   => count( $items ),
    ) );
}
add_action( 'wp_ajax_kcg_checklist_reset_to_default', 'kcg_checklist_reset_to_default' );

/**
 * Update volunteer assignment for a role
 */
function kcg_checklist_update_volunteer() {
    // Verify nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'kcg_checklist_nonce' ) ) {
        wp_send_json_error( 'Invalid nonce' );
    }
    
    $role = isset( $_POST['role'] ) ? sanitize_text_field( $_POST['role'] ) : '';
    $person_id = isset( $_POST['person_id'] ) ? sanitize_text_field( $_POST['person_id'] ) : '';
    
    if ( ! $role || ! $person_id ) {
        wp_send_json_error( 'Missing role or person ID' );
    }
    
    // Get the person's name from cache
    $members = kcg_checklist_get_cached_members();
    $person_name = null;
    
    foreach ( $members as $member ) {
        if ( $member['id'] === $person_id ) {
            $person_name = kcg_checklist_format_person_name( $member );
            break;
        }
    }
    
    if ( ! $person_name ) {
        wp_send_json_error( array( 'message' => 'Person not found', 'code' => 'person_not_found' ) );
    }
    
    // Get current week's data
    $current_week_key = kcg_checklist_get_week_key();
    $week_data_key = 'kcg_checklist_week_' . $current_week_key;
    $week_data = get_option( $week_data_key, [] );
    
    // Update the assignment with both ID and name for persistence and display
    $week_data[ $role ] = array(
        'id' => $person_id,
        'name' => $person_name,
    );
    update_option( $week_data_key, $week_data );
    
    // Broadcast the update to all clients
    kcg_checklist_broadcast_update( $role, $week_data[ $role ] );
    
    wp_send_json_success( array(
        'message' => 'Volunteer assigned successfully',
        'role' => $role,
        'name' => $person_name,
    ) );
}

/**
 * Remove volunteer assignment
 */
function kcg_checklist_remove_volunteer() {
    // Verify nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'kcg_checklist_nonce' ) ) {
        wp_send_json_error( 'Invalid nonce' );
    }
    
    $role = isset( $_POST['role'] ) ? sanitize_text_field( $_POST['role'] ) : '';
    
    if ( ! $role ) {
        wp_send_json_error( 'Missing role' );
    }
    
    // Get current week's data
    $current_week_key = kcg_checklist_get_week_key();
    $week_data_key = 'kcg_checklist_week_' . $current_week_key;
    $week_data = get_option( $week_data_key, [] );
    
    // Remove the assignment (set to false)
    $week_data[ $role ] = false;
    update_option( $week_data_key, $week_data );
    
    // Broadcast the update to all clients
    kcg_checklist_broadcast_update( $role, false );
    
    wp_send_json_success( array(
        'message' => 'Volunteer assignment removed',
        'role' => $role,
    ) );
}

/**
 * Clear all caches
 */
function kcg_checklist_clear_all_caches() {
    delete_transient( 'kcg_checklist_members_cache' );
}

/**
 * Refresh all caches via AJAX
 */
function kcg_checklist_refresh_all_caches() {
    // Verify nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'kcg_checklist_nonce' ) ) {
        wp_send_json_error( 'Invalid nonce' );
    }
    
    // Check capabilities
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }
    
    // Clear and refresh caches
    kcg_checklist_clear_all_caches();
    kcg_checklist_refresh_members_cache();
    
    wp_send_json_success( [
        'message' => 'Cache refreshed successfully',
    ] );
}