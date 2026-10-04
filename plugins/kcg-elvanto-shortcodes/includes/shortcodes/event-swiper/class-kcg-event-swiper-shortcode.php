<?php

/**
 * [elvanto_swiper] shortcode: a carousel of upcoming services and events.
 *
 * Examples:
 *   [elvanto_swiper limit="6"]
 *   [elvanto_swiper calendar="Youth"]
 *   [elvanto_swiper source="event" calendar="Youth,Kids"]
 *   [elvanto_swiper service_type="Sunday Service"]
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class KCG_Elvanto_Event_Swiper_Shortcode
{

    public function register()
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

        KCG_Elvanto_Event_Card::enqueue_assets();

        // Enqueue our custom CSS
        wp_enqueue_style(
            'elvanto-swiper-css',
            plugin_dir_url(__FILE__) . 'elvanto-swiper.css',
            array(),
            filemtime(__DIR__ . '/elvanto-swiper.css')
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
            plugin_dir_url(__FILE__) . 'elvanto-swiper.js',
            array('swiper-js'),
            filemtime(__DIR__ . '/elvanto-swiper.js'),
            true
        );
    }

    /**
     * Shortcode callback function
     */
    public function shortcode_callback($atts)
    {
        $atts = shortcode_atts(
            array_merge(
                KCG_Elvanto_Event_Query::filter_defaults('all'),
                array(
                    'show_date' => true,
                    'show_time' => true,
                    'show_description' => true,
                )
            ),
            $atts,
            'elvanto_swiper'
        );

        $events = KCG_Elvanto_Event_Query::get($atts);

        if (empty($events)) {
            return '<p>No upcoming events found.</p>';
        }

        // Start building the HTML
        ob_start();
?>
        <div class="swiper-container elvanto-swiper">
            <div class="swiper-wrapper">
                <?php foreach ($events as $event): ?>
                    <div class="swiper-slide">
                        <?php echo KCG_Elvanto_Event_Card::render($event, $atts); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php

        return ob_get_clean();
    }

}
