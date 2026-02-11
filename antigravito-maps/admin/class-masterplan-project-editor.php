<?php
/**
 * Editor de Proyectos - Página administrativa
 *
 * Permite crear y editar los lotes dentro de un proyecto
 * usando mapa 3D o imagen personalizada.
 *
 * @package MasterPlan_3D_Pro
 */

class Masterplan_Project_Editor
{

    /**
     * Renderizar página del editor de proyectos
     */
    public function render_editor_page()
    {
        $project_id = isset($_GET['project_id']) ? absint($_GET['project_id']) : 0;
        $lot_id = isset($_GET['lot_id']) ? absint($_GET['lot_id']) : 0;

        // Si no hay proyecto, mostrar selector
        if (!$project_id) {
            $this->render_project_selector();
            return;
        }

        // Obtener datos del proyecto
        $project = get_post($project_id);
        if (!$project || $project->post_type !== 'proyecto') {
            echo '<div class="notice notice-error"><p>Proyecto no encontrado.</p></div>';
            return;
        }

        // Enqueue Media
        wp_enqueue_media();

        // Obtener configuración del proyecto
        $use_custom_image = get_post_meta($project_id, '_project_use_custom_image', true);
        $custom_image_id = get_post_meta($project_id, '_project_custom_image_id', true);
        $custom_image_url = $custom_image_id ? wp_get_attachment_url($custom_image_id) : '';
        $center_lat = get_post_meta($project_id, '_project_center_lat', true) ?: get_option('masterplan_map_center_lat', '2.4568');
        $center_lng = get_post_meta($project_id, '_project_center_lng', true) ?: get_option('masterplan_map_center_lng', '-76.6310');
        $zoom = get_post_meta($project_id, '_project_zoom', true) ?: 16;

        // Obtener lotes del proyecto
        $lots = get_posts(array(
            'post_type' => 'lote',
            'posts_per_page' => -1,
            'meta_query' => array(
                    array(
                    'key' => '_project_id',
                    'value' => $project_id,
                    'compare' => '='
                )
            ),
            'post_status' => array('publish', 'draft')
        ));

        // Preparar datos de lotes
        global $wpdb;
        $table_name = $wpdb->prefix . 'masterplan_polygons';

        $lots_data = array();
        foreach ($lots as $lot) {
            $polygon = $wpdb->get_row($wpdb->prepare(
                "SELECT coordinates FROM $table_name WHERE lot_id = %d ORDER BY id DESC LIMIT 1",
                $lot->ID
            ));

            // Overlay data
            $overlay_id = get_post_meta($lot->ID, '_lot_overlay_id', true);
            $overlay_url = $overlay_id ? wp_get_attachment_url($overlay_id) : '';
            $overlay_corners = get_post_meta($lot->ID, '_lot_overlay_corners', true); // Array of 4 coords

            $lots_data[] = array(
                'id' => $lot->ID,
                'title' => $lot->post_title,
                'lot_number' => get_post_meta($lot->ID, '_lot_number', true),
                'status' => get_post_meta($lot->ID, '_lot_status', true),
                'price' => get_post_meta($lot->ID, '_lot_price', true),
                'area' => get_post_meta($lot->ID, '_lot_area', true),
                'coordinates' => $polygon ? json_decode($polygon->coordinates, true) : null,
                'thumbnail' => get_the_post_thumbnail_url($lot->ID, 'thumbnail'),
                'overlay_url' => $overlay_url,
                'overlay_corners' => $overlay_corners ? json_decode($overlay_corners) : null
            );
        }

        // Obtener POIs del proyecto
        $pois = get_posts(array(
            'post_type' => 'masterplan_poi',
            'posts_per_page' => -1,
            'meta_query' => array(
                    array(
                    'key' => '_project_id',
                    'value' => $project_id,
                    'compare' => '='
                )
            ),
            'post_status' => array('publish', 'draft')
        ));

        $pois_data = array();
        foreach ($pois as $poi) {
            $custom_image_id = get_post_meta($poi->ID, '_poi_custom_image_id', true);
            $pois_data[] = array(
                'id' => $poi->ID,
                'title' => $poi->post_title,
                'type' => get_post_meta($poi->ID, '_poi_type', true) ?: 'info',
                'style' => get_post_meta($poi->ID, '_poi_style', true) ?: 'default',
                'custom_image_url' => $custom_image_id ? wp_get_attachment_url($custom_image_id) : '',
                'lat' => get_post_meta($poi->ID, '_poi_lat', true),
                'lng' => get_post_meta($poi->ID, '_poi_lng', true),
                'thumbnail' => get_the_post_thumbnail_url($poi->ID, 'thumbnail')
            );
        }

        // Obtener Rutas
        $routes = get_posts(array(
            'post_type' => 'masterplan_route',
            'posts_per_page' => -1,
            'meta_query' => array(
                    array(
                    'key' => '_project_id',
                    'value' => $project_id,
                    'compare' => '='
                )
            ),
            'post_status' => array('publish', 'draft')
        ));

        $routes_data = array();
        foreach ($routes as $route) {
            $coordinates = get_post_meta($route->ID, '_route_coordinates', true);
            $routes_data[] = array(
                'id' => $route->ID,
                'title' => $route->post_title,
                'color' => get_post_meta($route->ID, '_route_color', true) ?: '#ffffff',
                'width' => get_post_meta($route->ID, '_route_width', true) ?: 4,
                'coordinates' => $coordinates ? json_decode($coordinates) : null
            );
        }

        // Obtener Overlays del Proyecto
        $overlays_meta = get_post_meta($project_id, '_project_overlays', true);
        $overlays_data = is_array($overlays_meta) ? $overlays_meta : array();

?>
        <div class="wrap masterplan-editor-wrap">
            <div class="masterplan-editor-header">
                <div class="header-left">
                    <a href="<?php echo admin_url('edit.php?post_type=proyecto'); ?>" class="back-link">
                        ← Volver a Proyectos
                    </a>
                    <h1>
                        <span class="dashicons dashicons-admin-multisite"></span>
                        <?php echo esc_html($project->post_title); ?>
                    </h1>
                </div>
                <div class="header-right">
                    <span class="lot-count">
                        <strong><?php echo count($lots); ?></strong> lotes
                    </span>
                    <a href="<?php echo get_edit_post_link($project_id); ?>" class="button">
                        ⚙️ Configuración
                    </a>
                </div>
            </div>

            <div class="masterplan-editor-container">
                <!-- Panel izquierdo: Listas -->
                <div class="lots-panel">
                    <div class="panel-header-tabs">
                        <div class="tab-item active" data-tab="lots">📍 Lotes</div>
                        <div class="tab-item" data-tab="pois">🚩 Puntos</div>
                        <div class="tab-item" data-tab="routes">🛣️ Rutas</div>
                        <div class="tab-item" data-tab="overlays">🗺️ Planos</div>
                    </div>

                    <!-- Pestaña LOTES -->
                    <div class="tab-content active" id="tab-content-lots">
                        <div class="panel-actions">
                            <button type="button" id="btn-new-lot" class="button button-primary" style="width:100%">
                                ➕ Nuevo Lote
                            </button>
                        </div>
                        <div class="lots-list" id="lots-list">
                            <?php if (empty($lots_data)): ?>
                                <div class="no-items">
                                    <p>No hay lotes creados</p>
                                    <small>Haz clic en "Nuevo Lote" para comenzar</small>
                                </div>
                            <?php
        else: ?>
                                <?php foreach ($lots_data as $lot):
                $status_colors = array('disponible' => '#10b981', 'reservado' => '#f59e0b', 'vendido' => '#ef4444');
                $status_color = isset($status_colors[$lot['status']]) ? $status_colors[$lot['status']] : '#ccc';
?>
                                    <div class="lot-item <?php echo $lot_id == $lot['id'] ? 'active' : ''; ?>"
                                         data-lot-id="<?php echo $lot['id']; ?>">
                                        <div class="lot-status-indicator" style="background: <?php echo $status_color; ?>"></div>
                                        <div class="lot-info">
                                            <strong><?php echo esc_html($lot['lot_number'] ?: 'Sin número'); ?></strong>
                                            <small><?php echo esc_html($lot['title']); ?></small>
                                            <?php if ($lot['price']): ?>
                                                <span class="lot-price">$ <?php echo number_format($lot['price'], 0, ',', '.'); ?></span>
                                            <?php
                endif; ?>
                                        </div>
                                        <div class="lot-actions">
                                            <button type="button" class="btn-draw" title="Dibujar polígono">
                                                <?php echo $lot['coordinates'] ? '✏️' : '🎨'; ?>
                                            </button>
                                            <a href="<?php echo get_edit_post_link($lot['id']); ?>" class="btn-edit" title="Editar lote">
                                                ⚙️
                                            </a>
                                        </div>
                                    </div>
                                <?php
            endforeach; ?>
                            <?php
        endif; ?>
                        </div>
                    </div>

                    <!-- Pestaña POIS -->
                    <div class="tab-content" id="tab-content-pois" style="display:none;">
                        <div class="panel-actions">
                            <button type="button" id="btn-new-poi" class="button button-primary" style="width:100%">
                                ➕ Nuevo Punto de Interés
                            </button>
                        </div>
                        <div class="lots-list" id="pois-list">
                            <?php if (empty($pois_data)): ?>
                                <div class="no-items">
                                    <p>No hay POIs creados</p>
                                    <small>Haz clic en "Nuevo Punto" para comenzar</small>
                                </div>
                            <?php else: ?>
                                <?php foreach ($pois_data as $poi):
                                    $has_coords = ($poi['lat'] && $poi['lng']);
                                ?>
                                    <div class="poi-item" data-poi-id="<?php echo $poi['id']; ?>">
                                        <div class="lot-info">
                                            <strong><?php echo esc_html($poi['title']); ?></strong>
                                            <small><?php echo esc_html($poi['type']); ?></small>
                                        </div>
                                        <div class="poi-actions">
                                            <button type="button" class="btn-locate-poi" title="Ubicar en Mapa" style="color: <?php echo $has_coords ? '#10b981' : '#ccc'; ?>">
                                                📍
                                            </button>
                                            <a href="<?php echo get_edit_post_link($poi['id']); ?>" class="btn-edit" title="Editar POI">
                                                ⚙️
                                            </a>
                                            <button type="button" class="btn-delete-poi" title="Eliminar POI" style="color: #ef4444;">
                                                🗑️
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Pestaña RUTAS -->
                    <div class="tab-content" id="tab-content-routes" style="display:none;">
                        <div class="panel-actions">
                            <button type="button" id="btn-new-route" class="button button-primary" style="width:100%">
                                ➕ Nueva Ruta/Vía
                            </button>
                        </div>
                        <div class="lots-list" id="routes-list">
                            <?php if (empty($routes_data)): ?>
                                <div class="no-items">
                                    <p>No hay Rutas creadas</p>
                                    <small>Haz clic en "Nueva Ruta" para comenzar</small>
                                </div>
                            <?php else: ?>
                                <?php foreach ($routes_data as $route):
                                    $has_path = !empty($route['coordinates']);
                                ?>
                                    <div class="route-item" data-route-id="<?php echo $route['id']; ?>">
                                        <div class="lot-status-indicator" style="background: <?php echo $route['color']; ?>"></div>
                                        <div class="lot-info">
                                            <strong><?php echo esc_html($route['title']); ?></strong>
                                        </div>
                                        <div class="lot-actions">
                                            <button type="button" class="btn-draw-route" title="Dibujar Ruta" style="color: <?php echo $has_path ? '#10b981' : '#ccc'; ?>">
                                                ✏️
                                            </button>
                                            <button type="button" class="btn-delete-route" title="Eliminar Ruta" style="color: #ef4444;">
                                                🗑️
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Pestaña OVERLAYS -->
                    <div class="tab-content" id="tab-content-overlays" style="display:none;">
                        <div class="panel-actions">
                            <button type="button" id="btn-new-project-overlay" class="button button-primary" style="width:100%">
                                ➕ Nuevo Plano/Mapa
                            </button>
                        </div>
                        <div class="lots-list" id="overlays-list">
                            <?php if (empty($overlays_data)): ?>
                                <div class="no-items">
                                    <p>No hay Planos agregados</p>
                                    <small>Sube una imagen (Blueprint, Mapa de sitio)</small>
                                </div>
                            <?php else: ?>
                                <?php foreach ($overlays_data as $overlay): ?>
                                    <div class="overlay-item" data-overlay-id="<?php echo $overlay['id']; ?>">
                                        <div class="lot-info">
                                            <strong><?php echo esc_html($overlay['title'] ?: 'Plano sin título'); ?></strong>
                                        </div>
                                        <div class="lot-actions">
                                            <button type="button" class="btn-edit-project-overlay" title="Ajustar Posición">
                                                🖼️
                                            </button>
                                            <button type="button" class="btn-delete-project-overlay" title="Eliminar Plano" style="color: #ef4444;">
                                                🗑️
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

                <!-- Panel derecho: Editor de mapa/imagen -->
                <div class="editor-panel">
                    <?php if ($use_custom_image == '1' && $custom_image_url): ?>
                        <!-- Modo Imagen -->
                        <div class="editor-mode-badge image">🖼️ Modo Imagen</div>
                        <div id="image-editor-container" class="image-editor-container">
                            <canvas id="image-canvas"></canvas>
                            <img id="background-image" src="<?php echo esc_url($custom_image_url); ?>" style="display: none;">
                        </div>
                    <?php
        else: ?>
                        <!-- Modo Mapa 3D -->
                        <div class="editor-mode-badge map">🗺️ Modo Mapa 3D</div>
                        <div id="map-editor-container" class="map-editor-container"></div>
                    <?php
        endif; ?>

                    <!-- Controles del editor -->
                    <div class="editor-controls">
                        <div class="control-group" id="controls-lots">
                            <button type="button" id="btn-draw-polygon" class="button" disabled title="Dibujar polígono del lote">
                                🎨 Dibujar
                            </button>
                            <button type="button" id="btn-edit-overlay" class="button" disabled title="Ajustar imagen superpuesta">
                                🖼️ Overlay
                            </button>
                            <button type="button" id="btn-save-polygon" class="button button-primary" disabled>
                                💾 Guardar
                            </button>
                            <button type="button" id="btn-clear-polygon" class="button" disabled title="Borrar polígono">
                                🗑️
                            </button>
                        </div>

                        <!-- Overlay Tools (Hidden by default) -->
                        <div class="control-group" id="controls-overlay" style="display:none; border-left: 1px solid #ddd; padding-left: 10px;">
                            <button type="button" id="btn-upload-overlay" class="button">📤 Subir Img</button>
                            <button type="button" id="btn-remove-overlay" class="button" style="color:red;">❌</button>
                            <span style="font-size:11px; color:#666;">Arrastra las 4 esquinas</span>
                        </div>
                        <div class="control-group" id="controls-pois" style="display:none;">
                            <button type="button" id="btn-place-poi" class="button" disabled>
                                📍 Ubicar Punto
                            </button>
                            <button type="button" id="btn-save-poi" class="button button-primary" disabled>
                                💾 Guardar Ubicación
                            </button>
                        </div>
                        <div class="control-group" id="controls-routes" style="display:none;">
                            <button type="button" id="btn-draw-route" class="button" disabled>
                                ✏️ Trazar Ruta
                            </button>
                            <button type="button" id="btn-clear-route" class="button" disabled>
                                🗑️ Borrar
                            </button>
                            <button type="button" id="btn-save-route" class="button button-primary" disabled>
                                💾 Guardar Ruta
                            </button>
                        </div>
                        <div class="control-group" id="controls-overlays-project" style="display:none;">
                            <button type="button" id="btn-edit-overlay-pos" class="button" disabled>
                                📐 Ajustar Esquinas
                            </button>
                            <button type="button" id="btn-save-overlay-project" class="button button-primary" disabled>
                                💾 Guardar Posición
                            </button>
                            <div style="display: flex; align-items: center; gap: 5px; font-size: 11px;">
                                <label>Opacidad:</label>
                                <input type="range" id="overlay-opacity-slider" min="0" max="1" step="0.1" value="0.7" style="width: 60px;">
                            </div>
                        </div>
                        <div class="control-info">
                            <span id="editor-status">Selecciona un elemento para editar</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal para nuevo lote -->
            <div id="new-lot-modal" class="modal-overlay" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>➕ Crear Nuevo Lote</h2>
                        <button type="button" class="modal-close">&times;</button>
                    </div>
                    <form id="new-lot-form">
                        <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">

                        <div class="form-row">
                            <label for="new_lot_number">Número de Lote *</label>
                            <input type="text" id="new_lot_number" name="lot_number" required placeholder="Ej: L-001">
                        </div>

                        <div class="form-row">
                            <label for="new_lot_title">Nombre/Título</label>
                            <input type="text" id="new_lot_title" name="title" placeholder="Ej: Lote Esquinero Vista Panorámica">
                        </div>

                        <div class="form-row">
                            <label for="new_lot_status">Estado</label>
                            <select id="new_lot_status" name="status">
                                <option value="disponible">🟢 Disponible</option>
                                <option value="reservado">🟡 Reservado</option>
                                <option value="vendido">🔴 Vendido</option>
                            </select>
                        </div>

                        <div class="form-row">
                            <label for="new_lot_price">Precio (COP)</label>
                            <input type="number" id="new_lot_price" name="price" placeholder="0" min="0">
                        </div>

                        <div class="form-row">
                            <label for="new_lot_area">Área (m²)</label>
                            <input type="number" id="new_lot_area" name="area" placeholder="0" step="0.01" min="0">
                        </div>

                        <div class="form-actions">
                            <button type="button" class="button modal-close">Cancelar</button>
                            <button type="submit" class="button button-primary">Crear Lote</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal para nuevo POI -->
            <div id="new-poi-modal" class="modal-overlay" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>➕ Nuevo Punto de Interés</h2>
                        <button type="button" class="modal-close">&times;</button>
                    </div>
                    <form id="new-poi-form">
                        <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">

                        <div class="form-row">
                            <label for="new_poi_title">Nombre del POI *</label>
                            <input type="text" id="new_poi_title" name="title" required placeholder="Ej: Entrada Principal">
                        </div>

                        <div class="form-row">
                            <label for="new_poi_type">Tipo</label>
                            <select id="new_poi_type" name="type">
                                <option value="info">ℹ️ Información</option>
                                <option value="park">🌳 Parque / Zona Verde</option>
                                <option value="facility">🏢 Instalación / Amenidad</option>
                                <option value="entrance">🚪 Acceso / Portería</option>
                            </select>
                        </div>

                        <div class="form-row">
                            <label for="new_poi_style">Estilo del Pin</label>
                            <select id="new_poi_style" name="style">
                                <option value="default">📍 Estándar</option>
                                <option value="orthogonal-label">🏷️ Ortogonal con Etiqueta</option>
                                <option value="orthogonal">📏 Línea Ortogonal con Logo</option>
                                <option value="gold">🏆 Icono 3D Dorado</option>
                                <option value="flag">🚩 Bandera</option>
                            </select>
                        </div>

                        <div class="form-row" id="poi-custom-image-row" style="display:none;">
                            <label>Logo / Icono Personalizado</label>
                            <div class="image-upload-container">
                                <input type="hidden" name="custom_image_id" id="new_poi_custom_image_id">
                                <div id="poi-image-preview" style="margin-bottom: 10px; max-width: 100px; display: none;"></div>
                                <button type="button" class="button" id="btn-upload-poi-image">Subir Imagen</button>
                                <button type="button" class="button" id="btn-remove-poi-image" style="display:none; color: #ef4444;">Eliminar</button>
                            </div>
                            <p class="description">Recomendado: PNG transparente</p>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="button modal-close">Cancelar</button>
                            <button type="submit" class="button button-primary">Crear POI</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal para nueva Ruta -->
            <div id="new-route-modal" class="modal-overlay" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>➕ Nueva Ruta/Vía</h2>
                        <button type="button" class="modal-close">&times;</button>
                    </div>
                    <form id="new-route-form">
                        <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">

                        <div class="form-row">
                            <label for="new_route_title">Nombre de la Ruta *</label>
                            <input type="text" id="new_route_title" name="title" required placeholder="Ej: Vía Principal de Acceso">
                        </div>

                        <div class="form-row">
                            <label for="new_route_color">Color del Trazado</label>
                            <input type="color" id="new_route_color" name="color" value="#ffffff" style="height: 40px; padding: 2px;">
                        </div>

                        <div class="form-row">
                            <label for="new_route_width">Grosor de Línea</label>
                            <input type="number" id="new_route_width" name="width" value="4" min="1" max="20">
                        </div>

                        <div class="form-actions">
                            <button type="button" class="button modal-close">Cancelar</button>
                            <button type="submit" class="button button-primary">Crear Ruta</button>
                        </div>
                    </form>
                </div>
            </div>

        </div>

        <style>
        .masterplan-editor-wrap {
            margin-right: 20px;
        }

        .masterplan-editor-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .masterplan-editor-header h1 {
            color: white;
            margin: 0;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .masterplan-editor-header .back-link {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            font-size: 12px;
            display: block;
            margin-bottom: 5px;
        }

        .masterplan-editor-header .back-link:hover {
            color: white;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .lot-count {
            background: rgba(255,255,255,0.2);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
        }

        .masterplan-editor-container {
            display: flex;
            gap: 20px;
            min-height: 600px;
        }

        .lots-panel {
            width: 320px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
        }

        .panel-header-tabs {
            display: flex;
            border-bottom: 1px solid #eee;
        }

        .tab-item {
            flex: 1;
            text-align: center;
            padding: 15px;
            cursor: pointer;
            font-weight: 600;
            color: #666;
            background: #f9f9f9;
        }

        .tab-item:first-child {
            border-top-left-radius: 8px;
        }

        .tab-item:last-child {
            border-top-right-radius: 8px;
        }

        .tab-item.active {
            background: white;
            color: #667eea;
            border-bottom: 2px solid #667eea;
        }

        .panel-actions {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }

        .tab-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .lots-list {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
        }

        /* LOT ITEMS */
        .lot-item, .poi-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 6px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .lot-item:hover, .poi-item:hover {
            background: #e9ecef;
        }

        .lot-item.active, .poi-item.active {
            background: #667eea;
            color: white;
        }

        .lot-item.active .lot-price {
            color: rgba(255,255,255,0.8);
        }

        .lot-status-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .lot-info, .poi-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .lot-info strong, .poi-info strong {
            font-size: 13px;
        }

        .lot-info small, .poi-info small {
            font-size: 11px;
            opacity: 0.7;
        }

        .lot-price {
            font-size: 11px;
            color: #10b981;
            font-weight: 600;
        }

        .lot-actions, .poi-actions {
            display: flex;
            gap: 5px;
        }

        .lot-actions button, .lot-actions a, .poi-actions button {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            padding: 4px;
            opacity: 0.7;
            text-decoration: none;
        }

        .lot-actions button:hover, .lot-actions a:hover, .poi-actions button:hover {
            opacity: 1;
        }

        .no-items {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }

        .editor-panel {
            flex: 1;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
        }

        .editor-mode-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            z-index: 100;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .editor-mode-badge.map {
            background: #3b82f6;
            color: white;
        }

        .editor-mode-badge.image {
            background: #8b5cf6;
            color: white;
        }

        .map-editor-container,
        .image-editor-container {
            flex: 1;
            min-height: 500px;
        }

        .image-editor-container {
            position: relative;
            overflow: hidden;
        }

        #image-canvas {
            position: absolute;
            top: 0;
            left: 0;
        }

        .editor-controls {
            padding: 15px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .control-group {
            display: flex;
            gap: 10px;
        }

        .control-info {
            color: #666;
            font-size: 13px;
        }

        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.6);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            width: 450px;
            max-width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 18px;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }

        .modal-close:hover {
            color: #333;
        }

        #new-lot-form, #poi-form {
            padding: 20px;
        }

        .form-row {
            margin-bottom: 15px;
        }

        .form-row label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            font-size: 13px;
        }

        .form-row input,
        .form-row select,
        .form-row textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        </style>

        <script>
        var masterplanEditorData = {
            projectId: <?php echo $project_id; ?>,
            lots: <?php echo json_encode($lots_data); ?>,
            pois: <?php echo json_encode($pois_data); ?>,
            routes: <?php echo json_encode($routes_data); ?>,
            overlays: <?php echo json_encode($overlays_data); ?>,
            useCustomImage: <?php echo $use_custom_image == '1' ? 'true' : 'false'; ?>,
            customImageUrl: '<?php echo esc_js($custom_image_url); ?>',
            centerLat: <?php echo floatval($center_lat); ?>,
            centerLng: <?php echo floatval($center_lng); ?>,
            zoom: <?php echo intval($zoom); ?>,
            apiKey: '<?php echo esc_js(get_option('masterplan_api_key', '')); ?>',
            ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('masterplan_admin_nonce'); ?>',
            selectedLotId: <?php echo $lot_id ?: 'null'; ?>
        };

        jQuery(document).ready(function($) {
            // Mostrar/Ocultar subida de imagen según estilo
            $('#new_poi_style').on('change', function() {
                var style = $(this).val();
                if (style === 'orthogonal' || style === 'gold' || style === 'flag') {
                    $('#poi-custom-image-row').slideDown();
                } else {
                    $('#poi-custom-image-row').slideUp();
                }
            });

            // Media Uploader para POI
            var poiFrame;
            $('#btn-upload-poi-image').on('click', function(e) {
                e.preventDefault();
                if (poiFrame) {
                    poiFrame.open();
                    return;
                }
                poiFrame = wp.media({
                    title: 'Seleccionar Logo o Icono',
                    button: { text: 'Usar esta imagen' },
                    multiple: false
                });
                poiFrame.on('select', function() {
                    var attachment = poiFrame.state().get('selection').first().toJSON();
                    $('#new_poi_custom_image_id').val(attachment.id);
                    $('#poi-image-preview').html('<img src="'+attachment.sizes.thumbnail.url+'" style="max-width:100px;">').show();
                    $('#btn-remove-poi-image').show();
                    $('#btn-upload-poi-image').text('Cambiar Imagen');
                });
                poiFrame.open();
            });

            $('#btn-remove-poi-image').on('click', function() {
                $('#new_poi_custom_image_id').val('');
                $('#poi-image-preview').hide().empty();
                $(this).hide();
                $('#btn-upload-poi-image').text('Subir Imagen');
            });
        });
        </script>
        <?php
    }

    /**
     * Renderizar selector de proyectos
     */
    private function render_project_selector()
    {
        $projects = get_posts(array(
            'post_type' => 'proyecto',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => 'publish'
        ));

?>
        <div class="wrap">
            <h1><span class="dashicons dashicons-admin-multisite"></span> Editor de Proyectos</h1>

            <?php if (empty($projects)): ?>
                <div class="notice notice-warning">
                    <p>No hay proyectos creados. <a href="<?php echo admin_url('post-new.php?post_type=proyecto'); ?>">Crear un proyecto</a> primero.</p>
                </div>
            <?php
        else: ?>
                <div style="background: white; padding: 30px; border-radius: 8px; max-width: 600px;">
                    <h2>Selecciona un proyecto para editar sus lotes:</h2>
                    <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 20px;">
                        <?php foreach ($projects as $project):
                $lot_count = count(get_posts(array(
                    'post_type' => 'lote',
                    'posts_per_page' => -1,
                    'meta_query' => array(array('key' => '_project_id', 'value' => $project->ID))
                )));
?>
                            <a href="<?php echo admin_url('admin.php?page=masterplan-project-editor&project_id=' . $project->ID); ?>"
                               style="display: flex; align-items: center; justify-content: space-between; padding: 15px 20px; background: #f8f9fa; border-radius: 8px; text-decoration: none; color: #333; transition: all 0.2s;">
                                <div>
                                    <strong style="font-size: 16px;"><?php echo esc_html($project->post_title); ?></strong>
                                    <br>
                                    <small style="color: #666;"><?php echo $lot_count; ?> lotes</small>
                                </div>
                                <span style="font-size: 20px;">→</span>
                            </a>
                        <?php
            endforeach; ?>
                    </div>
                </div>
            <?php
        endif; ?>
        </div>
        <?php
    }

    /**
     * AJAX: Crear nuevo lote
     */
    public function create_lot()
    {
        check_ajax_referer('masterplan_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permisos insuficientes'));
        }

        $project_id = isset($_POST['project_id']) ? absint($_POST['project_id']) : 0;
        $lot_number = isset($_POST['lot_number']) ? sanitize_text_field($_POST['lot_number']) : '';
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'disponible';
        $price = isset($_POST['price']) ? floatval($_POST['price']) : 0;
        $area = isset($_POST['area']) ? floatval($_POST['area']) : 0;

        if (!$project_id || !$lot_number) {
            wp_send_json_error(array('message' => 'Datos incompletos'));
        }

        // Crear el lote
        $lot_title = $title ?: 'Lote ' . $lot_number;
        $lot_id = wp_insert_post(array(
            'post_type' => 'lote',
            'post_title' => $lot_title,
            'post_status' => 'publish'
        ));

        if (is_wp_error($lot_id)) {
            wp_send_json_error(array('message' => 'Error al crear el lote'));
        }

        // Guardar metadatos
        update_post_meta($lot_id, '_project_id', $project_id);
        update_post_meta($lot_id, '_lot_number', $lot_number);
        update_post_meta($lot_id, '_lot_status', $status);
        update_post_meta($lot_id, '_lot_price', $price);
        update_post_meta($lot_id, '_lot_area', $area);

        wp_send_json_success(array(
            'message' => 'Lote creado exitosamente',
            'lot_id' => $lot_id,
            'lot' => array(
                'id' => $lot_id,
                'title' => $lot_title,
                'lot_number' => $lot_number,
                'status' => $status,
                'price' => $price,
                'area' => $area,
                'coordinates' => null
            )
        ));
    }

    /**
     * AJAX: Guardar polígono de lote
     */
    public function save_lot_polygon()
    {
        check_ajax_referer('masterplan_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permisos insuficientes'));
        }

        $lot_id = isset($_POST['lot_id']) ? absint($_POST['lot_id']) : 0;
        $project_id = isset($_POST['project_id']) ? absint($_POST['project_id']) : 0;
        $coordinates = isset($_POST['coordinates']) ? $_POST['coordinates'] : '';

        if (!$lot_id || !$coordinates) {
            wp_send_json_error(array('message' => 'Datos incompletos'));
        }

        // Validar JSON
        $decoded = json_decode(stripslashes($coordinates));
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array('message' => 'Coordenadas inválidas'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'masterplan_polygons';

        // Verificar si ya existe
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE lot_id = %d",
            $lot_id
        ));

        if ($existing) {
            $wpdb->update(
                $table_name,
                array(
                'coordinates' => stripslashes($coordinates),
                'project_id' => $project_id
            ),
                array('lot_id' => $lot_id),
                array('%s', '%d'),
                array('%d')
            );
        }
        else {
            $wpdb->insert(
                $table_name,
                array(
                'lot_id' => $lot_id,
                'project_id' => $project_id,
                'coordinates' => stripslashes($coordinates)
            ),
                array('%d', '%d', '%s')
            );
        }

        wp_send_json_success(array(
            'message' => 'Polígono guardado exitosamente',
            'lot_id' => $lot_id
        ));
    }

    /**
     * AJAX: Guardar Overlay de Lote
     */
    public function save_lot_overlay()
    {
        check_ajax_referer('masterplan_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error();

        $lot_id = isset($_POST['lot_id']) ? absint($_POST['lot_id']) : 0;
        $overlay_id = isset($_POST['overlay_id']) ? absint($_POST['overlay_id']) : 0;
        $corners = isset($_POST['corners']) ? $_POST['corners'] : ''; // JSON string

        if (!$lot_id) wp_send_json_error();

        if ($overlay_id) {
            update_post_meta($lot_id, '_lot_overlay_id', $overlay_id);
        } else {
            delete_post_meta($lot_id, '_lot_overlay_id');
        }

        if ($corners) {
            update_post_meta($lot_id, '_lot_overlay_corners', stripslashes($corners));
        }

        wp_send_json_success(array('message' => 'Overlay guardado'));
    }


    /**
     * AJAX: Búsqueda de ubicación con Nominatim
     */
    public function search_location()
    {
        check_ajax_referer('masterplan_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permisos insuficientes'));
        }

        $query = isset($_GET['query']) ? sanitize_text_field($_GET['query']) : '';

        if (strlen($query) < 3) {
            wp_send_json_error(array('message' => 'Búsqueda muy corta'));
        }

        // Agregar ", Colombia" para mejorar resultados
        $search_query = $query . ', Colombia';

        // Llamar a Nominatim (OpenStreetMap)
        $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query(array(
            'q' => $search_query,
            'format' => 'json',
            'limit' => 5,
            'countrycodes' => 'co'
        ));

        $response = wp_remote_get($url, array(
            'headers' => array(
                'User-Agent' => 'MasterPlan3DPro/1.0'
            ),
            'timeout' => 10
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => 'Error de conexión'));
        }

        $body = wp_remote_retrieve_body($response);
        $results = json_decode($body, true);

        if (empty($results)) {
            wp_send_json_error(array('message' => 'Sin resultados'));
        }

        $locations = array();
        foreach ($results as $result) {
            $locations[] = array(
                'name' => $result['display_name'],
                'lat' => floatval($result['lat']),
                'lng' => floatval($result['lon'])
            );
        }

        wp_send_json_success(array('locations' => $locations));
    }

    /**
     * AJAX: Crear nuevo POI
     */
    public function create_poi()
    {
        check_ajax_referer('masterplan_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permisos insuficientes'));
        }

        $project_id = isset($_POST['project_id']) ? absint($_POST['project_id']) : 0;
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'info';
        $style = isset($_POST['style']) ? sanitize_text_field($_POST['style']) : 'default';
        $custom_image_id = isset($_POST['custom_image_id']) ? absint($_POST['custom_image_id']) : 0;

        if (!$project_id || !$title) {
            wp_send_json_error(array('message' => 'Datos incompletos'));
        }

        // Crear el POI
        $poi_id = wp_insert_post(array(
            'post_type' => 'masterplan_poi',
            'post_title' => $title,
            'post_status' => 'publish'
        ));

        if (is_wp_error($poi_id)) {
            wp_send_json_error(array('message' => 'Error al crear el POI'));
        }

        // Guardar metadatos
        update_post_meta($poi_id, '_project_id', $project_id);
        update_post_meta($poi_id, '_poi_type', $type);
        update_post_meta($poi_id, '_poi_style', $style);
        if ($custom_image_id) {
            update_post_meta($poi_id, '_poi_custom_image_id', $custom_image_id);
        }

        wp_send_json_success(array(
            'message' => 'POI creado exitosamente',
            'poi' => array(
                'id' => $poi_id,
                'title' => $title,
                'type' => $type,
                'style' => $style,
                'custom_image_url' => $custom_image_id ? wp_get_attachment_url($custom_image_id) : '',
                'lat' => null,
                'lng' => null
            )
        ));
    }

    /**
     * AJAX: Guardar ubicación POI
     */
    public function save_poi_location()
    {
        check_ajax_referer('masterplan_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permisos insuficientes'));
        }

        $poi_id = isset($_POST['poi_id']) ? absint($_POST['poi_id']) : 0;
        $lat = isset($_POST['lat']) ? sanitize_text_field($_POST['lat']) : '';
        $lng = isset($_POST['lng']) ? sanitize_text_field($_POST['lng']) : '';

        if (!$poi_id) {
            wp_send_json_error(array('message' => 'Datos incompletos'));
        }

        update_post_meta($poi_id, '_poi_lat', $lat);
        update_post_meta($poi_id, '_poi_lng', $lng);

        wp_send_json_success(array('message' => 'Ubicación guardada'));
    }

    /**
     * AJAX: Eliminar POI
     */
    public function delete_poi()
    {
        check_ajax_referer('masterplan_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permisos insuficientes'));
        }

        $poi_id = isset($_POST['poi_id']) ? absint($_POST['poi_id']) : 0;

        if (!$poi_id) {
            wp_send_json_error(array('message' => 'ID inválido'));
        }

        $deleted = wp_delete_post($poi_id, true);

        if ($deleted) {
            wp_send_json_success(array('message' => 'POI eliminado'));
        }
        else {
            wp_send_json_error(array('message' => 'Error al eliminar'));
        }
    }

    /**
     * AJAX: Crear Ruta
     */
    public function create_route()
    {
        check_ajax_referer('masterplan_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(array('message' => 'Permisos insuficientes'));

        $project_id = isset($_POST['project_id']) ? absint($_POST['project_id']) : 0;
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $color = isset($_POST['color']) ? sanitize_text_field($_POST['color']) : '#ffffff';
        $width = isset($_POST['width']) ? absint($_POST['width']) : 4;

        if (!$project_id || !$title) wp_send_json_error(array('message' => 'Datos incompletos'));

        $route_id = wp_insert_post(array(
            'post_type' => 'masterplan_route',
            'post_title' => $title,
            'post_status' => 'publish'
        ));

        if (is_wp_error($route_id)) wp_send_json_error(array('message' => 'Error al crear'));

        update_post_meta($route_id, '_project_id', $project_id);
        update_post_meta($route_id, '_route_color', $color);
        update_post_meta($route_id, '_route_width', $width);

        wp_send_json_success(array('message' => 'Ruta creada'));
    }

    /**
     * AJAX: Guardar coordenadas Ruta
     */
    public function save_route_path()
    {
        check_ajax_referer('masterplan_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(array('message' => 'Permisos insuficientes'));

        $route_id = isset($_POST['route_id']) ? absint($_POST['route_id']) : 0;
        $coordinates = isset($_POST['coordinates']) ? $_POST['coordinates'] : ''; // JSON string

        if (!$route_id || !$coordinates) wp_send_json_error(array('message' => 'Datos incompletos'));

        update_post_meta($route_id, '_route_coordinates', stripslashes($coordinates));
        wp_send_json_success(array('message' => 'Ruta guardada'));
    }

    /**
     * AJAX: Eliminar Ruta
     */
    public function delete_route()
    {
        check_ajax_referer('masterplan_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(array('message' => 'Permisos insuficientes'));
        $route_id = isset($_POST['route_id']) ? absint($_POST['route_id']) : 0;
        if(wp_delete_post($route_id, true)) wp_send_json_success();
        else wp_send_json_error();
    }

    /**
     * AJAX: Guardar/Actualizar Project Overlay
     */
    public function save_project_overlay()
    {
        check_ajax_referer('masterplan_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error();

        $project_id = isset($_POST['project_id']) ? absint($_POST['project_id']) : 0;
        $overlay_data = isset($_POST['overlay_data']) ? $_POST['overlay_data'] : ''; // JSON object string

        if (!$project_id || !$overlay_data) wp_send_json_error(array('message' => 'Datos incompletos'));

        $new_overlay = json_decode(stripslashes($overlay_data), true);
        if (!$new_overlay || !isset($new_overlay['id'])) wp_send_json_error(array('message' => 'JSON inválido'));

        // Obtener array actual
        $overlays = get_post_meta($project_id, '_project_overlays', true);
        if (!is_array($overlays)) $overlays = array();

        // Buscar si existe para actualizar, sino agregar
        $found = false;
        foreach ($overlays as $k => $v) {
            if ($v['id'] == $new_overlay['id']) {
                $overlays[$k] = $new_overlay;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $overlays[] = $new_overlay;
        }

        update_post_meta($project_id, '_project_overlays', $overlays);
        wp_send_json_success(array('message' => 'Plano guardado'));
    }

    /**
     * AJAX: Eliminar Project Overlay
     */
    public function delete_project_overlay()
    {
        check_ajax_referer('masterplan_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error();

        $project_id = isset($_POST['project_id']) ? absint($_POST['project_id']) : 0;
        $overlay_id = isset($_POST['overlay_id']) ? sanitize_text_field($_POST['overlay_id']) : '';

        if (!$project_id || !$overlay_id) wp_send_json_error();

        $overlays = get_post_meta($project_id, '_project_overlays', true);
        if (!is_array($overlays)) wp_send_json_error();

        $new_overlays = array();
        foreach ($overlays as $v) {
            if ($v['id'] != $overlay_id) {
                $new_overlays[] = $v;
            }
        }

        update_post_meta($project_id, '_project_overlays', $new_overlays);
        wp_send_json_success(array('message' => 'Plano eliminado'));
    }
}
