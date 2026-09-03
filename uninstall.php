<?php
/**
 * Uninstall ChatProjects
 *
 * Runs when the plugin is deleted from the Plugins screen. Removes every
 * option, transient, table, post, capability and role the plugin created,
 * unless the administrator enabled "Keep data on uninstall" in Settings.
 *
 * @package ChatProjects
 */

// Exit if not called by WordPress uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

call_user_func(
	function () {
		global $wpdb;

		// Respect the administrator's choice to keep data for a reinstall.
		if ( get_option( 'chatprojects_keep_data_on_uninstall' ) ) {
			return;
		}

		// Cron hooks.
		wp_clear_scheduled_hook( 'chatprojects_cleanup_transients' );
		wp_clear_scheduled_hook( 'chatprojects_process_index_batch' );
		wp_clear_scheduled_hook( 'chatprojects_cleanup_widget_sessions' );

		/**
		 * Options and transients.
		 *
		 * Every option the plugin writes is prefixed chatprojects_ and every
		 * transient chatpr_ / chatprojects_. The LIKE patterns are anchored so
		 * ChatProjects Pro and unrelated plugins are never touched.
		 */
		$patterns = array(
			$wpdb->esc_like( 'chatprojects_' ) . '%',
			$wpdb->esc_like( '_transient_chatprojects_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_chatprojects_' ) . '%',
			$wpdb->esc_like( '_transient_chatpr_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_chatpr_' ) . '%',
		);
		foreach ( $patterns as $pattern ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup on uninstall.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $pattern ) );
		}

		// Legacy option names from early versions.
		foreach ( array( 'cp_openai_api_key', 'cp_gemini_api_key', 'cp_anthropic_api_key', 'cp_chutes_api_key' ) as $legacy ) {
			delete_option( $legacy );
		}

		/**
		 * Custom tables.
		 */
		$tables = array(
			'chatprojects_chats',
			'chatprojects_messages',
			'chatprojects_indexed_content',
			'chatprojects_widget_sessions',
			'chatprojects_widget_messages',
		);
		foreach ( $tables as $table ) {
			$table_name = esc_sql( $wpdb->prefix . $table );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Table name from $wpdb->prefix; DROP required on uninstall.
			$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
		}

		/**
		 * Custom post type content (posts + meta) and taxonomy terms.
		 */
		$post_ids = get_posts(
			array(
				'post_type'   => 'chatpr_project',
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);
		foreach ( $post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}

		$term_ids = $wpdb->get_col(
			$wpdb->prepare( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", 'chatpr_project_category' )
		);
		foreach ( (array) $term_ids as $term_id ) {
			wp_delete_term( (int) $term_id, 'chatpr_project_category' );
		}

		// Per-user preferences.
		delete_metadata( 'user', 0, 'cp_theme_preference', '', true );

		/**
		 * Capabilities and role.
		 */
		$capabilities = array(
			'edit_chatpr_project',
			'read_chatpr_project',
			'delete_chatpr_project',
			'edit_chatpr_projects',
			'edit_others_chatpr_projects',
			'publish_chatpr_projects',
			'read_private_chatpr_projects',
			'delete_chatpr_projects',
			'delete_others_chatpr_projects',
			'delete_published_chatpr_projects',
			'delete_private_chatpr_projects',
			'edit_published_chatpr_projects',
			'edit_private_chatpr_projects',
			'manage_chatprojects_settings',
		);
		foreach ( wp_roles()->role_objects as $role ) {
			foreach ( $capabilities as $capability ) {
				$role->remove_cap( $capability );
			}
		}
		remove_role( 'chatpr_projects_user' );

		wp_cache_flush();
	}
);
