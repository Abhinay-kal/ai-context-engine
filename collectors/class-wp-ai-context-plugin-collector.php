<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Plugin Collector
 * Gathers data about active and installed plugins.
 */
class WP_AI_Context_Plugin_Collector {

	/**
	 * Gets a list of all active plugins with their versions.
	 *
	 * @return array
	 */
	public function get_active_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins    = get_plugins();
		$active_plugins = get_option( 'active_plugins', array() );
		$context        = array();

		foreach ( $active_plugins as $plugin_path ) {
			if ( isset( $all_plugins[ $plugin_path ] ) ) {
				// We want a clean slug, usually the folder name
				$slug = dirname( $plugin_path );
				if ( '.' === $slug ) {
					$slug = basename( $plugin_path, '.php' );
				}

				$context[ $slug ] = array(
					'name'    => $all_plugins[ $plugin_path ]['Name'],
					'version' => $all_plugins[ $plugin_path ]['Version'],
					'path'    => $plugin_path,
				);
			}
		}

		return $context;
	}
}
