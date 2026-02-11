<?php
/**
 * REST Stream Endpoint
 *
 * Provides a WordPress REST API endpoint for SSE streaming.
 * Replaces the direct file access approach with a proper REST endpoint.
 *
 * @package ChatProjects
 */

namespace ChatProjects;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST Stream Endpoint Class
 */
class REST_Stream_Endpoint {

	/**
	 * REST API namespace
	 *
	 * @var string
	 */
	const NAMESPACE = 'chatprojects/v1';

	/**
	 * Initialize the REST endpoint.
	 */
	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/stream',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_stream' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'project_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'message'    => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'thread_id'  => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'default'           => '',
					),
				),
			)
		);
	}

	/**
	 * Check if user has permission to use the stream endpoint.
	 *
	 * @param \WP_REST_Request $request REST request object.
	 * @return bool|\WP_Error True if has permission, WP_Error otherwise.
	 */
	public function check_permission( $request ) {
		// Check if user is logged in.
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'rest_not_logged_in',
				__( 'You must be logged in.', 'chatprojects' ),
				array( 'status' => 401 )
			);
		}

		// Verify nonce from header or body.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( empty( $nonce ) ) {
			$nonce = $request->get_param( 'nonce' );
		}

		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			// Also check chatpr_ajax_nonce for backwards compatibility.
			$legacy_nonce = $request->get_param( 'nonce' );
			if ( empty( $legacy_nonce ) || ! wp_verify_nonce( $legacy_nonce, 'chatpr_ajax_nonce' ) ) {
				return new \WP_Error(
					'rest_invalid_nonce',
					__( 'Invalid security token. Please refresh the page.', 'chatprojects' ),
					array( 'status' => 403 )
				);
			}
		}

		// Check project access.
		$project_id = $request->get_param( 'project_id' );
		if ( ! Access::can_access_project( $project_id ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Access denied.', 'chatprojects' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Handle the streaming request.
	 *
	 * @param \WP_REST_Request $request REST request object.
	 * @return void Outputs SSE stream directly.
	 */
	public function handle_stream( $request ) {
		global $wpdb;

		// Get parameters.
		$project_id = $request->get_param( 'project_id' );
		$message    = $request->get_param( 'message' );
		$thread_id  = $request->get_param( 'thread_id' );
		$user_id    = get_current_user_id();

		// Validate message.
		if ( empty( $message ) ) {
			$this->send_sse_error( __( 'Message is required.', 'chatprojects' ) );
			return;
		}

		// Disable output buffering for SSE.
		$this->setup_sse_environment();

		// Get or create chat.
		$chat_id = $this->get_or_create_chat( $thread_id, $project_id, $user_id );
		if ( is_wp_error( $chat_id ) ) {
			$this->send_sse_error( $chat_id->get_error_message() );
			return;
		}

		// Get project settings.
		$vector_store_id = get_post_meta( $project_id, '_cp_vector_store_id', true );
		if ( empty( $vector_store_id ) ) {
			$this->send_sse_error( __( 'No vector store configured for this project. Please upload files first.', 'chatprojects' ) );
			return;
		}

		$instructions = get_post_meta( $project_id, '_cp_instructions', true );
		if ( empty( $instructions ) ) {
			$instructions = get_option( 'chatprojects_assistant_instructions', '' );
		}

		$model = get_post_meta( $project_id, '_cp_model', true );
		if ( empty( $model ) ) {
			$model = get_option( 'chatprojects_default_model', 'gpt-4o' );
		}

		// Store user message.
		$messages_table = $wpdb->prefix . 'chatprojects_messages';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table.
		$wpdb->insert(
			$messages_table,
			array(
				'chat_id'    => $chat_id,
				'role'       => 'user',
				'content'    => $message,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s' )
		);

		// Stream the response.
		$assistant_content = '';
		$sources           = array();

		$api_handler = new API_Handler();
		$api_handler->stream_response_with_filesearch(
			$message,
			$vector_store_id,
			function ( $chunk ) use ( &$assistant_content, &$sources ) {
				if ( isset( $chunk['type'] ) && 'content' === $chunk['type'] ) {
					$assistant_content .= $chunk['content'];
				} elseif ( isset( $chunk['type'] ) && 'sources' === $chunk['type'] ) {
					$sources = $chunk['sources'];
				}

				// Output immediately (SSE format with JSON encoding).
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SSE data format, JSON encoded.
				echo 'data: ' . wp_json_encode( $chunk ) . "\n\n";

				if ( function_exists( 'litespeed_flush' ) ) {
					litespeed_flush();
				}
				@ob_flush();
				@flush();
			},
			$model,
			$instructions
		);

		// Store assistant message.
		if ( ! empty( $assistant_content ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table.
			$wpdb->insert(
				$messages_table,
				array(
					'chat_id'    => $chat_id,
					'role'       => 'assistant',
					'content'    => $assistant_content,
					'metadata'   => ! empty( $sources ) ? wp_json_encode( array( 'sources' => $sources ) ) : null,
					'created_at' => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%s', '%s', '%s' )
			);
		}

		// Update message count and generate title.
		$this->update_chat_metadata( $chat_id, $message, $assistant_content );

		// Send chat_id.
		$this->send_sse_data(
			array(
				'type'    => 'chat_id',
				'chat_id' => $chat_id,
			)
		);

		// Send done signal.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SSE done marker.
		echo "data: [DONE]\n\n";
		@flush();

		exit;
	}

	/**
	 * Setup SSE environment (headers, buffering, etc.)
	 */
	private function setup_sse_environment() {
		// Disable output buffering.
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		// Set SSE headers.
		header( 'Content-Type: text/event-stream; charset=utf-8' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate, private' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		header( 'X-Accel-Buffering: no' );
		header( 'Connection: keep-alive' );
		header( 'X-LiteSpeed-Cache-Control: no-cache, no-store, esi=off' );
		header( 'X-LiteSpeed-Tag: no-cache' );
		header( 'X-CF-Buffering: off' );

		// Configure PHP for streaming.
		if ( function_exists( 'ini_set' ) ) {
			// phpcs:disable Squiz.PHP.DiscouragedFunctions.Discouraged -- Required for SSE streaming.
			@ini_set( 'zlib.output_compression', '0' );
			@ini_set( 'implicit_flush', '1' );
			@ini_set( 'output_buffering', '0' );
			// phpcs:enable Squiz.PHP.DiscouragedFunctions.Discouraged
		}
		@ob_implicit_flush( true );

		// Send SSE padding to fill server buffer.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SSE comment with spaces only.
		echo ':' . str_repeat( ' ', 16384 ) . "\n\n";
		@flush();
	}

	/**
	 * Get existing chat or create a new one.
	 *
	 * @param string $thread_id  Thread/chat ID.
	 * @param int    $project_id Project ID.
	 * @param int    $user_id    User ID.
	 * @return int|\WP_Error Chat ID or error.
	 */
	private function get_or_create_chat( $thread_id, $project_id, $user_id ) {
		global $wpdb;

		$chat_id = null;

		if ( ! empty( $thread_id ) ) {
			$chats_table     = $wpdb->prefix . 'chatprojects_chats';
			$chats_table_sql = esc_sql( $chats_table );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
			$chat = $wpdb->get_row(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized.
					"SELECT * FROM `{$chats_table_sql}` WHERE id = %d AND project_id = %d AND user_id = %d",
					intval( $thread_id ),
					$project_id,
					$user_id
				)
			);
			if ( $chat ) {
				$chat_id = $chat->id;
			}
		}

		if ( empty( $chat_id ) ) {
			$chat_interface = new Chat_Interface();
			$chat_id        = $chat_interface->create_chat( $project_id );
			if ( is_wp_error( $chat_id ) ) {
				return $chat_id;
			}
		}

		return $chat_id;
	}

	/**
	 * Update chat metadata (message count, title).
	 *
	 * @param int    $chat_id           Chat ID.
	 * @param string $user_message      User's message.
	 * @param string $assistant_message Assistant's response.
	 */
	private function update_chat_metadata( $chat_id, $user_message, $assistant_message ) {
		global $wpdb;

		$chats_table     = $wpdb->prefix . 'chatprojects_chats';
		$chats_table_sql = esc_sql( $chats_table );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$chat = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized.
				"SELECT * FROM `{$chats_table_sql}` WHERE id = %d",
				$chat_id
			)
		);

		if ( $chat ) {
			$new_count = $chat->message_count + 2;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
			$wpdb->update(
				$chats_table,
				array(
					'message_count' => $new_count,
					'updated_at'    => current_time( 'mysql' ),
				),
				array( 'id' => $chat_id ),
				array( '%d', '%s' ),
				array( '%d' )
			);

			// Auto-generate title after first message exchange.
			if ( 2 === $new_count && ( empty( $chat->title ) || 0 === strpos( $chat->title, 'Chat ' ) ) ) {
				$generated_title = $this->generate_chat_title( $user_message, $assistant_message );
				if ( ! empty( $generated_title ) ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
					$wpdb->update(
						$chats_table,
						array( 'title' => sanitize_text_field( $generated_title ) ),
						array( 'id' => $chat_id ),
						array( '%s' ),
						array( '%d' )
					);
					// Notify frontend of title change.
					$this->send_sse_data(
						array(
							'type'    => 'title_update',
							'chat_id' => $chat_id,
							'title'   => $generated_title,
						)
					);
				}
			}
		}
	}

	/**
	 * Generate a chat title from conversation using OpenAI.
	 *
	 * @param string $user_msg      User's message.
	 * @param string $assistant_msg Assistant's response.
	 * @return string Generated title or empty string on failure.
	 */
	private function generate_chat_title( $user_msg, $assistant_msg ) {
		try {
			$api = new API_Handler();

			$prompt = "Based on this conversation, generate a short, concise title (3-6 words maximum):\n\nUser: {$user_msg}\n\nAssistant: {$assistant_msg}\n\nRespond with ONLY the title, nothing else.";

			$messages = array(
				array(
					'role'    => 'user',
					'content' => $prompt,
				),
			);

			$title_response = $api->create_chat_completion( $messages, 'gpt-4o-mini' );

			if ( is_wp_error( $title_response ) ) {
				return $this->fallback_title( $user_msg );
			}

			$title = isset( $title_response['choices'][0]['message']['content'] )
				? $title_response['choices'][0]['message']['content']
				: '';

			// Clean up the title.
			$title = trim( $title, "\"'\n\r\t " );
			$title = preg_replace( '/^(Title:|title:)\s*/i', '', $title );

			// Ensure reasonable length.
			if ( strlen( $title ) > 100 ) {
				$title = substr( $title, 0, 97 ) . '...';
			}

			return $title;
		} catch ( \Exception $e ) {
			return $this->fallback_title( $user_msg );
		}
	}

	/**
	 * Generate a fallback title from user message.
	 *
	 * @param string $user_msg User's message.
	 * @return string Fallback title.
	 */
	private function fallback_title( $user_msg ) {
		$words = explode( ' ', $user_msg );
		$title = implode( ' ', array_slice( $words, 0, 5 ) );
		if ( count( $words ) > 5 ) {
			$title .= '...';
		}
		return $title;
	}

	/**
	 * Send SSE error message and exit.
	 *
	 * @param string $message Error message to send.
	 */
	private function send_sse_error( $message ) {
		// Ensure SSE headers are set.
		if ( ! headers_sent() ) {
			$this->setup_sse_environment();
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SSE data format, JSON encoded.
		echo 'data: ' . wp_json_encode(
			array(
				'type'    => 'error',
				'content' => $message,
			)
		) . "\n\n";
		@flush();
		exit;
	}

	/**
	 * Send SSE data chunk.
	 *
	 * @param array $data Data to send.
	 */
	private function send_sse_data( $data ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SSE data format, JSON encoded.
		echo 'data: ' . wp_json_encode( $data ) . "\n\n";
		@flush();
	}
}
