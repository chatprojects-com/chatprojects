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

use ChatProjects\Model_Registry;
use ChatProjects\SSE_Stream_Manager;

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
        $this->models = Model_Registry::get_labels( 'gemini' );

        parent::__construct();
    }

    /**
     * Build generationConfig for a model.
     *
     * @param string $model   Model id.
     * @param array  $options Caller options.
     * @return array
     */
    private function build_generation_config( $model, $options ) {
        $entry      = Model_Registry::get_model( 'gemini', $model );
        $max_output = $entry ? (int) $entry['max_output'] : 8192;
        $max_tokens = isset( $options['max_tokens'] ) ? absint( $options['max_tokens'] ) : $max_output;

        $config = array(
            'maxOutputTokens' => max( 1, min( $max_tokens, $max_output ) ),
        );

        if ( isset( $options['temperature'] ) && ( ! $entry || ! empty( $entry['supports_temperature'] ) ) ) {
            $config['temperature'] = (float) $options['temperature'];
        }

        return $config;
    }

    /**
     * Request headers. The API key travels in a header, never in the URL.
     *
     * @param string|null $api_key Key override (validation), defaults to the stored key.
     * @param bool        $stream  Whether to request an event stream.
     * @return array
     */
    private function request_headers( $api_key = null, $stream = false ) {
        $headers = array(
            'Content-Type'   => 'application/json',
            'x-goog-api-key' => null === $api_key ? $this->api_key : $api_key,
        );
        if ( $stream ) {
            $headers['Accept'] = 'text/event-stream';
        }
        return $headers;
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
            'generationConfig' => $this->build_generation_config( $model, $options ),
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
        $url = self::API_BASE_URL . 'models/' . rawurlencode( $model ) . ':generateContent';

        $headers = $this->request_headers();

        $response = $this->make_request($url, $data, 'POST', $headers);

        if (is_wp_error($response)) {
            return $response;
        }

        if (!empty($response['promptFeedback']['blockReason'])) {
            return $this->error('blocked', __('Gemini blocked this request because of its safety settings.', 'chatprojects'));
        }

        // Extract the reply text (skipping "thought" summary parts).
        $content = '';
        if (!empty($response['candidates'][0]['content']['parts']) && is_array($response['candidates'][0]['content']['parts'])) {
            foreach ($response['candidates'][0]['content']['parts'] as $part) {
                if (isset($part['text']) && empty($part['thought'])) {
                    $content .= $part['text'];
                }
            }
        }

        $finish = isset($response['candidates'][0]['finishReason']) ? $response['candidates'][0]['finishReason'] : '';
        if ('' !== $content && $this->is_early_finish($finish)) {
            $content .= SSE_Stream_Manager::truncation_notice($this->finish_reason_key($finish));
        }

        if ('' !== $content) {
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
            'generationConfig' => $this->build_generation_config( $model, $options ),
        );

        if ( ! empty( $options['instructions'] ) ) {
            $data['systemInstruction'] = array(
                'parts' => array(
                    array( 'text' => $options['instructions'] ),
                ),
            );
        }

        $url = self::API_BASE_URL . 'models/' . rawurlencode( $model ) . ':streamGenerateContent?alt=sse';

        // Headers for WordPress HTTP API (associative array format).
        $headers = $this->request_headers( null, true );

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

                    if ( ! empty( $parsed['promptFeedback']['blockReason'] ) ) {
                        $callback( array( 'type' => 'error', 'content' => __( 'Gemini blocked this request because of its safety settings.', 'chatprojects' ) ) );
                        continue;
                    }

                    // Extract text from Gemini's response (skipping "thought" summary parts).
                    if ( isset( $parsed['candidates'][0]['content']['parts'] ) ) {
                        foreach ( $parsed['candidates'][0]['content']['parts'] as $part ) {
                            if ( isset( $part['text'] ) && empty( $part['thought'] ) ) {
                                $state['has_text'] = true;
                                $callback( array( 'type' => 'content', 'content' => $part['text'] ) );
                            }
                        }
                    }

                    // MAX_TOKENS, SAFETY, etc. end the reply early.
                    $finish = isset( $parsed['candidates'][0]['finishReason'] ) ? $parsed['candidates'][0]['finishReason'] : '';
                    if ( $this->is_early_finish( $finish ) ) {
                        if ( empty( $state['has_text'] ) ) {
                            $callback( array( 'type' => 'error', 'content' => __( 'Gemini stopped before producing a reply.', 'chatprojects' ) ) );
                        } else {
                            $callback( array( 'type' => 'content', 'content' => SSE_Stream_Manager::truncation_notice( $this->finish_reason_key( $finish ) ) ) );
                        }
                    }
                }
            }
        };

        // Execute streaming request using WordPress HTTP API.
        $result = $this->make_streaming_request( $url, $data, $headers, $callback, $parser );

        if ( true !== $result ) {
            $callback( array( 'type' => 'error', 'content' => $result ) );
            return;
        }

        $callback( array( 'type' => 'done' ) );
    }

    /**
     * Whether a Gemini finishReason means the reply ended before it was complete.
     *
     * @param string $finish finishReason value.
     * @return bool
     */
    private function is_early_finish( $finish ) {
        return '' !== (string) $finish && ! in_array( $finish, array( 'STOP', 'FINISH_REASON_UNSPECIFIED' ), true );
    }

    /**
     * Map a Gemini finishReason to a truncation_notice() reason.
     *
     * @param string $finish finishReason value.
     * @return string
     */
    private function finish_reason_key( $finish ) {
        return 'MAX_TOKENS' === $finish ? 'max_tokens' : 'safety';
    }

    /**
     * Validate API key
     *
     * @param string $api_key API key to validate
     * @return bool|WP_Error True if valid, error otherwise
     */
    public function validate_api_key($api_key) {
        $url = self::API_BASE_URL . 'models';

        $response = wp_remote_get(
            $url,
            array(
                'timeout' => 10,
                'headers' => $this->request_headers( $api_key ),
            )
        );

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
