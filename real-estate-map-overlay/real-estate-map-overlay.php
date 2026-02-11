<?php
/**
 * Plugin Name: Real Estate Map Overlay
 * Description: Interactive Map System with Perspective Warping for Real Estate lots.
 * Version: 1.0.0
 * Author: Jules
 * Text Domain: real-estate-map-overlay
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'REMO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'REMO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include required classes
require_once REMO_PLUGIN_DIR . 'admin/class-map-overlay-admin.php';
require_once REMO_PLUGIN_DIR . 'public/class-map-overlay-public.php';

// Initialize the plugin
function remo_init_plugin() {
    // Register CPT
    register_post_type( 'map_project', array(
        'labels' => array(
            'name' => __( 'Map Projects', 'real-estate-map-overlay' ),
            'singular_name' => __( 'Map Project', 'real-estate-map-overlay' ),
            'add_new' => __( 'Add New Project', 'real-estate-map-overlay' ),
            'add_new_item' => __( 'Add New Map Project', 'real-estate-map-overlay' ),
            'edit_item' => __( 'Edit Map Project', 'real-estate-map-overlay' ),
        ),
        'public' => true,
        'has_archive' => false,
        'supports' => array( 'title' ),
        'menu_icon' => 'dashicons-location-alt',
        'show_in_rest' => true,
    ));

    // Initialize Admin
    if ( is_admin() ) {
        new RealEstateMapOverlay_Admin();
    }

    // Initialize Public
    new RealEstateMapOverlay_Public();
}
add_action( 'init', 'remo_init_plugin' );
