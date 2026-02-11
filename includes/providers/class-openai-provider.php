<?php
/**
 * OpenAI Provider
 *
 * Handles OpenAI API interactions using the Responses API
 * Updated to remove thread-based methods - uses local message storage
 *
 * @package ChatProjects
 */

namespace ChatProjects\Providers;

use ChatProjects\Security;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * OpenAI Provider Class
 */
class OpenAI_Provider extends Base_Provider {
    /**
     * API base URL
     */
    const API_BASE_URL = 'https://api.openai.com/v1/';

    /**
     * Constructor
     */
    public function __construct() {
        $this->name = 'OpenAI';
        $this->identifier = 'openai';
        $this->api_base_url = self::API_BASE_URL;
        $this->models = array(
            'gpt-5.2-chat-latest' => 'GPT-5.2 Instant (Recommended)',
            'gpt-5-mini' => 'GPT-5 Mini',
            'gpt-4.1' => 'GPT-4.1',
            'gpt-4.1-mini' => 'GPT-4.1 Mini',
            'gpt-4o' => 'GPT-4o',
            'gpt-4o-mini' => 'GPT-4o Mini',
            'o4-mini' => 'o4-mini (Reasoning)',
            'o3-mini' => 'o3-mini (Reasoning)',
        );

        parent::__construct();
    }

    /**
     * Run completion using Responses API
     *
     * @param array  $messages Array of message objects with 'role' and 'content'
     * @param string $model    Model identifier
     * @param array  $options  Additional options (instructions, temperature, etc.)
     * @return array|WP_Error Response with 'content' key or error
     */
    public function run_completion($messages, $model, $options = array()) {
        if (!$this->has_api_key()) {
            return $this->error('no_api_key', __('OpenAI API key is not configured.', 'chatprojects'));
        }

        if (empty($messages)) {
            return $this->error('no_messages', __('No messages provided.', 'chatprojects'));
        }

        // Build Responses API request
        $data = array(
            'model' => $model,
            'input' => $this->format_messages_for_api($messages),
        );

        // Add system instructions if provided
        if (!empty($options['instructions'])) {
            $data['instructions'] = $options['instructions'];
        }

        // Handle model-specific options
        $is_newer_model = $this->is_newer_model($model);

        if (!$is_newer_model && isset($options['temperature'])) {
            $data['temperature'] = $options['temperature'];
        }

        if (isset($options['max_tokens'])) {
            if ($is_newer_model) {
                $data['max_output_tokens'] = $options['max_tokens'];
            } else {
                $data['max_output_tokens'] = $options['max_tokens'];
            }
        }

        $headers = array(
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json',
        );

        $response = $this->make_request(
            self::API_BASE_URL . 'responses',
            $data,
            'POST',
            $headers
        );

        if (is_wp_error($response)) {
            return $response;
        }

        // Extract text from Responses API format
        $content = $this->extract_response_text($response);

        if (empty($content)) {
            return $this->error('no_response', __('No response from OpenAI.', 'chatprojects'));
        }

        return array(
            'content' => $content,
            'model' => $model,
            'usage' => isset($response['usage']) ? $response['usage'] : null,
        );
    }

    /**
     * Stream completion with callback using Responses API
     *
     * Uses the OpenAI Responses API with streaming for conversation continuity.
     * When previous_response_id is provided, maintains context from previous turn.
     *
     * @param array    $messages Array of message objects
     * @param string   $model    Model identifier
     * @param callable $callback Callback for each chunk
     * @param array    $options  Additional options (previous_response_id, instructions, etc.)
     * @return void
     */
    public function stream_completion( $messages, $model, $callback, $options = array() ) {
        if ( ! $this->has_api_key() ) {
            $callback( array( 'type' => 'error', 'content' => __( 'OpenAI API key is not configured.', 'chatprojects' ) ) );
            return;
        }

        if ( empty( $messages ) ) {
            $callback( array( 'type' => 'error', 'content' => __( 'No messages provided.', 'chatprojects' ) ) );
            return;
        }

        $url = self::API_BASE_URL . 'responses';

        // Build request data for Responses API.
        $data = array(
            'model'  => $model,
            'stream' => true,
        );

        // If we have a previous_response_id, use it for conversation continuity
        // and only send the latest user message.
        if ( ! empty( $options['previous_response_id'] ) ) {
            $data['previous_response_id'] = $options['previous_response_id'];
            // Only send the latest user message when using previous_response_id.
            $latest_user_message = $this->get_latest_user_message( $messages );
            $data['input'] = $latest_user_message;
        } else {
            // No previous response - send full conversation history.
            $data['input'] = $this->format_messages_for_api( $messages );
        }

        // Add system instructions if provided.
        if ( ! empty( $options['instructions'] ) ) {
            $data['instructions'] = $options['instructions'];
        }

        // Handle model-specific options.
        $is_newer_model = $this->is_newer_model( $model );

        if ( ! $is_newer_model && isset( $options['temperature'] ) ) {
            $data['temperature'] = $options['temperature'];
        }

        if ( isset( $options['max_tokens'] ) ) {
            $data['max_output_tokens'] = $options['max_tokens'];
        }

        // Headers for WordPress HTTP API.
        $headers = array(
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type'  => 'application/json',
            'Accept'        => 'text/event-stream',
        );

        // SSE parser for OpenAI Responses API streaming format.
        $parser = function ( $chunk, $callback, &$buffer, &$state ) {
            $buffer .= $chunk;

            // Process complete SSE events (separated by double newlines).
            while ( ( $pos = strpos( $buffer, "\n\n" ) ) !== false ) {
                $event  = substr( $buffer, 0, $pos );
                $buffer = substr( $buffer, $pos + 2 );

                // Parse event type and data lines.
                $event_type = '';
                $json_data  = '';

                foreach ( explode( "\n", $event ) as $line ) {
                    if ( strpos( $line, 'event: ' ) === 0 ) {
                        $event_type = trim( substr( $line, 7 ) );
                    } elseif ( strpos( $line, 'data: ' ) === 0 ) {
                        $json_data = trim( substr( $line, 6 ) );
                    }
                }

                if ( empty( $json_data ) || '[DONE]' === $json_data ) {
                    continue;
                }

                $parsed = json_decode( $json_data, true );
                if ( ! $parsed ) {
                    continue;
                }

                // Handle different event types from Responses API.
                switch ( $event_type ) {
                    case 'response.output_text.delta':
                        // Text delta - stream content to user.
                        if ( isset( $parsed['delta'] ) ) {
                            $callback( array( 'type' => 'content', 'content' => $parsed['delta'] ) );
                        }
                        break;

                    case 'response.completed':
                        // Response completed - capture response_id for next turn.
                        if ( isset( $parsed['response']['id'] ) ) {
                            $callback( array( 'type' => 'response_id', 'response_id' => $parsed['response']['id'] ) );
                        }
                        break;

                    case 'error':
                        // API error during streaming.
                        $error_msg = isset( $parsed['error']['message'] ) ? $parsed['error']['message'] : __( 'Unknown streaming error', 'chatprojects' );
                        $callback( array( 'type' => 'error', 'content' => $error_msg ) );
                        break;
                }
            }
        };

        // Execute streaming request using WordPress HTTP API.
        $result = $this->make_streaming_request( $url, $data, $headers, $callback, $parser );

        if ( true !== $result ) {
            $callback( array( 'type' => 'error', 'content' => __( 'Connection error: ', 'chatprojects' ) . $result ) );
            return;
        }

        $callback( array( 'type' => 'done' ) );
    }

    /**
     * Get the latest user message from messages array
     *
     * @param array $messages Array of message objects
     * @return string The content of the latest user message
     */
    private function get_latest_user_message( $messages ) {
        // Iterate backwards to find the last user message.
        for ( $i = count( $messages ) - 1; $i >= 0; $i-- ) {
            if ( isset( $messages[ $i ]['role'] ) && 'user' === $messages[ $i ]['role'] ) {
                $content = isset( $messages[ $i ]['content'] ) ? $messages[ $i ]['content'] : '';

                // Handle vision/image content for Responses API format.
                if ( ! empty( $messages[ $i ]['images'] ) && is_array( $messages[ $i ]['images'] ) ) {
                    $content_parts = array();

                    if ( ! empty( $content ) ) {
                        $content_parts[] = array(
                            'type' => 'input_text',
                            'text' => $content,
                        );
                    }

                    foreach ( $messages[ $i ]['images'] as $image_url ) {
                        $content_parts[] = array(
                            'type'      => 'input_image',
                            'image_url' => $image_url,
                        );
                    }

                    return $content_parts;
                }

                return $content;
            }
        }

        return '';
    }

    /**
     * Format messages for Chat Completions API
     *
     * @param array $messages Messages array
     * @return array Formatted messages
     */
    private function format_messages_for_chat_api($messages) {
        $formatted = array();

        foreach ($messages as $msg) {
            $role = isset($msg['role']) ? $msg['role'] : 'user';
            $content = isset($msg['content']) ? $msg['content'] : '';

            // Handle vision/image content
            if (!empty($msg['images']) && is_array($msg['images'])) {
                $content_parts = array();

                if (!empty($content)) {
                    $content_parts[] = array(
                        'type' => 'text',
                        'text' => $content,
                    );
                }

                foreach ($msg['images'] as $image_url) {
                    $content_parts[] = array(
                        'type' => 'image_url',
                        'image_url' => array(
                            'url' => $image_url,
                        ),
                    );
                }

                $formatted[] = array(
                    'role' => $role,
                    'content' => $content_parts,
                );
            } else {
                $formatted[] = array(
                    'role' => $role,
                    'content' => $content,
                );
            }
        }

        return $formatted;
    }

    /**
     * Validate API key
     *
     * @param string $api_key API key to validate
     * @return bool|WP_Error True if valid, error otherwise
     */
    public function validate_api_key($api_key) {
        $headers = array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        );

        $response = wp_remote_get(
            self::API_BASE_URL . 'models',
            array(
                'headers' => $headers,
                'timeout' => 10,
            )
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code === 200) {
            return true;
        }

        return $this->error('invalid_api_key', __('Invalid OpenAI API key.', 'chatprojects'));
    }

    /**
     * Format messages array for API input
     *
     * @param array $messages Array of message objects
     * @return array Formatted messages for Responses API
     */
    private function format_messages_for_api($messages) {
        $formatted = array();

        foreach ($messages as $msg) {
            $role = isset($msg['role']) ? $msg['role'] : 'user';
            $content = isset($msg['content']) ? $msg['content'] : '';

            // Handle vision/image content
            if (!empty($msg['images']) && is_array($msg['images'])) {
                $content_parts = array();

                if (!empty($content)) {
                    $content_parts[] = array(
                        'type' => 'input_text',
                        'text' => $content,
                    );
                }

                foreach ($msg['images'] as $image_url) {
                    $content_parts[] = array(
                        'type' => 'input_image',
                        'image_url' => $image_url,
                    );
                }

                $formatted[] = array(
                    'role' => $role,
                    'content' => $content_parts,
                );
            } else {
                $formatted[] = array(
                    'role' => $role,
                    'content' => $content,
                );
            }
        }

        return $formatted;
    }

    /**
     * Extract text content from Responses API response
     *
     * @param array $response API response
     * @return string Extracted text
     */
    private function extract_response_text($response) {
        $text = '';

        if (isset($response['output']) && is_array($response['output'])) {
            foreach ($response['output'] as $output_item) {
                if (isset($output_item['type']) && $output_item['type'] === 'message') {
                    if (isset($output_item['content']) && is_array($output_item['content'])) {
                        foreach ($output_item['content'] as $content_item) {
                            if (isset($content_item['type']) && $content_item['type'] === 'output_text') {
                                if (isset($content_item['text'])) {
                                    $text .= $content_item['text'];
                                }
                            }
                        }
                    }
                }
            }
        }

        return $text;
    }

    /**
     * Check if model is a newer model with different parameter requirements
     *
     * @param string $model Model identifier
     * @return bool True if newer model
     */
    private function is_newer_model($model) {
        return (
            strpos($model, 'gpt-5') === 0 ||
            strpos($model, 'o1') === 0
        );
    }
}
