<?php
/**
 * Activación del plugin
 * Código que se ejecuta durante la activación
 *
 * @package MasterPlan_3D_Pro
 */

class Masterplan_Activator
{

    /**
     * Activar el plugin
     */
    public static function activate()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Tabla para almacenar coordenadas de polígonos de lotes
        $table_polygons = $wpdb->prefix . 'masterplan_polygons';
        $sql_polygons = "CREATE TABLE IF NOT EXISTS $table_polygons (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            lot_id bigint(20) NOT NULL,
            project_id bigint(20) DEFAULT NULL,
            coordinates longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY lot_id (lot_id),
            KEY project_id (project_id)
        ) $charset_collate;";
        dbDelta($sql_polygons);

        // Tabla para almacenar datos de proyectos
        $table_projects = $wpdb->prefix . 'masterplan_projects';
        $sql_projects = "CREATE TABLE IF NOT EXISTS $table_projects (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            project_id bigint(20) NOT NULL,
            sector_coordinates longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY project_id (project_id)
        ) $charset_collate;";
        dbDelta($sql_projects);

        // Configuración por defecto - Colombia
        $default_options = array(
            'masterplan_api_key' => 'ZKYKywWdAzdCttb1jxOi',
            'masterplan_map_center_lat' => '4.5709', // Bogotá, Colombia
            'masterplan_map_center_lng' => '-74.2973',
            'masterplan_map_zoom' => '14',
            'masterplan_whatsapp_number' => '+57', // Código Colombia
            'masterplan_email_from' => get_option('admin_email'),
            'masterplan_email_from_name' => get_option('blogname'),
            'masterplan_currency' => 'COP', // Pesos colombianos
            'masterplan_currency_symbol' => '$',
        );

        foreach ($default_options as $option => $value) {
            if (!get_option($option)) {
                add_option($option, $value);
            }
        }

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Actualizar tablas si es necesario (para actualizaciones del plugin)
     */
    public static function update_tables()
    {
        global $wpdb;

        // Agregar columna project_id si no existe
        $table_polygons = $wpdb->prefix . 'masterplan_polygons';
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_polygons LIKE 'project_id'");

        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table_polygons ADD COLUMN project_id bigint(20) DEFAULT NULL AFTER lot_id");
            $wpdb->query("ALTER TABLE $table_polygons ADD INDEX project_id (project_id)");
        }
    }
}
