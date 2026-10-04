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
            'start' => current_time('Y-m-d'),
            'end' => wp_date('Y-m-d', time() + 7 * DAY_IN_SECONDS),
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
        $calendars = class_exists('KCG_Elvanto_Cache') ? KCG_Elvanto_Cache::get_calendars() : array();
        $merged_events = class_exists('KCG_Elvanto_Cache') ? KCG_Elvanto_Cache::get_merged_events() : array();
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
                            <div style="font-size: 12px; color: #666; text-transform: uppercase;">Calendars</div>
                            <div style="font-size: 28px; font-weight: 600; margin-top: 8px;"><?php echo esc_html(count($calendars)); ?></div>
                        </div>
                        <div style="padding: 12px; border: 1px solid #ddd; background: #fff; border-radius: 4px;">
                            <div style="font-size: 12px; color: #666; text-transform: uppercase;">Merged events</div>
                            <div style="font-size: 28px; font-weight: 600; margin-top: 8px;"><?php echo esc_html(count($merged_events)); ?></div>
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
                                        <?php
                                        $updated_at = trim((string) ($details['updated_at'] ?? ''));
                                        $updated_datetime = $updated_at !== ''
                                            ? DateTime::createFromFormat('Y-m-d H:i:s', $updated_at, wp_timezone())
                                            : false;
                                        $updated_at_display = $updated_datetime instanceof DateTime
                                            ? $updated_datetime->format('Y-m-d H:i:s')
                                            : ($updated_at !== '' ? $updated_at : '—');
                                        ?>
                                        <td><?php echo esc_html($updated_at_display); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p style="margin-top: 16px; color: #666;">No cache refreshes have completed yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <?php $this->render_merged_events_table($merged_events); ?>

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

    /**
     * Render the cached merged_events as a filterable table.
     */
    private function render_merged_events_table(array $merged_events) {
        $date_format = get_option('date_format');
        $time_format = get_option('time_format');
        $groups = array();
        foreach ($merged_events as $item) {
            $group = $item['source'] === 'service' ? $item['service_type'] : $item['calendar_name'];
            if ($group !== '') {
                $groups[$group] = true;
            }
        }
        ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);
        ?>
        <style>
            .kcg-me { max-width: 1400px; }
            .kcg-me-toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 12px; }
            .kcg-me-toolbar input[type=search] { min-width: 240px; }
            .kcg-me-wrap { overflow-x: auto; border: 1px solid #dcdcde; border-radius: 6px; }
            .kcg-me table { border: 0; border-collapse: collapse; width: 100%; }
            .kcg-me th { position: sticky; top: 32px; background: #f6f7f7; text-align: left; font-size: 12px; text-transform: uppercase; letter-spacing: .03em; color: #50575e; white-space: nowrap; padding: 10px 12px; }
            .kcg-me td { padding: 10px 12px; vertical-align: top; border-top: 1px solid #f0f0f1; }
            .kcg-me tbody tr:hover { background: #f6f9fc; }
            .kcg-me-swatch { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 6px; vertical-align: baseline; border: 1px solid rgba(0,0,0,.15); }
            .kcg-me-title { font-weight: 600; }
            .kcg-me-sub, .kcg-me-muted { color: #646970; font-size: 12px; }
            .kcg-me-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
            .kcg-me-badge.service { background: #e7f5ea; color: #1a6b2c; }
            .kcg-me-badge.event { background: #e6f0fb; color: #1d5fa8; }
            .kcg-me-thumb { width: 40px; height: 40px; object-fit: cover; border-radius: 4px; display: block; }
            .kcg-me-empty { padding: 24px; text-align: center; color: #646970; }
        </style>
        <div class="postbox kcg-me" style="margin-bottom: 20px;">
            <h2 class="hndle"><span>Merged events (<span id="kcg-me-count"><?php echo esc_html(count($merged_events)); ?></span> of <?php echo esc_html(count($merged_events)); ?>)</span></h2>
            <div class="inside">
                <?php if (empty($merged_events)): ?>
                    <p class="kcg-me-empty">No merged events yet. Add an API key and refresh the cache.</p>
                <?php else: ?>
                    <div class="kcg-me-toolbar">
                        <input type="search" id="kcg-me-search" placeholder="Search merged events…">
                        <select id="kcg-me-source">
                            <option value="">All sources</option>
                            <option value="service">Services</option>
                            <option value="event">Events</option>
                        </select>
                        <select id="kcg-me-group">
                            <option value="">All service types / calendars</option>
                            <?php foreach (array_keys($groups) as $group): ?>
                                <option value="<?php echo esc_attr(strtolower($group)); ?>"><?php echo esc_html($group); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="kcg-me-wrap">
                        <table id="kcg-me-table">
                            <thead>
                                <tr>
                                    <th></th><th>When</th><th>Title</th><th>Source</th><th>Service type</th><th>Calendar</th><th>Location</th><th>Links</th><th>ID</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($merged_events as $item):
                                    $dt = KCG_Elvanto_Datetime::from_local($item['date'] ?? '', $item['time'] ?? '');
                                    $day = $dt ? wp_date($date_format, $dt->getTimestamp(), $dt->getTimezone()) : (string) ($item['date'] ?? '');
                                    $time = $dt && !empty($item['time']) ? wp_date($time_format, $dt->getTimestamp(), $dt->getTimezone()) : (empty($item['date']) ? '' : 'All day');
                                    $group = $item['source'] === 'service' ? $item['service_type'] : $item['calendar_name'];
                                    $search = strtolower(implode(' ', array($item['title'], $item['subtitle'], $item['service_type'], $item['calendar_name'], $item['location'], $day)));
                                    ?>
                                    <tr data-source="<?php echo esc_attr($item['source']); ?>" data-group="<?php echo esc_attr(strtolower($group)); ?>" data-search="<?php echo esc_attr($search); ?>">
                                        <td><?php if (!empty($item['picture'])): ?><img class="kcg-me-thumb" src="<?php echo esc_url($item['picture']); ?>" alt="" loading="lazy"><?php endif; ?></td>
                                        <td style="white-space: nowrap;"><strong><?php echo esc_html($day); ?></strong><div class="kcg-me-muted"><?php echo esc_html($time); ?></div></td>
                                        <td>
                                            <?php if (!empty($item['color'])): ?><span class="kcg-me-swatch" style="background: <?php echo esc_attr($item['color']); ?>;" title="<?php echo esc_attr($item['color']); ?>"></span><?php endif; ?>
                                            <span class="kcg-me-title"><?php echo esc_html($item['title'] !== '' ? $item['title'] : 'Untitled'); ?></span>
                                            <?php if ($item['subtitle'] !== ''): ?><div class="kcg-me-sub"><?php echo esc_html($item['subtitle']); ?></div><?php endif; ?>
                                        </td>
                                        <td><span class="kcg-me-badge <?php echo esc_attr($item['source']); ?>"><?php echo esc_html($item['source']); ?></span></td>
                                        <td><?php echo $item['service_type'] !== '' ? esc_html($item['service_type']) : '<span class="kcg-me-muted">—</span>'; ?></td>
                                        <td><?php echo $item['calendar_name'] !== '' ? esc_html($item['calendar_name']) : '<span class="kcg-me-muted">—</span>'; ?></td>
                                        <td><?php echo $item['location'] !== '' ? esc_html($item['location']) : '<span class="kcg-me-muted">—</span>'; ?></td>
                                        <td style="white-space: nowrap;">
                                            <?php if (!empty($item['link_info'])): ?><a href="<?php echo esc_url($item['link_info']); ?>" target="_blank" rel="noopener">Info</a><?php endif; ?>
                                            <?php if (!empty($item['link_register'])): ?> · <a href="<?php echo esc_url($item['link_register']); ?>" target="_blank" rel="noopener">Register</a><?php endif; ?>
                                            <?php if (empty($item['link_info']) && empty($item['link_register'])): ?><span class="kcg-me-muted">—</span><?php endif; ?>
                                        </td>
                                        <td><code style="font-size: 11px;"><?php echo esc_html(substr((string) $item['id'], 0, 8)); ?></code></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p class="kcg-me-empty" id="kcg-me-none" hidden>No merged events match your filters.</p>
                    </div>
                    <script>
                    (function () {
                        var search = document.getElementById('kcg-me-search'),
                            source = document.getElementById('kcg-me-source'),
                            group = document.getElementById('kcg-me-group'),
                            rows = document.querySelectorAll('#kcg-me-table tbody tr'),
                            count = document.getElementById('kcg-me-count'),
                            none = document.getElementById('kcg-me-none');
                        function apply() {
                            var q = search.value.trim().toLowerCase(), shown = 0;
                            rows.forEach(function (row) {
                                var ok = (!q || row.dataset.search.indexOf(q) !== -1)
                                    && (!source.value || row.dataset.source === source.value)
                                    && (!group.value || row.dataset.group === group.value);
                                row.hidden = !ok;
                                if (ok) { shown++; }
                            });
                            count.textContent = shown;
                            none.hidden = shown !== 0;
                        }
                        [search, source, group].forEach(function (el) { el.addEventListener('input', apply); });
                    })();
                    </script>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
