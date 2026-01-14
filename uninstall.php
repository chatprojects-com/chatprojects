<?php
/**
 * Uninstall ChatProjects
 *
 * Cleans up all plugin data when the plugin is deleted.
 * This file is called automatically by WordPress when the plugin is deleted.
 *
 * @package ChatProjects
 */

// Exit if not called by WordPress uninstall
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

call_user_func(function () {
    global $wpdb;

    /**
     * Delete all plugin options
     */
    $options_to_delete = array(
        'chatprojects_openai_key',
        'chatprojects_gemini_key',
        'chatprojects_anthropic_key',
        'chatprojects_chutes_key',
        'chatprojects_general_chat_provider',
        'chatprojects_general_chat_model',
        'chatprojects_assistant_instructions',
        'chatprojects_default_model',
        'chatprojects_max_file_size',
        'chatprojects_allowed_file_types',
        'chatprojects_db_version',
        'chatprojects_activated',
        'chatprojects_encryption_key',
        'chatprojects_rewrites_flushed',
    );

    foreach ($options_to_delete as $option_name) {
        delete_option($option_name);
    }

    // Delete any transients
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup on uninstall
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '%chatprojects%'
        )
    );

    /**
     * Drop custom database tables
     */
    $tables_to_drop = array(
        esc_sql($wpdb->prefix . 'chatprojects_chats'),
        esc_sql($wpdb->prefix . 'chatprojects_messages'),
    );

    foreach ($tables_to_drop as $table_name) {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Table name from $wpdb->prefix, DROP TABLE required for uninstall cleanup
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
    }

    /**
     * Delete all custom post types and their meta
     */
    $post_types = array('chatpr_project');

    foreach ($post_types as $post_type) {
        // Get all posts of this type
        $posts = get_posts(array(
            'post_type' => $post_type,
            'post_status' => 'any',
            'numberposts' => -1,
            'fields' => 'ids',
        ));

        // Delete each post and its meta
        foreach ($posts as $post_id) {
            wp_delete_post($post_id, true);
        }
    }

    /**
     * Remove custom capabilities from roles
     */
    $capabilities_to_remove = array(
        // Project capabilities
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
        // Settings capability
        'manage_chatprojects_settings',
    );

    $roles = array('administrator', 'editor', 'author');

    foreach ($roles as $role_name) {
        $role = get_role($role_name);
        if ($role) {
            foreach ($capabilities_to_remove as $capability) {
                $role->remove_cap($capability);
            }
        }
    }

    /**
     * Remove custom user role
     */
    remove_role('chatpr_projects_user');

    /**
     * Clear any cached data
     */
    wp_cache_flush();
});
