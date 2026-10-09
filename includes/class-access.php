<?php
/**
 * Access Control Class
 *
 * Handles permission checking and access control
 *
 * @package ChatProjects
 */

namespace ChatProjects;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Access Control Class
 */
class Access {
    /**
     * Check if user can access project
     *
     * @param int $project_id Project ID
     * @param int $user_id User ID (optional, defaults to current user)
     * @return bool
     */
    public static function can_access_project($project_id, $user_id = null) {
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }

        // Must be logged in
        if (!$user_id) {
            return false;
        }

        $project = get_post($project_id);

        if (!$project || $project->post_type !== 'chatpr_project') {
            return false;
        }

        // Administrators can access every project.
        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        // Everyone else: only their own projects (sharing controls are a Pro feature).
        $can_access = (int) $project->post_author === (int) $user_id;

        /**
         * Filter whether a user may access (read/chat with) a project.
         *
         * @param bool     $can_access Default decision.
         * @param int      $project_id Project ID.
         * @param int      $user_id    User ID.
         * @param \WP_Post $project    Project post.
         */
        return (bool) apply_filters('chatprojects_can_access_project', $can_access, (int) $project_id, (int) $user_id, $project);
    }

    /**
     * Check if user can edit project
     *
     * @param int $project_id Project ID
     * @param int $user_id User ID (optional, defaults to current user)
     * @return bool
     */
    public static function can_edit_project($project_id, $user_id = null) {
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        // Administrators can edit all projects
        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        $project = get_post($project_id);
        
        if (!$project || $project->post_type !== 'chatpr_project') {
            return false;
        }

        // Only the author can edit
        return (int) $project->post_author === (int) $user_id;
    }

    /**
     * Check if user can access chat
     *
     * @param int $chat_id Chat ID
     * @param int $user_id User ID (optional, defaults to current user)
     * @return bool
     */
    public static function can_access_chat($chat_id, $user_id = null) {
        global $wpdb;
        
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        $chats_table = esc_sql($wpdb->prefix . 'chatprojects_chats');

        // Get chat details
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query
        $chat = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$chats_table} WHERE id = %d",
            $chat_id
        ));
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        if (!$chat) {
            return false;
        }

        // For general chats (Pro Chat), check if user owns the chat
        if ($chat->chat_mode === 'general' || $chat->project_id === null) {
            return (int) $chat->user_id === (int) $user_id;
        }

        // Project chats belong to the user who started them (administrators can
        // see all), and the user must still have access to the project.
        $owns_chat = (int) $chat->user_id === (int) $user_id || user_can($user_id, 'manage_options');

        return $owns_chat && self::can_access_project($chat->project_id, $user_id);
    }

    /**
     * Check if user can delete project
     *
     * @param int $project_id Project ID
     * @param int $user_id User ID (optional, defaults to current user)
     * @return bool
     */
    public static function can_delete_project($project_id, $user_id = null) {
        // Same permissions as editing
        return self::can_edit_project($project_id, $user_id);
    }

    /**
     * Get user's accessible projects
     *
     * @param int   $user_id User ID (optional, defaults to current user)
     * @param array $args Additional query arguments
     * @return array Array of project IDs
     */
    public static function get_accessible_projects($user_id = null, $args = array()) {
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }

        // Must be logged in
        if (!$user_id) {
            return array();
        }

        $defaults = array(
            'post_type' => 'chatpr_project',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
        );

        $args = wp_parse_args($args, $defaults);

        // Same rule as can_access_project(): administrators see every project,
        // everyone else only their own (sharing is a Pro feature).
        if (!user_can($user_id, 'manage_options')) {
            $args['author'] = (int) $user_id;
        }

        return get_posts($args);
    }
}
