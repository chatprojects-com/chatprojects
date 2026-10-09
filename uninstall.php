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

/**
 * Whether ChatProjects Pro is installed (active or not).
 *
 * Pro uses the same options, tables, post type and capabilities, so removing
 * Free must leave all of that in place while Pro is present.
 *
 * @return bool
 */
function chatprojects_uninstall_pro_installed() {
	if ( defined( 'CHATPROJECTS_PRO_VERSION' ) ) {
		return true;
	}
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	foreach ( get_plugins() as $plugin_file => $plugin_data ) {
		if ( 0 === strpos( $plugin_file, 'chatprojects-pro/' ) || 'ChatProjects Pro' === $plugin_data['Name'] ) {
			return true;
		}
	}
	return false;
}

/**
 * Remove the plugin's data from the current site.
 */
function chatprojects_uninstall_site() {
	global $wpdb;

	// Respect the administrator's choice to keep data for a reinstall.
	if ( get_option( 'chatprojects_keep_data_on_uninstall' ) ) {
		return;
	}

	// Cron hooks. wp_unschedule_hook() also clears events scheduled with arguments
	// (indexing batches are scheduled per project ID).
	wp_unschedule_hook( 'chatprojects_cleanup_transients' );
	wp_unschedule_hook( 'chatprojects_process_index_batch' );
	wp_unschedule_hook( 'chatprojects_cleanup_widget_sessions' );
	wp_unschedule_hook( 'chatprojects_sync_indexed_post' );

	/**
	 * Options and transients.
	 *
	 * Every option the plugin writes is prefixed chatprojects_ and every
	 * transient chatpr_ / chatprojects_. The LIKE patterns are anchored so
	 * unrelated plugins are never touched (Pro is handled above).
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
		'chatprojects_widget_visitor_sessions',
		'chatprojects_widget_visitor_messages',
		// Pre-1.3.0 names (Pro isn't installed, or we wouldn't be here).
		'chatprojects_widget_sessions',
		'chatprojects_widget_messages',
	);
	foreach ( $tables as $table ) {
		$table_name = esc_sql( $wpdb->prefix . $table );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Table name from $wpdb->prefix; DROP required on uninstall.
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
	}

	/**
	 * Custom post type content (posts + meta).
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
		'read_chatpr_prompt',
		'read_private_chatpr_prompts',
		'edit_chatpr_prompt',
		'edit_chatpr_prompts',
		'edit_others_chatpr_prompts',
		'edit_published_chatpr_prompts',
		'publish_chatpr_prompts',
		'delete_chatpr_prompt',
		'delete_chatpr_prompts',
		'delete_others_chatpr_prompts',
		'delete_published_chatpr_prompts',
		'manage_chatprojects_settings',
	);
	foreach ( wp_roles()->role_objects as $role ) {
		foreach ( $capabilities as $capability ) {
			$role->remove_cap( $capability );
		}
	}
	remove_role( 'chatpr_projects_user' );
}

if ( ! chatprojects_uninstall_pro_installed() ) {
	if ( is_multisite() ) {
		foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $chatprojects_site_id ) {
			switch_to_blog( $chatprojects_site_id );
			chatprojects_uninstall_site();
			restore_current_blog();
		}
	} else {
		chatprojects_uninstall_site();
	}
	wp_cache_flush();
}
