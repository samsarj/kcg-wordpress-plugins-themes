<?php
/**
 * [next-on-card] shortcode: the next upcoming service or event as a compact event card
 * (the [elvanto_swiper] card without the description and buttons).
 *
 * Examples:
 *   [next-on-card type="Sunday Service"]        service type or calendar name
 *   [next-on-card service_type="Prayer Night"]  service type only
 *   [next-on-card calendar="Youth" align="center" width="20rem"]
 *
 * width accepts a CSS length (px, rem, em, %, vw, ch) or auto / fit-content / max-content.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KCG_Elvanto_Next_On_Card_Shortcode {

    const DEFAULT_TYPE = 'Sunday Service';

    public function register() {
        add_shortcode( 'next-on-card', array( $this, 'render' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function enqueue_assets() {
        KCG_Elvanto_Event_Card::enqueue_assets();
    }

    public function render( $atts = array() ) {
        $atts = shortcode_atts(
            array_merge( KCG_Elvanto_Event_Query::filter_defaults( 'all' ), array( 'align' => '', 'width' => '' ) ),
            $atts,
            'next-on-card'
        );

        // Keep [next-on-card] working with no attributes.
        if ( ! KCG_Elvanto_Event_Query::has_type_filter( $atts ) ) {
            $atts['type'] = self::DEFAULT_TYPE;
        }

        $align = strtolower( trim( (string) $atts['align'] ) );
        $flex  = array( 'left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end' );
        $style = isset( $flex[ $align ] ) ? ' style="display:flex;justify-content:' . $flex[ $align ] . ';"' : '';

        $width = trim( (string) $atts['width'] );
        if ( preg_match( '/^(\d+(\.\d+)?(px|rem|em|%|vw|ch)|auto|fit-content|max-content)$/i', $width ) ) {
            $style = '' === $style ? ' style="' : rtrim( $style, '"' ) . ';';
            $style .= '--kcg-next-on-width:' . $width . ';"';
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
                'card_class'       => 'event-card--compact',
            )
        );

        return '<div class="kcg-next-on-card"' . $style . '>' . $card . '</div>';
    }
}
