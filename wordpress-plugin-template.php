<?php
/**
 * Plugin Name: WordPress Plugin Template
 * Version: 1.0.0
 * Plugin URI: http://www.hughlashbrooke.com/
 * Description: This is your starter template for your next WordPress plugin.
 * Author: Hugh Lashbrooke
 * Author URI: http://www.hughlashbrooke.com/
 * Requires at least: 4.0
 * Tested up to: 4.0
 *
 * Text Domain: wordpress-plugin-template
 * Domain Path: /lang/
 *
 * @package WordPress
 * @author Hugh Lashbrooke
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load plugin class files.
require_once 'includes/class-wordpress-plugin-template.php';
require_once 'includes/class-wordpress-plugin-template-settings.php';

// Load plugin libraries.
require_once 'includes/lib/class-wordpress-plugin-template-admin-api.php';
require_once 'includes/lib/class-wordpress-plugin-template-post-type.php';
require_once 'includes/lib/class-wordpress-plugin-template-taxonomy.php';
// Alumnus CPTs (Batch and Course).
require_once 'includes/class-alumnus-directory-cpt.php';

// Shortcodes.
require_once 'community-feed-shortcode.php';
require_once 'header-shortcode.php';
require_once 'directory-shortcode.php';
// Event information template & shortcode.
require_once 'event-information-template.php';
// Event list view shortcode.
require_once 'event-list-shortcode.php';

/**
 * Returns the main instance of WordPress_Plugin_Template to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return object WordPress_Plugin_Template
 */
function wordpress_plugin_template() {
	$instance = WordPress_Plugin_Template::instance( __FILE__, '1.0.0' );

	if ( is_null( $instance->settings ) ) {
		$instance->settings = WordPress_Plugin_Template_Settings::instance( $instance );
	}

	return $instance;
}

wordpress_plugin_template();

// Bootstrap the Alumnus CPTs.
if ( class_exists( 'Alumnus_Directory_CPT' ) ) {
	// Initialize once plugins are loaded to ensure core APIs are ready.
	add_action( 'plugins_loaded', function () {
		static $alumnus_cpt = null;
		if ( null === $alumnus_cpt ) {
			$alumnus_cpt = new Alumnus_Directory_CPT();
		}
	} );
}
