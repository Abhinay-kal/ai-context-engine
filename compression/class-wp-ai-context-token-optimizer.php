<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Token Optimizer
 * Compresses the context payload to reduce LLM token usage.
 */
class WP_AI_Context_Token_Optimizer {

	private $baseline_defaults = array();

	public function __construct() {
		$baseline_file = __DIR__ . '/wp-core-defaults.json';
		if ( file_exists( $baseline_file ) ) {
			$json                    = file_get_contents( $baseline_file );
			$this->baseline_defaults = json_decode( $json, true );
			if ( ! is_array( $this->baseline_defaults ) ) {
				$this->baseline_defaults = array();
			}
		}
	}

	/**
	 * Removes empty, null, or useless default values recursively.
	 *
	 * @param array  $data
	 * @param string $mode 'full' or 'delta'
	 * @return array
	 */
	public function optimize( $data, $mode = 'delta' ) {
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$optimized_array = $this->optimize( $value, $mode );
				if ( empty( $optimized_array ) && $mode === 'delta' ) {
					unset( $data[ $key ] );
				} else {
					$data[ $key ] = $optimized_array;
				}
			} else {
				// 1. Transient Summarization
				if ( is_string( $key ) && strpos( $key, '_transient_' ) !== false ) {
					$data[ $key ] = $this->summarize_transient( $value );
					continue;
				}

				// 2. Delta Mode (Strip useless defaults)
				if ( $mode === 'delta' ) {
					// Check against baseline if this is a known top-level option key
					if ( is_string( $key ) && isset( $this->baseline_defaults[ $key ] ) ) {
						// Standardize types for comparison (WordPress often returns numeric options as strings)
						if ( (string) $this->baseline_defaults[ $key ] === (string) $value ) {
							unset( $data[ $key ] );
							continue;
						}
					}

					// Also strip inherently useless values
					if ( $this->is_useless_value( $value ) ) {
						unset( $data[ $key ] );
					}
				}
			}
		}

		return $data;
	}

	/**
	 * Determines if a value adds no context for the AI.
	 */
	private function is_useless_value( $value ) {
		// Empty strings add no context
		if ( $value === '' ) {
			return true;
		}

		// Nulls add no context
		if ( $value === null ) {
			return true;
		}

		// 'no' or 'false' stored as strings might be defaults, but we should be careful.
		// For Phase 2, we aggressively trim empty arrays, nulls, and empty strings.

		return false;
	}

	/**
	 * Summarizes a transient value to save tokens.
	 */
	private function summarize_transient( $value ) {
		$type = gettype( $value );
		$size = is_string( $value ) ? strlen( $value ) : ( is_array( $value ) ? count( $value ) . ' items' : '' );
		return "[TRANSIENT_TRUNCATED] Type: {$type} | Size: {$size}";
	}
}
