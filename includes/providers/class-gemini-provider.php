<?php
/**
 * Google Gemini Provider
 *
 * Handles Google Gemini API interactions
 * Updated to use new interface without thread storage
 *
 * @package ChatProjects
 */

namespace ChatProjects\Providers;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gemini Provider Class
 */
class Gemini_Provider extends Base_Provider {
    /**
     * API base URL
     */
    const API_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/';

    /**
     * Constructor
     */
    public function __construct() {
        $this->name = 'Google Gemini';
        $this->identifier = 'gemini';
        $this->api_base_url = self::API_BASE_URL;
        $this->models = array(
            'gemini-3-pro-preview' => 'Gemini 3 Pro (Preview)',
            'gemini-3-flash-preview' => 'Gemini 3 Flash (Preview)',
            'gemini-2.5-pro' => 'Gemini 2.5 Pro',
            'gemini-2.5-flash' => 'Gemini 2.5 Flash (Recommended)',
            'gemini-2.5-flash-lite' => 'Gemini 2.5 Flash Lite',
            'gemini-2.0-flash' => 'Gemini 2.0 Flash',
        );

        parent::__construct();
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
            return $this->error('no_api_key', __('Gemini API key is not configured.', 'chatprojects'));
        }

        if (empty($messages)) {
            return $this->error('no_messages', __('No messages provided.', 'chatprojects'));
        }

        // Format conversation for Gemini
        $contents = $this->format_messages_for_gemini($messages);

        // Prepare request data
        $data = array(
            'contents' => $contents,
            'generationConfig' => array(
                'temperature' => isset($options['temperature']) ? $options['temperature'] : 0.7,
                'maxOutputTokens' => isset($options['max_tokens']) ? $options['max_tokens'] : 2048,
            ),
        );

        // Add system instruction if provided
        if (!empty($options['instructions'])) {
            $data['systemInstruction'] = array(
                'parts' => array(
                    array('text' => $options['instructions']),
                ),
            );
        }

        // Make API request
        $url = self::API_BASE_URL . "models/{$model}:generateContent?key=" . $this->api_key;

        $headers = array(
            'Content-Type' => 'application/json',
        );

        $response = $this->make_request($url, $data, 'POST', $headers);

        if (is_wp_error($response)) {
            return $response;
        }

        // Extract assistant's message
        if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            $content = $response['candidates'][0]['content']['parts'][0]['text'];

            return array(
                'content' => $content,
                'model' => $model,
            );
        }

        return $this->error('no_response', __('No response from Gemini.', 'chatprojects'));
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
            $callback( array( 'type' => 'error', 'content' => __( 'Gemini API key is not configured.', 'chatprojects' ) ) );
            return;
        }

        if ( empty( $messages ) ) {
            $callback( array( 'type' => 'error', 'content' => __( 'No messages provided.', 'chatprojects' ) ) );
            return;
        }

        // Format conversation for Gemini.
        $contents = $this->format_messages_for_gemini( $messages );

        $data = array(
            'contents'         => $contents,
            'generationConfig' => array(
                'temperature'     => isset( $options['temperature'] ) ? $options['temperature'] : 0.7,
                'maxOutputTokens' => isset( $options['max_tokens'] ) ? $options['max_tokens'] : 2048,
            ),
        );

        if ( ! empty( $options['instructions'] ) ) {
            $data['systemInstruction'] = array(
                'parts' => array(
                    array( 'text' => $options['instructions'] ),
                ),
            );
        }

        $url = self::API_BASE_URL . "models/{$model}:streamGenerateContent?alt=sse&key=" . $this->api_key;

        // Headers for WordPress HTTP API (associative array format).
        $headers = array(
            'Content-Type' => 'application/json',
            'Accept'       => 'text/event-stream',
        );

        // SSE parser for Gemini's response format.
        // Gemini uses single newline line separation unlike other providers.
        $parser = function ( $chunk, $callback, &$buffer, &$state ) {
            $buffer .= $chunk;

            // Process complete lines - split by newlines.
            $lines  = explode( "\n", $buffer );
            // Keep the last potentially incomplete line in buffer.
            $buffer = array_pop( $lines );

            foreach ( $lines as $line ) {
                $line = trim( $line );

                // Skip empty lines.
                if ( empty( $line ) ) {
                    continue;
                }

                // Check for data: prefix.
                if ( strpos( $line, 'data:' ) === 0 ) {
                    $json_str = trim( substr( $line, 5 ) );

                    // Skip empty data or [DONE].
                    if ( empty( $json_str ) || '[DONE]' === $json_str ) {
                        continue;
                    }

                    $parsed = json_decode( $json_str, true );

                    if ( null === $parsed ) {
                        continue;
                    }

                    // Check for API error response.
                    if ( isset( $parsed['error'] ) ) {
                        $error_msg = isset( $parsed['error']['message'] ) ? $parsed['error']['message'] : 'Unknown Gemini error';
                        $callback( array( 'type' => 'error', 'content' => $error_msg ) );
                        continue;
                    }

                    // Extract text from Gemini's response.
                    if ( isset( $parsed['candidates'][0]['content']['parts'] ) ) {
                        foreach ( $parsed['candidates'][0]['content']['parts'] as $part ) {
                            if ( isset( $part['text'] ) ) {
                                $callback( array( 'type' => 'content', 'content' => $part['text'] ) );
                            }
                        }
                    }
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
        $url = self::API_BASE_URL . 'models?key=' . $api_key;

        $response = wp_remote_get($url, array('timeout' => 10));

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code === 200) {
            return true;
        }

        return $this->error('invalid_api_key', __('Invalid Gemini API key.', 'chatprojects'));
    }

    /**
     * Format messages for Gemini API
     *
     * @param array $messages Array of message objects
     * @return array Formatted contents for Gemini
     */
    private function format_messages_for_gemini($messages) {
        $contents = array();

        foreach ($messages as $msg) {
            $role = (isset($msg['role']) && $msg['role'] === 'assistant') ? 'model' : 'user';
            $content = isset($msg['content']) ? $msg['content'] : '';

            // Skip system messages - they go in systemInstruction
            if (isset($msg['role']) && $msg['role'] === 'system') {
                continue;
            }

            // Handle vision/image content
            if (!empty($msg['images']) && is_array($msg['images'])) {
                $parts = array();

                if (!empty($content)) {
                    $parts[] = array('text' => $content);
                }

                foreach ($msg['images'] as $image_url) {
                    $parsed = \ChatProjects\Security::parse_base64_image($image_url);
                    if ($parsed) {
                        $parts[] = array(
                            'inline_data' => array(
                                'mime_type' => $parsed['mime_type'],
                                'data' => $parsed['data'],
                            ),
                        );
                    }
                }

                $contents[] = array(
                    'role' => $role,
                    'parts' => $parts,
                );
            } else {
                $contents[] = array(
                    'role' => $role,
                    'parts' => array(
                        array('text' => $content),
                    ),
                );
            }
        }

        return $contents;
    }
}
