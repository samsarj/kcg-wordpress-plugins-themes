<?php
/**
 * Next-on display logic for the shared Elvanto service views plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'kcg_elvanto_parse_service_datetime' ) ) {
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

        $service = $this->get_next_service( $service_type );
        if ( empty( $service ) ) {
            return '';
        }

        $service_name = trim( (string) ( $service['name'] ?? $service['service_type']['name'] ?? $service_type ) );
        if ( '' === $service_name ) {
            $service_name = $service_type;
        }

        $service_title = '';
        if ( ! empty( $service['series_name'] ) ) {
            $service_title = trim( (string) ( $service['series_name'] ?? '' ) );
        }


        $service_date = trim( (string) ( $service['date'] ?? '' ) );
        if ( '' === $service_date ) {
            return '<div class="kcg-next-on"><h3>' . esc_html( $service_name ) . '</h3></div>';
        }

        $date_obj = $this->get_service_datetime( $service );
        if ( ! $date_obj ) {
            return '<div class="kcg-next-on"><h3>' . esc_html( $service_name ) . '</h3></div>';
        }

        $location = '';
        if ( ! empty( $service['location'] ) ) {
            if ( is_array( $service['location'] ) ) {
                $location = trim( (string) ( $service['location']['name'] ?? '' ) );
            } else {
                $location = trim( (string) $service['location'] );
            }
        }

        $time_display = $date_obj->format( 'g:ia' );
        $date_display = $date_obj->format( 'D jS M' );
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

    private function get_service_datetime( $service ) {
        if ( ! is_array( $service ) ) {
            return null;
        }

        $raw_value = trim( (string) ( $service['date'] ?? $service['start_date'] ?? '' ) );
        if ( '' === $raw_value ) {
            return null;
        }

        return kcg_elvanto_parse_service_datetime( $raw_value );
    }

    private function get_next_service( $service_type ) {
        if ( ! class_exists( 'KCG_Elvanto_Cache' ) ) {
            return array();
        }

        $requested_type = (string) $service_type;
        if ( '' === $requested_type ) {
            return array();
        }

        $services = KCG_Elvanto_Cache::get_services();
        if ( empty( $services ) ) {
            return array();
        }

        $matches = array();
        $now = current_time( 'timestamp' );

        foreach ( $services as $service ) {
            if ( ! is_array( $service ) ) {
                continue;
            }

            $service_date = trim( (string) ( $service['date'] ?? '' ) );
            if ( '' === $service_date ) {
                continue;
            }

            $service_ts = $this->get_service_datetime( $service );
            if ( ! $service_ts || $service_ts->getTimestamp() < $now ) {
                continue;
            }

            $service_type_name = (string) ( $service['service_type']['name'] ?? '' );
            if ( $service_type_name === $requested_type ) {
                $matches[] = $service;
            }
        }

        if ( empty( $matches ) ) {
            return array();
        }

        usort(
            $matches,
            function ( $a, $b ) {
                $ts_a = $this->get_service_datetime( $a );
                $ts_b = $this->get_service_datetime( $b );
                if ( ! $ts_a || ! $ts_b ) {
                    return 0;
                }
                return $ts_a->getTimestamp() <=> $ts_b->getTimestamp();
            }
        );

        $selected = $matches[0];

        return $selected;
    }
}
