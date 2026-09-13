<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Schema & CPT Collector
 * Gathers the architectural blueprint of the WordPress site (Custom Post Types, Taxonomies, Custom Tables).
 */
class WP_AI_Context_Schema_Collector {

	/**
	 * Get the complete architectural schema of the site.
	 *
	 * @param array|string $requested_tables Array of table names to DESCRIBE, or 'all'.
	 * @param array        $requested_prefixes Array of table prefixes to dynamically match and DESCRIBE (e.g. array('wc_', 'woocommerce_')).
	 * @return array
	 */
	public function get_schema( $requested_tables = array(), $requested_prefixes = array() ) {
		return array(
			'custom_post_types' => $this->get_cpts(),
			'custom_tables'     => $this->get_custom_tables( $requested_tables, $requested_prefixes ),
		);
	}

	/**
	 * Gathers all non-builtin public Custom Post Types and their associated Taxonomies.
	 *
	 * @return array
	 */
	private function get_cpts() {
		$cpt_data = array();

		// Only get public CPTs that aren't native to WordPress (post, page, attachment, etc.)
		$args       = array(
			'public'   => true,
			'_builtin' => false,
		);
		$post_types = get_post_types( $args, 'objects' );

		foreach ( $post_types as $cpt ) {
			// Find which taxonomies are registered for this CPT
			$taxonomies = get_object_taxonomies( $cpt->name, 'objects' );
			$tax_data   = array();

			foreach ( $taxonomies as $tax ) {
				$tax_data[] = array(
					'name'         => $tax->name,
					'hierarchical' => $tax->hierarchical,
				);
			}

			$cpt_data[ $cpt->name ] = array(
				'label'        => $cpt->label,
				'hierarchical' => $cpt->hierarchical,
				'has_archive'  => $cpt->has_archive,
				'taxonomies'   => $tax_data,
			);
		}

		return $cpt_data;
	}

	/**
	 * Gathers the schema (columns, types) of non-core database tables.
	 * Groups them by plugin prefix. If $requested_tables is empty, it returns
	 * just the names to save tokens, allowing the AI to request full schema later.
	 *
	 * @param array|string $requested_tables Array of table names, or 'all'.
	 * @param array        $requested_prefixes Array of table prefixes to match.
	 * @return array
	 */
	private function get_custom_tables( $requested_tables = array(), $requested_prefixes = array() ) {
		global $wpdb;
		$custom_tables = array();

		$cache_key = 'wp_ai_context_schema_' . md5(
			wp_json_encode( (array) $requested_tables ) . '|' . wp_json_encode( (array) $requested_prefixes ) . '|' . $wpdb->prefix
		);
		$cached    = get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		// Fetch all tables in the current database
		$all_tables = $wpdb->get_col( 'SHOW TABLES' );

		// Core tables to ignore to save tokens (AI already knows their schema)
		// We include multisite global tables here as well.
		$core_table_suffixes = array(
			'posts',
			'postmeta',
			'comments',
			'commentmeta',
			'terms',
			'termmeta',
			'term_taxonomy',
			'term_relationships',
			'users',
			'usermeta',
			'options',
			'links',
			'blogs',
			'blog_versions',
			'registration_log',
			'signups',
			'site',
			'sitemeta',
		);

		$core_tables = array();
		foreach ( $core_table_suffixes as $suffix ) {
			$core_tables[] = $wpdb->prefix . $suffix;
			// Also add base_prefix for multisite global tables
			if ( $wpdb->prefix !== $wpdb->base_prefix ) {
				$core_tables[] = $wpdb->base_prefix . $suffix;
			}
		}

		foreach ( $all_tables as $table_name ) {
			if ( in_array( $table_name, $core_tables, true ) ) {
				continue;
			}

			// MULTISITE FIX: Check if it belongs to this subsite (prefix) OR the network (base_prefix)
			$is_subsite_table = ( strpos( $table_name, $wpdb->prefix ) === 0 );
			$is_global_table  = ( strpos( $table_name, $wpdb->base_prefix ) === 0 );

			if ( ! $is_subsite_table && ! $is_global_table ) {
				continue;
			}

			// Strip the prefix to find the clean name
			$clean_name = $is_subsite_table
				? substr( $table_name, strlen( $wpdb->prefix ) )
				: substr( $table_name, strlen( $wpdb->base_prefix ) );

			// Categorization: group by the first word before an underscore (e.g. 'woocommerce_tax' -> 'woocommerce')
			$category = 'uncategorized';
			if ( strpos( $clean_name, '_' ) !== false ) {
				$parts    = explode( '_', $clean_name );
				$category = $parts[0];
			}

			if ( ! isset( $custom_tables[ $category ] ) ) {
				$custom_tables[ $category ] = array();
			}

			// ON-DEMAND SCHEMA: If 'all' is requested, or this specific table is requested, run DESCRIBE.
			// Or if it matches one of the requested prefixes.
			$needs_describe = ( $requested_tables === 'all' ) || ( is_array( $requested_tables ) && in_array( $clean_name, $requested_tables, true ) );

			if ( ! $needs_describe && ! empty( $requested_prefixes ) ) {
				foreach ( $requested_prefixes as $prefix ) {
					if ( strpos( $clean_name, $prefix ) === 0 ) {
						$needs_describe = true;
						break;
					}
				}
			}

			if ( $needs_describe ) {
				// Identifiers cannot be bound as placeholders; escape via backticks after stripping backticks.
				$safe_table = str_replace( '`', '', $table_name );
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$columns    = $wpdb->get_results( "DESCRIBE `{$safe_table}`", ARRAY_A );
				if ( ! empty( $columns ) ) {
					$schema = array();
					foreach ( $columns as $col ) {
						$field_info = $col['Type'];
						if ( $col['Key'] === 'PRI' ) {
							$field_info .= ' (PRIMARY KEY)';
						} elseif ( $col['Key'] === 'MUL' ) {
							$field_info .= ' (INDEX)';
						}
						$schema[ $col['Field'] ] = $field_info;
					}
					$custom_tables[ $category ][ $clean_name ] = $schema;
				}
			} else {
				// Just add the name to the category array
				$custom_tables[ $category ][] = $clean_name;
			}
		}

		if ( ! empty( $custom_tables ) ) {
			set_transient( $cache_key, $custom_tables, 6 * HOUR_IN_SECONDS );
		}

		return $custom_tables;
	}
}
