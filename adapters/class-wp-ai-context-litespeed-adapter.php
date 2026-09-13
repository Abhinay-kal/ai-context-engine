<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * LiteSpeed Cache Specific Adapter
 */
class WP_AI_Context_LiteSpeed_Adapter implements WP_AI_Context_Adapter_Interface {

	public function get_settings( $plugin_slug ) {
		// 1. Get generic options first (fallback logic)
		$generic  = new WP_AI_Context_Generic_Adapter();
		$settings = $generic->get_settings( $plugin_slug );

		global $wpdb;

		// 2. Fetch all options starting with 'litespeed' directly from the DB
		// This guarantees we capture EVERY single setting, parameter, and configuration block.
		$query   = $wpdb->prepare(
			"SELECT option_name, option_value FROM $wpdb->options WHERE option_name LIKE %s",
			$wpdb->esc_like( 'litespeed' ) . '%'
		);
  // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results( $query );

		foreach ( $results as $row ) {
			$settings[ $row->option_name ] = maybe_unserialize( $row->option_value );
		}

		// 3. Add highly specific context (if any additional derived state is needed)
		$server_software                       = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '';
		$settings['litespeed_custom_metadata'] = array(
			'is_litespeed_server' => strpos( $server_software, 'LiteSpeed' ) !== false ? 'yes' : 'no',
		);

		return $settings;
	}

	/**
	 * Updates a LiteSpeed option (used for rollbacks).
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

// Register the LiteSpeed adapter into the Registry
add_filter(
	'wp_ai_context_adapters',
	function ( $adapters ) {
		$adapters['litespeed-cache'] = 'WP_AI_Context_LiteSpeed_Adapter';
		return $adapters;
	}
);
