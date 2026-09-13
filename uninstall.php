<?php
/**
 * Uninstall routine for AI Context Engine.
 *
 * Removes all options, transients, and generated files created by the plugin.
 */

// If uninstall is not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$wp_ai_context_options = array(
	'wp_ai_pending_actions',
	'wp_ai_context_learned_options',
	'wp_ai_context_api_key',
	'wp_ai_context_cloud_endpoint',
);

$wp_ai_context_transients = array(
	'wp_ai_context_qm_history',
	'wp_ai_context_qm_last_capture',
);

/**
 * Deletes the plugin's upload directories and their contents.
 */
function wp_ai_context_delete_upload_dir( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return;
	}

	$files = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $files as $file ) {
		if ( $file->isDir() ) {
			@rmdir( $file->getPathname() );
		} else {
			@unlink( $file->getPathname() );
		}
	}

	@rmdir( $dir );
}

/**
 * Performs cleanup for a single site.
 */
function wp_ai_context_cleanup_site() {
	global $wpdb;

	foreach ( array( 'wp_ai_pending_actions', 'wp_ai_context_learned_options', 'wp_ai_context_api_key', 'wp_ai_context_cloud_endpoint' ) as $option ) {
		delete_option( $option );
	}

	delete_transient( 'wp_ai_context_qm_history' );
	delete_transient( 'wp_ai_context_qm_last_capture' );

	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( 'wp_ai_preview_' ) . '%'
		)
	);

	$upload_dir = wp_upload_dir();
	wp_ai_context_delete_upload_dir( trailingslashit( $upload_dir['basedir'] ) . 'wp-ai-context' );
	wp_ai_context_delete_upload_dir( trailingslashit( $upload_dir['basedir'] ) . 'wp-ai-context-snapshots' );
}

if ( is_multisite() ) {
	$sites = get_sites( array( 'fields' => 'ids' ) );
	foreach ( $sites as $site_id ) {
		switch_to_blog( $site_id );
		wp_ai_context_cleanup_site();
		restore_current_blog();
	}
} else {
	wp_ai_context_cleanup_site();
}

// Unused variable kept for clarity (option list documented above).
unset( $wp_ai_context_options, $wp_ai_context_transients );
