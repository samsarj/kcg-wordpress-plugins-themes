<?php
/**
 * Shared querying of the provider's merged_events for every display shortcode.
 *
 * Shortcodes share these filter attributes (comma-separated lists are allowed, matching is
 * case-insensitive, and all supplied filters must match):
 *
 *   type          Service type OR calendar name. Use this when you do not care which it is.
 *   service_type  Service type only (e.g. "Sunday Service").
 *   calendar      Calendar name only (e.g. "Youth").
 *   source        "service", "event" or "all" (default depends on the shortcode).
 *   limit         Maximum number of items (0 = no limit).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KCG_Elvanto_Event_Query {

    /** Filter attribute defaults shared by all shortcodes. */
    public static function filter_defaults( $source = 'all' ) {
        return array(
            'type'         => '',
            'service_type' => '',
            'calendar'     => '',
            'source'       => $source,
            'limit'        => 10,
        );
    }

    /** True when the shortcode attributes narrow the results by type, service type or calendar. */
    public static function has_type_filter( array $args ) {
        return '' !== trim( (string) ( $args['type'] ?? '' ) )
            || '' !== trim( (string) ( $args['service_type'] ?? '' ) )
            || '' !== trim( (string) ( $args['calendar'] ?? '' ) );
    }

    /**
     * Get merged_events matching the filter attributes, in chronological order.
     *
     * @param array $args See the class docblock.
     * @return array[]
     */
    public static function get( array $args = array() ) {
        if ( ! class_exists( 'KCG_Elvanto_Cache' ) ) {
            return array();
        }

        $type         = self::to_list( $args['type'] ?? '' );
        $service_type = self::to_list( $args['service_type'] ?? '' );
        $calendar     = self::to_list( $args['calendar'] ?? '' );
        $source       = strtolower( trim( (string) ( $args['source'] ?? 'all' ) ) );

        $items = array_filter(
            KCG_Elvanto_Cache::get_merged_events(),
            function ( $item ) use ( $type, $service_type, $calendar, $source ) {
                if ( ! is_array( $item ) ) {
                    return false;
                }
                if ( in_array( $source, array( 'service', 'event' ), true ) && ( $item['source'] ?? '' ) !== $source ) {
                    return false;
                }
                if ( $type && ! self::matches( $type, array( $item['service_type'] ?? '', $item['calendar_name'] ?? '' ) ) ) {
                    return false;
                }
                if ( $service_type && ! self::matches( $service_type, array( $item['service_type'] ?? '' ) ) ) {
                    return false;
                }
                if ( $calendar && ! self::matches( $calendar, array( $item['calendar_name'] ?? '' ) ) ) {
                    return false;
                }
                return true;
            }
        );

        $items = array_values( $items );
        $limit = (int) ( $args['limit'] ?? 0 );

        return $limit > 0 ? array_slice( $items, 0, $limit ) : $items;
    }

    /** The first (soonest) item matching the filter attributes, or null. */
    public static function next( array $args = array() ) {
        $args['limit'] = 1;
        $items         = self::get( $args );
        return $items ? $items[0] : null;
    }

    /** Human-readable "nothing found" message naming whatever the user filtered on. */
    public static function no_results_message( array $args ) {
        $label = trim( implode( ' / ', array_filter( array(
            trim( (string) ( $args['type'] ?? '' ) ),
            trim( (string) ( $args['service_type'] ?? '' ) ),
            trim( (string) ( $args['calendar'] ?? '' ) ),
        ) ) ) );

        return '' === $label
            ? 'No dates are currently scheduled.'
            : 'No ' . $label . ' dates are currently scheduled.';
    }

    /** Local date and time of an item as a DateTime in the display timezone, or null. */
    public static function datetime( array $item ) {
        return KCG_Elvanto_Datetime::from_local( $item['date'] ?? '', $item['time'] ?? '' );
    }

    private static function to_list( $value ) {
        $list = array_map( 'trim', explode( ',', (string) $value ) );
        return array_map( 'strtolower', array_values( array_filter( $list, 'strlen' ) ) );
    }

    private static function matches( array $wanted, array $candidates ) {
        foreach ( $candidates as $candidate ) {
            if ( in_array( strtolower( trim( (string) $candidate ) ), $wanted, true ) ) {
                return true;
            }
        }
        return false;
    }
}
