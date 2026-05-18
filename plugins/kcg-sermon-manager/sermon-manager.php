<?php
/*
Plugin Name: Sermon Manager
Description: A plugin to manage sermons, speakers, and series for King's Church Guildford.
Version: 2.0.2
Author: Sam Sarjudeen
Author URI: https://github.com/samsarj/
Plugin URI: https://github.com/samsarj/kcg-sermon-manager
GitHub Plugin URI: https://github.com/samsarj/kcg-sermon-manager
Primary Branch: main
Text Domain: kcg-sermon-manager
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include Custom Post Types
require_once (plugin_dir_path(__FILE__) . 'includes/custom-post-types.php');

// Include all shortcode files from the includes/shortcodes directory
$shortcode_files = glob(plugin_dir_path(__FILE__) . 'includes/shortcodes/*.php');

foreach ($shortcode_files as $file) {
    require_once $file;
}

// Include Custom Fields (ACF setup)
require_once (plugin_dir_path(__FILE__) . 'includes/custom-fields.php');

// // Include sermon rss generator
require_once (plugin_dir_path(__FILE__) . 'includes/rss.php');

function enqueue_sermon_styles() {
    // Get plugin version for cache busting
    $plugin_data = get_plugin_data(__FILE__);
    $plugin_version = $plugin_data['Version'];
    
    // Enqueue component-specific stylesheets with versioning
    wp_enqueue_style('sermon-manager-series-grid', plugin_dir_url(__FILE__) . 'includes/css/series-grid.css', array(), $plugin_version);
    wp_enqueue_style('sermon-manager-sermon-single', plugin_dir_url(__FILE__) . 'includes/css/sermon-single.css', array(), $plugin_version);
    wp_enqueue_style('sermon-manager-latest-sermon', plugin_dir_url(__FILE__) . 'includes/css/latest-sermon.css', array(), $plugin_version);
    wp_enqueue_style('sermon-manager-sermon-details', plugin_dir_url(__FILE__) . 'includes/css/sermon-details.css', array(), $plugin_version);
    wp_enqueue_style('sermon-manager-responsive', plugin_dir_url(__FILE__) . 'includes/css/responsive.css', array(), $plugin_version);
}

add_action('wp_enqueue_scripts', 'enqueue_sermon_styles');

// Function to get series image for archives
function get_sermon_series_image($post_id) {
    $series_terms = get_the_terms($post_id, 'series');
    
    if (!$series_terms || is_wp_error($series_terms)) {
        return '';
    }
    
    $series = $series_terms[0]; // Assuming one series per sermon
    $series_image_id = get_term_meta($series->term_id, 'series_image', true);
    
    if (!$series_image_id) {
        return '';
    }
    
    $series_image_url = wp_get_attachment_url($series_image_id);
    
    $output = '<div class="sermon-series-image">';
    $output .= '<img src="' . esc_url($series_image_url) . '" alt="' . esc_attr($series->name) . '" width="150" height="150" />';
    $output .= '</div>';
    
    return $output;
}

// Remove the additional filter since we're handling it in the main content function

function custom_sermon_content_filter($content) {
    global $post;
    
    // Only process sermon posts
    if (!is_object($post) || $post->post_type !== 'sermon') {
        return $content;
    }

    // For single sermon pages
    if (is_single() && get_post_type() === 'sermon') {
        return get_sermon_single_content($post->ID);
    }

    // For the front page - treat query loop sermons as single content
    if (is_front_page()) {
        return get_sermon_single_content($post->ID);
    }

    // For the sermons page - check if we're in a specific query context
    if (is_page('sermons')) {
        // Use a simple approach - check if this is the first sermon being processed
        static $sermons_page_first_post = true;
        
        if ($sermons_page_first_post) {
            $sermons_page_first_post = false;
            // First sermon gets full content (latest sermon)
            return get_sermon_single_content($post->ID);
        } else {
            // Subsequent sermons get archive content (recent sermons)
            return $content . get_sermon_archive_content($post->ID);
        }
    }
    
    // For archive pages, add minimal sermon info
    if (is_archive()) {
        return $content . get_sermon_archive_content($post->ID);
    }
    
    // For other contexts, just return the original content
    return $content;
}

// Reset the static variable when starting a new page load
function reset_sermons_page_counter() {
    if (is_page('sermons')) {
        // This will reset our static counter for each page load
        static $reset = false;
        if (!$reset) {
            $reset = true;
        }
    }
}
add_action('wp_head', 'reset_sermons_page_counter');
add_filter('the_content', 'custom_sermon_content_filter');

// Function to get single sermon content
function get_sermon_single_content($post_id) {
    $output = '<div class="sermon-main">';
    
    // Get sermon details (passage, date, speaker)
    $output .= get_sermon_details_html($post_id);

    // Get sermon excerpt
    $output .= get_sermon_excerpt_html($post_id);
    
    // Get audio player
    $output .= get_sermon_audio_html($post_id);
    
    // Get the full series info with image, title, and description
    $series_terms = get_the_terms($post_id, 'series');
    if ($series_terms && !is_wp_error($series_terms)) {
        // Assuming only one series per sermon
        $output .= get_full_series_info_html($series_terms[0]->term_id);
    }

    $output .= '</div>';
    return $output;
}

// Function to get archive sermon content
function get_sermon_archive_content($post_id) {
    $output = '<div class="sermon-archive-details">';
    // Just sermon details for archives (passage, date, speaker)
    $output .= get_sermon_details_html($post_id);
    $output .= '</div>';
    return $output;
}

// Reusable function for sermon details
function get_sermon_details_html($post_id) {
    $output = '<div class="sermon-details">';
    
    // Get ACF fields
    $sermon_passage = get_field('sermon_passage', $post_id);

    // Get WP meta fields
    $sermon_date = get_the_date('D jS F Y', $post_id);
    $speaker = get_the_terms($post_id, 'speaker');
    
    // Output sermon passage if it exists
    if ($sermon_passage) {
        $passage_url = urlencode($sermon_passage);
        $bible_gateway_url = 'https://www.biblegateway.com/passage/?search=' . $passage_url . '&version=NIVUK';
        $output .= '<a href="' . esc_url($bible_gateway_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($sermon_passage) . '</a>';
    } else {
        $output .= 'No passage available.';
    }
    
    // Output sermon date if it exists
    if ($sermon_date) {
        $output .= '<div class="sermon-date">' . esc_html($sermon_date) . '</div>';
    }
    
    // Output speaker(s) if it exists
    if ($speaker && !is_wp_error($speaker)) {
        $speaker_name = esc_html($speaker[0]->name); // Assuming there's only one speaker per sermon
        $speaker_link = get_term_link($speaker[0]);
        $output .= '<a href="' . esc_url($speaker_link) . '">' . $speaker_name . '</a>';
    } else {
        $output .= 'No speaker information available.';
    }
    
    $output .= '</div>';
    return $output;
}

// Function to get sermon excerpt
function get_sermon_excerpt_html($post_id) {
    $excerpt = has_excerpt($post_id) ? get_the_excerpt($post_id) : '';
    
    if (!empty($excerpt)) {
        return '<div class="sermon-excerpt">' . esc_html($excerpt) . '</div>';
    }
    
    return '';
}

// Function to get series info HTML (for archives - minimal)
function get_series_info_html($series_id) {
    $series = get_term($series_id, 'series');
    
    if (!$series || is_wp_error($series)) {
        return '';
    }
    
    $output = '<div class="series-info-minimal">';
    $output .= '<div class="series-title">Series: <a href="' . esc_url(get_term_link($series)) . '">' . esc_html($series->name) . '</a></div>';
    $output .= '</div>';
    
    return $output;
}

// Function to get full series info HTML (for single sermons)
function get_full_series_info_html($series_id) {
    $series = get_term($series_id, 'series');
    
    if (!$series || is_wp_error($series)) {
        return '';
    }

    $output = '<a href="' . esc_url(get_term_link($series)) . '" class="kcg-card series-info-full">';

    // Series image
    $series_image_id = get_term_meta($series_id, 'series_image', true);
    if ($series_image_id) {
        $series_image_url = wp_get_attachment_url($series_image_id);
        // $output .= '<div class="series-image-wrapper">';
        $output .= '<img src="' . esc_url($series_image_url) . '" alt="' . esc_attr($series->name) . '" class="series-image" />';
        // $output .= '</div>';
    }
    
    // Series details
    $output .= '<div class="series-details">';

    // Series title
    $output .= '<h2 class="series-title">' . esc_html($series->name) . '</h2>';

    // Add series book
    $series_book = get_field('series_book', 'series_' . $series_id);
    if ($series_book) {
        if ($series_book === 'topical') {
            $output .= '<p>Part of a topical series.</p>';
        } else {
            $output .= '<p>A series in ' . esc_html($series_book) . '.</p>';
        }
    }

    // Series description
    $series_description = term_description($series_id, 'series');
    if ($series_description) {
        $output .= '<div class="series-description">' . wp_kses_post($series_description) . '</div>';
    }
    $output .= '</div>'; // Close series-details div
    
    $output .= '</a>';
    
    return $output;
}

// Reusable function for sermon audio
function get_sermon_audio_html($post_id) {
    $sermon_audio = get_field('sermon_audio', $post_id);
    $output = '';
    
    if ($sermon_audio) {
        $audio_url = esc_url($sermon_audio['url']);
        $output .= '<div class="sermon-audio">';
        $output .= '<audio controls>';
        $output .= '<source src="' . $audio_url . '" type="audio/mpeg">';
        $output .= 'Your browser does not support the audio element.';
        $output .= '</audio>';
        $output .= '</div>';
    } else {
        $output .= '<div class="sermon-audio"><p>No audio file available for this sermon.</p></div>';
    }
    
    return $output;
}

// Function to get series featured image for archive display
function get_series_featured_image_html($post_id = null, $size = 'medium', $class = 'series-image') {
    // Get the post ID if not provided
    if (!$post_id) {
        if (in_the_loop()) {
            $post_id = get_the_ID();
        } else {
            global $post;
            $post_id = $post ? $post->ID : 0;
        }
    }
    
    if (!$post_id || get_post_type($post_id) !== 'sermon') {
        return '';
    }
    
    // Get series terms
    $series_terms = get_the_terms($post_id, 'series');
    if (!$series_terms || is_wp_error($series_terms)) {
        return '';
    }
    
    // Get series image
    $series = $series_terms[0];
    $series_image_id = get_term_meta($series->term_id, 'series_image', true);
    
    if (!$series_image_id) {
        return '';
    }
    
    // Get image URL
    $image_url = wp_get_attachment_image_url($series_image_id, $size);
    if (!$image_url) {
        return '';
    }
    
    // Get alt text
    $alt_text = get_post_meta($series_image_id, '_wp_attachment_image_alt', true);
    if (!$alt_text) {
        $alt_text = $series->name . ' series image';
    }
    
    return '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($alt_text) . '" class="' . esc_attr($class) . '" />';
}

// Add shortcode for backward compatibility
function series_featured_image_shortcode($atts) {
    $atts = shortcode_atts(array(
        'size' => 'medium',
        'class' => 'card-series-image',
    ), $atts, 'series_featured_image');
    
    return get_series_featured_image_html(null, $atts['size'], $atts['class']);
}
add_shortcode('series_featured_image', 'series_featured_image_shortcode');

// Filter to replace series image placeholder in template parts
function replace_series_image_placeholder($content) {
    // Only process if the content contains our placeholder
    if (strpos($content, '{{series_image}}') !== false) {
        // Get the series image HTML
        $series_image_html = get_series_featured_image_html();
        
        // Debug: Log what we're doing
        error_log('Replacing {{series_image}} placeholder. Image HTML: ' . $series_image_html);
        
        // Replace the placeholder with actual image
        $content = str_replace('{{series_image}}', $series_image_html, $content);
    }
    
    return $content;
}
add_filter('the_content', 'replace_series_image_placeholder', 20);

// Also apply to template parts content using render_block filter
function replace_series_image_in_blocks($block_content, $block) {
    // Check if this is a template part block with our series-image slug
    if (isset($block['blockName']) && $block['blockName'] === 'core/template-part') {
        if (isset($block['attrs']['slug']) && $block['attrs']['slug'] === 'series-image') {
            error_log('Processing series-image template part block');
            return replace_series_image_placeholder($block_content);
        }
    }
    
    // Also check for any content that contains our placeholder
    if (strpos($block_content, '{{series_image}}') !== false) {
        error_log('Found {{series_image}} placeholder in block content');
        return replace_series_image_placeholder($block_content);
    }
    
    return $block_content;
}
add_filter('render_block', 'replace_series_image_in_blocks', 10, 2);
