<?php
/**
 * Desactivación del plugin
 *
 * @package MasterPlan_3D_Pro
 */

class Masterplan_Deactivator
{

    /**
     * Desactivar el plugin
     */
    public static function deactivate()
    {
        // Flush rewrite rules
        flush_rewrite_rules();

    // No eliminamos datos para permitir reactivación sin pérdida de información
    }
}
