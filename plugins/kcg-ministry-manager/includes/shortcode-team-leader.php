<?php

/**
 * Shortcode to display the leader assigned to a specific ministry
 * Usage: [ministry_leader "youth"]
 * Returns: "Leader Suffix: Leader Name" or nothing if no leader found
 */

/**
 * Shortcode handler to display ministry leader
 *
 * @param array $atts Shortcode attributes
 * @return string The leader suffix and name, or empty string
 */
function display_ministry_leader($atts)
{
    // Handle both formats: [ministry_leader "youth"] and [ministry_leader ministry_slug="youth"]
    if (is_array($atts) && isset($atts[0])) {
        // Simple format: [ministry_leader "youth"]
        $ministry_slug = $atts[0];
    }

    // Get ministry post
    $ministry = null;

    if (!empty($ministry_slug)) {
        $ministry = get_page_by_path($ministry_slug, OBJECT, 'ministry');
    }

    // Return empty if ministry not found
    if (!$ministry || $ministry->post_type !== 'ministry') {
        return '';
    }

        // Query team members who have this ministry selected
    $args = array(
        'post_type' => 'team',
        'posts_per_page' => 1,
        'post_status' => 'publish',
        'meta_query' => array(
            array(
                'key' => 'team_ministries',
                'value' => '"' . $ministry->ID . '"',
                'compare' => 'LIKE',
            ),
        ),
    );
    $team_query = new WP_Query($args);

    if (!$team_query->have_posts()) {
        return '';
    }

    $team_query->the_post();
    $leader = get_post();
    wp_reset_postdata();

    if (!$leader) {
        return '';
    }

    // Get ministry leader suffix
    $leader_suffix = get_field('leader_suffix', $ministry->ID);

    // Return empty if no suffix
    if (empty($leader_suffix)) {
        return '';
    }

    // Return "Suffix: Leader Name"
    return esc_html($leader_suffix) . ': ' . esc_html($leader->post_title);
}

// Register the shortcode
add_shortcode('ministry_leader', 'display_ministry_leader');
