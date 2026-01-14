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
            'gemini-2.5-pro' => 'Gemini 2.5 Pro',
            'gemini-2.5-flash' => 'Gemini 2.5 Flash',
            'gemini-2.5-flash-lite' => 'Gemini 2.5 Flash Lite',
            'gemini-2.0-flash' => 'Gemini 2.0 Flash',
            'gemini-2.0-flash-lite' => 'Gemini 2.0 Flash Lite',
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
     * @param array    $messages Array of message objects
     * @param string   $model    Model identifier
     * @param callable $callback Callback for each chunk
     * @param array    $options  Additional options
     * @return void
     */
    public function stream_completion($messages, $model, $callback, $options = array()) {
        if (!$this->has_api_key()) {
            $callback(array('type' => 'error', 'content' => __('Gemini API key is not configured.', 'chatprojects')));
            return;
        }

        if (empty($messages)) {
            $callback(array('type' => 'error', 'content' => __('No messages provided.', 'chatprojects')));
            return;
        }

        // Format conversation for Gemini
        $contents = $this->format_messages_for_gemini($messages);

        $data = array(
            'contents' => $contents,
            'generationConfig' => array(
                'temperature' => isset($options['temperature']) ? $options['temperature'] : 0.7,
                'maxOutputTokens' => isset($options['max_tokens']) ? $options['max_tokens'] : 2048,
            ),
        );

        if (!empty($options['instructions'])) {
            $data['systemInstruction'] = array(
                'parts' => array(
                    array('text' => $options['instructions']),
                ),
            );
        }

        $url = self::API_BASE_URL . "models/{$model}:streamGenerateContent?alt=sse&key=" . $this->api_key;

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
        $content_found = false;

        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt_array -- cURL required for SSE streaming
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => wp_json_encode($data),
            CURLOPT_HTTPHEADER     => array(
                'Content-Type: application/json',
                'Accept: text/event-stream',
            ),
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT        => 300,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_BUFFERSIZE     => 128,
            // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt -- cURL required for SSE streaming
            CURLOPT_WRITEFUNCTION  => function($ch, $chunk) use ($callback, &$buffer, &$content_found) {
                $buffer .= $chunk;

                // Process complete lines - split by newlines
                $lines = explode("\n", $buffer);
                // Keep the last potentially incomplete line in buffer
                $buffer = array_pop($lines);

                foreach ($lines as $line) {
                    $line = trim($line);

                    // Skip empty lines
                    if (empty($line)) {
                        continue;
                    }

                    // Check for data: prefix
                    if (strpos($line, 'data:') === 0) {
                        $json_str = trim(substr($line, 5));

                        // Skip empty data or [DONE]
                        if (empty($json_str) || $json_str === '[DONE]') {
                            continue;
                        }

                        $parsed = json_decode($json_str, true);

                        if ($parsed === null) {
                            continue;
                        }

                        // Check for API error response
                        if (isset($parsed['error'])) {
                            $error_msg = isset($parsed['error']['message']) ? $parsed['error']['message'] : 'Unknown Gemini error';
                            $callback(array('type' => 'error', 'content' => $error_msg));
                            continue;
                        }

                        // Extract text from Gemini's response
                        if (isset($parsed['candidates'][0]['content']['parts'])) {
                            foreach ($parsed['candidates'][0]['content']['parts'] as $part) {
                                if (isset($part['text'])) {
                                    $content_found = true;
                                    $callback(array('type' => 'content', 'content' => $part['text']));
                                }
                            }
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

        // Process any remaining buffer content
        if (!empty($buffer)) {
            // Try to parse remaining content as JSON (might be error response)
            $line = trim($buffer);
            if (strpos($line, 'data:') === 0) {
                $json_str = trim(substr($line, 5));
                $parsed = json_decode($json_str, true);
                if ($parsed && isset($parsed['error']['message'])) {
                    $callback(array('type' => 'error', 'content' => $parsed['error']['message']));
                    return;
                }
            } elseif ($line && $line[0] === '{') {
                // Direct JSON without data: prefix
                $parsed = json_decode($line, true);
                if ($parsed && isset($parsed['error']['message'])) {
                    $callback(array('type' => 'error', 'content' => $parsed['error']['message']));
                    return;
                }
            }
        }

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
