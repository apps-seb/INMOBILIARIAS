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

        // Registrar POIs (Puntos de Interés)
        $poi_labels = array(
            'name' => 'Puntos de Interés',
            'singular_name' => 'Punto de Interés',
            'menu_name' => 'Puntos de Interés',
            'add_new' => 'Agregar Nuevo',
            'add_new_item' => 'Agregar Nuevo POI',
            'new_item' => 'Nuevo POI',
            'edit_item' => 'Editar POI',
            'view_item' => 'Ver POI',
            'all_items' => 'Todos los POIs',
            'search_items' => 'Buscar POIs',
            'parent_item_colon' => 'Proyecto Padre:',
        );

        $poi_args = array(
            'labels' => $poi_labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => false, // Oculto del menú principal, gestionado desde el editor
            'query_var' => true,
            'rewrite' => array('slug' => 'poi'),
            'capability_type' => 'post',
            'has_archive' => false,
            'hierarchical' => false,
            'supports' => array('title', 'editor', 'thumbnail', 'excerpt'),
            'show_in_rest' => true,
        );

        register_post_type('masterplan_poi', $poi_args);
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

        // Meta boxes para POIs
        add_meta_box(
            'masterplan_poi_project',
            'Proyecto Asociado',
            array($this, 'render_project_metabox'),
            'masterplan_poi',
            'side',
            'high'
        );

        add_meta_box(
            'masterplan_poi_details',
            'Detalles del POI',
            array($this, 'render_poi_details_metabox'),
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
     * Renderizar Meta Box de Detalles del POI
     */
    public function render_poi_details_metabox($post)
    {
        wp_nonce_field('masterplan_save_poi_details', 'masterplan_poi_details_nonce');

        $poi_type = get_post_meta($post->ID, '_poi_type', true);
        $poi_lat = get_post_meta($post->ID, '_poi_lat', true);
        $poi_lng = get_post_meta($post->ID, '_poi_lng', true);
        $poi_style = get_post_meta($post->ID, '_poi_marker_style', true) ?: 'icon';
        $poi_image_id = get_post_meta($post->ID, '_poi_marker_image_id', true);
        $poi_image_url = $poi_image_id ? wp_get_attachment_url($poi_image_id) : '';
?>
        <table class="form-table">
            <tr>
                <th><label for="poi_type">Tipo de POI</label></th>
                <td>
                    <select id="poi_type" name="poi_type">
                        <option value="info" <?php selected($poi_type, 'info'); ?>>ℹ️ Información</option>
                        <option value="park" <?php selected($poi_type, 'park'); ?>>🌳 Parque / Zona Verde</option>
                        <option value="facility" <?php selected($poi_type, 'facility'); ?>>🏢 Instalación / Amenidad</option>
                        <option value="entrance" <?php selected($poi_type, 'entrance'); ?>>🚪 Acceso / Portería</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="poi_marker_style">Estilo del Marcador</label></th>
                <td>
                    <select id="poi_marker_style" name="poi_marker_style">
                        <option value="icon" <?php selected($poi_style, 'icon'); ?>>📍 Icono Simple (Centro-Abajo)</option>
                        <option value="line" <?php selected($poi_style, 'line'); ?>>📏 Línea Vertical con Icono</option>
                        <option value="flag" <?php selected($poi_style, 'flag'); ?>>🚩 Bandera / Asta</option>
                    </select>
                    <p class="description">Define cómo se visualiza el marcador en el mapa/imagen.</p>
                </td>
            </tr>
            <tr>
                <th><label>Icono Personalizado</label></th>
                <td>
                    <div style="margin-bottom: 10px;">
                        <?php if ($poi_image_url): ?>
                            <img src="<?php echo esc_url($poi_image_url); ?>" style="max-width: 50px; display: block; margin-bottom: 5px;" id="poi_marker_preview">
                        <?php else: ?>
                            <img src="" style="max-width: 50px; display: none; margin-bottom: 5px;" id="poi_marker_preview">
                        <?php endif; ?>
                    </div>
                    <input type="hidden" id="poi_marker_image_id" name="poi_marker_image_id" value="<?php echo esc_attr($poi_image_id); ?>">
                    <button type="button" class="button" id="upload_marker_image_btn"><?php echo $poi_image_id ? 'Cambiar Icono' : 'Subir Icono'; ?></button>
                    <button type="button" class="button" id="remove_marker_image_btn" style="<?php echo $poi_image_id ? '' : 'display:none;'; ?>">Eliminar</button>
                    <p class="description">Sube un icono o logo (PNG transparente recomendado). Si no se sube, se usará el emoji por defecto.</p>

                    <script>
                    jQuery(document).ready(function($){
                        var mediaUploader;
                        $('#upload_marker_image_btn').click(function(e) {
                            e.preventDefault();
                            if (mediaUploader) {
                                mediaUploader.open();
                                return;
                            }
                            mediaUploader = wp.media.frames.file_frame = wp.media({
                                title: 'Seleccionar Icono del Marcador',
                                button: { text: 'Usar este icono' },
                                multiple: false
                            });
                            mediaUploader.on('select', function() {
                                var attachment = mediaUploader.state().get('selection').first().toJSON();
                                $('#poi_marker_image_id').val(attachment.id);
                                $('#poi_marker_preview').attr('src', attachment.url).show();
                                $('#upload_marker_image_btn').text('Cambiar Icono');
                                $('#remove_marker_image_btn').show();
                            });
                            mediaUploader.open();
                        });
                        $('#remove_marker_image_btn').click(function(e){
                            e.preventDefault();
                            $('#poi_marker_image_id').val('');
                            $('#poi_marker_preview').hide();
                            $('#upload_marker_image_btn').text('Subir Icono');
                            $(this).hide();
                        });
                    });
                    </script>
                </td>
            </tr>
            <tr>
                <th><label>Coordenadas</label></th>
                <td>
                    <div style="display:flex; gap:10px;">
                        <div>
                            <label for="poi_lat">Latitud / Y</label>
                            <input type="text" id="poi_lat" name="poi_lat" value="<?php echo esc_attr($poi_lat); ?>" class="regular-text">
                        </div>
                        <div>
                            <label for="poi_lng">Longitud / X</label>
                            <input type="text" id="poi_lng" name="poi_lng" value="<?php echo esc_attr($poi_lng); ?>" class="regular-text">
                        </div>
                    </div>
                    <p class="description">Coordenadas geográficas o relativas (0-1) según el modo.</p>
                </td>
            </tr>
        </table>
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
        // Verificar si es Lote o POI
        $post_type = get_post_type($post_id);

        if ($post_type === 'lote') {
            // Verificar nonce de Lote
            if (!isset($_POST['masterplan_lot_details_nonce']) ||
            !wp_verify_nonce($_POST['masterplan_lot_details_nonce'], 'masterplan_save_lot_details')) {
                return;
            }
        } elseif ($post_type === 'masterplan_poi') {
             // Verificar nonce de POI
             if (!isset($_POST['masterplan_poi_details_nonce']) ||
             !wp_verify_nonce($_POST['masterplan_poi_details_nonce'], 'masterplan_save_poi_details')) {
                 return;
             }
        } else {
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

        // Guardar proyecto asociado (común para ambos)
        if (isset($_POST['project_id'])) {
            update_post_meta($post_id, '_project_id', absint($_POST['project_id']));
        }

        // Guardar datos del POI
        if ($post_type === 'masterplan_poi') {
            if (isset($_POST['poi_type'])) {
                update_post_meta($post_id, '_poi_type', sanitize_text_field($_POST['poi_type']));
            }
            if (isset($_POST['poi_marker_style'])) {
                update_post_meta($post_id, '_poi_marker_style', sanitize_text_field($_POST['poi_marker_style']));
            }
            if (isset($_POST['poi_marker_image_id'])) {
                update_post_meta($post_id, '_poi_marker_image_id', absint($_POST['poi_marker_image_id']));
            }
            if (isset($_POST['poi_lat'])) {
                update_post_meta($post_id, '_poi_lat', sanitize_text_field($_POST['poi_lat']));
            }
            if (isset($_POST['poi_lng'])) {
                update_post_meta($post_id, '_poi_lng', sanitize_text_field($_POST['poi_lng']));
            }
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
