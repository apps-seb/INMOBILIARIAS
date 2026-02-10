<?php
/**
 * Custom Post Type para Puntos de Interés (POI)
 *
 * Permite definir puntos de interés 3D que no se desplazan,
 * con logo, información y opciones de visualización.
 *
 * @package MasterPlan_3D_Pro
 */

class Masterplan_POI_CPT
{

    /**
     * Registrar el Custom Post Type 'POI'
     */
    public function register_post_type()
    {
        $labels = array(
            'name' => 'Puntos de Interés',
            'singular_name' => 'Punto de Interés',
            'menu_name' => 'Puntos de Interés (POI)',
            'name_admin_bar' => 'POI',
            'add_new' => 'Agregar Nuevo',
            'add_new_item' => 'Agregar Nuevo POI',
            'new_item' => 'Nuevo POI',
            'edit_item' => 'Editar POI',
            'view_item' => 'Ver POI',
            'all_items' => 'Todos los POIs',
            'search_items' => 'Buscar POIs',
            'parent_item_colon' => 'Proyecto Padre:',
            'not_found' => 'No se encontraron POIs',
            'not_found_in_trash' => 'No se encontraron POIs en la papelera',
            'featured_image' => 'Logo del POI',
            'set_featured_image' => 'Establecer logo',
            'remove_featured_image' => 'Eliminar logo',
            'use_featured_image' => 'Usar como logo',
        );

        $args = array(
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => 'masterplan-settings', // Submenú de MasterPlan 3D
            'query_var' => true,
            'rewrite' => array('slug' => 'poi'),
            'capability_type' => 'post',
            'has_archive' => false,
            'hierarchical' => false,
            'menu_position' => 25,
            'menu_icon' => 'dashicons-location',
            'supports' => array('title', 'editor', 'thumbnail', 'excerpt'),
            'show_in_rest' => true,
        );

        register_post_type('masterplan_poi', $args);
    }

    /**
     * Agregar Meta Boxes
     */
    public function add_meta_boxes()
    {
        add_meta_box(
            'masterplan_poi_project',
            'Proyecto Asociado',
            array($this, 'render_project_metabox'),
            'masterplan_poi',
            'side',
            'high'
        );

        add_meta_box(
            'masterplan_poi_location',
            'Ubicación 3D',
            array($this, 'render_location_metabox'),
            'masterplan_poi',
            'normal',
            'high'
        );

        add_meta_box(
            'masterplan_poi_visualization',
            'Opciones de Visualización',
            array($this, 'render_visualization_metabox'),
            'masterplan_poi',
            'side',
            'default'
        );
    }

    /**
     * Renderizar Meta Box de Proyecto Asociado
     */
    public function render_project_metabox($post)
    {
        $project_id = get_post_meta($post->ID, '_poi_project_id', true);

        // Obtener todos los proyectos
        $projects = get_posts(array(
            'post_type' => 'proyecto',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => 'publish'
        ));

?>
        <select id="poi_project_id" name="poi_project_id" style="width: 100%;">
            <option value="">-- Seleccionar Proyecto --</option>
            <?php foreach ($projects as $project): ?>
                <option value="<?php echo $project->ID; ?>" <?php selected($project_id, $project->ID); ?>>
                    <?php echo esc_html($project->post_title); ?>
                </option>
            <?php
        endforeach; ?>
        </select>
        <p class="description">Selecciona el proyecto donde aparecerá este POI.</p>
        <?php
    }

    /**
     * Renderizar Meta Box de Ubicación (Lat/Lng/Alt)
     */
    public function render_location_metabox($post)
    {
        wp_nonce_field('masterplan_save_poi_details', 'masterplan_poi_details_nonce');

        $lat = get_post_meta($post->ID, '_poi_lat', true);
        $lng = get_post_meta($post->ID, '_poi_lng', true);
        $alt = get_post_meta($post->ID, '_poi_alt', true);

?>
        <table class="form-table">
            <tr>
                <th><label for="poi_lat">Latitud</label></th>
                <td>
                    <input type="text" id="poi_lat" name="poi_lat" value="<?php echo esc_attr($lat); ?>" class="regular-text" placeholder="Ej: 4.5709">
                </td>
            </tr>
            <tr>
                <th><label for="poi_lng">Longitud</label></th>
                <td>
                    <input type="text" id="poi_lng" name="poi_lng" value="<?php echo esc_attr($lng); ?>" class="regular-text" placeholder="Ej: -74.2973">
                </td>
            </tr>
            <tr>
                <th><label for="poi_alt">Altitud (m)</label></th>
                <td>
                    <input type="number" id="poi_alt" name="poi_alt" value="<?php echo esc_attr($alt); ?>" class="regular-text" step="0.1" placeholder="Ej: 0">
                    <p class="description">Altitud relativa al terreno (0 para estar pegado al suelo).</p>
                </td>
            </tr>
        </table>
        <p class="description">
            <small>Usa un mapa online (como Google Maps o Maptiler) para obtener las coordenadas Lat/Lng.</small>
        </p>
        <?php
    }

    /**
     * Renderizar Meta Box de Visualización
     */
    public function render_visualization_metabox($post)
    {
        $color = get_post_meta($post->ID, '_poi_color', true) ?: '#3b82f6';
        $viz_type = get_post_meta($post->ID, '_poi_viz_type', true) ?: 'icon';

?>
        <p>
            <label for="poi_viz_type"><strong>Tipo de Visualización:</strong></label><br>
            <select id="poi_viz_type" name="poi_viz_type" style="width: 100%; margin-top: 5px;">
                <option value="icon" <?php selected($viz_type, 'icon'); ?>>Icono (Logo)</option>
                <option value="billboard" <?php selected($viz_type, 'billboard'); ?>>Cartel Flotante</option>
                <option value="sphere" <?php selected($viz_type, 'sphere'); ?>>Esfera 3D</option>
            </select>
        </p>

        <p>
            <label for="poi_color"><strong>Color de Resalte:</strong></label><br>
            <input type="color" id="poi_color" name="poi_color" value="<?php echo esc_attr($color); ?>" style="width: 100%; height: 40px; margin-top: 5px;">
        </p>

        <hr>

        <p><strong>Logo / Icono:</strong></p>
        <p class="description">Usa la "Imagen Destacada" de este post para subir el logo del POI.</p>
        <?php
    }

    /**
     * Guardar Meta Boxes
     */
    public function save_meta_boxes($post_id)
    {
        // Verificar nonce
        if (!isset($_POST['masterplan_poi_details_nonce']) ||
        !wp_verify_nonce($_POST['masterplan_poi_details_nonce'], 'masterplan_save_poi_details')) {
            return;
        }

        // Verificar autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Verificar permisos
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Guardar datos
        if (isset($_POST['poi_project_id'])) {
            update_post_meta($post_id, '_poi_project_id', absint($_POST['poi_project_id']));
        }

        if (isset($_POST['poi_lat'])) {
            update_post_meta($post_id, '_poi_lat', sanitize_text_field($_POST['poi_lat']));
        }

        if (isset($_POST['poi_lng'])) {
            update_post_meta($post_id, '_poi_lng', sanitize_text_field($_POST['poi_lng']));
        }

        if (isset($_POST['poi_alt'])) {
            update_post_meta($post_id, '_poi_alt', floatval($_POST['poi_alt']));
        }

        if (isset($_POST['poi_viz_type'])) {
            update_post_meta($post_id, '_poi_viz_type', sanitize_text_field($_POST['poi_viz_type']));
        }

        if (isset($_POST['poi_color'])) {
            update_post_meta($post_id, '_poi_color', sanitize_hex_color($_POST['poi_color']));
        }
    }
}
