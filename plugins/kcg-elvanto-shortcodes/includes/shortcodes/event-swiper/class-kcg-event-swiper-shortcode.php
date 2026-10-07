<?php

/**
 * [next-on-card-swiper] shortcode: upcoming services and events.
 *
 * Examples:
 *   [next-on-card-swiper limit="6"]
 *   [next-on-card-swiper calendar="Youth"]
 *   [next-on-card-swiper source="event" calendar="Youth,Kids"]
 *   [next-on-card-swiper service_type="Sunday Service"]
 *   [next-on-card-swiper exclude_service_type="Prayer Night,Special Service"]
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class KCG_Elvanto_Event_Swiper_Shortcode
{

    public function register()
    {
        add_shortcode('next-on-card-swiper', array($this, 'shortcode_callback'));
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
                    'exclude_service_type' => '',
                    'include_service_name' => '',
                    'show_date' => true,
                    'show_time' => true,
                    'show_description' => true,
                )
            ),
            $atts,
            'next-on-card-swiper'
        );

        $events = KCG_Elvanto_Event_Query::get($atts);

        if (empty($events)) {
            return '<p>No upcoming events found.</p>';
        }

        if (KCG_Elvanto_Email_Context::is_email()) {
            $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">';
            $last_index = count($events) - 1;
            foreach ($events as $index => $event) {
                $html .= '<tr><td style="padding:0 0 ' . ($index < $last_index ? '1rem' : '0') . ';">';
                $html .= KCG_Elvanto_Event_Card::render($event, $atts);
                $html .= '</td></tr>';
            }
            return $html . '</table>';
        }

        // Start building the HTML
        ob_start();
?>
        <div class="swiper elvanto-swiper">
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
