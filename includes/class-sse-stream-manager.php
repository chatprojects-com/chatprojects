<?php
/**
 * SSE Stream Manager
 *
 * Provides WordPress HTTP API compliant SSE streaming by using the
 * http_api_curl action hook to inject CURLOPT_WRITEFUNCTION callbacks.
 * This satisfies WordPress plugin directory requirements while maintaining
 * full streaming functionality.
 *
 * @package ChatProjects
 */

namespace ChatProjects;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SSE Stream Manager Class
 *
 * Singleton class that manages SSE streaming context for WordPress HTTP API.
 * Uses the http_api_curl hook to configure cURL for streaming callbacks.
 */
class SSE_Stream_Manager {

	/**
	 * Singleton instance.
	 *
	 * @var SSE_Stream_Manager|null
	 */
	private static $instance = null;

	/**
	 * Current streaming context.
	 *
	 * @var array|null
	 */
	private $context = null;

	/**
	 * Whether hooks are registered.
	 *
	 * @var bool
	 */
	private $hooks_registered = false;

	/**
	 * Get singleton instance.
	 *
	 * @return SSE_Stream_Manager
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor for singleton.
	 */
	private function __construct() {}

	/**
	 * Register the http_api_curl hook.
	 */
	private function register_hooks() {
		if ( ! $this->hooks_registered ) {
			add_action( 'http_api_curl', array( $this, 'configure_curl_for_streaming' ), 10, 3 );
			$this->hooks_registered = true;
		}
	}

	/**
	 * Unregister the http_api_curl hook.
	 */
	private function unregister_hooks() {
		if ( $this->hooks_registered ) {
			remove_action( 'http_api_curl', array( $this, 'configure_curl_for_streaming' ), 10 );
			$this->hooks_registered = false;
		}
	}

	/**
	 * Configure cURL handle for streaming.
	 *
	 * This is the http_api_curl action callback. WordPress calls this hook
	 * just before executing a cURL request, allowing us to add streaming options.
	 *
	 * @param resource $handle      cURL handle.
	 * @param array    $parsed_args Request arguments.
	 * @param string   $url         Request URL.
	 */
	public function configure_curl_for_streaming( $handle, $parsed_args, $url ) {
		// Only modify if we have an active streaming context for this URL.
		if ( null === $this->context || $this->context['url'] !== $url ) {
			return;
		}

		$callback = $this->context['callback'];
		$parser   = $this->context['parser'];
		$buffer   = &$this->context['buffer'];
		$state    = &$this->context['state'];

		// Set streaming-specific cURL options via http_api_curl hook.
		// This is the WordPress-approved method for customizing cURL behavior.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt -- Intentional use via http_api_curl hook.
		curl_setopt( $handle, CURLOPT_RETURNTRANSFER, false );

		// Small buffer size for low-latency streaming.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt -- Intentional use via http_api_curl hook.
		curl_setopt( $handle, CURLOPT_BUFFERSIZE, 128 );

		// The write function callback processes each chunk as it arrives.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt -- Intentional use via http_api_curl hook.
		curl_setopt(
			$handle,
			CURLOPT_WRITEFUNCTION,
			function ( $ch, $chunk ) use ( $callback, $parser, &$buffer, &$state ) {
				// Use the provider-specific parser to process chunks.
				$parser( $chunk, $callback, $buffer, $state );
				return strlen( $chunk );
			}
		);
	}

	/**
	 * Execute a streaming request using WordPress HTTP API.
	 *
	 * @param string   $url      API URL.
	 * @param array    $data     Request body data (will be JSON encoded).
	 * @param array    $headers  HTTP headers (associative array).
	 * @param callable $callback Callback for each parsed event.
	 * @param callable $parser   SSE parser function with signature: function($chunk, $callback, &$buffer, &$state).
	 * @param array    $state    Optional state to pass to parser (can be modified by parser).
	 * @return array|WP_Error WordPress HTTP API response or error.
	 */
	public function stream_request( $url, $data, $headers, $callback, $parser, $state = array() ) {
		// Set up the streaming context.
		$this->context = array(
			'url'      => $url,
			'callback' => $callback,
			'parser'   => $parser,
			'buffer'   => '',
			'state'    => $state,
		);

		// Register hooks before the request.
		$this->register_hooks();

		// Make the request using WordPress HTTP API.
		// The http_api_curl hook will be triggered, configuring streaming.
		$response = wp_remote_post(
			$url,
			array(
				'headers' => $headers,
				'body'    => wp_json_encode( $data ),
				'timeout' => 300,
			)
		);

		// Clean up after request completes.
		$this->unregister_hooks();
		$this->context = null;

		return $response;
	}

	/**
	 * Get reference to current buffer (for post-processing if needed).
	 *
	 * @return string|null Current buffer contents or null if no active context.
	 */
	public function get_buffer() {
		return $this->context ? $this->context['buffer'] : null;
	}

	/**
	 * Get reference to current state (for post-processing if needed).
	 *
	 * @return array|null Current state or null if no active context.
	 */
	public function get_state() {
		return $this->context ? $this->context['state'] : null;
	}
}
