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
     * Admin page content
     */
    public function admin_page() {
        $services = class_exists('KCG_Elvanto_Cache') ? KCG_Elvanto_Cache::get_services() : array();
        $events = class_exists('KCG_Elvanto_Cache') ? KCG_Elvanto_Cache::get_events() : array();

        $services_count = is_array($services) ? count($services) : 0;
        $events_count = is_array($events) ? count($events) : 0;
        $merged_events = array_values(array_merge(is_array($services) ? $services : array(), is_array($events) ? $events : array()));
        $merged_count = count($merged_events);
        $preview_events = array_slice($merged_events, 0, 5);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p>Configure how the Elvanto event swiper displays events. The API key is managed in the <a href="<?php echo esc_url(admin_url('admin.php?page=kcg-elvanto-api')); ?>">Elvanto API settings</a>.</p>

            <div class="postbox" style="max-width: 800px; margin-bottom: 20px;">
                <h2 class="hndle"><span><?php esc_html_e('Current Swiper Data', 'kcg-elvanto-swiper'); ?></span></h2>
                <div class="inside">
                    <p><?php esc_html_e('This plugin reads the shared provider cache. Refreshes are managed in the Elvanto API provider admin.', 'kcg-elvanto-swiper'); ?></p>
                    <table class="widefat striped">
                        <tbody>
                            <tr>
                                <th style="width: 180px;">Events</th>
                                <td><?php echo esc_html($events_count); ?></td>
                            </tr>
                            <tr>
                                <th>Services</th>
                                <td><?php echo esc_html($services_count); ?></td>
                            </tr>
                            <tr>
                                <th>Merged items</th>
                                <td><?php echo esc_html($merged_count); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="postbox" style="max-width: 900px; margin-top: 20px;">
                <h2 class="hndle"><span><?php esc_html_e('Upcoming event preview', 'kcg-elvanto-swiper'); ?></span></h2>
                <div class="inside">
                    <?php if (!empty($preview_events)): ?>
                        <ul style="margin: 0; padding-left: 20px;">
                            <?php foreach ($preview_events as $event): ?>
                                <li style="margin-bottom: 12px;">
                                    <strong><?php echo esc_html($event['title'] ?? $event['name'] ?? 'Untitled'); ?></strong>
                                    <div style="color: #666; font-size: 13px;">
                                        <?php echo esc_html($event['date'] ?? $event['start_date'] ?? ''); ?>
                                        <?php if (!empty($event['time'])) : ?>
                                            &nbsp;|&nbsp;<?php echo esc_html($event['time']); ?>
                                        <?php endif; ?>
                                        <?php if (!empty($event['location']['name'] ?? $event['location'] ?? '')) : ?>
                                            &nbsp;|&nbsp;<?php echo esc_html(is_array($event['location'] ?? null) ? ($event['location']['name'] ?? '') : $event['location']); ?>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>No rendered event cards are available yet.</p>
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
        </div>
        <?php
    }
}
