<?php
/**
 * Shared date/time handling for Elvanto data.
 *
 * Elvanto sends "Y-m-d H:i:s" in UTC, or "Y-m-d" for all-day items.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KCG_Elvanto_Datetime {

    public static function timezone() {
        $site_timezone = wp_timezone();
        return 'UTC' !== $site_timezone->getName() ? $site_timezone : new DateTimeZone( 'Europe/London' );
    }

    /**
     * Parse an Elvanto date into a DateTime in the display timezone, or null if invalid.
     * Date-only values are treated as local midnight.
     */
    public static function parse( $raw ) {
        $raw = trim( (string) $raw );

        $utc = DateTime::createFromFormat( 'Y-m-d H:i:s', $raw, new DateTimeZone( 'UTC' ) );
        if ( $utc instanceof DateTime ) {
            return $utc->setTimezone( self::timezone() );
        }

        $local = DateTime::createFromFormat( '!Y-m-d', $raw, self::timezone() );
        return $local instanceof DateTime ? $local : null;
    }

    public static function has_time( $raw ) {
        return strlen( trim( (string) $raw ) ) > 10;
    }

    public static function timestamp( $raw ) {
        static $memo = array();

        $raw = (string) $raw;
        if ( ! array_key_exists( $raw, $memo ) ) {
            $dt = self::parse( $raw );
            $memo[ $raw ] = $dt ? $dt->getTimestamp() : null;
        }

        return $memo[ $raw ];
    }

    /**
     * Build a DateTime from already-localised "Y-m-d" and optional "H:i:s" strings.
     */
    public static function from_local( $date, $time = '' ) {
        $date = trim( (string) $date );
        $time = trim( (string) $time );
        if ( '' === $date ) {
            return null;
        }

        $dt = '' !== $time
            ? DateTime::createFromFormat( 'Y-m-d H:i:s', $date . ' ' . $time, self::timezone() )
            : DateTime::createFromFormat( '!Y-m-d', $date, self::timezone() );

        return $dt instanceof DateTime ? $dt : null;
    }
}
