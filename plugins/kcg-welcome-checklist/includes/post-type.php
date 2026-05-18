<?php
/**
 * Custom Post Type for Checklist Items
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register custom post type for checklist items
 */
function kcg_checklist_register_post_type() {
    $labels = array(
        'name'               => _x( 'Checklist Items', 'post type general name', 'kcg-welcome-checklist' ),
        'singular_name'      => _x( 'Checklist Item', 'post type singular name', 'kcg-welcome-checklist' ),
        'menu_name'          => _x( 'Items', 'admin menu', 'kcg-welcome-checklist' ),
        'name_admin_bar'     => _x( 'Checklist Item', 'add new on admin bar', 'kcg-welcome-checklist' ),
        'add_new'            => _x( 'Add New', 'kcg_checklist_item', 'kcg-welcome-checklist' ),
        'add_new_item'       => __( 'Add New Checklist Item', 'kcg-welcome-checklist' ),
        'new_item'           => __( 'New Checklist Item', 'kcg-welcome-checklist' ),
        'edit_item'          => __( 'Edit Checklist Item', 'kcg-welcome-checklist' ),
        'view_item'          => __( 'View Checklist Item', 'kcg-welcome-checklist' ),
        'all_items'          => __( 'All Items', 'kcg-welcome-checklist' ),
        'search_items'       => __( 'Search Checklist Items', 'kcg-welcome-checklist' ),
        'not_found'          => __( 'No checklist items found.', 'kcg-welcome-checklist' ),
        'not_found_in_trash' => __( 'No checklist items found in Trash.', 'kcg-welcome-checklist' ),
    );

    $args = array(
        'labels'             => $labels,
        'description'        => __( 'Welcome Checklist Items', 'kcg-welcome-checklist' ),
        'public'             => false,
        'publicly_queryable' => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'show_in_nav_menus'  => false,
        'show_in_rest'       => true,
        'rest_base'          => 'kcg-checklist-items',
        'has_archive'        => false,
        'hierarchical'       => false,
        'menu_position'      => null,
        'supports'           => array( 'title', 'editor' ),
        'taxonomies'         => array( 'kcg_checklist_section' ),
        'capability_type'    => 'post',
    );

    register_post_type( 'kcg_checklist_item', $args );
}
add_action( 'init', 'kcg_checklist_register_post_type' );

/**
 * Initialize order for new items
 */
function kcg_checklist_set_initial_order( $post_id, $post ) {
    if ( 'kcg_checklist_item' !== $post->post_type ) {
        return;
    }

    // Ensure item has a section assigned
    $sections = wp_get_post_terms( $post_id, 'kcg_checklist_section', array( 'fields' => 'ids' ) );
    
    if ( empty( $sections ) ) {
        // Assign to first available section if none exists
        $all_sections = get_terms( array(
            'taxonomy'   => 'kcg_checklist_section',
            'hide_empty' => false,
            'number'     => 1,
            'fields'     => 'ids',
        ) );
        
        if ( ! empty( $all_sections ) ) {
            wp_set_post_terms( $post_id, $all_sections, 'kcg_checklist_section' );
            $sections = $all_sections;
        }
    }

    // Only set order if it doesn't exist
    $existing_order = get_post_meta( $post_id, '_kcg_checklist_order', true );
    if ( '' === $existing_order ) {
        // Get the highest order for items in this post's section
        $args = array(
            'post_type'      => 'kcg_checklist_item',
            'posts_per_page' => 1,
            'nopaging'       => false,
            'orderby'        => 'meta_value_num',
            'meta_key'       => '_kcg_checklist_order',
            'order'          => 'DESC',
            'fields'         => 'ids',
        );

        if ( ! empty( $sections ) ) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'kcg_checklist_section',
                    'field'    => 'term_id',
                    'terms'    => $sections,
                ),
            );
        }

        $query = new WP_Query( $args );
        $highest_order = 0;

        if ( ! empty( $query->posts ) ) {
            $highest_order = intval( get_post_meta( $query->posts[0], '_kcg_checklist_order', true ) );
        }

        update_post_meta( $post_id, '_kcg_checklist_order', $highest_order + 1 );
    }
}
add_action( 'save_post_kcg_checklist_item', 'kcg_checklist_set_initial_order', 10, 2 );

/**
 * Register custom taxonomy for sections
 */
function kcg_checklist_register_taxonomy() {
    $labels = array(
        'name'                       => _x( 'Sections', 'taxonomy general name', 'kcg-welcome-checklist' ),
        'singular_name'              => _x( 'Section', 'taxonomy singular name', 'kcg-welcome-checklist' ),
        'search_items'               => __( 'Search Sections', 'kcg-welcome-checklist' ),
        'popular_items'              => __( 'Popular Sections', 'kcg-welcome-checklist' ),
        'all_items'                  => __( 'All Sections', 'kcg-welcome-checklist' ),
        'edit_item'                  => __( 'Edit Section', 'kcg-welcome-checklist' ),
        'update_item'                => __( 'Update Section', 'kcg-welcome-checklist' ),
        'add_new_item'               => __( 'Add New Section', 'kcg-welcome-checklist' ),
        'new_item_name'              => __( 'New Section Name', 'kcg-welcome-checklist' ),
        'back_to_items'              => __( 'Back to Sections', 'kcg-welcome-checklist' ),
    );

    $args = array(
        'labels'            => $labels,
        'public'            => false,
        'show_ui'           => true,
        'show_in_menu'      => false,
        'show_in_rest'      => true,
        'rest_base'         => 'kcg-checklist-sections',
        'show_admin_column' => true,
        'hierarchical'      => false,
    );

    register_taxonomy( 'kcg_checklist_section', array( 'kcg_checklist_item' ), $args );
}
add_action( 'init', 'kcg_checklist_register_taxonomy' );

/**
 * Create default sections
 */
function kcg_checklist_create_default_sections() {
    $sections = array(
        'before_sunday' => array(
            'name' => 'Before Sunday',
            'slug' => 'before-sunday',
        ),
        'before_gathering' => array(
            'name' => 'Before Our Gathering',
            'slug' => 'before-gathering',
        ),
        'during_gathering' => array(
            'name' => 'During Our Gathering',
            'slug' => 'during-gathering',
        ),
        'after_gathering' => array(
            'name' => 'After Our Gathering',
            'slug' => 'after-gathering',
        ),
    );

    foreach ( $sections as $key => $section ) {
        // Check if term exists
        if ( ! term_exists( $section['slug'], 'kcg_checklist_section' ) ) {
            wp_insert_term(
                $section['name'],
                'kcg_checklist_section',
                array( 'slug' => $section['slug'] )
            );
        }
    }
}

/**
 * Add custom meta boxes for checklist items
 */
function kcg_checklist_add_meta_boxes() {
    // No custom meta boxes needed
}
add_action( 'add_meta_boxes', 'kcg_checklist_add_meta_boxes' );
