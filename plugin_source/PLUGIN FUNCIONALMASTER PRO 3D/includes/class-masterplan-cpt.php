<?php
/**
 * Custom Post Type para Lotes
 *
 * Un Lote pertenece a un Proyecto y tiene sus propias coordenadas,
 * información de precio, disponibilidad e imagen.
 *
 * @package MasterPlan_3D_Pro
 */

class Masterplan_CPT
{

    /**
     * Registrar el Custom Post Type 'Lote'
     */
    public function register_post_type()
    {
        $labels = array(
            'name' => 'Lotes',
            'singular_name' => 'Lote',
            'menu_name' => 'Lotes',
            'name_admin_bar' => 'Lote',
            'add_new' => 'Agregar Nuevo',
            'add_new_item' => 'Agregar Nuevo Lote',
            'new_item' => 'Nuevo Lote',
            'edit_item' => 'Editar Lote',
            'view_item' => 'Ver Lote',
            'all_items' => 'Todos los Lotes',
            'search_items' => 'Buscar Lotes',
            'parent_item_colon' => 'Proyecto Padre:',
            'not_found' => 'No se encontraron lotes',
            'not_found_in_trash' => 'No se encontraron lotes en la papelera',
            'featured_image' => 'Imagen del Lote',
            'set_featured_image' => 'Establecer imagen del lote',
            'remove_featured_image' => 'Eliminar imagen del lote',
            'use_featured_image' => 'Usar como imagen del lote',
        );

        $args = array(
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => 'masterplan-settings', // Submenú de MasterPlan 3D
            'query_var' => true,
            'rewrite' => array('slug' => 'lote'),
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => 22,
            'menu_icon' => 'dashicons-location-alt',
            'supports' => array('title', 'editor', 'thumbnail', 'excerpt'),
            'show_in_rest' => true,
        );

        register_post_type('lote', $args);
    }

    /**
     * Agregar Meta Boxes
     */
    public function add_meta_boxes()
    {
        add_meta_box(
            'masterplan_lot_project',
            'Proyecto Asociado',
            array($this, 'render_project_metabox'),
            'lote',
            'side',
            'high'
        );

        add_meta_box(
            'masterplan_lot_details',
            'Detalles del Lote',
            array($this, 'render_lot_details_metabox'),
            'lote',
            'normal',
            'high'
        );

        add_meta_box(
            'masterplan_lot_polygon',
            'Coordenadas del Polígono',
            array($this, 'render_polygon_metabox'),
            'lote',
            'side',
            'default'
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
            <?php
        endforeach; ?>
        </select>
        <p class="description">Selecciona el proyecto al que pertenece este lote</p>

        <?php if ($project_id): ?>
            <p style="margin-top: 10px;">
                <a href="<?php echo get_edit_post_link($project_id); ?>" class="button">
                    Ver Proyecto
                </a>
            </p>
        <?php
        endif; ?>
        <?php
    }

    /**
     * Renderizar Meta Box de Detalles del Lote
     */
    public function render_lot_details_metabox($post)
    {
        wp_nonce_field('masterplan_save_lot_details', 'masterplan_lot_details_nonce');

        $lot_number = get_post_meta($post->ID, '_lot_number', true);
        $status = get_post_meta($post->ID, '_lot_status', true);
        $price = get_post_meta($post->ID, '_lot_price', true);
        $area = get_post_meta($post->ID, '_lot_area', true);

?>
        <table class="form-table">
            <tr>
                <th><label for="lot_number">Número de Lote</label></th>
                <td>
                    <input type="text" id="lot_number" name="lot_number" value="<?php echo esc_attr($lot_number); ?>" class="regular-text" required>
                    <p class="description">Ejemplo: L-001, Lote 15, Manzana A-Lote 3, etc.</p>
                </td>
            </tr>
            <tr>
                <th><label for="lot_status">Estado / Disponibilidad</label></th>
                <td>
                    <select id="lot_status" name="lot_status" required style="min-width: 200px;">
                        <option value="">Seleccionar Estado</option>
                        <option value="disponible" <?php selected($status, 'disponible'); ?>>🟢 Disponible</option>
                        <option value="reservado" <?php selected($status, 'reservado'); ?>>🟡 Reservado / Apartado</option>
                        <option value="vendido" <?php selected($status, 'vendido'); ?>>🔴 Vendido</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="lot_price">Precio (COP)</label></th>
                <td>
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <span style="font-size: 18px; font-weight: bold;">$</span>
                        <input type="number" id="lot_price" name="lot_price" value="<?php echo esc_attr($price); ?>" class="regular-text" step="1" min="0">
                    </div>
                    <p class="description">Precio del lote en pesos colombianos (COP)</p>
                </td>
            </tr>
            <tr>
                <th><label for="lot_area">Área (m²)</label></th>
                <td>
                    <input type="number" id="lot_area" name="lot_area" value="<?php echo esc_attr($area); ?>" class="regular-text" step="0.01" min="0">
                    <p class="description">Superficie del lote en metros cuadrados</p>
                </td>
            </tr>
        </table>

        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 20px;">
            <h4 style="margin: 0 0 10px;">💡 Vista Previa del Precio</h4>
            <p style="font-size: 24px; font-weight: bold; color: #10b981; margin: 0;" id="price-preview">
                <?php
        if ($price) {
            echo '$ ' . number_format($price, 0, ',', '.');
        }
        else {
            echo '$ 0';
        }
?>
            </p>
            <small style="color: #666;">Formato: Pesos Colombianos</small>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#lot_price').on('input', function() {
                var price = $(this).val();
                if (price) {
                    // Formatear con separador de miles (punto)
                    var formatted = parseInt(price).toLocaleString('es-CO');
                    $('#price-preview').text('$ ' + formatted);
                } else {
                    $('#price-preview').text('$ 0');
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Renderizar Meta Box de Coordenadas del Polígono
     */
    public function render_polygon_metabox($post)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'masterplan_polygons';

        $polygon = $wpdb->get_row($wpdb->prepare(
            "SELECT coordinates FROM $table_name WHERE lot_id = %d ORDER BY id DESC LIMIT 1",
            $post->ID
        ));

        $coordinates = $polygon ? $polygon->coordinates : '';
        $project_id = get_post_meta($post->ID, '_project_id', true);

?>
        <p class="description">
            <?php if ($coordinates): ?>
                <span style="color: green;">✓ Polígono definido</span><br>
                <small>Puntos: <?php echo count(json_decode($coordinates, true) ?: []); ?></small>
            <?php
        else: ?>
                <span style="color: orange;">⚠ Sin polígono</span><br>
                <small>Dibuja el lote en el editor</small>
            <?php
        endif; ?>
        </p>

        <?php if ($project_id): ?>
            <p style="margin-top: 10px;">
                <a href="<?php echo admin_url('admin.php?page=masterplan-project-editor&project_id=' . $project_id . '&lot_id=' . $post->ID); ?>"
                   class="button button-primary" style="width: 100%; text-align: center;">
                    <?php echo $coordinates ? '✏️ Editar en Mapa' : '🎨 Dibujar en Mapa'; ?>
                </a>
            </p>
        <?php
        else: ?>
            <p style="color: #dc3545; font-style: italic;">
                ⚠️ Selecciona un proyecto primero
            </p>
        <?php
        endif; ?>

        <textarea id="polygon_coordinates" name="polygon_coordinates" readonly
                  style="width:100%; height:80px; font-family:monospace; font-size:9px; margin-top: 10px;"><?php echo esc_textarea($coordinates); ?></textarea>
        <?php
    }

    /**
     * Guardar Meta Boxes
     */
    public function save_meta_boxes($post_id)
    {
        // Verificar nonce
        if (!isset($_POST['masterplan_lot_details_nonce']) ||
        !wp_verify_nonce($_POST['masterplan_lot_details_nonce'], 'masterplan_save_lot_details')) {
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

        // Guardar datos del lote
        if (isset($_POST['lot_number'])) {
            update_post_meta($post_id, '_lot_number', sanitize_text_field($_POST['lot_number']));
        }

        if (isset($_POST['lot_status'])) {
            update_post_meta($post_id, '_lot_status', sanitize_text_field($_POST['lot_status']));
        }

        if (isset($_POST['lot_price'])) {
            update_post_meta($post_id, '_lot_price', floatval($_POST['lot_price']));
        }

        if (isset($_POST['lot_area'])) {
            update_post_meta($post_id, '_lot_area', floatval($_POST['lot_area']));
        }
    }

    /**
     * Columnas personalizadas en la lista de lotes
     */
    public function add_custom_columns($columns)
    {
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['lot_number'] = 'Número';
                $new_columns['lot_project'] = 'Proyecto';
                $new_columns['lot_status'] = 'Estado';
                $new_columns['lot_price'] = 'Precio';
            }
        }
        return $new_columns;
    }

    /**
     * Contenido de columnas personalizadas
     */
    public function custom_column_content($column, $post_id)
    {
        switch ($column) {
            case 'lot_number':
                echo esc_html(get_post_meta($post_id, '_lot_number', true) ?: '-');
                break;
            case 'lot_project':
                $project_id = get_post_meta($post_id, '_project_id', true);
                if ($project_id) {
                    $project = get_post($project_id);
                    if ($project) {
                        echo '<a href="' . get_edit_post_link($project_id) . '">' . esc_html($project->post_title) . '</a>';
                    }
                }
                else {
                    echo '<span style="color: #999;">Sin proyecto</span>';
                }
                break;
            case 'lot_status':
                $status = get_post_meta($post_id, '_lot_status', true);
                $colors = array(
                    'disponible' => '#10b981',
                    'reservado' => '#f59e0b',
                    'vendido' => '#ef4444'
                );
                $labels = array(
                    'disponible' => 'Disponible',
                    'reservado' => 'Reservado',
                    'vendido' => 'Vendido'
                );
                if ($status && isset($colors[$status])) {
                    echo '<span style="background: ' . $colors[$status] . '; color: white; padding: 3px 8px; border-radius: 4px; font-size: 11px;">' . $labels[$status] . '</span>';
                }
                break;
            case 'lot_price':
                $price = get_post_meta($post_id, '_lot_price', true);
                if ($price) {
                    echo '<strong>$ ' . number_format($price, 0, ',', '.') . '</strong>';
                }
                else {
                    echo '-';
                }
                break;
        }
    }
}
