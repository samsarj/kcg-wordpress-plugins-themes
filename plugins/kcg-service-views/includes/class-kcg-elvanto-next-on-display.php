<?php
/**
 * Next-on display logic for the shared Elvanto service views plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'kcg_get_next_service_by_type' ) ) {
    require_once __DIR__ . '/helpers.php';
}

class KCG_Elvanto_Next_On_Display {

    public function register_shortcodes() {
        add_shortcode( 'next-on', array( $this, 'render_next_on_shortcode' ) );
    }

    public function render_next_on_shortcode( $atts = array() ) {
        $atts = shortcode_atts(
            array(
                'type' => 'Sunday Service',
                'align' => '',
            ),
            $atts,
            'next-on'
        );

        $service_type = (string) $atts['type'];
        $align = strtolower( trim( (string) $atts['align'] ) );
        $alignment_style = '';

        if ( in_array( $align, array( 'left', 'center', 'right', 'justify' ), true ) ) {
            $alignment_style = ' style="text-align:' . esc_attr( $align ) . ';"';
        }

        if ( '' === $service_type ) {
            return '';
        }

        $service = kcg_get_next_service_by_type( $service_type );
        if ( ! $service ) {
            return '<div class="kcg-next-on"' . $alignment_style . '><p>' . esc_html( kcg_elvanto_no_service_dates_message( $service_type ) ) . '</p></div>';
        }

        $service_name = trim( (string) ( $service['name'] ?? $service['service_type']['name'] ?? $service_type ) );
        if ( '' === $service_name ) {
            $service_name = $service_type;
        }

        $service_title = '';
        if ( ! empty( $service['series_name'] ) ) {
            $service_title = trim( (string) ( $service['series_name'] ?? '' ) );
        }


        $date_obj = KCG_Elvanto_Datetime::parse( $service['date'] );

        $location = '';
        if ( ! empty( $service['location'] ) ) {
            if ( is_array( $service['location'] ) ) {
                $location = trim( (string) ( $service['location']['name'] ?? '' ) );
            } else {
                $location = trim( (string) $service['location'] );
            }
        }

        $timestamp = $date_obj->getTimestamp();
        $time_display = wp_date( get_option( 'time_format' ), $timestamp, $date_obj->getTimezone() );
        $date_display = wp_date( get_option( 'date_format' ), $timestamp, $date_obj->getTimezone() );
        $location_display = '' !== $location ? $location : 'Location TBC';

        $output = '<div class="kcg-next-on"' . $alignment_style . '>';
        $output .= '<p>';
        $output .= esc_html( $service_name );
        if ( '' !== $service_title ) {
            $output .= ' | ' . esc_html( $service_title );
        }
        $output .= '</p>';
        $output .= '<p style="font-size: 0.8em;">' . esc_html( $time_display . ' | ' . $date_display . ' | ' . $location_display ) . '</p>';
        $output .= '</div>';
        return $output;
    }
}
