<?php
// Load WordPress
define('WP_USE_THEMES', false);
require_once('wp-load.php'); // Adjust the path as per your WordPress setup

// Function to update sermon passages
function update_sermon_passages() {
    echo "Starting update process...\n";

    // Get all sermons
    $sermons = new WP_Query(array(
        'post_type' => 'sermon',
        'posts_per_page' => -1,
    ));

    // Counter for updated passages
    $updated_count = 0;

    // Iterate through each sermon
    while ($sermons->have_posts()) {
        $sermons->the_post();
        $sermon_id = get_the_ID();
        $sermon_passage = get_field('sermon_passage'); // Assuming the field name is 'sermon_passage'

        if ($sermon_passage && strpos($sermon_passage, '.') !== false) {
            // Replace '.' with ':'
            $updated_passage = str_replace('.', ':', $sermon_passage);

            // Update the field if changed
            if ($updated_passage !== $sermon_passage) {
                update_field('sermon_passage', $updated_passage, $sermon_id);
                echo "Updated passage for sermon: " . get_the_title() . "\n";
                $updated_count++;
            }
        }
    }

    wp_reset_postdata();

    echo "Update process completed. {$updated_count} passages updated.\n";
}

// Execute the function
update_sermon_passages();
