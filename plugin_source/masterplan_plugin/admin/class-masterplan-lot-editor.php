<?php
/**
 * Editor de Lotes - AJAX Handler
 *
 * @package MasterPlan_3D_Pro
 */

class Masterplan_Lot_Editor
{

    /**
     * Guardar polígono vía AJAX
     */
    public function save_polygon()
    {
        // Verificar nonce
        check_ajax_referer('masterplan_admin_nonce', 'nonce');

        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permisos insuficientes'), 403);
        }

        // Obtener datos
        $lot_id = isset($_POST['lot_id']) ? absint($_POST['lot_id']) : 0;
        $coordinates = isset($_POST['coordinates']) ? $_POST['coordinates'] : '';

        // Validar lot_id
        if (!$lot_id) {
            wp_send_json_error(array('message' => 'ID de lote inválido'));
        }

        // Validar que el lote existe
        $lot = get_post($lot_id);
        if (!$lot || $lot->post_type !== 'lote') {
            wp_send_json_error(array('message' => 'Lote no encontrado'));
        }

        // Validar coordenadas (debe ser JSON válido)
        $decoded = json_decode(stripslashes($coordinates));
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array('message' => 'Coordenadas inválidas'));
        }

        // Guardar en la base de datos
        global $wpdb;
        $table_name = $wpdb->prefix . 'masterplan_polygons';

        // Verificar si ya existe un polígono para este lote
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE lot_id = %d",
            $lot_id
        ));

        if ($existing) {
            // Actualizar
            $wpdb->update(
                $table_name,
                array('coordinates' => stripslashes($coordinates)),
                array('lot_id' => $lot_id),
                array('%s'),
                array('%d')
            );
        }
        else {
            // Insertar
            $wpdb->insert(
                $table_name,
                array(
                'lot_id' => $lot_id,
                'coordinates' => stripslashes($coordinates),
            ),
                array('%d', '%s')
            );
        }

        wp_send_json_success(array(
            'message' => 'Polígono guardado exitosamente',
            'lot_id' => $lot_id,
        ));
    }

    /**
     * Obtener datos del lote vía AJAX
     */
    public function get_lot_data()
    {
        // Verificar nonce
        check_ajax_referer('masterplan_admin_nonce', 'nonce');

        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permisos insuficientes'), 403);
        }

        // Obtener lot_id
        $lot_id = isset($_GET['lot_id']) ? absint($_GET['lot_id']) : 0;

        if (!$lot_id) {
            wp_send_json_error(array('message' => 'ID de lote inválido'));
        }

        // Obtener datos del lote
        $lot = get_post($lot_id);
        if (!$lot || $lot->post_type !== 'lote') {
            wp_send_json_error(array('message' => 'Lote no encontrado'));
        }

        // Obtener coordenadas del polígono
        global $wpdb;
        $table_name = $wpdb->prefix . 'masterplan_polygons';

        $polygon = $wpdb->get_row($wpdb->prepare(
            "SELECT coordinates FROM $table_name WHERE lot_id = %d ORDER BY id DESC LIMIT 1",
            $lot_id
        ));

        $response = array(
            'id' => $lot_id,
            'title' => get_the_title($lot_id),
            'lot_number' => get_post_meta($lot_id, '_lot_number', true),
            'status' => get_post_meta($lot_id, '_lot_status', true),
            'coordinates' => $polygon ? json_decode($polygon->coordinates, true) : null,
        );

        wp_send_json_success($response);
    }
}
