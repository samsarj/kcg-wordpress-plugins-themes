<?php
/**
 * Merges Elvanto services and calendar events into a single "merged_events" list.
 *
 * Every merged item has the same shape, whatever its source:
 *
 *   id             string  Elvanto service / event id
 *   source         string  'service' or 'event'
 *   title          string  service.name            | event.name
 *   subtitle       string  service.series_name     | (none)
 *   description    string  service.description    | event.description
 *   date           string  local "Y-m-d"           (service.date | event.start_date)
 *   time           string  local "H:i:s", omitted for all-day items
 *   all_day        mixed   (none)                  | event.all_day
 *   location       string  service.location.name   | event.where
 *   picture        string  service.picture         | event.picture
 *   color          string  matching event colour, else calendar colour, else default (services)
 *   link_info      string  configured service-type link | event.url
 *   link_register  string  (none)                  | event.register_url
 *   service_type   string  service.service_type.name | '' for events
 *   calendar_id    string  event.calendar_id, or that of an event sharing the service's id
 *   calendar_name  string  name of the calendar identified by calendar_id
 *   volunteers     array   service.volunteers (used to find the preacher)
 *
 * An event whose id equals a service id is the calendar entry for that service: it is dropped
 * in favour of the service and only lends it a colour and a calendar.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KCG_Elvanto_Event_Merger {

    /** Option holding service type => "More Info" URL, edited on the shortcodes settings page. */
    const SERVICE_LINKS_OPTION = 'elvanto_swiper_service_links';

    const DEFAULT_SERVICE_COLOR = '#2e7d32';

    /**
     * @param array $events    Raw events from the API.
     * @param array $services  Raw services from the API.
     * @param array $calendars Raw calendars from the API.
     * @return array[] Merged items sorted by date and time.
     */
    public static function merge( $events, $services, $calendars ) {
        $events    = is_array( $events ) ? $events : array();
        $services  = is_array( $services ) ? $services : array();
        $calendars = is_array( $calendars ) ? $calendars : array();

        $calendars_by_id = self::index_by_id( $calendars );
        $events_by_id    = self::index_by_id( $events );
        $service_links   = (array) get_option( self::SERVICE_LINKS_OPTION, array() );

        $merged       = array();
        $service_ids  = array();

        foreach ( $services as $service ) {
            $item = self::map_service( $service, $events_by_id, $calendars_by_id, $service_links );
            if ( $item ) {
                $merged[]      = $item;
                $service_ids[] = $item['id'];
            }
        }

        foreach ( $events as $event ) {
            if ( ! is_array( $event ) || empty( $event['id'] ) || in_array( $event['id'], $service_ids, true ) ) {
                continue;
            }
            $merged[] = self::map_event( $event, $calendars_by_id );
        }

        // Local "Y-m-d" and "H:i:s" strings sort chronologically; all-day items sort first on their day.
        usort(
            $merged,
            function ( $a, $b ) {
                return strcmp(
                    ( $a['date'] ?? '' ) . ' ' . ( $a['time'] ?? '00:00:00' ),
                    ( $b['date'] ?? '' ) . ' ' . ( $b['time'] ?? '00:00:00' )
                );
            }
        );

        return $merged;
    }

    private static function map_service( $service, array $events_by_id, array $calendars_by_id, array $service_links ) {
        if ( ! is_array( $service ) || empty( $service['id'] ) ) {
            return null;
        }

        $id           = $service['id'];
        $service_type = trim( (string) ( $service['service_type']['name'] ?? '' ) );
        $twin_event   = $events_by_id[ $id ] ?? null; // The calendar entry for this service, if any.
        $calendar     = self::calendar_for( $twin_event, $calendars_by_id );

        $item = array(
            'id'            => $id,
            'source'        => 'service',
            'title'         => (string) ( $service['name'] ?? '' ),
            'subtitle'      => (string) ( $service['series_name'] ?? '' ),
            'description'   => (string) ( $service['description'] ?? '' ),
            'location'      => (string) ( $service['location']['name'] ?? '' ),
            'picture'       => (string) ( $service['picture'] ?? '' ),
            'color'         => ! empty( $twin_event['color'] ) ? $twin_event['color'] : self::DEFAULT_SERVICE_COLOR,
            'link_info'     => self::service_link( $service, $service_type, $service_links ),
            'service_type'  => $service_type,
            'calendar_id'   => (string) ( $twin_event['calendar_id'] ?? '' ),
            'calendar_name' => (string) ( $calendar['name'] ?? '' ),
            'volunteers'    => $service['volunteers'] ?? array(),
        );

        return array_merge( $item, self::split_date( $service['date'] ?? '' ) );
    }

    private static function map_event( array $event, array $calendars_by_id ) {
        $calendar = self::calendar_for( $event, $calendars_by_id );

        $item = array(
            'id'            => $event['id'],
            'source'        => 'event',
            'title'         => (string) ( $event['name'] ?? '' ),
            'subtitle'      => '',
            'description'   => (string) ( $event['description'] ?? '' ),
            'location'      => (string) ( $event['where'] ?? '' ),
            'picture'       => (string) ( $event['picture'] ?? '' ),
            'color'         => (string) ( ! empty( $event['color'] ) ? $event['color'] : ( $calendar['color'] ?? '' ) ),
            'link_info'     => (string) ( $event['url'] ?? '' ),
            'link_register' => (string) ( $event['register_url'] ?? '' ),
            'service_type'  => '',
            'calendar_id'   => (string) ( $event['calendar_id'] ?? '' ),
            'calendar_name' => (string) ( $calendar['name'] ?? '' ),
        );

        if ( isset( $event['all_day'] ) ) {
            $item['all_day'] = $event['all_day'];
        }

        return array_merge( $item, self::split_date( $event['start_date'] ?? '' ) );
    }

    /**
     * Pick the "More Info" link for a service: by service type, then by series name,
     * then by any configured type that appears in the service's name.
     */
    private static function service_link( array $service, $service_type, array $service_links ) {
        foreach ( array( $service_type, (string) ( $service['series_name'] ?? '' ) ) as $key ) {
            if ( '' !== $key && isset( $service_links[ $key ] ) ) {
                return (string) $service_links[ $key ];
            }
        }

        $name = (string) ( $service['name'] ?? '' );
        if ( '' !== $name ) {
            foreach ( $service_links as $configured_type => $url ) {
                if ( false !== strpos( $name, (string) $configured_type ) ) {
                    return (string) $url;
                }
            }
        }

        return '';
    }

    private static function calendar_for( $event, array $calendars_by_id ) {
        $calendar_id = is_array( $event ) ? ( $event['calendar_id'] ?? '' ) : '';
        return '' !== $calendar_id ? ( $calendars_by_id[ $calendar_id ] ?? null ) : null;
    }

    /**
     * Split an Elvanto date into local 'date' and (unless all-day) 'time' strings.
     */
    private static function split_date( $raw ) {
        $dt = KCG_Elvanto_Datetime::parse( $raw );
        if ( ! $dt ) {
            return array( 'date' => trim( (string) $raw ) );
        }

        $parts = array( 'date' => $dt->format( 'Y-m-d' ) );
        if ( KCG_Elvanto_Datetime::has_time( $raw ) ) {
            $parts['time'] = $dt->format( 'H:i:s' );
        }

        return $parts;
    }

    private static function index_by_id( array $items ) {
        $indexed = array();
        foreach ( $items as $item ) {
            if ( is_array( $item ) && ! empty( $item['id'] ) ) {
                $indexed[ $item['id'] ] = $item;
            }
        }
        return $indexed;
    }
}
