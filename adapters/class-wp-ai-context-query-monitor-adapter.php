<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Query Monitor Adapter
 * Extracts performance profiling data from the Query Monitor plugin.
 */
class WP_AI_Context_Query_Monitor_Adapter implements WP_AI_Context_Adapter_Interface {

	/**
	 * Initializes the hooks to capture frontend profiling data.
	 */
	public static function init() {
		// Only hook the frontend capture when Query Monitor is actually active,
		// so the plugin adds no overhead to sites that do not run QM.
		add_action( 'init', array( __CLASS__, 'maybe_init_capture' ) );
	}

	public static function maybe_init_capture() {
		if ( ! class_exists( 'QM_Collectors' ) ) {
			return;
		}
		add_action( 'shutdown', array( __CLASS__, 'capture_frontend_profiling' ), 999 );
	}

	/**
	 * Captures the profiling data during a frontend request and saves it to a transient.
	 */
	public static function capture_frontend_profiling() {
		// Skip AJAX, CRON, and Admin requests to capture standard frontend loads
		if ( wp_doing_ajax() || wp_doing_cron() || is_admin() ) {
			return;
		}

		if ( ! class_exists( 'QM_Collectors' ) ) {
			return;
		}

		// Debounce: only persist history once per 5 minutes to avoid a
		// database/transient write on every page load.
		$last_write = get_transient( 'wp_ai_context_qm_last_capture' );
		if ( $last_write && ( time() - (int) $last_write ) < 5 * MINUTE_IN_SECONDS ) {
			return;
		}
		set_transient( 'wp_ai_context_qm_last_capture', time(), 5 * MINUTE_IN_SECONDS );

		$profiling_data = self::extract_profiling_data();
		if ( empty( $profiling_data ) ) {
			return;
		}

		// Add requested URL and time
		$https                          = isset( $_SERVER['HTTPS'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTPS'] ) ) : '';
		$scheme                         = ( 'on' === $https ) ? 'https' : 'http';
		$http_host                      = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$request_uri                    = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$profiling_data['request_url']  = $scheme . '://' . $http_host . $request_uri;
		$profiling_data['request_time'] = current_time( 'mysql' );

		// Retrieve history
		$history = get_transient( 'wp_ai_context_qm_history' );
		if ( ! is_array( $history ) ) {
			$history = array();
		}

		// Add to beginning of array
		array_unshift( $history, $profiling_data );

		// Keep only the last 5 requests
		$history = array_slice( $history, 0, 5 );

		set_transient( 'wp_ai_context_qm_history', $history, HOUR_IN_SECONDS * 24 );
	}

	/**
	 * Get the settings and profiling data for Query Monitor.
	 *
	 * @param string $plugin_slug The slug of the plugin.
	 * @return array The extracted data.
	 */
	public function get_settings( $plugin_slug ) {
		$data = array();

		// Check if Query Monitor is active and its core class exists
		if ( ! class_exists( 'QM_Collectors' ) ) {
			return array( '_status' => 'Query Monitor classes not loaded in this context.' );
		}

		// 1. Get Basic Settings
		$data['settings'] = get_option( 'qm_settings', array() );

		// 2. Token-Optimized Profiling Data (Historical)
		// We pull from the history transient instead of profiling the AJAX request itself.
		$history = get_transient( 'wp_ai_context_qm_history' );
		if ( is_array( $history ) && ! empty( $history ) ) {
			$data['historical_frontend_profiling'] = $history;
		} else {
			$data['historical_frontend_profiling'] = 'No recent frontend requests captured.';
		}

		return $data;
	}

	/**
	 * Extracts and heavily optimizes QM profiling data to save tokens.
	 *
	 * @return array
	 */
	private static function extract_profiling_data() {
		$profiling = array();

		// Database Queries
		if ( $db_queries = QM_Collectors::get( 'db_queries' ) ) {
			if ( isset( $db_queries->data ) ) {
				$profiling['database'] = array(
					'total_queries' => $db_queries->data->total_qs,
					'total_time'    => round( $db_queries->data->total_time, 4 ) . 's',
					'total_errors'  => $db_queries->data->total_errors,
				);

				// TOKEN OPTIMIZATION: Only send the 5 slowest queries instead of all queries
				if ( ! empty( $db_queries->data->times ) ) {
					// $db_queries->data->times is typically sorted by time
					$slowest_queries        = array_slice( $db_queries->data->times, 0, 5 );
					$optimized_slow_queries = array();

					foreach ( $slowest_queries as $query_data ) {
						// Strip absolute paths from the calling component
						$component                = str_replace( ABSPATH, '', $query_data->component );
						$optimized_slow_queries[] = array(
							'time'      => round( $query_data->ltime, 4 ) . 's',
							'sql'       => substr( trim( $query_data->sql ), 0, 200 ) . '...', // Truncate long SQL
							'component' => $component,
						);
					}
					$profiling['database']['top_5_slowest'] = $optimized_slow_queries;
				}
			}
		}

		// Environment & Memory
		if ( $env = QM_Collectors::get( 'environment' ) ) {
			if ( isset( $env->data ) ) {
				$profiling['memory'] = array(
					'peak_usage' => size_format( $env->data->memory_peak ),
					'limit'      => $env->data->memory_limit,
				);
			}
		}

		// PHP Errors (if any were caught during the request)
		if ( $php_errors = QM_Collectors::get( 'php_errors' ) ) {
			if ( ! empty( $php_errors->data->errors ) ) {
				$profiling['php_errors_count'] = count( $php_errors->data->errors );
				// We don't export the full stack traces here because our Log Collector
				// already handles PHP errors efficiently and deduplicates them.
			}
		}

		return $profiling;
	}

	/**
	 * Updates a Query Monitor option (used for rollbacks).
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
