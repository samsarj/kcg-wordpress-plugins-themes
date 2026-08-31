<?php

/**
 * Plugin Name: Ministry Manager
 * Description: Manage church ministries, leaders, and staff.
 * Version: 1.2.0
 * Author: Sam Sarjudeen
 * Author URI: https://github.com/samsarj
 * Plugin URI: https://github.com/samsarj/kcg-ministry-manager
 * Github Plugin URI: https://github.com/samsarj/kcg-ministry-manager
 * Primary Branch: main
 * Text Domain: kcg-ministry-manager
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MINISTRY_MANAGER_PATH', plugin_dir_path(__FILE__));
define('MINISTRY_MANAGER_URL', plugin_dir_url(__FILE__));

require_once MINISTRY_MANAGER_PATH . 'includes/shortcode-team-members.php';
require_once MINISTRY_MANAGER_PATH . 'includes/shortcode-team-leader.php';

function enqueue_custom_scripts()
{
    wp_enqueue_script(
        'custom-team-script',
        MINISTRY_MANAGER_URL . 'includes/js/team.js',
        array('jquery'),
        filemtime(MINISTRY_MANAGER_PATH . 'includes/js/team.js'),
        true
    );
    wp_enqueue_style(
        'custom-team-style',
        MINISTRY_MANAGER_URL . 'includes/css/team.css',
        array(),
        filemtime(MINISTRY_MANAGER_PATH . 'includes/css/team.css')
    );
}
add_action('wp_enqueue_scripts', 'enqueue_custom_scripts');



function mm_register_post_types()
{
    // Register Team Post Type
    register_post_type(
        'team',
        array(
            'labels'      => array(
                'name'               => __('Team'),
                'singular_name'      => __('Team Member'),
                'add_new_item'       => __('Add New Team Member'),
                'edit_item'          => __('Edit Team Member'),
                'new_item'           => __('New Team Member'),
                'view_item'          => __('View Team Member'),
                'search_items'       => __('Search Team Members'),
                'not_found'          => __('No Team Members found'),
                'not_found_in_trash' => __('No Team Members found in Trash'),
                'menu_name'          => __('Team'),
            ),
            'public'      => true,
            'has_archive' => false,
            'supports'    => array('title'),
            'menu_icon'   => 'dashicons-groups',
        )
    );

    // Register Ministries Post Type
    register_post_type(
        'ministry',
        array(
            'labels'      => array(
                'name'          => __('Ministries'),
                'singular_name' => __('Ministry'),
                'add_new_item' => __('Add New Ministry'),
                'edit_item' => __('Edit Ministry'),
                'new_item' => __('New Ministry'),
                'view_item' => __('View Ministry'),
                'search_items' => __('Search Ministries'),
                'not_found' => __('No Ministries found'),
                'not_found_in_trash' => __('No Ministries found in Trash'),
                'menu_name'     => __('Ministries'),
            ),
            'public'      => true,
            'has_archive' => true,
            'supports'    => array('title'),
            'menu_icon'   => 'dashicons-admin-site',
        )
    );

    // Register Ministry Category Taxonomy
    register_taxonomy('ministry_category', 'ministry', array(
        'labels' => array(
            'name'          => __('Ministry Categories'),
            'singular_name' => __('Ministry Category'),
            'search_items'  => __('Search Ministry Categories'),
            'all_items'     => __('All Ministry Categories'),
            'edit_item'     => __('Edit Ministry Category'),
            'update_item'   => __('Update Ministry Category'),
            'add_new_item'  => __('Add New Ministry Category'),
            'menu_name'     => __('Categories'),
        ),
        'hierarchical' => true,
        'show_admin_column' => true,
    ));
}
add_action('init', 'mm_register_post_types');

if (function_exists('acf_add_local_field_group')) {
    // Team Member Fields
    acf_add_local_field_group(array(
        'key' => 'group_team_member',
        'title' => 'Team Member Details',
        'fields' => array(
            array(
                'key' => 'field_team_roles',
                'label' => 'Roles',
                'name' => 'team_roles',
                'type' => 'checkbox',
                'choices' => array(
                    'staff' => 'Staff',
                    'elder' => 'Elder',
                    'ministry_leader' => 'Ministry Leader',
                ),
                'layout' => 'horizontal',
            ),
            array(
                'key' => 'field_staff_role',
                'label' => 'Staff Role',
                'name' => 'staff_role',
                'type' => 'text',
                'conditional_logic' => array(
                    array(
                        array(
                            'field' => 'field_team_roles',
                            'operator' => '==',
                            'value' => 'staff',
                        ),
                    ),
                ),
            ),
            array(
                'key' => 'field_team_bio',
                'label' => 'Bio',
                'name' => 'team_bio',
                'type' => 'textarea',
                'conditional_logic' => array(
                    array(
                        array(
                            'field' => 'field_team_roles',
                            'operator' => '==',
                            'value' => 'elder',
                        ),
                    ),
                ),
            ),
            array(
                'key' => 'field_team_email',
                'label' => 'Email',
                'name' => 'team_email',
                'type' => 'email',
            ),
            array(
                'key' => 'field_team_photo',
                'label' => 'Photo',
                'name' => 'team_photo',
                'type' => 'image',
                'return_format' => 'url',
                'preview_size' => 'medium',
                'library' => 'all',
                'mime_types' => 'jpg,jpeg,png',
            ),
            array(
                'key' => 'field_team_ministries',
                'label' => 'Ministries',
                'name' => 'team_ministries',
                'type' => 'relationship',
                'post_type' => array('ministry'),
                'return_format' => 'object',
                'ui' => 1,
                'filters' => array('search'),
                'max' => 10,
                'conditional_logic' => array(
                    array(
                        array(
                            'field' => 'field_team_roles',
                            'operator' => '==',
                            'value' => 'ministry_leader',
                        ),
                    ),
                ),
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'team',
                ),
            ),
        ),
    ));

    // Ministry Fields
    acf_add_local_field_group(array(
        'key' => 'group_ministry_details',
        'title' => 'Ministry Details',
        'fields' => array(
            array(
                'key' => 'field_ministry_logo',
                'label' => 'Ministry Logo',
                'name' => 'ministry_logo',
                'type' => 'image',
                'return_format' => 'url',
            ),
            array(
                'key' => 'field_ministry_description',
                'label' => 'Ministry Description',
                'name' => 'ministry_description',
                'type' => 'textarea',
            ),
            array(
                'key' => 'field_ministry_category',
                'label' => 'Ministry Category',
                'name' => 'ministry_category',
                'type' => 'taxonomy',
                'taxonomy' => 'ministry_category',
                'field_type' => 'select',
                'allow_null' => 0,
                'add_term' => 1,
                'save_terms' => 1,
                'load_terms' => 1,
                'return_format' => 'id',
                'multiple' => 0,
            ),
            array(
                'key' => 'field_ministry_leader_suffix',
                'label' => 'Leader Suffix',
                'name' => 'leader_suffix',
                'type' => 'radio',
                'choices' => array(
                    'team_leader' => 'Team Leader',
                    'coordinator' => 'Coordinator',
                ),
                'other_choice' => 1,
                'default_value' => '',
                'return_format' => 'label',
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'ministry',
                ),
            ),
        ),
    ));
}

function mm_change_title_placeholder($title)
{
    $screen = get_current_screen();

    if ('team' == $screen->post_type) {
        $title = 'Enter Team Member\'s Name';
    }

    return $title;
}
add_filter('enter_title_here', 'mm_change_title_placeholder');

function mm_get_team_member_photo($post_id)
{
    $photo = get_field('team_photo', $post_id);

    if (!$photo) {
        // If no photo, use the placeholder image
        $photo = plugin_dir_url(__FILE__) . 'includes/images/placeholder.png';
    }

    return $photo;
}

// Function to get ministries that are selected for a team member
function mm_get_ministries_for_team_member($team_member_id)
{
    $ministries = get_field('team_ministries', $team_member_id);

    if (!$ministries) {
        return array();
    }

    return is_array($ministries) ? $ministries : array($ministries);
}
