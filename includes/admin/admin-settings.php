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
            plugin_dir_url( __FILE__ ) . '../../assets/img/KerkPoint20x20.png',
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

        add_settings_section(
            'kad_cache_section',
            'Cache Beheer',
            array($this, 'print_cache_section_info'),
            $this->menu_slug
        );

        add_settings_field(
            'kad_cache_status',
            'Cache Status',
            array($this, 'display_cache_controls'),
            $this->menu_slug,
            'kad_cache_section'
        );
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

    // Methode voor de sectie-informatie
    public function print_cache_section_info() {
        echo '<p>Hier kunt u de cache-status bekijken en de cache handmatig wissen.</p>';
    }

    // Methode voor de velden (status + knop)
    public function display_cache_controls() {
        $last_cleared = get_option('kad_cache_last_cleared');
        if ($last_cleared) {
            $formatted_time = date_i18n(get_option('date_format') . ' H:i:s', $last_cleared);
            echo '<p>Laatste cache-clear: <strong>' . esc_html($formatted_time) . '</strong></p>';
        } else {
            echo '<p>De cache is nog nooit geleegd.</p>';
        }

        // API fetch status (als je KAD_API_Handler injecteert)
        if ( isset($this->api_handler) && method_exists($this->api_handler, 'get_last_fetch_status') ) {
            $last_api_fetch = $this->api_handler->get_last_fetch_status();
            if ($last_api_fetch) {
                echo '<p>Laatste API-oproep: <strong>' . esc_html($last_api_fetch) . '</strong></p>';
            } else {
                echo '<p>Er is nog geen API-oproep gedaan.</p>';
            }
        }

        // Cache clear knop
        echo '<form method="post">';
        echo '<input type="hidden" name="kad_action" value="clear_cache" />';
        wp_nonce_field('kad_clear_cache_nonce', 'kad_nonce');
        submit_button('Cache Nu Wissen', 'secondary', 'kad_clear_cache_submit');
        
        if (isset($_POST['kad_action']) && $_POST['kad_action'] === 'clear_cache') {
            if (check_admin_referer('kad_clear_cache_nonce', 'kad_nonce') && current_user_can('manage_options')) {
                if ( isset($this->api_handler) && method_exists($this->api_handler, 'clear_cache') && $this->api_handler->clear_cache() ) {
                    update_option('kad_cache_last_cleared', time());
                    echo '<div class="notice notice-success is-dismissible"><p>Cache succesvol gewist!</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>Fout bij het wissen van de cache.</p></div>';
                }
            }
        }
    }
}
