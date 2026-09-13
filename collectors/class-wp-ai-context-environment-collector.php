<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Environment Collector
 * Gathers server and WordPress environment data.
 */
class WP_AI_Context_Environment_Collector {

	/**
	 * Get standard environment data.
	 *
	 * @return array
	 */
	public function get_environment() {
		global $wp_version;

		return array(
			'wordpress_version'      => $wp_version,
			'php_version'            => phpversion(),
			'multisite'              => is_multisite(),
			'debug_mode'             => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'routing_and_permalinks' => $this->get_routing_data(),
		);
	}

	/**
	 * Gathers permalink structure and registered rewrite rules.
	 *
	 * @return array
	 */
	private function get_routing_data() {
		global $wp_rewrite;

		// Defensive check for WP-CLI or early-execution contexts
		if ( ! ( $wp_rewrite instanceof WP_Rewrite ) ) {
			if ( ! class_exists( 'WP_Rewrite' ) ) {
				require_once ABSPATH . WPINC . '/class-wp-rewrite.php';
			}
			$wp_rewrite = new WP_Rewrite();
		}

		// Fetch the raw rules
		$rules = $wp_rewrite->wp_rewrite_rules();

		// To save tokens, we only extract the regex keys, as the AI can usually deduce the mapping
		$rule_keys = is_array( $rules ) ? array_keys( $rules ) : array();

		return array(
			'permalink_structure'          => get_option( 'permalink_structure' ),
			'category_base'                => get_option( 'category_base' ),
			'tag_base'                     => get_option( 'tag_base' ),
			'using_index_permalinks'       => $wp_rewrite->using_index_permalinks(),
			'using_mod_rewrite_permalinks' => $wp_rewrite->using_mod_rewrite_permalinks(),
			'rewrite_rules_count'          => count( $rule_keys ),
			'rewrite_rules_regex'          => $rule_keys,
		);
	}
}
