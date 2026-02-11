<?php
/**
 * Public Logic for Real Estate Map Overlay.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RealEstateMapOverlay_Public {

    public function __construct() {
        add_shortcode( 'interactive_map', array( $this, 'render_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ) );
    }

    /**
     * Register Scripts (Enqueued later in shortcode).
     */
    public function register_scripts() {
        wp_register_script(
            'remo-public-viewer',
            REMO_PLUGIN_URL . 'public/js/public-viewer.js',
            array(),
            '1.0.0',
            true
        );
    }

    /**
     * Shortcode Callback.
     */
    public function render_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'id' => 0,
        ), $atts, 'interactive_map' );

        $post_id = intval( $atts['id'] );
        if ( ! $post_id ) {
            return 'Invalid Map ID.';
        }

        $map_data_json = get_post_meta( $post_id, '_remo_map_data', true );
        if ( empty( $map_data_json ) ) {
            return 'Map data not found.';
        }

        // Enqueue script
        wp_enqueue_script( 'remo-public-viewer' );

        // Pass data to script
        // We use a unique variable name based on ID to support multiple maps if needed,
        // but for simplicity we'll just push to a global array or use data attributes.
        // Using wp_localize_script for a specific map ID is tricky if multiple shortcodes exist.
        // Better: Print data in a script tag or use data attribute on the container.
        // I will use data attribute on container.

        ob_start();
        ?>
        <div class="remo-map-wrapper" id="remo-map-<?php echo $post_id; ?>" style="position: relative; max-width: 100%; overflow: hidden;">
            <canvas class="remo-viewer-canvas" data-map-id="<?php echo $post_id; ?>" style="width: 100%; display: block;"></canvas>
            <div class="remo-tooltip" style="position: absolute; display: none; background: rgba(0,0,0,0.8); color: #fff; padding: 5px; border-radius: 4px; pointer-events: none; font-size: 12px; z-index: 10;"></div>
            <script type="text/javascript">
                // Initialize map data for this instance
                if (typeof window.remoMaps === 'undefined') window.remoMaps = {};
                window.remoMaps[<?php echo $post_id; ?>] = <?php echo wp_json_encode( json_decode( $map_data_json ) ); ?>;
            </script>
        </div>
        <?php
        return ob_get_clean();
    }
}
