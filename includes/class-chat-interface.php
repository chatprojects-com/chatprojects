<?php
/**
 * Chat Interface Class
 *
 * Handles chat functionality using Responses API and local message storage
 * Updated to remove thread-based approach
 *
 * @package ChatProjects
 */

namespace ChatProjects;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Chat Interface Class
 */
class Chat_Interface {
    /**
     * API Handler instance
     *
     * @var API_Handler
     */
    private $api;

    /**
     * Message Store instance
     *
     * @var Message_Store
     */
    private $message_store;

    /**
     * Chats table name for CRUD helpers
     *
     * @var string
     */
    private $chats_table;

    /**
     * Chats table name sanitized for manual SQL strings
     *
     * @var string
     */
    private $chats_table_sql;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;

        $this->api = new API_Handler();
        $this->message_store = new Message_Store();
        $this->chats_table = $wpdb->prefix . 'chatprojects_chats';
        $this->chats_table_sql = esc_sql($this->chats_table);
    }

    /**
     * Provider factory
     * Returns appropriate provider instance
     *
     * @param string $provider Provider identifier
     * @return Providers\AI_Provider_Interface|WP_Error
     */
    private function get_provider($provider) {
        $provider_map = array(
            'openai' => 'OpenAI_Provider',
            'gemini' => 'Gemini_Provider',
            'anthropic' => 'Anthropic_Provider',
            'chutes' => 'Chutes_Provider',
            'openrouter' => 'OpenRouter_Provider',
        );

        if (!isset($provider_map[$provider])) {
            return new \WP_Error('invalid_provider', __('Invalid AI provider.', 'chatprojects'));
        }

        $class_name = 'ChatProjects\\Providers\\' . $provider_map[$provider];

        if (!class_exists($class_name)) {
            return new \WP_Error('provider_not_found', __('Provider class not found.', 'chatprojects'));
        }

        return new $class_name();
    }

    /**
     * Create a new general chat
     * No more thread creation - just database record
     *
     * @param string $provider Provider identifier
     * @param string $model Model identifier
     * @param string $title Chat title (optional)
     * @return int|WP_Error Chat ID or error
     */
    public function create_general_chat($provider, $model, $title = '') {
        global $wpdb;

        // Validate provider exists
        $provider_instance = $this->get_provider($provider);
        if (is_wp_error($provider_instance)) {
            return $provider_instance;
        }

        // Validate user is logged in
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new \WP_Error('not_logged_in', __('You must be logged in to create a chat.', 'chatprojects'));
        }

        // Generate title if not provided
        if (empty($title)) {
            /* translators: %s: Date and time in Y-m-d H:i:s format */
            $title = sprintf(__('General Chat %s', 'chatprojects'), gmdate('Y-m-d H:i:s'));
        }

        // Insert chat record (no thread_id needed)
        $table = $this->chats_table;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table requires direct query
        $result = $wpdb->insert($table, array(
            'chat_mode' => 'general',
            'provider' => $provider,
            'model' => $model,
            'project_id' => null,
            'thread_id' => null, // No longer used
            'user_id' => $user_id,
            'title' => sanitize_text_field($title),
            'message_count' => 0,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ), array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s'));

        if ($result === false) {
            return new \WP_Error('db_error', __('Failed to create chat record.', 'chatprojects'));
        }

        return $wpdb->insert_id;
    }

    /**
     * Send message to general chat
     * Uses local message storage + provider run_completion
     *
     * @param int $chat_id Chat ID
     * @param string $message Message content
     * @param array $images Optional array of base64 image data URLs
     * @return array|WP_Error Message data or error
     */
    public function send_general_message($chat_id, $message, $images = array()) {
        global $wpdb;

        // Get chat details
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table requires direct query
        $chat = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->chats_table_sql} WHERE id = %d AND user_id = %d",
            $chat_id,
            get_current_user_id()
        ));
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if (!$chat) {
            return new \WP_Error('invalid_chat', __('Invalid chat ID.', 'chatprojects'));
        }

        if ($chat->chat_mode !== 'general') {
            return new \WP_Error('wrong_mode', __('This is not a general chat.', 'chatprojects'));
        }

        // Get provider instance
        $provider_instance = $this->get_provider($chat->provider);
        if (is_wp_error($provider_instance)) {
            return $provider_instance;
        }

        // Save user message to database
        $user_metadata = !empty($images) ? array('images' => $images) : array();
        $this->message_store->save_message($chat_id, 'user', $message, $user_metadata);

        // Get assistant instructions (global default or chat-specific)
        $instructions = $chat->instructions;
        if (empty($instructions)) {
            $instructions = get_option('chatprojects_assistant_instructions', '');
        }

        // Build messages array from history
        $messages = $this->message_store->get_messages_for_api($chat_id, 20, $instructions);

        // If current message has images, update the last message to include them
        if (!empty($images) && !empty($messages)) {
            $last_idx = count($messages) - 1;
            if ($messages[$last_idx]['role'] === 'user') {
                $messages[$last_idx]['images'] = $images;
            }
        }

        // Run completion with provider
        $response = $provider_instance->run_completion($messages, $chat->model, array(
            'instructions' => $instructions,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        // Extract content from response
        $assistant_content = isset($response['content']) ? $response['content'] : '';

        if (empty($assistant_content)) {
            return new \WP_Error('no_response', __('No response from provider.', 'chatprojects'));
        }

        // Save assistant message to database
        $this->message_store->save_message($chat_id, 'assistant', $assistant_content);

        // Update chat metadata
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query
        $wpdb->update($this->chats_table, array(
            'message_count' => $this->message_store->count_messages($chat_id),
            'updated_at' => current_time('mysql'),
        ), array('id' => $chat_id), array('%d', '%s'), array('%d'));

        return array(
            'role' => 'assistant',
            'content' => $assistant_content,
            'created_at' => current_time('mysql'),
        );
    }

    /**
     * Get chat history for general chat
     * Now reads from local message storage
     *
     * @param int $chat_id Chat ID
     * @return array|WP_Error Array of messages or error
     */
    public function get_general_chat_history($chat_id) {
        global $wpdb;

        // Get chat details
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table requires direct query
        $chat = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->chats_table_sql} WHERE id = %d AND user_id = %d",
            $chat_id,
            get_current_user_id()
        ));
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if (!$chat) {
            return new \WP_Error('invalid_chat', __('Invalid chat ID.', 'chatprojects'));
        }

        if ($chat->chat_mode !== 'general') {
            return new \WP_Error('wrong_mode', __('This is not a general chat.', 'chatprojects'));
        }

        // Get messages from local storage
        $messages = $this->message_store->get_messages($chat_id);

        // Format for frontend
        $formatted = array();
        foreach ($messages as $message) {
            $formatted_message = array(
                'id' => $message['id'],
                'role' => $message['role'],
                'content' => $message['content'],
                'created_at' => $message['created_at'],
            );

            // Include images from metadata if present
            if (!empty($message['metadata']) && isset($message['metadata']['images'])) {
                $formatted_message['images'] = $message['metadata']['images'];
            }

            $formatted[] = $formatted_message;
        }

        return $formatted;
    }

    /**
     * Delete general chat
     * Ensures all messages are deleted for privacy
     *
     * @param int $chat_id Chat ID
     * @return bool|WP_Error True on success, error on failure
     */
    public function delete_general_chat($chat_id) {
        global $wpdb;

        // Get chat details to verify ownership
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table requires direct query
        $chat = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->chats_table_sql} WHERE id = %d AND user_id = %d",
            $chat_id,
            get_current_user_id()
        ));
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if (!$chat) {
            return new \WP_Error('invalid_chat', __('Invalid chat ID.', 'chatprojects'));
        }

        // Delete all messages first (privacy compliance)
        $this->message_store->delete_chat_messages($chat_id);

        // Delete chat record
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query
        $result = $wpdb->delete($this->chats_table, array('id' => $chat_id), array('%d'));

        if ($result === false) {
            return new \WP_Error('db_error', __('Failed to delete chat record.', 'chatprojects'));
        }

        return true;
    }

    /**
     * Create a new project chat
     *
     * @param int $project_id Project ID
     * @param string $title Chat title (optional)
     * @return int|WP_Error Chat ID or error
     */
    public function create_chat($project_id, $title = '') {
        global $wpdb;

        // Check permissions
        if (!Access::can_access_project($project_id)) {
            return new \WP_Error('permission_denied', __('You do not have permission to create chats for this project.', 'chatprojects'));
        }

        // Check that project has vector store configured
        $vector_store_id = get_post_meta($project_id, '_cp_vector_store_id', true);

        if (empty($vector_store_id)) {
            return new \WP_Error('no_vector_store', __('Project does not have a vector store configured.', 'chatprojects'));
        }

        // Generate title if not provided
        if (empty($title)) {
            /* translators: %s: Date and time in Y-m-d H:i:s format */
            $title = sprintf(__('Chat %s', 'chatprojects'), gmdate('Y-m-d H:i:s'));
        }

        // Insert chat record
        $table = $this->chats_table;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table requires direct query
        $result = $wpdb->insert($table, array(
            'chat_mode' => 'project',
            'provider' => 'openai',
            'project_id' => $project_id,
            'user_id' => get_current_user_id(),
            'title' => sanitize_text_field($title),
            'message_count' => 0,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ), array('%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s'));

        if ($result === false) {
            return new \WP_Error('db_error', __('Failed to create chat record.', 'chatprojects'));
        }

        return $wpdb->insert_id;
    }

    /**
     * Send message to project chat (using Responses API with file search)
     *
     * @param int $chat_id Chat ID
     * @param string $message Message content
     * @return array|WP_Error Message data or error
     */
    public function send_message($chat_id, $message) {
        global $wpdb;

        // Check permissions
        if (!Access::can_access_chat($chat_id)) {
            return new \WP_Error('permission_denied', __('You do not have permission to access this chat.', 'chatprojects'));
        }

        // Get chat details
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table requires direct query
        $chat = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->chats_table_sql} WHERE id = %d AND user_id = %d",
            $chat_id,
            get_current_user_id()
        ));
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if (!$chat) {
            return new \WP_Error('invalid_chat', __('Invalid chat ID or access denied.', 'chatprojects'));
        }

        // Get vector store ID
        $vector_store_id = get_post_meta($chat->project_id, '_cp_vector_store_id', true);

        if (empty($vector_store_id)) {
            return new \WP_Error('no_vector_store', __('Project does not have a vector store configured.', 'chatprojects'));
        }

        // Get project instructions
        $instructions = get_post_meta($chat->project_id, '_cp_instructions', true);
        if (empty($instructions)) {
            $instructions = get_option('chatprojects_assistant_instructions', '');
        }

        // Get model from project or use default
        $model = get_post_meta($chat->project_id, '_cp_model', true);
        if (empty($model)) {
            $model = get_option('chatprojects_default_model', 'gpt-5.2');
        }

        // Save user message to local storage
        $this->message_store->save_message($chat_id, 'user', $message);

        // Build conversation context from history
        $history = $this->message_store->get_recent_messages($chat_id, 10);

        // Build input for Responses API - include conversation context
        $conversation_context = '';
        foreach ($history as $msg) {
            if ($msg['role'] === 'user') {
                $conversation_context .= "User: " . $msg['content'] . "\n\n";
            } else if ($msg['role'] === 'assistant') {
                $conversation_context .= "Assistant: " . $msg['content'] . "\n\n";
            }
        }

        // The current message with context
        $input_with_context = $conversation_context . "User: " . $message;

        // Call Responses API with file_search
        $response = $this->api->create_response_with_filesearch(
            $input_with_context,
            $vector_store_id,
            $model,
            $instructions,
            array('max_num_results' => 5)
        );

        if (is_wp_error($response)) {
            return $response;
        }

        // Extract response content
        $assistant_content = $this->api->extract_response_text($response);
        $sources = $this->extract_sources_from_response($response);

        if (empty($assistant_content)) {
            return new \WP_Error('no_response', __('No response from assistant.', 'chatprojects'));
        }

        // Save assistant message
        $metadata = !empty($sources) ? array('sources' => $sources) : array();
        $this->message_store->save_message($chat_id, 'assistant', $assistant_content, $metadata);

        // Update chat metadata
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query
        $wpdb->update($this->chats_table, array(
            'message_count' => $this->message_store->count_messages($chat_id),
            'updated_at' => current_time('mysql'),
        ), array('id' => $chat_id), array('%d', '%s'), array('%d'));

        return array(
            'role' => 'assistant',
            'content' => $assistant_content,
            'sources' => $sources,
            'created_at' => current_time('mysql'),
        );
    }

    /**
     * Extract sources/citations from Responses API response
     *
     * @param array $response API response
     * @return array Sources array
     */
    private function extract_sources_from_response($response) {
        $sources = array();

        if (isset($response['output']) && is_array($response['output'])) {
            foreach ($response['output'] as $output_item) {
                if (isset($output_item['type']) && $output_item['type'] === 'message') {
                    if (isset($output_item['content']) && is_array($output_item['content'])) {
                        foreach ($output_item['content'] as $content_item) {
                            if (isset($content_item['annotations']) && is_array($content_item['annotations'])) {
                                foreach ($content_item['annotations'] as $annotation) {
                                    if (isset($annotation['type']) && $annotation['type'] === 'file_citation') {
                                        $sources[] = array(
                                            'file_id' => $annotation['file_id'] ?? '',
                                            'filename' => $annotation['filename'] ?? ''
                                        );
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // Deduplicate sources
        $unique_sources = array();
        foreach ($sources as $source) {
            $key = $source['file_id'];
            if (!isset($unique_sources[$key])) {
                $unique_sources[$key] = $source;
            }
        }

        return array_values($unique_sources);
    }

    /**
     * Get chat messages
     *
     * @param int $chat_id Chat ID
     * @param int $limit Number of messages to retrieve
     * @return array|WP_Error Messages or error
     */
    public function get_messages($chat_id, $limit = 20) {
        // Check permissions
        if (!Access::can_access_chat($chat_id)) {
            return new \WP_Error('permission_denied', __('You do not have permission to access this chat.', 'chatprojects'));
        }

        // Get messages from local storage
        $messages = $this->message_store->get_messages($chat_id, $limit);

        // Format for frontend
        $formatted = array();
        foreach ($messages as $message) {
            $formatted_message = array(
                'id' => $message['id'],
                'role' => $message['role'],
                'content' => $message['content'],
                'created_at' => $message['created_at'],
            );

            // Include sources from metadata
            if (!empty($message['metadata']) && isset($message['metadata']['sources'])) {
                $formatted_message['sources'] = $message['metadata']['sources'];
            }

            $formatted[] = $formatted_message;
        }

        return $formatted;
    }

    /**
     * Delete chat
     * Ensures all messages are deleted for privacy compliance
     *
     * @param int $chat_id Chat ID
     * @return bool|WP_Error True on success, error on failure
     */
    public function delete_chat($chat_id) {
        global $wpdb;

        // Check permissions
        if (!Access::can_access_chat($chat_id)) {
            return new \WP_Error('permission_denied', __('You do not have permission to delete this chat.', 'chatprojects'));
        }

        // Get chat details
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table requires direct query
        $chat = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->chats_table_sql} WHERE id = %d AND user_id = %d",
            $chat_id,
            get_current_user_id()
        ));
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if (!$chat) {
            return new \WP_Error('invalid_chat', __('Invalid chat ID or access denied.', 'chatprojects'));
        }

        // Delete messages first (privacy compliance - must delete all data)
        $this->message_store->delete_chat_messages($chat_id);

        // Delete chat record
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query
        $result = $wpdb->delete($this->chats_table, array('id' => $chat_id), array('%d'));

        if ($result === false) {
            return new \WP_Error('db_error', __('Failed to delete chat record.', 'chatprojects'));
        }

        return true;
    }

    /**
     * Get user's chats for a project
     *
     * @param int $project_id Project ID
     * @param int $user_id User ID (optional, defaults to current user)
     * @return array|WP_Error Chats or error
     */
    public function get_project_chats($project_id, $user_id = null) {
        global $wpdb;

        if (null === $user_id) {
            $user_id = get_current_user_id();
        }

        // Check permissions
        if (!Access::can_access_project($project_id)) {
            return new \WP_Error('permission_denied', __('You do not have permission to access this project.', 'chatprojects'));
        }

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table requires direct query
        $chats = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->chats_table_sql} WHERE project_id = %d AND user_id = %d ORDER BY updated_at DESC",
            $project_id,
            $user_id
        ));
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

        return $chats ?: array();
    }

    /**
     * Get general chats for a user
     *
     * @param int $user_id User ID
     * @return array Chats
     */
    public function get_general_chats($user_id = null) {
        global $wpdb;

        if (null === $user_id) {
            $user_id = get_current_user_id();
        }

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table requires direct query
        $chats = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->chats_table_sql} WHERE chat_mode = 'general' AND user_id = %d ORDER BY updated_at DESC",
            $user_id
        ));
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

        return $chats ?: array();
    }

    /**
     * Update chat title
     *
     * @param int $chat_id Chat ID
     * @param string $title New title
     * @return bool|WP_Error True on success, error on failure
     */
    public function update_chat_title($chat_id, $title) {
        global $wpdb;

        // Check permissions
        if (!Access::can_access_chat($chat_id)) {
            return new \WP_Error('permission_denied', __('You do not have permission to update this chat.', 'chatprojects'));
        }

        $table = $this->chats_table;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query
        $result = $wpdb->update($table, array(
            'title' => sanitize_text_field($title),
            'updated_at' => current_time('mysql'),
        ), array('id' => $chat_id), array('%s', '%s'), array('%d'));

        if ($result === false) {
            return new \WP_Error('db_error', __('Failed to update chat title.', 'chatprojects'));
        }

        return true;
    }

    /**
     * Get chat details
     *
     * @param int $chat_id Chat ID
     * @return object|WP_Error Chat data or error
     */
    public function get_chat($chat_id) {
        global $wpdb;

        // Check permissions
        if (!Access::can_access_chat($chat_id)) {
            return new \WP_Error('permission_denied', __('You do not have permission to access this chat.', 'chatprojects'));
        }

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table requires direct query
        $chat = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->chats_table_sql} WHERE id = %d AND user_id = %d",
            $chat_id,
            get_current_user_id()
        ));
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if (!$chat) {
            return new \WP_Error('invalid_chat', __('Invalid chat ID or access denied.', 'chatprojects'));
        }

        return $chat;
    }
}
