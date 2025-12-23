<?php
/**
 * Shortcode voor het tonen van RSS diensten.
 * Gebruik: [kp_rss_diensten] of [kp_rss_diensten id="123"]
 */

add_shortcode( 'kp_rss_diensten', 'kad_render_rss_diensten' );

function kad_render_rss_diensten( $atts ) {
    // 1. Haal de globale instellingen op
    $options = get_option( 'kad_settings' );
    $default_id = isset( $options['rss_playlist_id'] ) ? $options['rss_playlist_id'] : '246';

    // 2. Shortcode attributen
    $atts = shortcode_atts( array(
        'id'        => $default_id,
        'max_items' => 10,
    ), $atts, 'kp_rss_diensten' );

    $feed_url = "https://kerkdienstgemist.nl/playlists/" . esc_attr($atts['id']) . ".rss?media=video";

    // 3. Ophalen feed
    if ( ! function_exists( 'fetch_feed' ) ) include_once ABSPATH . WPINC . '/feed.php';
    $feed = fetch_feed( $feed_url );

    if ( is_wp_error( $feed ) ) return '';

    $max_items = $feed->get_item_quantity( $atts['max_items'] );
    $items = $feed->get_items( 0, $max_items );

    if ( empty( $items ) ) return '';

    ob_start();
    ?>
    <div class="kp-rss-container">
        <h2 class="kp-rss-main-title">Laatste Kerkdiensten</h2>
        <div class="kp-rss-list">
            <?php foreach ( $items as $item ) :
                    $title = $item->get_title();
                    $link = $item->get_permalink();
                    $date = $item->get_date( 'U' );
                    $formatted_date = $date ? date_i18n( get_option('date_format'), $date ) : '';
                    
                    $author_name = 'Onbekend';
                    $authors = $item->get_item_tags( 'http://www.itunes.com/dtds/podcast-1.0.dtd', 'author' );
                    if ( ! empty( $authors[0]['data'] ) ) $author_name = $authors[0]['data'];

                    $thumbnail_url = '';
                    $thumbnails = $item->get_item_tags( 'http://search.yahoo.com/mrss/', 'thumbnail' );
                    if ( ! empty( $thumbnails[0]['attribs']['']['url'] ) ) $thumbnail_url = $thumbnails[0]['attribs']['']['url'];

                    $description = $item->get_description() ?: 'Geen details beschikbaar.';
                ?>
                <div class="kp-rss-item">
                    <div class="kp-rss-content">
                        <h3 class="kp-rss-item-title"><?= esc_html($title); ?></h3>
                        <p class="kp-rss-meta"><strong>Voorganger:</strong> <?= esc_html($author_name); ?></p>
                        <p class="kp-rss-meta"><strong>Datum:</strong> <?= esc_html($formatted_date); ?></p>
                        <details class="kp-rss-details">
                            <summary class="kp-rss-summary">Toon details en liturgie</summary>
                            <div class="kp-rss-description-full"><?= wp_kses_post($description); ?></div>
                        </details>
                        <a href="<?= esc_url($link); ?>" target="_blank" class="kp-rss-button">Bekijk video</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}