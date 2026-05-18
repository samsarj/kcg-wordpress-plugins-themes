<?php
/**
 * Plugin Name: KCG Select CTA
 * Plugin URI: https://yourwebsite.com
 * Description: A beautiful call-to-action plugin with auto-scrolling select options and a go button.
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL2
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class KCG_Select_CTA {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('kcg_select_cta', array($this, 'render_shortcode'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
    }
    
    public function init() {
        // Plugin initialization
    }
    
    public function enqueue_scripts() {
        wp_enqueue_style('kcg-select-cta-style', plugin_dir_url(__FILE__) . 'assets/style.css', array(), '1.0.0');
        wp_enqueue_script('kcg-select-cta-script', plugin_dir_url(__FILE__) . 'assets/script-simple.js', array('jquery'), '1.0.0', true);
    }
    
    public function render_shortcode($atts) {
        $atts = shortcode_atts(array(
            'options' => '',
            'button_text' => 'Go',
            'button_url' => '#',
            'speed' => '3000'
        ), $atts);
        
        // Get options from settings or shortcode
        $options = !empty($atts['options']) ? explode(',', $atts['options']) : $this->get_default_options();
        $button_text = $atts['button_text'];
        $button_url = $atts['button_url'];
        $speed = intval($atts['speed']);
        
        ob_start();
        ?>
        <div class="kcg-select-cta-container">
            <div class="kcg-cta-content">
                <span class="kcg-cta-text">I want to</span>
                <div class="kcg-select-wrapper">
                    <select id="kcg-auto-select" class="kcg-auto-select" data-speed="<?php echo esc_attr($speed); ?>">
                        <?php foreach ($options as $index => $option): ?>
                            <option value="<?php echo esc_attr(trim($option)); ?>" <?php echo $index === 0 ? 'selected' : ''; ?>>
                                <?php echo esc_html(trim($option)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <a href="<?php echo esc_url($button_url); ?>" class="kcg-cta-button">
                    <?php echo esc_html($button_text); ?>
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private function get_default_options() {
        $saved_options = get_option('kcg_select_cta_options', '');
        if (!empty($saved_options)) {
            return explode(',', $saved_options);
        }
        
        // Default options
        return array(
            'learn more',
            'get started',
            'book a demo',
            'contact us',
            'see pricing',
            'try for free'
        );
    }
    
    public function add_admin_menu() {
        add_options_page(
            'KCG Select CTA Settings',
            'Select CTA',
            'manage_options',
            'kcg-select-cta',
            array($this, 'admin_page')
        );
    }
    
    public function settings_init() {
        register_setting('kcg_select_cta_settings', 'kcg_select_cta_options');
        register_setting('kcg_select_cta_settings', 'kcg_select_cta_button_text');
        register_setting('kcg_select_cta_settings', 'kcg_select_cta_button_url');
        register_setting('kcg_select_cta_settings', 'kcg_select_cta_speed');
        
        add_settings_section(
            'kcg_select_cta_main_section',
            'Main Settings',
            null,
            'kcg_select_cta_settings'
        );
        
        add_settings_field(
            'kcg_select_cta_options',
            'Select Options (comma separated)',
            array($this, 'options_field_callback'),
            'kcg_select_cta_settings',
            'kcg_select_cta_main_section'
        );
        
        add_settings_field(
            'kcg_select_cta_button_text',
            'Button Text',
            array($this, 'button_text_field_callback'),
            'kcg_select_cta_settings',
            'kcg_select_cta_main_section'
        );
        
        add_settings_field(
            'kcg_select_cta_button_url',
            'Button URL',
            array($this, 'button_url_field_callback'),
            'kcg_select_cta_settings',
            'kcg_select_cta_main_section'
        );
        
        add_settings_field(
            'kcg_select_cta_speed',
            'Auto-scroll Speed (milliseconds)',
            array($this, 'speed_field_callback'),
            'kcg_select_cta_settings',
            'kcg_select_cta_main_section'
        );
    }
    
    public function options_field_callback() {
        $options = get_option('kcg_select_cta_options', 'learn more,get started,book a demo,contact us,see pricing,try for free');
        echo '<input type="text" name="kcg_select_cta_options" value="' . esc_attr($options) . '" class="regular-text" />';
        echo '<p class="description">Enter options separated by commas</p>';
    }
    
    public function button_text_field_callback() {
        $button_text = get_option('kcg_select_cta_button_text', 'Go');
        echo '<input type="text" name="kcg_select_cta_button_text" value="' . esc_attr($button_text) . '" class="regular-text" />';
    }
    
    public function button_url_field_callback() {
        $button_url = get_option('kcg_select_cta_button_url', '#');
        echo '<input type="url" name="kcg_select_cta_button_url" value="' . esc_attr($button_url) . '" class="regular-text" />';
    }
    
    public function speed_field_callback() {
        $speed = get_option('kcg_select_cta_speed', '3000');
        echo '<input type="number" name="kcg_select_cta_speed" value="' . esc_attr($speed) . '" min="1000" max="10000" step="500" />';
        echo '<p class="description">Time in milliseconds between option changes (1000 = 1 second)</p>';
    }
    
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>KCG Select CTA Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('kcg_select_cta_settings');
                do_settings_sections('kcg_select_cta_settings');
                submit_button();
                ?>
            </form>
            
            <div class="card">
                <h2>How to Use</h2>
                <p>Use the shortcode <code>[kcg_select_cta]</code> to display the CTA anywhere on your site.</p>
                <h3>Shortcode Parameters:</h3>
                <ul>
                    <li><code>options</code> - Custom options (comma separated)</li>
                    <li><code>button_text</code> - Custom button text</li>
                    <li><code>button_url</code> - Custom button URL</li>
                    <li><code>speed</code> - Custom scroll speed in milliseconds</li>
                </ul>
                <h3>Examples:</h3>
                <p><code>[kcg_select_cta]</code> - Uses default settings</p>
                <p><code>[kcg_select_cta options="buy now,learn more,contact" button_text="Start Now" button_url="/contact"]</code></p>
            </div>
        </div>
        <?php
    }
}

// Initialize the plugin
new KCG_Select_CTA();