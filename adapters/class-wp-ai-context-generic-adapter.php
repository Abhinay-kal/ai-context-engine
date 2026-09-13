<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Generic Adapter
 * Fallback adapter that guesses options based on plugin prefix.
 */
class WP_AI_Context_Generic_Adapter implements WP_AI_Context_Adapter_Interface {

	/**
	 * Extract settings for a given plugin slug.
	 *
	 * @param string $plugin_slug The slug of the plugin (e.g. 'woocommerce').
	 * @return array The extracted settings.
	 */
	public function get_settings( $plugin_slug ) {
		global $wpdb;
		$settings    = array();
		$option_keys = array();

		$cache_key = 'wp_ai_context_generic_' . md5( $plugin_slug );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		// 1. Static Analysis: Scan plugin files for get_option() calls
		if ( defined( 'WP_PLUGIN_DIR' ) ) {
			$plugin_dir = WP_PLUGIN_DIR . '/' . $plugin_slug;
			if ( is_dir( $plugin_dir ) ) {
				$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $plugin_dir ) );
				foreach ( $iterator as $file ) {
					if ( $file->isFile() && $file->getExtension() === 'php' ) {
						$content = file_get_contents( $file->getPathname() );
						if ( preg_match_all( '/get_option\s*\(\s*[\'"]([a-zA-Z0-9_\-\.]+)[\'"]/', $content, $matches ) ) {
							foreach ( $matches[1] as $option_name ) {
								$option_keys[ $option_name ] = true;
							}
						}
					}
				}
			}
		}

		// 1.5 Fetch user-taught options
		$learned_options = get_option( 'wp_ai_context_learned_options', array() );
		if ( isset( $learned_options[ $plugin_slug ] ) && is_array( $learned_options[ $plugin_slug ] ) ) {
			foreach ( $learned_options[ $plugin_slug ] as $learned_key ) {
				$option_keys[ $learned_key ] = true;
			}
		}

		// Fetch discovered exact options
		if ( ! empty( $option_keys ) ) {
			$keys_list = array_keys( $option_keys );
			// Process in chunks to avoid massive SQL queries
			$chunks = array_chunk( $keys_list, 100 );
			foreach ( $chunks as $chunk ) {
				$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );
				$query        = "SELECT option_name, option_value FROM $wpdb->options WHERE option_name IN ($placeholders)";

				// Call prepare dynamically to expand the array elements as arguments
				$prepared_query = call_user_func_array( array( $wpdb, 'prepare' ), array_merge( array( $query ), $chunk ) );

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$results = $wpdb->get_results( $prepared_query );
				foreach ( $results as $row ) {
					$settings[ $row->option_name ] = maybe_unserialize( $row->option_value );
				}
			}
		}

		// 2. Heuristic fallback: prefix match
		$prefix = str_replace( '-', '_', $plugin_slug ) . '_';
		$query  = $wpdb->prepare(
			"SELECT option_name, option_value FROM $wpdb->options WHERE option_name LIKE %s",
			$wpdb->esc_like( $prefix ) . '%'
		);

  // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results( $query );
		foreach ( $results as $row ) {
			// Don't overwrite if static analysis already fetched it (though values would be identical)
			if ( ! isset( $settings[ $row->option_name ] ) ) {
				$settings[ $row->option_name ] = maybe_unserialize( $row->option_value );
			}
		}

		if ( ! empty( $settings ) ) {
			set_transient( $cache_key, $settings, 6 * HOUR_IN_SECONDS );
		}

		return $settings;
	}

	/**
	 * Updates a specific option for the plugin (used for rollbacks).
	 *
	 * @param string $plugin_slug The slug of the plugin.
	 * @param string $key The option key.
	 * @param mixed  $value The new value.
	 * @return bool True on success.
	 */
	public function update_setting( $plugin_slug, $key, $value ) {
		// Option write allow-list: only accept keys namespaced to this plugin
		// so a compromised AI proposal cannot write arbitrary wp_options rows.
		$prefix = str_replace( '-', '_', $plugin_slug ) . '_';
		if ( strpos( $key, $prefix ) !== 0 ) {
			return false;
		}
		$result = update_option( $key, $value );
		delete_transient( 'wp_ai_context_generic_' . md5( $plugin_slug ) );
		return $result;
	}
}
