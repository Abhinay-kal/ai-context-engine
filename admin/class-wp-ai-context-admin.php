<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * The admin-specific functionality of the plugin.
 */
class WP_AI_Context_Admin {

	/**
	 * Register the administration menu for this plugin into the WordPress Dashboard menu.
	 */
	public function add_plugin_admin_menu() {
		$hook = add_menu_page(
			__( 'WP AI Context', 'wp-ai-context' ),
			__( 'WP AI Context', 'wp-ai-context' ),
			'manage_options',
			'wp-ai-context',
			array( $this, 'display_plugin_setup_page' ),
			'dashicons-media-code',
			100
		);

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue scripts and styles for the admin page.
	 */
	public function enqueue_scripts( $hook_suffix ) {
		if ( $hook_suffix !== 'toplevel_page_wp-ai-context' ) {
			return;
		}

		wp_enqueue_style( 'wp-components' ); // For native WP React components
		wp_enqueue_style( 'wp-ai-context-admin', plugin_dir_url( __DIR__ ) . 'admin/css/admin.css', array(), '1.0.0' );

		$asset_file = WP_AI_CONTEXT_PLUGIN_DIR . 'build/index.asset.php';

		if ( file_exists( $asset_file ) ) {
			$asset = require $asset_file;
			wp_enqueue_script(
				'wp-ai-context-app',
				plugin_dir_url( __DIR__ ) . 'build/index.js',
				$asset['dependencies'],
				$asset['version'],
				true
			);
			if ( file_exists( WP_AI_CONTEXT_PLUGIN_DIR . 'build/style-index.css' ) ) {
				wp_enqueue_style(
					'wp-ai-context-app-style',
					plugin_dir_url( __DIR__ ) . 'build/style-index.css',
					array(),
					$asset['version']
				);
			}
		} else {
			// Built asset missing; enqueue nothing rather than a non-existent file.
			wp_enqueue_script(
				'wp-ai-context-app',
				plugins_url( 'build/index.js', __DIR__ ),
				array( 'wp-element', 'wp-components', 'wp-api-fetch' ),
				'1.0.0',
				true
			);
		}

		require_once WP_AI_CONTEXT_PLUGIN_DIR . 'collectors/class-wp-ai-context-plugin-collector.php';
		require_once WP_AI_CONTEXT_PLUGIN_DIR . 'core/class-wp-ai-context-presets.php';
		require_once WP_AI_CONTEXT_PLUGIN_DIR . 'core/class-wp-ai-context-snapshot.php';

		$plugin_collector = new WP_AI_Context_Plugin_Collector();
		$active_plugins   = $plugin_collector->get_active_plugins();

		$preset_engine = new WP_AI_Context_Presets();
		$presets       = $preset_engine->get_presets();

		$snapshot_engine = new WP_AI_Context_Snapshot();
		$snapshots       = $snapshot_engine->get_snapshot_list();

		$pending_actions = get_option( 'wp_ai_pending_actions', array() );

		wp_localize_script(
			'wp-ai-context-app',
			'wpAiContextData',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'wp_ai_context_generate' ),
				'plugins'        => $active_plugins,
				'presets'        => array_values( $presets ),
				'snapshots'      => $snapshots,
				'pendingActions' => array_values( $pending_actions ),
				'selfSlug'       => defined( 'WP_AI_CONTEXT_SLUG' ) ? WP_AI_CONTEXT_SLUG : 'ai-context-engine',
				'selfSlugPrev'   => defined( 'WP_AI_CONTEXT_SLUG_PREVIOUS' ) ? WP_AI_CONTEXT_SLUG_PREVIOUS : 'wp-ai-context',
				'is_pro'         => true,
			)
		);
	}

	/**
	 * Render the settings page for this plugin.
	 */
	public function display_plugin_setup_page() {
		// Handle API Key saving
		if ( isset( $_POST['wp_ai_context_api_key_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_ai_context_api_key_nonce'] ) ), 'save_api_key' ) ) {
			if ( current_user_can( 'manage_options' ) && isset( $_POST['wp_ai_context_api_key'] ) ) {
				$new_key = sanitize_text_field( wp_unslash( $_POST['wp_ai_context_api_key'] ) );
				if ( ! empty( $new_key ) && $new_key !== 'Key is set (hidden). Enter new to replace or leave blank to delete.' ) {
					update_option( 'wp_ai_context_api_key', wp_hash_password( $new_key ) );
				} elseif ( isset( $_POST['wp_ai_context_delete_key'] ) && $_POST['wp_ai_context_delete_key'] === '1' ) {
					// Only delete if explicitly requested (e.g. via a new hidden field or checkbox)
					delete_option( 'wp_ai_context_api_key' );
				}
				// Note: if $new_key is empty but no delete flag is set, we do nothing and keep the existing key.
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'API Key saved successfully!', 'wp-ai-context' ) . '</p></div>';
			}
		}

		require_once WP_AI_CONTEXT_PLUGIN_DIR . 'admin/views/admin-page.php';
	}
}
