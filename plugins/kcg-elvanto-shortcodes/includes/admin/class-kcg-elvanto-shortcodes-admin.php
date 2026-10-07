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

    private function render_shortcode_argument_table($arguments) {
        echo '<table class="widefat striped"><thead><tr><th>Argument</th><th>What it does</th></tr></thead><tbody>';
        foreach ($arguments as $argument => $description) {
            printf(
                '<tr><td><code>%1$s</code></td><td>%2$s</td></tr>',
                esc_html($argument),
                esc_html($description)
            );
        }
        echo '</tbody></table>';
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
                    <p>Service types and calendars can be selected by exact name. Filter values support comma-separated lists and match case-insensitively. Each shortcode below lists only the arguments that shortcode accepts.</p>
                    <p>Email rendering uses email-safe markup in recognized editor contexts. Newsletter send workers should use the <code>kcg_elvanto_is_email</code> filter to enable it only while rendering the email.</p>

                    <h3><code>[next-on]</code> — next matching service or event as text</h3>
                    <?php
                    $this->render_shortcode_argument_table(array(
                        'type' => 'Match a service type or calendar name. Multiple comma-separated exact names are allowed.',
                        'service_type' => 'Match service types only.',
                        'calendar' => 'Match calendar names only.',
                        'source' => 'service, event, or all. Defaults to all.',
                        'limit' => 'Accepted for shared-filter consistency, but this shortcode always selects one next match.',
                        'align' => 'Text alignment: left, center, right, or justify. Default: theme alignment.',
                        'show_preacher' => 'Show the mapped preacher name when present: yes or no. Default: no.',
                    ));
                    ?>
                    <p>No type/service_type/calendar filter defaults to <code>Sunday Service</code>. This shortcode always displays just the next match.</p>
                    <p><code>[next-on type="Sunday Service" show_preacher="yes"]</code><br>
                    <code>[next-on calendar="Youth" align="center"]</code></p>

                    <h3><code>[next-on-card]</code> — event card; email can show a list</h3>
                    <?php
                    $this->render_shortcode_argument_table(array(
                        'type' => 'Match a service type or calendar name. Multiple comma-separated exact names are allowed.',
                        'service_type' => 'Match service types only.',
                        'calendar' => 'Match calendar names only.',
                        'source' => 'service, event, or all. Defaults to all.',
                        'limit' => 'Accepted for shared-filter consistency, but overridden: website output selects one card; email count is controlled by amount/range.',
                        'exclude_service_type' => 'Hide services of the listed service types. Comma-separated.',
                        'include_service_name' => 'Exception to exclude_service_type: allow excluded-type services whose title contains a listed phrase, e.g. Christmas. Comma-separated.',
                        'show_preacher' => 'Optionally show the mapped preacher name when present: true or false. Default: false.',
                        'show_description' => 'Show the event description: true or false. Default: true.',
                        'align' => 'Card alignment: left, center, or right. Email defaults to center.',
                        'amount' => 'Email only: positive maximum number of cards. Without range, defaults to one card.',
                        'range' => 'Email only: future end range, such as 14 days, 6 weeks, or 3 months. With a range and no amount, show all matches in the range.',
                        'from' => 'Email only: optional start boundary, next-week (next Monday) or a positive offset such as 7 days. Combine with range to select a window.',
                        'layout' => 'Email only: compact places the image beside smaller event details and buttons. Other values use the full card layout.',
                    ));
                    ?>
                    <p>Website output remains a single compact card. Email cards fill their containing width; constrain them with the newsletter editor's group/layout controls. Amount, range, from, and layout affect email output only. Defaults to the next matching service or event.</p>
                    <p><code>[next-on-card]</code><br>
                    <code>[next-on-card show_description="false" show_preacher="false"]</code><br>
                    <code>[next-on-card amount="4" range="6 weeks" from="next-week" layout="compact" exclude_service_type="Sunday Gathering,Equip" include_service_name="Christmas"]</code></p>

                    <h3><code>[next-on-card-swiper]</code> — carousel on the website, stacked cards in email</h3>
                    <?php
                    $this->render_shortcode_argument_table(array(
                        'type' => 'Match a service type or calendar name. Multiple comma-separated exact names are allowed.',
                        'service_type' => 'Match service types only.',
                        'calendar' => 'Match calendar names only.',
                        'source' => 'service, event, or all. Defaults to all.',
                        'limit' => 'Maximum number of items. Defaults to 10; use 0 for no limit.',
                        'exclude_service_type' => 'Hide services of the listed service types. Comma-separated.',
                        'include_service_name' => 'Exception to exclude_service_type: allow excluded-type services whose title contains a listed phrase, e.g. Christmas. Comma-separated.',
                        'show_date' => 'Show event date: yes or no. Default: yes.',
                        'show_time' => 'Show event time: yes or no. Default: yes.',
                        'show_description' => 'Show event description: yes or no. Default: yes.',
                    ));
                    ?>
                    <p><code>[next-on-card-swiper limit="6" calendar="Youth,Kids"]</code><br>
                    <code>[next-on-card-swiper exclude_service_type="Sunday Gathering" include_service_name="Christmas"]</code></p>

                    <h3><code>[kcg_preaching_table]</code> — upcoming services and speakers</h3>
                    <?php
                    $this->render_shortcode_argument_table(array(
                        'type' => 'Match a service type or calendar name. Multiple comma-separated exact names are allowed.',
                        'service_type' => 'Match service types only.',
                        'calendar' => 'Match calendar names only.',
                        'source' => 'service, event, or all. Defaults to service.',
                        'limit' => 'Maximum number of rows. Defaults to 10; use 0 for no limit.',
                        'class' => 'CSS class for the table. Default: kcg-preaching-table.',
                    ));
                    ?>
                    <p><code>[kcg_preaching_table service_type="Sunday Service" limit="12"]</code></p>
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
