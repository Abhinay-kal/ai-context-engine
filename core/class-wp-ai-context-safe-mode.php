<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Safe Mode Engine
 * Hook-based shadowing to safely preview AI changes without altering the DB.
 */

class WP_AI_Context_Safe_Mode {

	public function __construct() {
		add_action( 'init', array( $this, 'maybe_init_safe_mode' ) );
	}

	public function maybe_init_safe_mode() {
		// Only run if admin and requesting preview
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_GET['ai_preview'] ) || $_GET['ai_preview'] !== 'true' ) {
			return;
		}

		$user_id   = get_current_user_id();
		$action_id = get_transient( "wp_ai_preview_{$user_id}" );

		if ( empty( $action_id ) ) {
			return;
		}

		$pending = get_option( 'wp_ai_pending_actions', array() );
		if ( ! isset( $pending[ $action_id ] ) ) {
			return;
		}

		$action  = $pending[ $action_id ];
		$changes = $action['changes'];

		// Hook into pre_option_{$key} and get_post_metadata for each proposed change
		foreach ( $changes as $change ) {
			$setting_key = $change['key'];
			$new_value   = $change['value'];

			// Issue 2 Fix: Support intercepting wp_options
			add_filter(
				"pre_option_{$setting_key}",
				function ( $false_val ) use ( $new_value ) {
					return $new_value; // Serve the shadowed value from memory
				}
			);

			// Issue 2 Fix: Support intercepting WooCommerce / Custom Post Meta
			// If the setting key looks like postmeta (e.g., _price or _stock)
			add_filter(
				'get_post_metadata',
				function ( $value, $object_id, $meta_key, $single ) use ( $setting_key, $new_value ) {
					if ( $meta_key === $setting_key ) {
						return $single ? $new_value : array( $new_value );
					}
					return $value;
				},
				10,
				4
			);
		}

		// Add an admin bar notice so they know they are in Safe Mode
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_notice' ), 999 );
	}

	public function add_admin_bar_notice( $wp_admin_bar ) {
		$wp_admin_bar->add_node(
			array(
				'id'    => 'ai-safe-mode',
				'title' => '<span style="color: #ffb900; font-weight: bold;">⚠️ AI Safe Mode Active</span>',
				'href'  => admin_url( 'admin.php?page=wp-ai-context' ),
				'meta'  => array( 'class' => 'ai-safe-mode-warning' ),
			)
		);
	}
}
