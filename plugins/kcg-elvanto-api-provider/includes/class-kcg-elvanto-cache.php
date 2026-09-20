<?php
/**
 * Shared Elvanto cache and scheduled refresh coordinator.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KCG_Elvanto_Cache {

    const SERVICES_TRANSIENT = 'kcg_elvanto_provider_services';
    const EVENTS_TRANSIENT = 'kcg_elvanto_provider_events';
    const PEOPLE_TRANSIENT = 'kcg_elvanto_provider_people';
    const META_OPTION = 'kcg_elvanto_provider_cache_meta';

    const SERVICES_TTL = 30 * MINUTE_IN_SECONDS;
    const EVENTS_TTL = 30 * MINUTE_IN_SECONDS;
    const PEOPLE_TTL = 2 * HOUR_IN_SECONDS;
    const SERVICES_RANGE_DAYS = 365;
    const EVENTS_RANGE_DAYS = 30;

    /**
     * Register background refresh hooks and ensure a schedule exists.
     */
    public static function init() {
        if ( ! wp_next_scheduled( 'kcg_elvanto_provider_refresh' ) ) {
            wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'hourly', 'kcg_elvanto_provider_refresh' );
        }

        if ( ! has_action( 'kcg_elvanto_provider_refresh', array( __CLASS__, 'refresh_all' ) ) ) {
            add_action( 'kcg_elvanto_provider_refresh', array( __CLASS__, 'refresh_all' ) );
        }
    }

    /**
     * Activation bootstrap for the provider plugin.
     */
    public static function activate() {
        self::init();
        self::refresh_all();
    }

    /**
     * Deactivation cleanup.
     */
    public static function deactivate() {
        $timestamp = wp_next_scheduled( 'kcg_elvanto_provider_refresh' );
        if ( $timestamp ) {
            wp_clear_scheduled_hook( 'kcg_elvanto_provider_refresh' );
        }
    }

    /**
     * Refresh all cached Elvanto datasets.
     *
     * @param bool $force
     * @return array
     */
    public static function refresh_all( $force = false ) {
        $results = array(
            'services' => self::refresh_services( $force ),
            'events' => self::refresh_events( $force ),
            'people' => self::refresh_people( $force ),
            'timestamp' => current_time( 'mysql' ),
        );

        $meta = get_option( self::META_OPTION, array() );
        $meta['last_refresh'] = current_time( 'mysql' );
        $meta['status'] = 'success';
        $meta['results'] = $results;
        update_option( self::META_OPTION, $meta );

        return $results;
    }

    /**
     * Fetch and cache the services dataset.
     *
     * @param bool $force
     * @return bool
     */
    public static function refresh_services( $force = false ) {
        return self::refresh_dataset(
            'services',
            self::SERVICES_TRANSIENT,
            self::SERVICES_TTL,
            function () {
                $start_date = current_time( 'Y-m-d' );
                $end_date = date_i18n( 'Y-m-d', strtotime( '+' . self::SERVICES_RANGE_DAYS . ' days' ) );
                $fields = array( 'series_name', 'volunteers', 'picture' );

                return KCG_Elvanto_API_Client::fetch_services( $start_date, $end_date, $fields );
            },
            $force,
            true
        );
    }

    /**
     * Fetch and cache the events dataset.
     *
     * @param bool $force
     * @return bool
     */
    public static function refresh_events( $force = false ) {
        return self::refresh_dataset(
            'events',
            self::EVENTS_TRANSIENT,
            self::EVENTS_TTL,
            function () {
                $start_date = current_time( 'Y-m-d' );
                $end_date = date_i18n( 'Y-m-d', strtotime( '+' . self::EVENTS_RANGE_DAYS . ' days' ) );

                return KCG_Elvanto_API_Client::fetch_events( $start_date, $end_date, array() );
            },
            $force
        );
    }

    /**
     * Fetch and cache the people dataset.
     *
     * @param bool $force
     * @return bool
     */
    public static function refresh_people( $force = false ) {
        return self::refresh_dataset(
            'people',
            self::PEOPLE_TRANSIENT,
            self::PEOPLE_TTL,
            function () {
                return KCG_Elvanto_API_Client::fetch_people();
            },
            $force
        );
    }

    private static function refresh_dataset( $key, $transient_name, $ttl, callable $fetcher, $force = false, $log_errors = false ) {
        if ( ! $force && ! self::needs_refresh( $transient_name, $ttl ) ) {
            return true;
        }

        if ( ! class_exists( 'KCG_Elvanto_API_Client' ) ) {
            return false;
        }

        $payload = $fetcher();
        if ( is_wp_error( $payload ) || ! is_array( $payload ) ) {
            $details = is_wp_error( $payload ) ? $payload->get_error_message() : 'Invalid payload';
            if ( $log_errors ) {
                error_log( '[KCG Elvanto Cache] refresh_' . $key . ' failed: ' . $details );
            }
            self::store_meta_status( $key, 'error', array( 'details' => $details ) );
            return false;
        }

        set_transient( $transient_name, $payload, $ttl );
        update_option( $transient_name, $payload );
        self::store_meta_status( $key, 'success', array( 'count' => count( $payload ) ) );

        return true;
    }

    /**
     * Get the cached services payload.
     */
    public static function get_services() {
        $services = get_transient( self::SERVICES_TRANSIENT );

        if ( false === $services ) {
            self::refresh_services( true );
            $services = get_transient( self::SERVICES_TRANSIENT );
        } else {
            $services = is_array( $services ) ? $services : array();
            if ( ! self::services_have_required_fields( $services ) ) {
                self::refresh_services( true );
                $services = get_transient( self::SERVICES_TRANSIENT );
            }
        }

        $services = is_array( $services ) ? $services : array();

        return $services;
    }

    /**
     * Determine whether the cached service payload includes the fields required by the frontend.
     *
     * @param array $services
     * @return bool
     */
    private static function services_have_required_fields( array $services ) {
        if ( empty( $services ) ) {
            return true;
        }

        $has_series = false;
        $has_volunteers = false;

        foreach ( $services as $service ) {
            if ( ! is_array( $service ) ) {
                continue;
            }

            if ( ! empty( $service['series_name'] ) || ! empty( $service['series']['name'] ) || ! empty( $service['service_type']['name'] ) ) {
                $has_series = true;
            }

            if ( ! empty( $service['volunteers'] ) || ! empty( $service['preacher'] ) || ! empty( $service['speaker'] ) ) {
                $has_volunteers = true;
            }
        }

        return $has_series && $has_volunteers;
    }

    /**
     * Get the cached events payload.
     */
    public static function get_events() {
        $events = get_transient( self::EVENTS_TRANSIENT );

        if ( false === $events ) {
            self::refresh_events();
            $events = get_transient( self::EVENTS_TRANSIENT );
        }

        return is_array( $events ) ? $events : array();
    }

    /**
     * Get the cached people payload.
     */
    public static function get_people() {
        $people = get_transient( self::PEOPLE_TRANSIENT );

        if ( false === $people ) {
            self::refresh_people();
            $people = get_transient( self::PEOPLE_TRANSIENT );
        }

        return is_array( $people ) ? $people : array();
    }

    /**
     * Persist lightweight status metadata for the provider cache.
     *
     * @param string $key
     * @param string $status
     * @param array $extra
     */
    private static function store_meta_status( $key, $status, array $extra = array() ) {
        $meta = get_option( self::META_OPTION, array() );
        $meta[ $key ] = array_merge(
            array(
                'status' => $status,
                'updated_at' => current_time( 'mysql' ),
            ),
            $extra
        );
        update_option( self::META_OPTION, $meta );
    }

    /**
     * Determine whether the given cache entry should be refreshed.
     *
     * @param string $transient_name
     * @param int $ttl_seconds
     * @return bool
     */
    private static function needs_refresh( $transient_name, $ttl_seconds ) {
        $cached = get_transient( $transient_name );
        if ( false === $cached ) {
            return true;
        }

        $meta = get_option( self::META_OPTION, array() );
        $key = str_replace( 'kcg_elvanto_provider_', '', $transient_name );
        $updated = ! empty( $meta[ $key ]['updated_at'] ) ? strtotime( $meta[ $key ]['updated_at'] ) : 0;

        if ( ! $updated ) {
            return true;
        }

        return ( time() - $updated ) > $ttl_seconds;
    }

    /**
     * Force-refresh the provider cache immediately, bypassing cron and transient staleness.
     *
     * @return array
     */
    public static function refresh_all_override() {
        return self::refresh_all( true );
    }
}
