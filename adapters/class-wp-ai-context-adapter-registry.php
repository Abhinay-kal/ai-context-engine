<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Adapter Registry
 * Resolves the correct adapter for a given plugin slug.
 */
class WP_AI_Context_Adapter_Registry {

	/**
	 * Get the appropriate adapter for a plugin.
	 *
	 * @param string $plugin_slug
	 * @return WP_AI_Context_Adapter_Interface
	 */
	public function get_adapter( $plugin_slug ) {
		// Allow external plugins to register their own adapters
		$adapters = apply_filters( 'wp_ai_context_adapters', array() );

		if ( isset( $adapters[ $plugin_slug ] ) && class_exists( $adapters[ $plugin_slug ] ) ) {
			$custom_adapter = new $adapters[ $plugin_slug ]();
			if ( $custom_adapter instanceof WP_AI_Context_Adapter_Interface ) {
				return $custom_adapter;
			}
		}

		// Fallback to the generic adapter
		return new WP_AI_Context_Generic_Adapter();
	}
}
