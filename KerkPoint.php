<?php
/**
 * Plugin Name: KerkPoint
 * Plugin URI: https://kerkpoint.nl
 * Description: A Wordpress plugin to get service and collection data from an external API.
 * Version: 1.0.0
 * Author: FlexWave - Flexibel in aanpak, krachtig online
 * Author URI: https://flex-wave.nl
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: kerkpoint
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Global Constanten definieren
define( 'KERKPOINT_VERSION', '1.0.0' );
define( 'KERKPOINT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'KERKPOINT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'KERKPOINT_TEXT_DOMAIN', 'kerkpoint' ); // Nieuwe constante voor text domain

// Laad de benodigde klassen
require_once KERKPOINT_PLUGIN_DIR . 'includes/class-api-handler.php';
require_once KERKPOINT_PLUGIN_DIR . 'includes/class-frontend-display.php';
require_once KERKPOINT_PLUGIN_DIR . 'includes/class-admin-settings.php';

// Laad de externe bibliotheek voor QR-codes
require_once KERKPOINT_PLUGIN_DIR . 'vendor/phpqrcode/qrlib.php';


class KerkPoint {
    
    public function __construct() {
        add_action( 'plugins_loaded', array( $this, 'initialize_components' ) );
    }

    /**
     * Start de componenten van de plugin.
     */
    public function initialize_components() {
        // API Handler initialiseren
        $api_handler = new KAD_API_Handler();

        // Frontend Display (Shortcodes/Blocks)
        new KAD_Frontend_Display( $api_handler );

        // Admin Settings
        if ( is_admin() ) {
            new KAD_Admin_Settings( $api_handler );
        }
    }

    public function enqueue_plugin_styles() {
    // Registreer het style-bestand
    wp_register_style(
        'kp-diensten-style', // Unieke handle
        plugins_url( 'assets/css/kp-diensten-overzicht.css', __FILE__ ), // Pad naar uw CSS-bestand
        array(), // Afhankelijkheden (geen)
        '1.0', // Versie nummer
        'all' // Media type
    );

    // Enqueue de style, dit zorgt ervoor dat het daadwerkelijk geladen wordt
    wp_enqueue_style( 'kp-diensten-style' );
    }
}

// Koppel de functie aan de juiste actie
add_action( 'wp_enqueue_scripts', array( new KerkPoint(), 'enqueue_plugin_styles' ) );
