<?php
/**
 * AI Provider Interface
 *
 * Defines the contract for all AI provider implementations
 * Updated for Responses API - no more thread-based methods
 *
 * @package ChatProjects
 */

namespace ChatProjects\Providers;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI Provider Interface
 */
interface AI_Provider_Interface {
    /**
     * Run completion and get response
     *
     * @param array  $messages Array of message objects with 'role' and 'content'
     * @param string $model    Model identifier
     * @param array  $options  Additional options (instructions, temperature, etc.)
     * @return array|WP_Error Response with 'content' key or error
     */
    public function run_completion($messages, $model, $options = array());

    /**
     * Stream completion with callback
     *
     * @param array    $messages Array of message objects
     * @param string   $model    Model identifier
     * @param callable $callback Callback for each chunk: function(array $chunk)
     * @param array    $options  Additional options
     * @return void
     */
    public function stream_completion($messages, $model, $callback, $options = array());

    /**
     * Get available models for this provider
     *
     * @return array Array of model identifiers => display names
     */
    public function get_available_models();

    /**
     * Validate API key
     *
     * @param string $api_key API key to validate
     * @return bool|WP_Error True if valid, error otherwise
     */
    public function validate_api_key($api_key);

    /**
     * Get provider display name
     *
     * @return string Provider name (e.g., "OpenAI", "Google Gemini")
     */
    public function get_name();

    /**
     * Get provider identifier
     *
     * @return string Provider identifier (openai, gemini, anthropic, chutes)
     */
    public function get_identifier();

    /**
     * Check if provider has a valid API key configured
     *
     * @return bool True if API key is set
     */
    public function has_api_key();
}
