<?php
/**
 * Admin functionality for KCG Elvanto Service Views.
 *
 * @package KCGElvantoServiceViews
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class KCG_Elvanto_Preaching_Admin {
    
    /**
     * Initialize admin functionality
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_page'), 20);
    }

    /**
     * Add admin menu page
     */
    public function add_admin_page() {
        add_submenu_page(
            'kcg-elvanto-api',
            'Elvanto Service Views',
            'Service Views',
            'manage_options',
            'kcg-service-views',
            array($this, 'admin_page')
        );
    }

    /**
     * Admin page content
     */
    public function admin_page() {
        $services = class_exists('KCG_Elvanto_Cache') ? KCG_Elvanto_Cache::get_services() : array();

        $upcoming_services = array_filter($services, function ($service) {
            if (!is_array($service)) {
                return false;
            }

            $date_value = trim((string) ($service['date'] ?? $service['start_date'] ?? ''));
            if ($date_value === '') {
                return false;
            }

            return strtotime($date_value) > current_time('timestamp');
        });

        usort($upcoming_services, function ($a, $b) {
            $date_a = strtotime((string) ($a['date'] ?? $a['start_date'] ?? ''));
            $date_b = strtotime((string) ($b['date'] ?? $b['start_date'] ?? ''));
            return ($date_a ?: PHP_INT_MAX) <=> ($date_b ?: PHP_INT_MAX);
        });

        $upcoming_services = array_slice($upcoming_services, 0, 5);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="postbox" style="max-width: 800px; margin-bottom: 20px;">
                <h2 class="hndle"><span><?php esc_html_e('Shared provider status', 'kcg-service-views'); ?></span></h2>
                <div class="inside">
                    <p><?php esc_html_e('This plugin reads the shared Elvanto provider cache. Refreshes are managed from the parent Elvanto API page.', 'kcg-service-views'); ?></p>
                    <table class="widefat striped">
                        <tbody>
                            <tr>
                                <th style="width: 180px;">Cached services</th>
                                <td><?php echo esc_html(count($services)); ?></td>
                            </tr>
                            <tr>
                                <th>Preview items</th>
                                <td><?php echo esc_html(count($upcoming_services)); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="postbox" style="max-width: 900px; margin-top: 20px;">
                <h2 class="hndle"><span><?php esc_html_e('Upcoming services', 'kcg-service-views'); ?></span></h2>
                <div class="inside">
                    <?php if (!empty($upcoming_services)): ?>
                        <ul style="margin: 0; padding-left: 20px;">
                            <?php foreach ($upcoming_services as $service): ?>
                                <?php
                                $service_name = trim((string) ($service['name'] ?? $service['title'] ?? 'Untitled'));
                                $service_type_name = trim((string) ($service['service_type']['name'] ?? $service['service_type'] ?? ''));
                                $service_date = trim((string) ($service['date'] ?? $service['start_date'] ?? ''));
                                $location_name = is_array($service['location'] ?? null)
                                    ? trim((string) ($service['location']['name'] ?? ''))
                                    : trim((string) ($service['location'] ?? ''));
                                $formatted_service_date = $service_date !== '' ? wp_date('g:ia | D jS M', kcg_elvanto_parse_service_datetime($service_date)->getTimestamp()) : '';
                                ?>
                                <li style="margin-bottom: 12px;">
                                    <strong><?php echo esc_html($service_name !== '' ? $service_name : 'Untitled'); ?></strong>
                                    <div style="color: #666; font-size: 13px;">
                                        <?php echo esc_html($formatted_service_date !== '' ? $formatted_service_date : $service_date); ?>
                                        <?php if ($service_type_name !== ''): ?>
                                            &nbsp;|&nbsp;<?php echo esc_html($service_type_name); ?>
                                        <?php endif; ?>
                                        <?php if ($location_name !== ''): ?>
                                            &nbsp;|&nbsp;<?php echo esc_html($location_name); ?>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>No upcoming services are currently loaded.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
}
