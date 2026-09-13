<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * WooCommerce Specific Adapter
 */
class WP_AI_Context_WooCommerce_Adapter implements WP_AI_Context_Adapter_Interface {

	public function get_settings( $plugin_slug ) {
		// 1. Get generic options first (fallback logic)
		$generic  = new WP_AI_Context_Generic_Adapter();
		$settings = $generic->get_settings( $plugin_slug );

		global $wpdb;

		// 2. Fetch all WooCommerce options directly from the DB
		$query   = $wpdb->prepare(
			"SELECT option_name, option_value FROM $wpdb->options WHERE option_name LIKE %s",
			$wpdb->esc_like( 'woocommerce' ) . '%'
		);
  // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results( $query );

		foreach ( $results as $row ) {
			$settings[ $row->option_name ] = maybe_unserialize( $row->option_value );
		}

		// Also grab 'wc_' prefixed options which Woo often uses
		$query_wc   = $wpdb->prepare(
			"SELECT option_name, option_value FROM $wpdb->options WHERE option_name LIKE %s",
			$wpdb->esc_like( 'wc_' ) . '%'
		);
  // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results_wc = $wpdb->get_results( $query_wc );

		foreach ( $results_wc as $row ) {
			$settings[ $row->option_name ] = maybe_unserialize( $row->option_value );
		}

		// 3. Add highly specific context (if any additional derived state is needed)
		$settings['wc_custom_metadata'] = array(
			// Example of grabbing data outside standard options (if WC was loaded)
			'has_shipping_zones' => $this->has_shipping_zones(),
		);

		return $settings;
	}

	private function has_shipping_zones() {
		global $wpdb;
		// A specific DB check WooCommerce relies on
		$table_name = $wpdb->prefix . 'woocommerce_shipping_zones';
  // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$table      = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
		if ( $table ) {
   // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name}" ) );
			return (int) $count > 0 ? 'yes' : 'no';
		}
		return 'unknown';
	}

	/**
	 * Updates a WooCommerce option (used for rollbacks).
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

// Register the WooCommerce adapter into the Registry
add_filter(
	'wp_ai_context_adapters',
	function ( $adapters ) {
		$adapters['woocommerce'] = 'WP_AI_Context_WooCommerce_Adapter';
		return $adapters;
	}
);
