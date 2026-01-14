<?php
/**
 * User Roles Class
 *
 * Handles custom user roles and capabilities
 *
 * @package ChatProjects
 */

namespace ChatProjects;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * User Roles Class
 */
class User_Roles {
    /**
     * Initialize user roles
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'add_capabilities'));
    }

    /**
     * Add custom "Projects" role on plugin activation
     */
    public static function add_custom_role() {
        // Remove old role if exists
        remove_role('chatpr_projects_user');
        
        // Create custom "Projects" role with ChatProjects capabilities
        // No admin access - frontend only
        add_role(
            'chatpr_projects_user',
            __('Projects User', 'chatprojects'),
            array(
                'read' => true, // Can log in to WordPress
                'upload_files' => true, // Allow file uploads
            )
        );

        // Add ChatProjects capabilities to the custom role
        $custom_role = get_role('chatpr_projects_user');
        if ($custom_role) {
            self::add_author_capabilities($custom_role);
        }
    }

    /**
     * Add custom capabilities to existing roles
     */
    public static function add_capabilities() {
        // Add capabilities to Administrator role
        $admin = get_role('administrator');
        if ($admin) {
            self::add_role_capabilities($admin);
        }

        // Add capabilities to Editor role
        $editor = get_role('editor');
        if ($editor) {
            self::add_role_capabilities($editor, false);
        }

        // Add capabilities to Author role
        $author = get_role('author');
        if ($author) {
            self::add_author_capabilities($author);
        }

        // Add capabilities to custom "Projects User" role
        $projects_user = get_role('chatpr_projects_user');
        if ($projects_user) {
            self::add_author_capabilities($projects_user);
        }
    }

    /**
     * Add full capabilities to a role
     *
     * @param WP_Role $role WordPress role object
     * @param bool $manage_settings Whether role can manage settings
     */
    private static function add_role_capabilities($role, $manage_settings = true) {
        // Project capabilities
        $role->add_cap('read_chatpr_project');
        $role->add_cap('read_private_chatpr_projects');
        $role->add_cap('edit_chatpr_project');
        $role->add_cap('edit_chatpr_projects');
        $role->add_cap('edit_others_chatpr_projects');
        $role->add_cap('edit_published_chatpr_projects');
        $role->add_cap('publish_chatpr_projects');
        $role->add_cap('delete_chatpr_project');
        $role->add_cap('delete_chatpr_projects');
        $role->add_cap('delete_others_chatpr_projects');
        $role->add_cap('delete_published_chatpr_projects');

        // Prompt capabilities
        $role->add_cap('read_chatpr_prompt');
        $role->add_cap('read_private_chatpr_prompts');
        $role->add_cap('edit_chatpr_prompt');
        $role->add_cap('edit_chatpr_prompts');
        $role->add_cap('edit_others_chatpr_prompts');
        $role->add_cap('edit_published_chatpr_prompts');
        $role->add_cap('publish_chatpr_prompts');
        $role->add_cap('delete_chatpr_prompt');
        $role->add_cap('delete_chatpr_prompts');
        $role->add_cap('delete_others_chatpr_prompts');
        $role->add_cap('delete_published_chatpr_prompts');

        // Settings capability
        if ($manage_settings) {
            $role->add_cap('manage_chatprojects_settings');
        }
    }

    /**
     * Add author capabilities
     *
     * @param WP_Role $role WordPress role object
     */
    private static function add_author_capabilities($role) {
        // Project capabilities (own posts only)
        $role->add_cap('read_chatpr_project');
        $role->add_cap('edit_chatpr_project');
        $role->add_cap('edit_chatpr_projects');
        $role->add_cap('edit_published_chatpr_projects');
        $role->add_cap('publish_chatpr_projects');
        $role->add_cap('delete_chatpr_project');
        $role->add_cap('delete_chatpr_projects');
        $role->add_cap('delete_published_chatpr_projects');

        // Prompt capabilities (own posts only)
        $role->add_cap('read_chatpr_prompt');
        $role->add_cap('edit_chatpr_prompt');
        $role->add_cap('edit_chatpr_prompts');
        $role->add_cap('edit_published_chatpr_prompts');
        $role->add_cap('publish_chatpr_prompts');
        $role->add_cap('delete_chatpr_prompt');
        $role->add_cap('delete_chatpr_prompts');
        $role->add_cap('delete_published_chatpr_prompts');
    }

    /**
     * Remove custom role and capabilities (on plugin uninstall)
     */
    public static function remove_custom_role() {
        remove_role('chatpr_projects_user');
    }

    /**
     * Remove custom capabilities from all roles (on plugin uninstall)
     */
    public static function remove_capabilities() {
        $roles = array('administrator', 'editor', 'author', 'chatpr_projects_user');

        foreach ($roles as $role_name) {
            $role = get_role($role_name);
            if (!$role) {
                continue;
            }

            // Remove project capabilities
            $role->remove_cap('read_chatpr_project');
            $role->remove_cap('read_private_chatpr_projects');
            $role->remove_cap('edit_chatpr_project');
            $role->remove_cap('edit_chatpr_projects');
            $role->remove_cap('edit_others_chatpr_projects');
            $role->remove_cap('edit_published_chatpr_projects');
            $role->remove_cap('publish_chatpr_projects');
            $role->remove_cap('delete_chatpr_project');
            $role->remove_cap('delete_chatpr_projects');
            $role->remove_cap('delete_others_chatpr_projects');
            $role->remove_cap('delete_published_chatpr_projects');

            // Remove prompt capabilities
            $role->remove_cap('read_chatpr_prompt');
            $role->remove_cap('read_private_chatpr_prompts');
            $role->remove_cap('edit_chatpr_prompt');
            $role->remove_cap('edit_chatpr_prompts');
            $role->remove_cap('edit_others_chatpr_prompts');
            $role->remove_cap('edit_published_chatpr_prompts');
            $role->remove_cap('publish_chatpr_prompts');
            $role->remove_cap('delete_chatpr_prompt');
            $role->remove_cap('delete_chatpr_prompts');
            $role->remove_cap('delete_others_chatpr_prompts');
            $role->remove_cap('delete_published_chatpr_prompts');

            // Remove settings capability
            $role->remove_cap('manage_chatprojects_settings');
        }
    }

    /**
     * Check if current user can use ChatProjects
     *
     * @param int $user_id User ID (optional, defaults to current user)
     * @return bool
     */
    public static function can_use_chatprojects($user_id = null) {
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        // Check if user has any ChatProjects capability
        // Use publish_* capabilities (general) instead of read_* (meta capabilities that require post ID)
        return user_can($user_id, 'publish_chatpr_projects') || user_can($user_id, 'publish_chatpr_prompts');
    }

    /**
     * Check if current user can manage settings
     *
     * @param int $user_id User ID (optional, defaults to current user)
     * @return bool
     */
    public static function can_manage_settings($user_id = null) {
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        return user_can($user_id, 'manage_chatprojects_settings') || user_can($user_id, 'manage_options');
    }
}