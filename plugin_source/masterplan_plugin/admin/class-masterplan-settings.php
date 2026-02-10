<?php
/**
 * Settings Page Handler
 *
 * @package MasterPlan_3D_Pro
 */

class Masterplan_Settings
{

    /**
     * Registrar configuraciones
     */
    public function register_settings()
    {
        // Grupo de configuración
        register_setting('masterplan_settings_group', 'masterplan_api_key', array(
            'sanitize_callback' => 'sanitize_text_field',
        ));

        register_setting('masterplan_settings_group', 'masterplan_map_center_lat', array(
            'sanitize_callback' => 'sanitize_text_field',
        ));

        register_setting('masterplan_settings_group', 'masterplan_map_center_lng', array(
            'sanitize_callback' => 'sanitize_text_field',
        ));

        register_setting('masterplan_settings_group', 'masterplan_map_zoom', array(
            'sanitize_callback' => 'sanitize_text_field',
        ));

        register_setting('masterplan_settings_group', 'masterplan_whatsapp_number', array(
            'sanitize_callback' => 'sanitize_text_field',
        ));

        register_setting('masterplan_settings_group', 'masterplan_email_from', array(
            'sanitize_callback' => 'sanitize_email',
        ));

        register_setting('masterplan_settings_group', 'masterplan_email_from_name', array(
            'sanitize_callback' => 'sanitize_text_field',
        ));

        // Sección de Mapa
        add_settings_section(
            'masterplan_map_section',
            'Configuración del Mapa',
            array($this, 'map_section_callback'),
            'masterplan-settings'
        );

        add_settings_field(
            'masterplan_api_key',
            'API Key (Maptiler)',
            array($this, 'api_key_field_callback'),
            'masterplan-settings',
            'masterplan_map_section'
        );

        add_settings_field(
            'masterplan_map_center_lat',
            'Centro del Mapa - Latitud',
            array($this, 'center_lat_field_callback'),
            'masterplan-settings',
            'masterplan_map_section'
        );

        add_settings_field(
            'masterplan_map_center_lng',
            'Centro del Mapa - Longitud',
            array($this, 'center_lng_field_callback'),
            'masterplan-settings',
            'masterplan_map_section'
        );

        add_settings_field(
            'masterplan_map_zoom',
            'Zoom Inicial',
            array($this, 'zoom_field_callback'),
            'masterplan-settings',
            'masterplan_map_section'
        );

        // Sección de Lead Generation
        add_settings_section(
            'masterplan_leads_section',
            'Configuración de Leads',
            array($this, 'leads_section_callback'),
            'masterplan-settings'
        );

        add_settings_field(
            'masterplan_whatsapp_number',
            'Número de WhatsApp',
            array($this, 'whatsapp_field_callback'),
            'masterplan-settings',
            'masterplan_leads_section'
        );

        add_settings_field(
            'masterplan_email_from',
            'Email de Contacto',
            array($this, 'email_from_field_callback'),
            'masterplan-settings',
            'masterplan_leads_section'
        );

        add_settings_field(
            'masterplan_email_from_name',
            'Nombre del Remitente',
            array($this, 'email_from_name_field_callback'),
            'masterplan-settings',
            'masterplan_leads_section'
        );
    }

    // Callbacks de secciones
    public function map_section_callback()
    {
        echo '<p>Configura los parámetros del mapa 3D y la API de Maptiler.</p>';
    }

    public function leads_section_callback()
    {
        echo '<p>Configura los canales de contacto para la generación de leads.</p>';
    }

    // Callbacks de campos
    public function api_key_field_callback()
    {
        $value = get_option('masterplan_api_key', '');
        echo '<input type="text" name="masterplan_api_key" value="' . esc_attr($value) . '" class="regular-text" required>';
        echo '<p class="description">Obtén tu API key gratuita en <a href="https://www.maptiler.com/" target="_blank">Maptiler</a></p>';
    }

    public function center_lat_field_callback()
    {
        $value = get_option('masterplan_map_center_lat', '19.4326');
        echo '<input type="text" name="masterplan_map_center_lat" value="' . esc_attr($value) . '" class="regular-text" required>';
    }

    public function center_lng_field_callback()
    {
        $value = get_option('masterplan_map_center_lng', '-99.1332');
        echo '<input type="text" name="masterplan_map_center_lng" value="' . esc_attr($value) . '" class="regular-text" required>';
    }

    public function zoom_field_callback()
    {
        $value = get_option('masterplan_map_zoom', '14');
        echo '<input type="number" name="masterplan_map_zoom" value="' . esc_attr($value) . '" min="1" max="20" required>';
    }

    public function whatsapp_field_callback()
    {
        $value = get_option('masterplan_whatsapp_number', '');
        echo '<input type="text" name="masterplan_whatsapp_number" value="' . esc_attr($value) . '" class="regular-text" placeholder="+5215512345678">';
        echo '<p class="description">Formato internacional: +52 (código de país) + número completo</p>';
    }

    public function email_from_field_callback()
    {
        $value = get_option('masterplan_email_from', get_option('admin_email'));
        echo '<input type="email" name="masterplan_email_from" value="' . esc_attr($value) . '" class="regular-text" required>';
    }

    public function email_from_name_field_callback()
    {
        $value = get_option('masterplan_email_from_name', get_bloginfo('name'));
        echo '<input type="text" name="masterplan_email_from_name" value="' . esc_attr($value) . '" class="regular-text" required>';
    }
}
