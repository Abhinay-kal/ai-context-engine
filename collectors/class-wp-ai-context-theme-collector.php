<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Theme Code Snapshot Collector
 * Extracts information and code from the active theme and child theme.
 */
class WP_AI_Context_Theme_Collector {

	/**
	 * Get the theme context, including file tree and functions.php.
	 * Can load specific files on-demand to save tokens.
	 *
	 * @param array $requested_files Array of relative file paths to read (e.g. ['single.php', 'template-parts/content.php']).
	 * @param array $requested_overrides Array of folder names to scan for overrides (e.g. ['woocommerce']).
	 * @return array
	 */
	public function get_theme_data( $requested_files = array(), $requested_overrides = array() ) {
		$child_dir  = get_stylesheet_directory();
		$parent_dir = get_template_directory();
		$is_child   = ( $child_dir !== $parent_dir );
		$theme      = wp_get_theme();

		$data = array(
			'active_theme'   => $theme->get( 'Name' ),
			'version'        => $theme->get( 'Version' ),
			'is_child_theme' => $is_child,
		);

		if ( $is_child ) {
			$data['parent_theme'] = $theme->parent() ? $theme->parent()->get( 'Name' ) : 'Unknown';
		}

		// 1. Core Functions (always load functions.php as it's the source of most hooks/issues)
		$data['functions_php'] = $this->get_functions_php( $child_dir, $parent_dir, $is_child );

		// 2. File Tree (Lightweight structural map)
		$data['file_tree'] = $this->get_file_tree( $child_dir, $parent_dir, $is_child );

		// 3. Auto-detect and include requested overrides (e.g., WooCommerce templates in theme)
		if ( ! empty( $requested_overrides ) ) {
			$override_files  = $this->find_override_files( $requested_overrides, $child_dir, $parent_dir, $is_child );
			$requested_files = array_unique( array_merge( $requested_files, $override_files ) );
		}

		// 4. On-Demand File Content (MCP Pattern)
		if ( ! empty( $requested_files ) ) {
			$data['requested_files'] = $this->get_requested_files( $requested_files, $child_dir, $parent_dir );
		}

		return $data;
	}

	/**
	 * Reads the functions.php file(s).
	 */
	private function get_functions_php( $child_dir, $parent_dir, $is_child ) {
		$functions = array();

		// Read Child functions.php if it exists
		if ( $is_child && file_exists( $child_dir . '/functions.php' ) ) {
			$functions['child_functions.php'] = $this->safe_read_file( $child_dir . '/functions.php' );
		}

		// Read Parent functions.php if it exists
		if ( file_exists( $parent_dir . '/functions.php' ) ) {
			$functions['parent_functions.php'] = $this->safe_read_file( $parent_dir . '/functions.php' );
		}

		return $functions;
	}

	/**
	 * Scans the root directories of the theme(s) to provide a structural map to the AI.
	 */
	private function get_file_tree( $child_dir, $parent_dir, $is_child ) {
		$tree = array();

		if ( $is_child ) {
			$tree['child_theme_files'] = $this->scan_directory( $child_dir );
		}

		$tree['parent_theme_files'] = $this->scan_directory( $parent_dir );

		return $tree;
	}

	/**
	 * Safely scans a directory up to 3 levels deep to return a list of files/folders.
	 */
	private function scan_directory( $base_dir, $sub_dir = '', $depth = 0 ) {
		$current_dir = $base_dir . ( $sub_dir ? '/' . $sub_dir : '' );

		if ( ! is_dir( $current_dir ) || $depth > 3 ) {
			return array();
		}

		$files = array();
		$items = scandir( $current_dir );

		foreach ( $items as $item ) {
			if ( $item === '.' || $item === '..' || $item === '.git' ) {
				continue;
			}

			$item_path     = $current_dir . '/' . $item;
			$relative_path = $sub_dir ? $sub_dir . '/' . $item : $item;

			if ( is_dir( $item_path ) ) {
				$files[] = $relative_path . '/'; // Add the directory itself
				// Recurse into directory
				$nested_files = $this->scan_directory( $base_dir, $relative_path, $depth + 1 );
				$files        = array_merge( $files, $nested_files );
			} else {
				$files[] = $relative_path;
			}
		}

		return $files;
	}

	/**
	 * Finds all files within specific override directories (e.g., 'woocommerce/') in the active theme.
	 */
	private function find_override_files( $requested_overrides, $child_dir, $parent_dir, $is_child ) {
		$override_files = array();

		foreach ( $requested_overrides as $folder_name ) {
			// Clean the folder name
			$folder_name = trim( $folder_name, '/' );

			if ( $is_child && is_dir( $child_dir . '/' . $folder_name ) ) {
				$files = $this->scan_directory( $child_dir, $folder_name );
				foreach ( $files as $file ) {
					if ( substr( $file, -1 ) !== '/' ) { // Skip directories themselves
						$override_files[] = $file;
					}
				}
			}

			if ( is_dir( $parent_dir . '/' . $folder_name ) ) {
				$files = $this->scan_directory( $parent_dir, $folder_name );
				foreach ( $files as $file ) {
					if ( substr( $file, -1 ) !== '/' ) {
						$override_files[] = $file;
					}
				}
			}
		}

		return $override_files;
	}

	/**
	 * Reads specific requested files safely (preventing directory traversal).
	 */
	private function get_requested_files( $requested_files, $child_dir, $parent_dir ) {
		$content = array();

		foreach ( $requested_files as $relative_path ) {
			// Security: Prevent directory traversal (e.g. '../../wp-config.php')
			if ( strpos( $relative_path, '..' ) !== false || strpos( $relative_path, '/' ) === 0 ) {
				$content[ $relative_path ] = 'SECURITY_ERROR: Invalid file path requested.';
				continue;
			}

			// Check Child Theme first (since it overrides parent templates)
			$child_path = $child_dir . '/' . $relative_path;
			if ( file_exists( $child_path ) ) {
				$content[ '[CHILD] ' . $relative_path ] = $this->safe_read_file( $child_path );
				continue;
			}

			// Fallback to Parent Theme
			$parent_path = $parent_dir . '/' . $relative_path;
			if ( file_exists( $parent_path ) ) {
				$content[ '[PARENT] ' . $relative_path ] = $this->safe_read_file( $parent_path );
				continue;
			}

			$content[ $relative_path ] = 'FILE_NOT_FOUND: Could not locate this file in the theme directories.';
		}

		return $content;
	}

	/**
	 * Reads a file, returning a truncated string if it's too large to protect tokens.
	 */
	private function safe_read_file( $path ) {
		if ( ! is_readable( $path ) ) {
			return 'ERROR: File is not readable due to permissions.';
		}

		$size = filesize( $path );
		if ( $size > 150000 ) { // ~150KB limit per file to save tokens
			return 'ERROR: File is too large to safely export into AI context (>150KB).';
		}

		return file_get_contents( $path );
	}
}
