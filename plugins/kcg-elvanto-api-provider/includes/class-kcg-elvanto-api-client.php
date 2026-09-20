<?php
/**
 * Shared Elvanto API client for KCG Elvanto plugins.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class KCG_Elvanto_API_Client {

    private const BASE_URL = 'https://api.elvanto.com/v1/';

    /**
     * Validate the provider and API key.
     *
     * @param array|null $debug_info
     * @return string|WP_Error
     */
    private static function get_api_key_or_error(?array &$debug_info = null) {
        if (!class_exists('KCG_Elvanto_API_Registry')) {
            $error = new WP_Error('elvanto_api_provider_missing', 'Elvanto API provider is not available.');
            if (is_array($debug_info)) {
                $debug_info['error'] = $error->get_error_message();
            }
            return $error;
        }

        $api_key = KCG_Elvanto_API_Registry::get_api_key();
        if (empty($api_key)) {
            $error = new WP_Error('elvanto_api_key_missing', 'Elvanto API key is not configured.');
            if (is_array($debug_info)) {
                $debug_info['error'] = $error->get_error_message();
            }
            return $error;
        }

        return $api_key;
    }

    /**
     * Fetch services from Elvanto.
     *
     * @param string $start_date
     * @param string $end_date
     * @param array  $fields
     * @param array  $debug_info
     * @return array
     */
    public static function fetch_services($start_date, $end_date, array $fields = array(), ?array &$debug_info = null) {
        $body = array(
            'start' => $start_date,
            'end' => $end_date,
        );

        if ( ! empty( $fields ) ) {
            $body['fields'] = $fields;
        }

        return self::fetch_post('services/getAll.php', $body, 'services', 'service', $debug_info);
    }

    /**
     * Fetch events from Elvanto.
     *
     * @param string $start_date
     * @param string $end_date
     * @param array  $fields
     * @param array  $debug_info
     * @return array
     */
    public static function fetch_events($start_date, $end_date, array $fields = array(), ?array &$debug_info = null) {
        $params = array(
            'start' => $start_date,
            'end' => $end_date,
        );

        if ( ! empty( $fields ) ) {
            $params['fields'] = $fields;
        }

        return self::fetch_get('calendar/events/getAll.php', $params, 'events', 'event', $debug_info);
    }

    /**
     * Fetch people from Elvanto.
     *
     * @param array $params
     * @param array|null $debug_info
     * @return array|WP_Error
     */
    public static function fetch_people(array $params = array(), ?array &$debug_info = null) {
        return self::fetch_post('people/getAll.php', $params, 'people', 'person', $debug_info);
    }

    /**
     * Fetch JSON data via a POST request.
     *
     * @param string $endpoint
     * @param array  $body
     * @param string $wrapper_key
     * @param string $item_key
     * @param array  $debug_info
     * @return array
     */
    private static function fetch_post($endpoint, array $body, $wrapper_key, $item_key, ?array &$debug_info = null) {
        $api_key = self::get_api_key_or_error($debug_info);
        if (is_wp_error($api_key)) {
            return $api_key;
        }

        $url = self::BASE_URL . $endpoint;
        $body['output'] = 'php';

        $args = array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($api_key . ':x'),
            ),
            'body' => wp_json_encode($body),
            'timeout' => 30,
        );

        return self::fetch_json($url, $args, $wrapper_key, $item_key, $debug_info, 'POST');
    }

    /**
     * Fetch JSON data via a GET request.
     *
     * @param string $endpoint
     * @param array  $params
     * @param string $wrapper_key
     * @param string $item_key
     * @param array  $debug_info
     * @return array
     */
    private static function fetch_get($endpoint, array $params, $wrapper_key, $item_key, ?array &$debug_info = null) {
        $api_key = self::get_api_key_or_error($debug_info);
        if (is_wp_error($api_key)) {
            return $api_key;
        }

        $params['apikey'] = $api_key;
        $params['output'] = 'php';
        $url = add_query_arg($params, self::BASE_URL . $endpoint);
        $args = array('timeout' => 30);

        return self::fetch_json($url, $args, $wrapper_key, $item_key, $debug_info, 'GET');
    }

    /**
     * Perform the request and normalize the JSON response.
     *
     * @param string $url
     * @param array  $args
     * @param string $wrapper_key
     * @param string $item_key
     * @param array  $debug_info
     * @param string $method
     * @return array
     */
    private static function fetch_json($url, array $args, $wrapper_key, $item_key, ?array &$debug_info = null, $method = 'GET') {
        if (is_array($debug_info)) {
            $debug_info['request'] = array(
                'method' => $method,
                'url' => $url,
                'args' => $args,
            );
        }

        $response = $method === 'POST' ? wp_remote_post($url, $args) : wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log(sprintf('[KCG Elvanto API] %s %s -> request failed: %s', strtoupper($method), $wrapper_key, $error_message));
            if (is_array($debug_info)) {
                $debug_info['response'] = array('error' => $error_message);
            }
            return new WP_Error('elvanto_request_failed', $error_message);
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = self::decode_response_body($body);

        if (null === $data) {
            $error_message = 'Unable to parse Elvanto response: unsupported response format.';
            error_log(sprintf('[KCG Elvanto API] %s %s -> invalid response payload: %s', strtoupper($method), $wrapper_key, $error_message));
            if (is_array($debug_info)) {
                $debug_info['response'] = array('error' => $error_message, 'body' => $body);
            }
            return new WP_Error('elvanto_invalid_response', $error_message);
        }

        if (is_array($debug_info)) {
            $debug_info['response'] = array(
                'http_code' => $response_code,
                'body' => $data,
            );
        }

        if (isset($data['error'])) {
            $error_message = is_string($data['error']) ? $data['error'] : wp_json_encode($data['error']);
            if (is_array($debug_info)) {
                $debug_info['api_error'] = $data['error'];
            }
            error_log(sprintf('[KCG Elvanto API] %s %s -> API returned an error: %s', strtoupper($method), $wrapper_key, $error_message));
            return new WP_Error('elvanto_api_error', $error_message);
        }

        $normalized = self::normalize_pagination_response($data, $wrapper_key, $item_key, $debug_info);

        return $normalized;
    }

    /**
     * Convert nested objects from Elvanto PHP responses into arrays recursively.
     *
     * @param mixed $value
     * @return mixed
     */
    private static function convert_to_array($value) {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::convert_to_array($item);
            }
            return $value;
        }

        if (is_object($value)) {
            $converted = array();
            foreach ((array) $value as $key => $item) {
                $converted[$key] = self::convert_to_array($item);
            }
            return $converted;
        }

        return $value;
    }

    /**
     * Decode Elvanto responses as PHP-serialized data, with JSON fallback for safety.
     *
     * @param string $body
     * @return array|null
     */
    private static function decode_response_body($body) {
        if (!is_string($body)) {
            return null;
        }

        $trimmed = trim($body);
        if ('' === $trimmed) {
            return null;
        }

        $php_decoded = @unserialize($trimmed);
        if (is_array($php_decoded) || is_object($php_decoded)) {
            return self::convert_to_array($php_decoded);
        }

        $json_decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json_decoded)) {
            return $json_decoded;
        }

        $maybe_serialized = maybe_unserialize($trimmed);
        if (is_array($maybe_serialized) || is_object($maybe_serialized)) {
            return self::convert_to_array($maybe_serialized);
        }

        return null;
    }

    /**
     * Normalize a pagination-wrapped Elvanto response.
     *
     * @param array $response
     * @param string $wrapper_key
     * @param string $item_key
     * @param array  $debug_info
     * @return array
     */
    private static function normalize_pagination_response(array $response, $wrapper_key, $item_key, ?array &$debug_info = null) {
        $response = self::convert_to_array($response);
        $result = array();
        $wrapper = isset($response[$wrapper_key]) ? $response[$wrapper_key] : array();

        if (isset($wrapper[$item_key]) && is_array($wrapper[$item_key])) {
            $result = $wrapper[$item_key];
        } elseif (is_array($wrapper) && !isset($wrapper[$item_key])) {
            $result = $wrapper;
        }

        if (is_array($debug_info)) {
            $debug_info['normalized'] = array(
                'wrapper_key' => $wrapper_key,
                'item_key' => $item_key,
                'items_count' => count($result),
                'wrapper_keys' => is_array($wrapper) ? array_keys($wrapper) : array(),
            );
        }

        return $result;
    }
}
