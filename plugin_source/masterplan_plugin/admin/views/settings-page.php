<?php
/**
 * Settings Page View
 */

if (!defined('ABSPATH')) {
    exit;
}

// Verificar permisos
if (!current_user_can('manage_options')) {
    wp_die('No tienes permisos para acceder a esta página.');
}

// Guardar configuración
if (isset($_POST['submit']) && check_admin_referer('masterplan_settings_save', 'masterplan_settings_nonce')) {
    echo '<div class="notice notice-success is-dismissible"><p>Configuración guardada.</p></div>';
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="masterplan-settings-header">
        <p>Bienvenido al panel de configuración de <strong>MasterPlan 3D Pro</strong>. Configura aquí los parámetros del mapa y los canales de contacto.</p>
    </div>

    <form method="post" action="options.php">
        <?php
settings_fields('masterplan_settings_group');
do_settings_sections('masterplan-settings');
submit_button('Guardar Cambios');
?>
    </form>

    <hr>

    <div class="masterplan-shortcode-info">
        <h2>Uso del Shortcode</h2>
        <p>Para mostrar el mapa 3D en cualquier página o entrada, utiliza el siguiente shortcode:</p>
        <code style="display: block; background: #f0f0f0; padding: 10px; margin: 10px 0; font-size: 14px;">[masterplan_map]</code>
        <p class="description">Puedes agregarlo en el editor de bloques o en modo clásico.</p>
    </div>
</div>

<style>
.masterplan-settings-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 8px;
    margin: 20px 0;
}

.masterplan-shortcode-info {
    background: #fff;
    border: 1px solid #ddd;
    padding: 20px;
    border-radius: 8px;
    margin-top: 20px;
}
</style>
