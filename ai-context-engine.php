<?php
/**
 * Plugin Name:       AI Context Engine
 * Plugin URI:        https://wordpress.org/plugins/ai-context-engine/
 * Description:       Exports WordPress and plugin settings in an AI-friendly format for ChatGPT, Claude, and Gemini.
 * Version:           1.0.0
 * Author:            Abhinay Kalkhanday
 * Author URI:        https://wordpress.org/plugins/ai-context-engine/
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-context-engine
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'WP_AI_CONTEXT_VERSION', '1.0.0' );
define( 'WP_AI_CONTEXT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Clean up options and generated files on uninstall.
define( 'WP_AI_CONTEXT_SLUG', 'ai-context-engine' );
define( 'WP_AI_CONTEXT_SLUG_PREVIOUS', 'wp-ai-context' );

/**
 * Load the plugin text domain for translations.
 */
function wp_ai_context_load_textdomain() {
	load_plugin_textdomain( 'wp-ai-context', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'wp_ai_context_load_textdomain' );

// Failsafe: wrap core initialization to prevent White Screen of Death
try {
	// Core includes
	require_once WP_AI_CONTEXT_PLUGIN_DIR . 'includes/class-wp-ai-context.php';

	/**
	 * Begins execution of the plugin.
	 */
	function ai_context_engine_run() {
		$plugin = new WP_AI_Context();
		$plugin->run();
	}
	ai_context_engine_run();
} catch ( Throwable $e ) {
	// Log the error but don't break the entire site
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( 'WP AI Context Plugin Error: ' . $e->getMessage() );
	}
}
