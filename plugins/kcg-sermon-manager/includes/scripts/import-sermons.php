<?php
// Load WordPress environment
require_once('wp-load.php');

// URL to your RSS feed
$rss_feed_url = 'https://www.kcg.org.uk/podcast/';

// Fetch RSS feed
$rss = simplexml_load_file($rss_feed_url);

// Construct sanitized audio file name
function sanitize_audio_filename($title) {
    // Allow only alphanumeric characters and hyphens
    $sanitized_title = preg_replace('/[^a-zA-Z0-9-]/', '', $title);
    return $sanitized_title;
}

// Get attachment ID by file path
function get_attachment_id_from_src($file_path) {
    global $wpdb;
    $attachment = $wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE guid='%s';", $file_path));
    return isset($attachment[0]) ? $attachment[0] : false;
}

if ($rss) {
    // Loop through each item in RSS feed
    // $count = 0; // Limit to processing five items
    foreach ($rss->channel->item as $item) {
        // if ($count >= 5) break; // Process only five items

        // Extract data from RSS item
        $title = (string) $item->title;
        $author = (string) $item->author;
        $description = (string) $item->description;
        $pubDate = date('Y-m-d H:i:s', strtotime((string) $item->pubDate));
        $passage = (string) $item->children('itunes', true)->subtitle;

        echo "Processing item: $title\n";
        echo "Speaker: $author\n";
        echo "Passage: $passage\n";
        echo "Date: $pubDate\n";

        // Extract series from description
        preg_match('/Series: ([^.]+)\./', $description, $series_matches);
        $series_name = isset($series_matches[1]) ? trim($series_matches[1]) : '';
        echo "Series: $series_name\n";

        // Find series term ID in 'series' taxonomy
        $series_term = get_term_by('name', $series_name, 'series');
        if ($series_term && !is_wp_error($series_term)) {
            $series_id = $series_term->term_id;
        } else {
            echo "Series term not found: $series_name\n";
            continue;
        }
        echo "Series ID: $series_id\n";

        // Find speaker term ID in 'speaker' taxonomy
        $speaker_term = get_term_by('name', $author, 'speaker');
        if ($speaker_term && !is_wp_error($speaker_term)) {
            $speaker_id = $speaker_term->term_id;
        } else {
            echo "Speaker term not found: $author\n";
            continue;
        }
        echo "Speaker ID: $speaker_id\n";

        // Get sanitized title
        $sanitized_title = sanitize_audio_filename($title);

        // Construct audio file path
        $upload_dir = wp_upload_dir();
        $audio_file_url = $upload_dir['baseurl'] . '/sermons/audio/';
        $audio_file_name = date('y-m-d', strtotime($pubDate)) . '_' . $sanitized_title . '.mp3';
        $audio_file_url .= $audio_file_name;

        echo "Expected audio file URL: $audio_file_url\n";

        // Get attachment ID for the audio file
        $attachment_id = get_attachment_id_from_src($audio_file_url);

        if ($attachment_id) {
            echo "Audio file exists for $title. Attachment ID: $attachment_id\n";
        } else {
            echo "Audio file NOT found for $title\n";
        }

        // Prepare sermon data
        $sermon_data = array(
            'post_title' => $title,
            'post_type' => 'sermon',
            'post_status' => 'publish',
            'post_author' => 1, // Change this to the appropriate author ID
            'post_date' => $pubDate,
        );

        // Insert sermon post
        $sermon_id = wp_insert_post($sermon_data);

        if (!is_wp_error($sermon_id)) {
            // Update sermon meta fields
            update_post_meta($sermon_id, 'sermon_passage', $passage);
            wp_set_object_terms($sermon_id, $series_id, 'series');
            wp_set_object_terms($sermon_id, $speaker_id, 'speaker');
            
            if ($attachment_id) {
                update_field('sermon_audio', $attachment_id, $sermon_id);
            }

            echo "Sermon imported successfully. Sermon ID: $sermon_id\n";
        } else {
            echo "Error importing sermon: " . $sermon_id->get_error_message() . "\n";
        }

        // Increment count
        // $count++;
    }

    echo "Import process completed.\n";
} else {
    echo "Error fetching RSS feed.\n";
}
?>
