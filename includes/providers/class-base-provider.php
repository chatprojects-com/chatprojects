<?php
/**
 * Base Provider Class
 *
 * Abstract base class for all AI provider implementations
 * Updated for Responses API - no more thread storage
 *
 * @package ChatProjects
 */

namespace ChatProjects\Providers;

use ChatProjects\Security;
use ChatProjects\SSE_Stream_Manager;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base Provider Abstract Class
 */
abstract class Base_Provider implements AI_Provider_Interface {
    /**
     * Provider API key
     *
     * @var string
     */
    protected $api_key;

    /**
     * Provider name
     *
     * @var string
     */
    protected $name;

    /**
     * Provider identifier
     *
     * @var string
     */
    protected $identifier;

    /**
     * Available models
     *
     * @var array
     */
    protected $models = array();

    /**
     * API base URL
     *
     * @var string
     */
    protected $api_base_url;

    /**
     * Constructor
     */
    public function __construct() {
        $this->load_api_key();
    }

    /**
     * Load API key from settings
     */
    protected function load_api_key() {
        $option_name = 'chatprojects_' . $this->identifier . '_key';
        $encrypted_key = get_option($option_name, '');

        if (!empty($encrypted_key)) {
            $this->api_key = Security::decrypt($encrypted_key);
        }
    }

    /**
     * Check if API key is configured
     *
     * @return bool
     */
    public function has_api_key() {
        return !empty($this->api_key);
    }

    /**
     * Get provider name
     *
     * @return string
     */
    public function get_name() {
        return $this->name;
    }

    /**
     * Get provider identifier
     *
     * @return string
     */
    public function get_identifier() {
        return $this->identifier;
    }

    /**
     * Get available models
     *
     * @return array
     */
    public function get_available_models() {
        return $this->models;
    }

    /**
     * Make HTTP request to provider API
     *
     * @param string $url     API endpoint URL
     * @param array  $data    Request data
     * @param string $method  HTTP method
     * @param array  $headers Additional headers
     * @return array|WP_Error Response data or error
     */
    protected function make_request($url, $data = array(), $method = 'POST', $headers = array()) {
        if (!$this->has_api_key()) {
            return new \WP_Error(
                'no_api_key',
                /* translators: %s: Provider name (e.g., OpenAI, Anthropic) */
                sprintf(__('%s API key is not configured.', 'chatprojects'), $this->name)
            );
        }

        $args = array(
            'headers' => $headers,
            'method' => $method,
            'timeout' => 120,
        );

        if ($method !== 'GET' && !empty($data)) {
            $args['body'] = wp_json_encode($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $this->log('API request failed', 'error', array(
                'url' => $url,
                'error' => $response->get_error_message(),
            ));
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if ($status_code >= 400) {
            $error_message = $this->extract_error_message($decoded, $status_code);

            $this->log('API error', 'error', array(
                'status' => $status_code,
                'response' => $decoded,
            ));

            return new \WP_Error(
                'api_error',
                $error_message,
                array('status' => $status_code, 'response' => $decoded)
            );
        }

        return $decoded;
    }

    /**
     * HTTP status of the last streaming request (0 on connection failure).
     *
     * @var int
     */
    protected $last_stream_status = 0;

    /**
     * Provider error code from the last failed streaming request, if any.
     *
     * @var string
     */
    protected $last_stream_error_code = '';

    /**
     * Execute a streaming request using WordPress HTTP API.
     *
     * Uses the SSE_Stream_Manager to handle streaming via the http_api_curl hook.
     * This is compliant with WordPress plugin directory requirements.
     *
     * @param string   $url      API URL.
     * @param array    $data     Request body data (will be JSON encoded).
     * @param array    $headers  HTTP headers (associative array format for WordPress).
     * @param callable $callback Callback for each parsed event: function(array $event).
     * @param callable $parser   Provider-specific SSE parser: function($chunk, $callback, &$buffer, &$state).
     * @param array    $state    Optional state to pass to parser.
     * @return bool|string True on success, user-facing error message on failure.
     */
    protected function make_streaming_request( $url, $data, $headers, $callback, $parser, $state = array() ) {
        $manager  = SSE_Stream_Manager::get_instance();
        $response = $manager->stream_request( $url, $data, $headers, $callback, $parser, $state );

        $this->last_stream_status     = 0;
        $this->last_stream_error_code = '';

        if ( is_wp_error( $response ) ) {
            /* translators: %s: connection error message */
            return sprintf( __( 'Connection error: %s', 'chatprojects' ), $response->get_error_message() );
        }

        // On success the body was consumed by the parser; on failure the
        // stream manager keeps the provider's JSON error body for us.
        $this->last_stream_status = (int) wp_remote_retrieve_response_code( $response );
        if ( $this->last_stream_status >= 400 ) {
            $this->last_stream_error_code = SSE_Stream_Manager::error_code( $response );
            return SSE_Stream_Manager::error_message( $response );
        }

        return true;
    }

    /**
     * Request body for an OpenAI-compatible /chat/completions call.
     *
     * The system prompt goes first in messages (there is no top-level
     * "system" field in this API). temperature and max_tokens are sent only
     * when the caller sets them, so each model's own defaults apply.
     *
     * @param string $model    Model id.
     * @param array  $messages Messages with 'role' and 'content'.
     * @param array  $options  Caller options (instructions, temperature, max_tokens).
     * @param bool   $stream   Whether to stream.
     * @return array
     */
    protected function chat_completions_body( $model, $messages, $options, $stream ) {
        $formatted = array();
        if ( ! empty( $options['instructions'] ) ) {
            $formatted[] = array(
                'role'    => 'system',
                'content' => $options['instructions'],
            );
        }
        foreach ( $messages as $msg ) {
            $formatted[] = array(
                'role'    => isset( $msg['role'] ) ? $msg['role'] : 'user',
                'content' => isset( $msg['content'] ) ? $msg['content'] : '',
            );
        }

        $data = array(
            'model'    => $model,
            'messages' => $formatted,
        );
        if ( isset( $options['temperature'] ) ) {
            $data['temperature'] = (float) $options['temperature'];
        }
        if ( isset( $options['max_tokens'] ) ) {
            $data['max_tokens'] = absint( $options['max_tokens'] );
        }
        if ( $stream ) {
            $data['stream'] = true;
        }
        return $data;
    }

    /**
     * Stream an OpenAI-compatible /chat/completions request.
     *
     * Emits content chunks, reports errors sent inside the stream, and marks
     * replies that stop early (finish_reason "length" / "content_filter").
     *
     * @param string   $url      Endpoint URL.
     * @param array    $headers  Request headers.
     * @param array    $data     Body from chat_completions_body( ..., true ).
     * @param callable $callback Event callback.
     * @return void
     */
    protected function stream_chat_completions( $url, $headers, $data, $callback ) {
        $parser = function ( $chunk, $callback, &$buffer, &$state ) {
            $buffer .= $chunk;

            // Process complete SSE events (separated by a blank line).
            while ( ( $pos = strpos( $buffer, "\n\n" ) ) !== false ) {
                $event  = substr( $buffer, 0, $pos );
                $buffer = substr( $buffer, $pos + 2 );

                foreach ( explode( "\n", $event ) as $line ) {
                    // Lines starting with ":" are keep-alive comments.
                    if ( 0 !== strpos( $line, 'data:' ) ) {
                        continue;
                    }
                    $json_data = trim( substr( $line, 5 ) );
                    if ( '' === $json_data || '[DONE]' === $json_data ) {
                        continue;
                    }

                    $parsed = json_decode( $json_data, true );
                    if ( ! is_array( $parsed ) ) {
                        continue;
                    }

                    if ( isset( $parsed['error'] ) ) {
                        $error_msg = isset( $parsed['error']['message'] ) ? $parsed['error']['message'] : __( 'The AI provider reported an error while generating the reply.', 'chatprojects' );
                        $callback( array( 'type' => 'error', 'content' => $error_msg ) );
                        continue;
                    }

                    if ( isset( $parsed['choices'][0]['delta']['content'] ) && '' !== $parsed['choices'][0]['delta']['content'] ) {
                        $state['has_text'] = true;
                        $callback( array( 'type' => 'content', 'content' => $parsed['choices'][0]['delta']['content'] ) );
                    }

                    $finish = isset( $parsed['choices'][0]['finish_reason'] ) ? $parsed['choices'][0]['finish_reason'] : '';
                    if ( 'length' === $finish || 'content_filter' === $finish ) {
                        if ( empty( $state['has_text'] ) ) {
                            $callback( array( 'type' => 'error', 'content' => __( 'The model stopped before producing a reply.', 'chatprojects' ) ) );
                        } else {
                            $callback( array( 'type' => 'content', 'content' => SSE_Stream_Manager::truncation_notice( 'length' === $finish ? 'max_tokens' : 'content_filter' ) ) );
                        }
                    }
                }
            }
        };

        $result = $this->make_streaming_request( $url, $data, $headers, $callback, $parser );

        if ( true !== $result ) {
            $callback( array( 'type' => 'error', 'content' => $result ) );
            return;
        }

        $callback( array( 'type' => 'done' ) );
    }

    /**
     * Extract error message from API response
     *
     * @param array $response Decoded response
     * @param int   $status   HTTP status code
     * @return string Error message
     */
    protected function extract_error_message($response, $status) {
        // All providers use the same error format
        if (isset($response['error']['message'])) {
            return $response['error']['message'];
        }
        /* translators: %d: HTTP status code */
        return sprintf(__('API request failed with status %d', 'chatprojects'), $status);
    }

    /**
     * Format error response
     *
     * @param string $code    Error code
     * @param string $message Error message
     * @param array  $data    Additional error data
     * @return WP_Error
     */
    protected function error($code, $message, $data = array()) {
        return new \WP_Error($code, $message, $data);
    }

    /**
     * Log provider activity
     *
     * @param string $message Log message
     * @param string $level   Log level (info, warning, error)
     * @param array  $context Additional context
     */
    protected function log($message, $level = 'info', $context = array()) {
        Security::log_security_event(
            $this->identifier . ': ' . $message,
            $level,
            $context
        );
    }

    /**
     * Default stream completion implementation (non-streaming fallback)
     * Providers should override this for proper streaming support
     *
     * @param array    $messages Array of message objects
     * @param string   $model    Model identifier
     * @param callable $callback Callback for each chunk
     * @param array    $options  Additional options
     * @return void
     */
    public function stream_completion($messages, $model, $callback, $options = array()) {
        // Default: fall back to non-streaming
        $response = $this->run_completion($messages, $model, $options);

        if (is_wp_error($response)) {
            $callback(array('type' => 'error', 'content' => $response->get_error_message()));
            return;
        }

        if (isset($response['content'])) {
            $callback(array('type' => 'content', 'content' => $response['content']));
        }

        $callback(array('type' => 'done'));
    }
}
