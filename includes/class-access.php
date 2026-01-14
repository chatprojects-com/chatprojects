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

        if (!$user_id) {
            return false;
        }

        // Administrators can access all projects
        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        $project = get_post($project_id);
        
        if (!$project || $project->post_type !== 'chatpr_project') {
            return false;
        }

        // Check if user is the author
        if ((int) $project->post_author === (int) $user_id) {
            return true;
        }

        // Check sharing settings
        $sharing_mode = get_post_meta($project_id, '_cp_sharing_mode', true);
        
        switch ($sharing_mode) {
            case 'public':
                // Anyone can access
                return true;
                
            case 'shared':
                // Check if user is in shared users list
                $shared_users = get_post_meta($project_id, '_cp_shared_users', true);
                if (is_array($shared_users) && in_array($user_id, $shared_users, true)) {
                    return true;
                }
                break;
                
            case 'private':
            default:
                // Only author can access
                return false;
        }

        return false;
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
            $can_access = (int) $chat->user_id === (int) $user_id;

            // Security logging for access denials
            if (!$can_access) {
            }

            return $can_access;
        }

        // For project chats, check if user can access the parent project
        $can_access_project = self::can_access_project($chat->project_id, $user_id);

        if (!$can_access_project) {
        }

        return $can_access_project;
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
     * Check if user can access prompt
     *
     * @param int $prompt_id Prompt ID
     * @param int $user_id User ID (optional, defaults to current user)
     * @return bool
     */
    public static function can_access_prompt($prompt_id, $user_id = null) {
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        // Administrators can access all prompts
        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        $prompt = get_post($prompt_id);
        
        if (!$prompt || $prompt->post_type !== 'chatpr_prompt') {
            return false;
        }

        // Check if user is the author
        if ((int) $prompt->post_author === (int) $user_id) {
            return true;
        }

        // Check sharing settings
        $sharing_mode = get_post_meta($prompt_id, '_cp_sharing_mode', true);
        
        switch ($sharing_mode) {
            case 'public':
                // Anyone can access
                return true;
                
            case 'shared':
                // Check if user is in shared users list
                $shared_users = get_post_meta($prompt_id, '_cp_shared_users', true);
                if (is_array($shared_users) && in_array($user_id, $shared_users, true)) {
                    return true;
                }
                break;
                
            case 'private':
            default:
                // Only author can access
                return false;
        }

        return false;
    }

    /**
     * Check if user can edit prompt
     *
     * @param int $prompt_id Prompt ID
     * @param int $user_id User ID (optional, defaults to current user)
     * @return bool
     */
    public static function can_edit_prompt($prompt_id, $user_id = null) {
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        // Administrators can edit all prompts
        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        $prompt = get_post($prompt_id);
        
        if (!$prompt || $prompt->post_type !== 'chatpr_prompt') {
            return false;
        }

        // Only the author can edit
        return (int) $prompt->post_author === (int) $user_id;
    }

    /**
     * Check if user can delete prompt
     *
     * @param int $prompt_id Prompt ID
     * @param int $user_id User ID (optional, defaults to current user)
     * @return bool
     */
    public static function can_delete_prompt($prompt_id, $user_id = null) {
        // Same permissions as editing
        return self::can_edit_prompt($prompt_id, $user_id);
    }

    /**
     * Check if user can access transcription
     *
     * @param int $transcription_id Transcription ID
     * @param int $user_id User ID (optional, defaults to current user)
     * @return bool
     */
    public static function can_access_transcription($transcription_id, $user_id = null) {
        global $wpdb;

        if (null === $user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        // Administrators can access all transcriptions
        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        $transcriptions_table = esc_sql($wpdb->prefix . 'chatprojects_transcriptions');

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query
        $transcription = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$transcriptions_table} WHERE id = %d",
            $transcription_id
        ));
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        if (!$transcription) {
            return false;
        }

        // Only the owner can access
        return (int) $transcription->user_id === (int) $user_id;
    }

    /**
     * Check if user can access comparison
     *
     * @param int $comparison_id Comparison ID
     * @param int $user_id User ID (optional, defaults to current user)
     * @return bool
     */
    public static function can_access_comparison($comparison_id, $user_id = null) {
        global $wpdb;

        if (null === $user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        // Administrators can access all comparisons
        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        $comparisons_table = esc_sql($wpdb->prefix . 'chatprojects_comparisons');

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query
        $comparison = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$comparisons_table} WHERE id = %d",
            $comparison_id
        ));
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        if (!$comparison) {
            return false;
        }

        // Only the owner can access
        $can_access = (int) $comparison->user_id === (int) $user_id;

        // Security logging for access denials
        if (!$can_access) {
        }

        return $can_access;
    }

    /**
     * Get user's accessible projects
     *
     * @param int $user_id User ID (optional, defaults to current user)
     * @param array $args Additional query arguments
     * @return array Array of project IDs
     */
    public static function get_accessible_projects($user_id = null, $args = array()) {
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }

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

        // Administrators can access all projects
        if (user_can($user_id, 'manage_options')) {
            $projects = get_posts($args);
            return $projects;
        }

        // Get user's own projects
        $own_args = array_merge($args, array('author' => $user_id));
        $own_projects = get_posts($own_args);

        // Get public projects
        $public_args = array_merge($args, array(
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required for sharing functionality
            'meta_query' => array(
                array(
                    'key' => '_cp_sharing_mode',
                    'value' => 'public',
                ),
            ),
        ));
        $public_projects = get_posts($public_args);

        // Get shared projects
        $shared_args = array_merge($args, array(
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required for sharing functionality
            'meta_query' => array(
                array(
                    'key' => '_cp_sharing_mode',
                    'value' => 'shared',
                ),
                array(
                    'key' => '_cp_shared_users',
                    'value' => serialize(strval($user_id)),
                    'compare' => 'LIKE',
                ),
            ),
        ));
        $shared_projects = get_posts($shared_args);

        // Merge and remove duplicates
        $all_projects = array_unique(array_merge($own_projects, $public_projects, $shared_projects));

        return $all_projects;
    }

    /**
     * Get user's accessible prompts
     *
     * @param int $user_id User ID (optional, defaults to current user)
     * @param array $args Additional query arguments
     * @return array Array of prompt IDs
     */
    public static function get_accessible_prompts($user_id = null, $args = array()) {
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return array();
        }

        $defaults = array(
            'post_type' => 'chatpr_prompt',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
        );

        $args = wp_parse_args($args, $defaults);

        // Administrators can access all prompts
        if (user_can($user_id, 'manage_options')) {
            $prompts = get_posts($args);
            return $prompts;
        }

        // Get user's own prompts
        $own_args = array_merge($args, array('author' => $user_id));
        $own_prompts = get_posts($own_args);

        // Get public prompts
        $public_args = array_merge($args, array(
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required for sharing functionality
            'meta_query' => array(
                array(
                    'key' => '_cp_sharing_mode',
                    'value' => 'public',
                ),
            ),
        ));
        $public_prompts = get_posts($public_args);

        // Get shared prompts
        $shared_args = array_merge($args, array(
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required for sharing functionality
            'meta_query' => array(
                array(
                    'key' => '_cp_sharing_mode',
                    'value' => 'shared',
                ),
                array(
                    'key' => '_cp_shared_users',
                    'value' => serialize(strval($user_id)),
                    'compare' => 'LIKE',
                ),
            ),
        ));
        $shared_prompts = get_posts($shared_args);

        // Merge and remove duplicates
        $all_prompts = array_unique(array_merge($own_prompts, $public_prompts, $shared_prompts));

        return $all_prompts;
    }
}
