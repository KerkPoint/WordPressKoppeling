<?php
/**
 * Plugin Name: KerkPoint
 * Plugin URI: https://kerkpoint.nl
 * Description: A Wordpress plugin to get service and collection data from an external API.
 * Version: 1.0.1
 * Author: FlexWave
 * Author URI: https://flex-wave.nl
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: kerkpoint
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'KERKPOINT_VERSION', '1.0.0' );
define( 'KERKPOINT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'KERKPOINT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'KERKPOINT_TEXT_DOMAIN', 'kerkpoint' );

require_once KERKPOINT_PLUGIN_DIR . 'includes/api/api-handler.php';
require_once KERKPOINT_PLUGIN_DIR . 'includes/frontend/frontend-display.php';
require_once KERKPOINT_PLUGIN_DIR . 'includes/admin/admin-settings.php';
require_once KERKPOINT_PLUGIN_DIR . 'includes/admin/dashboard-widget.php';

// Laad de externe bibliotheek voor QR-codes
require_once KERKPOINT_PLUGIN_DIR . 'vendor/phpqrcode/qrlib.php';


class KerkPoint {

    public function __construct() {
        add_action( 'plugins_loaded', array( $this, 'initialize_components' ) );

        // Voeg link toe aan admin pluginlijst
        add_filter( 'plugin_row_meta', array( $this, 'add_author_link_to_plugin_row' ), 10, 2 );

        // Voeg link toe in frontend <head>
        add_action('wp_head', array( $this, 'add_author_link_head' ));
    }

    public function initialize_components() {
        $api_handler = new KAD_API_Handler();
        new KAD_Frontend_Display( $api_handler );

        if ( is_admin() ) {
            new KAD_Admin_Settings( $api_handler );
            new KAD_Dashboard_Widget( $api_handler );
        }
    }

    public function enqueue_plugin_styles() {
        wp_register_style(
            'kp-diensten-style',
            plugins_url( 'assets/css/kp-diensten-overzicht.css', __FILE__ ),
            array(),
            '1.0',
            'all'
        );
        wp_enqueue_style( 'kp-diensten-style' );
    }

    // Admin pluginlijst link
    public function add_author_link_to_plugin_row($links, $file) {
        if ( plugin_basename(__FILE__) === $file ) {
            $links[] = '<a href="https://kerkpoint.nl" target="_blank">Auteur site</a>';
        }
        return $links;
    }

    // Frontend <head> link
    public function add_author_link_head() {
        echo '<link rel="author" href="https://kerkpoint.nl">' . "\n";
    }
}

add_action( 'wp_enqueue_scripts', array( new KerkPoint(), 'enqueue_plugin_styles' ) );