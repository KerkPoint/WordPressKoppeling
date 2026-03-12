<?php
/**
 * Shortcode voor het tonen van RSS diensten.
 * Gebruik: [kp_rss_diensten] of [kp_rss_diensten id="123"]
 */

add_shortcode( 'kp_rss_diensten', 'kad_render_rss_diensten' );
add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style(
        'kp-rss-diensten-style',
        plugins_url( '../../assets/css/kp-rss-diensten.css', __FILE__ ),
        array(),
        '1.0',
        'all'
    );
});

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

    // 3. Caching met transients
    $transient_key = 'kp_rss_diensten_' . md5($feed_url);
    $items = get_transient($transient_key);
    if ($items === false) {
        if ( ! function_exists( 'fetch_feed' ) ) include_once ABSPATH . WPINC . '/feed.php';
        $feed = fetch_feed( $feed_url );
        if ( is_wp_error( $feed ) ) {
            return '<div class="kp-rss-container"><p>Feed kon niet worden opgehaald.</p></div>';
        }
        $max_items = $feed->get_item_quantity( $atts['max_items'] );
        $items = $feed->get_items( 0, $max_items );
        set_transient($transient_key, $items, 60 * 10); // 10 minuten cache
    }

    // Filter: alleen diensten die nog niet voorbij zijn
    $now = current_time('timestamp');
    $filtered_items = array();
    foreach ($items as $item) {
        $date = $item->get_date('U');
        if ($date && $date >= $now) {
            $filtered_items[] = $item;
        }
    }

    // Cache alleen de gefilterde items
    set_transient($transient_key, $filtered_items, 60 * 10);
    $items = $filtered_items;

    if ( empty( $items ) ) {
        return '<div class="kp-rss-container"><p>Geen toekomstige diensten gevonden.</p></div>';
    }

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
                $description = wp_trim_words($description, 50, '...');
            ?>
                <div class="kp-rss-item">
                    <div class="kp-rss-content">
                        <h3 class="kp-rss-item-title"><?php echo esc_html($title); ?></h3>
                        <p class="kp-rss-meta"><strong>Voorganger:</strong> <?php echo esc_html($author_name); ?></p>
                        <p class="kp-rss-meta"><strong>Datum:</strong> <?php echo esc_html($formatted_date); ?></p>
                        <details class="kp-rss-details">
                            <summary class="kp-rss-summary">Toon details en liturgie</summary>
                            <div class="kp-rss-description-full"><?php echo wp_kses_post($description); ?></div>
                        </details>
                        <a href="<?php echo esc_url($link); ?>" target="_blank" class="kp-rss-button">Bekijk video</a>
                        <?php if ($thumbnail_url): ?>
                            <img src="<?php echo esc_url($thumbnail_url); ?>" alt="Thumbnail" class="kp-rss-thumbnail" style="max-width:120px; margin-top:10px;" />
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}