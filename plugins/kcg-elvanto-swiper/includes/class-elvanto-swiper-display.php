<?php

/**
 * Display functionality for Elvanto Swiper Plugin
 *
 * @package ElvantoSwiper
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Elvanto_Swiper_Display
{

    /**
     * Initialize display functionality
     */
    public function __construct()
    {
        add_shortcode('elvanto_swiper', array($this, 'shortcode_callback'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
    }

    /**
     * Enqueue frontend CSS and JS
     */
    public function enqueue_frontend_assets()
    {
        // Enqueue Swiper CSS
        wp_enqueue_style(
            'swiper-css',
            'https://cdn.jsdelivr.net/npm/swiper@12/swiper-bundle.min.css'
        );

        // Enqueue our custom CSS
        wp_enqueue_style(
            'elvanto-swiper-css',
            ELVANTO_SWIPER_URL . 'includes/assets/elvanto-swiper.css',
            array(),
            filemtime(ELVANTO_SWIPER_PATH . 'includes/assets/elvanto-swiper.css')
        );

        // Enqueue Swiper JS
        wp_enqueue_script(
            'swiper-js',
            'https://cdn.jsdelivr.net/npm/swiper@12/swiper-bundle.min.js',
            array(),
            null,
            true
        );

        // Enqueue our custom JS
        wp_enqueue_script(
            'elvanto-swiper-js',
            ELVANTO_SWIPER_URL . 'includes/assets/elvanto-swiper.js',
            array('swiper-js'),
            filemtime(ELVANTO_SWIPER_PATH . 'includes/assets/elvanto-swiper.js'),
            true
        );
    }

    /**
     * Shortcode callback function
     */
    public function shortcode_callback($atts)
    {
        // Parse shortcode attributes
        $atts = shortcode_atts(array(
            'limit' => 10,
            'show_date' => true,
            'show_time' => true,
            'show_description' => true
        ), $atts);

        // Get events from API provider
        if (!class_exists('Elvanto_Swiper_API')) {
            return '<p>' . esc_html__('API provider not available', 'elvanto-swiper') . '</p>';
        }
        
        $api = new Elvanto_Swiper_API();
        if (!$api) {
            return '<p>' . esc_html__('Events service not initialized', 'elvanto-swiper') . '</p>';
        }
        
        $events = $api->get_events();

        if (empty($events)) {
            return '<p>No upcoming events found.</p>';
        }

        // Limit events if specified
        if ($atts['limit'] > 0) {
            $events = array_slice($events, 0, intval($atts['limit']));
        }

        // Start building the HTML
        ob_start();
?>
        <div class="swiper-container elvanto-swiper">
            <div class="swiper-wrapper">
                <?php foreach ($events as $event): ?>
                    <div class="swiper-slide">
                        <?php echo $this->render_event_card($event, $atts); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php

        return ob_get_clean();
    }

    /**
     * Render individual event card
     */
    private function render_event_card($event, $atts)
    {
        ob_start();

        $formatted_date = '';
        $formatted_time = '';

        // Format date and time from standardized fields with proper timezone handling
        if (!empty($event['date'])) {
            // Just format the date
            $formatted_date = wp_date('D jS M', strtotime($event['date']));
        }

        // Format time from stored local date/time values
        if (!empty($event['time']) && empty($event['all_day'])) {
            $formatted_time = date_i18n('g:i A', strtotime(($event['date'] ?? '') . ' ' . $event['time']));
        }

    ?>
        <div class="kcg-card event-card" <?php if (!empty($event['color'])): ?> style="border-color: <?php echo esc_attr($event['color']); ?>; background-color: hsl(from <?php echo esc_attr($event['color']); ?> h s 98);" <?php endif; ?>>
            <div class="event-header">
                <?php if (!empty($event['picture'])): ?>
                    <div class="event-image">
                        <img src="<?php echo esc_url($event['picture']); ?>" alt="<?php echo esc_attr($event['title'] ?? 'Event'); ?>">
                    </div>
                <?php endif; ?>
                <div class="event-title">
                    <h4><?php echo esc_html($event['title'] ?? 'Event'); ?></h4>
                    <?php if (!empty($event['subtitle'])): ?>
                        <h5><?php echo esc_html($event['subtitle']); ?></h5>
                    <?php endif; ?>
                </div>
            </div>

            <div class="event-content">

                <div class="event-details">
                    <?php if (($atts['show_date'] && !empty($formatted_date)) || ($atts['show_time'] && !empty($formatted_time))): ?>
                        <div class="event-date-time">
                            <?php if ($atts['show_date'] && !empty($formatted_date)): ?>
                                📅 <?php echo esc_html($formatted_date); ?>
                            <?php endif; ?>

                            <?php if ($atts['show_time'] && !empty($formatted_time)): ?>
                                <?php if ($atts['show_date'] && !empty($formatted_date)): ?> | <?php endif; ?>
                                ⏰ <?php echo esc_html($formatted_time); ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($event['location'])): ?>
                        <div class="event-locations">
                            📍 <?php echo esc_html($event['location']); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($atts['show_description'] && !empty($event['description'])): ?>
                    <div class="event-description">
                        <?php echo wp_kses_post(wpautop($event['description'])); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Event Action Buttons -->
            <?php
            // Determine button availability from standardized fields
            $has_more_info = !empty($event['link_info']) && filter_var($event['link_info'], FILTER_VALIDATE_URL);
            $has_register = !empty($event['link_register']) && filter_var($event['link_register'], FILTER_VALIDATE_URL);

            // Determine button width class
            if ($has_more_info && $has_register) {
                $button_width_class = 'wp-block-button__width-50';
            } else {
                $button_width_class = 'wp-block-button__width-100';
            }
            ?>
            <div class="event-buttons wp-block-buttons">
                <?php if ($has_more_info): ?>
                    <div class="wp-block-button is-style-outline is-style-outline--1 has-custom-width <?php echo esc_attr($button_width_class); ?>">
                        <a href="<?php echo esc_url($event['link_info']); ?>" <?php if (!empty($event['color'])): ?> style="border-color: <?php echo esc_attr($event['color']); ?>; color: <?php echo esc_attr($event['color']); ?>; background: transparent;" <?php endif; ?> class="wp-block-button__link wp-element-button" target="_blank">
                            More Info
                        </a>
                    </div>
                <?php endif; ?>

                <?php if ($has_register): ?>
                    <div class="wp-block-button has-custom-width <?php echo esc_attr($button_width_class); ?>">
                        <a href="<?php echo esc_url($event['link_register']); ?>" <?php if (!empty($event['color'])): ?> style="border-color: <?php echo esc_attr($event['color']); ?>; color: var(--wp--preset--color--base); background: <?php echo esc_attr($event['color']); ?>;" <?php endif; ?> class="wp-block-button__link wp-element-button" target="_blank">
                            Register
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
<?php

        return ob_get_clean();
    }
}
