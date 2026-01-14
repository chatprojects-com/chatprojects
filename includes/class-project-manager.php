<?php
/**
 * Project Manager Class
 *
 * Handles project CRUD operations and OpenAI integration
 *
 * @package ChatProjects
 */

namespace ChatProjects;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Project Manager Class
 */
class Project_Manager {
    /**
     * API Handler instance
     *
     * @var API_Handler
     */
    private $api;

    /**
     * Constructor
     */
    public function __construct() {
        $this->api = new API_Handler();
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Save post meta when project is saved
        add_action('save_post_chatpr_project', array($this, 'save_project_meta'), 10, 3);
        
        // Delete OpenAI resources when project is deleted
        add_action('before_delete_post', array($this, 'before_delete_project'));
        
        // Add custom columns to project list
        add_filter('manage_chatpr_project_posts_columns', array($this, 'add_project_columns'));
        add_action('manage_chatpr_project_posts_custom_column', array($this, 'render_project_columns'), 10, 2);
    }

    /**
     * Create a new project
     *
     * @param array $args Project arguments
     * @return int|WP_Error Project ID or error
     */
    public function create_project($args) {
        $defaults = array(
            'post_title' => '',
            'post_content' => '',
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
            'instructions' => '',
            'model' => get_option('chatprojects_default_model', 'gpt-5.2'),
            'tools' => array(array('type' => 'file_search')),
            'sharing_mode' => 'private',
            'shared_users' => array(),
        );

        $args = wp_parse_args($args, $defaults);

        // Create WordPress post
        $post_data = array(
            'post_title' => sanitize_text_field($args['post_title']),
            'post_content' => wp_kses_post($args['post_content']),
            'post_status' => sanitize_text_field($args['post_status']),
            'post_author' => absint($args['post_author']),
            'post_type' => 'chatpr_project',
        );

        $project_id = wp_insert_post($post_data);

        if (is_wp_error($project_id)) {
            return $project_id;
        }

        // Create Vector Store (no assistant needed for Responses API)
        $vector_store_name = $args['post_title'];
        $vector_store = $this->api->create_vector_store($vector_store_name);

        if (is_wp_error($vector_store)) {
            wp_delete_post($project_id, true);
            return $vector_store;
        }

        // Save metadata
        update_post_meta($project_id, '_cp_vector_store_id', $vector_store['id']);
        update_post_meta($project_id, '_cp_model', $args['model']);
        update_post_meta($project_id, '_cp_instructions', sanitize_textarea_field($args['instructions']));
        update_post_meta($project_id, '_cp_sharing_mode', sanitize_text_field($args['sharing_mode']));

        if (!empty($args['shared_users'])) {
            update_post_meta($project_id, '_cp_shared_users', array_map('absint', $args['shared_users']));
        }

        return $project_id;
    }

    /**
     * Update project
     *
     * @param int $project_id Project ID
     * @param array $args Update arguments
     * @return bool|WP_Error True on success, error on failure
     */
    public function update_project($project_id, $args) {
        // Check permissions
        if (!Access::can_edit_project($project_id)) {
            return new \WP_Error('permission_denied', __('You do not have permission to edit this project.', 'chatprojects'));
        }

        $project = get_post($project_id);
        
        if (!$project || $project->post_type !== 'chatpr_project') {
            return new \WP_Error('invalid_project', __('Invalid project ID.', 'chatprojects'));
        }

        // Update post data
        if (isset($args['post_title']) || isset($args['post_content']) || isset($args['post_status'])) {
            $post_data = array('ID' => $project_id);
            
            if (isset($args['post_title'])) {
                $post_data['post_title'] = sanitize_text_field($args['post_title']);
            }
            
            if (isset($args['post_content'])) {
                $post_data['post_content'] = wp_kses_post($args['post_content']);
            }
            
            if (isset($args['post_status'])) {
                $post_data['post_status'] = sanitize_text_field($args['post_status']);
            }

            wp_update_post($post_data);
        }

        // Update meta fields
        if (isset($args['instructions'])) {
            update_post_meta($project_id, '_cp_instructions', sanitize_textarea_field($args['instructions']));
        }

        if (isset($args['model'])) {
            update_post_meta($project_id, '_cp_model', sanitize_text_field($args['model']));
        }

        if (isset($args['sharing_mode'])) {
            update_post_meta($project_id, '_cp_sharing_mode', sanitize_text_field($args['sharing_mode']));
        }

        if (isset($args['shared_users'])) {
            update_post_meta($project_id, '_cp_shared_users', array_map('absint', $args['shared_users']));
        }

        return true;
    }

    /**
     * Delete project
     *
     * @param int $project_id Project ID
     * @return bool|WP_Error True on success, error on failure
     */
    public function delete_project($project_id) {
        // Check permissions
        if (!Access::can_delete_project($project_id)) {
            return new \WP_Error('permission_denied', __('You do not have permission to delete this project.', 'chatprojects'));
        }

        $project = get_post($project_id);
        
        if (!$project || $project->post_type !== 'chatpr_project') {
            return new \WP_Error('invalid_project', __('Invalid project ID.', 'chatprojects'));
        }

        // Delete WordPress post (this will trigger before_delete_post hook)
        $result = wp_delete_post($project_id, true);

        if (!$result) {
            return new \WP_Error('delete_failed', __('Failed to delete project.', 'chatprojects'));
        }

        return true;
    }

    /**
     * Get project details
     *
     * @param int $project_id Project ID
     * @return array|WP_Error Project data or error
     */
    public function get_project($project_id) {
        // Check permissions
        if (!Access::can_access_project($project_id)) {
            return new \WP_Error('permission_denied', __('You do not have permission to access this project.', 'chatprojects'));
        }

        $project = get_post($project_id);
        
        if (!$project || $project->post_type !== 'chatpr_project') {
            return new \WP_Error('invalid_project', __('Invalid project ID.', 'chatprojects'));
        }

        return array(
            'id' => $project->ID,
            'title' => $project->post_title,
            'content' => $project->post_content,
            'status' => $project->post_status,
            'author' => $project->post_author,
            'created' => $project->post_date,
            'modified' => $project->post_modified,
            'vector_store_id' => get_post_meta($project->ID, '_cp_vector_store_id', true),
            'model' => get_post_meta($project->ID, '_cp_model', true),
            'instructions' => get_post_meta($project->ID, '_cp_instructions', true),
            'sharing_mode' => get_post_meta($project->ID, '_cp_sharing_mode', true),
            'shared_users' => get_post_meta($project->ID, '_cp_shared_users', true),
        );
    }

    /**
     * Get user's projects
     *
     * @param int $user_id User ID (optional, defaults to current user)
     * @param array $args Query arguments
     * @return array Array of projects
     */
    public function get_user_projects($user_id = null, $args = array()) {
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }

        $project_ids = Access::get_accessible_projects($user_id, $args);
        $projects = array();

        foreach ($project_ids as $project_id) {
            $project = $this->get_project($project_id);
            if (!is_wp_error($project)) {
                $projects[] = $project;
            }
        }

        return $projects;
    }

    /**
     * Save project meta on post save
     *
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     * @param bool $update Whether this is an update
     */
    public function save_project_meta($post_id, $post, $update) {
        // Skip autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Only handle published posts
        if ($post->post_status !== 'publish') {
            return;
        }

        // Create OpenAI resources if this is a new project
        if (!$update) {
            $vector_store_id = get_post_meta($post_id, '_cp_vector_store_id', true);

            if (empty($vector_store_id)) {
                // Create vector store for file search
                $this->create_openai_resources($post_id);
            }
        }
    }

    /**
     * Create OpenAI resources for project (Responses API - only vector store needed)
     *
     * @param int $project_id Project ID
     * @return bool|WP_Error True on success, error on failure
     */
    private function create_openai_resources($project_id) {
        $project = get_post($project_id);

        if (!$project) {
            return new \WP_Error('invalid_project', __('Invalid project ID.', 'chatprojects'));
        }

        $model = get_post_meta($project_id, '_cp_model', true);
        if (empty($model)) {
            $model = get_option('chatprojects_default_model', 'gpt-5.2');
            update_post_meta($project_id, '_cp_model', $model);
        }

        // Create vector store (no assistant needed for Responses API)
        $vector_store = $this->api->create_vector_store($project->post_title);

        if (is_wp_error($vector_store)) {
            return $vector_store;
        }

        // Save vector store ID
        update_post_meta($project_id, '_cp_vector_store_id', $vector_store['id']);

        return true;
    }

    /**
     * Delete OpenAI resources before project deletion
     *
     * @param int $post_id Post ID
     */
    public function before_delete_project($post_id) {
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'chatpr_project') {
            return;
        }

        // Delete vector store (no assistant to delete with Responses API)
        $vector_store_id = get_post_meta($post_id, '_cp_vector_store_id', true);

        if (!empty($vector_store_id)) {
            $this->api->delete_vector_store($vector_store_id);
        }

        // Delete associated chats and messages
        global $wpdb;

        $chats_table = $wpdb->prefix . 'chatprojects_chats';
        $chats_table_sql = esc_sql($chats_table);
        $messages_table = $wpdb->prefix . 'chatprojects_messages';
        $messages_table_sql = esc_sql($messages_table);

        // Get chat IDs for this project
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query
        $chat_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$chats_table_sql} WHERE project_id = %d",
            $post_id
        ));
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        // Delete messages for all chats
        if (!empty($chat_ids)) {
            $placeholders = implode(',', array_fill(0, count($chat_ids), '%d'));
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Dynamic placeholders for IN clause, values passed via spread operator
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$messages_table_sql} WHERE chat_id IN ({$placeholders})",
                ...$chat_ids
            ));
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        }

        // Delete chats
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query
        $wpdb->delete($chats_table, array('project_id' => $post_id), array('%d'));
    }

    /**
     * Add custom columns to project list
     *
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public function add_project_columns($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            
            if ($key === 'title') {
                $new_columns['vector_store_id'] = __('Vector Store', 'chatprojects');
            }
        }
        
        return $new_columns;
    }

    /**
     * Render custom columns
     *
     * @param string $column Column name
     * @param int $post_id Post ID
     */
    public function render_project_columns($column, $post_id) {
        switch ($column) {
            case 'vector_store_id':
                $vector_store_id = get_post_meta($post_id, '_cp_vector_store_id', true);
                if ($vector_store_id) {
                    // Show shortened ID for readability
                    $short_id = substr($vector_store_id, 0, 12) . '...';
                    echo '<span title="' . esc_attr($vector_store_id) . '">' . esc_html($short_id) . '</span>';
                } else {
                    echo '—';
                }
                break;
        }
    }
}
