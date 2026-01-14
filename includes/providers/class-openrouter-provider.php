<?php
/**
 * OpenRouter Provider
 *
 * Handles OpenRouter API interactions (OpenAI-compatible API)
 *
 * @package ChatProjects
 */

namespace ChatProjects\Providers;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * OpenRouter Provider Class
 */
class OpenRouter_Provider extends Base_Provider {
    /**
     * API base URL
     */
    const API_BASE_URL = 'https://openrouter.ai/api/v1/';

    /**
     * Constructor
     */
    public function __construct() {
        $this->name = 'OpenRouter';
        $this->identifier = 'openrouter';
        $this->api_base_url = self::API_BASE_URL;
        $this->models = array(
            'default' => 'OpenRouter Default Model',
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
            return $this->error('no_api_key', __('OpenRouter API key is not configured.', 'chatprojects'));
        }

        if (empty($messages)) {
            return $this->error('no_messages', __('No messages provided.', 'chatprojects'));
        }

        // Format messages
        $formatted_messages = array();
        foreach ($messages as $msg) {
            $role = isset($msg['role']) ? $msg['role'] : 'user';
            $content = isset($msg['content']) ? $msg['content'] : '';

            $formatted_messages[] = array(
                'role' => $role,
                'content' => $content,
            );
        }

        // Prepare request data
        $data = array(
            'model' => $model,
            'messages' => $formatted_messages,
            'temperature' => isset($options['temperature']) ? $options['temperature'] : 0.7,
            'max_tokens' => isset($options['max_tokens']) ? $options['max_tokens'] : 2000,
        );

        // Add system message if provided
        if (!empty($options['instructions'])) {
            // Prepend system message
            array_unshift($data['messages'], array(
                'role' => 'system',
                'content' => $options['instructions'],
            ));
        }

        $headers = array(
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json',
            'HTTP-Referer' => home_url(),
            'X-Title' => get_bloginfo('name'),
        );

        $response = $this->make_request(
            self::API_BASE_URL . 'chat/completions',
            $data,
            'POST',
            $headers
        );

        if (is_wp_error($response)) {
            return $response;
        }

        // Extract assistant's message (OpenAI-compatible format)
        if (isset($response['choices'][0]['message']['content'])) {
            $content = $response['choices'][0]['message']['content'];

            return array(
                'content' => $content,
                'model' => $model,
            );
        } elseif (isset($response['response'])) {
            // Alternative response structure
            return array(
                'content' => $response['response'],
                'model' => $model,
            );
        }

        return $this->error('no_response', __('No response from OpenRouter.', 'chatprojects'));
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

        return $this->error('invalid_api_key', __('Invalid OpenRouter API key.', 'chatprojects'));
    }

    /**
     * Get available models from OpenRouter
     *
     * @return array|WP_Error
     */
    public function fetch_available_models() {
        if (!$this->has_api_key()) {
            return $this->error('no_api_key', __('API key not configured.', 'chatprojects'));
        }

        $headers = array(
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json',
        );

        $response = wp_remote_get(
            self::API_BASE_URL . 'models',
            array(
                'headers' => $headers,
                'timeout' => 15,
            )
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        $model_list = $data['data'] ?? $data['models'] ?? null;

        if (isset($model_list) && is_array($model_list)) {
            $models = array();
            foreach ($model_list as $model) {
                $id = $model['id'] ?? $model['name'] ?? '';
                $name = $model['name'] ?? $model['id'] ?? '';
                if ($id && $name) {
                    $models[$id] = $name;
                }
            }
            if (!empty($models)) {
                // Sort models alphabetically by name
                asort($models);
                $this->models = $models;
                return $models;
            }
        }

        return $this->models;
    }
}
