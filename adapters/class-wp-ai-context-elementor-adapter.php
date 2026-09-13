<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Elementor Specific Adapter
 */
class WP_AI_Context_Elementor_Adapter implements WP_AI_Context_Adapter_Interface {

	public function get_settings( $plugin_slug ) {
		// 1. Get generic options first (fallback logic)
		$generic  = new WP_AI_Context_Generic_Adapter();
		$settings = $generic->get_settings( $plugin_slug );

		global $wpdb;

		// 2. Fetch all Elementor options directly from the DB
		$query   = $wpdb->prepare(
			"SELECT option_name, option_value FROM $wpdb->options WHERE option_name LIKE %s",
			$wpdb->esc_like( 'elementor' ) . '%'
		);
  // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results( $query );

		foreach ( $results as $row ) {
			$settings[ $row->option_name ] = maybe_unserialize( $row->option_value );
		}

		// 3. Add highly specific context (if any additional derived state is needed)
		$settings['elementor_custom_metadata'] = array(
			// DB check for Elementor templates
			'has_custom_templates' => $this->has_custom_templates(),
		);

		return $settings;
	}

	private function has_custom_templates() {
		global $wpdb;
		// Check if there are any published Elementor library templates
		$query = $wpdb->prepare(
			"SELECT COUNT(ID) FROM $wpdb->posts WHERE post_type = %s AND post_status = %s",
			'elementor_library',
			'publish'
		);

  // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = $wpdb->get_var( $query );
		return (int) $count > 0 ? 'yes' : 'no';
	}

	/**
	 * Updates an Elementor option (used for rollbacks).
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

// Register the Elementor adapter into the Registry
add_filter(
	'wp_ai_context_adapters',
	function ( $adapters ) {
		$adapters['elementor'] = 'WP_AI_Context_Elementor_Adapter';
		return $adapters;
	}
);
