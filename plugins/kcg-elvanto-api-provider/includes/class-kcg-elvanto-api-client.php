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
    private static function get_api_key_or_error(array &$debug_info = null) {
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
    public static function fetch_services($start_date, $end_date, array $fields = array(), array &$debug_info = null) {
        $body = array(
            'start' => $start_date,
            'end' => $end_date,
            'fields' => $fields,
        );

        return self::fetch_post('services/getAll.json', $body, 'services', 'service', $debug_info);
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
    public static function fetch_events($start_date, $end_date, array $fields = array(), array &$debug_info = null) {
        $params = array(
            'start' => $start_date,
            'end' => $end_date,
            'fields' => $fields,
        );

        return self::fetch_get('calendar/events/getAll.json', $params, 'events', 'event', $debug_info);
    }

    /**
     * Fetch people from Elvanto.
     *
     * @param array $params
     * @param array|null $debug_info
     * @return array|WP_Error
     */
    public static function fetch_people(array $params = array(), array &$debug_info = null) {
        return self::fetch_post('people/getAll.json', $params, 'people', 'person', $debug_info);
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
    private static function fetch_post($endpoint, array $body, $wrapper_key, $item_key, array &$debug_info = null) {
        $api_key = self::get_api_key_or_error($debug_info);
        if (is_wp_error($api_key)) {
            return $api_key;
        }

        $url = self::BASE_URL . $endpoint;
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
    private static function fetch_get($endpoint, array $params, $wrapper_key, $item_key, array &$debug_info = null) {
        $api_key = self::get_api_key_or_error($debug_info);
        if (is_wp_error($api_key)) {
            return $api_key;
        }

        $params['apikey'] = $api_key;
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
    private static function fetch_json($url, array $args, $wrapper_key, $item_key, array &$debug_info = null, $method = 'GET') {
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
            error_log('Elvanto API request failed: ' . $error_message);
            if (is_array($debug_info)) {
                $debug_info['response'] = array('error' => $error_message);
            }
            return new WP_Error('elvanto_request_failed', $error_message);
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $error_message = 'Unable to parse Elvanto response: ' . json_last_error_msg();
            error_log($error_message);
            if (is_array($debug_info)) {
                $debug_info['response'] = array('error' => $error_message, 'body' => $body);
            }
            return new WP_Error('elvanto_invalid_json', $error_message);
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
            error_log('Elvanto API returned an error: ' . $error_message);
            return new WP_Error('elvanto_api_error', $error_message);
        }

        return self::normalize_pagination_response($data, $wrapper_key, $item_key, $debug_info);
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
    private static function normalize_pagination_response(array $response, $wrapper_key, $item_key, array &$debug_info = null) {
        $result = array();

        if (isset($response[$wrapper_key][$item_key]) && is_array($response[$wrapper_key][$item_key])) {
            $result = $response[$wrapper_key][$item_key];
        } elseif (isset($response[$wrapper_key]) && is_array($response[$wrapper_key]) && !isset($response[$wrapper_key][$item_key])) {
            $result = $response[$wrapper_key];
        }

        if (is_array($debug_info)) {
            $debug_info['normalized'] = array(
                'wrapper_key' => $wrapper_key,
                'item_key' => $item_key,
                'items_count' => count($result),
                'wrapper_keys' => isset($response[$wrapper_key]) && is_array($response[$wrapper_key]) ? array_keys($response[$wrapper_key]) : array(),
            );
        }

        return $result;
    }
}
