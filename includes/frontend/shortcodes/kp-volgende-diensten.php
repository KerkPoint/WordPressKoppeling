<?php
defined( 'ABSPATH' ) || exit;

class KP_Volgende_Diensten {
    private $api_handler;

    public function __construct( $api_handler ) {
        $this->api_handler = $api_handler;
        add_shortcode( 'kp_volgende_diensten', array( $this, 'render_volgende_diensten' ) );
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