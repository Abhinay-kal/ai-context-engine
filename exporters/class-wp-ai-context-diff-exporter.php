<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Diff Exporter
 * Compares two Context JSON arrays and generates a Markdown diff.
 */
class WP_AI_Context_Diff_Exporter {

	/**
	 * Compare a historical snapshot with a current context package and return Markdown.
	 */
	public function export_diff( $old_context, $new_context ) {
		$markdown = "# WP AI Context - State Diff\n\n";

		$markdown .= "## 1. Plugin Version Changes\n";
		$markdown .= $this->diff_conflict_surface( $old_context['conflict_surface'] ?? array(), $new_context['conflict_surface'] ?? array() );

		$markdown .= "\n## 2. Setting Changes\n";
		$markdown .= $this->diff_settings( $old_context['settings'] ?? array(), $new_context['settings'] ?? array() );

		$markdown .= "\n## 3. Environment Changes\n";
		$markdown .= $this->diff_environment( $old_context['environment'] ?? array(), $new_context['environment'] ?? array() );

		return $markdown;
	}

	private function diff_conflict_surface( $old, $new ) {
		$output = '';

		// Find removed or downgraded plugins
		foreach ( $old as $slug => $data ) {
			if ( ! isset( $new[ $slug ] ) ) {
				$output .= "- [REMOVED] Plugin deactivated or deleted: {$data['Name']} (was v{$data['Version']})\n";
			} elseif ( $new[ $slug ]['Version'] !== $data['Version'] ) {
				$output .= "! [UPDATED] {$data['Name']} changed from v{$data['Version']} to v{$new[ $slug ]['Version']}\n";
			}
		}

		// Find newly added plugins
		foreach ( $new as $slug => $data ) {
			if ( ! isset( $old[ $slug ] ) ) {
				$output .= "+ [ADDED] Plugin activated: {$data['Name']} (v{$data['Version']})\n";
			}
		}

		if ( empty( $output ) ) {
			return "*No plugin version changes detected.*\n";
		}

		return "```diff\n" . $output . "```\n";
	}

	private function diff_settings( $old, $new ) {
		$output = '';

		$all_plugins = array_unique( array_merge( array_keys( $old ), array_keys( $new ) ) );

		foreach ( $all_plugins as $plugin_slug ) {
			$old_settings = $old[ $plugin_slug ] ?? array();
			$new_settings = $new[ $plugin_slug ] ?? array();

			if ( empty( $old_settings ) && empty( $new_settings ) ) {
				continue;
			}

			$plugin_diff = '';

			// Check removed or changed settings
			foreach ( $old_settings as $key => $val ) {
				if ( ! array_key_exists( $key, $new_settings ) ) {
					$plugin_diff .= "- [$key] deleted (was: " . wp_json_encode( $val ) . ")\n";
				} else {
					// Both exist, compare them
					$new_val = $new_settings[ $key ];
					if ( wp_json_encode( $val ) !== wp_json_encode( $new_val ) ) {
						$plugin_diff .= "! [$key] changed:\n";
						$plugin_diff .= '  - Old: ' . wp_json_encode( $val ) . "\n";
						$plugin_diff .= '  + New: ' . wp_json_encode( $new_val ) . "\n";
					}
				}
			}

			// Check added settings
			foreach ( $new_settings as $key => $val ) {
				if ( ! array_key_exists( $key, $old_settings ) ) {
					$plugin_diff .= "+ [$key] added: " . wp_json_encode( $val ) . "\n";
				}
			}

			if ( ! empty( $plugin_diff ) ) {
				$output .= "### $plugin_slug\n```diff\n$plugin_diff```\n";
			}
		}

		if ( empty( $output ) ) {
			return "*No specific settings changes detected.*\n";
		}

		return $output;
	}

	private function diff_environment( $old, $new ) {
		$output = '';

		// Just a shallow comparison of keys like php_version, memory_limit
		$keys = array_unique( array_merge( array_keys( $old ), array_keys( $new ) ) );
		foreach ( $keys as $key ) {
			// Skip complex nested structures for basic diffing
			if ( is_array( $old[ $key ] ?? null ) || is_array( $new[ $key ] ?? null ) ) {
				continue;
			}

			$old_val = $old[ $key ] ?? 'null';
			$new_val = $new[ $key ] ?? 'null';

			if ( $old_val !== $new_val ) {
				$output .= "! [$key] changed from '$old_val' to '$new_val'\n";
			}
		}

		if ( empty( $output ) ) {
			return "*No environment changes detected.*\n";
		}

		return "```diff\n" . $output . "```\n";
	}
}
