<?php
// Global array to track displayed members
global $displayed_members;
$displayed_members = [];



/**
 * Function to determine the priority of the team member.
 *
 * @param array $roles An array of roles.
 * @param string $staff_role Staff role.
 * @return int Priority value.
 */
function get_team_member_priority($roles, $staff_role) {
    if (!empty($staff_role)) {
        return 1; // Highest priority
    } elseif (is_array($roles) && in_array('elder', $roles)) {
        return 2;
    } elseif (is_array($roles) && in_array('team_leader', $roles)) {
        return 3;
    } else {
        return 4; // Lowest priority
    }
}


/**
 * Shortcode handler to display team members based on role.
 *
 * @param string $role_type The role type to filter by (e.g., 'elder', 'staff', etc.).
 * @return string The HTML output of the team members.
 */
function display_team_members_by_role($role_type) {
    global $displayed_members;

    // Query all team members
    $args = array(
        'post_type' => 'team',
        'posts_per_page' => -1,
        'post_status' => 'publish',
    );
    $query = new WP_Query($args);

    // Collect and sort team members
    $team_members = [];

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();

            // Skip members who have already been displayed
            if (in_array($post_id, $displayed_members)) {
                continue;
            }

            $team_roles = get_field('team_roles', $post_id);
            $staff_role = get_field('staff_role', $post_id);
            $ministries = mm_get_ministries_for_team_member($post_id);

            // Determine if this member should be displayed in the current shortcode
            $display_in_current_shortcode = false;

            switch ($role_type) {
                case 'elder':
                    if (is_array($team_roles) && in_array('elder', $team_roles)) {
                        $display_in_current_shortcode = true;
                    }
                    break;
                case 'staff':
                    if (!in_array('elder', $team_roles) && !empty($staff_role)) {
                        $display_in_current_shortcode = true;
                    }
                    break;
                case 'team_leader':
                    if (!in_array('elder', $team_roles) && !in_array('staff', $team_roles) && !empty($ministries)) {
                        $display_in_current_shortcode = true;
                    }
                    break;
            }

            if ($display_in_current_shortcode) {
                // Add the member to the team members array
                $team_members[] = [
                    'post_id' => $post_id,
                    'priority' => get_team_member_priority($team_roles, $staff_role),
                    'title' => get_the_title($post_id),
                    'photo' => get_field('team_photo', $post_id) ?: plugin_dir_url(__FILE__) . 'images/placeholder.jpg', // Path to your placeholder image
                    'bio' => get_field('team_bio', $post_id),
                    'email' => get_field('team_email', $post_id),
                    'roles' => get_team_member_role_labels($team_roles, $staff_role, $ministries),
                ];
            }
        }
        wp_reset_postdata();

        // Sort team members based on priority and then alphabetically
        usort($team_members, function($a, $b) {
            if ($a['priority'] === $b['priority']) {
                return strcmp($a['title'], $b['title']);
            }
            return $a['priority'] - $b['priority'];
        });

        // Output the members
        ob_start();
        // Assign CSS class based on role type
        switch ($role_type) {
            case 'elder':
            $list_class = 'team-members-list elders';
            break;
            case 'staff':
            $list_class = 'team-members-list staff';
            break;
            case 'team_leader':
            $list_class = 'team-members-list team';
            break;
        }
        echo '<div class="' . esc_attr($list_class) . '">';
        foreach ($team_members as $member) {
            $displayed_members[] = $member['post_id'];
            echo '<div class="kcg-card team-member-card">';
            if ($member['photo']) {
                echo '<img src="' . esc_url($member['photo']) . '" alt="' . esc_attr($member['title']) . '"/>';
            }
            echo '<div class="card-content">';
            echo '<h3>' . esc_html($member['title']) . '</h3>';
            echo '<h5>' . esc_html($member['roles']) . '</h5>'; // Display roles
            if ($member['bio']) {
                echo '<p class="bio">' . esc_html($member['bio']) . '</p>';
            }
            if ($member['email']) {
                // Generate a button for the email link
                $email_button = sprintf(
                    '<a href="mailto:%1$s" class="wp-block-button__link">%2$s</a>',
                    esc_attr($member['email']),
                    esc_html__('Send Email', 'text-domain')
                );
                echo '<div class="wp-block-button">' . $email_button . '</div>';
            }
            echo '</div>'; // Close card-content div
            echo '</div>'; // Close card div
        }
        echo '</div>'; // Close team-members-list div
        return ob_get_clean();
    } else {
        return '<p>No team members found.</p>';
    }
}

/**
 * Function to get role labels for a team member.
 *
 * @param array $roles Team roles.
 * @param string $staff_role Staff role.
 * @param array $ministries Ministries.
 * @return string Role labels.
 */
function get_team_member_role_labels($roles, $staff_role, $ministries) {
    $roles_output = [];

    // Staff role
    if (!empty($staff_role)) {
        $roles_output[] = esc_html($staff_role);
    }

    // Elder role
    if (is_array($roles) && in_array('elder', $roles)) {
        $roles_output[] = 'Elder';
    }

    // Ministry roles and suffixes
    if (!empty($ministries)) {
        foreach ($ministries as $ministry) {
            if (is_a($ministry, 'WP_Post')) {
                $ministry_name = esc_html(get_the_title($ministry->ID));
                $suffix = get_field('leader_suffix', $ministry->ID);

                // Directly use the suffix value
                if (!empty($suffix)) {
                    $roles_output[] = $ministry_name . ' ' . esc_html($suffix);
                } else {
                    $roles_output[] = $ministry_name;
                }
            }
        }
    }

    return implode(' and ', $roles_output);
}

/**
 * Shortcode handler to display team members based on role.
 *
 * @param array $atts Shortcode attributes.
 * @return string The HTML output of the team members.
 */
function display_team_members_shortcode($atts) {
    $role = isset($atts['role']) ? $atts['role'] : '';
    return display_team_members_by_role($role);
}

function register_team_members_shortcodes() {
    $roles = [
        'elders_list' => 'elder',
        'staff_list' => 'staff',
        'ministry_leaders_list' => 'team_leader'
    ];
    foreach ($roles as $shortcode => $role) {
        add_shortcode($shortcode, function() use ($role) {
            return display_team_members_by_role($role);
        });
    }
}
add_action('init', 'register_team_members_shortcodes');
?>
