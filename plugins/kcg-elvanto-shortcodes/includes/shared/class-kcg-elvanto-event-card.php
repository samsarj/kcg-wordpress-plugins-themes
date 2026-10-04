<?php
/**
 * The event card shared by [elvanto_swiper] and [next-on-card].
 *
 * Options ($atts): show_date, show_time, show_description, show_buttons (all default true)
 * and card_class (extra CSS class, e.g. "event-card--compact").
 */

if (!defined('ABSPATH')) {
    exit;
}

class KCG_Elvanto_Event_Card
{
    public static function enqueue_assets()
    {
        wp_enqueue_style(
            'kcg-elvanto-event-card',
            plugin_dir_url(__FILE__) . 'event-card.css',
            array(),
            filemtime(__DIR__ . '/event-card.css')
        );
    }

    /**
     * Render one merged_events item as a card.
     */
    public static function render($event, $atts = array())
    {
        $atts = wp_parse_args($atts, array(
            'show_date' => true,
            'show_time' => true,
            'show_description' => true,
            'show_buttons' => true,
            'card_class' => '',
        ));

        ob_start();

        $formatted_date = '';
        $formatted_time = '';

        $event_time = trim((string) ($event['time'] ?? ''));
        $dt = KCG_Elvanto_Event_Query::datetime($event);

        if ($dt) {
            $display_timezone = KCG_Elvanto_Datetime::timezone();
            $formatted_date = wp_date(get_option('date_format'), $dt->getTimestamp(), $display_timezone);
            if ('' !== $event_time && empty($event['all_day'])) {
                $formatted_time = wp_date(get_option('time_format'), $dt->getTimestamp(), $display_timezone);
            }
        }

    ?>
        <div class="kcg-card event-card <?php echo esc_attr($atts['card_class']); ?>" <?php if (!empty($event['color'])): ?> style="border-color: <?php echo esc_attr($event['color']); ?>; background-color: hsl(from <?php echo esc_attr($event['color']); ?> h s 98);" <?php endif; ?>>
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

            <?php if ($atts['show_buttons']): ?>
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
                    <div class="wp-block-button is-style-outline is-style-outline--2 has-custom-width <?php echo esc_attr($button_width_class); ?>">
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
            <?php endif; ?>
        </div>
<?php

        return ob_get_clean();
    }
}
