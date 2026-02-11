<?php
/**
 * Custom Post Type para Proyectos (Sectores/Terrenos)
 *
 * Un Proyecto es el contenedor principal que agrupa lotes.
 * Puede usar mapa 3D real o una imagen personalizada del terreno.
 *
 * @package MasterPlan_3D_Pro
 */

class Masterplan_Project_CPT
{

    /**
     * Registrar el Custom Post Type 'Proyecto'
     */
    public function register_post_type()
    {
        $labels = array(
            'name' => 'Proyectos',
            'singular_name' => 'Proyecto',
            'menu_name' => 'Proyectos',
            'name_admin_bar' => 'Proyecto',
            'add_new' => 'Agregar Nuevo',
            'add_new_item' => 'Agregar Nuevo Proyecto',
            'new_item' => 'Nuevo Proyecto',
            'edit_item' => 'Editar Proyecto',
            'view_item' => 'Ver Proyecto',
            'all_items' => 'Todos los Proyectos',
            'search_items' => 'Buscar Proyectos',
            'not_found' => 'No se encontraron proyectos',
            'not_found_in_trash' => 'No se encontraron proyectos en la papelera',
            'featured_image' => 'Imagen del Proyecto',
            'set_featured_image' => 'Establecer imagen del proyecto',
            'remove_featured_image' => 'Eliminar imagen del proyecto',
            'use_featured_image' => 'Usar como imagen del proyecto',
        );

        $args = array(
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => 'masterplan-settings', // Submenú de MasterPlan 3D
            'query_var' => true,
            'rewrite' => array('slug' => 'proyecto'),
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => 21,
            'menu_icon' => 'dashicons-admin-multisite',
            'supports' => array('title', 'editor', 'thumbnail', 'excerpt'),
            'show_in_rest' => true,
        );

        register_post_type('proyecto', $args);
    }

    /**
     * Agregar Meta Boxes para Proyecto
     */
    public function add_meta_boxes()
    {
        add_meta_box(
            'masterplan_project_location',
            'Ubicación del Proyecto',
            array($this, 'render_location_metabox'),
            'proyecto',
            'normal',
            'high'
        );

        add_meta_box(
            'masterplan_project_display',
            'Modo de Visualización',
            array($this, 'render_display_mode_metabox'),
            'proyecto',
            'normal',
            'high'
        );

        add_meta_box(
            'masterplan_project_logo',
            'Logo del Proyecto',
            array($this, 'render_logo_metabox'),
            'proyecto',
            'side',
            'default'
        );

        add_meta_box(
            'masterplan_project_lots',
            'Lotes del Proyecto',
            array($this, 'render_lots_metabox'),
            'proyecto',
            'side',
            'default'
        );

        add_meta_box(
            'masterplan_project_shortcode',
            'Código Embed',
            array($this, 'render_shortcode_metabox'),
            'proyecto',
            'side',
            'default'
        );
    }

    /**
     * Renderizar Meta Box de Ubicación
     */
    public function render_location_metabox($post)
    {
        wp_nonce_field('masterplan_save_project', 'masterplan_project_nonce');

        $location_name = get_post_meta($post->ID, '_project_location_name', true);
        $center_lat = get_post_meta($post->ID, '_project_center_lat', true);
        $center_lng = get_post_meta($post->ID, '_project_center_lng', true);
        $zoom = get_post_meta($post->ID, '_project_zoom', true) ?: 16;

?>
        <table class="form-table">
            <tr>
                <th><label for="project_location_name">Ciudad / Municipio</label></th>
                <td>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <input type="text" id="project_location_name" name="project_location_name"
                               value="<?php echo esc_attr($location_name); ?>" class="regular-text"
                               placeholder="Ej: Popayán, Cauca">
                        <button type="button" id="btn-search-location" class="button">
                            🔍 Buscar
                        </button>
                    </div>
                    <p class="description">Busca la ubicación y el mapa se centrará automáticamente</p>
                </td>
            </tr>
            <tr>
                <th><label for="project_center_lat">Latitud</label></th>
                <td>
                    <input type="text" id="project_center_lat" name="project_center_lat"
                           value="<?php echo esc_attr($center_lat); ?>" class="regular-text" readonly>
                </td>
            </tr>
            <tr>
                <th><label for="project_center_lng">Longitud</label></th>
                <td>
                    <input type="text" id="project_center_lng" name="project_center_lng"
                           value="<?php echo esc_attr($center_lng); ?>" class="regular-text" readonly>
                </td>
            </tr>
            <tr>
                <th><label for="project_zoom">Zoom</label></th>
                <td>
                    <input type="number" id="project_zoom" name="project_zoom"
                           value="<?php echo esc_attr($zoom); ?>" min="10" max="20" step="1">
                    <p class="description">Nivel de acercamiento (14-18 recomendado para lotes)</p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Renderizar Meta Box de Logo
     */
    public function render_logo_metabox($post)
    {
        $logo_id = get_post_meta($post->ID, '_project_logo_id', true);
        $logo_url = $logo_id ? wp_get_attachment_url($logo_id) : '';

        ?>
        <div style="text-align: center;">
            <input type="hidden" id="project_logo_id" name="project_logo_id" value="<?php echo esc_attr($logo_id); ?>">
            <div id="project-logo-preview" style="margin-bottom: 15px; min-height: 80px; display: flex; align-items: center; justify-content: center; background: #f0f0f1; border-radius: 4px; padding: 10px;">
                <?php if ($logo_url): ?>
                    <img src="<?php echo esc_url($logo_url); ?>" style="max-width: 100%; max-height: 100px;">
                <?php else: ?>
                    <span style="color: #ccc;">Sin Logo</span>
                <?php endif; ?>
            </div>

            <button type="button" id="btn-upload-logo" class="button button-secondary" style="width: 100%; margin-bottom: 5px;">
                <?php echo $logo_url ? 'Cambiar Logo' : 'Subir Logo'; ?>
            </button>

            <button type="button" id="btn-remove-logo" class="button-link-delete" style="<?php echo !$logo_id ? 'display:none;' : ''; ?>; color: #a00; text-decoration: none;">
                Eliminar Logo
            </button>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var logoFrame;

            $('#btn-upload-logo').on('click', function(e) {
                e.preventDefault();

                if (logoFrame) {
                    logoFrame.open();
                    return;
                }

                logoFrame = wp.media({
                    title: 'Seleccionar Logo del Proyecto',
                    button: { text: 'Usar como Logo' },
                    multiple: false
                });

                logoFrame.on('select', function() {
                    var attachment = logoFrame.state().get('selection').first().toJSON();
                    $('#project_logo_id').val(attachment.id);
                    $('#project-logo-preview').html('<img src="' + attachment.url + '" style="max-width: 100%; max-height: 100px;">');
                    $('#btn-remove-logo').show();
                    $('#btn-upload-logo').text('Cambiar Logo');
                });

                logoFrame.open();
            });

            $('#btn-remove-logo').on('click', function(e) {
                e.preventDefault();
                $('#project_logo_id').val('');
                $('#project-logo-preview').html('<span style="color: #ccc;">Sin Logo</span>');
                $(this).hide();
                $('#btn-upload-logo').text('Subir Logo');
            });
        });
        </script>
        <?php
    }

    /**
     * Renderizar Meta Box de Modo de Visualización
     */
    public function render_display_mode_metabox($post)
    {
        $use_custom_image = get_post_meta($post->ID, '_project_use_custom_image', true);
        $custom_image_id = get_post_meta($post->ID, '_project_custom_image_id', true);
        $custom_image_url = $custom_image_id ? wp_get_attachment_url($custom_image_id) : '';

        ?>
        <table class="form-table">
            <tr>
                <th><label>Tipo de Mapa</label></th>
                <td>
                    <fieldset>
                        <label>
                            <input type="radio" name="project_use_custom_image" value="0"
                                   <?php checked($use_custom_image, '0'); ?> <?php checked($use_custom_image, ''); ?>>
                            <strong>🗺️ Mapa 3D Real</strong> - Usar MapLibre con terreno satelital
                        </label>
                        <br><br>
                        <label>
                            <input type="radio" name="project_use_custom_image" value="1"
                                   <?php checked($use_custom_image, '1'); ?>>
                            <strong>🖼️ Imagen Personalizada</strong> - Subir imagen/plano del terreno
                        </label>
                    </fieldset>
                </td>
            </tr>
            <tr id="custom-image-row" style="<?php echo $use_custom_image != '1' ? 'display:none;' : ''; ?>">
                <th><label for="project_custom_image">Imagen del Terreno</label></th>
                <td>
                    <input type="hidden" id="project_custom_image_id" name="project_custom_image_id"
                           value="<?php echo esc_attr($custom_image_id); ?>">
                    <div id="custom-image-preview" style="margin-bottom: 10px;">
                        <?php if ($custom_image_url): ?>
                            <img src="<?php echo esc_url($custom_image_url); ?>"
                                 style="max-width: 400px; max-height: 300px; border: 2px solid #ddd; border-radius: 8px;">
                        <?php endif; ?>
                    </div>
                    <button type="button" id="btn-upload-image" class="button button-primary">
                        📤 Subir Imagen
                    </button>
                    <button type="button" id="btn-remove-image" class="button"
                            style="<?php echo !$custom_image_id ? 'display:none;' : ''; ?>">
                        ❌ Eliminar
                    </button>
                    <p class="description">Sube una imagen o plano del terreno. Los lotes se dibujarán sobre esta imagen.</p>
                </td>
            </tr>
        </table>

        <hr style="margin: 20px 0;">

        <div style="text-align: center;">
            <a href="<?php echo admin_url('admin.php?page=masterplan-project-editor&project_id=' . $post->ID); ?>"
               class="button button-primary button-hero" style="font-size: 16px;">
                🎨 Abrir Editor de Lotes
            </a>
            <p class="description" style="margin-top: 10px;">
                Dibuja los lotes directamente en el mapa o imagen del proyecto
            </p>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Mostrar/ocultar campo de imagen
            $('input[name="project_use_custom_image"]').on('change', function() {
                if ($(this).val() === '1') {
                    $('#custom-image-row').show();
                } else {
                    $('#custom-image-row').hide();
                }
            });

            // Subir imagen con Media Library
            $('#btn-upload-image').on('click', function(e) {
                e.preventDefault();

                var frame = wp.media({
                    title: 'Seleccionar Imagen del Terreno',
                    button: { text: 'Usar esta imagen' },
                    multiple: false
                });

                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    $('#project_custom_image_id').val(attachment.id);
                    $('#custom-image-preview').html(
                        '<img src="' + attachment.url + '" style="max-width: 400px; max-height: 300px; border: 2px solid #ddd; border-radius: 8px;">'
                    );
                    $('#btn-remove-image').show();
                });

                frame.open();
            });

            // Eliminar imagen
            $('#btn-remove-image').on('click', function(e) {
                e.preventDefault();
                $('#project_custom_image_id').val('');
                $('#custom-image-preview').html('');
                $(this).hide();
            });
        });
        </script>
        <?php
    }

    /**
     * Renderizar Meta Box de Lotes
     */
    public function render_lots_metabox($post)
    {
        // Obtener lotes asociados a este proyecto
        $lots = get_posts(array(
            'post_type' => 'lote',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_project_id',
                    'value' => $post->ID,
                    'compare' => '='
                )
            ),
            'orderby' => 'meta_value',
            'meta_key' => '_lot_number',
            'order' => 'ASC'
        ));

        $count_disponible = 0;
        $count_reservado = 0;
        $count_vendido = 0;

        foreach ($lots as $lot) {
            $status = get_post_meta($lot->ID, '_lot_status', true);
            if ($status === 'disponible') $count_disponible++;
            elseif ($status === 'reservado') $count_reservado++;
            elseif ($status === 'vendido') $count_vendido++;
        }

        ?>
        <div style="text-align: center; margin-bottom: 15px;">
            <strong style="font-size: 24px;"><?php echo count($lots); ?></strong>
            <br>
            <span style="color: #666;">Lotes totales</span>
        </div>

        <div style="display: flex; justify-content: space-around; text-align: center; margin-bottom: 15px;">
            <div>
                <span style="background: #10b981; color: white; padding: 4px 10px; border-radius: 12px; font-size: 12px;">
                    <?php echo $count_disponible; ?> Disponibles
                </span>
            </div>
            <div>
                <span style="background: #f59e0b; color: white; padding: 4px 10px; border-radius: 12px; font-size: 12px;">
                    <?php echo $count_reservado; ?> Reservados
                </span>
            </div>
            <div>
                <span style="background: #ef4444; color: white; padding: 4px 10px; border-radius: 12px; font-size: 12px;">
                    <?php echo $count_vendido; ?> Vendidos
                </span>
            </div>
        </div>

        <?php if (count($lots) > 0): ?>
            <ul style="max-height: 200px; overflow-y: auto; margin: 0; padding: 0; list-style: none;">
                <?php foreach ($lots as $lot):
                    $lot_number = get_post_meta($lot->ID, '_lot_number', true);
                    $status = get_post_meta($lot->ID, '_lot_status', true);
                    $status_color = $status === 'disponible' ? '#10b981' : ($status === 'reservado' ? '#f59e0b' : '#ef4444');
                ?>
                    <li style="padding: 8px 10px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                        <span>
                            <strong><?php echo esc_html($lot_number); ?></strong>
                            <small style="color: #666;"><?php echo esc_html($lot->post_title); ?></small>
                        </span>
                        <span style="width: 10px; height: 10px; background: <?php echo $status_color; ?>; border-radius: 50%;"></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p style="color: #666; text-align: center; font-style: italic;">
                No hay lotes creados aún
            </p>
        <?php endif; ?>

        <div style="margin-top: 15px; text-align: center;">
            <a href="<?php echo admin_url('admin.php?page=masterplan-project-editor&project_id=' . $post->ID); ?>"
               class="button button-primary">
                ➕ Agregar Lotes
            </a>
        </div>
        <?php
    }

    /**
     * Renderizar Meta Box de Shortcode
     */
    public function render_shortcode_metabox($post)
    {
        ?>
        <p>Copia este shortcode para mostrar el mapa del proyecto:</p>
        <code style="display: block; padding: 10px; background: #f1f1f1; border-radius: 4px; text-align: center; font-size: 13px;">
            [masterplan_map project="<?php echo $post->ID; ?>"]
        </code>
        <p class="description" style="margin-top: 10px;">
            Pégalo en cualquier página o entrada de WordPress
        </p>
        <?php
    }

    /**
     * Guardar Meta Boxes del Proyecto
     */
    public function save_meta_boxes($post_id)
    {
        // Verificar nonce
        if (!isset($_POST['masterplan_project_nonce']) ||
            !wp_verify_nonce($_POST['masterplan_project_nonce'], 'masterplan_save_project')) {
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

        // Verificar que es un proyecto
        if (get_post_type($post_id) !== 'proyecto') {
            return;
        }

        // Guardar ubicación
        if (isset($_POST['project_location_name'])) {
            update_post_meta($post_id, '_project_location_name', sanitize_text_field($_POST['project_location_name']));
        }

        if (isset($_POST['project_center_lat'])) {
            update_post_meta($post_id, '_project_center_lat', sanitize_text_field($_POST['project_center_lat']));
        }

        if (isset($_POST['project_center_lng'])) {
            update_post_meta($post_id, '_project_center_lng', sanitize_text_field($_POST['project_center_lng']));
        }

        if (isset($_POST['project_zoom'])) {
            update_post_meta($post_id, '_project_zoom', absint($_POST['project_zoom']));
        }

        // Guardar modo de visualización
        if (isset($_POST['project_use_custom_image'])) {
            update_post_meta($post_id, '_project_use_custom_image', sanitize_text_field($_POST['project_use_custom_image']));
        }

        if (isset($_POST['project_custom_image_id'])) {
            update_post_meta($post_id, '_project_custom_image_id', absint($_POST['project_custom_image_id']));
        }

        // Guardar Logo
        if (isset($_POST['project_logo_id'])) {
            update_post_meta($post_id, '_project_logo_id', absint($_POST['project_logo_id']));
        }
    }
}
