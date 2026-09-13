<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Automated Snapshot Engine
 * Captures and stores state context before updates occur.
 */
class WP_AI_Context_Snapshot {

	private $storage_dir;

	public function __construct() {
		$upload_dir        = wp_upload_dir();
		$this->storage_dir = trailingslashit( $upload_dir['basedir'] ) . 'wp-ai-context-snapshots';

		// Hook before a plugin/theme update downloads the package
		add_filter( 'upgrader_pre_download', array( $this, 'capture_pre_update_snapshot' ), 10, 3 );
	}

	/**
	 * Ensures the protected storage directory exists.
	 */
	private function ensure_storage_directory() {
		if ( ! is_dir( $this->storage_dir ) ) {
			wp_mkdir_p( $this->storage_dir );

			// Protect directory from web access
			file_put_contents( $this->storage_dir . '/.htaccess', "Require all denied\nOptions -Indexes\n" );
			file_put_contents( $this->storage_dir . '/index.html', '' );
			file_put_contents( $this->storage_dir . '/index.php', '<?php // Silence is golden.' );
		}
	}

	/**
	 * Captures a lightweight snapshot right before an update occurs.
	 *
	 * @param bool   $reply   Whether to bail without returning the package.
	 * @param string $package The package file name.
	 * @param object $upgrader The Upgrader instance.
	 * @return bool
	 */
	public function capture_pre_update_snapshot( $reply, $package, $upgrader ) {
		// Only run once per request to avoid multiple snapshots during bulk updates
		static $snapshot_taken = false;
		if ( $snapshot_taken ) {
			return $reply;
		}

		$snapshot_taken = true;

		// We need to build a lightweight context package (skip schema/theme files to save DB space)
		$plugin_collector = new WP_AI_Context_Plugin_Collector();
		$active_plugins   = $plugin_collector->get_active_plugins();

		$env_collector = new WP_AI_Context_Environment_Collector();

		$context = array(
			'environment'      => $env_collector->get_environment(),
			'conflict_surface' => array(),
			'settings'         => array(),
		);

		// Store plugin versions
		foreach ( $active_plugins as $slug => $data ) {
			$context['conflict_surface'][ $slug ] = array(
				'Name'    => $data['Name'],
				'Version' => $data['Version'],
			);
		}

		// Store settings (only non-default)
		$registry = new WP_AI_Context_Adapter_Registry();
		$masker   = new WP_AI_Context_Secret_Masker();
		foreach ( array_keys( $active_plugins ) as $slug ) {
			$adapter = $registry->get_adapter( $slug );
			if ( $adapter instanceof WP_AI_Context_Adapter_Interface ) {
				$raw_settings    = $adapter->get_settings( $slug );
				$masked_settings = $masker->mask( $raw_settings );
				if ( ! empty( $masked_settings ) ) {
					$context['settings'][ $slug ] = $masked_settings;
				}
			}
		}

		// Compress
		$optimizer = new WP_AI_Context_Token_Optimizer();
		$context   = $optimizer->optimize( $context, 'delta' );

		// Save to Filesystem
		$this->save_snapshot( 'Pre-Update Snapshot', $context );

		return $reply;
	}

	/**
	 * Captures a snapshot manually (triggered by user).
	 */
	public function capture_manual_snapshot() {
		$plugin_collector = new WP_AI_Context_Plugin_Collector();
		$active_plugins   = $plugin_collector->get_active_plugins();
		$env_collector    = new WP_AI_Context_Environment_Collector();

		$context = array(
			'environment'      => $env_collector->get_environment(),
			'conflict_surface' => array(),
			'settings'         => array(),
		);

		foreach ( $active_plugins as $slug => $data ) {
			$context['conflict_surface'][ $slug ] = array(
				'Name'    => $data['Name'],
				'Version' => $data['Version'],
			);
		}

		$registry = new WP_AI_Context_Adapter_Registry();
		$masker   = new WP_AI_Context_Secret_Masker();
		foreach ( array_keys( $active_plugins ) as $slug ) {
			$adapter = $registry->get_adapter( $slug );
			if ( $adapter instanceof WP_AI_Context_Adapter_Interface ) {
				$raw_settings    = $adapter->get_settings( $slug );
				$masked_settings = $masker->mask( $raw_settings );
				if ( ! empty( $masked_settings ) ) {
					$context['settings'][ $slug ] = $masked_settings;
				}
			}
		}

		$optimizer = new WP_AI_Context_Token_Optimizer();
		$context   = $optimizer->optimize( $context, 'delta' );

		return $this->save_snapshot( 'Manual Snapshot', $context );
	}

	/**
	 * Saves a snapshot array to the secure filesystem.
	 */
	public function save_snapshot( $title, $context_array ) {
		$this->ensure_storage_directory();

		$timestamp = time();
		$filename  = 'snapshot-' . $timestamp . '.json';
		$filepath  = $this->storage_dir . '/' . $filename;

		$data = array(
			'id'      => $filename,
			'title'   => sanitize_text_field( $title ),
			'date'    => current_time( 'mysql' ),
			'context' => $context_array,
		);

		file_put_contents( $filepath, wp_json_encode( $data ) );

		$this->cleanup_old_snapshots();

		return $filename;
	}

	/**
	 * Pushes a snapshot to a configured external cloud endpoint.
	 */
	public function push_to_cloud( $file_id ) {
		$endpoint = get_option( 'wp_ai_context_cloud_endpoint', '' );
		if ( empty( $endpoint ) ) {
			return new WP_Error( 'no_endpoint', 'No cloud endpoint configured.' );
		}

		$data = $this->get_snapshot_data( $file_id );
		if ( empty( $data ) ) {
			return new WP_Error( 'not_found', 'Snapshot not found.' );
		}

		$response = wp_remote_post(
			$endpoint,
			array(
				'body'    => wp_json_encode(
					array(
						'snapshot_id' => $file_id,
						'data'        => $data,
					)
				),
				'headers' => array( 'Content-Type' => 'application/json' ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;
	}

	/**
	 * Deletes snapshots older than the 10 most recent.
	 */
	private function cleanup_old_snapshots() {
		$files = glob( $this->storage_dir . '/snapshot-*.json' );
		if ( ! $files ) {
			return;
		}

		// Sort files by modified time (oldest first)
		usort(
			$files,
			function ( $a, $b ) {
				return filemtime( $a ) - filemtime( $b );
			}
		);

		if ( count( $files ) > 10 ) {
			$to_delete = array_slice( $files, 0, count( $files ) - 10 );
			foreach ( $to_delete as $file ) {
				wp_delete_file( $file );
			}
		}
	}

	/**
	 * Retrieves a list of available snapshots for the UI.
	 */
	public function get_snapshot_list() {
		$this->ensure_storage_directory();

		$files = glob( $this->storage_dir . '/snapshot-*.json' );
		if ( ! $files ) {
			return array();
		}

		// Sort newest first
		usort(
			$files,
			function ( $a, $b ) {
				return filemtime( $b ) - filemtime( $a );
			}
		);

		$list = array();
		foreach ( $files as $file ) {
			$data = json_decode( file_get_contents( $file ), true );
			if ( $data ) {
				$list[] = array(
					'id'    => basename( $file ), // e.g. snapshot-12345.json
					'title' => $data['title'] ?? 'Snapshot',
					'date'  => $data['date'] ?? gmdate( 'Y-m-d H:i:s', filemtime( $file ) ),
				);
			}
		}

		return $list;
	}

	/**
	 * Retrieves the decoded array for a specific snapshot.
	 */
	public function get_snapshot_data( $file_id ) {
		$file_id  = sanitize_file_name( $file_id );
		$filepath = $this->storage_dir . '/' . $file_id;

		if ( file_exists( $filepath ) ) {
			$data = json_decode( file_get_contents( $filepath ), true );
			return $data['context'] ?? array();
		}

		return array();
	}
}
