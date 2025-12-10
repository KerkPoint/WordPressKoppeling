<?php

defined( 'ABSPATH' ) || exit;

/**
 * Beheert de instellingenpagina voor de KerkPoint plugin.
 */
class KAD_Admin_Settings {

    private $option_group = 'kad_settings_group';
    private $option_name  = 'kad_settings';
    private $menu_slug    = 'kerk-api-display';

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
    }

    /**
     * Voeg de instellingenpagina toe aan het menu.
     */
    public function add_plugin_page() {
        add_menu_page(
            __('KerkPoint', KERKPOINT_TEXT_DOMAIN),
            __('KerkPoint', KERKPOINT_TEXT_DOMAIN),
            'manage_options',
            $this->menu_slug,
            array( $this, 'create_admin_page' ),
            plugin_dir_url( __FILE__ ) . '../assets/img/KerkPoint20x20.png',
            2,
            60
        );

        add_submenu_page(
            $this->menu_slug,
            __('KerkPoint Instellingen', KERKPOINT_TEXT_DOMAIN),
            __('Instellingen', KERKPOINT_TEXT_DOMAIN),
            'manage_options',
            $this->menu_slug,
            array( $this, 'create_admin_page' )
        );
    }

    /**
     * Creëer de inhoud van de beheerderspagina.
     */
    public function create_admin_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form method="post" action="options.php">
            <?php
                settings_fields( $this->option_group );
                do_settings_sections( $this->menu_slug );
                submit_button();
            ?>
            </form>
        </div>
        <?php
    }

    /**
     * Registreer en definieer de instellingen.
     */
    public function page_init() {
        register_setting(
            $this->option_group,
            $this->option_name,
            array( $this, 'sanitize' ) 
        );

        add_settings_section(
            'kad_api_settings_section',
            'API Verbindingsinstellingen',
            array( $this, 'print_section_info' ),
            $this->menu_slug
        );

        $fields = array(
            'api_url' => array('label' => 'Basis API URL', 'type' => 'url', 'desc' => 'De basis URL van de API (bijv. https://api.domein.nl)'),
            'api_token' => array('label' => 'Bearer Token (Sanctum)', 'type' => 'text', 'desc' => 'De vereiste Sanctum Bearer Token voor authenticatie.'),
            'cache_duration' => array('label' => 'Cache Duur (minuten)', 'type' => 'number', 'desc' => 'Hoe lang de API-data wordt opgeslagen in de cache (minuten). Standaard: 60.'),
        );

        foreach ( $fields as $id => $field ) {
            add_settings_field(
                $id,
                $field['label'],
                array( $this, 'create_input_field' ),
                $this->menu_slug,
                'kad_api_settings_section',
                array( 'id' => $id, 'type' => $field['type'], 'desc' => $field['desc'] )
            );
        }
    }

    public function sanitize( $input ) {
        $new_input = array();
        if ( isset( $input['api_url'] ) ) $new_input['api_url'] = esc_url_raw( $input['api_url'] );
        if ( isset( $input['api_token'] ) ) $new_input['api_token'] = sanitize_text_field( $input['api_token'] );
        if ( isset( $input['cache_duration'] ) ) $new_input['cache_duration'] = absint( $input['cache_duration'] ) * MINUTE_IN_SECONDS; 
        return $new_input;
    }

    public function create_input_field( $args ) {
        $options = get_option( $this->option_name );
        $value = $options[ $args['id'] ] ?? '';

        if ($args['id'] === 'cache_duration') $value = (int) $value / MINUTE_IN_SECONDS; 

        printf(
            '<input type="%s" id="%s" name="%s[%s]" value="%s" class="regular-text" />',
            esc_attr( $args['type'] ),
            esc_attr( $args['id'] ),
            esc_attr( $this->option_name ),
            esc_attr( $args['id'] ),
            esc_attr( $value )
        );
        printf( '<p class="description">%s</p>', esc_html( $args['desc'] ) );
    }

    public function print_section_info() {
        print 'Voer hier de basis-URL en de Bearer-token in voor de API-verbinding.';
    }
}
