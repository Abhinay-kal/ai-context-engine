<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Markdown Exporter
 * Formats the context package into highly token-efficient Markdown and YAML.
 */
class WP_AI_Context_Markdown_Exporter {

	/**
	 * Generates a Markdown representation of the context.
	 *
	 * @param array $context The complete context data.
	 * @return string Markdown formatted context.
	 */
	public function export( $context ) {
		$output = "# WP AI Context Package\n\n";

		if ( ! empty( $context['resource_warning'] ) ) {
			$output .= "> **[CRITICAL SYSTEM WARNING]**\n";
			$output .= "> {$context['resource_warning']}\n\n";
		}

		if ( ! empty( $context['environment'] ) ) {
			$output .= "## Environment\n";
			$output .= "```yaml\n";
			$output .= $this->to_yaml( $context['environment'] );
			$output .= "```\n\n";
		}

		// The builder emits the plugin list under conflict_surface; the legacy
		// "plugins" key is still honoured if present (e.g. snapshot contexts).
		$active_plugins = array();
		if ( ! empty( $context['conflict_surface'] ) ) {
			$active_plugins = $context['conflict_surface'];
		} elseif ( ! empty( $context['plugins'] ) ) {
			$active_plugins = $context['plugins'];
		}

		if ( ! empty( $active_plugins ) ) {
			$output .= "## Active Plugins\n";
			foreach ( $active_plugins as $slug => $data ) {
				$name    = isset( $data['name'] ) ? $data['name'] : ( isset( $data['Name'] ) ? $data['Name'] : $slug );
				$version = isset( $data['version'] ) ? $data['version'] : ( isset( $data['Version'] ) ? $data['Version'] : '' );
				$output .= "- **{$name}**";
				if ( $version ) {
					$output .= " (v{$version})";
				}
				$output .= " (`{$slug}`)\n";
			}
			$output .= "\n";
		}

		if ( ! empty( $context['schema'] ) ) {
			$output .= "## Database Schema\n";
			$output .= "```yaml\n";
			$output .= $this->to_yaml( $context['schema'] );
			$output .= "```\n\n";
		}

		if ( ! empty( $context['theme'] ) ) {
			$output .= "## Active Theme\n";
			$output .= "```yaml\n";
			$output .= $this->to_yaml( $context['theme'] );
			$output .= "```\n\n";
		}

		if ( ! empty( $context['debug_logs'] ) ) {
			$output .= "## Debug Logs\n";
			$output .= "```yaml\n";
			$output .= $this->to_yaml( $context['debug_logs'] );
			$output .= "```\n\n";
		}

		if ( ! empty( $context['settings'] ) ) {
			$output .= "## Plugin Settings\n";
			foreach ( $context['settings'] as $plugin_slug => $settings ) {
				$output .= "### {$plugin_slug}\n";
				$output .= "```yaml\n";
				$output .= $this->to_yaml( $settings );
				$output .= "```\n\n";
			}
		}

		if ( ! empty( $context['visuals'] ) ) {
			$output .= $context['visuals'];
		}

		return $output;
	}

	/**
	 * A very basic YAML converter for token efficiency.
	 */
	private function to_yaml( $array, $indent = 0 ) {
		$yaml   = '';
		$spaces = str_repeat( '  ', $indent );

		foreach ( $array as $key => $value ) {
			if ( is_array( $value ) || is_object( $value ) ) {
				$yaml .= "{$spaces}{$key}:\n";
				$yaml .= $this->to_yaml( (array) $value, $indent + 1 );
			} else {
				// Convert booleans to strings
				if ( is_bool( $value ) ) {
					$value = $value ? 'true' : 'false';
				}
				$yaml .= "{$spaces}{$key}: {$value}\n";
			}
		}

		return $yaml;
	}
}
