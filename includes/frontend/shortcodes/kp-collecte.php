<?php
defined( 'ABSPATH' ) || exit;

class KP_Collecte {
    private $api_handler;

    public function __construct( $api_handler ) {
        $this->api_handler = $api_handler;
        add_shortcode( 'kp_collecte', array( $this, 'render_collecte_doelen' ) );
    }

    /**
     * Genereert een QR-code als Base64 afbeelding.
     */
    private function generate_qr_code( $data, $size = 5, $margin = 2 ) {
        if ( empty( $data ) || ! class_exists( 'QRcode' ) ) {
            return '<span class="kp-qr-error">Geen QR</span>';
        }

        try {
            ob_start();
            QRcode::png( $data, null, QR_ECLEVEL_L, $size, $margin );
            $image_data = ob_get_clean();

            return sprintf(
                '<img src="data:image/png;base64,%s" alt="QR Code" class="kp-qr-image" loading="lazy" />',
                base64_encode( $image_data )
            );
        } catch ( Exception $e ) {
            if ( ob_get_length() ) ob_end_clean();
            return '';
        }
    }

    /**
     * Rendert de collectedoelen.
     */
    public function render_collecte_doelen( $atts ) {
        $raw_data = $this->api_handler->get_collection_goals_data();
        
        if ( is_wp_error( $raw_data ) ) {
            $msg = current_user_can( 'manage_options' ) 
                ? 'ADMIN: ' . esc_html( $raw_data->get_error_message() )
                : 'Er kon geen collecte-informatie worden opgehaald.';
            return '<p class="kp-error">' . $msg . '</p>';
        }

        $now = time();
        $filtered_goals = [];

        // 1. Filter: Alleen met URL en niet verlopen
        if ( is_array( $raw_data ) ) {
            foreach ( $raw_data as $doel ) {
                $link   = $doel['payment_request'] ?? '';
                $expiry = strtotime( $doel['payment_request_expiry'] ?? '' );

                // Alleen toevoegen als er een link is én (geen vervaldatum OF vervaldatum is in de toekomst)
                if ( ! empty( $link ) && ( ! $expiry || $expiry > $now ) ) {
                    $filtered_goals[] = $doel;
                }
            }
        }

        // 2. Sorteren op vervaldatum (dichtstbijzijnde eerst)
        usort( $filtered_goals, function( $a, $b ) {
            $date_a = strtotime( $a['payment_request_expiry'] ?? '9999-12-31' );
            $date_b = strtotime( $b['payment_request_expiry'] ?? '9999-12-31' );
            return $date_a <=> $date_b;
        });

        ob_start();
        ?>
        <div class="kp-collecte-overzicht-wrapper">
            <h2 class="kp-collecte-titel">Collecte Doelen</h2>
            
            <?php if ( ! empty( $filtered_goals ) ) : ?>
                <section class="kp-collecte-table-grid">
                    <?php foreach ( $filtered_goals as $doel ) : 
                        $link = $doel['payment_request'];
                        $naam = $doel['name'] ?? 'Onbekend doel';
                        ?>
                        <a href="<?= esc_url( $link ); ?>" 
                           target="_blank" 
                           rel="noopener noreferrer" 
                           class="kp-row">
                            
                            <span class="kp-cell doel-naam">
                                <?= esc_html( $naam ); ?>
                            </span>

                            <span class="kp-cell qr">
                                <?= $this->generate_qr_code( $link ); ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </section>
            <?php else : ?>
                <p class="kp-empty-state">Er zijn momenteel geen actieve collectes met een betaallink.</p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}