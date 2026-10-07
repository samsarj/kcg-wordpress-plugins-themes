<?php
/**
 * [next-on-card] shortcode: the next upcoming service or event as a compact event card
 * (a compact event card without the description and buttons).
 *
 * Examples:
 *   [next-on-card]                              next service or event
 *   [next-on-card type="Sunday Service"]        service type
 *   [next-on-card service_type="Prayer Night"]  service type only
 *   [next-on-card calendar="Youth" align="center" width="20rem"]
 *   [next-on-card show_preacher="yes"]           include the preacher
 *   [next-on-card amount="4"]                    show up to four cards in email
 *   [next-on-card range="6 weeks"]               show cards within the next six weeks in email
 *   [next-on-card from="next-week" range="2 months" layout="compact"]
 *
 * width accepts a CSS length (px, rem, em, %, vw, ch) or auto / fit-content / max-content.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KCG_Elvanto_Next_On_Card_Shortcode {

    public function register() {
        add_shortcode( 'next-on-card', array( $this, 'render' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function enqueue_assets() {
        KCG_Elvanto_Event_Card::enqueue_assets();
    }

    public function render( $atts = array() ) {
        $atts = shortcode_atts(
            array_merge(
                KCG_Elvanto_Event_Query::filter_defaults( 'all' ),
                array(
                    'align' => '',
                    'width' => '',
                    'show_preacher' => 'no',
                    'amount' => '',
                    'range' => '',
                    'from' => '',
                    'layout' => '',
                    'exclude_service_type' => '',
                )
            ),
            $atts,
            'next-on-card'
        );

        $align = strtolower( trim( (string) $atts['align'] ) );
        $flex  = array( 'left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end' );
        $style = isset( $flex[ $align ] ) ? ' style="display:flex;justify-content:' . $flex[ $align ] . ';"' : '';

        $width = trim( (string) $atts['width'] );
        if ( preg_match( '/^(\d+(\.\d+)?(px|rem|em|%|vw|ch)|auto|fit-content|max-content)$/i', $width ) ) {
            $style = '' === $style ? ' style="' : rtrim( $style, '"' ) . ';';
            $style .= '--kcg-next-on-width:' . $width . ';"';
        }

        if ( KCG_Elvanto_Email_Context::is_email() ) {
            return $this->render_email_cards( $atts, $width, $align );
        }

        $item = KCG_Elvanto_Event_Query::next( $atts );
        if ( ! $item ) {
            return '<div class="kcg-card kcg-next-on-card"><p>' . esc_html( KCG_Elvanto_Event_Query::no_results_message( $atts ) ) . '</p></div>';
        }

        $card = KCG_Elvanto_Event_Card::render(
            $item,
            array(
                'show_description' => false,
                'show_buttons'     => false,
                'show_preacher'    => filter_var( $atts['show_preacher'], FILTER_VALIDATE_BOOLEAN ),
                'card_class'       => 'event-card--compact',
            )
        );

        return '<div class="kcg-next-on-card"' . $style . '>' . $card . '</div>';
    }

    /**
     * Render a stack of email cards using optional amount and date-range constraints.
     */
    private function render_email_cards( $atts, $width, $align ) {
        $amount_raw = trim( (string) $atts['amount'] );
        $amount = null;
        if ( '' !== $amount_raw ) {
            if ( ! ctype_digit( $amount_raw ) || (int) $amount_raw < 1 ) {
                return '<p class="kcg-email-shortcode-error">' . esc_html__( 'The amount attribute must be a positive whole number.', 'kcg-elvanto-shortcodes' ) . '</p>';
            }
            $amount = (int) $amount_raw;
        }

        $today = new DateTimeImmutable( 'today', KCG_Elvanto_Datetime::timezone() );
        $from = trim( (string) $atts['from'] );
        $range_start = $today;
        if ( '' !== $from ) {
            if ( 0 === strcasecmp( $from, 'next-week' ) ) {
                $range_start = $today->modify( 'monday next week' );
            } elseif ( preg_match( '/^([1-9]\d*)\s*(days?|weeks?|months?)$/i', $from, $from_matches ) ) {
                $range_start = $today->modify( '+' . (int) $from_matches[1] . ' ' . strtolower( $from_matches[2] ) );
            } else {
                return '<p class="kcg-email-shortcode-error">' . esc_html__( 'Use a start such as "next-week" or "7 days".', 'kcg-elvanto-shortcodes' ) . '</p>';
            }
        }

        $range_end = null;
        $range = trim( (string) $atts['range'] );
        if ( '' !== $range ) {
            if ( ! preg_match( '/^([1-9]\d*)\s*(days?|weeks?|months?)$/i', $range, $matches ) ) {
                return '<p class="kcg-email-shortcode-error">' . esc_html__( 'Use a range such as "14 days", "6 weeks" or "3 months".', 'kcg-elvanto-shortcodes' ) . '</p>';
            }

            $range_end = $today->modify( '+' . (int) $matches[1] . ' ' . strtolower( $matches[2] ) )->setTime( 23, 59, 59 );
        }

        $query = $atts;
        unset( $query['from'], $query['range'], $query['amount'], $query['layout'] );
        $query['limit'] = 0;
        $items = KCG_Elvanto_Event_Query::get( $query );
        if ( $range_end ) {
            $items = array_values(
                array_filter(
                    $items,
                    static function ( $item ) use ( $range_start, $range_end ) {
                        $datetime = KCG_Elvanto_Event_Query::datetime( $item );
                        return $datetime && $datetime >= $range_start && $datetime <= $range_end;
                    }
                )
            );
        } elseif ( '' !== $from ) {
            $items = array_values(
                array_filter(
                    $items,
                    static function ( $item ) use ( $range_start ) {
                        $datetime = KCG_Elvanto_Event_Query::datetime( $item );
                        return $datetime && $datetime >= $range_start;
                    }
                )
            );
        }

        $limit = null !== $amount ? $amount : ( '' === $range ? 1 : null );
        if ( null !== $limit ) {
            $items = array_slice( $items, 0, $limit );
        }

        if ( empty( $items ) ) {
            return '<p>' . esc_html( KCG_Elvanto_Event_Query::no_results_message( $atts ) ) . '</p>';
        }

        $max_width = '30rem';
        if ( preg_match( '/^(\d+(?:\.\d+)?)(px|rem)$/i', $width, $matches ) ) {
            $max_width = 'px' === strtolower( $matches[2] )
                ? ( (float) $matches[1] / 16 ) . 'rem'
                : $matches[1] . 'rem';
        }
        $table_align = in_array( $align, array( 'left', 'center', 'right' ), true ) ? $align : 'center';

        $output = '<table role="presentation" align="' . esc_attr( $table_align ) . '" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:' . esc_attr( $max_width ) . ';margin:0 auto;">';
        $last_index = count( $items ) - 1;
        foreach ( $items as $index => $item ) {
            $card = KCG_Elvanto_Event_Card::render(
                $item,
                array(
                    'show_description' => false,
                    'show_buttons'     => true,
                    'show_preacher'    => filter_var( $atts['show_preacher'], FILTER_VALIDATE_BOOLEAN ),
                    'layout'           => 'compact' === strtolower( trim( (string) $atts['layout'] ) ) ? 'compact' : 'full',
                )
            );
            $output .= '<tr><td style="padding:0 0 ' . ( $index < $last_index ? '1rem' : '0' ) . ';">' . $card . '</td></tr>';
        }

        return $output . '</table>';
    }
}
