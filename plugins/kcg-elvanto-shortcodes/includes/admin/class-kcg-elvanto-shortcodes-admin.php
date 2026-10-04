<?php
/**
 * Admin page for the KCG Elvanto Shortcodes plugin: service-type links, merged_events preview
 * and a shortcode reference.
 */

if (!defined('ABSPATH')) {
    exit;
}

class KCG_Elvanto_Shortcodes_Admin {

    const SETTINGS_GROUP = 'kcg_elvanto_shortcodes_settings_group';
    const PAGE_SLUG = 'kcg-elvanto-shortcodes';

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_page'), 20);
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function add_admin_page() {
        add_submenu_page(
            'kcg-elvanto-api',
            'Elvanto Shortcodes',
            'Shortcodes',
            'manage_options',
            self::PAGE_SLUG,
            array($this, 'admin_page')
        );
    }

    public function register_settings() {
        register_setting(self::SETTINGS_GROUP, KCG_Elvanto_Event_Merger::SERVICE_LINKS_OPTION, array(
            'sanitize_callback' => array($this, 'sanitize_service_links'),
        ));
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
     * Sorted unique non-empty values of a merged_events field.
     */
    private function distinct($merged_events, $field) {
        $values = array();
        foreach ($merged_events as $item) {
            $value = trim((string) ($item[$field] ?? ''));
            if ($value !== '') {
                $values[$value] = true;
            }
        }
        $values = array_keys($values);
        sort($values, SORT_NATURAL | SORT_FLAG_CASE);
        return $values;
    }

    private function render_service_links($service_types) {
        $saved = (array) get_option(KCG_Elvanto_Event_Merger::SERVICE_LINKS_OPTION, array());
        $orphans = array_diff(array_keys($saved), $service_types);

        if (empty($service_types) && empty($orphans)) {
            echo '<p class="description">No service types found. Refresh the cache in the Elvanto API settings first.</p>';
            return;
        }

        echo '<p>Set a "More Info" link for each service type. Leave a link blank and no "More Info" button is shown for that type.</p>';
        echo '<table class="widefat striped"><thead><tr><th>Service type</th><th>Link URL</th></tr></thead><tbody>';
        foreach (array_merge($service_types, $orphans) as $type) {
            printf(
                '<tr><td>%1$s%2$s</td><td><input type="url" class="large-text" name="%3$s[%4$s]" value="%5$s" placeholder="https://"></td></tr>',
                esc_html($type),
                in_array($type, $orphans, true) ? ' <em>(not in current Elvanto data)</em>' : '',
                esc_attr(KCG_Elvanto_Event_Merger::SERVICE_LINKS_OPTION),
                esc_attr($type),
                esc_attr($saved[$type] ?? '')
            );
        }
        echo '</tbody></table>';
    }

    public function admin_page() {
        $merged = class_exists('KCG_Elvanto_Cache') ? KCG_Elvanto_Cache::get_merged_events() : array();
        $service_types = $this->distinct($merged, 'service_type');
        $calendars = $this->distinct($merged, 'calendar_name');
        $preview = array_slice($merged, 0, 8);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p>These shortcodes read the provider's <code>merged_events</code> cache. The API key and refreshes are managed in the <a href="<?php echo esc_url(admin_url('admin.php?page=kcg-elvanto-api')); ?>">Elvanto API settings</a>.</p>

            <div class="postbox" style="max-width: 900px;">
                <h2 class="hndle"><span>Shortcodes</span></h2>
                <div class="inside">
                    <p>All shortcodes accept the same filters. Services have <strong>service types</strong>; events have <strong>calendars</strong>. Use comma-separated lists for several values; matching is case-insensitive.</p>
                    <table class="widefat striped">
                        <thead><tr><th>Attribute</th><th>Meaning</th></tr></thead>
                        <tbody>
                            <tr><td><code>type</code></td><td>A service type <em>or</em> a calendar name</td></tr>
                            <tr><td><code>service_type</code></td><td>Service type only</td></tr>
                            <tr><td><code>calendar</code></td><td>Calendar name only</td></tr>
                            <tr><td><code>source</code></td><td><code>service</code>, <code>event</code> or <code>all</code></td></tr>
                            <tr><td><code>limit</code></td><td>Maximum items (0 = all)</td></tr>
                        </tbody>
                    </table>
                    <ul style="list-style: disc; padding-left: 20px;">
                        <li><code>[next-on type="Sunday Service"]</code>, <code>[next-on calendar="Youth" align="center"]</code> (default type: Sunday Service)</li>
                        <li><code>[elvanto_swiper limit="6" calendar="Youth,Kids"]</code> (default: all services and events)</li>
                        <li><code>[kcg_preaching_table service_type="Sunday Service" limit="12"]</code> (default source: service)</li>
                    </ul>
                    <p><strong>Service types:</strong> <?php echo esc_html($service_types ? implode(', ', $service_types) : 'none loaded'); ?><br>
                    <strong>Calendars:</strong> <?php echo esc_html($calendars ? implode(', ', $calendars) : 'none loaded'); ?></p>
                </div>
            </div>

            <div class="postbox" style="max-width: 900px;">
                <h2 class="hndle"><span>Service type links</span></h2>
                <div class="inside">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields(self::SETTINGS_GROUP);
                        $this->render_service_links($service_types);
                        submit_button();
                        ?>
                    </form>
                </div>
            </div>

            <div class="postbox" style="max-width: 900px;">
                <h2 class="hndle"><span>Upcoming merged_events preview (<?php echo esc_html(count($merged)); ?> total)</span></h2>
                <div class="inside">
                    <?php if ($preview): ?>
                        <ul style="margin: 0; padding-left: 20px;">
                            <?php foreach ($preview as $item): ?>
                                <?php
                                $dt = KCG_Elvanto_Event_Query::datetime($item);
                                $when = $dt
                                    ? wp_date(get_option('date_format') . (!empty($item['time']) ? ' ' . get_option('time_format') : ''), $dt->getTimestamp(), $dt->getTimezone())
                                    : (string) ($item['date'] ?? '');
                                $group = $item['service_type'] !== '' ? $item['service_type'] : $item['calendar_name'];
                                ?>
                                <li style="margin-bottom: 8px;">
                                    <strong><?php echo esc_html($item['title'] !== '' ? $item['title'] : 'Untitled'); ?></strong>
                                    <div style="color: #666; font-size: 13px;">
                                        <?php echo esc_html($when); ?> | <?php echo esc_html(ucfirst($item['source'])); ?><?php echo $group !== '' ? ' | ' . esc_html($group) : ''; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>No upcoming services or events are currently loaded.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
}
