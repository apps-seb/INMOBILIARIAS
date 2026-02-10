<?php
/**
 * Clase principal del plugin MasterPlan 3D Pro
 * Coordina todos los componentes del plugin
 *
 * @package MasterPlan_3D_Pro
 */

class Masterplan_Core
{

    /**
     * Cargador de hooks
     */
    protected $loader;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_api_hooks();
    }

    /**
     * Cargar las dependencias del plugin
     */
    private function load_dependencies()
    {
        // Loader para hooks
        require_once MASTERPLAN_PLUGIN_DIR . 'includes/class-masterplan-loader.php';

        // Custom Post Types
        require_once MASTERPLAN_PLUGIN_DIR . 'includes/class-masterplan-cpt.php';
        require_once MASTERPLAN_PLUGIN_DIR . 'includes/class-masterplan-project-cpt.php';
        require_once MASTERPLAN_PLUGIN_DIR . 'includes/class-masterplan-poi-cpt.php';

        // Email Handler
        require_once MASTERPLAN_PLUGIN_DIR . 'includes/class-masterplan-email.php';

        // REST API
        require_once MASTERPLAN_PLUGIN_DIR . 'includes/class-masterplan-api.php';

        // Funcionalidad Admin
        require_once MASTERPLAN_PLUGIN_DIR . 'admin/class-masterplan-admin.php';
        require_once MASTERPLAN_PLUGIN_DIR . 'admin/class-masterplan-settings.php';
        require_once MASTERPLAN_PLUGIN_DIR . 'admin/class-masterplan-lot-editor.php';
        require_once MASTERPLAN_PLUGIN_DIR . 'admin/class-masterplan-project-editor.php';

        // Funcionalidad Frontend
        require_once MASTERPLAN_PLUGIN_DIR . 'public/class-masterplan-public.php';

        $this->loader = new Masterplan_Loader();
    }

    /**
     * Registrar hooks del área administrativa
     */
    private function define_admin_hooks()
    {
        $admin = new Masterplan_Admin();
        $settings = new Masterplan_Settings();
        $lot_editor = new Masterplan_Lot_Editor();
        $project_editor = new Masterplan_Project_Editor();
        $cpt = new Masterplan_CPT();
        $project_cpt = new Masterplan_Project_CPT();
        $poi_cpt = new Masterplan_POI_CPT();

        // Admin menu y scripts
        $this->loader->add_action('admin_menu', $admin, 'add_admin_menu');
        $this->loader->add_action('admin_enqueue_scripts', $admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $admin, 'enqueue_scripts');

        // Settings
        $this->loader->add_action('admin_init', $settings, 'register_settings');

        // Custom Post Type - Lotes
        $this->loader->add_action('init', $cpt, 'register_post_type');
        $this->loader->add_action('add_meta_boxes', $cpt, 'add_meta_boxes');
        $this->loader->add_action('save_post', $cpt, 'save_meta_boxes');
        $this->loader->add_filter('manage_lote_posts_columns', $cpt, 'add_custom_columns');
        $this->loader->add_action('manage_lote_posts_custom_column', $cpt, 'custom_column_content', 10, 2);

        // Custom Post Type - Proyectos
        $this->loader->add_action('init', $project_cpt, 'register_post_type');
        $this->loader->add_action('add_meta_boxes', $project_cpt, 'add_meta_boxes');
        $this->loader->add_action('save_post', $project_cpt, 'save_meta_boxes');

        // Custom Post Type - POIs
        $this->loader->add_action('init', $poi_cpt, 'register_post_type');
        $this->loader->add_action('add_meta_boxes', $poi_cpt, 'add_meta_boxes');
        $this->loader->add_action('save_post', $poi_cpt, 'save_meta_boxes');

        // AJAX handlers para el editor de lotes (legacy)
        $this->loader->add_action('wp_ajax_masterplan_save_polygon', $lot_editor, 'save_polygon');
        $this->loader->add_action('wp_ajax_masterplan_get_lot_data', $lot_editor, 'get_lot_data');

        // AJAX handlers para el editor de proyectos
        $this->loader->add_action('wp_ajax_masterplan_create_lot', $project_editor, 'create_lot');
        $this->loader->add_action('wp_ajax_masterplan_save_lot_polygon', $project_editor, 'save_lot_polygon');
        $this->loader->add_action('wp_ajax_masterplan_search_location', $project_editor, 'search_location');
    }

    /**
     * Registrar hooks del frontend
     */
    private function define_public_hooks()
    {
        $public = new Masterplan_Public();

        // Scripts y estilos del frontend
        $this->loader->add_action('wp_enqueue_scripts', $public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $public, 'enqueue_scripts');

        // Shortcode
        $this->loader->add_action('init', $public, 'register_shortcodes');

        // AJAX handler para el formulario de contacto
        $this->loader->add_action('wp_ajax_masterplan_contact_form', $public, 'handle_contact_form');
        $this->loader->add_action('wp_ajax_nopriv_masterplan_contact_form', $public, 'handle_contact_form');
    }

    /**
     * Registrar hooks de REST API
     */
    private function define_api_hooks()
    {
        $api = new Masterplan_API();

        $this->loader->add_action('rest_api_init', $api, 'register_routes');
    }

    /**
     * Ejecutar el plugin
     */
    public function run()
    {
        $this->loader->run();
    }
}
