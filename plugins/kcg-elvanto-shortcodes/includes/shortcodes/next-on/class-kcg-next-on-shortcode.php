<?php
/**
 * [next-on] shortcode: the next upcoming service or event.
 *
 * Examples:
 *   [next-on type="Sunday Service"]        service type or calendar name
 *   [next-on service_type="Prayer Night"]  service type only
 *   [next-on calendar="Youth" align="center"]
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KCG_Elvanto_Next_On_Shortcode {

    const DEFAULT_TYPE = 'Sunday Service';

    public function register() {
        add_shortcode( 'next-on', array( $this, 'render' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function enqueue_assets() {
        $path = __DIR__ . '/next-on.css';
        wp_enqueue_style( 'kcg-next-on', plugin_dir_url( __FILE__ ) . 'next-on.css', array(), filemtime( $path ) );
    }

    public function render( $atts = array() ) {
        $atts = shortcode_atts(
            array_merge( KCG_Elvanto_Event_Query::filter_defaults( 'all' ), array( 'align' => '' ) ),
            $atts,
            'next-on'
        );

        // Keep [next-on] working with no attributes.
        if ( ! KCG_Elvanto_Event_Query::has_type_filter( $atts ) ) {
            $atts['type'] = self::DEFAULT_TYPE;
        }

        $align = strtolower( trim( (string) $atts['align'] ) );
        $style = in_array( $align, array( 'left', 'center', 'right', 'justify' ), true )
            ? ' style="text-align:' . esc_attr( $align ) . ';"'
            : '';

        $item = KCG_Elvanto_Event_Query::next( $atts );
        if ( ! $item ) {
            return '<div class="kcg-next-on"' . $style . '><p>' . esc_html( KCG_Elvanto_Event_Query::no_results_message( $atts ) ) . '</p></div>';
        }

        $title = trim( (string) ( $item['title'] ?? '' ) );
        if ( '' === $title ) {
            $title = trim( (string) ( $item['service_type'] ?: $item['calendar_name'] ) );
        }

        $dt    = KCG_Elvanto_Event_Query::datetime( $item );
        $parts = array();
        if ( $dt ) {
            if ( ! empty( $item['time'] ) ) {
                $parts[] = wp_date( get_option( 'time_format' ), $dt->getTimestamp(), $dt->getTimezone() );
            }
            $parts[] = wp_date( get_option( 'date_format' ), $dt->getTimestamp(), $dt->getTimezone() );
        }
        $parts[] = '' !== trim( (string) $item['location'] ) ? $item['location'] : 'Location TBC';

        $output  = '<div class="kcg-next-on"' . $style . '><p>' . esc_html( $title );
        if ( '' !== trim( (string) $item['subtitle'] ) ) {
            $output .= ' | ' . esc_html( $item['subtitle'] );
        }
        $output .= '</p><p style="font-size: 0.8em;">' . esc_html( implode( ' | ', $parts ) ) . '</p></div>';

        return $output;
    }
}
