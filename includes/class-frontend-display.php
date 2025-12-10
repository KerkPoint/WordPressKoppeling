<?php

defined( 'ABSPATH' ) || exit;

class KAD_Frontend_Display {

    private $api_handler;

    public function __construct( KAD_API_Handler $api_handler ) {
        $this->api_handler = $api_handler;
        add_action( 'init', array( $this, 'register_shortcodes' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function register_shortcodes() {
        add_shortcode( 'kp_collecte', array( $this, 'render_collecte_doelen' ) );
        add_shortcode( 'kp_diensten', array( $this, 'render_diensten_overzicht' ) );
        add_shortcode( 'kp_volgende_diensten', array( $this, 'render_volgende_diensten' ) );
    }

    public function enqueue_assets() {
        wp_enqueue_style( 
            'kp-frontend-style', 
            KERKPOINT_PLUGIN_URL . 'assets/css/style.css', 
            array(), 
            KERKPOINT_VERSION 
        );
    }
    
    private function generate_qr_code( $data, $size = 5, $margin = 2 ) {
        if ( empty( $data ) ) {
            return '<span class="kp-qr-error">QR Link mist.</span>';
        }
        ob_start();
        try {
            // Vereist QR Code Library (qrlib.php)
            QRcode::png( $data, false, QR_ECLEVEL_L, $size, $margin );
            $image_data = ob_get_clean();
            if ( empty( $image_data ) ) {
                return '<span class="kp-qr-error">Generatie mislukt.</span>';
            }
            $base64 = base64_encode( $image_data );
            return sprintf(
                '<img src="data:image/png;base64,%s" alt="QR Code voor %s" class="kp-qr-image" />',
                $base64,
                esc_attr( $data )
            );
        } catch ( Exception $e ) {
            ob_end_clean(); 
            return '<span class="kp-qr-error">QR Fout: ' . esc_html( $e->getMessage() ) . '</span>';
        }
    }

    public function render_collecte_doelen( $atts ) {
        $data = $this->api_handler->get_collection_goals_data();
        if ( is_wp_error( $data ) ) {
            $msg = current_user_can( 'manage_options' ) 
                ? 'ADMIN OPMERKING: ' . esc_html( $data->get_error_message() )
                : 'Er kon geen collecte-informatie worden opgehaald.';
            return '<p class="kp-error">' . $msg . '</p>';
        }

        $grouped_goals = [];
        foreach ($data as $goal) {
            $date_key = $goal['payment_request_expiry'] ?? 'Onbekende datum'; 
            if (!isset($grouped_goals[$date_key])) {
                $grouped_goals[$date_key] = [];
            }
            $grouped_goals[$date_key][] = $goal;
        }
        ksort($grouped_goals);

        ob_start();
        ?>
        <div class="kp-collecte-overzicht">
            <h2>Collectedoelen</h2>
            <?php if ( ! empty( $grouped_goals ) ) : ?>
                <?php foreach ( $grouped_goals as $date_time_string => $goals ) : 
                    $formatted_date = date_i18n( get_option('date_format') . ' H:i', strtotime($date_time_string) );
                ?>
                    <div class="kp-collecte-zondag">
                        <h3>Collecte geldig t/m: <?= esc_html( $formatted_date ); ?></h3>
                        <ul class="kp-doel-lijst">
                            <?php foreach ( $goals as $doel ) : 
                                $link = $doel['payment_request'] ?? ''; 
                                $doel_naam = $doel['name'] ?? 'Onbekend doel'; 
                                $qr_code_html = $this->generate_qr_code( $link ); 
                            ?>
                            <li class="kp-doel-item">
                                <div class="kp-doel-info">
                                    <h4><?= esc_html( $doel_naam ); ?></h4>
                                    <?php if ( ! empty( $link ) ) : ?>
                                        <a href="<?= esc_url( $link ); ?>" target="_blank" rel="noopener noreferrer" class="kp-donate-link">
                                            Doneer via Betaallink &rarr;
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div class="kp-doel-qr">
                                    <?= $qr_code_html; ?>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <p>Er zijn momenteel geen collectedoelen beschikbaar.</p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
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

    public function render_volgende_diensten( $atts ) {
        $services_data = $this->api_handler->get_services_data();
        $preachers_map = $this->api_handler->get_service_preacher_map();

        if ( is_wp_error( $services_data ) ) {
            $msg = current_user_can('manage_options') ? 'ADMIN OPMERKING: ' . esc_html($services_data->get_error_message()) : 'Er kon geen dienstenoverzicht worden opgehaald.';
            return '<p class="kp-error">' . $msg . '</p>';
        }
        if ( empty($services_data) || !is_array($services_data) ) {
            return '<p>Er zijn geen diensten beschikbaar.</p>';
        }

        $parse_date_to_ts = function($date_str) {
            $date_str = trim((string) $date_str);
            if ($date_str === '') return false;
            $d = DateTime::createFromFormat('Y-m-d', $date_str);
            if ($d !== false) return $d->getTimestamp();
            $d = DateTime::createFromFormat('d-m-Y', $date_str);
            if ($d !== false) return $d->getTimestamp();
            $d = DateTime::createFromFormat('d/m/Y', $date_str);
            if ($d !== false) return $d->getTimestamp();
            $ts = strtotime($date_str);
            return $ts === false ? false : $ts;
        };

        $today_ts = strtotime('today');
        $future_services = array_filter($services_data, function($svc) use ($today_ts, $parse_date_to_ts) {
            if ( empty($svc['date']) ) return false;
            $ts = $parse_date_to_ts($svc['date']);
            return $ts !== false && $ts >= $today_ts;
        });

        if (empty($future_services)) return '<p>Er zijn geen komende diensten gevonden.</p>';

        $dates_map = [];
        foreach ($future_services as $s) {
            $date = $s['date'] ?? '';
            $ts = $parse_date_to_ts($date);
            if ($ts !== false) $dates_map[$date] = $ts;
        }

        if (empty($dates_map)) return '<p>Geen geldige datums gevonden in de diensten.</p>';

        asort($dates_map, SORT_NUMERIC);
        reset($dates_map);
        $next_date = key($dates_map);

        $services_next_day = array_filter($future_services, function($s) use ($next_date) {
            return isset($s['date']) && $s['date'] === $next_date;
        });

        if (empty($services_next_day)) return '<p>Geen diensten gevonden voor de eerstvolgende datum.</p>';

        $grouped = [];
        $buildings = [];

        foreach ($services_next_day as $dienst) {
            $location_id = $dienst['location'] ?? 0;
            if (!isset($buildings[$location_id])) {
                $buildings[$location_id] = $this->api_handler->get_building_by_id($location_id);
            }
            $grouped[$location_id][] = $dienst;
        }

        ob_start();
        ?>
        <div class="kp-volgende-diensten">
            <h2><?= esc_html(date_i18n('l ' . get_option('date_format'), strtotime($next_date))); ?></h2>
            <?php foreach ($grouped as $location_id => $diensten): 
                usort($diensten, function($a, $b) {
                    $t1 = isset($a['start_time']) ? strtotime($a['start_time']) : 0;
                    $t2 = isset($b['start_time']) ? strtotime($b['start_time']) : 0;
                    return $t1 <=> $t2;
                });
                $building_name = 'Onbekend Gebouw';
                if ($location_id !== 0 && isset($buildings[$location_id]['name'])) {
                    $building_name = esc_html($buildings[$location_id]['name']);
                } elseif ($location_id === 0) {
                    $building_name = 'Overige Diensten';
                }
            ?>
                <div class="kp-volgende-gebouw">
                    <h3><?= $building_name; ?></h3>
                    <?php foreach ($diensten as $dienst): 
                        $id = $dienst['id'] ?? null;
                        $voorganger = $id && isset($preachers_map[(int)$id]) ? $preachers_map[(int)$id] : 'Onbekend';
                    ?>
                        <div class="kp-volgende-item">
                            <p><?= esc_html($dienst['start_time'] ?? ''); ?> - <?= esc_html($voorganger); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}