<?php
/**
 * The event card shared by [next-on-card-swiper] and [next-on-card].
 *
 * Options ($atts): show_date, show_time, show_description, show_buttons (all default true)
 * and card_class (extra CSS class, e.g. "event-card--compact").
 */

if (!defined('ABSPATH')) {
    exit;
}

class KCG_Elvanto_Event_Card
{
    public static function has_preacher($event)
    {
        if (!is_array($event) || !isset($event['preacher']) || !is_scalar($event['preacher'])) {
            return false;
        }

        $preacher = trim((string) $event['preacher']);
        return '' !== $preacher && 0 !== strcasecmp($preacher, 'TBD');
    }

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
            'show_preacher' => false,
            'card_class' => '',
        ));

        if (KCG_Elvanto_Email_Context::is_email()) {
            return self::render_email($event, $atts);
        }

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
                    <?php if ($atts['show_preacher'] && self::has_preacher($event)): ?>
                        <small class="event-preacher">
                            <?php echo esc_html($event['preacher']); ?>
                        </small>
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

    /**
     * Email-safe card: table layout and inline styles only (no CSS variables, flex or stylesheet).
     */
    private static function render_email($event, $atts)
    {
        $event_color = sanitize_hex_color($event['color'] ?? '');
        $color = $event_color ?: '#444444';
        $border_color = $event_color ?: '#eeeeee';
        $background_color = $event_color ? self::email_background_color($event_color) : '#ffffff';
        $card_radius = self::email_card_radius();
        $card_padding = self::email_card_radius(2);
        $image_radius = $card_padding;
        $compact = 'compact' === ( $atts['layout'] ?? '' );
        if ( $compact ) {
            $card_padding = '0.75rem';
        }
        $has_picture = ! empty( $event['picture'] );
        $title = esc_html($event['title'] ?? 'Event');
        $dt = KCG_Elvanto_Event_Query::datetime($event);
        $lines = array();

        if ($dt) {
            $tz = KCG_Elvanto_Datetime::timezone();
            $when = array();
            if ($atts['show_date']) {
                $when[] = '📅 ' . esc_html(wp_date(get_option('date_format'), $dt->getTimestamp(), $tz));
            }
            if ($atts['show_time'] && '' !== trim((string) ($event['time'] ?? '')) && empty($event['all_day'])) {
                $when[] = '⏰ ' . esc_html(wp_date(get_option('time_format'), $dt->getTimestamp(), $tz));
            }
            if ($when) {
                $lines[] = implode(' &nbsp;|&nbsp; ', $when);
            }
        }
        if (!empty($event['location'])) {
            $lines[] = '📍 ' . esc_html($event['location']);
        }
        $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:separate;border-spacing:0;"><tr><td style="padding:' . esc_attr($card_padding) . ';border:0.0625rem solid ' . esc_attr($border_color) . ';border-radius:' . esc_attr($card_radius) . ';background-color:' . esc_attr($background_color) . ';color:#222222;text-align:' . ( $compact ? 'left' : 'center' ) . ';">';

        if ( $compact ) {
            $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>';
            if ( $has_picture ) {
                $html .= '<td valign="top" style="width:5rem;padding-right:0.75rem;">';
                $html .= '<img src="' . esc_url($event['picture']) . '" alt="' . esc_attr($event['title'] ?? 'Event') . '" width="80" height="80" style="display:block;width:5rem;height:5rem;object-fit:cover;border-radius:' . esc_attr($image_radius) . ';border:0;">';
                $html .= '</td>';
            }
            $html .= '<td valign="top" style="vertical-align:top;">';
        } elseif ( $has_picture ) {
            $html .= '<img src="' . esc_url($event['picture']) . '" alt="' . esc_attr($event['title'] ?? 'Event') . '" width="96" height="96" style="display:block;margin:0 auto 0.75rem;width:6rem;height:6rem;object-fit:cover;border-radius:' . esc_attr($image_radius) . ';border:0;">';
        }
        $html .= '<h4 style="margin:0;font-size:' . ( $compact ? '1rem' : '1.25rem' ) . ';line-height:1.3;font-weight:bold;">' . $title . '</h4>';
        if (!empty($event['subtitle'])) {
            $html .= '<h5 style="margin:0;font-size:' . ( $compact ? '0.8125rem' : '0.9375rem' ) . ';line-height:1.3;color:#555555;">' . esc_html($event['subtitle']) . '</h5>';
        }
        if ($atts['show_preacher'] && self::has_preacher($event)) {
            $html .= '<div style="font-size:0.75rem;line-height:1.4;color:#555555;">' . esc_html($event['preacher']) . '</div>';
        }
        foreach ($lines as $index => $line) {
            $margin_top = 0 === $index ? ( $compact ? 'margin-top:0.375rem;' : 'margin-top:0.5rem;' ) : '';
            $html .= '<div style="font-size:' . ( $compact ? '0.8125rem' : '0.9375rem' ) . ';line-height:1.5;' . $margin_top . '">' . $line . '</div>';
        }
        if ($atts['show_description'] && !empty($event['description'])) {
            $html .= '<div style="font-size:0.9375rem;line-height:1.5;margin-top:0.75rem;">' . wp_kses_post(wpautop($event['description'])) . '</div>';
        }

        if ($atts['show_buttons']) {
            $buttons = array();
            if (!empty($event['link_info']) && filter_var($event['link_info'], FILTER_VALIDATE_URL)) {
                $buttons[] = array('More Info', $event['link_info'], false);
            }
            if (!empty($event['link_register']) && filter_var($event['link_register'], FILTER_VALIDATE_URL)) {
                $buttons[] = array('Register', $event['link_register'], true);
            }
            if ($buttons) {
                $html .= '<table role="presentation" align="' . ( $compact ? 'left' : 'center' ) . '" cellpadding="0" cellspacing="0" border="0" style="margin:' . ( $compact ? '0.5rem 0 0' : '1rem auto 0' ) . ';"><tr>';
                foreach ($buttons as $i => $b) {
                    $style = $b[2]
                        ? 'background-color:' . $color . ';border:0.125rem solid ' . $color . ';color:#ffffff;'
                        : 'background-color:#ffffff;border:0.125rem solid ' . $color . ';color:' . $color . ';';
                    $button_padding = $compact ? '0.3rem 0.55rem' : '0.625rem 1.25rem';
                    $button_font_size = $compact ? '0.75rem' : '0.9375rem';
                    $html .= '<td style="padding:0 ' . ($i < count($buttons) - 1 ? '0.25rem 0 0' : '0 0 0.25rem') . ';"><a href="' . esc_url($b[1]) . '" target="_blank" style="display:inline-block;padding:' . $button_padding . ';border-radius:0.375rem;font-size:' . $button_font_size . ';font-weight:bold;text-decoration:none;' . $style . '">' . esc_html($b[0]) . '</a></td>';
                }
                $html .= '</tr></table>';
            }
        }

        if ( $compact ) {
            $html .= '</td></tr></table>';
        }

        return $html . '</td></tr></table>';
    }

    /**
     * Match the web card's hsl(from color h s 98%) background with a static email-safe color.
     */
    private static function email_background_color($color)
    {
        $hex = ltrim($color, '#');
        if (3 === strlen($hex)) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        $red = hexdec(substr($hex, 0, 2)) / 255;
        $green = hexdec(substr($hex, 2, 2)) / 255;
        $blue = hexdec(substr($hex, 4, 2)) / 255;
        $max = max($red, $green, $blue);
        $min = min($red, $green, $blue);
        $delta = $max - $min;
        $lightness = ($max + $min) / 2;
        $saturation = 0 === $delta ? 0 : $delta / (1 - abs((2 * $lightness) - 1));

        if (0 === $delta) {
            $hue = 0;
        } elseif ($max === $red) {
            $hue = 60 * fmod((($green - $blue) / $delta), 6);
        } elseif ($max === $green) {
            $hue = 60 * ((($blue - $red) / $delta) + 2);
        } else {
            $hue = 60 * ((($red - $green) / $delta) + 4);
        }
        if ($hue < 0) {
            $hue += 360;
        }

        $lightness = 0.98;
        $chroma = (1 - abs((2 * $lightness) - 1)) * $saturation;
        $hue_sector = $hue / 60;
        $intermediate = $chroma * (1 - abs(fmod($hue_sector, 2) - 1));
        $offset = $lightness - ($chroma / 2);

        if ($hue_sector < 1) {
            $rgb = array($chroma, $intermediate, 0);
        } elseif ($hue_sector < 2) {
            $rgb = array($intermediate, $chroma, 0);
        } elseif ($hue_sector < 3) {
            $rgb = array(0, $chroma, $intermediate);
        } elseif ($hue_sector < 4) {
            $rgb = array(0, $intermediate, $chroma);
        } elseif ($hue_sector < 5) {
            $rgb = array($intermediate, 0, $chroma);
        } else {
            $rgb = array($chroma, 0, $intermediate);
        }

        return sprintf(
            '#%02x%02x%02x',
            (int) round(($rgb[0] + $offset) * 255),
            (int) round(($rgb[1] + $offset) * 255),
            (int) round(($rgb[2] + $offset) * 255)
        );
    }

    /**
     * Resolve the theme's KCG card radius preset to a value safe for inline email CSS.
     */
    private static function email_card_radius($divisor = 1)
    {
        if (function_exists('wp_get_global_settings')) {
            $spacing_sizes = wp_get_global_settings(array('spacing', 'spacingSizes'));
            if (is_array($spacing_sizes)) {
                foreach ($spacing_sizes as $spacing_size) {
                    if (
                        isset($spacing_size['slug'], $spacing_size['size'])
                        && '40' === (string) $spacing_size['slug']
                        && preg_match('/^\d+(?:\.\d+)?(?:px|rem|em)$/i', $spacing_size['size'])
                    ) {
                        preg_match('/^(\d+(?:\.\d+)?)(px|rem|em)$/i', $spacing_size['size'], $matches);
                        $rem_size = 'px' === strtolower($matches[2])
                            ? (float) $matches[1] / 16
                            : (float) $matches[1];
                        return ($rem_size / $divisor) . 'rem';
                    }
                }
            }
        }

        return (1 / $divisor) . 'rem';
    }
}
