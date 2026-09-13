<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Preset Engine
 * Defines and resolves diagnostic presets.
 */
class WP_AI_Context_Presets {

	/**
	 * Get the list of registered presets.
	 * Filters are applied so 3rd party plugins can register their own.
	 */
	public function get_presets() {
		require_once WP_AI_CONTEXT_PLUGIN_DIR . 'collectors/class-wp-ai-context-log-collector.php';
		$log_collector = new WP_AI_Context_Log_Collector();
		$logs          = $log_collector->get_logs();
		$log_text      = '';
		if ( isset( $logs['groups'] ) && is_array( $logs['groups'] ) ) {
			foreach ( $logs['groups'] as $group ) {
				if ( isset( $group['message'] ) ) {
					$log_text .= $group['message'] . "\n";
				}
			}
		}

		$presets = array(
			'woocommerce_diagnostic' => array(
				'id'             => 'woocommerce_diagnostic',
				'title'          => 'WooCommerce Diagnostic',
				'description'    => 'Gathers WooCommerce settings, payment gateways, and custom shop tables.',
				'icon'           => 'cart', // dashicon slug
				'is_recommended' => ( stripos( $log_text, 'woocommerce' ) !== false ),
			),
			'seo_audit'              => array(
				'id'             => 'seo_audit',
				'title'          => 'SEO Audit',
				'description'    => 'Gathers SEO plugin configurations (Yoast/RankMath) and permalink structures.',
				'icon'           => 'search',
				'is_recommended' => ( stripos( $log_text, 'seo' ) !== false || stripos( $log_text, 'yoast' ) !== false ),
			),
			'performance_audit'      => array(
				'id'             => 'performance_audit',
				'title'          => 'Performance & Caching',
				'description'    => 'Focuses on caching rules (WP Rocket, LiteSpeed) and PHP execution limits.',
				'icon'           => 'dashboard',
				'is_recommended' => ( stripos( $log_text, 'memory_limit' ) !== false || stripos( $log_text, 'execution time' ) !== false ),
			),
		);

		return apply_filters( 'wp_ai_context_presets', $presets );
	}

	/**
	 * Resolve a preset ID into actual export arguments (plugins, tables, etc.).
	 */
	public function resolve_preset( $preset_id, $active_plugins = array() ) {
		$args = array(
			'plugins'         => array(),
			'tables'          => array(),
			'table_prefixes'  => array(),
			'theme_files'     => array(),
			'theme_overrides' => array(),
			'mode'            => 'delta',
		);

		switch ( $preset_id ) {
			case 'woocommerce_diagnostic':
				$args['table_prefixes']  = array( 'wc_', 'woocommerce_' ); // Automatically fetch ALL WooCommerce tables
				$args['theme_overrides'] = array( 'woocommerce' ); // Capture template overrides from the active theme
				foreach ( $active_plugins as $slug => $data ) {
					if ( strpos( $slug, 'woocommerce' ) !== false || strpos( $slug, 'stripe' ) !== false || strpos( $slug, 'paypal' ) !== false ) {
						$args['plugins'][] = $slug;
					}
				}
				break;

			case 'seo_audit':
				foreach ( $active_plugins as $slug => $data ) {
					if ( in_array( $slug, array( 'wordpress-seo', 'seo-by-rank-math', 'all-in-one-seo-pack', 'autodescription' ), true ) ) {
						$args['plugins'][] = $slug;
					}
				}
				break;

			case 'performance_audit':
				foreach ( $active_plugins as $slug => $data ) {
					if ( in_array( $slug, array( 'query-monitor', 'litespeed-cache', 'w3-total-cache', 'wp-super-cache', 'wp-rocket', 'autoptimize' ), true ) ) {
						$args['plugins'][] = $slug;
					}
				}
				break;
		}

		return apply_filters( 'wp_ai_context_resolve_preset', $args, $preset_id, $active_plugins );
	}
}
