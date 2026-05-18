<?php
/**
 * Admin functionality for Elvanto Swiper Plugin
 *
 * @package ElvantoSwiper
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Elvanto_Swiper_Admin {
    
    /**
     * Initialize admin functionality
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_page'), 20);
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'check_for_manual_actions'));
        add_action('admin_init', array($this, 'handle_refresh'));
    }

    /**
     * Add admin menu page
     */
    public function add_admin_page() {
        add_submenu_page(
            'kcg-elvanto-api',
            'Elvanto Swiper Settings',
            'Event Swiper',
            'manage_options',
            'elvanto-swiper',
            array($this, 'admin_page')
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('elvanto_swiper_settings_group', 'elvanto_swiper_service_links');
        
        add_settings_section(
            'elvanto_swiper_service_links_section', 
            'Service Type Links', 
            array($this, 'service_links_section_callback'), 
            'elvanto-swiper'
        );
        
        add_settings_field(
            'elvanto_swiper_service_links', 
            'Custom Links for Service Types', 
            array($this, 'service_links_callback'), 
            'elvanto-swiper', 
            'elvanto_swiper_service_links_section'
        );
    }

    /**
     * Service links section callback
     */
    public function service_links_section_callback() {
        echo '<p>Configure custom "More Info" links for different service types. When a service card is displayed, it will use the corresponding link below. If no link is specified for a service type, no "More Info" button will be shown.</p>';
        echo '<p><strong>Format:</strong> One service type per line in the format: <code>Service Type Name|https://example.com/link</code></p>';
        echo '<p><strong>Example:</strong><br>';
        echo '<code>Sunday Service|https://kcg.church/sunday-service<br>';
        echo 'Small Groups|https://kcg.church/small-groups<br>';
        echo 'Youth Group|https://kcg.church/youth</code></p>';
    }

    /**
     * Service links field callback
     */
    public function service_links_callback() {
        $service_links = get_option('elvanto_swiper_service_links', '');
        echo '<textarea name="elvanto_swiper_service_links" rows="8" cols="80" class="large-text">' . esc_textarea($service_links) . '</textarea>';
        echo '<p class="description">Enter service type links in the format: Service Type Name|URL (one per line)</p>';
    }

    /**
     * Check for manual test button clicks
     */
    public function check_for_manual_actions() {
        // Currently no manual actions for swiper admin
    }

    /**
     * Handle manual refresh
     */
    public function handle_refresh() {
        if (!isset($_POST['elvanto_swiper_refresh_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['elvanto_swiper_refresh_nonce'], 'elvanto_swiper_refresh')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_POST['elvanto_swiper_refresh'])) {
            $api = new Elvanto_Swiper_API();
            $success = $api->fetch_events();
            
            update_option('elvanto_swiper_last_refresh', current_time('mysql'));
            update_option('elvanto_swiper_last_refresh_status', $success ? 'success' : 'failed');
            
            wp_safe_remote_post(
                add_query_arg('elvanto_swiper_refresh_success', '1', admin_url('admin.php?page=elvanto-swiper'))
            );
        }
    }

    /**
     * Admin page content
     */
    public function admin_page() {
        $last_refresh = get_option('elvanto_swiper_last_refresh');
        $last_refresh_status = get_option('elvanto_swiper_last_refresh_status');
        $raw_events = get_transient('elvanto_swiper_raw_events') ?: get_option('elvanto_swiper_raw_events', array());
        $raw_services = get_transient('elvanto_swiper_raw_services') ?: get_option('elvanto_swiper_raw_services', array());
        $events_count = is_array($raw_events) ? count($raw_events) : 0;
        $services_count = is_array($raw_services) ? count($raw_services) : 0;
        $merged_events = get_transient('elvanto_swiper_events') ?: get_option('elvanto_swiper_events', array());
        $merged_count = is_array($merged_events) ? count($merged_events) : 0;
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p>Configure how the Elvanto event swiper displays events. The API key is managed in the <a href="<?php echo esc_url(admin_url('admin.php?page=kcg-elvanto-api')); ?>">Elvanto API settings</a>.</p>
            
            <?php if (isset($_GET['elvanto_swiper_refresh_success'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Swiper data refreshed successfully!', 'kcg-elvanto-swiper'); ?></p>
                </div>
            <?php endif; ?>

            <div class="postbox" style="max-width: 600px;">
                <h2 class="hndle"><span><?php esc_html_e('Refresh Data', 'kcg-elvanto-swiper'); ?></span></h2>
                <div class="inside">
                    <p><?php esc_html_e('Click the button below to manually refresh the swiper events from Elvanto:', 'kcg-elvanto-swiper'); ?></p>
                    
                    <form method="post" action="">
                        <?php wp_nonce_field('elvanto_swiper_refresh', 'elvanto_swiper_refresh_nonce'); ?>
                        <p>
                            <button type="submit" name="elvanto_swiper_refresh" class="button button-primary">
                                <?php esc_html_e('Refresh Now', 'kcg-elvanto-swiper'); ?>
                            </button>
                        </p>
                    </form>
                    
                    <hr>
                    
                    <h3><?php esc_html_e('Connection Status', 'kcg-elvanto-swiper'); ?></h3>
                    
                    <?php if ($last_refresh): ?>
                        <div style="background-color: #f5f5f5; padding: 12px; border-left: 4px solid #28a745; margin-bottom: 12px;">
                            <p style="margin: 0;">
                                <strong><?php esc_html_e('Last Refresh:', 'kcg-elvanto-swiper'); ?></strong>
                                <code><?php echo esc_html($last_refresh); ?></code>
                            </p>
                            <p style="margin: 8px 0 0 0; color: #666;">
                                <strong><?php esc_html_e('Status:', 'kcg-elvanto-swiper'); ?></strong>
                                <span style="background-color: <?php echo $last_refresh_status === 'success' ? '#28a745' : '#dc3545'; ?>; color: white; padding: 2px 8px; border-radius: 3px; display: inline-block;">
                                    <?php echo esc_html($last_refresh_status ?: 'unknown'); ?>
                                </span>
                            </p>
                            <p style="margin: 8px 0 0 0; color: #666;">
                                <strong><?php esc_html_e('Data Loaded:', 'kcg-elvanto-swiper'); ?></strong>
                                <?php printf(
                                    esc_html__('%d events, %d services, %d merged', 'kcg-elvanto-swiper'),
                                    $events_count,
                                    $services_count,
                                    $merged_count
                                ); ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <p style="color: #999;"><em><?php esc_html_e('No refresh yet. Click "Refresh Now" to fetch data.', 'kcg-elvanto-swiper'); ?></em></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="postbox" style="max-width: 900px; margin-top: 20px;">
                <h2 class="hndle"><span><?php esc_html_e('Plugin Settings', 'kcg-elvanto-swiper'); ?></span></h2>
                <div class="inside">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('elvanto_swiper_settings_group');
                        do_settings_sections('elvanto-swiper');
                        submit_button();
                        ?>
                    </form>
                </div>
            </div>

            <?php if (!empty($merged_events)): ?>
            <div class="postbox" style="max-width: 1600px; margin-top: 20px;">
                <h2 class="hndle"><span><?php esc_html_e('Merged Events', 'kcg-elvanto-swiper'); ?> (<?php echo esc_html($merged_count); ?>)</span></h2>
                <div class="inside">
                    <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; min-width: 1400px;">
                        <thead>
                            <tr style="background-color: #f5f5f5; border-bottom: 2px solid #ddd;">
                                <th style="padding: 8px; text-align: left; border-right: 1px solid #ddd; min-width: 60px;"><strong>ID</strong></th>
                                <th style="padding: 8px; text-align: left; border-right: 1px solid #ddd; min-width: 120px;"><strong>Title</strong></th>
                                <th style="padding: 8px; text-align: left; border-right: 1px solid #ddd; min-width: 80px;"><strong>Source</strong></th>
                                <th style="padding: 8px; text-align: left; border-right: 1px solid #ddd; min-width: 100px;"><strong>Date</strong></th>
                                <th style="padding: 8px; text-align: left; border-right: 1px solid #ddd; min-width: 80px;"><strong>Time</strong></th>
                                <th style="padding: 8px; text-align: left; border-right: 1px solid #ddd; min-width: 60px;"><strong>All Day</strong></th>
                                <th style="padding: 8px; text-align: left; border-right: 1px solid #ddd; min-width: 100px;"><strong>Subtitle</strong></th>
                                <th style="padding: 8px; text-align: left; border-right: 1px solid #ddd; min-width: 120px;"><strong>Location</strong></th>
                                <th style="padding: 8px; text-align: left; border-right: 1px solid #ddd; min-width: 150px;"><strong>Description</strong></th>
                                <th style="padding: 8px; text-align: left; border-right: 1px solid #ddd; min-width: 80px;"><strong>Color</strong></th>
                                <th style="padding: 8px; text-align: left; border-right: 1px solid #ddd; min-width: 60px;"><strong>Image</strong></th>
                                <th style="padding: 8px; text-align: left; border-right: 1px solid #ddd; min-width: 100px;"><strong>More Info</strong></th>
                                <th style="padding: 8px; text-align: left; min-width: 100px;"><strong>Register</strong></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($merged_events as $event): 
                            ?>
                                <tr style="border-bottom: 1px solid #eee;">
                                    <td style="padding: 8px; border-right: 1px solid #eee;"><code><?php echo esc_html($event['id'] ?? ''); ?></code></td>
                                    <td style="padding: 8px; border-right: 1px solid #eee;"><strong><?php echo esc_html($event['title'] ?? $event['name'] ?? ''); ?></strong></td>
                                    <td style="padding: 8px; border-right: 1px solid #eee;">
                                        <?php 
                                        $source = $event['source'] ?? 'unknown';
                                        $source_colors = ['service' => '#d4edda', 'event' => '#cfe2ff', 'unknown' => '#fff3cd'];
                                        $source_text_colors = ['service' => '#155724', 'event' => '#084298', 'unknown' => '#856404'];
                                        ?>
                                        <span style="background-color: <?php echo esc_attr($source_colors[$source] ?? '#f8f9fa'); ?>; color: <?php echo esc_attr($source_text_colors[$source] ?? '#000'); ?>; padding: 2px 6px; border-radius: 3px; font-size: 12px;"><?php echo esc_html(ucfirst($source)); ?></span>
                                    </td>
                                    <td style="padding: 8px; border-right: 1px solid #eee;"><code><?php echo esc_html($event['date'] ?? $event['start_date'] ?? '—'); ?></code></td>
                                    <td style="padding: 8px; border-right: 1px solid #eee;">
                                        <?php
                                        if (!empty($event['time'])) {
                                            echo esc_html($event['time']);
                                        } elseif (!empty($event['start_date']) && strpos($event['start_date'], ':') !== false) {
                                            $datetime = new DateTime($event['start_date']);
                                            echo esc_html($datetime->format('H:i:s'));
                                        } else {
                                            echo '—';
                                        }
                                        ?>
                                    </td>
                                    <td style="padding: 8px; border-right: 1px solid #eee; text-align: center;">
                                        <?php echo (!empty($event['all_day']) ? '✅' : '❌'); ?>
                                    </td>
                                    <td style="padding: 8px; border-right: 1px solid #eee;"><?php echo esc_html($event['subtitle'] ?? '—'); ?></td>
                                    <td style="padding: 8px; border-right: 1px solid #eee;"><?php echo esc_html($event['location'] ?? '—'); ?></td>
                                    <td style="padding: 8px; border-right: 1px solid #eee; font-size: 12px;">
                                        <?php 
                                        if (!empty($event['description'])) {
                                            $desc = substr($event['description'], 0, 50);
                                            if (strlen($event['description']) > 50) $desc .= '...';
                                            echo esc_html($desc);
                                        } else {
                                            echo '—';
                                        }
                                        ?>
                                    </td>
                                    <td style="padding: 8px; border-right: 1px solid #eee;">
                                        <?php 
                                        $color = $event['color'] ?? '#007cba';
                                        ?>
                                        <span style="display: inline-block; width: 16px; height: 16px; background-color: <?php echo esc_attr($color); ?>; border-radius: 3px; vertical-align: middle; margin-right: 5px; border: 1px solid #ddd;"></span>
                                        <code style="font-size: 11px;"><?php echo esc_html($color); ?></code>
                                    </td>
                                    <td style="padding: 8px; border-right: 1px solid #eee; text-align: center;">
                                        <?php echo (!empty($event['picture']) ? '✅' : '❌'); ?>
                                    </td>
                                    <td style="padding: 8px; border-right: 1px solid #eee;">
                                        <?php
                                        $has_link_info = !empty($event['link_info']) && filter_var($event['link_info'], FILTER_VALIDATE_URL);
                                        echo ($has_link_info ? '✅' : '❌');
                                        ?>
                                    </td>
                                    <td style="padding: 8px;">
                                        <?php
                                        $has_link_register = !empty($event['link_register']) && filter_var($event['link_register'], FILTER_VALIDATE_URL);
                                        echo ($has_link_register ? '✅' : '❌');
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
