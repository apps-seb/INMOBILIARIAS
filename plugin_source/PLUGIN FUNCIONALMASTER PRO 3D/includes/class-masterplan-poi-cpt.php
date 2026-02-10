<?php
/**
 * Custom Post Type para Puntos de Interés (POIs)
 *
 * Un POI pertenece a un Proyecto y tiene coordenadas y descripción.
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
            'menu_name' => 'Puntos de Interés',
            'name_admin_bar' => 'Punto de Interés',
            'add_new' => 'Agregar Nuevo',
            'add_new_item' => 'Agregar Nuevo Punto',
            'new_item' => 'Nuevo Punto',
            'edit_item' => 'Editar Punto',
            'view_item' => 'Ver Punto',
            'all_items' => 'Todos los Puntos',
            'search_items' => 'Buscar Puntos',
            'parent_item_colon' => 'Proyecto Padre:',
            'not_found' => 'No se encontraron puntos',
            'not_found_in_trash' => 'No se encontraron puntos en la papelera',
            'featured_image' => 'Imagen del Punto',
            'set_featured_image' => 'Establecer imagen',
            'remove_featured_image' => 'Eliminar imagen',
            'use_featured_image' => 'Usar como imagen',
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
            'menu_position' => 23,
            'menu_icon' => 'dashicons-location',
            'supports' => array('title', 'editor', 'thumbnail'),
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
            'Ubicación',
            array($this, 'render_location_metabox'),
            'masterplan_poi',
            'normal',
            'high'
        );
    }

    /**
     * Renderizar Meta Box de Proyecto Asociado
     */
    public function render_project_metabox($post)
    {
        $project_id = get_post_meta($post->ID, '_project_id', true);

        // Obtener todos los proyectos
        $projects = get_posts(array(
            'post_type' => 'proyecto',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => 'publish'
        ));

?>
        <select id="project_id" name="project_id" style="width: 100%;">
            <option value="">-- Seleccionar Proyecto --</option>
            <?php foreach ($projects as $project): ?>
                <option value="<?php echo $project->ID; ?>" <?php selected($project_id, $project->ID); ?>>
                    <?php echo esc_html($project->post_title); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">Selecciona el proyecto al que pertenece este punto</p>

        <?php if ($project_id): ?>
            <p style="margin-top: 10px;">
                <a href="<?php echo get_edit_post_link($project_id); ?>" class="button">
                    Ver Proyecto
                </a>
            </p>
        <?php endif; ?>
        <?php
    }

    /**
     * Renderizar Meta Box de Ubicación
     */
    public function render_location_metabox($post)
    {
        wp_nonce_field('masterplan_save_poi_details', 'masterplan_poi_details_nonce');

        $lat = get_post_meta($post->ID, '_poi_lat', true);
        $lng = get_post_meta($post->ID, '_poi_lng', true);
        $project_id = get_post_meta($post->ID, '_project_id', true);

?>
        <table class="form-table">
            <tr>
                <th><label for="poi_lat">Latitud / Y</label></th>
                <td>
                    <input type="text" id="poi_lat" name="poi_lat" value="<?php echo esc_attr($lat); ?>" class="regular-text">
                    <p class="description">Coordenada Latitud (Mapa) o Y relativa (Imagen)</p>
                </td>
            </tr>
            <tr>
                <th><label for="poi_lng">Longitud / X</label></th>
                <td>
                    <input type="text" id="poi_lng" name="poi_lng" value="<?php echo esc_attr($lng); ?>" class="regular-text">
                    <p class="description">Coordenada Longitud (Mapa) o X relativa (Imagen)</p>
                </td>
            </tr>
        </table>

        <?php if ($project_id): ?>
            <p style="margin-top: 15px;">
                <a href="<?php echo admin_url('admin.php?page=masterplan-project-editor&project_id=' . $project_id . '&poi_id=' . $post->ID); ?>"
                   class="button button-primary" style="width: 100%; text-align: center;">
                    📍 Ubicar en Mapa
                </a>
            </p>
        <?php else: ?>
            <p style="color: #dc3545; font-style: italic; margin-top: 15px;">
                ⚠️ Selecciona un proyecto primero para ubicar en el mapa
            </p>
        <?php endif; ?>
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

        // Guardar proyecto asociado
        if (isset($_POST['project_id'])) {
            update_post_meta($post_id, '_project_id', absint($_POST['project_id']));
        }

        // Guardar coordenadas
        if (isset($_POST['poi_lat'])) {
            update_post_meta($post_id, '_poi_lat', sanitize_text_field($_POST['poi_lat']));
        }

        if (isset($_POST['poi_lng'])) {
            update_post_meta($post_id, '_poi_lng', sanitize_text_field($_POST['poi_lng']));
        }
    }
}
