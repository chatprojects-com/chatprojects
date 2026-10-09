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
	 * Switch the current request into a Server-Sent Events response.
	 *
	 * Clears output buffers, sends no-cache / no-buffering headers for common
	 * servers and proxies, and pads the first write so buffering proxies flush.
	 * Call only after authentication, so failures can still return JSON.
	 */
	public static function begin_response() {
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		header( 'Content-Type: text/event-stream; charset=utf-8' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate, private' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		header( 'X-Accel-Buffering: no' ); // nginx / LiteSpeed.
		header( 'Connection: keep-alive' );
		header( 'X-LiteSpeed-Cache-Control: no-cache, no-store, esi=off' );
		header( 'X-LiteSpeed-Tag: no-cache' );
		header( 'X-CF-Buffering: off' ); // Cloudflare.

		// Keep going if the visitor closes the tab, so the reply is still saved
		// and the chat history stays consistent.
		ignore_user_abort( true );

		// Compression buffers the whole response; turn it off for this request only.
		if ( function_exists( 'apache_setenv' ) ) {
			apache_setenv( 'no-gzip', '1' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_apache_setenv -- Disables mod_deflate for this SSE response only.
		}
		if ( ! headers_sent() && ini_get( 'zlib.output_compression' ) ) {
			ini_set( 'zlib.output_compression', '0' ); // phpcs:ignore WordPress.PHP.IniSet.Risky, Squiz.PHP.DiscouragedFunctions.Discouraged -- Required for SSE; affects this request only.
		}
		ob_implicit_flush( true );

		// Padding fills server buffers (often 8KB) so streaming starts immediately.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SSE comment of spaces only.
		echo ':' . str_repeat( ' ', 8192 ) . "\n\n";
		echo ": stream started\n\n";
		flush();
	}

	/**
	 * Text appended to a reply the provider stopped early, so the user (and the
	 * saved history) can tell it is incomplete.
	 *
	 * @param string $reason Provider stop reason (max_tokens, length, content_filter, safety, ...).
	 * @return string
	 */
	public static function truncation_notice( $reason = '' ) {
		$reason = strtolower( (string) $reason );
		if ( in_array( $reason, array( 'content_filter', 'safety', 'refusal', 'blocklist', 'prohibited_content', 'spii', 'recitation' ), true ) ) {
			$text = __( 'The AI provider stopped this reply because of its content policy.', 'chatprojects' );
		} else {
			$text = __( 'This reply was cut off because it reached the maximum length.', 'chatprojects' );
		}
		return "\n\n_(" . $text . ')_';
	}

	/**
	 * Push everything written so far to the browser.
	 */
	public static function flush() {
		if ( function_exists( 'litespeed_flush' ) ) {
			litespeed_flush();
		}
		if ( ob_get_level() > 0 ) {
			ob_flush();
		}
		flush();
	}

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

		$callback   = $this->context['callback'];
		$parser     = $this->context['parser'];
		$buffer     = &$this->context['buffer'];
		$state      = &$this->context['state'];
		$error_body = &$this->context['error_body'];

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
			function ( $ch, $chunk ) use ( $callback, $parser, &$buffer, &$state, &$error_body ) {
				// Error responses are plain JSON, not SSE: keep the body so the
				// caller can show the provider's actual error message.
				if ( (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE ) >= 400 ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_getinfo -- Inside the http_api_curl write callback.
					if ( strlen( $error_body ) < 65536 ) {
						$error_body .= $chunk;
					}
					return strlen( $chunk );
				}

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
			'url'        => $url,
			'callback'   => $callback,
			'parser'     => $parser,
			'buffer'     => '',
			'state'      => $state,
			'error_body' => '',
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

		// With CURLOPT_RETURNTRANSFER off the body is empty; put a captured
		// error body back so callers can read it.
		if ( ! is_wp_error( $response ) && '' !== $this->context['error_body'] ) {
			$response['body'] = $this->context['error_body'];
		}

		// Clean up after request completes.
		$this->unregister_hooks();
		$this->context = null;

		return $response;
	}

	/**
	 * Build a readable message from a failed (status >= 400) streaming response.
	 *
	 * Understands the JSON error shapes used by OpenAI, Anthropic, Gemini and
	 * OpenAI-compatible APIs ({"error":{"message":...}}, Gemini's list form, or
	 * a plain {"message":...}).
	 *
	 * @param array $response WordPress HTTP API response.
	 * @return string
	 */
	public static function error_message( $response ) {
		$status  = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( is_array( $decoded ) && isset( $decoded[0] ) && is_array( $decoded[0] ) ) {
			$decoded = $decoded[0];
		}

		$message = '';
		if ( is_array( $decoded ) ) {
			if ( isset( $decoded['error']['message'] ) ) {
				$message = (string) $decoded['error']['message'];
			} elseif ( isset( $decoded['error'] ) && is_string( $decoded['error'] ) ) {
				$message = $decoded['error'];
			} elseif ( isset( $decoded['message'] ) ) {
				$message = (string) $decoded['message'];
			} elseif ( isset( $decoded['detail'] ) && is_string( $decoded['detail'] ) ) {
				$message = $decoded['detail'];
			}
		}

		if ( '' === $message ) {
			/* translators: %d: HTTP status code */
			return sprintf( __( 'The AI provider returned an error (HTTP %d).', 'chatprojects' ), $status );
		}

		/* translators: 1: HTTP status code, 2: error message from the AI provider */
		return sprintf( __( 'AI provider error (HTTP %1$d): %2$s', 'chatprojects' ), $status, wp_strip_all_tags( $message ) );
	}

	/**
	 * Machine-readable error code from a failed streaming response, if any.
	 *
	 * @param array $response WordPress HTTP API response.
	 * @return string
	 */
	public static function error_code( $response ) {
		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( is_array( $decoded ) && isset( $decoded['error']['code'] ) && is_string( $decoded['error']['code'] ) ) {
			return $decoded['error']['code'];
		}
		return '';
	}
}
