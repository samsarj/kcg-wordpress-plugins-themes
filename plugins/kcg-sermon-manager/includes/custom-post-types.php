<?php
// Register Custom Post Type for Sermons
function sm_register_sermon_post_type() {
    $labels = array(
        'name' => 'Sermons',
        'singular_name' => 'Sermon',
        'menu_name' => 'Sermons',
        'name_admin_bar' => 'Sermon',
        'add_new' => 'Add New',
        'add_new_item' => 'Add New Sermon',
        'edit_item' => 'Edit Sermon',
        'new_item' => 'New Sermon',
        'view_item' => 'View Sermon',
        'search_items' => 'Search Sermons',
        'not_found' => 'No sermons found',
        'not_found_in_trash' => 'No sermons found in trash',
    );

    $args = array(
        'labels' => $labels,
        'public' => true,
        'publicly_queryable' => true,
        'show_ui' => true,
        'show_in_rest' => true,
        'show_in_menu' => true,
        'menu_icon' => 'dashicons-format-quote',
        'query_var' => true,
        'rewrite' => array('slug' => 'sermon'),
        'capability_type' => 'post',
        'has_archive' => true,
        'hierarchical' => false,
        'menu_position' => 20,
        'supports' => array('title', 'thumbnail', 'excerpt'),
    );

    register_post_type('sermon', $args);

    // Flush rewrite rules on plugin activation
    flush_rewrite_rules();
}
add_action('init', 'sm_register_sermon_post_type');

// Register Custom Taxonomy for Sermon Series
function sm_register_sermon_series_taxonomy() {
    $labels = array(
        'name' => 'Series',
        'singular_name' => 'Series',
        'search_items' => 'Search Series',
        'all_items' => 'All Series',
        'parent_item' => 'Parent Series',
        'parent_item_colon' => 'Parent Series:',
        'edit_item' => 'Edit Series',
        'update_item' => 'Update Series',
        'add_new_item' => 'Add New Series',
        'new_item_name' => 'New Series Name',
        'menu_name' => 'Series',
    );

    $args = array(
        'labels' => $labels,
        'hierarchical' => true,
        'public' => true,
        'show_ui' => true,
        'show_in_rest' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => array('slug' => 'series'),
        'supports' => array('thumbnail'),
    );

    register_taxonomy('series', 'sermon', $args);

    // Flush rewrite rules on plugin activation
    flush_rewrite_rules();
}
add_action('init', 'sm_register_sermon_series_taxonomy');


// Register Custom Taxonomy for Speakers
function sm_register_speaker_taxonomy() {
    $labels = array(
        'name' => 'Speakers',
        'singular_name' => 'Speaker',
        'search_items' => 'Search Speakers',
        'popular_items' => 'Popular Speakers',
        'all_items' => 'All Speakers',
        'edit_item' => 'Edit Speaker',
        'update_item' => 'Update Speaker',
        'add_new_item' => 'Add New Speaker',
        'new_item_name' => 'New Speaker Name',
        'separate_items_with_commas' => 'Separate speakers with commas',
        'add_or_remove_items' => 'Add or remove speakers',
        'choose_from_most_used' => 'Choose from the most used speakers',
        'menu_name' => 'Speakers',
    );

    $args = array(
        'labels' => $labels,
        'hierarchical' => false,
        'public' => true,
        'show_ui' => true,
        'show_in_rest' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => array('slug' => 'speakers'),
    );

    register_taxonomy('speaker', 'sermon', $args);

    // Flush rewrite rules on plugin activation
    flush_rewrite_rules();
}
add_action('init', 'sm_register_speaker_taxonomy');
