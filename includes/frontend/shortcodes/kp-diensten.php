<?php
defined( 'ABSPATH' ) || exit;

class KP_Diensten {
    private $api_handler;

    public function __construct( $api_handler ) {
        $this->api_handler = $api_handler;
        add_shortcode( 'kp_diensten', array( $this, 'render_diensten_overzicht' ) );
    }

    public function render_diensten_overzicht( $atts ) {
        $services_data = $this->api_handler->get_services_data();
        $preachers_map = $this->api_handler->get_service_preacher_map();

        if ( is_wp_error( $services_data ) ) {
            $msg = current_user_can( 'manage_options' ) 
                ? 'ADMIN OPMERKING: ' . esc_html( $services_data->get_error_message() )
                : 'Er kon geen dienstenoverzicht worden opgehaald.';
            return '<p class="kp-error">' . $msg . '</p>';
        }

        $grouped_services = [];
        $gebouwen_data = [];
        if ( is_array( $services_data ) ) {
            foreach ( $services_data as $dienst ) {
                $location_id = $dienst['location'] ?? null;
                if ( $location_id ) {
                    if ( ! isset( $gebouwen_data[$location_id] ) ) {
                        $gebouwen_data[$location_id] = $this->api_handler->get_building_by_id( $location_id );
                    }
                    $grouped_services[$location_id][] = $dienst;
                } else {
                    $grouped_services[0][] = $dienst; 
                }
            }
        }
        
        ob_start();
        ?>
        <div class="kp-diensten-overzicht">
            <h2>Diensten & Voorgangers</h2>
            <?php if ( ! empty( $grouped_services ) ) : ?>
                <div class="kp-services-columns">
                    <?php foreach ( $grouped_services as $location_id => $services_in_group ) :
                        usort($services_in_group, function($a, $b) {
                            $t1 = strtotime(($a['date'] ?? '') . ' ' . ($a['start_time'] ?? ''));
                            $t2 = strtotime(($b['date'] ?? '') . ' ' . ($b['start_time'] ?? ''));
                            return $t1 <=> $t2;
                        });
                        $building_name = 'Onbekend Gebouw';
                        if ( $location_id !== 0 && isset( $gebouwen_data[$location_id]['name'] ) ) {
                            $building_name = esc_html( $gebouwen_data[$location_id]['name'] );
                        } elseif ( $location_id === 0 ) {
                            $building_name = 'Overige Diensten';
                        }
                    ?>
                    <div class="kp-service-column kp-location-<?= esc_attr($location_id); ?>">
                        <h3><?= $building_name; ?></h3>
                        <table class="kp-service-table">
                            <thead><tr><th>Datum</th><th>Tijd</th><th>Voorganger</th></tr></thead>
                            <tbody>
                            <?php $last_date = null;
                            foreach ( $services_in_group as $dienst ) :
                                $service_id = $dienst['id'] ?? null;
                                $voorganger_naam = $service_id && isset( $preachers_map[(int)$service_id] ) ? $preachers_map[(int)$service_id] : 'Onbekend';
                                $datum = $dienst['date'] ?? 'N.n.b.';
                                $tijd  = $dienst['start_time'] ?? '';
                                $formatted_date = date_i18n(get_option('date_format'), strtotime($datum));
                            ?>
                                <tr>
                                    <td>
                                        <?php 
                                        if ($last_date !== $datum) {
                                            echo esc_html($formatted_date);
                                            $last_date = $datum;
                                        }
                                        ?>
                                    </td>
                                    <td><?= esc_html($tijd); ?></td>
                                    <td><strong><?= esc_html($voorganger_naam); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p class="kp-empty-state">Er zijn momenteel geen geplande diensten bekend.</p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
}