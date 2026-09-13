<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Generates an OpenAPI (Swagger) v3 schema for the WP AI Context API.
 * This allows ChatGPT Web and other Cloud SaaS apps to connect instantly.
 */
class WP_AI_Context_OpenAPI_Generator {

	public function get_schema() {
		$site_url = get_rest_url( null, 'wp-ai-context/v1' );

		return array(
			'openapi'    => '3.1.0',
			'info'       => array(
				'title'       => 'WP AI Context - Cloud Relay API',
				'description' => 'Allows AI Agents (like ChatGPT) to securely read context and propose fixes to this WordPress site.',
				'version'     => '1.0.0',
			),
			'servers'    => array(
				array(
					'url'         => $site_url,
					'description' => 'WordPress Site API',
				),
			),
			'paths'      => array(
				'/generate'       => array(
					'post' => array(
						'summary'     => 'Generate Site Context',
						'description' => 'Generates the complete Markdown context of the site (environment, active plugins, schema, theme, logs).',
						'operationId' => 'generateContext',
						'requestBody' => array(
							'required' => false,
							'content'  => array(
								'application/json' => array(
									'schema' => array(
										'type'       => 'object',
										'properties' => array(
											'plugins'     => array(
												'type'  => 'array',
												'items' => array( 'type' => 'string' ),
											),
											'tables'      => array(
												'type'  => 'array',
												'items' => array( 'type' => 'string' ),
											),
											'theme_files' => array(
												'type'  => 'array',
												'items' => array( 'type' => 'string' ),
											),
											'mode'        => array(
												'type'    => 'string',
												'enum'    => array( 'delta', 'full' ),
												'default' => 'delta',
											),
										),
									),
								),
							),
						),
						'responses'   => array(
							'200' => array(
								'description' => 'Successful operation',
								'content'     => array(
									'application/json' => array(
										'schema' => array(
											'type'       => 'object',
											'properties' => array(
												'markdown' => array( 'type' => 'string' ),
												'file_url' => array( 'type' => 'string' ),
											),
										),
									),
								),
							),
						),
					),
				),
				'/propose-action' => array(
					'post' => array(
						'summary'     => 'Propose Settings Patch',
						'description' => 'Propose a change to WordPress options or plugin settings. Requires human approval.',
						'operationId' => 'proposeAction',
						'requestBody' => array(
							'required' => true,
							'content'  => array(
								'application/json' => array(
									'schema' => array(
										'type'       => 'object',
										'properties' => array(
											'plugin_slug' => array( 'type' => 'string' ),
											'reason'      => array( 'type' => 'string' ),
											'changes'     => array(
												'type'  => 'array',
												'items' => array(
													'type' => 'object',
													'properties' => array(
														'key' => array( 'type' => 'string' ),
														'value' => array( 'type' => 'string' ),
													),
												),
											),
										),
									),
								),
							),
						),
						'responses'   => array(
							'201' => array(
								'description' => 'Action proposed successfully',
							),
						),
					),
				),
				'/patch-file'     => array(
					'post' => array(
						'summary'     => 'Propose File Patch',
						'description' => 'Propose a raw PHP/CSS/JS file edit. Requires human approval.',
						'operationId' => 'patchFile',
						'requestBody' => array(
							'required' => true,
							'content'  => array(
								'application/json' => array(
									'schema' => array(
										'type'       => 'object',
										'properties' => array(
											'file_path' => array(
												'type' => 'string',
												'description' => 'Path relative to ABSPATH',
											),
											'content'   => array(
												'type' => 'string',
												'description' => 'New file content',
											),
											'reason'    => array( 'type' => 'string' ),
										),
									),
								),
							),
						),
						'responses'   => array(
							'200' => array(
								'description' => 'File patch proposed successfully',
							),
						),
					),
				),
			),
			'components' => array(
				'securitySchemes' => array(
					'BasicAuth' => array(
						'type'        => 'http',
						'scheme'      => 'basic',
						'description' => 'Use your WordPress Username and an Application Password.',
					),
				),
			),
			'security'   => array(
				array( 'BasicAuth' => array() ),
			),
		);
	}
}
