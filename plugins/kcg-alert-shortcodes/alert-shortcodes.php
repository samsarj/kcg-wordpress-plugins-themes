<?php
/*
Plugin Name: Bootstrap Alert Shortcodes
Description: A plugin to add Bootstrap-style alerts using shortcodes with options to hide and expire alerts.
Version: 2.0.0
Author: Sam Sarjudeen
Author URI: https://github.com/samsarj
Plugin URI: https://github.com/samsarj/kcg-alert-shortcodes
GitHub Plugin URI: https://github.com/samsarj/kcg-alert-shortcodes
Primary Branch: main
Text Domain: kcg-alert-shortcodes
*/

function enqueue_alert_styles() {
    if (has_shortcode(get_post()->post_content, 'alert')) {
        wp_enqueue_style('custom-alert-styles', plugin_dir_url(__FILE__) . 'css/alert-styles.css');
    }
}
add_action('wp_enqueue_scripts', 'enqueue_alert_styles');

function register_alert_post_type() {
    $labels = [
        'name' => 'Alerts',
        'singular_name' => 'Alert',
        'menu_name' => 'Alerts',
        'add_new' => 'Add New Alert',
        'add_new_item' => 'Add New Alert',
        'edit_item' => 'Edit Alert',
        'new_item' => 'New Alert',
        'view_item' => 'View Alert',
        'search_items' => 'Search Alerts',
        'not_found' => 'No alerts found',
        'not_found_in_trash' => 'No alerts found in Trash',
    ];

    $args = [
        'labels' => $labels,
        'public' => false,
        'show_ui' => true,
        'publicly_queryable' => true,
        'show_in_admin' => true,
        'show_in_rest' => true,
        'menu_icon' => 'dashicons-warning',
        'supports' => ['title', 'editor'],
        'rewrite' => [
            'slug' => 'alert',
            'with_front' => false,
        ],
        'has_archive' => false,
    ];

    register_post_type('alert', $args);
}
add_action('init', 'register_alert_post_type');

function register_alert_acf_fields() {
    if( function_exists('acf_add_local_field_group') ) {
        acf_add_local_field_group([
            'key' => 'group_alert_fields',
            'title' => 'Alert Fields',
            'fields' => [
                [
                    'key' => 'field_alert_type',
                    'label' => 'Alert Type',
                    'name' => 'alert_type',
                    'type' => 'select',
                    'choices' => [
                        'primary' => 'Primary',
                        'secondary' => 'Secondary',
                        'success' => 'Success',
                        'danger' => 'Danger',
                        'warning' => 'Warning',
                        'info' => 'Info',
                        'light' => 'Light',
                        'dark' => 'Dark'
                    ],
                    'default_value' => 'info'
                ],
                [
                    'key' => 'field_alert_title_visibility',
                    'label' => 'Show Title',
                    'name' => 'alert_title_visibility',
                    'type' => 'true_false',
                    'ui' => 1,
                    'default_value' => 1
                ],
                [
                    'key' => 'field_alert_hidden',
                    'label' => 'Hidden',
                    'name' => 'alert_hidden',
                    'type' => 'true_false',
                    'ui' => 1,
                    'default_value' => 0
                ],
                [
                    'key' => 'field_alert_expiry',
                    'label' => 'Expiry Date/Time',
                    'name' => 'alert_expiry',
                    'type' => 'date_time_picker',
                    'display_format' => 'Y-m-d H:i',
                    'return_format' => 'Y-m-d H:i'
                ]
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'alert'
                    ]
                ]
            ]
        ]);
    }
}
add_action('acf/init', 'register_alert_acf_fields');

function bootstrap_alert_shortcode($atts) {
    $atts = shortcode_atts(['name' => ''], $atts, 'alert');
    $name = sanitize_title_with_dashes($atts['name']); // Sanitize the name (slug)

    // Query specific alert by slug (name)
    $alert_query = new WP_Query([
        'post_type' => 'alert',
        'name' => $name,
        'posts_per_page' => 1
    ]);

    if ($alert_query->have_posts()) {
        $alert_query->the_post();
        
        // Get ACF field values
        $type = get_field('alert_type') ?: 'info';
        $show_title = get_field('alert_title_visibility');
        $hidden = get_field('alert_hidden');
        $expiry = get_field('alert_expiry');
        
        // Check hidden and expiry status
        if ($hidden || ($expiry && strtotime($expiry) < time())) {
            return ''; // Return empty if hidden or expired
        }

        // Start output buffering
        ob_start();
        ?>
        <div class="kcg-card alert alert-<?php echo esc_attr($type); ?>" role="alert">
            <?php if ($show_title) : ?>
                <h4><?php echo esc_html(get_the_title()); ?></h4>
            <?php endif; ?>
            <?php echo wp_kses_post(get_the_content()); ?>
        </div>
        <?php
        
        wp_reset_postdata();
        return ob_get_clean();
    } else {
        return '';
    }
}
add_shortcode('alert', 'bootstrap_alert_shortcode');
