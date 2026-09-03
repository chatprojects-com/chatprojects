<?php
/**
 * Anthropic Claude Provider
 *
 * Handles Anthropic Claude API interactions
 * Updated to use new interface without thread storage
 *
 * @package ChatProjects
 */

namespace ChatProjects\Providers;

use ChatProjects\Model_Registry;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Anthropic Provider Class
 */
class Anthropic_Provider extends Base_Provider {
    /**
     * API base URL
     */
    const API_BASE_URL = 'https://api.anthropic.com/v1/';

    /**
     * API version
     */
    const API_VERSION = '2023-06-01';

    /**
     * Constructor
     */
    public function __construct() {
        $this->name = 'Anthropic Claude';
        $this->identifier = 'anthropic';
        $this->api_base_url = self::API_BASE_URL;
        $this->models = Model_Registry::get_labels( 'anthropic' );

        parent::__construct();
    }

    /**
     * Default max_tokens when the caller does not specify one.
     */
    const DEFAULT_MAX_TOKENS = 16384;

    /**
     * Build the Messages API request body for a model.
     *
     * Claude Opus 5 / 4.8, Sonnet 5 and Fable reject sampling parameters, so
     * temperature is only sent when the registry says the model accepts it
     * and the caller actually asked for one. max_tokens is clamped to the
     * model's output limit.
     *
     * @param string $model    Model id.
     * @param array  $messages Formatted messages.
     * @param array  $options  Caller options.
     * @param bool   $stream   Whether this is a streaming request.
     * @return array
     */
    private function build_request_body( $model, $messages, $options, $stream ) {
        $entry      = Model_Registry::get_model( 'anthropic', $model );
        $max_output = $entry ? (int) $entry['max_output'] : self::DEFAULT_MAX_TOKENS;
        $max_tokens = isset( $options['max_tokens'] ) ? absint( $options['max_tokens'] ) : self::DEFAULT_MAX_TOKENS;

        $data = array(
            'model'      => $model,
            'messages'   => $messages,
            'max_tokens' => max( 1, min( $max_tokens, $max_output ) ),
        );

        $supports_temperature = $entry ? ! empty( $entry['supports_temperature'] ) : false;
        if ( $supports_temperature && isset( $options['temperature'] ) ) {
            $data['temperature'] = (float) $options['temperature'];
        }

        if ( ! empty( $options['instructions'] ) ) {
            $data['system'] = $options['instructions'];
        }

        if ( $stream ) {
            $data['stream'] = true;
        }

        return $data;
    }

    /**
     * Run completion and get response
     *
     * @param array  $messages Array of message objects with 'role' and 'content'
     * @param string $model    Model identifier
     * @param array  $options  Additional options (instructions, temperature, etc.)
     * @return array|WP_Error Response with 'content' key or error
     */
    public function run_completion($messages, $model, $options = array()) {
        if (!$this->has_api_key()) {
            return $this->error('no_api_key', __('Anthropic API key is not configured.', 'chatprojects'));
        }

        if (empty($messages)) {
            return $this->error('no_messages', __('No messages provided.', 'chatprojects'));
        }

        // Format messages for Claude
        $formatted_messages = $this->format_messages_for_claude($messages);

        $data = $this->build_request_body( $model, $formatted_messages, $options, false );

        // Make API request
        $headers = array(
            'x-api-key' => $this->api_key,
            'anthropic-version' => self::API_VERSION,
            'Content-Type' => 'application/json',
        );

        $response = $this->make_request(
            self::API_BASE_URL . 'messages',
            $data,
            'POST',
            $headers
        );

        if (is_wp_error($response)) {
            return $response;
        }

        // Concatenate text blocks (a thinking block may precede the first text block).
        $content = '';
        if ( ! empty( $response['content'] ) && is_array( $response['content'] ) ) {
            foreach ( $response['content'] as $block ) {
                if ( isset( $block['type'], $block['text'] ) && 'text' === $block['type'] ) {
                    $content .= $block['text'];
                }
            }
        }

        $stop_reason = isset( $response['stop_reason'] ) ? $response['stop_reason'] : '';

        if ( 'refusal' === $stop_reason && '' === trim( $content ) ) {
            return $this->error( 'refusal', __( 'Claude declined to answer this request.', 'chatprojects' ) );
        }

        if ( '' !== $content ) {
            return array(
                'content'     => $content,
                'model'       => $model,
                'stop_reason' => $stop_reason,
            );
        }

        return $this->error('no_response', __('No response from Claude.', 'chatprojects'));
    }

    /**
     * Stream completion with callback
     *
     * Uses WordPress HTTP API with http_api_curl hook for SSE streaming.
     *
     * @param array    $messages Array of message objects
     * @param string   $model    Model identifier
     * @param callable $callback Callback for each chunk
     * @param array    $options  Additional options
     * @return void
     */
    public function stream_completion( $messages, $model, $callback, $options = array() ) {
        if ( ! $this->has_api_key() ) {
            $callback( array( 'type' => 'error', 'content' => __( 'Anthropic API key is not configured.', 'chatprojects' ) ) );
            return;
        }

        if ( empty( $messages ) ) {
            $callback( array( 'type' => 'error', 'content' => __( 'No messages provided.', 'chatprojects' ) ) );
            return;
        }

        $formatted_messages = $this->format_messages_for_claude( $messages );

        $data = $this->build_request_body( $model, $formatted_messages, $options, true );

        $url = self::API_BASE_URL . 'messages';

        // Headers for WordPress HTTP API (associative array format).
        $headers = array(
            'x-api-key'         => $this->api_key,
            'anthropic-version' => self::API_VERSION,
            'Content-Type'      => 'application/json',
            'Accept'            => 'text/event-stream',
        );

        // SSE parser for Anthropic's response format.
        $parser = function ( $chunk, $callback, &$buffer, &$state ) {
            $buffer .= $chunk;

            // Process complete SSE events (separated by double newlines).
            while ( ( $pos = strpos( $buffer, "\n\n" ) ) !== false ) {
                $event  = substr( $buffer, 0, $pos );
                $buffer = substr( $buffer, $pos + 2 );

                $event = trim( $event );
                if ( empty( $event ) ) {
                    continue;
                }

                // Parse event type and data from SSE format.
                $event_type = null;
                $event_data = null;

                $lines = explode( "\n", $event );
                foreach ( $lines as $line ) {
                    if ( strpos( $line, 'event: ' ) === 0 ) {
                        $event_type = trim( substr( $line, 7 ) );
                    } elseif ( strpos( $line, 'data: ' ) === 0 ) {
                        $event_data = trim( substr( $line, 6 ) );
                    }
                }

                // Skip non-data events.
                if ( empty( $event_data ) ) {
                    continue;
                }

                $parsed = json_decode( $event_data, true );
                if ( ! $parsed ) {
                    continue;
                }

                // Handle content_block_delta events (contains the actual text).
                if ( 'content_block_delta' === $event_type && isset( $parsed['delta']['text'] ) ) {
                    $state['has_text'] = true;
                    $callback( array( 'type' => 'content', 'content' => $parsed['delta']['text'] ) );
                }

                // Safety classifiers can end the turn with stop_reason "refusal".
                if ( 'message_delta' === $event_type && isset( $parsed['delta']['stop_reason'] ) && 'refusal' === $parsed['delta']['stop_reason'] ) {
                    $callback( array( 'type' => 'error', 'content' => __( 'Claude declined to answer this request.', 'chatprojects' ) ) );
                }

                // Handle errors.
                if ( isset( $parsed['error'] ) ) {
                    $error_msg = isset( $parsed['error']['message'] ) ? $parsed['error']['message'] : 'Unknown Anthropic error';
                    $callback( array( 'type' => 'error', 'content' => $error_msg ) );
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
     * Validate API key
     *
     * @param string $api_key API key to validate
     * @return bool|WP_Error True if valid, error otherwise
     */
    public function validate_api_key($api_key) {
        $headers = array(
            'x-api-key' => $api_key,
            'anthropic-version' => self::API_VERSION,
            'Content-Type' => 'application/json',
        );

        // Simple test request
        $data = array(
            'model' => Model_Registry::get_utility_model( 'anthropic_validate' ),
            'max_tokens' => 10,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => 'Hi',
                ),
            ),
        );

        $response = wp_remote_post(
            self::API_BASE_URL . 'messages',
            array(
                'headers' => $headers,
                'body' => wp_json_encode($data),
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

        return $this->error('invalid_api_key', __('Invalid Anthropic API key.', 'chatprojects'));
    }

    /**
     * Format messages for Claude API
     *
     * @param array $messages Array of message objects
     * @return array Formatted messages for Claude
     */
    private function format_messages_for_claude($messages) {
        $formatted = array();

        foreach ($messages as $msg) {
            $role = isset($msg['role']) ? $msg['role'] : 'user';
            $content = isset($msg['content']) ? $msg['content'] : '';

            // Skip system messages - they go in the 'system' field
            if ($role === 'system') {
                continue;
            }

            // Handle vision/image content
            if (!empty($msg['images']) && is_array($msg['images'])) {
                $content_parts = array();

                // Add images first (Claude prefers images before text)
                foreach ($msg['images'] as $image_url) {
                    $parsed = \ChatProjects\Security::parse_base64_image($image_url);
                    if ($parsed) {
                        $content_parts[] = array(
                            'type' => 'image',
                            'source' => array(
                                'type' => 'base64',
                                'media_type' => $parsed['mime_type'],
                                'data' => $parsed['data'],
                            ),
                        );
                    }
                }

                // Add text content
                if (!empty($content)) {
                    $content_parts[] = array(
                        'type' => 'text',
                        'text' => $content,
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
}
