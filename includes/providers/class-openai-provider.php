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
            'gpt-5.2' => 'GPT-5.2 (Latest)',
            'gpt-5.2-pro' => 'GPT-5.2 Pro',
            'gpt-5.2-chat-latest' => 'GPT-5.2 Instant',
            'gpt-5-mini' => 'GPT-5 Mini',
            'gpt-5-nano' => 'GPT-5 Nano',
            'gpt-5.1' => 'GPT-5.1',
            'gpt-5.1-codex-max' => 'GPT-5.1 Codex Max',
            'gpt-5' => 'GPT-5',
            'o1-preview' => 'O1 Preview',
            'o1-mini' => 'O1 Mini',
            'gpt-4o' => 'GPT-4o',
            'gpt-4o-mini' => 'GPT-4o Mini',
            'gpt-4-turbo' => 'GPT-4 Turbo',
            'gpt-4' => 'GPT-4',
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
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
     * @param array    $messages Array of message objects
     * @param string   $model    Model identifier
     * @param callable $callback Callback for each chunk
     * @param array    $options  Additional options
     * @return void
     */
    public function stream_completion($messages, $model, $callback, $options = array()) {
        if (!$this->has_api_key()) {
            $callback(array('type' => 'error', 'content' => __('OpenAI API key is not configured.', 'chatprojects')));
            return;
        }

        if (empty($messages)) {
            $callback(array('type' => 'error', 'content' => __('No messages provided.', 'chatprojects')));
            return;
        }

        $url = self::API_BASE_URL . 'chat/completions';

        // Build request data for Chat Completions API
        $data = array(
            'model' => $model,
            'messages' => $this->format_messages_for_chat_api($messages),
            'stream' => true,
        );

        // Add system instructions if provided
        if (!empty($options['instructions'])) {
            array_unshift($data['messages'], array(
                'role' => 'system',
                'content' => $options['instructions']
            ));
        }

        // Handle model-specific options
        $is_newer_model = $this->is_newer_model($model);

        if (!$is_newer_model && isset($options['temperature'])) {
            $data['temperature'] = $options['temperature'];
        }

        if (isset($options['max_tokens'])) {
            $data['max_tokens'] = $options['max_tokens'];
        }

        // cURL is required for Server-Sent Events (SSE) streaming.
        // WordPress HTTP API (wp_remote_*) does not support:
        // 1. CURLOPT_WRITEFUNCTION callbacks for real-time chunk processing
        // 2. Streaming responses - it waits for the entire response before returning
        // 3. Progressive data handling needed for AI chat streaming
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_init -- Required for SSE streaming; WP HTTP API lacks callback support
        $ch = curl_init($url);

        if ($ch === false) {
            $callback(array('type' => 'error', 'content' => __('Failed to initialize streaming.', 'chatprojects')));
            return;
        }

        $buffer = '';

        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt_array -- cURL required for SSE streaming
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => wp_json_encode($data),
            CURLOPT_HTTPHEADER     => array(
                'Authorization: Bearer ' . $this->api_key,
                'Content-Type: application/json',
                'Accept: text/event-stream',
            ),
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT        => 300,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_BUFFERSIZE     => 128,
            // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt -- cURL required for SSE streaming
            CURLOPT_WRITEFUNCTION  => function($ch, $chunk) use ($callback, &$buffer) {
                $buffer .= $chunk;

                // Process complete SSE events
                while (($pos = strpos($buffer, "\n\n")) !== false) {
                    $event = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 2);

                    // Parse data line
                    if (preg_match('/^data: (.+)$/m', $event, $matches)) {
                        $json_data = trim($matches[1]);

                        if ($json_data === '[DONE]') {
                            continue;
                        }

                        $parsed = json_decode($json_data, true);
                        if ($parsed && isset($parsed['choices'][0]['delta']['content'])) {
                            $content = $parsed['choices'][0]['delta']['content'];
                            $callback(array('type' => 'content', 'content' => $content));
                        }
                    }
                }

                return strlen($chunk);
            },
        ));

        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_exec -- cURL required for SSE streaming
        $result = curl_exec($ch);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_errno -- cURL required for SSE streaming
        $error_no = curl_errno($ch);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_error -- cURL required for SSE streaming
        $error_msg = curl_error($ch);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_getinfo -- cURL required for SSE streaming
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_close -- cURL required for SSE streaming
        curl_close($ch);

        if ($error_no !== 0) {
            $callback(array('type' => 'error', 'content' => __('Connection error: ', 'chatprojects') . $error_msg));
            return;
        }

        if ($http_code >= 400) {
            $callback(array('type' => 'error', 'content' => __('API error (HTTP ', 'chatprojects') . $http_code . ')'));
            return;
        }

        $callback(array('type' => 'done'));
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
