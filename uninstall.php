<?php
/**
 * Desinstalación del plugin
 * Se ejecuta cuando el plugin es eliminado
 *
 * @package MasterPlan_3D_Pro
 */

// Si uninstall no es llamado desde WordPress, salir
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Eliminar tabla de polígonos
$table_name = $wpdb->prefix . 'masterplan_polygons';
$wpdb->query("DROP TABLE IF EXISTS $table_name");

// Eliminar opciones del plugin
$options = array(
    'masterplan_api_key',
    'masterplan_map_center_lat',
    'masterplan_map_center_lng',
    'masterplan_map_zoom',
    'masterplan_whatsapp_number',
    'masterplan_email_from',
    'masterplan_email_from_name',
);

foreach ($options as $option) {
    delete_option($option);
}

// Eliminar posts del tipo 'lote'
$lotes = get_posts(array(
    'post_type' => 'lote',
    'numberposts' => -1,
    'post_status' => 'any',
));

foreach ($lotes as $lote) {
    wp_delete_post($lote->ID, true);
}

// Limpiar rewrite rules
flush_rewrite_rules();
