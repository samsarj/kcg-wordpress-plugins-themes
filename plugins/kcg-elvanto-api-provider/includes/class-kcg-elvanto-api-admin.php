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
        add_action('admin_init', array($this, 'handle_provider_refresh_override'));
        add_action('wp_loaded', array($this, 'bootstrap_provider_cache'));
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

    public function bootstrap_provider_cache() {
        if ( class_exists('KCG_Elvanto_Cache') ) {
            KCG_Elvanto_Cache::init();
        }
    }

    public function handle_provider_refresh_override() {
        if (!isset($_POST['kcg_elvanto_provider_refresh_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['kcg_elvanto_provider_refresh_nonce'], 'kcg_elvanto_provider_refresh')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_POST['kcg_elvanto_provider_refresh'])) {
            if (class_exists('KCG_Elvanto_Cache')) {
                KCG_Elvanto_Cache::refresh_all_override();
            }

            wp_safe_redirect(
                add_query_arg('kcg_elvanto_provider_refresh_success', '1', admin_url('admin.php?page=kcg-elvanto-api'))
            );
            exit;
        }
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
        
        // Do not guess field names for the provider test request. Use a broad date window and
        // allow the provider to confirm supported fields before a stricter request is tested.
        $body_data = array(
            'start' => date('Y-m-d'),
            'end' => date('Y-m-d', strtotime('+7 days')),
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
        $cache_meta = get_option('kcg_elvanto_provider_cache_meta', array());
        $services = class_exists('KCG_Elvanto_Cache') ? KCG_Elvanto_Cache::get_services() : array();
        $events = class_exists('KCG_Elvanto_Cache') ? KCG_Elvanto_Cache::get_events() : array();
        $people = class_exists('KCG_Elvanto_Cache') ? KCG_Elvanto_Cache::get_people() : array();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php if (isset($_GET['kcg_elvanto_provider_refresh_success'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Provider cache refreshed successfully.', 'kcg-elvanto-api-provider'); ?></p>
                </div>
            <?php endif; ?>

            <div class="postbox" style="max-width: 1000px; margin-bottom: 20px;">
                <h2 class="hndle"><span>Provider Status</span></h2>
                <div class="inside">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 16px;">
                        <div style="padding: 12px; border: 1px solid #ddd; background: #fff; border-radius: 4px;">
                            <div style="font-size: 12px; color: #666; text-transform: uppercase;">Services</div>
                            <div style="font-size: 28px; font-weight: 600; margin-top: 8px;"><?php echo esc_html(count($services)); ?></div>
                        </div>
                        <div style="padding: 12px; border: 1px solid #ddd; background: #fff; border-radius: 4px;">
                            <div style="font-size: 12px; color: #666; text-transform: uppercase;">Events</div>
                            <div style="font-size: 28px; font-weight: 600; margin-top: 8px;"><?php echo esc_html(count($events)); ?></div>
                        </div>
                        <div style="padding: 12px; border: 1px solid #ddd; background: #fff; border-radius: 4px;">
                            <div style="font-size: 12px; color: #666; text-transform: uppercase;">People</div>
                            <div style="font-size: 28px; font-weight: 600; margin-top: 8px;"><?php echo esc_html(count($people)); ?></div>
                        </div>
                    </div>

                    <form method="post" action="">
                        <?php wp_nonce_field('kcg_elvanto_provider_refresh', 'kcg_elvanto_provider_refresh_nonce'); ?>
                        <button type="submit" name="kcg_elvanto_provider_refresh" class="button button-primary">
                            Refresh Elvanto cache
                        </button>
                    </form>

                    <?php if (!empty($cache_meta)) : ?>
                        <table class="widefat striped" style="margin-top: 16px;">
                            <thead>
                                <tr>
                                    <th>Dataset</th>
                                    <th>Status</th>
                                    <th>Last updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cache_meta as $key => $details) : ?>
                                    <tr>
                                        <td><?php echo esc_html(ucfirst((string) $key)); ?></td>
                                        <td><?php echo esc_html($details['status'] ?? 'unknown'); ?></td>
                                        <td><?php echo esc_html($details['updated_at'] ?? '—'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p style="margin-top: 16px; color: #666;">No cache refreshes have completed yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <form method="post" action="options.php">
                <?php
                settings_fields('kcg_elvanto_api_settings_group');
                do_settings_sections('kcg-elvanto-api');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}
