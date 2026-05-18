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

if ($rss) {
    // Loop through each item in RSS feed
    $count = 0; 
    foreach ($rss->channel->item as $item) {

        // Extract data from RSS item
        $title = (string) $item->title;
        $pubDate = date('Y-m-d H:i:s', strtotime((string) $item->pubDate));

        // Get sanitized title
        $sanitized_title = sanitize_audio_filename($title);

        // Construct audio file path
        $upload_dir = wp_upload_dir();
        $audio_file_path = $upload_dir['basedir'] . '/sermons/audio/';
        $audio_file_name = date('y-m-d', strtotime($pubDate)) . '_' . $sanitized_title . '.mp3';
        $audio_file_path .= $audio_file_name;

        // Check if audio file exists
        if (file_exists($audio_file_path)) {
            // echo "Audio file exists for $title\n";
        } else {
            echo "Expected audio file path: $audio_file_path\n";
            echo "Audio file NOT found for $title\n";
            $count++;
        }
    }

    echo "Verification process completed.\n";
    echo "$count FILES NOT FOUND\n";
} else {
    echo "Error fetching RSS feed.\n";
}
?>
