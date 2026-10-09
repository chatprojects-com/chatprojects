<?php
/**
 * General Chat AJAX Handler
 *
 * Handles AJAX requests for general chat mode
 *
 * @package ChatProjects
 */

namespace ChatProjects;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * General Chat AJAX Class
 */
class General_Chat_Ajax {
    /**
     * Messages table name
     *
     * @var string
     */
    private $messages_table;

    /**
     * Messages table name (escaped for SQL)
     *
     * @var string
     */
    private $messages_table_sql;

    /**
     * Chats table name
     *
     * @var string
     */
    private $chats_table;

    /**
     * Chats table name (escaped)
     *
     * @var string
     */
    private $chats_table_sql;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;

        $this->messages_table = $wpdb->prefix . 'chatprojects_messages';
        $this->messages_table_sql = esc_sql($this->messages_table);
        $this->chats_table = $wpdb->prefix . 'chatprojects_chats';
        $this->chats_table_sql = esc_sql($this->chats_table);

        add_action('wp_ajax_chatpr_create_general_chat', array($this, 'create_general_chat'));
        add_action('wp_ajax_chatpr_send_general_message', array($this, 'send_general_message'));
        add_action('wp_ajax_chatpr_stream_general_message', array($this, 'stream_general_message'));
        add_action('wp_ajax_chatpr_get_general_chat_list', array($this, 'get_general_chat_list'));
        add_action('wp_ajax_chatpr_delete_general_chat', array($this, 'delete_general_chat'));
        add_action('wp_ajax_chatpr_rename_general_chat', array($this, 'rename_general_chat'));
        add_action('wp_ajax_chatpr_get_general_chat_history', array($this, 'get_general_chat_history'));
        add_action('wp_ajax_chatpr_get_available_providers', array($this, 'get_available_providers'));
        add_action('wp_ajax_chatpr_get_chat_metadata', array($this, 'get_chat_metadata'));
        add_action('wp_ajax_chatpr_refresh_nonce', array($this, 'refresh_nonce'));
    }

    /**
     * Refresh nonce for cached pages
     * This endpoint doesn't require nonce verification since its purpose is to get a fresh nonce
     * but we verify the request origin to prevent CSRF attacks
     */
    public function refresh_nonce() {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Not logged in', 'chatprojects')));
            return;
        }

        if (!User_Roles::can_use_chatprojects()) {
            wp_send_json_error(array('message' => __('Permission denied.', 'chatprojects')), 403);
        }

        // Verify request comes from the same site to prevent CSRF.
        $referer      = wp_get_referer();
        $referer_host = $referer ? wp_parse_url($referer, PHP_URL_HOST) : '';
        if (!$referer_host || $referer_host !== wp_parse_url(home_url(), PHP_URL_HOST)) {
            wp_send_json_error(array('message' => __('Invalid request origin', 'chatprojects')));
            return;
        }

        wp_send_json_success(array(
            'nonce' => wp_create_nonce('chatpr_ajax_nonce'),
        ));
    }

    /**
     * Create a new general chat
     */
    public function create_general_chat() {
        check_ajax_referer('chatpr_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in.', 'chatprojects')));
            return;
        }

        // Basic capability check
        if (!User_Roles::can_use_chatprojects()) {
            wp_send_json_error(array('message' => __('Permission denied.', 'chatprojects')));
            return;
        }

        $provider = isset($_POST['provider']) ? sanitize_key(wp_unslash($_POST['provider'])) : 'openai';
        $model = isset($_POST['model']) ? sanitize_text_field(wp_unslash($_POST['model'])) : '';
        $model = Model_Registry::resolve($provider, $model, get_option('chatprojects_general_chat_model'));
        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';

        $chat_interface = new Chat_Interface();
        $chat_id = $chat_interface->create_general_chat($provider, $model, $title);

        if (is_wp_error($chat_id)) {
            wp_send_json_error(array(
                'message' => $chat_id->get_error_message(),
            ));
        }

        wp_send_json_success(array(
            'chat_id' => $chat_id,
            'message' => __('General chat created successfully.', 'chatprojects'),
        ));
    }

    /**
     * Send a message in general chat
     */
    public function send_general_message() {
        check_ajax_referer('chatpr_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in.', 'chatprojects')));
            return;
        }

        // Basic capability check
        if (!User_Roles::can_use_chatprojects()) {
            wp_send_json_error(array('message' => __('Permission denied.', 'chatprojects')));
            return;
        }

        if (!Security::check_rate_limit('stream_general', get_current_user_id(), (int) apply_filters('chatprojects_chat_rate_limit', 60), MINUTE_IN_SECONDS)) {
            wp_send_json_error(array('message' => __('Too many requests. Please wait a moment and try again.', 'chatprojects')), 429);
        }

        $chat_id = isset($_POST['chat_id']) ? intval($_POST['chat_id']) : 0;
        $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
        $provider = isset($_POST['provider']) ? sanitize_key(wp_unslash($_POST['provider'])) : 'openai';
        $model = isset($_POST['model']) ? sanitize_text_field(wp_unslash($_POST['model'])) : '';
        $model = Model_Registry::resolve($provider, $model, get_option('chatprojects_general_chat_model'));

        // Process images from base64 JSON (clipboard paste / drag-drop).
        $images = $this->process_images_from_request();
        if (is_wp_error($images)) {
            wp_send_json_error(array('message' => $images->get_error_message()));
        }

        // Message can be empty if images are provided
        if (empty($message) && empty($images)) {
            wp_send_json_error(array(
                'message' => __('Message or image is required.', 'chatprojects'),
            ));
        }

        // Default message if only images provided
        if (empty($message) && !empty($images)) {
            $message = __('What is in this image?', 'chatprojects');
        }

        $chat_interface = new Chat_Interface();

        // Create new chat if no chat_id provided
        if (empty($chat_id)) {
            $chat_id = $chat_interface->create_general_chat($provider, $model, '');

            if (is_wp_error($chat_id)) {
                wp_send_json_error(array(
                    'message' => $chat_id->get_error_message(),
                ));
            }
        }
        $response = $chat_interface->send_general_message($chat_id, $message, $images);

        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => $response->get_error_message(),
            ));
        }
        // Extract content from response
        $response_content = '';
        if (is_array($response) && isset($response['content'])) {
            $response_content = $response['content'];
        } elseif (is_string($response)) {
            $response_content = $response;
        }

        // Auto-generate title after first message exchange
        $chat = $chat_interface->get_chat($chat_id);
        if (!is_wp_error($chat) && $chat->message_count == 2) {
            // This is the first exchange, generate a meaningful title
            $generated_title = $this->generate_title($message, $response_content);
            $chat_interface->update_chat_title($chat_id, $generated_title);
        }

        wp_send_json_success(array(
            'response' => $response_content,
            'chat_id' => $chat_id,
            'message' => __('Message sent successfully.', 'chatprojects'),
        ));
    }

    /**
     * Stream a message in general chat (Server-Sent Events)
     */
    public function stream_general_message() {
        // Authenticate BEFORE any output: a failed check must return a normal JSON error,
        // not an SSE stream to an anonymous caller.
        if (!check_ajax_referer('chatpr_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Invalid security token. Please refresh the page.', 'chatprojects')), 403);
        }
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in.', 'chatprojects')), 401);
        }
        if (!User_Roles::can_use_chatprojects()) {
            wp_send_json_error(array('message' => __('Permission denied.', 'chatprojects')), 403);
        }
        if (!Security::check_rate_limit('stream_general', get_current_user_id(), (int) apply_filters('chatprojects_chat_rate_limit', 60), MINUTE_IN_SECONDS)) {
            wp_send_json_error(array('message' => __('Too many requests. Please wait a moment and try again.', 'chatprojects')), 429);
        }

        SSE_Stream_Manager::begin_response();

        try {
            $chat_id = isset($_POST['chat_id']) ? intval($_POST['chat_id']) : 0;
            $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
            $provider_name = isset($_POST['provider']) ? sanitize_key(wp_unslash($_POST['provider'])) : 'openai';
            $model = isset($_POST['model']) ? sanitize_text_field(wp_unslash($_POST['model'])) : '';
            $model = Model_Registry::resolve($provider_name, $model, get_option('chatprojects_general_chat_model'));

            // Process images from base64 JSON
            $images = $this->process_images_from_request();
            if (is_wp_error($images)) {
                $this->send_sse_error($images->get_error_message());
                return;
            }

            // Message can be empty if images are provided
            if (empty($message) && empty($images)) {
                $this->send_sse_error(__('Message or image is required.', 'chatprojects'));
                return;
            }

            // Default message if only images provided
            if (empty($message) && !empty($images)) {
                $message = __('What is in this image?', 'chatprojects');
            }

            $chat_interface = new Chat_Interface();

            // Create new chat if no chat_id provided
            if (empty($chat_id)) {
                $chat_id = $chat_interface->create_general_chat($provider_name, $model, '');

                if (is_wp_error($chat_id)) {
                    $this->send_sse_error($chat_id->get_error_message());
                    return;
                }
            }

            // The chat must belong to the current user before anything is written to it.
            global $wpdb;
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table requires direct query
            $owned_chat = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->chats_table_sql} WHERE id = %d AND user_id = %d AND chat_mode = 'general'",
                $chat_id,
                get_current_user_id()
            ));
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
            if (!$owned_chat) {
                $this->send_sse_error(__('Invalid chat ID.', 'chatprojects'));
                return;
            }

            // Send chat_id to frontend immediately
            $this->send_sse_data(array('type' => 'chat_id', 'chat_id' => $chat_id));

            // Get provider instance
            $provider_instance = $this->get_provider_instance($provider_name);
            if (!$provider_instance) {
                $this->send_sse_error(__('Provider not available.', 'chatprojects'));
                return;
            }

            if (!$provider_instance->has_api_key()) {
                $this->send_sse_error(__('API key not configured for this provider.', 'chatprojects'));
                return;
            }

            // Don't send images to a model that can't read them.
            if (!empty($images) && !Model_Registry::supports_vision($provider_name, $model)) {
                $this->send_sse_error(__('The selected model cannot read images. Choose a vision-capable model or remove the image.', 'chatprojects'));
                return;
            }

            // Save user message to database
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table requires direct query
            $saved = $wpdb->insert($this->messages_table, array(
                'chat_id' => $chat_id,
                'role' => 'user',
                'content' => $message,
                'created_at' => current_time('mysql'),
            ), array('%d', '%s', '%s', '%s'));
            if (false === $saved) {
                $this->send_sse_error(__('Could not save your message. Please try again.', 'chatprojects'));
                return;
            }

            // Recent history, oldest first; the last row is the message just saved.
            $message_store = new Message_Store();
            $recent        = $message_store->get_recent_messages($chat_id, 20);
            $api_messages  = array();
            foreach ($recent as $row) {
                if (!in_array($row['role'], array('user', 'assistant'), true)) {
                    continue;
                }
                // The conversation sent to the API must start with a user turn.
                if (empty($api_messages) && 'assistant' === $row['role']) {
                    continue;
                }
                $api_messages[] = array(
                    'role' => $row['role'],
                    'content' => $row['content'],
                );
            }

            // OpenAI can continue from its previous reply server-side, but only
            // when that reply came directly before this message (another
            // provider may have answered in between).
            $previous_response_id = null;
            $recent_count         = count($recent);
            if ('openai' === $provider_name && $recent_count >= 2) {
                $prev = $recent[ $recent_count - 2 ];
                if ('assistant' === $prev['role'] && is_array($prev['metadata']) && !empty($prev['metadata']['response_id'])) {
                    $previous_response_id = $prev['metadata']['response_id'];
                }
            }

            // Attach images to the latest user message for vision support
            if (!empty($images) && !empty($api_messages)) {
                $api_messages[ count($api_messages) - 1 ]['images'] = $images;
            }

            // Chat-specific instructions, else the site-wide default.
            $instructions = $owned_chat->instructions;
            if (empty($instructions)) {
                $instructions = get_option('chatprojects_assistant_instructions', '');
            }

            // Build options for provider
            $provider_options = array(
                'images'       => $images,
                'instructions' => $instructions,
            );
            if ($previous_response_id) {
                $provider_options['previous_response_id'] = $previous_response_id;
            }

            // Stream response from provider
            $assistant_content = '';
            $captured_response_id = null;

            $provider_instance->stream_completion(
                $api_messages,
                $model,
                function($chunk) use (&$assistant_content, &$captured_response_id) {
                    if (isset($chunk['type'])) {
                        if ($chunk['type'] === 'content' && isset($chunk['content'])) {
                            $assistant_content .= $chunk['content'];
                        }
                        // Capture response_id from OpenAI Responses API.
                        if ($chunk['type'] === 'response_id' && isset($chunk['response_id'])) {
                            $captured_response_id = $chunk['response_id'];
                            // Don't send response_id to frontend, it's internal.
                            return;
                        }
                        // Output immediately
                        echo 'data: ' . wp_json_encode($chunk) . "\n\n";
                        SSE_Stream_Manager::flush();
                    }
                },
                $provider_options
            );

            // Save assistant response to database with response_id in metadata (for OpenAI).
            if (!empty($assistant_content)) {
                $insert_data = array(
                    'chat_id' => $chat_id,
                    'role' => 'assistant',
                    'content' => $assistant_content,
                    'created_at' => current_time('mysql'),
                );
                $insert_format = array('%d', '%s', '%s', '%s');

                // Store response_id in metadata for OpenAI provider.
                if ($provider_name === 'openai' && $captured_response_id) {
                    $insert_data['metadata'] = wp_json_encode(array('response_id' => $captured_response_id));
                    $insert_format[] = '%s';
                }

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table requires direct query
                $wpdb->insert($this->messages_table, $insert_data, $insert_format);
            }

            // Update message count
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table requires direct query
            $chat = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->chats_table_sql} WHERE id = %d",
                $chat_id
            ));
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

            if ($chat) {
                $new_count = $chat->message_count + (empty($assistant_content) ? 1 : 2);
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query
                $wpdb->update(
                    $this->chats_table,
                    array(
                        'message_count' => $new_count,
                        'updated_at' => current_time('mysql')
                    ),
                    array('id' => $chat_id),
                    array('%d', '%s'),
                    array('%d')
                );

                // Auto-generate title after first message exchange
                if ($new_count == 2 && (empty($chat->title) || strpos($chat->title, 'General Chat ') === 0)) {
                    $generated_title = $this->generate_title($message, $assistant_content);
                    $chat_interface->update_chat_title($chat_id, $generated_title);
                    // Notify frontend of title change
                    $this->send_sse_data(array(
                        'type' => 'title_update',
                        'chat_id' => $chat_id,
                        'title' => $generated_title
                    ));
                }
            }

            $this->send_sse_done();

        } catch (\Exception $e) {
            $this->send_sse_error(__('Server error: ', 'chatprojects') . $e->getMessage());
        }

        exit;
    }

    /**
     * Process images from request.
     *
     * Validation process:
     * 1. JSON string unslashed to handle escaped characters
     * 2. Decoded and validated as array
     * 3. Each element checked for required 'dataUrl' key and string type
     * 4. Each dataUrl validated via Security::validate_base64_image() for format and MIME type
     *
     * IMPORTANT: Callers MUST verify nonce before calling this method.
     *
     * @return array|WP_Error Array of image data URLs or error
     */
    private function process_images_from_request() {
        $images = array();
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce MUST be verified by calling method
        if ( ! isset( $_POST['images_base64'] ) ) {
            return $images;
        }
        // JSON string containing base64 data - unslash only, sanitization happens per-element below.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized per-element after json_decode
        $images_base64_json = wp_unslash( $_POST['images_base64'] );
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        // Validate it's a string.
        if ( ! is_string( $images_base64_json ) || empty( $images_base64_json ) ) {
            return $images;
        }

        $images_data = json_decode( $images_base64_json, true );

        // Validate JSON decode succeeded and result is an array.
        if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $images_data ) ) {
            return $images;
        }

        // Each image can be up to 10 MB, so cap how many one message may carry.
        $max_images = (int) apply_filters( 'chatprojects_max_images_per_message', 4 );
        if ( count( $images_data ) > $max_images ) {
            /* translators: %d: maximum number of images */
            return new \WP_Error( 'too_many_images', sprintf( __( 'You can attach up to %d images per message.', 'chatprojects' ), $max_images ) );
        }

        // Validate and sanitize each image element.
        foreach ( $images_data as $img ) {
            // Skip elements without proper structure.
            if ( ! is_array( $img ) || ! isset( $img['dataUrl'] ) || ! is_string( $img['dataUrl'] ) ) {
                continue;
            }

            // Sanitize: Remove any characters not valid in base64 data URLs.
            // Valid chars: alphanumeric, +, /, =, :, ;, , (for data:mime;base64,data format).
            $data_url = preg_replace( '/[^a-zA-Z0-9+\/=:;,]/', '', $img['dataUrl'] );

            // Validate base64 image format, MIME type, and data integrity.
            $validation = Security::validate_base64_image( $data_url );
            if ( is_wp_error( $validation ) ) {
                return $validation;
            }

            $images[] = $data_url;
        }

        return $images;
    }

    /**
     * Send SSE data chunk
     *
     * @param array $data Data to send
     */
    private function send_sse_data($data) {
        echo 'data: ' . wp_json_encode($data) . "\n\n";
        SSE_Stream_Manager::flush();
    }

    /**
     * Send SSE error
     *
     * @param string $message Error message
     */
    private function send_sse_error($message) {
        $this->send_sse_data(array(
            'type' => 'error',
            'content' => $message
        ));
        echo "data: [DONE]\n\n";
        SSE_Stream_Manager::flush();
    }

    /**
     * Send SSE completion signal
     */
    private function send_sse_done() {
        echo "data: [DONE]\n\n";
        SSE_Stream_Manager::flush();
    }

    /**
     * Get list of general chats
     */
    public function get_general_chat_list() {
        check_ajax_referer('chatpr_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in.', 'chatprojects')));
        }

        if (!User_Roles::can_use_chatprojects()) {
            wp_send_json_error(array('message' => __('Permission denied.', 'chatprojects')));
        }

        global $wpdb;
        $user_id = get_current_user_id();

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table requires direct query
        $chats = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->chats_table_sql}
            WHERE user_id = %d
            AND chat_mode = 'general'
            ORDER BY updated_at DESC",
            $user_id
        ));
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if ($chats === false) {
            wp_send_json_error(array(
                'message' => __('Failed to load chat list.', 'chatprojects'),
            ));
        }

        wp_send_json_success(array(
            'chats' => $chats,
        ));
    }

    /**
     * Delete a general chat
     */
    public function delete_general_chat() {
        check_ajax_referer('chatpr_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in.', 'chatprojects')));
        }

        $chat_id = intval($_POST['chat_id'] ?? 0);

        if (empty($chat_id)) {
            wp_send_json_error(array(
                'message' => __('Invalid chat ID.', 'chatprojects'),
            ));
        }

        // Verify ownership before deleting
        if (!Access::can_access_chat($chat_id)) {
            wp_send_json_error(array('message' => __('Access denied.', 'chatprojects')));
        }

        $chat_interface = new Chat_Interface();
        $result = $chat_interface->delete_chat($chat_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
            ));
        }

        wp_send_json_success(array(
            'message' => __('Chat deleted successfully.', 'chatprojects'),
        ));
    }

    /**
     * Rename a general chat
     */
    public function rename_general_chat() {
        check_ajax_referer('chatpr_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in.', 'chatprojects')));
        }

        $chat_id = isset($_POST['chat_id']) ? intval($_POST['chat_id']) : 0;
        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';

        if (empty($chat_id) || empty($title)) {
            wp_send_json_error(array(
                'message' => __('Invalid chat ID or title.', 'chatprojects'),
            ));
        }

        // Verify ownership before renaming
        if (!Access::can_access_chat($chat_id)) {
            wp_send_json_error(array('message' => __('Access denied.', 'chatprojects')));
        }

        $chat_interface = new Chat_Interface();
        $result = $chat_interface->update_chat_title($chat_id, $title);

        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
            ));
        }

        wp_send_json_success(array(
            'message' => __('Chat renamed successfully.', 'chatprojects'),
        ));
    }

    /**
     * Get chat history for general chat
     */
    public function get_general_chat_history() {
        check_ajax_referer('chatpr_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in.', 'chatprojects')));
        }

        $chat_id = intval($_POST['chat_id'] ?? 0);
        if (empty($chat_id)) {
            wp_send_json_error(array(
                'message' => __('Invalid chat ID.', 'chatprojects'),
            ));
        }

        // Verify ownership before retrieving history
        if (!Access::can_access_chat($chat_id)) {
            wp_send_json_error(array('message' => __('Access denied.', 'chatprojects')));
        }

        $chat_interface = new Chat_Interface();
        $messages = $chat_interface->get_general_chat_history($chat_id);

        if (is_wp_error($messages)) {
            wp_send_json_error(array(
                'message' => $messages->get_error_message(),
            ));
        }
        wp_send_json_success(array(
            'messages' => $messages,
        ));
    }

    /**
     * Get available providers (only those with API keys configured)
     */
    public function get_available_providers() {
        check_ajax_referer('chatpr_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in.', 'chatprojects')));
        }

        if (!User_Roles::can_use_chatprojects()) {
            wp_send_json_error(array('message' => __('Permission denied.', 'chatprojects')));
        }

        $providers = array();
        foreach (Model_Registry::get_provider_names() as $key => $name) {
            $providers[ $key ] = array(
                'name'    => $name,
                'has_key' => false,
                'default' => Model_Registry::get_default($key),
                'models'  => array(),
            );
        }

        foreach ($providers as $key => &$provider) {
            $provider_class = $this->get_provider_instance($key);
            if ($provider_class) {
                $provider['has_key'] = $provider_class->has_api_key();
                if ($provider['has_key']) {
                    // Dynamic providers (Chutes, OpenRouter) fetch their model list from the API
                    if (Model_Registry::is_dynamic_provider($key) && method_exists($provider_class, 'fetch_available_models')) {
                        $fetched_models = $provider_class->fetch_available_models();
                        // If fetch succeeds, use the fetched models; otherwise fallback to defaults
                        if (!is_wp_error($fetched_models) && !empty($fetched_models)) {
                            $models = $fetched_models;
                        } else {
                            // Fallback to default models if fetch fails
                            $models = $provider_class->get_available_models();
                        }
                    } else {
                        // For other providers, use hardcoded models
                        $models = $provider_class->get_available_models();
                    }
                    // id + label pairs so the UI can show friendly names.
                    $provider['models'] = array();
                    foreach ($models as $id => $label) {
                        $provider['models'][] = array(
                            'id'    => (string) $id,
                            'label' => is_string($label) && '' !== $label ? $label : (string) $id,
                        );
                    }
                    if (empty($provider['default']) && !empty($provider['models'])) {
                        $provider['default'] = $provider['models'][0]['id'];
                    }
                }
            }
        }

        // Filter to only return providers with API keys
        $available = array_filter($providers, function($p) {
            return $p['has_key'];
        });

        wp_send_json_success(array('providers' => $available));
    }

    /**
     * Get chat metadata (provider and model)
     */
    public function get_chat_metadata() {
        check_ajax_referer('chatpr_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in.', 'chatprojects')));
        }

        if (!User_Roles::can_use_chatprojects()) {
            wp_send_json_error(array('message' => __('Permission denied.', 'chatprojects')));
        }

        $chat_id = intval($_POST['chat_id'] ?? 0);

        if (empty($chat_id)) {
            wp_send_json_error(array('message' => __('Invalid chat ID.', 'chatprojects')));
        }

        global $wpdb;

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table requires direct query
        $chat = $wpdb->get_row($wpdb->prepare(
            "SELECT provider, model FROM {$this->chats_table_sql} WHERE id = %d AND user_id = %d",
            $chat_id,
            get_current_user_id()
        ));
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if (!$chat) {
            wp_send_json_error(array('message' => __('Chat not found.', 'chatprojects')));
        }

        wp_send_json_success(array(
            'provider' => $chat->provider,
            'model' => $chat->model
        ));
    }

    /**
     * Get provider instance
     *
     * @param string $provider Provider identifier
     * @return object|null Provider instance or null
     */
    private function get_provider_instance($provider) {
        $provider_map = Chat_Interface::provider_class_map();

        if (!isset($provider_map[ $provider ])) {
            return null;
        }

        $class_name = 'ChatProjects\\Providers\\' . $provider_map[ $provider ];

        if (!class_exists($class_name)) {
            return null;
        }

        return new $class_name();
    }

    /**
     * Generate a meaningful title from conversation
     *
     * @param string $user_message User's message
     * @param string $assistant_response Assistant's response
     * @return string Generated title
     */
    private function generate_title($user_message, $assistant_response) {
        try {
            // Use OpenAI to generate a concise title
            $api_handler = new API_Handler();

            $prompt = "Based on this conversation, generate a short, concise title (3-6 words maximum):\n\nUser: {$user_message}\n\nAssistant: {$assistant_response}\n\nRespond with ONLY the title, nothing else.";

            $messages = array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            );

            $response = $api_handler->create_chat_completion($messages, \ChatProjects\Model_Registry::get_utility_model('title'));

            if (is_wp_error($response)) {
                // Fallback: use first few words of user message
                return $this->generate_fallback_title($user_message);
            }

            $title = isset($response['choices'][0]['message']['content'])
                ? trim($response['choices'][0]['message']['content'])
                : __('New Chat', 'chatprojects');

            // Remove quotes if present
            $title = trim($title, '"\'');

            // Limit length
            if (strlen($title) > 50) {
                $title = substr($title, 0, 47) . '...';
            }

            return $title;
        } catch (\Exception $e) {
            return $this->generate_fallback_title($user_message);
        }
    }

    /**
     * Generate fallback title from user message
     *
     * @param string $user_message User's message
     * @return string Fallback title
     */
    private function generate_fallback_title($user_message) {
        $words = explode(' ', $user_message);
        $title = implode(' ', array_slice($words, 0, 5));
        if (count($words) > 5) {
            $title .= '...';
        }
        return $title ?: __('New Chat', 'chatprojects');
    }
}
