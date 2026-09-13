<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * The core plugin class.
 * Orchestrates the collectors, adapters, exporters, and admin UI.
 */
class WP_AI_Context {

	public function __construct() {
		$this->load_dependencies();
	}

	private function load_dependencies() {
		// Admin
		require_once WP_AI_CONTEXT_PLUGIN_DIR . 'admin/class-wp-ai-context-admin.php';
		// Privacy
		require_once WP_AI_CONTEXT_PLUGIN_DIR . 'privacy/class-wp-ai-context-secret-masker.php';
		// Collectors
		require_once WP_AI_CONTEXT_PLUGIN_DIR . 'collectors/class-wp-ai-context-environment-collector.php';
		require_once WP_AI_CONTEXT_PLUGIN_DIR . 'collectors/class-wp-ai-context-plugin-collector.php';
		require_once WP_AI_CONTEXT_PLUGIN_DIR . 'collectors/class-wp-ai-context-log-collector.php';
		require_once WP_AI_CONTEXT_PLUGIN_DIR . 'collectors/class-wp-ai-context-schema-collector.php';
		require_once WP_AI_CONTEXT_PLUGIN_DIR . 'collectors/class-wp-ai-context-theme-collector.php';
		require_once WP_AI_CONTEXT_PLUGIN_DIR . 'collectors/class-wp-ai-context-visual-collector.php';
		require_once WP_AI_CONTEXT_PLUGIN_DIR . 'core/class-wp-ai-context-presets.php';
		require_once WP_AI_CONTEXT_PLUGIN_DIR . 'core/class-wp-ai-context-snapshot.php';
		require_once WP_AI_CONTEXT_PLUGIN_DIR . 'core/class-wp-ai-context-safe-mode.php';

		// Initialize Background Capture logic immediately
		new WP_AI_Context_Snapshot();
		new WP_AI_Context_Safe_Mode();
		// Adapters
		require_once WP_AI_CONTEXT_PLUGIN_DIR . 'adapters/interface-adapter.php';
		require_once WP_AI_CONTEXT_PLUGIN_DIR . 'adapters/class-wp-ai-context-adapter-registry.php';
		require_once WP_AI_CONTEXT_PLUGIN_DIR . 'adapters/class-wp-ai-context-generic-adapter.php';
		// Optional: specific adapters
		require_once WP_AI_CONTEXT_PLUGIN_DIR . 'adapters/class-wp-ai-context-woocommerce-adapter.php';
		require_once WP_AI_CONTEXT_PLUGIN_DIR . 'adapters/class-wp-ai-context-litespeed-adapter.php';
		require_once WP_AI_CONTEXT_PLUGIN_DIR . 'adapters/class-wp-ai-context-yoast-adapter.php';
		require_once WP_AI_CONTEXT_PLUGIN_DIR . 'adapters/class-wp-ai-context-elementor-adapter.php';
		require_once WP_AI_CONTEXT_PLUGIN_DIR . 'adapters/class-wp-ai-context-query-monitor-adapter.php';
		// Compression
		require_once WP_AI_CONTEXT_PLUGIN_DIR . 'compression/class-wp-ai-context-token-optimizer.php';
		// API
		require_once WP_AI_CONTEXT_PLUGIN_DIR . 'api/class-wp-ai-context-openapi-generator.php';
		require_once WP_AI_CONTEXT_PLUGIN_DIR . 'api/class-wp-ai-context-rest-api.php';
		// Exporters
		require_once WP_AI_CONTEXT_PLUGIN_DIR . 'exporters/class-wp-ai-context-markdown-exporter.php';
		require_once WP_AI_CONTEXT_PLUGIN_DIR . 'exporters/class-wp-ai-context-diff-exporter.php';
	}

	public function run() {
		$plugin_admin = new WP_AI_Context_Admin();
		add_action( 'admin_menu', array( $plugin_admin, 'add_plugin_admin_menu' ) );

		// Initialize Adapters that need global hooks
		if ( class_exists( 'WP_AI_Context_Query_Monitor_Adapter' ) ) {
			WP_AI_Context_Query_Monitor_Adapter::init();
		}

		// AJAX endpoint for frontend UI
		add_action( 'wp_ajax_wp_ai_context_generate', array( $this, 'generate_context' ) );
		add_action( 'wp_ajax_wp_ai_context_learn_option', array( $this, 'learn_option' ) );
		add_action( 'wp_ajax_wp_ai_context_generate_diff', array( $this, 'generate_diff' ) );
		add_action( 'wp_ajax_wp_ai_context_take_snapshot', array( $this, 'take_snapshot' ) );
		add_action( 'wp_ajax_wp_ai_context_push_cloud', array( $this, 'push_cloud' ) );
		add_action( 'wp_ajax_wp_ai_context_revert_setting', array( $this, 'revert_setting' ) );
		add_action( 'wp_ajax_wp_ai_context_preview_action', array( $this, 'preview_action' ) );
		add_action( 'wp_ajax_wp_ai_context_approve_action', array( $this, 'approve_action' ) );
		add_action( 'wp_ajax_wp_ai_context_reject_action', array( $this, 'reject_action' ) );
		add_action( 'wp_ajax_wp_ai_context_export_playground', array( $this, 'export_playground' ) );
		add_action( 'wp_ajax_wp_ai_context_generate_app_password', array( $this, 'generate_app_password' ) );

		// REST API endpoint for Cloud Relay (Phase 4)
		$rest_api = new WP_AI_Context_REST_API();
		add_action( 'rest_api_init', array( $rest_api, 'register_routes' ) );

		// Register Internal Adapters
		add_filter( 'wp_ai_context_adapters', array( $this, 'register_internal_adapters' ) );

		// Expose Pro status to the admin UI
		add_action( 'admin_enqueue_scripts', array( $this, 'localize_pro_status' ) );

		// WP-CLI
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once WP_AI_CONTEXT_PLUGIN_DIR . 'cli/class-wp-ai-context-cli.php';
			WP_CLI::add_command( 'ai-context', 'WP_AI_Context_CLI' );
		}
	}

	/**
	 * Registers our bundled specific adapters.
	 */
	public function register_internal_adapters( $adapters ) {
		$adapters['query-monitor/query-monitor.php'] = 'WP_AI_Context_Query_Monitor_Adapter';
		// Future: add woocommerce, litespeed, etc.
		return $adapters;
	}

	/**
	 * Whether this is the Pro build. Placeholder until Pro is shipped.
	 *
	 * @return bool
	 */
	public static function is_pro() {
		return defined( 'WP_AI_CONTEXT_PRO' ) && WP_AI_CONTEXT_PRO;
	}

	/**
	 * Localize Pro status for the admin React app.
	 */
	public function localize_pro_status() {
		wp_localize_script(
			'wp-ai-context-app',
			'wpAiContextPro',
			array(
				'isPro' => self::is_pro(),
			)
		);
	}

	/**
	 * Helper function to convert PHP memory_limit string to bytes.
	 */
	private function convert_memory_limit_to_bytes( $val ) {
		$val = trim( $val );
		if ( empty( $val ) || $val === '-1' ) {
			return 256 * 1024 * 1024; // Assume 256MB if unlimited or empty
		}

		$last = strtolower( $val[ strlen( $val ) - 1 ] );
		$val  = (int) $val;

		switch ( $last ) {
			case 'g':
				$val *= 1024;
				// no break
			case 'm':
				$val *= 1024;
				// no break
			case 'k':
				$val *= 1024;
		}
		return $val;
	}

	/**
	 * AJAX handler to generate the AI context package.
	 */
	public function generate_context() {
		// Basic security check
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		// Verify Nonce sent by React UI
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wp_ai_context_generate' ) ) {
			wp_send_json_error( 'Invalid security token' );
		}

		$post_args = array(
			'plugins'         => isset( $_POST['plugins'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['plugins'] ) ) : array(),
			'tables'          => isset( $_POST['tables'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['tables'] ) ) : array(),
			'theme_files'     => isset( $_POST['theme_files'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['theme_files'] ) ) : array(),
			'mode'            => isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : 'delta',
			'include_logs'    => isset( $_POST['include_logs'] ) && $_POST['include_logs'] === 'true',
			'include_visuals' => isset( $_POST['include_visuals'] ) && $_POST['include_visuals'] === 'true',
		);

		try {
			// STREAMING STRATEGY: Write directly to a file to save RAM
			$upload_dir  = wp_upload_dir();
			$context_dir = $upload_dir['basedir'] . '/wp-ai-context';
			if ( ! file_exists( $context_dir ) ) {
				wp_mkdir_p( $context_dir );
				file_put_contents( $context_dir . '/index.php', '<?php // Silence is golden.' );
				file_put_contents( $context_dir . '/.htaccess', "Require all denied\nOptions -Indexes\n" );
			}

			// Expire generated context files older than 24 hours to avoid
			// unbounded accumulation of settings/schema data.
			foreach ( (array) glob( $context_dir . '/context-*.md' ) as $old_context_file ) {
				if ( time() - filemtime( $old_context_file ) > DAY_IN_SECONDS ) {
					@unlink( $old_context_file );
				}
			}

			$filename = 'context-' . wp_generate_uuid4() . '.md';
			$filepath = $context_dir . '/' . $filename;
			$file_url = $upload_dir['baseurl'] . '/wp-ai-context/' . $filename;

			$markdown_output = $this->build_context_package( $post_args );

			// Save it physically
			file_put_contents( $filepath, $markdown_output );

			wp_send_json_success(
				array(
					'markdown' => $markdown_output, // For immediate UI
					'file_url' => $file_url,         // For MCP to fetch directly
				)
			);
		} catch ( Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'WP AI Context generation error: ' . $e->getMessage() );
			}
			wp_send_json_error( 'An unexpected error occurred during context generation. Please check the site debug log for details.' );
		}
	}

	/**
	 * AJAX handler to generate a diff from a historical snapshot.
	 */
	public function generate_diff() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wp_ai_context_generate' ) ) {
			wp_send_json_error( 'Invalid security token' );
		}

		$snapshot_id = isset( $_POST['snapshot_id'] ) ? sanitize_file_name( wp_unslash( $_POST['snapshot_id'] ) ) : '';
		if ( empty( $snapshot_id ) ) {
			wp_send_json_error( 'No snapshot ID provided.' );
		}

		$snapshot_engine = new WP_AI_Context_Snapshot();
		$old_context     = $snapshot_engine->get_snapshot_data( $snapshot_id );

		if ( empty( $old_context ) ) {
			wp_send_json_error( 'Snapshot data could not be loaded.' );
		}

		// Generate current context for diffing (no schema, just plugins/settings to match snapshot weight)
		$current_args = array(
			'mode'    => 'delta',
			// Provide all plugins so diff catches anything
			'plugins' => array_keys( ( new WP_AI_Context_Plugin_Collector() )->get_active_plugins() ),
		);

		// Build the raw array (we need to intercept before markdown conversion)
		// To do this properly, we need to extract the raw building logic, but for simplicity
		// we can temporarily hack a return format, or just build it.
		// Actually, let's just generate the current context array using the same logic used in the Snapshot engine.

		$plugin_collector = new WP_AI_Context_Plugin_Collector();
		$active_plugins   = $plugin_collector->get_active_plugins();
		$env_collector    = new WP_AI_Context_Environment_Collector();
		$new_context      = array(
			'environment'      => $env_collector->get_environment(),
			'conflict_surface' => array(),
			'settings'         => array(),
		);
		foreach ( $active_plugins as $slug => $data ) {
			$new_context['conflict_surface'][ $slug ] = array(
				'Name'    => $data['Name'],
				'Version' => $data['Version'],
			);
		}
		$registry = new WP_AI_Context_Adapter_Registry();
		$masker   = new WP_AI_Context_Secret_Masker();
		foreach ( array_keys( $active_plugins ) as $slug ) {
			$adapter = $registry->get_adapter( $slug );
			$raw_settings                     = $adapter->get_settings( $slug );
			$new_context['settings'][ $slug ] = $masker->mask( $raw_settings );
		}
		$optimizer   = new WP_AI_Context_Token_Optimizer();
		$new_context = $optimizer->optimize( $new_context, 'delta' );

		$diff_exporter = new WP_AI_Context_Diff_Exporter();
		$markdown_diff = $diff_exporter->export_diff( $old_context, $new_context );

		wp_send_json_success( array( 'markdown' => $markdown_diff ) );
	}

	/**
	 * AJAX handler to take a manual snapshot.
	 */
	public function take_snapshot() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wp_ai_context_generate' ) ) {
			wp_send_json_error( 'Invalid security token' );
		}

		$snapshot_engine = new WP_AI_Context_Snapshot();
		$filename        = $snapshot_engine->capture_manual_snapshot();

		if ( $filename ) {
			wp_send_json_success( 'Snapshot created successfully.' );
		} else {
			wp_send_json_error( 'Failed to create snapshot.' );
		}
	}

	/**
	 * AJAX handler to push a snapshot to the cloud.
	 */
	public function push_cloud() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wp_ai_context_generate' ) ) {
			wp_send_json_error( 'Invalid security token' );
		}

		$snapshot_id = isset( $_POST['snapshot_id'] ) ? sanitize_file_name( wp_unslash( $_POST['snapshot_id'] ) ) : '';
		if ( empty( $snapshot_id ) ) {
			wp_send_json_error( 'No snapshot ID provided.' );
		}

		$snapshot_engine = new WP_AI_Context_Snapshot();
		$result          = $snapshot_engine->push_to_cloud( $snapshot_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( 'Snapshot pushed to cloud successfully.' );
	}

	/**
	 * AJAX handler to revert a specific setting from a snapshot.
	 */
	public function revert_setting() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wp_ai_context_generate' ) ) {
			wp_send_json_error( 'Invalid security token' );
		}

		$snapshot_id = isset( $_POST['snapshot_id'] ) ? sanitize_file_name( wp_unslash( $_POST['snapshot_id'] ) ) : '';
		$plugin_slug = isset( $_POST['plugin_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin_slug'] ) ) : '';
		$setting_key = isset( $_POST['setting_key'] ) ? sanitize_text_field( wp_unslash( $_POST['setting_key'] ) ) : '';

		if ( empty( $snapshot_id ) || empty( $plugin_slug ) || empty( $setting_key ) ) {
			wp_send_json_error( 'Missing parameters.' );
		}

		$snapshot_engine = new WP_AI_Context_Snapshot();
		$snapshot_data   = $snapshot_engine->get_snapshot_data( $snapshot_id );

		if ( empty( $snapshot_data ) ) {
			wp_send_json_error( 'Snapshot not found.' );
		}

		if ( ! isset( $snapshot_data['settings'][ $plugin_slug ][ $setting_key ] ) ) {
			wp_send_json_error( 'Setting not found in snapshot.' );
		}

		$old_value = $snapshot_data['settings'][ $plugin_slug ][ $setting_key ];

		// Attempt to use adapter if it supports update_setting, otherwise fallback to update_option
		$registry = new WP_AI_Context_Adapter_Registry();
		$adapter  = $registry->get_adapter( $plugin_slug );

		$success = false;
		if ( $adapter ) {
			$success = $adapter->update_setting( $plugin_slug, $setting_key, $old_value );
		} else {
			// Generic fallback: Assume the setting key is the WP option name
			$success = update_option( $setting_key, $old_value );
			// update_option returns false when the value is unchanged; treat a
			// "no change" as success rather than a failure.
			if ( ! $success ) {
				$current = get_option( $setting_key, '__ai_context_sentinel__' );
				if ( $current === $old_value ) {
					$success = true;
				}
			}
		}

		if ( ! $success ) {
			wp_send_json_error( 'Failed to revert the setting. The value may have been changed by another update.' );
		}

		wp_send_json_success( 'Setting reverted successfully.' );
	}

	/**
	 * AJAX handler to approve an AI proposed action.
	 */
	public function approve_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wp_ai_context_generate' ) ) {
			wp_send_json_error( 'Invalid security token' );
		}

		if ( ! self::is_pro() ) {
			wp_send_json_error( 'AI write actions require WP AI Context Pro.' );
		}

		$action_id = isset( $_POST['action_id'] ) ? sanitize_text_field( wp_unslash( $_POST['action_id'] ) ) : '';
		if ( empty( $action_id ) ) {
			wp_send_json_error( 'Missing action ID.' );
		}

		$pending = get_option( 'wp_ai_pending_actions', array() );
		if ( ! isset( $pending[ $action_id ] ) ) {
			wp_send_json_error( 'Action not found or already processed.' );
		}

		$action = $pending[ $action_id ];

		// Handle File Patches
		if ( isset( $action['type'] ) && $action['type'] === 'file_patch' ) {
			$file_path = $action['full_path'];
			$content   = $action['content'];

			// Allow only safe, code-like extensions.
			$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
			if ( ! in_array( $extension, array( 'php', 'css', 'js' ), true ) ) {
				wp_send_json_error( 'Refusing to write a file with a disallowed extension.' );
			}

			$normalized_path = wp_normalize_path( $file_path );
			$is_critical     = ! empty( $action['is_critical'] );

			// Non-critical edits must stay inside wp-content.
			if ( ! $is_critical && strpos( $normalized_path, wp_normalize_path( WP_CONTENT_DIR ) ) !== 0 ) {
				wp_send_json_error( 'Refusing to write a file outside wp-content.' );
			}

			// Never allow executable PHP into the web-served uploads directory.
			if ( 'php' === $extension && strpos( $normalized_path, wp_normalize_path( WP_CONTENT_DIR . '/uploads' ) ) === 0 ) {
				wp_send_json_error( 'Refusing to write executable PHP into the uploads directory.' );
			}

			// Ensure directory exists
			$dir = dirname( $file_path );
			if ( ! file_exists( $dir ) ) {
				wp_mkdir_p( $dir );
			}

			// Write file
			$bytes = file_put_contents( $file_path, $content );
			if ( $bytes === false ) {
				wp_send_json_error( 'Failed to write file to disk. Check permissions.' );
			}

			// Remove from pending
			unset( $pending[ $action_id ] );
			update_option( 'wp_ai_pending_actions', $pending, false );

			wp_send_json_success( 'File patch applied successfully.' );
		}

		// Handle Settings Changes
		$plugin_slug = $action['plugin_slug'];
		$changes     = $action['changes'];

		// OPTIMIZATION: Auto-Snapshot before applying AI changes (Rollback safety)
		$snapshot_engine   = new WP_AI_Context_Snapshot();
		$snapshot_filename = $snapshot_engine->capture_manual_snapshot();

		$registry = new WP_AI_Context_Adapter_Registry();
		$adapter  = $registry->get_adapter( $plugin_slug );

		global $wpdb;
		// START STRATEGY 5: DB Transaction for safety
		$wpdb->query( 'START TRANSACTION' );

		foreach ( $changes as $change ) {
			$setting_key = $change['key'];
			$new_value   = $change['value'];

			if ( $adapter ) {
				$adapter->update_setting( $plugin_slug, $setting_key, $new_value );
			} else {
				update_option( $setting_key, $new_value );
			}
		}

		// Self-Healing Validation (Guard Rail against WSOD)
		$test_request = wp_remote_get(
			home_url(),
			array(
				'timeout' => 5,
			)
		);

		if ( is_wp_error( $test_request ) || wp_remote_retrieve_response_code( $test_request ) >= 500 ) {
			// FATAL ERROR DETECTED: Immediately rollback database changes!
			$wpdb->query( 'ROLLBACK' );
			wp_cache_flush(); // Issue 1 Fix: Clear Redis/Memcached of the broken values
			wp_send_json_error( 'Action failed! The proposed change caused a fatal error on the frontend. The system has automatically rolled back the database and cleared cache to protect your site.' );
		} else {
			// All good, commit to DB.
			$wpdb->query( 'COMMIT' );
		}

		// Remove from pending
		unset( $pending[ $action_id ] );
		update_option( 'wp_ai_pending_actions', $pending, false );

		wp_send_json_success( 'Action approved and executed. A safety snapshot (' . $snapshot_filename . ') was taken before execution.' );
	}

	/**
	 * AJAX handler to reject an AI proposed action.
	 */
	public function reject_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wp_ai_context_generate' ) ) {
			wp_send_json_error( 'Invalid security token' );
		}

		if ( ! self::is_pro() ) {
			wp_send_json_error( 'AI write actions require WP AI Context Pro.' );
		}

		$action_id = isset( $_POST['action_id'] ) ? sanitize_text_field( wp_unslash( $_POST['action_id'] ) ) : '';
		if ( empty( $action_id ) ) {
			wp_send_json_error( 'Missing action ID.' );
		}

		$pending = get_option( 'wp_ai_pending_actions', array() );
		if ( isset( $pending[ $action_id ] ) ) {
			unset( $pending[ $action_id ] );
			update_option( 'wp_ai_pending_actions', $pending, false );
		}

		wp_send_json_success( 'Action rejected.' );
	}

	/**
	 * AJAX handler to enable Safe Mode Preview for an action.
	 */
	public function preview_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wp_ai_context_generate' ) ) {
			wp_send_json_error( 'Invalid security token' );
		}

		if ( ! self::is_pro() ) {
			wp_send_json_error( 'AI write actions require WP AI Context Pro.' );
		}

		$action_id = isset( $_POST['action_id'] ) ? sanitize_text_field( wp_unslash( $_POST['action_id'] ) ) : '';
		if ( empty( $action_id ) ) {
			wp_send_json_error( 'Missing action ID.' );
		}

		$user_id = get_current_user_id();
		set_transient( "wp_ai_preview_{$user_id}", $action_id, HOUR_IN_SECONDS );

		wp_send_json_success( home_url( '/?ai_preview=true' ) );
	}

	/**
	 * AJAX handler to generate a WP Playground Blueprint.
	 */
	public function export_playground() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wp_ai_context_generate' ) ) {
			wp_send_json_error( 'Invalid security token' );
		}

		// STRATEGY 2 / Issue 4 Fix: Realistic Playground Blueprint
		// Dynamically generate the blueprint steps based on active context
		$plugins_to_install = array();
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$active_plugins = get_option( 'active_plugins', array() );
		foreach ( $active_plugins as $plugin_file ) {
			$slug = dirname( $plugin_file );
			if ( $slug !== '.' && $slug !== WP_AI_CONTEXT_SLUG && $slug !== WP_AI_CONTEXT_SLUG_PREVIOUS ) {
				$plugins_to_install[] = array(
					'step'       => 'installPlugin',
					'pluginData' => array(
						'resource' => 'wordpress.org/plugins',
						'slug'     => $slug,
					),
					'options'    => array( 'activate' => true ),
				);
			}
		}

		$steps = array_merge(
			array(
				array(
					'step'     => 'login',
					'username' => 'admin',
					'password' => 'password',
				),
			),
			$plugins_to_install
		);

		$blueprint = array(
			'landingPage'       => '/wp-admin/',
			'preferredVersions' => array(
				'php' => '8.0',
				'wp'  => 'latest',
			),
			'steps'             => $steps,
		);

		// Base64 encode the blueprint JSON to pass directly via URL
		$blueprint_json    = wp_json_encode( $blueprint );
		$blueprint_encoded = rawurlencode( $blueprint_json );

		wp_send_json_success(
			array(
				'blueprint' => $blueprint_json,
				'url'       => 'https://playground.wordpress.net/?blueprint=' . $blueprint_encoded,
			)
		);
	}

	/**
	 * AJAX handler to dynamically generate an Application Password for the AI.
	 */
	public function generate_app_password() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wp_ai_context_generate' ) ) {
			wp_send_json_error( 'Invalid security token' );
		}

		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			wp_send_json_error( 'Application Passwords are not supported on this WordPress version.' );
		}

		$user_id  = get_current_user_id();
		$app_name = 'WP AI Context - ' . gmdate( 'Y-m-d H:i:s' );

		list( $new_password, $new_item ) = WP_Application_Passwords::create_new_application_password(
			$user_id,
			array(
				'name' => $app_name,
			)
		);

		if ( is_wp_error( $new_password ) ) {
			wp_send_json_error( $new_password->get_error_message() );
		}

		wp_send_json_success(
			array(
				'password' => $new_password,
				'username' => wp_get_current_user()->user_login,
			)
		);
	}

	/**
	 * Builds the core context data package.
	 * Decoupled from HTTP responses so CLI and REST APIs can use it.
	 *
	 * @param array $args Extraction arguments.
	 * @return string The rendered markdown context.
	 */
	public function build_context_package( $args ) {
		// RESOURCE GUARD: Track start time and memory
		$start_time       = microtime( true );
		$memory_limit     = ini_get( 'memory_limit' );
		$max_memory_bytes = $this->convert_memory_limit_to_bytes( $memory_limit );
		// Aim to stop if we hit 80% of allowed memory to prevent Fatal Errors
		$safe_memory_threshold = $max_memory_bytes > 0 ? ( $max_memory_bytes * 0.8 ) : ( 128 * 1024 * 1024 );

		$defaults = array(
			'plugins'         => array(),
			'tables'          => array(),
			'theme_files'     => array(),
			'mode'            => 'delta',
			'include_logs'    => false,
			'include_visuals' => false,
		);
		$args     = wp_parse_args( $args, $defaults );

		$plugin_collector = new WP_AI_Context_Plugin_Collector();
		$active_plugins   = $plugin_collector->get_active_plugins();

		// Intercept Presets
		if ( ! empty( $args['preset'] ) ) {
			$preset_engine = new WP_AI_Context_Presets();
			$preset_args   = $preset_engine->resolve_preset( sanitize_text_field( $args['preset'] ), $active_plugins );
			// Merge the preset args over the request args, so presets act as defaults
			$args = array_merge( $args, $preset_args );
		}

		$selected_plugins      = isset( $args['plugins'] ) ? (array) $args['plugins'] : array();
		$requested_tables      = isset( $args['tables'] ) ? (array) $args['tables'] : array();
		$requested_prefixes    = isset( $args['table_prefixes'] ) ? (array) $args['table_prefixes'] : array();
		$requested_theme_files = isset( $args['theme_files'] ) ? (array) $args['theme_files'] : array();
		$requested_overrides   = isset( $args['theme_overrides'] ) ? (array) $args['theme_overrides'] : array();
		$export_mode           = isset( $args['mode'] ) ? sanitize_text_field( wp_unslash( $args['mode'] ) ) : 'delta';

		// 1. Collect Environment, Logs, Schema, and Theme
		$env_collector    = new WP_AI_Context_Environment_Collector();
		$log_collector    = new WP_AI_Context_Log_Collector();
		$schema_collector = new WP_AI_Context_Schema_Collector();
		$theme_collector  = new WP_AI_Context_Theme_Collector();
		$visual_collector = new WP_AI_Context_Visual_Collector();

		$context = array(
			'environment'      => $env_collector->get_environment(),
			'schema'           => $schema_collector->get_schema( $requested_tables, $requested_prefixes ),
			'theme'            => $theme_collector->get_theme_data( $requested_theme_files, $requested_overrides ),
			'debug_logs'       => $log_collector->get_logs(),
			'conflict_surface' => array(),
			'focused_plugins'  => array(),
			'settings'         => array(),
		);

		// RESOURCE GUARD: Check memory before moving to heavy logs/settings
		if ( memory_get_usage() > $safe_memory_threshold ) {
			$context['resource_warning'] = 'WARNING: Context generation was halted early to prevent the server from running out of memory. Some data (like logs or settings) may be truncated.';
			$exporter = new WP_AI_Context_Markdown_Exporter();
			return $exporter->export( $context );
		}

		if ( ! empty( $args['include_visuals'] ) ) {
			$context['visuals'] = $visual_collector->get_visual_context();
		}

		// 2. Collect Plugins for Conflict Surface
		// Always provide a lightweight list of ALL active plugins to detect conflicts
		foreach ( $active_plugins as $slug => $data ) {
			$context['conflict_surface'][ $slug ] = array(
				'Name'    => $data['Name'],
				'Version' => $data['Version'],
			);
		}

		// 3. Process Selected Plugins (Deep Dive)
		$registry = new WP_AI_Context_Adapter_Registry();
		$masker   = new WP_AI_Context_Secret_Masker();

		foreach ( $selected_plugins as $slug ) {
			if ( isset( $active_plugins[ $slug ] ) ) {
				$context['focused_plugins'][ $slug ] = $active_plugins[ $slug ];
			}

			// Resolve the specific adapter for this plugin
			$adapter = $registry->get_adapter( $slug );
			$raw_settings    = $adapter->get_settings( $slug );
			$masked_settings = $masker->mask( $raw_settings );

			if ( ! empty( $masked_settings ) ) {
				$context['settings'][ $slug ] = $masked_settings;
			}
		}

		// 4. Optimize Tokens (Compression)
		$optimizer = new WP_AI_Context_Token_Optimizer();
		$context   = $optimizer->optimize( $context, $export_mode );

		// 5. Export to Markdown
		$exporter = new WP_AI_Context_Markdown_Exporter();
		return $exporter->export( $context );
	}

	/**
	 * AJAX handler to manually teach the adapter a missed option.
	 */
	public function learn_option() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wp_ai_context_generate' ) ) {
			wp_send_json_error( 'Invalid security token' );
		}

		$plugin_slug = isset( $_POST['plugin_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin_slug'] ) ) : '';
		$option_name = isset( $_POST['option_name'] ) ? sanitize_text_field( wp_unslash( $_POST['option_name'] ) ) : '';

		if ( empty( $plugin_slug ) || empty( $option_name ) ) {
			wp_send_json_error( 'Missing parameters' );
		}

		$learned_options = get_option( 'wp_ai_context_learned_options', array() );

		if ( ! isset( $learned_options[ $plugin_slug ] ) ) {
			$learned_options[ $plugin_slug ] = array();
		}

		if ( ! in_array( $option_name, $learned_options[ $plugin_slug ], true ) ) {
			$learned_options[ $plugin_slug ][] = $option_name;
			update_option( 'wp_ai_context_learned_options', $learned_options );
			wp_send_json_success( 'Option learned successfully.' );
		}

		wp_send_json_success( 'Option already learned.' );
	}
}
