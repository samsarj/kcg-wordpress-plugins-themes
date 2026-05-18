<?php
/**
 * Admin Filters for Checklist Items
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Add section filter dropdown to admin list
 */
function kcg_checklist_add_section_filter() {
    global $typenow;

    // Only add filter for our custom post type
    if ( 'kcg_checklist_item' !== $typenow ) {
        return;
    }

    // Get all sections
    $sections = get_terms( array(
        'taxonomy'   => 'kcg_checklist_section',
        'hide_empty' => false,
    ) );

    if ( empty( $sections ) || is_wp_error( $sections ) ) {
        return;
    }

    // Get current filter value if set
    $current_filter = isset( $_GET['kcg_section_filter'] ) ? sanitize_text_field( $_GET['kcg_section_filter'] ) : '';
    ?>
    <select name="kcg_section_filter" id="kcg_section_filter">
        <option value="">Filter by Section</option>
        <?php foreach ( $sections as $section ) : ?>
            <option value="<?php echo esc_attr( $section->slug ); ?>" <?php selected( $current_filter, $section->slug ); ?>>
                <?php echo esc_html( $section->name ); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php
}
add_action( 'restrict_manage_posts', 'kcg_checklist_add_section_filter' );

/**
 * Filter posts by section
 */
function kcg_checklist_filter_by_section( $query ) {
    global $pagenow, $typenow;

    // Only apply filter in admin
    if ( ! is_admin() || 'edit.php' !== $pagenow ) {
        return;
    }

    // Only apply filter for our custom post type
    if ( 'kcg_checklist_item' !== $typenow ) {
        return;
    }

    // Check if filter is set
    if ( ! isset( $_GET['kcg_section_filter'] ) || empty( $_GET['kcg_section_filter'] ) ) {
        return;
    }

    $section_slug = sanitize_text_field( $_GET['kcg_section_filter'] );

    // Add tax query to filter by section
    $tax_query = array(
        array(
            'taxonomy' => 'kcg_checklist_section',
            'field'    => 'slug',
            'terms'    => $section_slug,
        ),
    );

    $query->set( 'tax_query', $tax_query );
}
add_action( 'parse_query', 'kcg_checklist_filter_by_section' );

/**
 * Add order column to the admin list
 */
function kcg_checklist_add_order_column( $columns ) {
    $new_columns = array();
    
    foreach ( $columns as $key => $label ) {
        $new_columns[ $key ] = $label;
        if ( 'title' === $key ) {
            $new_columns['kcg_order'] = __( 'Order', 'kcg-welcome-checklist' );
        }
    }
    
    return $new_columns;
}
add_filter( 'manage_kcg_checklist_item_posts_columns', 'kcg_checklist_add_order_column' );

/**
 * Populate the order column with up/down buttons
 */
function kcg_checklist_populate_order_column( $column, $post_id ) {
    if ( 'kcg_order' === $column ) {
        $order = intval( get_post_meta( $post_id, '_kcg_checklist_order', true ) );
        $sections = wp_get_post_terms( $post_id, 'kcg_checklist_section', array( 'fields' => 'slugs' ) );
        $section = ! empty( $sections ) ? $sections[0] : '';
        
        echo '<span class="kcg-order-value">' . esc_html( $order ? $order : '0' ) . '</span> ';
        echo '<a href="#" class="kcg-move-up" data-post-id="' . esc_attr( $post_id ) . '" data-section="' . esc_attr( $section ) . '" title="Move up">▲</a> ';
        echo '<a href="#" class="kcg-move-down" data-post-id="' . esc_attr( $post_id ) . '" data-section="' . esc_attr( $section ) . '" title="Move down">▼</a>';
    }
}
add_action( 'manage_kcg_checklist_item_posts_custom_column', 'kcg_checklist_populate_order_column', 10, 2 );

/**
 * Make the order column sortable
 */
function kcg_checklist_sortable_order_column( $sortable_columns ) {
    $sortable_columns['kcg_order'] = 'kcg_order';
    return $sortable_columns;
}
add_filter( 'manage_edit-kcg_checklist_item_sortable_columns', 'kcg_checklist_sortable_order_column' );

/**
 * Sort by order meta
 */
function kcg_checklist_sort_by_order( $query ) {
    global $pagenow, $typenow;

    // Only on admin edit page for our post type
    if ( ! is_admin() || 'edit.php' !== $pagenow || 'kcg_checklist_item' !== $typenow ) {
        return;
    }

    // Don't modify query if it's not the main query
    if ( ! $query->is_main_query() ) {
        return;
    }

    // Check if there's an explicit orderby in the URL
    $orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : '';
    $order = isset( $_GET['order'] ) ? sanitize_text_field( $_GET['order'] ) : 'ASC';

    // If ordering by something else, don't override
    if ( ! empty( $orderby ) && 'kcg_order' !== $orderby ) {
        return;
    }

    // Always sort by order - either by default or when explicitly requested
    $query->set( 'meta_key', '_kcg_checklist_order' );
    $query->set( 'orderby', 'meta_value_num' );
    $query->set( 'order', strtoupper( $order ) );
}
add_action( 'parse_query', 'kcg_checklist_sort_by_order' );

/**
 * Enqueue admin scripts for reordering
 */
function kcg_checklist_enqueue_admin_scripts( $hook ) {
    global $typenow;

    if ( 'edit.php' !== $hook || 'kcg_checklist_item' !== $typenow ) {
        return;
    }

    // Initialize order for any items that don't have one
    kcg_checklist_ensure_all_items_have_order();

    wp_enqueue_script(
        'kcg-checklist-admin-order',
        KCG_CHECKLIST_PLUGIN_URL . 'assets/admin-order.js',
        array( 'jquery' ),
        '1.0',
        true
    );

    wp_localize_script( 'kcg-checklist-admin-order', 'kcgChecklistAdmin', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'kcg_checklist_reorder' ),
    ) );
}
add_action( 'admin_enqueue_scripts', 'kcg_checklist_enqueue_admin_scripts' );

/**
 * Ensure all checklist items have an order value
 */
function kcg_checklist_ensure_all_items_have_order() {
    global $wpdb;

    // Get all checklist items
    $all_items = $wpdb->get_col(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'kcg_checklist_item' ORDER BY post_date ASC"
    );

    if ( empty( $all_items ) ) {
        return;
    }

    // Check which items are missing order meta
    $items_without_order = $wpdb->get_col(
        "SELECT p.ID FROM {$wpdb->posts} p
         LEFT JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = '_kcg_checklist_order')
         WHERE p.post_type = 'kcg_checklist_item' AND (pm.post_id IS NULL OR pm.meta_value = '')"
    );

    if ( ! empty( $items_without_order ) ) {
        // Assign sequential order numbers starting from highest existing
        $highest_order = intval(
            $wpdb->get_var(
                "SELECT MAX(CAST(pm.meta_value AS SIGNED)) FROM {$wpdb->postmeta} pm
                 WHERE pm.meta_key = '_kcg_checklist_order'"
            )
        );

        foreach ( $items_without_order as $post_id ) {
            $highest_order++;
            update_post_meta( $post_id, '_kcg_checklist_order', $highest_order );
        }
    }
}

/**
 * AJAX handler for reordering items
 */
function kcg_checklist_ajax_reorder() {
    check_ajax_referer( 'kcg_checklist_reorder', 'nonce' );

    if ( ! isset( $_POST['post_id'], $_POST['direction'] ) ) {
        wp_send_json_error( 'Missing parameters' );
    }

    $post_id = intval( $_POST['post_id'] );
    $direction = sanitize_text_field( $_POST['direction'] );
    $section = isset( $_POST['section'] ) ? sanitize_text_field( $_POST['section'] ) : '';

    if ( ! in_array( $direction, array( 'up', 'down' ), true ) ) {
        wp_send_json_error( 'Invalid direction' );
    }

    // Verify post exists
    $post = get_post( $post_id );
    if ( ! $post || 'kcg_checklist_item' !== $post->post_type ) {
        wp_send_json_error( 'Post not found' );
    }

    // Get current order
    $current_order = intval( get_post_meta( $post_id, '_kcg_checklist_order', true ) );

    // Get post's section if not provided
    $post_sections = wp_get_post_terms( $post_id, 'kcg_checklist_section', array( 'fields' => 'slugs' ) );
    $post_section = ! empty( $post_sections ) ? $post_sections[0] : '';

    // Use post's section if no section provided
    if ( empty( $section ) ) {
        $section = $post_section;
    }

    // Get all items sorted by order
    $args = array(
        'post_type'      => 'kcg_checklist_item',
        'posts_per_page' => -1,
        'nopaging'       => true,
        'orderby'        => 'meta_value_num',
        'meta_key'       => '_kcg_checklist_order',
        'order'          => 'ASC',
        'fields'         => 'ids',
    );

    // Filter by section if provided
    if ( ! empty( $section ) ) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'kcg_checklist_section',
                'field'    => 'slug',
                'terms'    => $section,
            ),
        );
    }

    $items = new WP_Query( $args );
    $item_ids = $items->posts;

    if ( empty( $item_ids ) ) {
        wp_send_json_error( 'No items found' );
    }

    // Find the current item and the item to swap with
    $current_index = array_search( $post_id, $item_ids, true );

    if ( false === $current_index ) {
        wp_send_json_error( 'Item not found in list' );
    }

    if ( 'up' === $direction ) {
        if ( 0 === $current_index ) {
            wp_send_json_error( 'Already at the top' );
        }
        $swap_index = $current_index - 1;
    } else {
        if ( count( $item_ids ) - 1 === $current_index ) {
            wp_send_json_error( 'Already at the bottom' );
        }
        $swap_index = $current_index + 1;
    }

    $swap_post_id = $item_ids[ $swap_index ];
    $swap_order = intval( get_post_meta( $swap_post_id, '_kcg_checklist_order', true ) );

    // Swap the orders
    update_post_meta( $post_id, '_kcg_checklist_order', $swap_order );
    update_post_meta( $swap_post_id, '_kcg_checklist_order', $current_order );

    wp_send_json_success( array(
        'message' => 'Order updated',
        'new_order' => $swap_order,
    ) );
}
add_action( 'wp_ajax_kcg_checklist_reorder', 'kcg_checklist_ajax_reorder' );
add_action( 'wp_ajax_nopriv_kcg_checklist_reorder', 'kcg_checklist_ajax_reorder' );
