<?php

defined( 'ABSPATH' ) || exit;

/**
 * Beheert de instellingenpagina voor de KerkPoint plugin.
 */
class KAD_Admin_Settings {

    private $option_group = 'kad_settings_group';
    private $option_name  = 'kad_settings';
    private $menu_slug    = 'kerk-api-display';
    private $rss_slug     = 'kad-rss-settings';
    private $api_handler;

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
        add_action( 'admin_init', array( $this, 'handle_cache_clear' ) );
    }

    /**
     * Voeg de instellingenpagina's toe aan het menu.
     */
    public function add_plugin_page() {
        // Hoofdmenu: KerkPoint
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

        // Submenu: API Instellingen (zelfde als hoofdmenu)
        add_submenu_page(
            $this->menu_slug,
            __('API Instellingen', KERKPOINT_TEXT_DOMAIN),
            __('API Instellingen', KERKPOINT_TEXT_DOMAIN),
            'manage_options',
            $this->menu_slug,
            array( $this, 'create_admin_page' )
        );

        // Submenu: RSS Diensten (eigen pagina)
        add_submenu_page(
            $this->menu_slug,
            __('RSS Diensten', KERKPOINT_TEXT_DOMAIN),
            __('RSS Diensten', KERKPOINT_TEXT_DOMAIN),
            'manage_options',
            $this->rss_slug,
            array( $this, 'create_rss_admin_page' )
        );
    }

    /**
     * Render API pagina
     */
    public function create_admin_page() {
        $this->render_settings_form($this->menu_slug, 'API Verbindingsinstellingen');
    }

    /**
     * Render RSS pagina
     */
    public function create_rss_admin_page() {
        $this->render_settings_form($this->rss_slug, 'RSS Feed Instellingen');
    }

    /**
     * Hulpmethode voor het renderen van de formulieren
     */
    private function render_settings_form($slug, $title) {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( $title ); ?></h1>
            <form method="post" action="options.php">
            <?php
                settings_fields( $this->option_group );
                do_settings_sections( $slug );
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

        // --- SECTIE: API ---
        add_settings_section(
            'kad_api_settings_section',
            'API Verbindingsinstellingen',
            array( $this, 'print_api_section_info' ),
            $this->menu_slug
        );

        add_settings_field(
            'api_url', 'Basis API URL', array( $this, 'create_input_field' ), 
            $this->menu_slug, 'kad_api_settings_section', 
            array( 'id' => 'api_url', 'type' => 'url', 'desc' => 'De basis URL van de API (bijv. https://api.domein.nl)' )
        );

        add_settings_field(
            'api_token', 'Bearer Token', array( $this, 'create_input_field' ), 
            $this->menu_slug, 'kad_api_settings_section', 
            array( 'id' => 'api_token', 'type' => 'text', 'desc' => 'De vereiste Sanctum Bearer Token.' )
        );

        add_settings_field(
            'cache_duration', 'Cache Duur (minuten)', array( $this, 'create_input_field' ), 
            $this->menu_slug, 'kad_api_settings_section', 
            array( 'id' => 'cache_duration', 'type' => 'number', 'desc' => 'Standaard: 60.' )
        );

        // --- SECTIE: RSS ---
        add_settings_section(
            'kad_rss_settings_section',
            'RSS Instellingen',
            array( $this, 'print_rss_section_info' ),
            $this->rss_slug
        );

        add_settings_field(
            'rss_playlist_id', 'Playlist ID', array( $this, 'create_input_field' ), 
            $this->rss_slug, 'kad_rss_settings_section', 
            array( 'id' => 'rss_playlist_id', 'type' => 'text', 'desc' => 'Numerieke ID (bijv. 246).' )
        );

        // --- SECTIE: CACHE BEHEER (op API pagina) ---
        add_settings_section(
            'kad_cache_section',
            'Cache Beheer',
            array($this, 'print_cache_section_info'),
            $this->menu_slug
        );

        add_settings_field(
            'kad_cache_status', 'Cache Status', array($this, 'display_cache_controls'),
            $this->menu_slug, 'kad_cache_section'
        );
    }

    /**
     * Sanitize input
     */
    public function sanitize( $input ) {
        $new_input = array();
        if ( isset( $input['api_url'] ) ) $new_input['api_url'] = esc_url_raw( $input['api_url'] );
        if ( isset( $input['api_token'] ) ) $new_input['api_token'] = sanitize_text_field( $input['api_token'] );
        if ( isset( $input['rss_playlist_id'] ) ) $new_input['rss_playlist_id'] = sanitize_text_field( $input['rss_playlist_id'] );
        if ( isset( $input['cache_duration'] ) ) $new_input['cache_duration'] = absint( $input['cache_duration'] ) * MINUTE_IN_SECONDS; 
        return $new_input;
    }

    /**
     * Generieke input velden
     */
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

    public function print_api_section_info() { print 'Instellingen voor de API verbinding.'; }
    public function print_rss_section_info() { print 'Instellingen voor de Kerkdienstgemist RSS feed.'; }
    public function print_cache_section_info() { echo '<p>Beheer hier de lokale cache.</p>'; }

    public function display_cache_controls() {
        $last_cleared = get_option('kad_cache_last_cleared');
        
        if (isset($_GET['cache_cleared']) && $_GET['cache_cleared'] === 'true') {
            echo '<div class="notice notice-success is-dismissible inline"><p>Cache succesvol gewist!</p></div>';
        }

        if ($last_cleared) {
            $formatted_time = date_i18n(get_option('date_format') . ' H:i:s', $last_cleared);
            echo '<p>Laatste cache-clear: <strong>' . esc_html($formatted_time) . '</strong></p>';
        } else {
            echo '<p>De cache is nog nooit geleegd.</p>';
        }

        echo '<form method="post" action="">';
        wp_nonce_field('kad_clear_cache_nonce', 'kad_nonce');
        echo '<input type="hidden" name="kad_action" value="clear_cache" />';
        submit_button('Cache Nu Wissen', 'secondary', 'kad_clear_cache_submit', false);
        echo '</form>';
    }

    /**
     * Verwerkt het wissen van de cache buiten de HTML om.
     */
    public function handle_cache_clear() {
        if (isset($_POST['kad_action']) && $_POST['kad_action'] === 'clear_cache') {
            if (check_admin_referer('kad_clear_cache_nonce', 'kad_nonce') && current_user_can('manage_options')) {
                
                // 1. Wis de WordPress Transients (de cache van je RSS-feed)
                // Vervang 'jouw_transient_naam' door de naam die je in fetch_feed gebruikt 
                // OF wis de feed transients die WordPress automatisch maakt:
                global $wpdb;
                $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_feed_%'");
                $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_feed_%'");

                // 2. Update de tijdstempel
                update_option('kad_cache_last_cleared', time());

                // 3. Redirect terug om dubbele verzendingen te voorkomen
                wp_redirect(add_query_arg('cache_cleared', 'true', wp_get_referer()));
                exit;
            }
        }
    }
}