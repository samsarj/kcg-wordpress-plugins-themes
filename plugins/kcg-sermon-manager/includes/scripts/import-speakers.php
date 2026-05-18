<?php
// Include WordPress functions
require_once('wp-load.php');


// RSS feed URL
$rss_feed_url = 'https://www.kcg.org.uk/podcast';

// Fetch the RSS feed
$rss = simplexml_load_file($rss_feed_url);

// Check if RSS feed is loaded properly
if (!$rss) {
    die('Error loading RSS feed');
}

// Function to add speakers to the 'speaker' taxonomy
function import_speakers_from_rss($rss) {
    $speakers = array();

    // Loop through each item in the RSS feed
    foreach ($rss->channel->item as $item) {
        $author = (string) $item->author;

        // Add speaker to the array if not already added
        if (!in_array($author, $speakers)) {
            $speakers[] = $author;
        }
    }

    // Loop through the unique speakers and add them to the taxonomy
    foreach ($speakers as $speaker) {
        // Check if the speaker already exists
        $term = term_exists($speaker, 'speaker');

        // If the speaker doesn't exist, add it
        if ($term === 0 || $term === null) {
            wp_insert_term($speaker, 'speaker');
            echo "Imported: $speaker.\n";
        }
    }
}

// Call the function to import speakers
import_speakers_from_rss($rss);

echo 'Speakers imported successfully!';
?>
