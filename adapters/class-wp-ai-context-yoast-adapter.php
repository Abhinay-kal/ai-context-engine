<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Yoast SEO Specific Adapter
 */
class WP_AI_Context_Yoast_Adapter implements WP_AI_Context_Adapter_Interface {

	public function get_settings( $plugin_slug ) {
		// 1. Get generic options first (fallback logic)
		$generic  = new WP_AI_Context_Generic_Adapter();
		$settings = $generic->get_settings( $plugin_slug );

		global $wpdb;

		// 2. Fetch all Yoast SEO options directly from the DB
		$query   = $wpdb->prepare(
			"SELECT option_name, option_value FROM $wpdb->options WHERE option_name LIKE %s",
			$wpdb->esc_like( 'wpseo' ) . '%'
		);
  // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results( $query );

		foreach ( $results as $row ) {
			$settings[ $row->option_name ] = maybe_unserialize( $row->option_value );
		}

		// 3. Add highly specific context (if any additional derived state is needed)
		$settings['yoast_custom_metadata'] = array();

		return $settings;
	}

	/**
	 * Updates a Yoast option (used for rollbacks).
	 *
	 * @param string $plugin_slug The slug of the plugin.
	 * @param string $key The option key.
	 * @param mixed  $value The new value.
	 * @return bool True on success.
	 */
	public function update_setting( $plugin_slug, $key, $value ) {
		return update_option( $key, $value );
	}
}

// Register the Yoast adapter into the Registry
add_filter(
	'wp_ai_context_adapters',
	function ( $adapters ) {
		// 'wordpress-seo' is the typical slug for Yoast
		$adapters['wordpress-seo'] = 'WP_AI_Context_Yoast_Adapter';
		return $adapters;
	}
);
