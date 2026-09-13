<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * WP-CLI Interface
 * Allows power users to generate AI context via the terminal.
 */
class WP_AI_Context_CLI {

	/**
	 * Generates the AI context markdown package.
	 *
	 * ## OPTIONS
	 *
	 * [--plugins=<plugins>]
	 * : Comma-separated list of plugin slugs to deep dive into.
	 *
	 * [--tables=<tables>]
	 * : Comma-separated list of custom database tables to describe.
	 *
	 * [--theme_files=<theme_files>]
	 * : Comma-separated list of theme files to extract.
	 *
	 * [--mode=<mode>]
	 * : Export mode: 'delta' (optimized) or 'full'. Default: delta.
	 *
	 * [--output=<output>]
	 * : Path to save the markdown file (e.g. context.md).
	 *
	 * ## EXAMPLES
	 *
	 *     wp ai-context generate --plugins=woocommerce,elementor --output=context.md
	 *
	 * @when after_wp_load
	 */
	public function generate( $args, $assoc_args ) {
		WP_CLI::line( 'Generating AI Context Package...' );

		// Allow long CLI runs (e.g. large schema scans) but keep a hard ceiling
		// so a runaway loop cannot hang the process forever.
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 );
		}

		// OPTIMIZATION: If SAVEQUERIES is active on a dev site, it stores every SQL query in memory.
		// We clear it here to prevent the CLI from blowing up memory during the Schema DESCRIBE loops.
		global $wpdb;
		if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES ) {
			$wpdb->queries = array();
		}

		$request_args = array();

		if ( ! empty( $assoc_args['plugins'] ) ) {
			$request_args['plugins'] = array_map( 'trim', explode( ',', $assoc_args['plugins'] ) );
		}
		if ( ! empty( $assoc_args['tables'] ) ) {
			$request_args['tables'] = array_map( 'trim', explode( ',', $assoc_args['tables'] ) );
		}
		if ( ! empty( $assoc_args['theme_files'] ) ) {
			$request_args['theme_files'] = array_map( 'trim', explode( ',', $assoc_args['theme_files'] ) );
		}
		if ( ! empty( $assoc_args['mode'] ) ) {
			$request_args['mode'] = $assoc_args['mode'];
		} else {
			$request_args['mode'] = 'delta';
		}

		try {
			// Access the core plugin class to build the context
			// We instantiate it here because WP-CLI might execute before the standard plugin run()
			$wp_ai_context = new WP_AI_Context();
			$markdown      = $wp_ai_context->build_context_package( $request_args );

			if ( ! empty( $assoc_args['output'] ) ) {
				$file_path = $assoc_args['output'];
				$result    = file_put_contents( $file_path, $markdown );

				if ( $result !== false ) {
					WP_CLI::success( "Context package saved successfully to: {$file_path}" );
				} else {
					WP_CLI::error( "Failed to save file to: {$file_path}" );
				}
			} else {
				// Output directly to terminal
				WP_CLI::line( "\n" . $markdown . "\n" );
				WP_CLI::success( 'Context generated successfully.' );
			}
		} catch ( Exception $e ) {
			WP_CLI::error( 'Failed to generate context: ' . $e->getMessage() );
		}
	}
}
