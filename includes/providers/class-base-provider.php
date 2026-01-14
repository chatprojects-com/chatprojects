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
     * Extract error message from API response
     *
     * @param array $response Decoded response
     * @param int   $status   HTTP status code
     * @return string Error message
     */
    protected function extract_error_message($response, $status) {
        // OpenAI format
        if (isset($response['error']['message'])) {
            return $response['error']['message'];
        }
        // Anthropic format
        if (isset($response['error']['message'])) {
            return $response['error']['message'];
        }
        // Gemini format
        if (isset($response['error']['message'])) {
            return $response['error']['message'];
        }
        // Generic fallback
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
