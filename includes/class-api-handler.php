<?php
/**
 * API Handler Class
 *
 * Handles all OpenAI API interactions
 *
 * @package ChatProjects
 */

namespace ChatProjects;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * API Handler Class
 */
class API_Handler {
    /**
     * OpenAI API base URL
     */
    const API_BASE_URL = 'https://api.openai.com/v1/';

    /**
     * API key
     *
     * @var string
     */
    private $api_key;

    /**
     * Default model
     *
     * @var string
     */
    private $default_model;

    /**
     * Buffer for incomplete SSE data across chunks
     *
     * @var string
     */
    private $sse_buffer = '';

    /**
     * Constructor
     */
    public function __construct() {
        $this->api_key = Security::get_api_key();
        $this->default_model = get_option('chatprojects_default_model', 'gpt-5.2');
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
     * Make API request
     *
     * @param string $endpoint API endpoint
     * @param array  $data Request data
     * @param string $method HTTP method (GET, POST, DELETE)
     * @param array  $headers Additional headers
     * @return array|WP_Error Response data or error
     */
    private function make_request($endpoint, $data = array(), $method = 'POST', $headers = array()) {
        if (!$this->has_api_key()) {
            return new \WP_Error('no_api_key', __('OpenAI API key is not configured.', 'chatprojects'));
        }

        $url = self::API_BASE_URL . $endpoint;

        $default_headers = array(
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json',
        );

        $headers = array_merge($default_headers, $headers);

        $args = array(
            'headers' => $headers,
            'method' => $method,
            'timeout' => 60,
        );

        if ($method !== 'GET' && !empty($data)) {
            $args['body'] = wp_json_encode($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            Security::log_security_event('API request failed', 'error', array(
                'endpoint' => $endpoint,
                'error' => $response->get_error_message(),
            ));
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if ($status_code < 200 || $status_code >= 300) {
            $error_message = isset($decoded['error']['message']) ? $decoded['error']['message'] : __('Unknown API error', 'chatprojects');

            Security::log_security_event('API error response', 'error', array(
                'endpoint' => $endpoint,
                'status' => $status_code,
                'error' => $error_message,
            ));

            return new \WP_Error('api_error', $error_message, array('status' => $status_code));
        }

        return $decoded;
    }

    /**
     * Create Vector Store
     *
     * @param string $name Vector store name
     * @param array  $file_ids File IDs to add
     * @return array|WP_Error
     */
    public function create_vector_store($name, $file_ids = array()) {
        $data = array(
            'name' => $name,
        );

        if (!empty($file_ids)) {
            $data['file_ids'] = $file_ids;
        }

        return $this->make_request('vector_stores', $data);
    }

    /**
     * Delete Vector Store
     *
     * @param string $vector_store_id Vector store ID
     * @return array|WP_Error
     */
    public function delete_vector_store($vector_store_id) {
        return $this->make_request('vector_stores/' . $vector_store_id, array(), 'DELETE');
    }

    /**
     * Upload File
     *
     * @param string $file_path Local file path
     * @param string $purpose File purpose (assistants, vision, etc.)
     * @param string $original_filename Original filename (optional, for temp files)
     * @return array|WP_Error
     */
    public function upload_file($file_path, $purpose = 'assistants', $original_filename = null) {
        if (!file_exists($file_path)) {
            return new \WP_Error('file_not_found', __('File not found.', 'chatprojects'));
        }

        $url = self::API_BASE_URL . 'files';

        $boundary = wp_generate_password(24, false);
        
        $headers = array(
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        );

        $file_contents = file_get_contents($file_path);
        $filename = $original_filename ? $original_filename : basename($file_path);

        $body = "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"purpose\"\r\n\r\n";
        $body .= "{$purpose}\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"{$filename}\"\r\n";
        $body .= "Content-Type: application/octet-stream\r\n\r\n";
        $body .= $file_contents . "\r\n";
        $body .= "--{$boundary}--\r\n";

        $args = array(
            'headers' => $headers,
            'body' => $body,
            'method' => 'POST',
            'timeout' => 120,
        );

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if ($status_code < 200 || $status_code >= 300) {
            $error_message = isset($decoded['error']['message']) ? $decoded['error']['message'] : __('Unknown API error', 'chatprojects');
            return new \WP_Error('api_error', $error_message, array('status' => $status_code));
        }

        return $decoded;
    }

    /**
     * Delete File
     *
     * @param string $file_id File ID
     * @return array|WP_Error
     */
    public function delete_file($file_id) {
        return $this->make_request('files/' . $file_id, array(), 'DELETE');
    }

    /**
     * Add File to Vector Store
     *
     * @param string $vector_store_id Vector store ID
     * @param string $file_id File ID
     * @return array|WP_Error
     */
    public function add_file_to_vector_store($vector_store_id, $file_id) {
        return $this->make_request("vector_stores/{$vector_store_id}/files", array(
            'file_id' => $file_id,
        ));
    }

    /**
     * Remove File from Vector Store
     *
     * @param string $vector_store_id Vector store ID
     * @param string $file_id File ID
     * @return array|WP_Error
     */
    public function remove_file_from_vector_store($vector_store_id, $file_id) {
        return $this->make_request("vector_stores/{$vector_store_id}/files/{$file_id}", array(), 'DELETE');
    }

    /**
     * List files in Vector Store
     *
     * @param string $vector_store_id Vector store ID
     * @return array|WP_Error
     */
    public function list_vector_store_files($vector_store_id) {
        return $this->make_request("vector_stores/{$vector_store_id}/files", array(), 'GET');
    }

    /**
     * Get Vector Store file details
     *
     * @param string $vector_store_id Vector store ID
     * @param string $file_id File ID
     * @return array|WP_Error
     */
    public function get_vector_store_file($vector_store_id, $file_id) {
        return $this->make_request("vector_stores/{$vector_store_id}/files/{$file_id}", array(), 'GET');
    }

    /**
     * Transcribe Audio
     *
     * @param string $file_path Local audio file path
     * @param string $language Language code (optional)
     * @param string $prompt Transcription prompt (optional)
     * @return array|WP_Error
     */
    public function transcribe_audio($file_path, $language = '', $prompt = '') {
        if (!file_exists($file_path)) {
            return new \WP_Error('file_not_found', __('Audio file not found.', 'chatprojects'));
        }

        $url = self::API_BASE_URL . 'audio/transcriptions';

        $boundary = wp_generate_password(24, false);
        
        $headers = array(
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        );

        $file_contents = file_get_contents($file_path);
        $filename = basename($file_path);

        $body = "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"{$filename}\"\r\n";
        $body .= "Content-Type: application/octet-stream\r\n\r\n";
        $body .= $file_contents . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"model\"\r\n\r\n";
        $body .= "whisper-1\r\n";

        if (!empty($language)) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"language\"\r\n\r\n";
            $body .= "{$language}\r\n";
        }

        if (!empty($prompt)) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"prompt\"\r\n\r\n";
            $body .= "{$prompt}\r\n";
        }

        $body .= "--{$boundary}--\r\n";

        $args = array(
            'headers' => $headers,
            'body' => $body,
            'method' => 'POST',
            'timeout' => 120,
        );

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if ($status_code < 200 || $status_code >= 300) {
            $error_message = isset($decoded['error']['message']) ? $decoded['error']['message'] : __('Unknown API error', 'chatprojects');
            return new \WP_Error('api_error', $error_message, array('status' => $status_code));
        }

        return $decoded;
    }

    /**
     * Create Chat Completion
     *
     * @param array  $messages Messages array
     * @param string $model Model to use
     * @param array  $options Additional options
     * @return array|WP_Error
     */
    public function create_chat_completion($messages, $model = null, $options = array()) {
        if (null === $model) {
            $model = $this->default_model;
        }

        $data = array_merge(array(
            'model' => $model,
            'messages' => $messages,
        ), $options);

        return $this->make_request('chat/completions', $data);
    }

    /**
     * Create Response with File Search (Responses API)
     *
     * @param string $input User input/message
     * @param string $vector_store_id Vector store ID
     * @param string $model Model to use
     * @param string $instructions System instructions
     * @param array  $options Additional options (max_num_results, etc.)
     * @return array|WP_Error
     */
    public function create_response_with_filesearch($input, $vector_store_id, $model = null, $instructions = '', $options = array()) {
        if (null === $model) {
            $model = $this->default_model;
        }

        // Responses API format
        $data = array(
            'model' => $model,
            'input' => $input,
            'tools' => array(
                array(
                    'type' => 'file_search',
                    'vector_store_ids' => array($vector_store_id)
                )
            )
        );

        // Add instructions if provided
        if (!empty($instructions)) {
            $data['instructions'] = $instructions;
        }

        // Merge any additional options (like max_num_results)
        if (!empty($options)) {
            // If max_num_results is provided, add it to the file_search tool
            if (isset($options['max_num_results'])) {
                $data['tools'][0]['max_num_results'] = $options['max_num_results'];
                unset($options['max_num_results']);
            }
            $data = array_merge($data, $options);
        }

        return $this->make_request('responses', $data);
    }

    /**
     * Enhance Prompt
     *
     * @param string $prompt Original prompt
     * @param string $context Additional context
     * @return string|WP_Error Enhanced prompt or error
     */
    public function enhance_prompt($prompt, $context = '') {
        $system_message = "You are a prompt enhancement expert. Improve the given prompt to make it more effective, clear, and actionable while maintaining the user's original intent.";
        
        if (!empty($context)) {
            $system_message .= " Context: " . $context;
        }

        $messages = array(
            array('role' => 'system', 'content' => $system_message),
            array('role' => 'user', 'content' => $prompt),
        );

        $response = $this->create_chat_completion($messages, 'gpt-4o');

        if (is_wp_error($response)) {
            return $response;
        }

        if (isset($response['choices'][0]['message']['content'])) {
            return $response['choices'][0]['message']['content'];
        }

        return new \WP_Error('invalid_response', __('Invalid API response.', 'chatprojects'));
    }

    /**
     * Create Response using Responses API (without file search)
     *
     * @param array|string $input Messages array or single message string
     * @param string $model Model to use
     * @param string $instructions System instructions
     * @param array $options Additional options
     * @return array|WP_Error
     */
    public function create_response($input, $model = null, $instructions = '', $options = array()) {
        if (null === $model) {
            $model = $this->default_model;
        }

        // Responses API format
        $data = array(
            'model' => $model,
            'input' => $input,
        );

        // Add instructions if provided
        if (!empty($instructions)) {
            $data['instructions'] = $instructions;
        }

        // Merge any additional options
        if (!empty($options)) {
            $data = array_merge($data, $options);
        }

        return $this->make_request('responses', $data);
    }

    /**
     * Stream response using Responses API (without file search)
     *
     * @param array|string $input Messages array or single message string
     * @param callable $callback Callback function for each chunk
     * @param string $model Model to use
     * @param string $instructions System instructions
     * @param array $options Additional options
     * @return void
     */
    public function stream_response($input, $callback, $model = null, $instructions = '', $options = array()) {
        if (!$this->has_api_key()) {
            $callback(array('type' => 'error', 'content' => 'OpenAI API key is not configured'));
            return;
        }

        if (null === $model) {
            $model = $this->default_model;
        }

        $url = self::API_BASE_URL . 'responses';

        // Responses API with streaming enabled
        $data = array(
            'model'  => $model,
            'input'  => $input,
            'stream' => true,
        );

        // Add instructions if provided
        if (!empty($instructions)) {
            $data['instructions'] = $instructions;
        }

        // Merge any additional options
        $data = array_merge($data, $options);

        // Context for tracking state (not used for non-file-search but required by method)
        $context = array();

        // Use cURL streaming
        $result = $this->make_streaming_request($url, $data, $callback, $context);

        // Handle streaming errors
        if ($result !== true) {
            $callback(array('type' => 'error', 'content' => $result));
            return;
        }

        $callback(array('type' => 'done'));
    }

    /**
     * Extract text from Responses API response
     *
     * @param array $result API response
     * @return string Extracted text content
     */
    public function extract_response_text($result) {
        $text_content = '';

        if (isset($result['output']) && is_array($result['output'])) {
            foreach ($result['output'] as $output_item) {
                if (isset($output_item['type']) && $output_item['type'] === 'message') {
                    if (isset($output_item['content']) && is_array($output_item['content'])) {
                        foreach ($output_item['content'] as $content_item) {
                            if (isset($content_item['type']) && $content_item['type'] === 'output_text') {
                                if (isset($content_item['text'])) {
                                    $text_content .= $content_item['text'];
                                }
                            }
                        }
                    }
                }
            }
        }

        return $text_content;
    }

    /**
     * Make a streaming HTTP request using cURL
     *
     * @param string   $url      API URL
     * @param array    $data     Request data
     * @param callable $callback Callback for each parsed event
     * @param array    $context  Context data (for annotations tracking)
     * @return bool|string True on success, error message on failure
     */
    private function make_streaming_request($url, $data, $callback, &$context = array()) {
        // cURL is required for Server-Sent Events (SSE) streaming.
        // WordPress HTTP API (wp_remote_*) does not support:
        // 1. CURLOPT_WRITEFUNCTION callbacks for real-time chunk processing
        // 2. Streaming responses - it waits for the entire response before returning
        // 3. Progressive data handling needed for AI chat streaming
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_init -- Required for SSE streaming; WP HTTP API lacks callback support
        $ch = curl_init($url);

        if ($ch === false) {
            return 'Failed to initialize cURL';
        }

        // Reset buffer for this request
        $this->sse_buffer = '';

        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt_array -- cURL required for streaming
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => wp_json_encode($data),
            CURLOPT_HTTPHEADER     => array(
                'Authorization: Bearer ' . $this->api_key,
                'Content-Type: application/json',
                'Accept: text/event-stream',
                'Expect: ', // Disable Expect header that can cause buffering
            ),
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT        => 300,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_BUFFERSIZE     => 128, // Small buffer for immediate chunks
            // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt -- cURL required for streaming
            CURLOPT_WRITEFUNCTION  => function($ch, $chunk) use ($callback, &$context) {
                $this->parse_sse_chunk($chunk, $callback, $context);
                return strlen($chunk);
            },
        ));

        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_exec -- cURL required for streaming
        $result = curl_exec($ch);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_errno -- cURL required for streaming
        $error_no = curl_errno($ch);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_error -- cURL required for streaming
        $error_msg = curl_error($ch);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_getinfo -- cURL required for streaming
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_close -- cURL required for streaming
        curl_close($ch);

        if ($error_no !== 0) {
            return 'cURL error: ' . $error_msg;
        }

        if ($http_code >= 400) {
            return 'HTTP error: ' . $http_code;
        }

        return true;
    }

    /**
     * Parse SSE chunk and call callback for each event
     *
     * @param string   $chunk    Raw SSE chunk data
     * @param callable $callback Callback for each parsed event
     * @param array    $context  Context for tracking state (annotations, etc.)
     * @return void
     */
    private function parse_sse_chunk($chunk, $callback, &$context) {
        // Add chunk to buffer
        $this->sse_buffer .= $chunk;

        // Split by double newline (SSE event separator)
        $parts = explode("\n\n", $this->sse_buffer);

        // Keep the last part as it may be incomplete
        $this->sse_buffer = array_pop($parts);

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }

            // Parse SSE event
            $event_type = null;
            $event_data = null;

            $lines = explode("\n", $part);
            foreach ($lines as $line) {
                if (strpos($line, 'event: ') === 0) {
                    $event_type = substr($line, 7);
                } elseif (strpos($line, 'data: ') === 0) {
                    $event_data = substr($line, 6);
                }
            }

            // If no event type, check if data line exists on its own
            if ($event_data === null) {
                foreach ($lines as $line) {
                    if (strpos($line, 'data:') === 0) {
                        $event_data = trim(substr($line, 5));
                        break;
                    }
                }
            }

            if ($event_data === null || $event_data === '[DONE]') {
                continue;
            }

            $decoded = json_decode($event_data, true);
            if (!$decoded || !isset($decoded['type'])) {
                continue;
            }

            $type = $decoded['type'];

            // Handle text delta - the main streaming content
            if ($type === 'response.output_text.delta') {
                if (isset($decoded['delta'])) {
                    $callback(array(
                        'type'    => 'content',
                        'content' => $decoded['delta'],
                    ));
                }
            }
            // Handle file search in progress - show searching status
            elseif ($type === 'response.file_search_call.searching') {
                $callback(array(
                    'type'    => 'status',
                    'content' => 'Searching files...',
                ));
            }
            // Handle file search completed - extract sources
            elseif ($type === 'response.file_search_call.completed') {
                if (isset($decoded['results']) && is_array($decoded['results'])) {
                    $sources = array();
                    foreach ($decoded['results'] as $result) {
                        if (isset($result['filename'])) {
                            $sources[] = array(
                                'file_id'  => isset($result['file_id']) ? $result['file_id'] : '',
                                'filename' => $result['filename'],
                            );
                        }
                    }
                    if (!empty($sources)) {
                        $context['sources'] = $sources;
                    }
                }
            }
            // Handle text annotation added - for inline citations
            elseif ($type === 'response.output_text.annotation.added') {
                if (isset($decoded['annotation'])) {
                    $annotation = $decoded['annotation'];
                    if (isset($annotation['filename'])) {
                        if (!isset($context['annotations'])) {
                            $context['annotations'] = array();
                        }
                        $context['annotations'][] = array(
                            'file_id'  => isset($annotation['file_id']) ? $annotation['file_id'] : '',
                            'filename' => $annotation['filename'],
                        );
                    }
                }
            }
            // Handle errors
            elseif ($type === 'error') {
                $error_msg = isset($decoded['error']['message']) ? $decoded['error']['message'] : 'Unknown streaming error';
                $callback(array(
                    'type'    => 'error',
                    'content' => $error_msg,
                ));
            }
        }
    }

    /**
     * Stream response with file search using Responses API
     *
     * @param string $input User input/message
     * @param string $vector_store_id Vector store ID
     * @param callable $callback Callback function for each chunk
     * @param string $model Model to use
     * @param string $instructions Optional system instructions
     * @param array $options Additional options
     * @return void
     */
    public function stream_response_with_filesearch($input, $vector_store_id, $callback, $model = null, $instructions = '', $options = array()) {
        if (!$this->has_api_key()) {
            $callback(array('type' => 'error', 'content' => 'OpenAI API key is not configured'));
            return;
        }

        if (null === $model) {
            $model = $this->default_model;
        }

        $url = self::API_BASE_URL . 'responses';

        // Responses API with streaming enabled
        $data = array(
            'model'  => $model,
            'input'  => $input,
            'stream' => true,
            'tools'  => array(
                array(
                    'type'             => 'file_search',
                    'vector_store_ids' => array($vector_store_id),
                ),
            ),
        );

        // Add instructions if provided
        if (!empty($instructions)) {
            $data['instructions'] = $instructions;
        }

        // Merge any additional options
        $data = array_merge($data, $options);

        // Context to track sources/annotations across streaming events
        $context = array(
            'sources'     => array(),
            'annotations' => array(),
        );

        // Use cURL streaming
        $result = $this->make_streaming_request($url, $data, $callback, $context);

        // Handle streaming errors
        if ($result !== true) {
            $callback(array('type' => 'error', 'content' => $result));
            return;
        }

        // Send sources collected during streaming
        $sources = array();
        if (!empty($context['sources'])) {
            $sources = $context['sources'];
        } elseif (!empty($context['annotations'])) {
            // Fall back to annotations if no explicit sources
            $sources = $context['annotations'];
        }

        if (!empty($sources)) {
            // Deduplicate sources by filename
            $unique_sources = array();
            $seen = array();
            foreach ($sources as $source) {
                $key = $source['filename'];
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $unique_sources[] = $source;
                }
            }
            $callback(array('type' => 'sources', 'sources' => $unique_sources));
        }

        $callback(array('type' => 'done'));
    }
}
