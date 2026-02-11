<?php
/**
 * Clase Admin - Gestión del panel de administración
 *
 * @package MasterPlan_3D_Pro
 */

class Masterplan_Admin
{

    /**
     * Agregar menú de administración
     */
    public function add_admin_menu()
    {
        // Menú principal
        add_menu_page(
            'MasterPlan 3D',
            'MasterPlan 3D',
            'manage_options',
            'masterplan-settings',
            array($this, 'settings_page'),
            'dashicons-admin-site-alt3',
            30
        );

        // Submenú de configuración
        add_submenu_page(
            'masterplan-settings',
            'Configuración',
            '⚙️ Configuración',
            'manage_options',
            'masterplan-settings',
            array($this, 'settings_page')
        );

        // Submenú del editor de proyectos (NUEVO)
        add_submenu_page(
            'masterplan-settings',
            'Editor de Proyectos',
            '🎨 Editor de Proyectos',
            'manage_options',
            'masterplan-project-editor',
            array($this, 'project_editor_page')
        );

        // Submenú del editor de lotes (legacy)
        add_submenu_page(
            'masterplan-settings',
            'Editor de Mapas',
            '🗺️ Editor de Mapas',
            'manage_options',
            'masterplan-lot-editor',
            array($this, 'lot_editor_page')
        );
    }

    /**
     * Renderizar página de configuración
     */
    public function settings_page()
    {
        require_once MASTERPLAN_PLUGIN_DIR . 'admin/views/settings-page.php';
    }

    /**
     * Renderizar página del editor de proyectos (NUEVO)
     */
    public function project_editor_page()
    {
        $project_editor = new Masterplan_Project_Editor();
        $project_editor->render_editor_page();
    }

    /**
     * Renderizar página del editor de lotes (legacy)
     */
    public function lot_editor_page()
    {
        require_once MASTERPLAN_PLUGIN_DIR . 'admin/views/lot-editor-page.php';
    }

    /**
     * Encolar estilos del admin
     */
    public function enqueue_styles($hook)
    {
        // Solo cargar en páginas del plugin o CPT
        $allowed_screens = array(
            'masterplan',
            'post.php',
            'post-new.php'
        );

        $is_plugin_page = false;
        foreach ($allowed_screens as $screen) {
            if (strpos($hook, $screen) !== false) {
                $is_plugin_page = true;
                break;
            }
        }

        // También cargar en edición de proyectos/lotes
        $screen = get_current_screen();
        if ($screen && in_array($screen->post_type, array('proyecto', 'lote'))) {
            $is_plugin_page = true;
        }

        if (!$is_plugin_page) {
            return;
        }

        wp_enqueue_style(
            'masterplan-admin',
            MASTERPLAN_PLUGIN_URL . 'admin/css/admin-style.css',
            array(),
            MASTERPLAN_VERSION
        );

        // MapLibre GL CSS
        wp_enqueue_style(
            'maplibre-gl',
            'https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.css',
            array(),
            '4.7.1'
        );

        // Media uploader
        wp_enqueue_media();
    }

    /**
     * Encolar scripts del admin
     */
    public function enqueue_scripts($hook)
    {
        // Editor de proyectos
        if (strpos($hook, 'masterplan-project-editor') !== false) {
            $this->enqueue_project_editor_scripts();
            return;
        }

        // Editor de lotes legacy
        if ($hook === 'masterplan-3d_page_masterplan-lot-editor') {
            $this->enqueue_lot_editor_scripts();
            return;
        }

        // Edición de CPT Proyecto
        $screen = get_current_screen();
        if ($screen && $screen->post_type === 'proyecto' && in_array($screen->base, array('post', 'post-new'))) {
            // Scripts básicos para el CPT
            wp_enqueue_media();
        }
    }

    /**
     * Encolar scripts del editor de proyectos
     */
    private function enqueue_project_editor_scripts()
    {
        // MapLibre GL JS
        wp_enqueue_script(
            'maplibre-gl',
            'https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.js',
            array(),
            '4.7.1',
            true
        );

        // Script del editor de proyectos
        wp_enqueue_script(
            'masterplan-project-editor',
            MASTERPLAN_PLUGIN_URL . 'admin/js/project-editor.js',
            array('jquery', 'maplibre-gl'),
            MASTERPLAN_VERSION,
            true
        );
    }

    /**
     * Encolar scripts del editor de lotes (legacy)
     */
    private function enqueue_lot_editor_scripts()
    {
        // MapLibre GL JS
        wp_enqueue_script(
            'maplibre-gl',
            'https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.js',
            array(),
            '4.7.1',
            true
        );

        // Script del builder
        wp_enqueue_script(
            'masterplan-admin-builder',
            MASTERPLAN_PLUGIN_URL . 'admin/js/admin-builder.js',
            array('jquery', 'maplibre-gl'),
            MASTERPLAN_VERSION,
            true
        );

        // Pasar datos a JavaScript
        wp_localize_script(
            'masterplan-admin-builder',
            'masterplanAdmin',
            array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('masterplan_admin_nonce'),
            'apiKey' => get_option('masterplan_api_key', ''),
            'centerLat' => get_option('masterplan_map_center_lat', '2.4568'),
            'centerLng' => get_option('masterplan_map_center_lng', '-76.6310'),
            'zoom' => get_option('masterplan_map_zoom', '14'),
            'lotId' => isset($_GET['lot_id']) ? absint($_GET['lot_id']) : 0,
        )
        );
    }
}
