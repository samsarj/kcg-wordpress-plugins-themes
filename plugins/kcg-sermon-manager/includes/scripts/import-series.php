<?php
// Include WordPress functions
// require_once('wp-load.php');

// Function to import series taxonomies and update ACF fields
function import_series_and_update_acf() {
    echo "Starting series import...\n";

    // Directory where series images are stored
    $image_directory = wp_upload_dir()['basedir'] . '/sermons/series-images/';

    // Fetch RSS feed
    $rss_feed_url = 'https://www.kcg.org.uk/podcast/';
    $rss_feed = file_get_contents($rss_feed_url);

    if ($rss_feed === false) {
        echo "Failed to fetch RSS feed: $rss_feed_url\n";
        return;
    }

    // Parse RSS feed
    $xml = simplexml_load_string($rss_feed);
    if ($xml === false) {
        echo "Failed to parse RSS feed XML\n";
        return;
    }

    // Initialize an array to store series and their start dates
    $series_data = array();

    // Iterate through RSS items to gather series names and start dates
    foreach ($xml->channel->item as $item) {
        $title = (string) $item->title;
        $pubDate = strtotime((string) $item->pubDate); // Convert pubDate to timestamp
        $first_sermon_date = date('y-m-d', $pubDate); // Format as YY-MM-DD
        $description = (string) $item->description;

        // Extract series name from description (if Series: exists)
        if (strpos($description, 'Series: ') !== false) {
            preg_match('/Series: ([^\.\n]+)/', $description, $matches);
            $series_name = trim($matches[1]);

            // Store series name and start date
            if (!isset($series_data[$series_name])) {
                $series_data[$series_name] = $first_sermon_date;
            } else {
                // Keep the earliest start date for the series
                if (strtotime($series_data[$series_name]) > strtotime($first_sermon_date)) {
                    $series_data[$series_name] = $first_sermon_date;
                }
            }
        }
    }

    // Iterate through collected series data to match with images and create taxonomies
    foreach ($series_data as $series_name => $start_date) {
        echo "Processing series: $series_name\n";
        echo "First sermon date for $series_name: $start_date\n";

        // Generate image filename based on the adjusted title format (YY-MM-DD_Title)
        $clean_title = preg_replace('/[^\w\-]/', '', $series_name); // Remove special characters
        $image_filename = $start_date . '_' . $clean_title;

        // Check for supported image formats
        $supported_formats = array('jpg', 'jpeg', 'png', 'gif');
        $image_url = '';

        foreach ($supported_formats as $format) {
            $image_path = $image_directory . $image_filename . '.' . $format;
            if (file_exists($image_path)) {
                $image_url = wp_upload_dir()['baseurl'] . '/sermons/series-images/' . $image_filename . '.' . $format;
                echo "Image found: $image_path\n";

                // Get attachment ID from image URL
                $attachment_id = get_attachment_id_from_url($image_url);

                if ($attachment_id) {
                    $image_data = array('ID' => $attachment_id);
                    break;
                } else {
                    echo "Failed to get attachment ID for image: $image_url\n";
                }
            }
        }

        // If no image found, set image_data to empty array
        if (empty($image_data)) {
            echo "No image found for $series_name\n";
        }

        // Check if the series taxonomy term already exists
        $slug = sanitize_title($series_name);
        $term = term_exists($series_name, 'series');

        if (!$term || is_wp_error($term)) {
            // Term doesn't exist, create it
            $term_args = array(
                'slug' => $slug,
                'description' => '',
            );
            $term_result = wp_insert_term($series_name, 'series', $term_args);

            if (!is_wp_error($term_result)) {
                echo "Series term created: $series_name\n";

                // Update ACF field with the image attachment ID if an image exists
                if (!empty($image_data)) {
                    update_field('series_image', $image_data, 'series_' . $term_result['term_id']);
                    echo "ACF field updated with image attachment ID: $attachment_id\n";
                } else {
                    echo "No image to update for ACF field\n";
                }
            } else {
                echo "Failed to create series term: $series_name\n";
            }
        } else {
            echo "Series term already exists: $series_name\n";
        }

        echo "Finished processing series: $series_name\n";
    }

    echo "Series import completed!\n";
}

// Function to get attachment ID from image URL
function get_attachment_id_from_url($image_url) {
    global $wpdb;

    $attachment_id = $wpdb->get_var($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE guid='%s'", $image_url));

    return $attachment_id;
}



// Call the function to import series and images
import_series_and_update_acf();
?>
