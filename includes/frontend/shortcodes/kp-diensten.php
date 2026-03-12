<?php
defined( 'ABSPATH' ) || exit;

class KP_Diensten {
    private $api_handler;

    /**
     * Constructor: Registreert de shortcode.
     */
    public function __construct( $api_handler ) {
        $this->api_handler = $api_handler;
        add_shortcode( 'kp_diensten', array( $this, 'render_diensten_overzicht' ) );
    }

    /**
     * Rendert het overzicht van diensten.
     */
    public function render_diensten_overzicht( $atts ) {
        $atts = shortcode_atts( array(
            'dagen_vooruit' => 90, 
        ), $atts, 'kp_diensten' );

        $days_forward = max( 0, (int) $atts['dagen_vooruit'] );
        $services_data = $this->api_handler->get_services_data();
        $preachers_map = $this->api_handler->get_service_preacher_map();

        if ( is_wp_error( $services_data ) ) {
            $msg = current_user_can( 'manage_options' ) 
                ? 'ADMIN: ' . esc_html( $services_data->get_error_message() )
                : 'Er kon geen dienstenoverzicht worden opgehaald.';
            return '<p class="kp-error">' . $msg . '</p>';
        }

        $today      = new DateTime( 'today' );
        $limit_date = ( clone $today )->modify( "+$days_forward days" );
        
        $grouped_services = [];
        $gebouwen_data    = [];

        if ( is_array( $services_data ) ) {
            foreach ( $services_data as $dienst ) {
                if ( empty( $dienst['date'] ) ) continue;

                $service_date = new DateTime( $dienst['date'] );

                // Filter: alleen toekomstige diensten binnen de range
                if ( $service_date >= $today && $service_date <= $limit_date ) {
                    $location_id = $dienst['location'] ?? 0;

                    if ( $location_id && ! isset( $gebouwen_data[ $location_id ] ) ) {
                        $gebouwen_data[ $location_id ] = $this->api_handler->get_building_by_id( $location_id );
                    }
                    $grouped_services[ $location_id ][] = $dienst;
                }
            }
        }

        ob_start();
        ?>
        <div class="kp-diensten-wrapper">
            <header class="kp-header">
                <h2>Diensten & Voorgangers</h2>
                <span class="kp-subtitle"><?= esc_html( $days_forward ); ?> dagen vooruit</span>
            </header>

            <?php if ( ! empty( $grouped_services ) ) : ?>
                <div class="kp-services-grid">
                    <?php foreach ( $grouped_services as $location_id => $services_in_group ) : 
                        // Sorteer diensten chronologisch
                        usort( $services_in_group, function( $a, $b ) {
                            return strtotime( $a['date'] . ' ' . ($a['start_time'] ?? '') ) <=> 
                                   strtotime( $b['date'] . ' ' . ($b['start_time'] ?? '') );
                        });

                        $building_name = ( $location_id !== 0 && isset( $gebouwen_data[ $location_id ]['name'] ) ) 
                            ? $gebouwen_data[ $location_id ]['name'] 
                            : ( $location_id === 0 ? 'Overige Diensten' : 'Onbekend Gebouw' );
                    ?>
                        <section class="kp-location-card">
                            <h3><?= esc_html( $building_name ); ?></h3>
                            <div class="kp-table-responsive">
                                <table class="kp-service-table">
                                    <thead>
                                        <tr>
                                            <th>Datum</th>
                                            <th>Tijd & Info</th>
                                            <th>Voorganger</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $last_date = null;
                                        foreach ( $services_in_group as $dienst ) : 
                                            $service_id = $dienst['id'] ?? 0;
                                            $voorganger = $preachers_map[ (int) $service_id ] ?? 'Nog niet bekend';
                                            $datum_raw  = $dienst['date'];
                                            $tijd       = substr( $dienst['start_time'] ?? '', 0, 5 );
                                            $formatted_date = date_i18n( get_option( 'date_format' ), strtotime( $datum_raw ) );
                                        ?>
                                            <tr>
                                                <td class="kp-col-date">
                                                    <?php if ( $last_date !== $datum_raw ) : ?>
                                                        <strong><?= esc_html( $formatted_date ); ?></strong>
                                                        <?php $last_date = $datum_raw; ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="kp-col-time">
                                                    <span class="kp-time"><?= esc_html( $tijd ); ?></span>
                                                    <?php if ( ! empty( $dienst['description'] ) ) : ?>
                                                        <div class="kp-description"><?= esc_html( $dienst['description'] ); ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="kp-col-preacher">
                                                    <strong><?= esc_html( $voorganger ); ?></strong>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p class="kp-empty-state">Er zijn momenteel geen geplande diensten voor de komende periode.</p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}