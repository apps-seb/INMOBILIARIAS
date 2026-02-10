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
        $center_lat = get_post_meta($project_id, '_project_center_lat', true) ?: get_option('masterplan_map_center_lat', '4.5709');
        $center_lng = get_post_meta($project_id, '_project_center_lng', true) ?: get_option('masterplan_map_center_lng', '-74.2973');
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

            $lots_data[] = array(
                'id' => $lot->ID,
                'title' => $lot->post_title,
                'lot_number' => get_post_meta($lot->ID, '_lot_number', true),
                'status' => get_post_meta($lot->ID, '_lot_status', true),
                'price' => get_post_meta($lot->ID, '_lot_price', true),
                'area' => get_post_meta($lot->ID, '_lot_area', true),
                'coordinates' => $polygon ? json_decode($polygon->coordinates, true) : null,
                'thumbnail' => get_the_post_thumbnail_url($lot->ID, 'thumbnail')
            );
        }

        // Obtener POIs del proyecto
        $pois = get_posts(array(
            'post_type' => 'masterplan_poi',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_poi_project_id',
                    'value' => $project_id,
                    'compare' => '='
                )
            ),
            'post_status' => array('publish')
        ));

        $pois_data = array();
        foreach ($pois as $poi) {
            $pois_data[] = array(
                'id' => $poi->ID,
                'title' => $poi->post_title,
                'excerpt' => $poi->post_excerpt,
                'lat' => get_post_meta($poi->ID, '_poi_lat', true),
                'lng' => get_post_meta($poi->ID, '_poi_lng', true),
                'alt' => get_post_meta($poi->ID, '_poi_alt', true),
                'viz_type' => get_post_meta($poi->ID, '_poi_viz_type', true),
                'color' => get_post_meta($poi->ID, '_poi_color', true),
                'logo_url' => get_the_post_thumbnail_url($poi->ID, 'thumbnail')
            );
        }

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
                        <div class="tab-item" data-tab="pois">🚩 Puntos de Interés</div>
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

                    <!-- Pestaña POIs -->
                    <div class="tab-content" id="tab-content-pois" style="display:none;">
                        <div class="panel-actions">
                            <button type="button" id="btn-new-poi" class="button button-primary" style="width:100%">
                                🚩 Nuevo Punto de Interés
                            </button>
                        </div>
                        <div class="lots-list" id="pois-list">
                            <!-- Los POIs se renderizarán vía JS o PHP -->
                            <?php if (empty($pois_data)): ?>
                                <div class="no-items">
                                    <p>No hay POIs creados</p>
                                    <small>Haz clic en "Nuevo POI" para comenzar</small>
                                </div>
                            <?php else: ?>
                                <?php foreach ($pois_data as $poi): ?>
                                    <div class="poi-item" data-poi-id="<?php echo $poi['id']; ?>" data-poi-lat="<?php echo $poi['lat']; ?>" data-poi-lng="<?php echo $poi['lng']; ?>">
                                        <div class="poi-icon">
                                            <?php if($poi['logo_url']): ?>
                                                <img src="<?php echo esc_url($poi['logo_url']); ?>" style="width:24px; height:24px; border-radius:50%; object-fit:cover;">
                                            <?php else: ?>
                                                <span class="dashicons dashicons-location" style="color: <?php echo $poi['color']; ?>"></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="poi-info">
                                            <strong><?php echo esc_html($poi['title']); ?></strong>
                                            <small><?php echo ($poi['lat'] && $poi['lng']) ? '📍 Ubicado' : '⚠️ Sin ubicación'; ?></small>
                                        </div>
                                        <div class="poi-actions">
                                            <button type="button" class="btn-locate-poi" title="Ubicar en mapa">📍</button>
                                            <button type="button" class="btn-edit-poi" title="Editar POI">✏️</button>
                                            <button type="button" class="btn-delete-poi" title="Eliminar POI">🗑️</button>
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
                            <button type="button" id="btn-draw-polygon" class="button" disabled>
                                🎨 Dibujar Polígono
                            </button>
                            <button type="button" id="btn-clear-polygon" class="button" disabled>
                                🗑️ Borrar
                            </button>
                            <button type="button" id="btn-save-polygon" class="button button-primary" disabled>
                                💾 Guardar
                            </button>
                        </div>
                        <div class="control-group" id="controls-pois" style="display:none;">
                            <span class="dashicons dashicons-info"></span>
                            <span id="poi-mode-status">Haz clic en el mapa para ubicar el POI</span>
                        </div>
                        <div class="control-info">
                            <span id="editor-status">Selecciona un lote para dibujar</span>
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

            <!-- Modal para POI -->
            <div id="poi-modal" class="modal-overlay" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 id="poi-modal-title">🚩 Crear/Editar POI</h2>
                        <button type="button" class="modal-close">&times;</button>
                    </div>
                    <form id="poi-form">
                        <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
                        <input type="hidden" name="poi_id" id="poi_id">

                        <div class="form-row">
                            <label for="poi_title">Título del Punto de Interés *</label>
                            <input type="text" id="poi_title" name="title" required placeholder="Ej: Entrada Principal, Parque...">
                        </div>

                        <div class="form-row">
                            <label for="poi_description">Descripción</label>
                            <textarea id="poi_description" name="description" rows="3"></textarea>
                        </div>

                        <div class="form-row">
                            <label for="poi_logo">Logo / Icono</label>
                            <div style="display:flex; gap:10px; align-items:center;">
                                <input type="hidden" id="poi_logo_id" name="logo_id">
                                <button type="button" class="button" id="btn-upload-logo">Subir Imagen</button>
                                <div id="poi-logo-preview" style="width:40px; height:40px; border:1px solid #ddd; border-radius:4px; display:none; background-size:cover; background-position:center;"></div>
                                <button type="button" class="button-link delete-logo" style="display:none; color:#a00;">Quitar</button>
                            </div>
                        </div>

                        <div class="form-row" style="display:flex; gap:15px;">
                            <div style="flex:1;">
                                <label for="poi_lat">Latitud</label>
                                <input type="text" id="poi_lat" name="lat" readonly style="background:#f0f0f1;">
                            </div>
                            <div style="flex:1;">
                                <label for="poi_lng">Longitud</label>
                                <input type="text" id="poi_lng" name="lng" readonly style="background:#f0f0f1;">
                            </div>
                        </div>
                        <p class="description" style="margin-top:-10px; margin-bottom:15px; font-size:11px; color:#666;">
                            Haz clic en "Ubicar en mapa" después de guardar para ajustar la posición, o arrastra el marcador si ya existe.
                        </p>

                        <div class="form-row">
                            <label for="poi_viz_type">Tipo de Visualización</label>
                            <select id="poi_viz_type" name="viz_type">
                                <option value="icon">Icono (Logo)</option>
                                <option value="billboard">Cartel Flotante</option>
                                <option value="sphere">Esfera 3D</option>
                            </select>
                        </div>

                        <div class="form-row">
                            <label for="poi_color">Color de Resalte</label>
                            <input type="color" id="poi_color" name="color" value="#3b82f6" style="width:100%; height:30px;">
                        </div>

                        <div class="form-actions">
                            <button type="button" class="button modal-close">Cancelar</button>
                            <button type="submit" class="button button-primary">💾 Guardar y Publicar POI</button>
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
     * AJAX: Guardar POI
     */
    public function save_poi()
    {
        check_ajax_referer('masterplan_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permisos insuficientes'));
        }

        $project_id = isset($_POST['project_id']) ? absint($_POST['project_id']) : 0;
        $poi_id = isset($_POST['poi_id']) ? absint($_POST['poi_id']) : 0;
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
        $lat = isset($_POST['lat']) ? sanitize_text_field($_POST['lat']) : '';
        $lng = isset($_POST['lng']) ? sanitize_text_field($_POST['lng']) : '';
        $logo_id = isset($_POST['logo_id']) ? absint($_POST['logo_id']) : 0;
        $viz_type = isset($_POST['viz_type']) ? sanitize_text_field($_POST['viz_type']) : 'icon';
        $color = isset($_POST['color']) ? sanitize_hex_color($_POST['color']) : '#3b82f6';

        if (!$title || !$project_id) {
            wp_send_json_error(array('message' => 'Datos incompletos'));
        }

        // Crear o actualizar post - Asegurando que siempre esté publicado y visible
        $post_data = array(
            'post_type' => 'masterplan_poi',
            'post_title' => $title,
            'post_excerpt' => $description,
            'post_status' => 'publish' // Forzar publicación
        );

        if ($poi_id) {
            $post_data['ID'] = $poi_id;
            wp_update_post($post_data);
        } else {
            $poi_id = wp_insert_post($post_data);
            if (is_wp_error($poi_id)) {
                wp_send_json_error(array('message' => 'Error al guardar POI'));
            }
        }

        // Guardar meta
        update_post_meta($poi_id, '_poi_project_id', $project_id);
        update_post_meta($poi_id, '_poi_lat', $lat);
        update_post_meta($poi_id, '_poi_lng', $lng);
        update_post_meta($poi_id, '_poi_viz_type', $viz_type);
        update_post_meta($poi_id, '_poi_color', $color);
        update_post_meta($poi_id, '_thumbnail_id', $logo_id);

        wp_send_json_success(array(
            'message' => 'POI guardado exitosamente',
            'poi' => array(
                'id' => $poi_id,
                'title' => $title,
                'lat' => $lat,
                'lng' => $lng,
                'viz_type' => $viz_type,
                'color' => $color,
                'logo_url' => $logo_id ? wp_get_attachment_url($logo_id) : ''
            )
        ));
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

        wp_delete_post($poi_id, true);

        wp_send_json_success(array('message' => 'POI eliminado'));
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
}
