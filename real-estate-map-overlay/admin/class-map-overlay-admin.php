<?php
/**
 * Admin Logic for Real Estate Map Overlay.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RealEstateMapOverlay_Admin {

    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post', array( $this, 'save_meta_box_data' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    /**
     * Add Meta Boxes.
     */
    public function add_meta_boxes() {
        add_meta_box(
            'remo_map_editor',
            __( 'Map Editor', 'real-estate-map-overlay' ),
            array( $this, 'render_editor_meta_box' ),
            'map_project',
            'normal',
            'high'
        );
    }

    /**
     * Render the Editor Meta Box.
     */
    public function render_editor_meta_box( $post ) {
        // Add a nonce field for security
        wp_nonce_field( 'remo_save_map_data', 'remo_map_nonce' );

        // Retrieve existing value from the database
        $map_data_json = get_post_meta( $post->ID, '_remo_map_data', true );

        ?>
        <div id="remo-editor-wrapper">
            <div class="remo-toolbar" style="margin-bottom: 10px;">
                <button type="button" id="remo-upload-master" class="button button-primary">
                    <?php _e( 'Set Master Image', 'real-estate-map-overlay' ); ?>
                </button>
                <button type="button" id="remo-add-lot" class="button button-secondary">
                    <?php _e( 'Add Lot Layer', 'real-estate-map-overlay' ); ?>
                </button>
                <span id="remo-status" style="margin-left: 10px; font-style: italic;"></span>
            </div>

            <div id="remo-canvas-container" style="position: relative; border: 1px solid #ccc; background: #f0f0f0; min-height: 400px; overflow: hidden;">
                <canvas id="remo-editor-canvas"></canvas>
            </div>

            <!-- Hidden input to store the JSON data -->
            <input type="hidden" name="remo_map_data" id="remo_map_data" value="<?php echo esc_attr( $map_data_json ); ?>">

            <div class="remo-instructions" style="margin-top: 10px; color: #666;">
                <p><?php _e( 'Instructions: Upload a Master Image first. Then add Lot Layers. Drag the 4 corners of each lot to match the perspective.', 'real-estate-map-overlay' ); ?></p>
            </div>
        </div>

        <style>
            #remo-canvas-container canvas {
                display: block;
                /* Max width/height logic will be handled by JS resizing */
            }
        </style>
        <?php
    }

    /**
     * Save Meta Box Data.
     */
    public function save_meta_box_data( $post_id ) {
        // Check nonce
        if ( ! isset( $_POST['remo_map_nonce'] ) ) {
            return;
        }
        if ( ! wp_verify_nonce( $_POST['remo_map_nonce'], 'remo_save_map_data' ) ) {
            return;
        }

        // Check autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Check permissions
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Sanitize and save data
        if ( isset( $_POST['remo_map_data'] ) ) {
            // We use stripslashes because WP adds slashes to JSON strings in POST
            $json_data = stripslashes( $_POST['remo_map_data'] );

            // Validate JSON structure (optional but recommended)
            $decoded = json_decode( $json_data, true );
            if ( is_array( $decoded ) || is_object( $decoded ) ) {
                update_post_meta( $post_id, '_remo_map_data', $json_data );
            } else {
                // Handle invalid JSON or empty data
                // Usually allow empty if user clears everything
                if ( empty( $json_data ) ) {
                    delete_post_meta( $post_id, '_remo_map_data' );
                }
            }
        }
    }

    /**
     * Enqueue Scripts and Styles.
     */
    public function enqueue_scripts( $hook ) {
        global $post;

        if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
            return;
        }

        if ( 'map_project' !== get_post_type( $post ) ) {
            return;
        }

        // Enqueue Media Uploader
        wp_enqueue_media();

        // Enqueue Admin Editor Script
        wp_enqueue_script(
            'remo-admin-editor',
            REMO_PLUGIN_URL . 'admin/js/admin-editor.js',
            array(), // No jQuery dependency requested
            '1.0.0',
            true
        );

        // Localize Script
        wp_localize_script( 'remo-admin-editor', 'remoData', array(
            'nonce' => wp_create_nonce( 'remo_editor_action' ), // For potential AJAX calls
            'default_master' => '', // Could be placeholder
            'i18n' => array(
                'select_image' => __( 'Select Image', 'real-estate-map-overlay' ),
                'use_image' => __( 'Use This Image', 'real-estate-map-overlay' ),
            )
        ));
    }
}
