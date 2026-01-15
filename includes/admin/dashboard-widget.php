<?php

defined( 'ABSPATH' ) || exit;

/**
 * Dashboard widget voor KerkPoint cache-status
 */
class KAD_Dashboard_Widget {

    private $api_handler;

    public function __construct( KAD_API_Handler $api_handler ) {
        $this->api_handler = $api_handler;
        add_action('wp_dashboard_setup', array($this, 'register_widget'));
    }

    public function register_widget() {
        wp_add_dashboard_widget(
            'kad_cache_status_widget',
            'KerkPoint Cache Status',
            array($this, 'display_widget')
        );
    }

    public function display_widget() {
        echo '<img src="' . esc_url(plugin_dir_url(__FILE__) . '../../assets/img/KerkPointIcon.png') . '" alt="KerkPoint" style="height: 40px; margin-right: 8px;" /> ';
        $last_cleared = get_option('kad_cache_last_cleared');
        if ( $last_cleared ) {
            $formatted_time = date_i18n(get_option('date_format') . ' H:i:s', $last_cleared);
            echo '<p>Laatste cache-clear: <strong>' . esc_html($formatted_time) . '</strong></p>';
        } else {
            echo '<p>De cache is nog nooit geleegd.</p>';
        }

        $last_api_fetch = $this->api_handler->get_last_fetch_status();
        if ( $last_api_fetch ) {
            echo '<p>Laatste API-oproep: <strong>' . esc_html($last_api_fetch) . '</strong></p>';
        } else {
            echo '<p>Er is nog geen API-oproep gedaan.</p>';
        }

        echo '<form method="post">';
        echo '<input type="hidden" name="kad_action" value="clear_cache" />';
        wp_nonce_field('kad_clear_cache_nonce', 'kad_nonce');
        submit_button('Cache Nu Wissen', 'secondary', 'kad_clear_cache_submit');

        if (isset($_POST['kad_action']) && $_POST['kad_action'] === 'clear_cache') {
            if (check_admin_referer('kad_clear_cache_nonce', 'kad_nonce') && current_user_can('manage_options')) {
                if ($this->api_handler->clear_cache()) {
                    update_option('kad_cache_last_cleared', time());
                    echo '<div class="notice notice-success is-dismissible"><p>Cache succesvol gewist!</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>Fout bij het wissen van de cache.</p></div>';
                }
            }
        }
    }
}
