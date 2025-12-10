<?php

defined( 'ABSPATH' ) || exit;

require_once KERKPOINT_PLUGIN_DIR . 'includes/frontend/shortcodes/kp-collecte.php';
require_once KERKPOINT_PLUGIN_DIR . 'includes/frontend/shortcodes/kp-diensten.php';
require_once KERKPOINT_PLUGIN_DIR . 'includes/frontend/shortcodes/kp-volgende-diensten.php';

class KAD_Frontend_Display {
    private $api_handler;

    public function __construct( KAD_API_Handler $api_handler ) {
        $this->api_handler = $api_handler;

        new KP_Collecte( $this->api_handler );
        new KP_Diensten( $this->api_handler );
        new KP_Volgende_Diensten( $this->api_handler );

        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function enqueue_assets() {
        wp_enqueue_style( 'kp-frontend-style', KERKPOINT_PLUGIN_URL . 'assets/css/style.css', array(), KERKPOINT_VERSION );
    }
}
