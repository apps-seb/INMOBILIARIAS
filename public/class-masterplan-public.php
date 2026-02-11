<?php
/**
 * Frontend Public Display
 * Incluye shortcodes para proyectos y lotes
 *
 * @package MasterPlan_3D_Pro
 */

class Masterplan_Public
{

    /**
     * Registrar shortcodes
     */
    public function register_shortcodes()
    {
        add_shortcode('masterplan_map', array($this, 'render_map_shortcode'));
        add_shortcode('masterplan_project', array($this, 'render_project_shortcode'));
        add_shortcode('masterplan_projects', array($this, 'render_projects_list_shortcode'));
    }

    /**
     * Renderizar shortcode del mapa (legacy - todos los lotes)
     */
    public function render_map_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'height' => '600px',
            'project_id' => 0, // Opcional: filtrar por proyecto
        ), $atts);

        return $this->render_map_viewer($atts, null);
    }

    /**
     * Renderizar shortcode de un proyecto específico
     * Uso: [masterplan_project id="123" height="700px"]
     */
    public function render_project_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'id' => 0,
            'height' => '700px',
        ), $atts);

        $project_id = intval($atts['id']);
        if (!$project_id) {
            return '<p class="masterplan-error">Error: ID de proyecto no especificado.</p>';
        }

        $project = get_post($project_id);
        if (!$project || $project->post_type !== 'proyecto') {
            return '<p class="masterplan-error">Error: Proyecto no encontrado.</p>';
        }

        return $this->render_map_viewer($atts, $project);
    }

    /**
     * Renderizar lista de proyectos
     * Uso: [masterplan_projects columns="3"]
     */
    public function render_projects_list_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'columns' => 3,
            'show_lots_count' => 'yes',
        ), $atts);

        $projects = get_posts(array(
            'post_type' => 'proyecto',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        ));

        if (empty($projects)) {
            return '<p class="masterplan-no-projects">No hay proyectos disponibles.</p>';
        }

        ob_start();
?>
        <div class="masterplan-projects-grid" style="display: grid; grid-template-columns: repeat(<?php echo intval($atts['columns']); ?>, 1fr); gap: 20px;">
            <?php foreach ($projects as $project):
            $project_id = $project->ID;
            $thumbnail = get_the_post_thumbnail_url($project_id, 'medium_large');
            $location = get_post_meta($project_id, '_project_location', true);
            $custom_image_id = get_post_meta($project_id, '_project_custom_image_id', true);
            $display_image = $thumbnail ?: ($custom_image_id ? wp_get_attachment_url($custom_image_id) : '');

            // Contar lotes disponibles
            $lots = get_posts(array(
                'post_type' => 'lote',
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'meta_query' => array(
                        array('key' => '_project_id', 'value' => $project_id)
                )
            ));
            $available = array_filter($lots, function ($l) {
                return get_post_meta($l->ID, '_lot_status', true) === 'disponible';
            });
?>
                <div class="masterplan-project-card">
                    <?php if ($display_image): ?>
                        <div class="project-image" style="height: 200px; background: url('<?php echo esc_url($display_image); ?>') center/cover; border-radius: 12px 12px 0 0;"></div>
                    <?php
            else: ?>
                        <div class="project-image" style="height: 200px; background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 12px 12px 0 0; display: flex; align-items: center; justify-content: center;">
                            <span style="font-size: 48px;">🏗️</span>
                        </div>
                    <?php
            endif; ?>

                    <div class="project-info" style="padding: 20px; background: white; border-radius: 0 0 12px 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                        <h3 style="margin: 0 0 8px; font-size: 18px; color: #1e293b;">
                            <?php echo esc_html($project->post_title); ?>
                        </h3>
                        <?php if ($location): ?>
                            <p style="margin: 0 0 12px; color: #64748b; font-size: 14px;">
                                📍 <?php echo esc_html($location); ?>
                            </p>
                        <?php
            endif; ?>

                        <?php if ($atts['show_lots_count'] === 'yes'): ?>
                            <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                                <span style="background: #dcfce7; color: #166534; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                                    <?php echo count($available); ?> disponibles
                                </span>
                                <span style="background: #f1f5f9; color: #475569; padding: 4px 10px; border-radius: 12px; font-size: 12px;">
                                    <?php echo count($lots); ?> lotes
                                </span>
                            </div>
                        <?php
            endif; ?>

                        <a href="<?php echo esc_url(add_query_arg('project_id', $project_id, get_permalink())); ?>"
                           style="display: block; text-align: center; background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 12px; border-radius: 8px; text-decoration: none; font-weight: 600; transition: opacity 0.3s;">
                            Ver Proyecto →
                        </a>
                    </div>
                </div>
            <?php
        endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderizar visor de mapa
     */
    private function render_map_viewer($atts, $project = null)
    {
        $project_id = $project ? $project->ID : intval($atts['project_id'] ?? 0);

        // Configuración del mapa
        if ($project) {
            $use_custom_image = get_post_meta($project_id, '_project_use_custom_image', true) == '1';
            $custom_image_id = get_post_meta($project_id, '_project_custom_image_id', true);
            $custom_image_url = $custom_image_id ? wp_get_attachment_url($custom_image_id) : '';
            $center_lat = get_post_meta($project_id, '_project_center_lat', true) ?: get_option('masterplan_map_center_lat', '4.5709');
            $center_lng = get_post_meta($project_id, '_project_center_lng', true) ?: get_option('masterplan_map_center_lng', '-74.2973');
            $zoom = get_post_meta($project_id, '_project_zoom', true) ?: 16;
            $project_title = $project->post_title;
            $project_location = get_post_meta($project_id, '_project_location', true);
        }
        else {
            $use_custom_image = false;
            $custom_image_url = '';
            $center_lat = get_option('masterplan_map_center_lat', '4.5709');
            $center_lng = get_option('masterplan_map_center_lng', '-74.2973');
            $zoom = get_option('masterplan_map_zoom', '14');
            $project_title = '';
            $project_location = '';
        }

        ob_start();
?>
        <div class="masterplan-public-wrapper" data-project-id="<?php echo esc_attr($project_id); ?>">
            <?php if ($project_title): ?>
            <div class="masterplan-project-header">
                <h2 class="project-title"><?php echo esc_html($project_title); ?></h2>
                <?php if ($project_location): ?>
                    <p class="project-location">📍 <?php echo esc_html($project_location); ?></p>
                <?php
            endif; ?>
            </div>
            <?php
        endif; ?>

            <?php if ($use_custom_image && $custom_image_url): ?>
                <!-- Modo Imagen -->
                <div id="masterplan-image-viewer" class="masterplan-image-viewer" style="height: <?php echo esc_attr($atts['height']); ?>;">
                    <canvas id="masterplan-canvas"></canvas>
                    <img id="masterplan-background" src="<?php echo esc_url($custom_image_url); ?>" style="display: none;">
                </div>
            <?php
        else: ?>
                <!-- Modo Mapa 3D -->
                <div id="masterplan-public-map" class="masterplan-public-map" style="height: <?php echo esc_attr($atts['height']); ?>;"></div>
            <?php
        endif; ?>

            <!-- Sidebar lateral -->
            <div id="masterplan-sidebar" class="masterplan-sidebar">
                <button class="sidebar-close" id="sidebar-close-btn">&times;</button>
                <div class="sidebar-content" id="sidebar-content">
                    <!-- Contenido dinámico -->
                </div>
            </div>

            <!-- Overlay -->
            <div id="masterplan-overlay" class="masterplan-overlay"></div>
        </div>

        <script>
        var masterplanProjectConfig = {
            projectId: <?php echo $project_id ?: 'null'; ?>,
            useCustomImage: <?php echo $use_custom_image ? 'true' : 'false'; ?>,
            customImageUrl: '<?php echo esc_js($custom_image_url); ?>',
            centerLat: <?php echo floatval($center_lat); ?>,
            centerLng: <?php echo floatval($center_lng); ?>,
            zoom: <?php echo intval($zoom); ?>
        };
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Encolar estilos del frontend
     */
    public function enqueue_styles()
    {
        // Solo cargar en páginas con shortcodes del plugin
        global $post;
        if (!is_a($post, 'WP_Post')) {
            return;
        }

        $has_shortcode = has_shortcode($post->post_content, 'masterplan_map') ||
            has_shortcode($post->post_content, 'masterplan_project') ||
            has_shortcode($post->post_content, 'masterplan_projects');

        if (!$has_shortcode) {
            return;
        }

        // MapLibre GL CSS
        wp_enqueue_style(
            'maplibre-gl',
            'https://unpkg.com/maplibre-gl@3.6.2/dist/maplibre-gl.css',
            array(),
            '3.6.2'
        );

        // Estilos del viewer
        wp_enqueue_style(
            'masterplan-viewer',
            MASTERPLAN_PLUGIN_URL . 'public/css/viewer.css',
            array(),
            MASTERPLAN_VERSION
        );

    }

    /**
     * Encolar scripts del frontend
     */
    public function enqueue_scripts()
    {
        global $post;
        if (!is_a($post, 'WP_Post')) {
            return;
        }

        $has_shortcode = has_shortcode($post->post_content, 'masterplan_map') ||
            has_shortcode($post->post_content, 'masterplan_project');

        if (!$has_shortcode) {
            return;
        }

        // MapLibre GL JS
        wp_enqueue_script(
            'maplibre-gl',
            'https://unpkg.com/maplibre-gl@3.6.2/dist/maplibre-gl.js',
            array(),
            '3.6.2',
            true
        );

        // Script del viewer
        wp_enqueue_script(
            'masterplan-viewer',
            MASTERPLAN_PLUGIN_URL . 'public/js/frontend-viewer.js',
            array('jquery', 'maplibre-gl'),
            MASTERPLAN_VERSION,
            true
        );

        // Pasar datos a JavaScript
        wp_localize_script(
            'masterplan-viewer',
            'masterplanPublic',
            array(
            'apiUrl' => rest_url('masterplan/v1/'),
            'nonce' => wp_create_nonce('masterplan_contact_nonce'),
            'apiKey' => get_option('masterplan_api_key', ''),
            'centerLat' => get_option('masterplan_map_center_lat', '4.5709'),
            'centerLng' => get_option('masterplan_map_center_lng', '-74.2973'),
            'zoom' => get_option('masterplan_map_zoom', '14'),
            'whatsappNumber' => get_option('masterplan_whatsapp_number', ''),
            'currency' => 'COP',
            'currencySymbol' => '$',
        )
        );
    }

    /**
     * Manejar formulario de contacto vía AJAX
     */
    public function handle_contact_form()
    {
        check_ajax_referer('masterplan_contact_nonce', 'nonce');

        $lot_id = isset($_POST['lot_id']) ? absint($_POST['lot_id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';

        if (!$lot_id || !$name || !$email || !$phone) {
            wp_send_json_error(array('message' => 'Por favor completa todos los campos requeridos.'));
        }

        if (!is_email($email)) {
            wp_send_json_error(array('message' => 'Por favor ingresa un email válido.'));
        }

        $customer_data = array(
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'message' => $message,
        );

        $sent = Masterplan_Email::send_lot_inquiry($lot_id, $customer_data);

        if ($sent) {
            wp_send_json_success(array(
                'message' => '🎉 ¡Excelente! Tu solicitud ha sido enviada. Revisa tu email para más detalles. ¡Pronto te contactaremos!'
            ));
        }
        else {
            wp_send_json_error(array('message' => 'Error al enviar. Por favor intenta de nuevo.'));
        }
    }
}
