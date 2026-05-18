<?php
// Load WordPress
define('WP_USE_THEMES', false);
require_once('wp-load.php'); // Adjust the path as per your WordPress setup

// Function to update series associated book
function update_series_associated_book() {
    echo "Starting update process...\n";

    // Define the list of Bible book names
    $bible_books = array(
        'Genesis', 'Exodus', 'Leviticus', 'Numbers', 'Deuteronomy', 'Joshua', 'Judges', 'Ruth',
        '1 Samuel', '2 Samuel', '1 Kings', '2 Kings', '1 Chronicles', '2 Chronicles', 'Ezra', 'Nehemiah', 'Esther',
        'Job', 'Psalm', 'Proverbs', 'Ecclesiastes', 'Song of Songs', 'Isaiah', 'Jeremiah', 'Lamentations',
        'Ezekiel', 'Daniel', 'Hosea', 'Joel', 'Amos', 'Obadiah', 'Jonah', 'Micah', 'Nahum', 'Habakkuk', 'Zephaniah',
        'Haggai', 'Zechariah', 'Malachi',
        'Matthew', 'Mark', 'Luke', 'John', 'Acts',
        'Romans', '1 Corinthians', '2 Corinthians', 'Galatians', 'Ephesians', 'Philippians', 'Colossians',
        '1 Thessalonians', '2 Thessalonians', '1 Timothy', '2 Timothy', 'Titus', 'Philemon', 'Hebrews',
        'James', '1 Peter', '2 Peter', '1 John', '2 John', '3 John', 'Jude', 'Revelation'
    );

    // Get all series terms
    $series_terms = get_terms(array(
        'taxonomy' => 'series',
        'hide_empty' => false,
    ));

    // Iterate through each series term
    foreach ($series_terms as $series) {
        echo "Processing series: {$series->name}\n";

        // Get all sermons in this series
        $sermons = new WP_Query(array(
            'post_type' => 'sermon',
            'posts_per_page' => -1,
            'tax_query' => array(
                array(
                    'taxonomy' => 'series',
                    'field' => 'term_id',
                    'terms' => $series->term_id,
                ),
            ),
        ));

        // Array to hold all unique books from passages
        $books = array();

        // Iterate through each sermon
        while ($sermons->have_posts()) {
            $sermons->the_post();
            $sermon_passage = get_field('sermon_passage'); // Assuming the field name is 'sermon_passage'
            echo $sermon_passage . "\n";
            if ($sermon_passage) {
                // Find the book name in the passage
                $book_name = find_book_in_passage($sermon_passage, $bible_books);

                if ($book_name && !in_array($book_name, $books)) {
                    $books[] = $book_name;
                    echo "Found book '{$book_name}' in sermon: " . get_the_title() . "\n";
                }
            }
        }

        wp_reset_postdata();

        // Determine the associated book for the series
        if (count($books) == 1) {
            $associated_book = $books[0]; // Single book found
            echo "Setting associated book to '{$associated_book}' for series: {$series->name}\n";
        } else {
            $associated_book = ''; // Multiple books found, set to Topical
            echo "Multiple books found for series: {$series->name}. Setting associated book to 'Topical'.\n";
        }

        // Update the series term's associated book field
        $updated = update_field('series_book', $associated_book, 'term_' . $series->term_id);
        if ($updated) {
            echo "Associated book updated successfully for series: {$series->name}\n";
        } else {
            echo "Failed to update associated book for series: {$series->name}\n";
        }

        echo "------------------------------------------\n";
    }

    echo "Update process completed.\n";
}

// Function to find book name in passage
function find_book_in_passage($passage, $bible_books) {
    foreach ($bible_books as $book) {
        // Case-insensitive match
        if (preg_match('/\b' . preg_quote($book, '/') . '\b/i', $passage)) {
            return $book;
        }
    }
    return ''; // Return empty string if no match found
}

// Execute the function
update_series_associated_book();
