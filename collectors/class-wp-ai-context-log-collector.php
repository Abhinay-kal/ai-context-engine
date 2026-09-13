<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Log Collector
 * Gathers recent errors from the WordPress debug.log.
 */
class WP_AI_Context_Log_Collector {

	/**
	 * Get recent log entries.
	 *
	 * @param int $lines Number of lines to return.
	 * @return array
	 */
	public function get_logs( $lines = 50 ) {
		$log_file = $this->get_log_path();

		if ( ! $log_file || ! file_exists( $log_file ) ) {
			return array( 'status' => 'Log file not found or WP_DEBUG_LOG disabled.' );
		}

		if ( ! is_readable( $log_file ) ) {
			return array( 'status' => 'Log file is not readable due to permissions.' );
		}

		$file_size = filesize( $log_file );
		if ( $file_size === 0 ) {
			return array( 'status' => 'Log file is empty.' );
		}

		$grouped_logs = $this->process_log_lines( $this->tail_file( $log_file, $lines ) );

		return array(
			'status' => 'success',
			'file'   => basename( $log_file ),
			'size'   => size_format( $file_size, 2 ),
			'groups' => $grouped_logs,
		);
	}

	/**
	 * Determine the path to the debug log.
	 *
	 * @return string|false
	 */
	private function get_log_path() {
		if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
			return false;
		}

		// WP_DEBUG_LOG can be a boolean or a custom string path.
		if ( is_string( WP_DEBUG_LOG ) ) {
			return WP_DEBUG_LOG;
		}

		return WP_CONTENT_DIR . '/debug.log';
	}

	/**
	 * Read the last N lines of a file efficiently.
	 *
	 * @param string $file Path to file.
	 * @param int    $lines Number of lines to read.
	 * @return array
	 */
	private function tail_file( $file, $lines = 50 ) {
		$f = @fopen( $file, 'rb' );
		if ( $f === false ) {
			return array();
		}

		fseek( $f, -1, SEEK_END );
		if ( fread( $f, 1 ) != "\n" ) {
			$lines -= 1;
		}

		$output   = '';
		$chunklen = 4096;

		while ( ftell( $f ) > 0 && $lines >= 0 ) {
			$seek = min( ftell( $f ), $chunklen );
			fseek( $f, -$seek, SEEK_CUR );
			$output = ( $chunk = fread( $f, $seek ) ) . $output;
			// strlen() is byte-based and avoids the optional mbstring dependency.
			fseek( $f, -strlen( $chunk ), SEEK_CUR );
			$lines -= substr_count( $chunk, "\n" );
		}

		while ( $lines++ < 0 ) {
			$output = substr( $output, strpos( $output, "\n" ) + 1 );
		}

		fclose( $f );

		// Split into array and remove empty lines
		$lines_array = explode( "\n", trim( $output ) );
		return array_values( array_filter( $lines_array ) );
	}

	/**
	 * Mask sensitive paths, truncate stack traces, and group/deduplicate logs by date/hour.
	 *
	 * @param array $lines Raw log lines.
	 * @return array
	 */
	private function process_log_lines( $lines ) {
		$grouped       = array();
		$current_group = 'Unknown Time';

		// We want to mask the absolute server path so it's not exposed
		$abspath = defined( 'ABSPATH' ) ? ABSPATH : '';

		foreach ( $lines as $line ) {
			// 1. Secret Masking & Stack Trace Truncation
			if ( ! empty( $abspath ) ) {
				$line = str_replace( $abspath, '', $line );
			}
			// Fallback: If stack trace has /var/www/html/wp-content/..., strip up to wp-content
			$line = preg_replace( '/\/.*?\/wp-content\//', 'wp-content/', $line );

			// 2. Time Grouping & Deduplication
			$timestamp_str = '';
			if ( preg_match( '/^\[([^\]]+)\]\s*(.*)/', $line, $matches ) ) {
				$timestamp_str = $matches[1];
				$error_message = $matches[2];

				$timestamp = strtotime( $timestamp_str );
				if ( $timestamp ) {
					$current_group = gmdate( 'Y-m-d H:00', $timestamp ) . ' UTC';
				}
			} else {
				$error_message = $line;
			}

			if ( ! isset( $grouped[ $current_group ] ) ) {
				$grouped[ $current_group ] = array();
			}

			// Generate a hash of the error message (without timestamp) for deduplication
			$error_hash = md5( $error_message );

			if ( isset( $grouped[ $current_group ][ $error_hash ] ) ) {
				++$grouped[ $current_group ][ $error_hash ]['count'];
			} else {
				$grouped[ $current_group ][ $error_hash ] = array(
					'message' => $error_message,
					'count'   => 1,
				);
			}
		}

		// Format output for readability
		$formatted_groups = array();
		foreach ( $grouped as $time => $errors ) {
			$formatted_groups[ $time ] = array();
			foreach ( $errors as $error ) {
				if ( $error['count'] > 1 ) {
					$formatted_groups[ $time ][] = $error['message'] . ' (Repeated ' . $error['count'] . ' times)';
				} else {
					$formatted_groups[ $time ][] = $error['message'];
				}
			}
		}

		return $formatted_groups;
	}
}
