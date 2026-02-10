<?php
/**
 * Lot Editor Page View
 */

if (!defined('ABSPATH')) {
    exit;
}

// Verificar permisos
if (!current_user_can('manage_options')) {
    wp_die('No tienes permisos para acceder a esta página.');
}

// Obtener lot_id si está presente
$lot_id = isset($_GET['lot_id']) ? absint($_GET['lot_id']) : 0;
$lot_title = $lot_id ? get_the_title($lot_id) : 'Seleccionar un Lote';
?>

<div class="wrap masterplan-lot-editor-wrap">
    <h1>Editor de Mapas 3D</h1>

    <div class="masterplan-editor-header">
        <div class="editor-lot-selector">
            <label for="lot-selector">Lote:</label>
            <select id="lot-selector" class="masterplan-lot-select">
                <option value="">Seleccionar Lote</option>
                <?php
$lots = get_posts(array(
    'post_type' => 'lote',
    'posts_per_page' => -1,
    'post_status' => 'any',
    'orderby' => 'title',
    'order' => 'ASC',
));

foreach ($lots as $lot) {
    $selected = ($lot->ID === $lot_id) ? 'selected' : '';
    echo '<option value="' . esc_attr($lot->ID) . '" ' . $selected . '>' . esc_html(get_the_title($lot->ID)) . '</option>';
}
?>
            </select>

            <span class="editor-lot-info" id="current-lot-info">
                <?php if ($lot_id): ?>
                    <strong><?php echo esc_html($lot_title); ?></strong>
                <?php
endif; ?>
            </span>
        </div>

        <div class="editor-controls">
            <button id="start-drawing-btn" class="button button-primary" disabled>
                <span class="dashicons dashicons-edit"></span> Dibujar Polígono
            </button>
            <button id="clear-polygon-btn" class="button" disabled>
                <span class="dashicons dashicons-trash"></span> Borrar
            </button>
            <button id="save-polygon-btn" class="button button-primary" disabled>
                <span class="dashicons dashicons-saved"></span> Guardar
            </button>
        </div>
    </div>

    <div id="masterplan-map-container" class="masterplan-map-container"></div>

    <div class="masterplan-instructions">
        <h3>Instrucciones</h3>
        <ol>
            <li>Selecciona un lote del selector superior</li>
            <li>Haz clic en "Dibujar Polígono" para activar el modo de dibujo</li>
            <li>Haz clic en el mapa para agregar puntos del polígono</li>
            <li>El polígono se cerrará automáticamente al conectar el último punto con el primero</li>
            <li>Presiona "Guardar" para almacenar las coordenadas</li>
        </ol>
    </div>
</div>
