<?php
/**
 * AJAX Handlers
 *
 * Handles all AJAX requests for the ChatProjects plugin
 *
 * @package ChatProjects
 */

namespace ChatProjects;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX Handlers Class
 */
class AJAX_Handlers {
    
    private $api_handler;
    private $chat_interface;
    private $vector_store;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Register AJAX actions first - don't instantiate dependencies until needed
        $this->register_actions();
        
        // Log successful initialization
    }
    
    /**
     * Lazy load API handler
     */
    private function get_api_handler() {
        if (!$this->api_handler) {
            $this->api_handler = new API_Handler();
        }
        return $this->api_handler;
    }
    
    /**
     * Lazy load chat interface
     */
    private function get_chat_interface() {
        if (!$this->chat_interface) {
            $this->chat_interface = new Chat_Interface();
        }
        return $this->chat_interface;
    }
    
    /**
     * Lazy load vector store
     */
    private function get_vector_store() {
        if (!$this->vector_store) {
            $this->vector_store = new Vector_Store();
        }
        return $this->vector_store;
    }

    /**
     * Safely retrieve a value from $_POST with optional sanitization.
     *
     * Nonce verification is handled by the calling methods before this helper is invoked.
     *
     * IMPORTANT: Callers MUST verify nonce using check_ajax_referer() before calling this method.
     * This helper does not perform nonce verification itself.
     *
     * @param string               $key               Array key to retrieve.
     * @param callable|string $sanitize_callback Sanitization callback (required).
     * @param mixed           $default           Default value when key is absent.
     * @return mixed
     */
    private function get_post_value( $key, $sanitize_callback = 'sanitize_text_field', $default = '' ) {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce MUST be verified by calling method before invoking this helper
        if ( ! isset( $_POST[ $key ] ) ) {
            return $default;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via callback below
        $value = wp_unslash( $_POST[ $key ] );
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        // Always sanitize - default to sanitize_text_field if callback not provided.
        if ( ! $sanitize_callback || ! is_callable( $sanitize_callback ) ) {
            $sanitize_callback = 'sanitize_text_field';
        }

        return call_user_func( $sanitize_callback, $value );
    }

    /**
     * Retrieve an uploaded file entry with comprehensive sanitization.
     *
     * IMPORTANT: Callers MUST verify nonce using check_ajax_referer() before calling this method.
     * This helper does not perform nonce verification itself.
     *
     * @param string $key File input key.
     * @return array|null Sanitized file array or null if not present.
     */
    private function get_uploaded_file( $key ) {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce MUST be verified by calling method before invoking this helper
        if ( empty( $_FILES[ $key ] ) ) {
            return null;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each field sanitized individually below
        $file = $_FILES[ $key ];

        // Validate tmp_name is a real uploaded file (security check).
        $tmp_name = isset( $file['tmp_name'] ) ? $file['tmp_name'] : '';
        if ( $tmp_name && ! is_uploaded_file( $tmp_name ) ) {
            return null; // Not a valid upload, reject.
        }

        return array(
            'name'     => isset( $file['name'] ) ? sanitize_file_name( wp_unslash( $file['name'] ) ) : '',
            'type'     => isset( $file['type'] ) ? sanitize_mime_type( wp_unslash( $file['type'] ) ) : '',
            'tmp_name' => $tmp_name, // Validated via is_uploaded_file() above.
            'error'    => isset( $file['error'] ) ? absint( $file['error'] ) : UPLOAD_ERR_NO_FILE,
            'size'     => isset( $file['size'] ) ? absint( $file['size'] ) : 0,
        );
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }
    
    /**
     * Register all AJAX actions
     */
    private function register_actions() {
        // Project management
        add_action('wp_ajax_chatpr_create_project', array($this, 'create_project'));
        add_action('wp_ajax_chatpr_get_projects', array($this, 'get_projects'));

        // Chat interface
        add_action('wp_ajax_chatpr_send_chat_message', array($this, 'send_chat_message'));
        add_action('wp_ajax_chatpr_stream_chat_message', array($this, 'stream_chat_message'));
        add_action('wp_ajax_chatpr_load_chat_history', array($this, 'load_chat_history'));

        // Direct streaming via admin-post.php (bypasses admin-ajax buffering)
        add_action('admin_post_chatpr_direct_stream', array($this, 'direct_stream_chat_message'));
        add_action('wp_ajax_chatpr_new_chat', array($this, 'new_chat'));
        add_action('wp_ajax_chatpr_get_chat_list', array($this, 'get_chat_list'));
        add_action('wp_ajax_chatpr_rename_chat', array($this, 'rename_chat'));
        add_action('wp_ajax_chatpr_delete_chat', array($this, 'delete_chat'));
        add_action('wp_ajax_chatpr_generate_chat_title', array($this, 'generate_chat_title'));

        // File management
        add_action('wp_ajax_chatpr_upload_file', array($this, 'upload_file'));
        add_action('wp_ajax_chatpr_list_files', array($this, 'list_files'));
        add_action('wp_ajax_chatpr_delete_file', array($this, 'delete_file'));
        add_action('wp_ajax_chatpr_import_from_media_library', array($this, 'import_from_media_library'));

        // Project management
        add_action('wp_ajax_chatpr_delete_project', array($this, 'delete_project'));
        add_action('wp_ajax_chatpr_update_project', array($this, 'update_project'));

        // User settings
        add_action('wp_ajax_chatpr_update_user_settings', array($this, 'update_user_settings'));

        // Project instructions
        add_action('wp_ajax_chatpr_update_project_instructions', array($this, 'update_project_instructions'));
    }
    
    // ==================== PROJECT MANAGEMENT ====================
    
    /**
     * Create a new project via AJAX
     */
    public function create_project() {
        check_ajax_referer('chatpr_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in to create projects', 'chatprojects')));
            return;
        }

        // Check if user has capability to create projects
        // Allow: publish_chatpr_projects, manage_options, or basic 'read' capability (for any registered user in free version)
        if (!current_user_can('publish_chatpr_projects') && !current_user_can('manage_options') && !current_user_can('read')) {
            wp_send_json_error(array('message' => __('You do not have permission to create projects.', 'chatprojects')));
            return;
        }

        $user_id = get_current_user_id();
        $title = $this->get_post_value('title');
        $description = $this->get_post_value('description', 'wp_kses_post', '');
        $instructions = $this->get_post_value('instructions', 'wp_kses_post', '');
        $category = $this->get_post_value('category', 'absint', 0);

        if (empty($title)) {
            wp_send_json_error(array('message' => __('Project title is required', 'chatprojects')));
        }

        $project_id = wp_insert_post(array(
            'post_type' => 'chatpr_project',
            'post_title' => $title,
            'post_content' => $description,
            'post_status' => 'publish',
            'post_author' => $user_id,
        ));

        if (is_wp_error($project_id)) {
            // Log detailed error for debugging, return generic message to user.
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('ChatProjects: Failed to create project - ' . $project_id->get_error_message());
            wp_send_json_error(array('message' => __('Failed to create project. Please try again.', 'chatprojects')));
        }

        // Set category if provided
        if ($category > 0) {
            wp_set_post_terms($project_id, array($category), 'chatpr_project_category');
        }

        // Save project-specific instructions (used by Responses API at chat time)
        $project_instructions = !empty($instructions)
            ? $instructions
            : get_option('chatprojects_assistant_instructions', 'You are a helpful assistant for this project. Use the uploaded files as knowledge base to answer questions.');
        update_post_meta($project_id, '_cp_instructions', $project_instructions);

        // Create Vector Store for file search (Responses API - no assistant needed)
        try {
            $vector_store_response = $this->get_api_handler()->create_vector_store($title);

            if (is_wp_error($vector_store_response)) {
                wp_delete_post($project_id, true);
                wp_send_json_error(array('message' => __('Failed to create vector store: ', 'chatprojects') . $vector_store_response->get_error_message()));
            }

            $vector_store_id = isset($vector_store_response['id']) ? $vector_store_response['id'] : null;

            if (empty($vector_store_id)) {
                wp_delete_post($project_id, true);
                wp_send_json_error(array('message' => __('Failed to create vector store: no ID returned', 'chatprojects')));
            }

            // Store vector store ID and model (no assistant needed with Responses API)
            update_post_meta($project_id, '_cp_vector_store_id', $vector_store_id);
            update_post_meta($project_id, '_cp_model', get_option('chatprojects_default_model', 'gpt-5.2'));
        } catch (\Exception $e) {
            wp_delete_post($project_id, true);
            wp_send_json_error(array('message' => __('Failed to create OpenAI resources: ', 'chatprojects') . $e->getMessage()));
        }

        wp_send_json_success(array(
            'message' => __('Project created successfully', 'chatprojects'),
            'project_id' => $project_id,
            'redirect_url' => get_permalink($project_id)
        ));
    }
    
    /**
     * Get user's projects list
     */
    public function get_projects() {
        check_ajax_referer('chatpr_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in', 'chatprojects')));
            return;
        }

        if (!current_user_can('read')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'chatprojects')));
            return;
        }

        $user_id = get_current_user_id();
        
        // Query user's projects
        $args = array(
            'post_type' => 'chatpr_project',
            'author' => $user_id,
            'posts_per_page' => -1,
            'orderby' => 'modified',
            'order' => 'DESC',
            'post_status' => 'publish'
        );
        
        $projects = get_posts($args);
        
        $projects_data = array();
        foreach ($projects as $project) {
            $categories = wp_get_post_terms($project->ID, 'chatpr_project_category', array('fields' => 'all'));

            $projects_data[] = array(
                'id' => $project->ID,
                'title' => html_entity_decode($project->post_title, ENT_QUOTES, 'UTF-8'),
                'description' => html_entity_decode($project->post_content, ENT_QUOTES, 'UTF-8'),
                'instructions' => get_post_meta($project->ID, '_cp_instructions', true),
                'categories' => $categories,
                'url' => get_permalink($project->ID),
                'modified' => $project->post_modified
            );
        }

        wp_send_json_success($projects_data);
    }
    
    /**
     * Delete project
     */
    public function delete_project() {
        check_ajax_referer('chatpr_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in', 'chatprojects')));
            return;
        }

        $project_id = $this->get_post_value('project_id', 'absint', 0);

        // Check project exists
        $project = get_post($project_id);
        if (!$project || $project->post_type !== 'chatpr_project') {
            wp_send_json_error(array('message' => __('Project not found.', 'chatprojects')));
            return;
        }

        // Use Access class for permission check: author or admin can delete
        if (!Access::can_delete_project($project_id)) {
            wp_send_json_error(array('message' => __('You do not have permission to delete this project.', 'chatprojects')));
            return;
        }
        
        // Delete associated vector store (no assistant with Responses API)
        try {
            $vector_store_id = get_post_meta($project_id, '_cp_vector_store_id', true);

            if ($vector_store_id) {
                $this->get_api_handler()->delete_vector_store($vector_store_id);
            }
        } catch (\Exception $e) {
            // Log error but continue with deletion
        }
        
        // Delete project
        $result = wp_delete_post($project_id, true);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Project deleted successfully', 'chatprojects')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete project', 'chatprojects')));
        }
    }
    
    // ==================== CHAT INTERFACE ====================
    
    /**
     * Send chat message
     */
    public function send_chat_message() {
        try {
            check_ajax_referer('chatpr_ajax_nonce', 'nonce');

            if (!is_user_logged_in()) {
                wp_send_json_error(array('message' => __('You must be logged in', 'chatprojects')));
                return;
            }

            $project_id = $this->get_post_value('project_id', 'absint', 0);
            $message = $this->get_post_value('message', 'sanitize_textarea_field', '');
            $thread_id = $this->get_post_value('thread_id', 'sanitize_text_field', '');
            
            if (empty($message)) {
                wp_send_json_error(array('message' => __('Message is required', 'chatprojects')));
                return;
            }
            
            if (empty($project_id)) {
                wp_send_json_error(array('message' => __('Project ID is required', 'chatprojects')));
                return;
            }
            
            // Check access
            if (!Access::can_access_project($project_id)) {
                wp_send_json_error(array('message' => __('Access denied', 'chatprojects')));
                return;
            }
            
            global $wpdb;
            $chats_table = esc_sql($wpdb->prefix . 'chatprojects_chats');

            // Get or create chat
            $chat_id = null;

            if (!empty($thread_id)) {
                // Find existing chat by thread_id (must belong to current user)
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query
                $chat = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM {$chats_table} WHERE thread_id = %s AND project_id = %d AND user_id = %d",
                        $thread_id,
                        $project_id,
                        get_current_user_id()
                    )
                );
                // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

                if ($chat) {
                    $chat_id = $chat->id;
                }
            }

            // Create new chat if none exists
            if (empty($chat_id)) {
                $chat_id = $this->get_chat_interface()->create_chat($project_id);

                if (is_wp_error($chat_id)) {
                    wp_send_json_error(array('message' => $chat_id->get_error_message()));
                    return;
                }
                // Get the newly created chat to get thread_id (verify ownership)
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query
                $chat = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM {$chats_table} WHERE id = %d AND user_id = %d",
                        $chat_id,
                        get_current_user_id()
                    )
                );
                // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

                if (!$chat) {
                    wp_send_json_error(array('message' => __('Failed to retrieve chat after creation', 'chatprojects')));
                    return;
                }

                $thread_id = $chat->thread_id;
            }

            // Send message
            $response = $this->get_chat_interface()->send_message($chat_id, $message);

            if (is_wp_error($response)) {
                wp_send_json_error(array('message' => $response->get_error_message()));
                return;
            }
            // Auto-generate title after first message exchange
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query
            $chat = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$chats_table} WHERE id = %d",
                    $chat_id
                )
            );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            // Auto-generate title if this is the first exchange and title is still the default
            if ($chat && $chat->message_count == 2 && (empty($chat->title) || strpos($chat->title, 'Chat ') === 0)) {
                try {
                    $api_handler = new API_Handler();
                    $prompt = "Based on this conversation, generate a short, concise title (3-6 words maximum):\n\nUser: {$message}\n\nAssistant: {$response['content']}\n\nRespond with ONLY the title, nothing else.";

                    $messages = array(
                        array('role' => 'user', 'content' => $prompt)
                    );

                    $title_response = $api_handler->create_chat_completion($messages, 'gpt-4o-mini');

                    if (!is_wp_error($title_response)) {
                        $title = isset($title_response['choices'][0]['message']['content'])
                            ? trim($title_response['choices'][0]['message']['content'])
                            : '';

                        // Remove quotes if present
                        $title = trim($title, '"\'');

                        // Limit length
                        if (strlen($title) > 50) {
                            $title = substr($title, 0, 47) . '...';
                        }

                        if (!empty($title)) {
                            $this->get_chat_interface()->update_chat_title($chat_id, $title);
                        }
                    } else {
                    }
                } catch (\Exception $e) {
                }
            }

            wp_send_json_success(array(
                'response' => $response['content'],
                'thread_id' => $thread_id,
                'chat_id' => $chat_id
            ));
            
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => __('Server error: ', 'chatprojects') . $e->getMessage()));
        }
    }

    /**
     * Stream chat message (Server-Sent Events)
     */
    public function stream_chat_message() {
        // Disable ALL output buffering for SSE
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Set SSE headers - order matters for some servers
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate, private');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('X-Accel-Buffering: no'); // Disable nginx/LiteSpeed buffering
        header('Connection: keep-alive');
        header('Transfer-Encoding: chunked');

        // LiteSpeed specific - disable cache and buffering
        header('X-LiteSpeed-Cache-Control: no-cache, no-store, esi=off');
        header('X-LiteSpeed-Tag: no-cache');
        header('X-LiteSpeed-Purge: *');

        // Cloudflare - disable buffering
        header('X-CF-Buffering: off');

        // Disable compression and enable implicit flush
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }
        if (function_exists('ini_set')) {
            // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Required for SSE streaming
            @ini_set('zlib.output_compression', '0');
            // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Required for SSE streaming
            @ini_set('implicit_flush', '1');
            // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Required for SSE streaming
            @ini_set('output_buffering', '0');
        }

        // Enable implicit flush (function form)
        @ob_implicit_flush(true);

        // Send padding to fill server buffer and force immediate streaming
        // Some servers buffer 8KB+ before sending
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Safe: SSE padding with spaces only
        echo ':' . str_repeat(' ', 8192) . "\n\n";
        @flush();

        // Send a keepalive comment to ensure connection is established
        echo ": stream started\n\n";
        @flush();

        try {
            // Use false as third param to prevent wp_die() on failure
            if (!check_ajax_referer('chatpr_ajax_nonce', 'nonce', false)) {
                $this->send_sse_error('Invalid security token. Please refresh the page.');
                return;
            }

            if (!is_user_logged_in()) {
                $this->send_sse_error('You must be logged in');
                return;
            }

            $project_id = $this->get_post_value('project_id', 'absint', 0);
            $message = $this->get_post_value('message', 'sanitize_textarea_field', '');
            $thread_id = $this->get_post_value('thread_id', 'sanitize_text_field', '');

            if (empty($message)) {
                $this->send_sse_error('Message is required');
                return;
            }

            if (empty($project_id)) {
                $this->send_sse_error('Project ID is required');
                return;
            }

            if (!Access::can_access_project($project_id)) {
                $this->send_sse_error('Access denied');
                return;
            }

            global $wpdb;
            $chats_table = $wpdb->prefix . 'chatprojects_chats';
            $chats_table_sql = esc_sql($chats_table);

            // Get or create chat
            $chat_id = null;

            if (!empty($thread_id)) {
                // Try to find chat by ID (thread_id is now just a legacy param, we'll use it as chat identifier)
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query
                $chat = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM {$chats_table_sql} WHERE id = %d AND project_id = %d AND user_id = %d",
                        intval($thread_id),
                        $project_id,
                        get_current_user_id()
                    )
                );
                // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

                if ($chat) {
                    $chat_id = $chat->id;
                }
            }

            // Create new chat if none exists
            if (empty($chat_id)) {
                $chat_id = $this->get_chat_interface()->create_chat($project_id);

                if (is_wp_error($chat_id)) {
                    $this->send_sse_error($chat_id->get_error_message());
                    return;
                }

                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query
                $chat = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM {$chats_table_sql} WHERE id = %d AND user_id = %d",
                        $chat_id,
                        get_current_user_id()
                    )
                );
                // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

                if (!$chat) {
                    $this->send_sse_error('Failed to retrieve chat after creation');
                    return;
                }
            }

            // Get vector_store_id for this project
            $vector_store_id = get_post_meta($project_id, '_cp_vector_store_id', true);
            if (empty($vector_store_id)) {
                $this->send_sse_error('No vector store configured for this project');
                return;
            }

            // Get project instructions from meta
            $instructions = get_post_meta($project_id, '_cp_instructions', true);
            if (empty($instructions)) {
                $instructions = get_option('chatprojects_assistant_instructions', '');
            }

            $model = get_post_meta($project_id, '_cp_model', true);
            if (empty($model)) {
                $model = get_option('chatprojects_default_model', 'gpt-5.2');
            }

            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging for streaming API
            error_log(sprintf('[ChatProjects] stream_chat_message start project_id=%d chat_id=%s thread_id=%s', $project_id, $chat_id, $thread_id));

            // Store user message in database
            $messages_table = $wpdb->prefix . 'chatprojects_messages';
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table requires direct query
            $wpdb->insert($messages_table, array(
                'chat_id' => $chat_id,
                'role' => 'user',
                'content' => $message,
                'created_at' => current_time('mysql'),
            ), array('%d', '%s', '%s', '%s'));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
            // Stream the response using Responses API
            $assistant_content = '';
            $sources = array();

            $this->get_api_handler()->stream_response_with_filesearch(
                $message,
                $vector_store_id,
                function($chunk) use (&$assistant_content, &$sources) {
                    if (isset($chunk['type']) && $chunk['type'] === 'content') {
                        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging for streaming API
                        error_log('[ChatProjects] SSE chunk type=content len=' . strlen($chunk['content']));
                        $assistant_content .= $chunk['content'];
                    } elseif (isset($chunk['type']) && $chunk['type'] === 'sources') {
                        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging for streaming API
                        error_log('[ChatProjects] SSE chunk type=sources count=' . count($chunk['sources']));
                        $sources = $chunk['sources'];
                    } elseif (isset($chunk['type']) && $chunk['type'] === 'error') {
                        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging for streaming API
                        error_log('[ChatProjects] SSE chunk type=error message=' . ($chunk['content'] ?? ''));
                    }

                    // Output immediately
                    echo "data: " . wp_json_encode($chunk) . "\n\n";

                    // Try all flush methods
                    if (function_exists('litespeed_flush')) {
                        litespeed_flush();
                    }
                    if (function_exists('fastcgi_finish_request')) {
                        // Don't call this - it ends the request
                    }
                    @ob_flush();
                    @flush();
                },
                $model,
                $instructions
            );

            // Store assistant message in database
            if (!empty($assistant_content)) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging for streaming API
                error_log(sprintf('[ChatProjects] SSE assistant message captured len=%d', strlen($assistant_content)));
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table requires direct query
                $wpdb->insert($messages_table, array(
                    'chat_id' => $chat_id,
                    'role' => 'assistant',
                    'content' => $assistant_content,
                    'metadata' => !empty($sources) ? wp_json_encode(array('sources' => $sources)) : null,
                    'created_at' => current_time('mysql'),
                ), array('%d', '%s', '%s', '%s', '%s'));
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
            }

            // Update message count and generate title BEFORE sending [DONE]
            $chats_table = $wpdb->prefix . 'chatprojects_chats';
            $chats_table_sql = esc_sql($chats_table);
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query
            $chat = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM `{$chats_table_sql}` WHERE id = %d",
                    $chat_id
                )
            );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

            if ($chat) {
                // Increment message count (user message + assistant response = 2)
                $new_count = $chat->message_count + 2;
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query
                $wpdb->update(
                    $chats_table,
                    array(
                        'message_count' => $new_count,
                        'updated_at' => current_time('mysql')
                    ),
                    array('id' => $chat_id),
                    array('%d', '%s'),
                    array('%d')
                );
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

                // Auto-generate title after first message exchange (BEFORE [DONE] so frontend receives it)
                if ($new_count == 2 && (empty($chat->title) || strpos($chat->title, 'Chat ') === 0)) {
                    try {
                        $api_handler = new API_Handler();
                        $prompt = "Based on this conversation, generate a short, concise title (3-6 words maximum):\n\nUser: {$message}\n\nAssistant: {$assistant_content}\n\nRespond with ONLY the title, nothing else.";

                        $messages = array(
                            array('role' => 'user', 'content' => $prompt)
                        );

                        $title_response = $api_handler->create_chat_completion($messages, 'gpt-4o-mini');

                        if (!is_wp_error($title_response)) {
                            $title = isset($title_response['choices'][0]['message']['content'])
                                ? trim($title_response['choices'][0]['message']['content'])
                                : '';

                            // Remove quotes if present
                            $title = trim($title, '"\'');

                            // Limit length
                            if (strlen($title) > 50) {
                                $title = substr($title, 0, 47) . '...';
                            }

                            if (!empty($title)) {
                                $this->get_chat_interface()->update_chat_title($chat_id, $title);
                                // Send title update to frontend BEFORE [DONE]
                                $this->send_sse_data(array(
                                    'type' => 'title_update',
                                    'chat_id' => $chat_id,
                                    'title' => $title
                                ));
                            }
                        }
                    } catch (\Exception $e) {
                        // Silently fail - title generation is not critical
                    }
                }
            }

            // Send chat_id for frontend to track (fixes chat splitting bug)
            $this->send_sse_data(array(
                'type' => 'chat_id',
                'chat_id' => $chat_id
            ));

            // Send completion signal LAST
            $this->send_sse_done();

        } catch (\Exception $e) {
            $this->send_sse_error('Server error: ' . $e->getMessage());
        }

        exit;
    }

    /**
     * Direct stream chat message via admin-post.php
     * This bypasses admin-ajax.php which has buffering issues on some servers
     */
    public function direct_stream_chat_message() {
        // Disable ALL output buffering FIRST
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Set SSE headers immediately
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate, private');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('X-Accel-Buffering: no');
        header('Connection: keep-alive');
        header('X-LiteSpeed-Cache-Control: no-cache, no-store, esi=off');
        header('X-LiteSpeed-Tag: no-cache');

        if (function_exists('ini_set')) {
            // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Required for SSE streaming
            @ini_set('zlib.output_compression', '0');
            // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Required for SSE streaming
            @ini_set('implicit_flush', '1');
            // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Required for SSE streaming
            @ini_set('output_buffering', '0');
        }
        @ob_implicit_flush(true);

        // Send 16KB padding to fill server buffer
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Safe: SSE padding with spaces only
        echo ':' . str_repeat(' ', 16384) . "\n\n";
        @flush();

        // Verify nonce - use manual check since check_ajax_referer expects admin-ajax
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified below
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'chatpr_ajax_nonce')) {
            $this->send_sse_error('Invalid security token');
            exit;
        }

        if (!is_user_logged_in()) {
            $this->send_sse_error('You must be logged in');
            exit;
        }

        $project_id = $this->get_post_value('project_id', 'absint', 0);
        $message = $this->get_post_value('message', 'sanitize_textarea_field', '');
        $thread_id = $this->get_post_value('thread_id', 'sanitize_text_field', '');

        if (empty($message)) {
            $this->send_sse_error('Message is required');
            exit;
        }

        if (empty($project_id)) {
            $this->send_sse_error('Project ID is required');
            exit;
        }

        if (!Access::can_access_project($project_id)) {
            $this->send_sse_error('Access denied');
            exit;
        }

        global $wpdb;
        $chats_table = $wpdb->prefix . 'chatprojects_chats';
        $chats_table_sql = esc_sql($chats_table);
        $user_id = get_current_user_id();

        // Get or create chat
        $chat_id = null;
        if (!empty($thread_id)) {
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table requires direct query
            $chat = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$chats_table} WHERE id = %d AND project_id = %d AND user_id = %d",
                    intval($thread_id),
                    $project_id,
                    $user_id
                )
            );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
            if ($chat) {
                $chat_id = $chat->id;
            }
        }

        if (empty($chat_id)) {
            $chat_id = $this->get_chat_interface()->create_chat($project_id);
            if (is_wp_error($chat_id)) {
                $this->send_sse_error($chat_id->get_error_message());
                exit;
            }
        }

        // Get project settings
        $vector_store_id = get_post_meta($project_id, '_cp_vector_store_id', true);
        if (empty($vector_store_id)) {
            $this->send_sse_error('No vector store configured for this project');
            exit;
        }

        $instructions = get_post_meta($project_id, '_cp_instructions', true);
        if (empty($instructions)) {
            $instructions = get_option('chatprojects_assistant_instructions', '');
        }

        $model = get_post_meta($project_id, '_cp_model', true);
        if (empty($model)) {
            $model = get_option('chatprojects_default_model', 'gpt-5.2');
        }

        // Store user message
        $messages_table = $wpdb->prefix . 'chatprojects_messages';
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table requires direct query
        $wpdb->insert($messages_table, array(
            'chat_id' => $chat_id,
            'role' => 'user',
            'content' => $message,
            'created_at' => current_time('mysql'),
        ), array('%d', '%s', '%s', '%s'));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

        // Clear buffers again before streaming
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Stream the response
        $assistant_content = '';
        $sources = array();

        $this->get_api_handler()->stream_response_with_filesearch(
            $message,
            $vector_store_id,
            function($chunk) use (&$assistant_content, &$sources) {
                if (isset($chunk['type']) && $chunk['type'] === 'content') {
                    $assistant_content .= $chunk['content'];
                } elseif (isset($chunk['type']) && $chunk['type'] === 'sources') {
                    $sources = $chunk['sources'];
                }

                echo "data: " . wp_json_encode($chunk) . "\n\n";

                if (function_exists('litespeed_flush')) {
                    litespeed_flush();
                }
                @ob_flush();
                @flush();
            },
            $model,
            $instructions
        );

        // Store assistant message
        if (!empty($assistant_content)) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table requires direct query
            $wpdb->insert($messages_table, array(
                'chat_id' => $chat_id,
                'role' => 'assistant',
                'content' => $assistant_content,
                'metadata' => !empty($sources) ? wp_json_encode(array('sources' => $sources)) : null,
                'created_at' => current_time('mysql'),
            ), array('%d', '%s', '%s', '%s', '%s'));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
        }

        // Send chat_id
        $this->send_sse_data(array('type' => 'chat_id', 'chat_id' => $chat_id));

        // Update message count
        $chats_table = $wpdb->prefix . 'chatprojects_chats';
        $chats_table_sql = esc_sql($chats_table);
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query
        $chat = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM `{$chats_table_sql}` WHERE id = %d",
                $chat_id
            )
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        if ($chat) {
            $new_count = $chat->message_count + 2;
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query
            $wpdb->update(
                $chats_table,
                array(
                    'message_count' => $new_count,
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $chat_id),
                array('%d', '%s'),
                array('%d')
            );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        }

        $this->send_sse_done();
        exit;
    }

    /**
     * Send SSE data chunk
     */
    private function send_sse_data($data) {
        echo "data: " . wp_json_encode($data) . "\n\n";

        // Flush output buffers if any exist
        if (ob_get_level() > 0) {
            @ob_flush();
        }
        @flush();
    }

    /**
     * Send SSE error
     */
    private function send_sse_error($message) {
        $this->send_sse_data(array(
            'type' => 'error',
            'content' => $message
        ));
        echo "data: [DONE]\n\n";
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    /**
     * Send SSE completion signal
     */
    private function send_sse_done() {
        echo "data: [DONE]\n\n";
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    /**
     * Load chat history
     */
    public function load_chat_history() {
        check_ajax_referer('chatpr_ajax_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in', 'chatprojects')));
        }
        
        $chat_id = $this->get_post_value('chat_id', 'absint', 0);
        
        if (!Access::can_access_chat($chat_id)) {
            wp_send_json_error(array('message' => __('Access denied', 'chatprojects')));
        }
        
        try {
            $messages = $this->get_chat_interface()->get_messages($chat_id);
            
            if (is_wp_error($messages)) {
                wp_send_json_error(array('message' => $messages->get_error_message()));
            }
            
            // Get chat details for thread_id
            $chat = $this->get_chat_interface()->get_chat($chat_id);
            
            if (is_wp_error($chat)) {
                wp_send_json_error(array('message' => $chat->get_error_message()));
            }
            
            wp_send_json_success(array(
                'messages' => $messages,
                'thread_id' => $chat->thread_id
            ));
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Create new chat session
     */
    public function new_chat() {
        check_ajax_referer('chatpr_ajax_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in', 'chatprojects')));
        }
        
        $project_id = $this->get_post_value('project_id', 'absint', 0);
        
        if (!Access::can_access_project($project_id)) {
            wp_send_json_error(array('message' => __('Access denied', 'chatprojects')));
        }
        
        try {
            $chat_id = $this->get_chat_interface()->create_chat($project_id);
            
            if (is_wp_error($chat_id)) {
                wp_send_json_error(array('message' => $chat_id->get_error_message()));
            }
            
            wp_send_json_success(array(
                'chat_id' => $chat_id,
                'message' => __('New chat created', 'chatprojects')
            ));
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    /**
     * Get chat list for a project
     */
    public function get_chat_list() {
        check_ajax_referer('chatpr_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in', 'chatprojects')));
        }

        $project_id = $this->get_post_value('project_id', 'absint', 0);
        $chat_mode = $this->get_post_value('chat_mode', 'sanitize_text_field', null);

        // For Pro Chat (general mode), skip project access check
        if ($chat_mode !== 'general') {
            if (!Access::can_access_project($project_id)) {
                wp_send_json_error(array('message' => __('Access denied', 'chatprojects')));
            }
        }

        try {
            // Get chats based on mode (always filter by current user)
            if ($chat_mode === 'general') {
                $chats = $this->get_chat_interface()->get_general_chats(get_current_user_id());
            } else {
                $chats = $this->get_chat_interface()->get_project_chats($project_id, get_current_user_id());
            }
            
            // Format chats for frontend
            $formatted_chats = array();
            foreach ($chats as $chat) {
                $formatted_chats[] = array(
                    'id' => $chat->id,
                    'thread_id' => $chat->thread_id,
                    'title' => $chat->title,
                    'message_count' => $chat->message_count,
                    'created_at' => $chat->created_at,
                    'updated_at' => $chat->updated_at,
                );
            }
            
            wp_send_json_success(array('chats' => $formatted_chats));
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Rename chat
     */
    public function rename_chat() {
        check_ajax_referer('chatpr_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in', 'chatprojects')));
        }

        // Accept either chat_id or thread_id
        $chat_id = $this->get_post_value('chat_id', 'absint', 0);
        $thread_id = $this->get_post_value('thread_id', 'sanitize_text_field', '');
        $title = $this->get_post_value('title', 'sanitize_text_field', '');

        if (empty($title)) {
            wp_send_json_error(array('message' => __('Title is required', 'chatprojects')));
        }

        // If thread_id provided, look up chat_id (must belong to current user)
        if (empty($chat_id) && !empty($thread_id)) {
            global $wpdb;
            $chats_table = esc_sql($wpdb->prefix . 'chatprojects_chats');
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query
            $chat = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id FROM {$chats_table} WHERE thread_id = %s AND user_id = %d",
                    $thread_id,
                    get_current_user_id()
                )
            );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

            if ($chat) {
                $chat_id = $chat->id;
            }
        }

        if (empty($chat_id)) {
            wp_send_json_error(array('message' => __('Chat not found', 'chatprojects')));
        }

        if (!Access::can_access_chat($chat_id)) {
            wp_send_json_error(array('message' => __('Access denied', 'chatprojects')));
        }

        try {
            $result = $this->get_chat_interface()->update_chat_title($chat_id, $title);

            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()));
            }

            wp_send_json_success(array('message' => __('Chat renamed successfully', 'chatprojects')));
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Delete chat
     */
    public function delete_chat() {
        check_ajax_referer('chatpr_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in', 'chatprojects')));
        }

        // Accept either chat_id or thread_id
        $chat_id = $this->get_post_value('chat_id', 'absint', 0);
        $thread_id = $this->get_post_value('thread_id', 'sanitize_text_field', '');

        // If thread_id provided, look up chat_id (must belong to current user)
        if (empty($chat_id) && !empty($thread_id)) {
            global $wpdb;
            $chats_table = esc_sql($wpdb->prefix . 'chatprojects_chats');
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query
            $chat = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id FROM {$chats_table} WHERE thread_id = %s AND user_id = %d",
                    $thread_id,
                    get_current_user_id()
                )
            );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

            if ($chat) {
                $chat_id = $chat->id;
            }
        }

        if (empty($chat_id)) {
            wp_send_json_error(array('message' => __('Chat not found', 'chatprojects')));
        }

        if (!Access::can_access_chat($chat_id)) {
            wp_send_json_error(array('message' => __('Access denied. You can only delete your own chats.', 'chatprojects')));
        }

        try {
            $result = $this->get_chat_interface()->delete_chat($chat_id);

            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()));
            }

            wp_send_json_success(array('message' => __('Chat deleted successfully', 'chatprojects')));
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Generate chat title automatically from conversation
     */
    public function generate_chat_title() {
        check_ajax_referer('chatpr_ajax_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in', 'chatprojects')));
        }
        
        $chat_id = $this->get_post_value('chat_id', 'absint', 0);
        $user_message = $this->get_post_value('user_message', 'sanitize_textarea_field', '');
        $assistant_response = $this->get_post_value('assistant_response', 'sanitize_textarea_field', '');
        
        if (!Access::can_access_chat($chat_id)) {
            wp_send_json_error(array('message' => __('Access denied', 'chatprojects')));
        }
        
        try {
            // Use OpenAI to generate a concise title from the conversation
            $prompt = "Based on this conversation, generate a short, concise title (3-6 words maximum):\n\nUser: {$user_message}\n\nAssistant: {$assistant_response}\n\nRespond with ONLY the title, nothing else.";
            
            $messages = array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            );
            
            $response = $this->get_api_handler()->create_chat_completion($messages, 'gpt-4o-mini');
            
            if (is_wp_error($response)) {
                // Fallback: use first few words of user message
                $words = explode(' ', $user_message);
                $title = implode(' ', array_slice($words, 0, 5));
                if (count($words) > 5) {
                    $title .= '...';
                }
            } else {
                $title = isset($response['choices'][0]['message']['content'])
                    ? trim($response['choices'][0]['message']['content'])
                    : 'New Chat';
                
                // Remove quotes if present
                $title = trim($title, '"\'');
                
                // Limit length
                if (strlen($title) > 50) {
                    $title = substr($title, 0, 47) . '...';
                }
            }
            
            // Update the chat title
            $result = $this->get_chat_interface()->update_chat_title($chat_id, $title);
            
            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()));
            }
            
            wp_send_json_success(array(
                'title' => $title,
                'message' => __('Title generated successfully', 'chatprojects')
            ));
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    
    // ==================== FILE MANAGEMENT ====================
    
    /**
     * Upload file to vector store
     */
    public function upload_file() {
        check_ajax_referer('chatpr_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in', 'chatprojects')));
            return;
        }

        $project_id = $this->get_post_value('project_id', 'absint', 0);

        // Free version: any logged-in user can upload to any project they can access
        if (!Access::can_access_project($project_id)) {
            wp_send_json_error(array('message' => __('Access denied', 'chatprojects')));
            return;
        }
        
        $file = $this->get_uploaded_file('file');

        if (empty($file) || empty($file['tmp_name'])) {
            wp_send_json_error(array('message' => __('No file uploaded', 'chatprojects')));
        }

        // Validate file using Security class (includes Excel support)
        if (!Security::validate_file_type($file['name'])) {
            wp_send_json_error(array('message' => __('File type not allowed', 'chatprojects')));
        }
        
        // Check file size (50MB max)
        if ($file['size'] > 50 * 1024 * 1024) {
            wp_send_json_error(array('message' => __('File size exceeds 50MB limit', 'chatprojects')));
        }
        
        try {
            // Use handle_upload which includes Excel conversion logic
            $file_data = $this->get_vector_store()->handle_upload($project_id, $file);

            if (is_wp_error($file_data)) {
                wp_send_json_error(array('message' => $file_data->get_error_message()));
                return;
            }
            wp_send_json_success(array(
                'file' => $file_data,
                'message' => __('File uploaded successfully', 'chatprojects')
            ));
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * List project files
     */
    public function list_files() {
        try {
            check_ajax_referer('chatpr_ajax_nonce', 'nonce');
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => __('Security check failed', 'chatprojects')));
            return;
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in', 'chatprojects')));
            return;
        }
        
        $project_id = $this->get_post_value('project_id', 'absint', 0);
        
        if (empty($project_id)) {
            wp_send_json_error(array('message' => __('Project ID is required', 'chatprojects')));
            return;
        }
        
        if (!Access::can_access_project($project_id)) {
            wp_send_json_error(array('message' => __('Access denied', 'chatprojects')));
            return;
        }
        
        try {
            $files = $this->get_vector_store()->get_files($project_id);
            
            if (is_wp_error($files)) {
                wp_send_json_error(array('message' => $files->get_error_message()));
                return;
            }
            
            wp_send_json_success(array('files' => $files));
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Delete file from vector store
     */
    public function delete_file() {
        check_ajax_referer('chatpr_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in', 'chatprojects')));
            return;
        }

        $project_id = $this->get_post_value('project_id', 'absint', 0);
        $file_id = $this->get_post_value('file_id', 'sanitize_text_field', '');

        // Check if user can edit this project (owner or admin)
        if (!Access::can_edit_project($project_id)) {
            wp_send_json_error(array('message' => __('You do not have permission to delete files from this project.', 'chatprojects')));
            return;
        }
        
        try {
            $this->get_vector_store()->delete_file($project_id, $file_id);

            wp_send_json_success(array('message' => __('File deleted successfully', 'chatprojects')));
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Import files from WordPress Media Library to vector store
     */
    public function import_from_media_library() {
        // Extend PHP timeout for file upload operations.
        // phpcs:disable Squiz.PHP.DiscouragedFunctions.Discouraged -- Required for large file imports
        @set_time_limit(300);
        @ini_set('max_execution_time', 300);
        // phpcs:enable Squiz.PHP.DiscouragedFunctions.Discouraged

        check_ajax_referer('chatpr_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in', 'chatprojects')));
        }

        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $attachment_ids_json = isset($_POST['attachment_ids']) ? sanitize_text_field(wp_unslash($_POST['attachment_ids'])) : '';

        if (empty($project_id)) {
            wp_send_json_error(array('message' => __('Project ID is required', 'chatprojects')));
        }

        if (!Access::can_edit_project($project_id)) {
            wp_send_json_error(array('message' => __('Access denied', 'chatprojects')));
        }

        $attachment_ids = json_decode($attachment_ids_json, true);
        if (!is_array($attachment_ids) || empty($attachment_ids)) {
            wp_send_json_error(array('message' => __('No files selected', 'chatprojects')));
        }

        // Limit to 10 files at once
        if (count($attachment_ids) > 10) {
            wp_send_json_error(array('message' => __('Maximum 10 files can be imported at once', 'chatprojects')));
        }

        try {
            $results = $this->get_vector_store()->import_from_media_library($project_id, $attachment_ids);

            if (is_wp_error($results)) {
                wp_send_json_error(array('message' => $results->get_error_message()));
            }

            wp_send_json_success($results);
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        } catch (\Error $e) {
            wp_send_json_error(array('message' => 'A server error occurred: ' . $e->getMessage()));
        }
    }

    // ==================== USER SETTINGS ====================

    /**
     * Update user settings
     */
    public function update_user_settings() {
        // Verify nonce
        check_ajax_referer('chatpr_ajax_nonce', 'nonce');

        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in.', 'chatprojects')));
            return;
        }

        // Check capability
        if (!current_user_can('read')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'chatprojects')));
            return;
        }

        $user_id = get_current_user_id();

        // Update display name
        if (isset($_POST['display_name'])) {
            $display_name = $this->get_post_value('display_name');
            wp_update_user(array(
                'ID' => $user_id,
                'display_name' => $display_name
            ));
        }

        // Update email
        if (isset($_POST['user_email'])) {
            $user_email = $this->get_post_value('user_email', 'sanitize_email', '');
            if (is_email($user_email)) {
                wp_update_user(array(
                    'ID' => $user_id,
                    'user_email' => $user_email
                ));
            }
        }

        // Update theme preference
        if (isset($_POST['theme_preference'])) {
            $theme_preference = $this->get_post_value('theme_preference');
            if (in_array($theme_preference, array('light', 'dark', 'auto'))) {
                update_user_meta($user_id, 'cp_theme_preference', $theme_preference);
            }
        }

        wp_send_json_success(array('message' => __('Settings saved successfully.', 'chatprojects')));
    }

    /**
     * Update project details
     */
    public function update_project() {
        // Verify nonce
        check_ajax_referer('chatpr_ajax_nonce', 'nonce');

        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in.', 'chatprojects')));
            return;
        }

        $project_id = $this->get_post_value('project_id', 'absint', 0);

        if (!$project_id) {
            wp_send_json_error(array('message' => __('Invalid project ID.', 'chatprojects')));
            return;
        }

        // Check if user can edit this project (owner or admin)
        if (!Access::can_edit_project($project_id)) {
            wp_send_json_error(array('message' => __('You do not have permission to edit this project.', 'chatprojects')));
            return;
        }

        // Update project
        $update_data = array('ID' => $project_id);

        if (isset($_POST['title'])) {
            $update_data['post_title'] = $this->get_post_value('title');
        }

        if (isset($_POST['description'])) {
            $update_data['post_content'] = $this->get_post_value('description', 'wp_kses_post', '');
        }

        $result = wp_update_post($update_data);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        // Update category if provided
        if (isset($_POST['category'])) {
            $category = $this->get_post_value('category', 'absint', 0);
            if ($category > 0) {
                wp_set_post_terms($project_id, array($category), 'chatpr_project_category');
            } else {
                // Remove category if empty value sent
                wp_set_post_terms($project_id, array(), 'chatpr_project_category');
            }
        }

        // Update meta fields
        if (isset($_POST['instructions'])) {
            update_post_meta($project_id, '_cp_instructions', $this->get_post_value('instructions', 'wp_kses_post', ''));
        }

        if (isset($_POST['model'])) {
            update_post_meta($project_id, '_cp_model', $this->get_post_value('model'));
        }

        wp_send_json_success(array('message' => __('Project updated successfully.', 'chatprojects')));
    }

    // ==================== PROJECT INSTRUCTIONS ====================

    /**
     * Update project instructions via AJAX
     */
    public function update_project_instructions() {
        // Verify nonce
        check_ajax_referer('chatpr_ajax_nonce', 'nonce');

        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in.', 'chatprojects')));
            return;
        }

        $project_id = $this->get_post_value('project_id', 'absint', 0);
        $instructions = $this->get_post_value('instructions', 'wp_kses_post', '');

        if (!$project_id) {
            wp_send_json_error(array('message' => __('Invalid project ID.', 'chatprojects')));
            return;
        }

        // Check if user can edit this project (owner or admin)
        if (!Access::can_edit_project($project_id)) {
            $project = get_post($project_id);
            $debug = array(
                'current_user_id' => get_current_user_id(),
                'project_author' => $project ? $project->post_author : 'no project',
                'post_type' => $project ? $project->post_type : 'no project',
            );
            wp_send_json_error(array(
                'message' => __('You do not have permission to edit this project.', 'chatprojects'),
                'debug' => $debug
            ));
            return;
        }

        // Update instructions in post meta
        update_post_meta($project_id, '_cp_instructions', $instructions);

        wp_send_json_success(array(
            'message' => __('Instructions updated successfully.', 'chatprojects'),
            'instructions' => $instructions,
            'preview' => wp_trim_words($instructions, 30, '...')
        ));
    }
}

// Initialize AJAX handlers on init hook
add_action('init', function() {
    try {
        new \ChatProjects\AJAX_Handlers();
        new \ChatProjects\General_Chat_Ajax();
        // Note: Comparison_Ajax removed in Free version
    } catch (\Exception $e) {
    }
}, 10);
