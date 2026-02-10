<?php
/**
 * Plugin Name: MasterPlan 3D Pro
 * Plugin URI: https://masterplan3d.com
 * Description: Sistema avanzado de visualización 3D de terrenos inmobiliarios con gestor de lotes interactivo y generación de leads
 * Version: 1.0.0
 * Author: MasterPlan Team
 * Author URI: https://masterplan3d.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: masterplan-3d
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 8.0
 */

// Si este archivo es llamado directamente, abortar
if (!defined('WPINC')) {
    die;
}

// Versión del plugin
define('MASTERPLAN_VERSION', '1.0.0');
define('MASTERPLAN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MASTERPLAN_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MASTERPLAN_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Código que se ejecuta durante la activación del plugin
 */
function activate_masterplan_3d()
{
    require_once MASTERPLAN_PLUGIN_DIR . 'includes/class-masterplan-activator.php';
    Masterplan_Activator::activate();
}

/**
 * Código que se ejecuta durante la desactivación del plugin
 */
function deactivate_masterplan_3d()
{
    require_once MASTERPLAN_PLUGIN_DIR . 'includes/class-masterplan-deactivator.php';
    Masterplan_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_masterplan_3d');
register_deactivation_hook(__FILE__, 'deactivate_masterplan_3d');

/**
 * Clase principal del plugin
 */
require MASTERPLAN_PLUGIN_DIR . 'includes/class-masterplan-core.php';

/**
 * Iniciar la ejecución del plugin
 */
function run_masterplan_3d()
{
    $plugin = new Masterplan_Core();
    $plugin->run();
}

run_masterplan_3d();
