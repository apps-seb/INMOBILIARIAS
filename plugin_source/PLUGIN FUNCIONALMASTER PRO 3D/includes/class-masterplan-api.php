<?php
/**
 * REST API Endpoints
 * Incluye soporte para proyectos y lotes
 *
 * @package MasterPlan_3D_Pro
 */

class Masterplan_API
{

    /**
     * Registrar rutas de la API
     */
    public function register_routes()
    {
        // Endpoint para obtener todos los proyectos
        register_rest_route('masterplan/v1', '/projects', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_projects'),
            'permission_callback' => '__return_true',
        ));

        // Endpoint para obtener lotes de un proyecto
        register_rest_route('masterplan/v1', '/projects/(?P<project_id>\d+)/lots', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_project_lots'),
            'permission_callback' => '__return_true',
            'args' => array(
                'project_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));

        // Endpoint para obtener POIs de un proyecto
        register_rest_route('masterplan/v1', '/projects/(?P<project_id>\d+)/pois', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_project_pois'),
            'permission_callback' => '__return_true',
            'args' => array(
                'project_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));

        // Endpoint para obtener todos los lotes (legacy + nuevo)
        register_rest_route('masterplan/v1', '/lots', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_lots'),
            'permission_callback' => '__return_true',
            'args' => array(
                'project_id' => array(
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));

        // Endpoint para obtener un lote específico
        register_rest_route('masterplan/v1', '/lots/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_lot'),
            'permission_callback' => '__return_true',
            'args' => array(
                'id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));

        // Endpoint para el formulario de contacto
        register_rest_route('masterplan/v1', '/contact', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_contact'),
            'permission_callback' => '__return_true',
            'args' => array(
                'lot_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'name' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'email' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ),
                'phone' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'message' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                ),
                'nonce' => array(
                    'required' => true,
                    'type' => 'string',
                ),
            ),
        ));
    }

    /**
     * Obtener todos los proyectos
     */
    public function get_projects($request)
    {
        $projects = get_posts(array(
            'post_type' => 'proyecto',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        ));

        $result = array();

        foreach ($projects as $project) {
            $project_id = $project->ID;

            // Contar lotes
            $lot_count = count(get_posts(array(
                'post_type' => 'lote',
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'meta_query' => array(
                        array(
                        'key' => '_project_id',
                        'value' => $project_id,
                    )
                )
            )));

            $result[] = array(
                'id' => $project_id,
                'title' => get_the_title($project_id),
                'location' => get_post_meta($project_id, '_project_location', true),
                'thumbnail' => get_the_post_thumbnail_url($project_id, 'large'),
                'use_custom_image' => get_post_meta($project_id, '_project_use_custom_image', true) == '1',
                'custom_image_url' => wp_get_attachment_url(get_post_meta($project_id, '_project_custom_image_id', true)),
                'center_lat' => floatval(get_post_meta($project_id, '_project_center_lat', true) ?: 4.5709),
                'center_lng' => floatval(get_post_meta($project_id, '_project_center_lng', true) ?: -74.2973),
                'zoom' => intval(get_post_meta($project_id, '_project_zoom', true) ?: 16),
                'lot_count' => $lot_count,
            );
        }

        return rest_ensure_response($result);
    }

    /**
     * Obtener lotes de un proyecto específico
     */
    public function get_project_lots($request)
    {
        $project_id = $request->get_param('project_id');
        return $this->get_lots_by_project($project_id);
    }

    /**
     * Obtener POIs de un proyecto específico
     */
    public function get_project_pois($request)
    {
        $project_id = $request->get_param('project_id');

        $pois = get_posts(array(
            'post_type' => 'masterplan_poi',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => array(
                    array(
                    'key' => '_project_id',
                    'value' => $project_id,
                )
            )
        ));

        $result = array();

        foreach ($pois as $poi) {
            $lat = get_post_meta($poi->ID, '_poi_lat', true);
            $lng = get_post_meta($poi->ID, '_poi_lng', true);

            if (!$lat || !$lng) continue; // Solo devolver POIs con ubicación

            $result[] = array(
                'id' => $poi->ID,
                'title' => $poi->post_title,
                'type' => get_post_meta($poi->ID, '_poi_type', true),
                'lat' => $lat,
                'lng' => $lng,
                'description' => get_the_excerpt($poi->ID) ?: $poi->post_content,
                'thumbnail' => get_the_post_thumbnail_url($poi->ID, 'thumbnail')
            );
        }

        return rest_ensure_response($result);
    }

    /**
     * Obtener todos los lotes (opcionalmente filtrados por proyecto)
     */
    public function get_lots($request)
    {
        $project_id = $request->get_param('project_id');
        return $this->get_lots_by_project($project_id);
    }

    /**
     * Obtener lotes, opcionalmente filtrados por proyecto
     */
    private function get_lots_by_project($project_id = null)
    {
        global $wpdb;

        $args = array(
            'post_type' => 'lote',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        );

        // Filtrar por proyecto si se especifica
        if ($project_id) {
            $args['meta_query'] = array(
                    array(
                    'key' => '_project_id',
                    'value' => $project_id,
                )
            );
        }

        $lots = get_posts($args);
        $result = array();

        foreach ($lots as $lot) {
            $lot_id = $lot->ID;
            $lot_project_id = get_post_meta($lot_id, '_project_id', true);

            // Obtener coordenadas del polígono
            $table_name = $wpdb->prefix . 'masterplan_polygons';
            $polygon = $wpdb->get_row($wpdb->prepare(
                "SELECT coordinates FROM $table_name WHERE lot_id = %d ORDER BY id DESC LIMIT 1",
                $lot_id
            ));

            // Solo incluir lotes que tengan polígono definido
            if (!$polygon || empty($polygon->coordinates)) {
                continue;
            }

            // Obtener datos del proyecto
            $project_data = null;
            if ($lot_project_id) {
                $project = get_post($lot_project_id);
                if ($project) {
                    $project_data = array(
                        'id' => $lot_project_id,
                        'title' => $project->post_title,
                    );
                }
            }

            $result[] = array(
                'id' => $lot_id,
                'title' => get_the_title($lot_id),
                'excerpt' => get_the_excerpt($lot_id),
                'thumbnail' => get_the_post_thumbnail_url($lot_id, 'large'),
                'lot_number' => get_post_meta($lot_id, '_lot_number', true),
                'status' => get_post_meta($lot_id, '_lot_status', true),
                'price' => floatval(get_post_meta($lot_id, '_lot_price', true)),
                'price_formatted' => '$ ' . number_format(get_post_meta($lot_id, '_lot_price', true), 0, ',', '.') . ' COP',
                'area' => get_post_meta($lot_id, '_lot_area', true),
                'coordinates' => json_decode($polygon->coordinates, true),
                'project' => $project_data,
            );
        }

        return rest_ensure_response($result);
    }

    /**
     * Obtener un lote específico
     */
    public function get_lot($request)
    {
        global $wpdb;

        $lot_id = $request->get_param('id');
        $lot = get_post($lot_id);

        if (!$lot || $lot->post_type !== 'lote') {
            return new WP_Error('lot_not_found', 'Lote no encontrado', array('status' => 404));
        }

        // Obtener polígono
        $table_name = $wpdb->prefix . 'masterplan_polygons';
        $polygon = $wpdb->get_row($wpdb->prepare(
            "SELECT coordinates FROM $table_name WHERE lot_id = %d ORDER BY id DESC LIMIT 1",
            $lot_id
        ));

        // Obtener proyecto
        $project_id = get_post_meta($lot_id, '_project_id', true);
        $project_data = null;
        if ($project_id) {
            $project = get_post($project_id);
            if ($project) {
                $project_data = array(
                    'id' => $project_id,
                    'title' => $project->post_title,
                    'location' => get_post_meta($project_id, '_project_location', true),
                );
            }
        }

        $result = array(
            'id' => $lot_id,
            'title' => get_the_title($lot_id),
            'content' => apply_filters('the_content', $lot->post_content),
            'excerpt' => get_the_excerpt($lot_id),
            'thumbnail' => get_the_post_thumbnail_url($lot_id, 'large'),
            'gallery' => $this->get_lot_gallery($lot_id),
            'lot_number' => get_post_meta($lot_id, '_lot_number', true),
            'status' => get_post_meta($lot_id, '_lot_status', true),
            'price' => floatval(get_post_meta($lot_id, '_lot_price', true)),
            'price_formatted' => '$ ' . number_format(get_post_meta($lot_id, '_lot_price', true), 0, ',', '.') . ' COP',
            'area' => get_post_meta($lot_id, '_lot_area', true),
            'coordinates' => $polygon ? json_decode($polygon->coordinates, true) : null,
            'project' => $project_data,
        );

        return rest_ensure_response($result);
    }

    /**
     * Obtener galería de imágenes del lote
     */
    private function get_lot_gallery($lot_id)
    {
        $gallery = array();
        $attachments = get_attached_media('image', $lot_id);

        foreach ($attachments as $attachment) {
            $gallery[] = array(
                'id' => $attachment->ID,
                'url' => wp_get_attachment_url($attachment->ID),
                'thumbnail' => wp_get_attachment_image_url($attachment->ID, 'thumbnail'),
                'alt' => get_post_meta($attachment->ID, '_wp_attachment_image_alt', true),
            );
        }

        return $gallery;
    }

    /**
     * Manejar envío del formulario de contacto
     */
    public function handle_contact($request)
    {
        // Verificar nonce
        $nonce = $request->get_param('nonce');
        if (!wp_verify_nonce($nonce, 'masterplan_contact_nonce')) {
            return new WP_Error('invalid_nonce', 'Nonce inválido', array('status' => 403));
        }

        // Obtener parámetros
        $lot_id = $request->get_param('lot_id');
        $name = $request->get_param('name');
        $email = $request->get_param('email');
        $phone = $request->get_param('phone');
        $message = $request->get_param('message');

        // Validar email
        if (!is_email($email)) {
            return new WP_Error('invalid_email', 'Email inválido', array('status' => 400));
        }

        // Verificar que el lote existe
        $lot = get_post($lot_id);
        if (!$lot || $lot->post_type !== 'lote') {
            return new WP_Error('invalid_lot', 'Lote no encontrado', array('status' => 404));
        }

        // Enviar emails (admin + confirmación cliente)
        $customer_data = array(
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'message' => $message,
        );

        $sent = Masterplan_Email::send_lot_inquiry($lot_id, $customer_data);

        if ($sent) {
            return rest_ensure_response(array(
                'success' => true,
                'message' => '¡Excelente! Tu solicitud ha sido enviada. Revisa tu email para más información. Un asesor se comunicará contigo pronto.',
            ));
        }
        else {
            return new WP_Error('email_failed', 'Error al enviar el email', array('status' => 500));
        }
    }
}
