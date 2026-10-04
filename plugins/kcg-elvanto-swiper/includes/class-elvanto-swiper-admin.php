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
        register_setting('elvanto_swiper_settings_group', 'elvanto_swiper_service_links', array(
            'sanitize_callback' => array($this, 'sanitize_service_links'),
        ));
        
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
     * Sanitize submitted service links into a type => url array.
     */
    public function sanitize_service_links($input) {
        $clean = array();
        if (!is_array($input)) {
            return $clean;
        }
        foreach ($input as $type => $url) {
            $type = sanitize_text_field(wp_unslash((string) $type));
            $url = esc_url_raw(trim(wp_unslash((string) $url)));
            if ($type !== '' && $url !== '') {
                $clean[$type] = $url;
            }
        }
        return $clean;
    }

    /**
     * Collect service type names from the provider cache.
     */
    private function get_available_service_types() {
        $types = array();
        $services = class_exists('KCG_Elvanto_Cache') ? KCG_Elvanto_Cache::get_services() : array();
        foreach ((array) $services as $service) {
            $name = trim((string) ($service['service_type']['name'] ?? ''));
            if ($name !== '') {
                $types[$name] = true;
            }
        }
        $types = array_keys($types);
        sort($types, SORT_NATURAL | SORT_FLAG_CASE);
        return $types;
    }

    /**
     * Service links section callback
     */
    public function service_links_section_callback() {
        echo '<p>Set a "More Info" link for each service type. Service types are read from Elvanto. Leave a link blank and no "More Info" button is shown for that type.</p>';
    }

    /**
     * Service links field callback
     */
    public function service_links_callback() {
        $saved = (array) get_option('elvanto_swiper_service_links', array());
        $available = $this->get_available_service_types();
        $orphans = array_diff(array_keys($saved), $available);

        if (empty($available) && empty($orphans)) {
            echo '<p class="description">No service types found. Refresh the cache in the Elvanto API settings first.</p>';
            return;
        }

        echo '<table class="widefat striped" style="max-width: 800px;"><thead><tr><th>Service type</th><th>Link URL</th></tr></thead><tbody>';
        foreach (array_merge($available, $orphans) as $type) {
            $note = in_array($type, $orphans, true) ? ' <em>(not in current Elvanto data)</em>' : '';
            printf(
                '<tr><td>%1$s%2$s</td><td><input type="url" class="large-text" name="elvanto_swiper_service_links[%3$s]" value="%4$s" placeholder="https://"></td></tr>',
                esc_html($type),
                $note,
                esc_attr($type),
                esc_attr($saved[$type] ?? '')
            );
        }
        echo '</tbody></table>';
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
                                <?php
                                $event_date = trim((string) ($event['date'] ?? $event['start_date'] ?? ''));
                                $event_datetime = KCG_Elvanto_Datetime::parse($event_date);

                                $formatted_event_date = $event_datetime
                                    ? wp_date(get_option('date_format'), $event_datetime->getTimestamp(), $event_datetime->getTimezone())
                                    : $event_date;
                                $formatted_event_time = $event_datetime && KCG_Elvanto_Datetime::has_time($event_date) && empty($event['all_day'])
                                    ? wp_date(get_option('time_format'), $event_datetime->getTimestamp(), $event_datetime->getTimezone())
                                    : '';
                                ?>
                                <li style="margin-bottom: 12px;">
                                    <strong><?php echo esc_html($event['title'] ?? $event['name'] ?? 'Untitled'); ?></strong>
                                    <div style="color: #666; font-size: 13px;">
                                        <?php echo esc_html($formatted_event_date); ?>
                                        <?php if ($formatted_event_time !== '') : ?>
                                            &nbsp;|&nbsp;<?php echo esc_html($formatted_event_time); ?>
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
