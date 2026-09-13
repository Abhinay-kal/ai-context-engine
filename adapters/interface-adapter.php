<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Adapter Interface
 * All plugin-specific adapters must implement this interface.
 */
interface WP_AI_Context_Adapter_Interface {

	/**
	 * Get the settings for the plugin.
	 *
	 * @param string $plugin_slug The slug of the plugin.
	 * @return array The extracted settings.
	 */
	public function get_settings( $plugin_slug );

	/**
	 * Updates a specific setting for the plugin (used for rollbacks).
	 *
	 * @param string $plugin_slug The slug of the plugin.
	 * @param string $key The setting key.
	 * @param mixed  $value The new value.
	 * @return bool True on success.
	 */
	public function update_setting( $plugin_slug, $key, $value );
}
