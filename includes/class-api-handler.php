<?php

defined( 'ABSPATH' ) || exit;

/**
 * Verantwoordelijk voor de communicatie met de externe API, 
 * inclusief authenticatie, caching en foutafhandeling.
 */
class KAD_API_Handler {

    private $api_token;
    private $base_api_url;
    private $cache_duration; // In seconden

    public function __construct() {
        // Laad de opgeslagen instellingen uit de database
        $settings = get_option( 'kad_settings', array() );
        
        $this->api_token = $settings['api_token'] ?? '';
        // Zorgt ervoor dat de URL eindigt op een slash (bijv. https://api.domein.nl/)
        $this->base_api_url = trailingslashit( $settings['api_url'] ?? '' ); 
        // Standaard 1 uur (3600 seconden)
        $this->cache_duration = (int) ($settings['cache_duration'] ?? 3600); 
    }

    /**
     * Retourneert de ingestelde cache duur (in seconden).
     * @return int
     */
    public function get_cache_duration() {
        return $this->cache_duration;
    }

    /**
     * Retourneert de status van de laatste API-oproep voor weergave in de admin.
     * @return string
     */
    public function get_last_fetch_status() {
        $timestamp = get_transient( 'kad_last_fetch_timestamp' );
        $error = get_transient( 'kad_last_fetch_error' );
        
        if ( $error ) {
            return 'Fout op ' . date_i18n( 'd-m-Y H:i:s', $timestamp ) . ': ' . $error;
        } elseif ( $timestamp ) {
            return 'Succes op ' . date_i18n( 'd-m-Y H:i:s', $timestamp ) . ' (Data is gecached)';
        } else {
            return 'Nog geen succesvolle oproep gedaan.';
        }
    }

    /**
     * Voert een geauthenticeerde API-oproep uit en handelt caching af.
     *
     * @param string $endpoint Het API-pad (bijv. 'services', 'preachers').
     * @return array|WP_Error De geparseerde data of een WP_Error object.
     */
    private function fetch_and_cache( $endpoint ) {
        $cache_key = 'kad_data_' . sanitize_key( $endpoint );

        // 1. Probeer data uit de cache te halen
        $cached_data = get_transient( $cache_key );
        if ( $cached_data ) {
            return $cached_data;
        }

        if ( empty( $this->base_api_url ) || empty( $this->api_token ) ) {
            return new WP_Error( 'kad_config_error', 'API URL of Bearer Token is niet ingesteld. Controleer de plugin instellingen.' );
        }

        // 2. Geen cache, haal op via API
        $url = $this->base_api_url . 'api/' . $endpoint;

        $response = wp_remote_get( $url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_token, // Authenticatie vereist!
                'Accept'        => 'application/json',
            ),
            'timeout' => 10,
        ) );

        $current_time = time();

        if ( is_wp_error( $response ) ) {
            set_transient( 'kad_last_fetch_timestamp', $current_time, 300 ); 
            set_transient( 'kad_last_fetch_error', $response->get_error_message(), 300 );
            return $response; 
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        $code = wp_remote_retrieve_response_code( $response );

        if ( $code !== 200 ) {
            $error_message = sprintf('API Fout (%d): %s', $code, wp_remote_retrieve_response_message( $response ));
            set_transient( 'kad_last_fetch_timestamp', $current_time, 300 );
            set_transient( 'kad_last_fetch_error', $error_message, 300 );
            return new WP_Error( 'kad_api_error', $error_message );
        }

        // De API respons moet een array zijn
        if ( empty( $data ) || ! is_array( $data ) ) {
            $error_message = 'Fout bij parsen van API data of lege respons.';
            set_transient( 'kad_last_fetch_timestamp', $current_time, 300 );
            set_transient( 'kad_last_fetch_error', $error_message, 300 );
            return new WP_Error( 'kad_parse_error', $error_message );
        }

        // 3. Cache de succesvolle respons
        set_transient( $cache_key, $data, $this->cache_duration );
        set_transient( 'kad_last_fetch_timestamp', $current_time, DAY_IN_SECONDS );
        delete_transient( 'kad_last_fetch_error' );
        
        return $data;
    }

    /**
     * Haalt de diensten (services) op.
     * Route: GET /api/services
     */
    public function get_services_data() {
        return $this->fetch_and_cache( 'services' );
    }

    /**
     * Haalt de collectedoelen (collectiongoals) op.
     * Route: GET /api/collectiongoals
     */
    public function get_collection_goals_data() {
        return $this->fetch_and_cache( 'collectiongoals' );
    }
    
    /**
     * Haalt alle voorgangers (preachers) op.
     * Route: GET /api/preachers
     * @return array|WP_Error
     */
    public function get_preachers_data() {
        return $this->fetch_and_cache( 'preachers' );
    }

    /**
     * Haalt alle gebouwen (buildings) op.
     * Route: GET /api/buildings
     * @return array|WP_Error
     */
    public function get_buildings_data() {
        return $this->fetch_and_cache( 'buildings' );
    }

    /**
     * Maakt een lookup array van voorgangers (ID => Naam) voor snelle koppeling.
     * @return array
     */
    public function get_preacher_lookup_map() {
        $preachers_data = $this->get_preachers_data();

        if ( is_wp_error( $preachers_data ) || ! is_array( $preachers_data ) ) {
            return [];
        }

        $lookup = [];
        foreach ( $preachers_data as $preacher ) {
            $id = $preacher['id'] ?? null;
            $name = $preacher['name'] ?? null;
            if ( $id && $name ) {
                $lookup[ (int) $id ] = $name;
            }
        }
        return $lookup;
    }

    /**
     * Maakt een lookup array van voorgangers (Service ID => Naam) voor snelle koppeling.
     *
     * Gaat ervan uit dat de /api/preachers data een lijst is en dat elk item 
     * een veld 'service_id' bevat, en een veld 'name'.
     *
     * @return array
     */
    public function get_service_preacher_map() {
        $preachers_data = $this->get_preachers_data(); // Roept /api/preachers op

        if ( is_wp_error( $preachers_data ) || ! is_array( $preachers_data ) ) {
            return [];
        }

        $lookup = [];
        foreach ( $preachers_data as $preacher ) {
            $service_id = $preacher['service_id'] ?? null;
            $name = $preacher['name'] ?? null;
            if ( $service_id && $name ) {
                $lookup[ (int) $service_id ] = $name;
            }
        }
        return $lookup;
    }

    public function get_building_by_id( $building_id ) {
        $buildings_data = $this->get_buildings_data();

        if ( is_wp_error( $buildings_data ) || ! is_array( $buildings_data ) ) {
            return null;
        }

        foreach ( $buildings_data as $building ) {
            if ( isset( $building['id'] ) && (int) $building['id'] === (int) $building_id ) {
                return $building;
            }
        }

        return null;
    }

    /**
     * Wist handmatig de transient cache voor alle endpoints.
     * @return bool
     */
    public function clear_cache() {
        // Wis alle specifieke datatransients
        delete_transient( 'kad_data_services' );
        delete_transient( 'kad_data_collectiongoals' );
        delete_transient( 'kad_data_preachers' );
        delete_transient( 'kad_data_buildings' );
        
        // Wis de status transients
        delete_transient( 'kad_last_fetch_timestamp' );
        delete_transient( 'kad_last_fetch_error' );
        
        return true;
    }
}