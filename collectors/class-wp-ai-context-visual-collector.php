<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Visual Collector
 * Generates external screenshot URLs so multimodal AIs can see the site's frontend.
 */

class WP_AI_Context_Visual_Collector {

	/**
	 * Generates markdown for visual context.
	 *
	 * @return string Markdown formatted visual context.
	 */
	public function get_visual_context() {
		$home_url = home_url();
		$parsed   = wp_parse_url( $home_url );
		$host     = isset( $parsed['host'] ) ? $parsed['host'] : '';

		// Check if site is local (mshots cannot reach localhost)
		$is_local = false;
		if ( $host === 'localhost' ||
			$host === '127.0.0.1' ||
			strpos( $host, '.local' ) !== false ||
			strpos( $host, '.test' ) !== false
		) {
			$is_local = true;
		}

		$markdown = "## Visual Context (Frontend Screenshots)\n\n";

		if ( $is_local ) {
			$markdown .= "> **Notice:** The site is running on a local environment (`$host`). External screenshot APIs cannot reach it. The AI will not be able to see the frontend.\n\n";
			return $markdown;
		}

		// Use Automattic's free mshots API
		$mshots_url = 'https://s0.wordpress.com/mshots/v1/' . rawurlencode( $home_url ) . '?w=1280&h=800';

		$markdown .= "The following image represents the current visual state of the homepage. If you have multimodal capabilities, use this to diagnose layout or CSS issues.\n\n";
		$markdown .= '![Homepage Screenshot](' . esc_url( $mshots_url ) . ")\n\n";

		return $markdown;
	}
}
