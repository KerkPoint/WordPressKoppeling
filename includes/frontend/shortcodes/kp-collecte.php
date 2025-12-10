<?php
defined( 'ABSPATH' ) || exit;

class KP_Collecte {
    private $api_handler;

    public function __construct( $api_handler ) {
        $this->api_handler = $api_handler;
        add_shortcode( 'kp_collecte', array( $this, 'render_collecte_doelen' ) );
    }

    private function generate_qr_code( $data, $size = 5, $margin = 2 ) {
        if ( empty( $data ) ) {
            return '<span class="kp-qr-error">QR Link mist.</span>';
        }

        try {
            ob_start();
            QRcode::png($data, null, QR_ECLEVEL_L, $size, $margin);
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
            if ( ob_get_length() ) {
                ob_end_clean(); 
            }
            return '<span class="kp-qr-error">QR Fout: ' . esc_html( $e->getMessage() ) . '</span>';
        }
    }


    public function render_collecte_doelen( $atts ) {
        $data = $this->api_handler->get_collection_goals_data();
        
        $all_goals = is_array($data) ? $data : []; 
        
        usort($all_goals, function($a, $b) {
            $date_a = strtotime($a['payment_request_expiry'] ?? '9999-12-31');
            $date_b = strtotime($b['payment_request_expiry'] ?? '9999-12-31');
            return $date_a <=> $date_b;
        });

        if ( is_wp_error( $data ) ) {
            $msg = current_user_can( 'manage_options' ) 
                ? 'ADMIN OPMERKING: ' . esc_html( $data->get_error_message() )
                : 'Er kon geen collecte-informatie worden opgehaald.';
            return '<p class="kp-error">' . $msg . '</p>';
        }

        ob_start();
        ?>
        <div class="kp-collecte-overzicht-tabel">
            <h2 class="kp-collecte-titel">Collecte Doelen</h2>
            
            <?php if ( !empty( $all_goals ) ) : ?>
                
                <section class="kp-collecte-sectie-tabel">
                    <table class="kp-doel-tabel">
                        <thead>
                            <tr>
                                <th>Doel Naam</th>
                                <th class="col-qr">QR Code</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $all_goals as $doel ) : 
                                $link = $doel['payment_request'] ?? ''; 
                                $doel_naam = $doel['name'] ?? 'Onbekend doel'; 
                                $qr_code_html = $this->generate_qr_code( $link, 5, 2 ); 
                                $expiry_date_time = $doel['payment_request_expiry'] ?? 'N.n.b.';
                                $formatted_expiry = date_i18n( get_option('date_format') . ' H:i', strtotime($expiry_date_time) );

                                if ( strtotime($expiry_date_time) < time() ) {
                                    continue;
                                }
                            ?>
                            <tr class="kp-doel-rij">
                                <td data-label="Doel Naam" class="doel-naam-cel">
                                    <a href="<?= esc_url( $link ); ?>" target="_blank" rel="noopener noreferrer" class="kp-doel-naam-link">
                                        <?= esc_html( $doel_naam ); ?>
                                    </a>
                                </td>
                                <td data-label="QR Code" class="doel-qr-cel">
                                    <div class="qr-container">
                                        <?= $qr_code_html; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </section>

            <?php else : ?>
                <p class="kp-empty-state">Er zijn momenteel geen collectedoelen beschikbaar.</p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}