<?php
/**
 * Admin functionality for KCG Elvanto API Provider
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class KCG_Elvanto_API_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_page'), 5);
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'handle_test_request'));
    }

    public function add_admin_page() {
        add_menu_page(
            'Elvanto API Settings',
            'Elvanto API',
            'manage_options',
            'kcg-elvanto-api',
            array($this, 'admin_page'),
            'dashicons-admin-network',
            60
        );
    }

    public function register_settings() {
        register_setting('kcg_elvanto_api_settings_group', 'kcg_elvanto_api_key');
        
        add_settings_section(
            'kcg_elvanto_api_main_section', 
            'API Key', 
            null, 
            'kcg-elvanto-api'
        );
        
        add_settings_field(
            'kcg_elvanto_api_key', 
            'Elvanto API Key', 
            array($this, 'api_key_callback'), 
            'kcg-elvanto-api', 
            'kcg_elvanto_api_main_section'
        );
    }

    public function api_key_callback() {
        $api_key = get_option('kcg_elvanto_api_key');
        echo '<input type="password" name="kcg_elvanto_api_key" value="' . esc_attr($api_key) . '" class="regular-text">';
    }

    public function handle_test_request() {
        if (!isset($_POST['kcg_elvanto_api_test_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['kcg_elvanto_api_test_nonce'], 'kcg_elvanto_api_test')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_POST['kcg_elvanto_test_submit'])) {
            $this->test_api_connection();
        }
    }

    public function test_api_connection() {
        $api_key = get_option('kcg_elvanto_api_key');
        
        if (!$api_key) {
            update_option('kcg_elvanto_test_result', array('error' => 'No API key configured'));
            return;
        }

        $custom_options = isset($_POST['kcg_elvanto_custom_options']) ? sanitize_textarea_field($_POST['kcg_elvanto_custom_options']) : '';
        
        // Default options
        $body_data = array(
            'start' => date('Y-m-d'),
            'end' => date('Y-m-d', strtotime('+7 days')),
            'fields' => array('series_name', 'volunteers')
        );

        // Override with custom options if provided
        if (!empty($custom_options)) {
            $custom = json_decode($custom_options, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($custom)) {
                $body_data = array_merge($body_data, $custom);
            } else {
                update_option('kcg_elvanto_test_result', array('error' => 'Invalid JSON in custom options'));
                return;
            }
        }

        $url = 'https://api.elvanto.com/v1/services/getAll.json';
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($api_key . ':x')
            ),
            'body' => json_encode($body_data),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            update_option('kcg_elvanto_test_result', array('error' => $response->get_error_message()));
            return;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        $result = array(
            'http_code' => $code,
            'status' => isset($data['status']) ? $data['status'] : 'unknown',
            'response_preview' => wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        if (isset($data['error'])) {
            $result['error'] = $data['error'];
        }

        update_option('kcg_elvanto_test_result', $result);
    }

    public function admin_page() {
        $test_result = get_option('kcg_elvanto_test_result');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('kcg_elvanto_api_settings_group');
                do_settings_sections('kcg-elvanto-api');
                submit_button();
                ?>
            </form>

            <hr>

            <div class="postbox" style="max-width: 800px; margin-top: 20px;">
                <h2 class="hndle"><span>Test API Connection</span></h2>
                <div class="inside">
                    <form method="post" action="">
                        <?php wp_nonce_field('kcg_elvanto_api_test', 'kcg_elvanto_api_test_nonce'); ?>
                        
                        <h3>Custom Request Options (JSON)</h3>
                        <p> Calls https://api.elvanto.com/v1/services/getAll.json.</p>
                        <p style="color: #666; font-size: 13px;">
                            Leave empty to use defaults (start date, end date +7 days, fields: series_name, volunteers)
                        </p>
                        <textarea 
                            name="kcg_elvanto_custom_options" 
                            rows="6" 
                            cols="80" 
                            class="large-text code"
                            placeholder='{"start": "2026-01-19", "end": "2026-01-26", "fields": ["series_name", "volunteers"]}'
                            style="font-family: monospace;"
                        ></textarea>

                        <p>
                            <button type="submit" name="kcg_elvanto_test_submit" class="button button-primary">
                                Test Connection
                            </button>
                        </p>
                    </form>

                    <?php if (!empty($test_result)): ?>
                        <hr style="margin-top: 30px;">
                        <h3>Test Results</h3>
                        
                        <?php if (isset($test_result['error'])): ?>
                            <div style="background-color: #fff8f5; border-left: 4px solid #dc3545; padding: 12px;">
                                <strong style="color: #dc3545;">Error:</strong>
                                <pre style="margin: 8px 0 0 0; white-space: pre-wrap; word-wrap: break-word;">
                                    <?php echo esc_html(wp_json_encode($test_result['error'], JSON_PRETTY_PRINT)); ?>
                                </pre>
                            </div>
                        <?php else: ?>
                            <div style="background-color: #f5f5f5; border-left: 4px solid #28a745; padding: 12px; margin-bottom: 12px;">
                                <strong>HTTP Status:</strong> 
                                <span style="background-color: #28a745; color: white; padding: 2px 8px; border-radius: 3px; font-weight: bold;">
                                    <?php echo esc_html($test_result['http_code']); ?>
                                </span>
                                <br>
                                <strong>API Status:</strong> 
                                <code><?php echo esc_html($test_result['status']); ?></code>
                            </div>

                            <h4>Response Preview</h4>
                            <pre style="background-color: #f5f5f5; padding: 12px; border-radius: 3px; overflow-x: auto; max-height: 400px; overflow-y: auto; border: 1px solid #ddd; font-size: 12px;">
                                <?php echo esc_html($test_result['response_preview']); ?>
                            </pre>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
}
