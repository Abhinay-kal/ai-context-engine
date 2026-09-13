<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * REST API Endpoint for Context Generation
 * Allows remote AI agents to securely fetch context via Application Passwords.
 */
class WP_AI_Context_REST_API {

	/**
	 * Register the REST routes.
	 */
	public function register_routes() {
		register_rest_route(
			'wp-ai-context/v1',
			'/generate',
			array(
				'methods'             => WP_REST_Server::CREATABLE, // POST request
				'callback'            => array( $this, 'generate_context_callback' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// Phase 5: AI Proposes a fix
		register_rest_route(
			'wp-ai-context/v1',
			'/propose-action',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'propose_action_callback' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// Issue 3 Fix: File Patching API
		register_rest_route(
			'wp-ai-context/v1',
			'/patch-file',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'patch_file_callback' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// Phase 4: Cloud Relay OpenAPI Schema
		register_rest_route(
			'wp-ai-context/v1',
			'/openapi.json',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_openapi_schema' ),
				'permission_callback' => '__return_true', // OpenAPI schema is public to allow ChatGPT to read the definition
			)
		);
	}

	/**
	 * Permission callback: strictly limit to administrators.
	 *
	 * @return bool|WP_Error
	 */
	public function check_permissions( $request ) {
		// SECURITY FIX: Only allow users with administrator-level permissions.
		// Remote AI agents must use Application Passwords tied to an admin account.
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		// Optional secret-key auth (x-api-key header or "Authorization: Bearer ..."),
		// matching the key configured on the admin page.
		$api_key = get_option( 'wp_ai_context_api_key', '' );
		if ( ! empty( $api_key ) ) {
			$header_key = $request->get_header( 'x-api-key' );
			if ( empty( $header_key ) ) {
				$auth = $request->get_header( 'authorization' );
				if ( $auth && preg_match( '/^Bearer\s+(.+)$/i', $auth, $matches ) ) {
					$header_key = $matches[1];
				}
			}
			if ( ! empty( $header_key ) && hash_equals( $api_key, $header_key ) ) {
				return true;
			}
		}

		return new WP_Error(
			'rest_forbidden',
			esc_html__( 'You do not have permission to generate AI context. Ensure you are authenticating with an Administrator account.', 'wp-ai-context' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Handle the API request to generate context.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function generate_context_callback( WP_REST_Request $request ) {
		// Cap execution time instead of disabling it entirely. Memory is guarded
		// by the 80%-threshold check inside build_context_package().
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 60 );
		}

		// OPTIMIZATION: Clear SAVEQUERIES to prevent memory leaks during DESCRIBE loops
		global $wpdb;
		if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES ) {
			$wpdb->queries = array();
		}

		try {
			$args = array(
				'plugins'     => $request->get_param( 'plugins' ) ? (array) $request->get_param( 'plugins' ) : array(),
				'tables'      => $request->get_param( 'tables' ) ? (array) $request->get_param( 'tables' ) : array(),
				'theme_files' => $request->get_param( 'theme_files' ) ? (array) $request->get_param( 'theme_files' ) : array(),
				'mode'        => $request->get_param( 'mode' ) ? sanitize_text_field( $request->get_param( 'mode' ) ) : 'delta',
			);

			$wp_ai_context   = new WP_AI_Context();
			$markdown_output = $wp_ai_context->build_context_package( $args );

			// Return as a proper WP_REST_Response
			$response = new WP_REST_Response( array( 'markdown' => $markdown_output ) );
			$response->set_status( 200 );

			return $response;

		} catch ( Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'WP AI Context REST generation error: ' . $e->getMessage() );
			}
			return new WP_Error(
				'context_generation_failed',
				'An unexpected error occurred during context generation.',
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Handle AI agent proposing an action to fix the site.
	 */
	public function propose_action_callback( WP_REST_Request $request ) {
		if ( ! WP_AI_Context::is_pro() ) {
			return new WP_REST_Response( array( 'error' => 'AI Write capabilities require WP AI Context Pro. Please upgrade to allow the AI to change settings automatically.' ), 403 );
		}

		$plugin_slug = sanitize_text_field( $request->get_param( 'plugin_slug' ) );
		$reason      = sanitize_textarea_field( $request->get_param( 'reason' ) );
		$changes     = $request->get_param( 'changes' ); // Expects array: [ { key: '...', value: '...' }, ... ]

		if ( empty( $plugin_slug ) || empty( $changes ) || ! is_array( $changes ) ) {
			return new WP_Error( 'missing_params', 'plugin_slug and a changes array are required.', array( 'status' => 400 ) );
		}

		// SECURITY OPTIMIZATION: Prevent AI from touching critical core WP options
		$protected_keys = array( 'siteurl', 'home', 'users_can_register', 'admin_email', 'permalink_structure' );
		foreach ( $changes as $change ) {
			if ( in_array( $change['key'], $protected_keys, true ) ) {
				return new WP_Error( 'security_violation', 'AI is not permitted to modify critical core setting: ' . $change['key'], array( 'status' => 403 ) );
			}
		}

		$pending   = get_option( 'wp_ai_pending_actions', array() );
		$action_id = uniqid( 'action_' );

		$pending[ $action_id ] = array(
			'id'          => $action_id,
			'plugin_slug' => $plugin_slug,
			'changes'     => $changes, // Batched settings to save tokens
			'reason'      => $reason,
			'date'        => current_time( 'mysql' ),
		);

		update_option( 'wp_ai_pending_actions', $pending, false );

		return new WP_REST_Response(
			array(
				'success'   => true,
				'action_id' => $action_id,
				'message'   => 'Action proposed successfully and is awaiting human approval.',
			),
			201
		);
	}

	/**
	 * Handler for /wp-json/wp-ai-context/v1/patch-file
	 * Allows AI to submit PHP/CSS/JS file edits safely via Sandbox.
	 */
	public function patch_file_callback( WP_REST_Request $request ) {
		if ( ! WP_AI_Context::is_pro() ) {
			return new WP_REST_Response( array( 'error' => 'AI Write capabilities require WP AI Context Pro. Please upgrade to allow the AI to fix files automatically.' ), 403 );
		}

		$params = $request->get_json_params();

		if ( empty( $params['file_path'] ) || empty( $params['content'] ) ) {
			return new WP_Error( 'missing_params', 'file_path and content are required', array( 'status' => 400 ) );
		}

		$file_path = sanitize_text_field( $params['file_path'] );
		$content   = $params['content']; // Raw code content
		$reason    = sanitize_text_field( $params['reason'] ?? 'AI proposed file patch' );

		if ( strpos( $file_path, '..' ) !== false ) {
			return new WP_Error( 'invalid_path', 'Directory traversal is forbidden.', array( 'status' => 403 ) );
		}

		// Identify if this is a critical server file
		$critical_files = array( '.htaccess', 'php.ini', '.user.ini', 'web.config', 'nginx.conf' );
		$is_critical    = false;

		foreach ( $critical_files as $critical ) {
			if ( strpos( $file_path, $critical ) !== false ) {
				$is_critical = true;
				break;
			}
		}

		// Security: Must be inside wp-content OR be a critical file in ABSPATH
		$target_full_path = ABSPATH . ltrim( $file_path, '/' ); // Base it from ABSPATH now

		if ( ! $is_critical && strpos( $target_full_path, WP_CONTENT_DIR ) !== 0 ) {
			return new WP_Error( 'out_of_bounds', 'Standard files can only be edited inside wp-content. Core modifications are forbidden.', array( 'status' => 403 ) );
		}

		// Save as a pending file patch action
		$pending   = get_option( 'wp_ai_pending_actions', array() );
		$action_id = wp_generate_uuid4();

		$pending[ $action_id ] = array(
			'id'          => $action_id,
			'type'        => 'file_patch',
			'file_path'   => $file_path,
			'full_path'   => $target_full_path,
			'content'     => $content, // Base64 encoded or raw text
			'reason'      => $reason,
			'is_critical' => $is_critical,
			'date'        => current_time( 'mysql' ),
		);

		update_option( 'wp_ai_pending_actions', $pending, false );

		return new WP_REST_Response(
			array(
				'success'   => true,
				'action_id' => $action_id,
			),
			200
		);
	}

	/**
	 * Handler for /wp-json/wp-ai-context/v1/openapi.json
	 * Returns the OpenAPI schema for Custom GPT integrations.
	 */
	public function get_openapi_schema() {
		$generator = new WP_AI_Context_OpenAPI_Generator();
		$schema    = $generator->get_schema();
		return new WP_REST_Response( $schema, 200 );
	}
}
