<?php
/**
 * Widget Handler Class
 *
 * Handles public-facing chat widget endpoints. These endpoints work WITHOUT
 * WordPress login, using session token authentication and IP-based rate limiting.
 *
 * Security layers:
 * - Session token authentication (32-byte hex token per visitor)
 * - IP-based rate limiting (configurable limits)
 * - Referrer header validation
 * - Content sanitization and length limits
 * - API keys never exposed to the frontend
 * - Separate tables from main user data
 *
 * @package ChatProjects
 */

namespace ChatProjects;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Widget Handler Class
 */
class Widget_Handler {

	/**
	 * Maximum message length in characters.
	 *
	 * @var int
	 */
	const MAX_MESSAGE_LENGTH = 4000;

	/**
	 * Default session duration in hours.
	 *
	 * @var int
	 */
	const DEFAULT_SESSION_HOURS = 24;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->register_hooks();
	}

	/**
	 * Register AJAX hooks.
	 *
	 * Both wp_ajax_ and wp_ajax_nopriv_ are needed so the widget works
	 * for both logged-in and logged-out users.
	 */
	private function register_hooks() {
		// Public endpoints (logged-out visitors).
		add_action( 'wp_ajax_nopriv_chatpr_widget_init', array( $this, 'handle_init' ) );
		add_action( 'wp_ajax_nopriv_chatpr_widget_message', array( $this, 'handle_message' ) );
		add_action( 'wp_ajax_nopriv_chatpr_widget_history', array( $this, 'handle_history' ) );

		// Same endpoints for logged-in users visiting the frontend.
		add_action( 'wp_ajax_chatpr_widget_init', array( $this, 'handle_init' ) );
		add_action( 'wp_ajax_chatpr_widget_message', array( $this, 'handle_message' ) );
		add_action( 'wp_ajax_chatpr_widget_history', array( $this, 'handle_history' ) );

		// Session cleanup cron.
		add_action( 'chatprojects_cleanup_widget_sessions', array( $this, 'cleanup_expired_sessions' ) );

		// Schedule daily cleanup if not already scheduled.
		if ( ! wp_next_scheduled( 'chatprojects_cleanup_widget_sessions' ) ) {
			wp_schedule_event( time(), 'daily', 'chatprojects_cleanup_widget_sessions' );
		}
	}

	/**
	 * Initialize a widget session.
	 *
	 * Creates a new session token for the visitor and returns widget configuration.
	 */
	public function handle_init() {
		// Validate referrer.
		if ( ! $this->validate_referrer() ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request origin.', 'chatprojects' ) ) );
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Public endpoint, no nonce; uses session token auth
		$explicit_project_id = isset( $_POST['project_id'] ) ? absint( wp_unslash( $_POST['project_id'] ) ) : 0;
		$existing_token      = isset( $_POST['session_token'] ) ? sanitize_text_field( wp_unslash( $_POST['session_token'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		// Resolve the project: explicit (shortcode) or the global widget project.
		$project_id = ! empty( $explicit_project_id ) ? $explicit_project_id : absint( get_option( 'chatprojects_widget_project_id', 0 ) );

		if ( empty( $explicit_project_id ) && ! get_option( 'chatprojects_widget_enabled', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Chat widget is not enabled.', 'chatprojects' ) ) );
			return;
		}

		// The project must be explicitly exposed to the public widget.
		if ( ! $this->validate_project( $project_id ) ) {
			wp_send_json_error( array( 'message' => __( 'This project is not available for public chat.', 'chatprojects' ) ) );
			return;
		}

		$ip = Security::get_client_ip();

		// Check for existing valid session token first (no rate limit needed for restoring sessions).
		if ( ! empty( $existing_token ) ) {
			$session = $this->validate_session( $existing_token );
			if ( $session ) {
				wp_send_json_success( array(
					'session_token' => $session->session_token,
					'config'        => $this->get_public_config(),
				) );
				return;
			}
		}

		// Rate limit only applies to NEW session creation.
		$rate_check = Rate_Limiter::check_widget_session( $ip );
		if ( is_wp_error( $rate_check ) ) {
			wp_send_json_error( array( 'message' => $rate_check->get_error_message() ) );
			return;
		}

		// Create new session bound to the validated project ID.
		$session_token = $this->create_session( $ip, $project_id );

		if ( is_wp_error( $session_token ) ) {
			wp_send_json_error( array( 'message' => $session_token->get_error_message() ) );
			return;
		}

		wp_send_json_success( array(
			'session_token' => $session_token,
			'config'        => $this->get_public_config(),
		) );
	}

	/**
	 * Handle an incoming widget chat message.
	 *
	 * Streams the AI response via SSE.
	 */
	public function handle_message() {
		// Validate everything BEFORE any output so failures are plain JSON errors.
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Public endpoint; uses session token auth instead of nonce
		$session_token = isset( $_POST['session_token'] ) ? sanitize_text_field( wp_unslash( $_POST['session_token'] ) ) : '';
		$message       = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! $this->validate_referrer( true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request origin.', 'chatprojects' ) ), 403 );
		}

		$session = $this->validate_session( $session_token );
		if ( ! $session ) {
			wp_send_json_error( array( 'message' => __( 'Invalid or expired session. Please refresh the page.', 'chatprojects' ) ), 403 );
		}

		if ( empty( $message ) ) {
			wp_send_json_error( array( 'message' => __( 'Message is required.', 'chatprojects' ) ), 400 );
		}

		if ( mb_strlen( $message ) > self::MAX_MESSAGE_LENGTH ) {
			wp_send_json_error( array( 'message' => __( 'Message is too long.', 'chatprojects' ) ), 400 );
		}

		$ip         = Security::get_client_ip();
		$rate_check = Rate_Limiter::check_widget_message( $session_token, $ip );
		if ( is_wp_error( $rate_check ) ) {
			wp_send_json_error( array( 'message' => $rate_check->get_error_message() ), 429 );
		}

		// Project comes from the session row only (bound at init); re-check it is still public.
		$project_id = absint( $session->project_id );
		if ( empty( $project_id ) || ! self::is_project_public( $project_id ) ) {
			wp_send_json_error( array( 'message' => __( 'This project is not available for public chat.', 'chatprojects' ) ), 403 );
		}

		$vector_store_id = get_post_meta( $project_id, '_cp_vector_store_id', true );
		if ( empty( $vector_store_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Project vector store not found.', 'chatprojects' ) ), 500 );
		}

		// Disable output buffering for SSE.
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
		header( 'X-CF-Buffering: off' );

		if ( function_exists( 'apache_setenv' ) ) {
			@apache_setenv( 'no-gzip', '1' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Required for SSE
		}
		if ( function_exists( 'ini_set' ) ) {
			@ini_set( 'zlib.output_compression', '0' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, Squiz.PHP.DiscouragedFunctions.Discouraged -- Required for SSE
			@ini_set( 'implicit_flush', '1' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, Squiz.PHP.DiscouragedFunctions.Discouraged -- Required for SSE
			@ini_set( 'output_buffering', '0' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, Squiz.PHP.DiscouragedFunctions.Discouraged -- Required for SSE
		}
		@ob_implicit_flush( true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Required for SSE

		// SSE padding to fill server buffer.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SSE padding with safe characters only
		echo ':' . str_repeat( ' ', 8192 ) . "\n\n";
		flush();


		// Build system instructions that constrain the AI to site content.
		$site_name    = get_bloginfo( 'name' );
		$instructions = get_post_meta( $project_id, '_cp_instructions', true );
		if ( empty( $instructions ) ) {
			$instructions = sprintf(
				/* translators: %s: Website name */
				__( 'You are a helpful assistant for %s. Answer questions based only on the provided content. If a question is unrelated to the content available, politely let the user know you can only help with questions about this website.', 'chatprojects' ),
				$site_name
			);
		}

		// Store user message.
		$this->store_widget_message( $session->id, 'user', $message );

		// Update session message count.
		$this->update_session_activity( $session->id );

		// Get previous response ID for conversation continuity.
		$previous_response_id = $this->get_previous_response_id( $session->id );

		// Stream response.
		$api             = new API_Handler();
		$model           = Model_Registry::resolve( 'openai', get_post_meta( $project_id, '_cp_model', true ), get_option( 'chatprojects_default_model' ) );
		$assistant_content = '';
		$response_id     = null;

		$callback = function ( $event ) use ( &$assistant_content, &$response_id ) {
			if ( ! is_array( $event ) || ! isset( $event['type'] ) ) {
				return;
			}

			switch ( $event['type'] ) {
				case 'content':
					$assistant_content .= $event['content'];
					$this->send_widget_sse( 'content', $event['content'] );
					break;

				case 'sources':
					$this->send_widget_sse( 'sources', '', $event['sources'] );
					break;

				case 'done':
					// Response ID captured separately.
					break;

				case 'error':
					$this->send_widget_sse( 'error', $event['content'] );
					break;
			}
		};

		$response_id = $api->stream_response_with_filesearch(
			$message,
			$vector_store_id,
			$callback,
			$model,
			$instructions,
			array(),
			$previous_response_id
		);

		// Store assistant response.
		if ( ! empty( $assistant_content ) ) {
			$metadata = array();
			if ( ! empty( $response_id ) ) {
				$metadata['response_id'] = $response_id;
			}
			$this->store_widget_message( $session->id, 'assistant', $assistant_content, $metadata );
		}

		// Send done event.
		$this->send_widget_sse( 'done', '' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SSE protocol terminator
		echo "data: [DONE]\n\n";
		flush();
		exit;
	}

	/**
	 * Return conversation history for a widget session.
	 */
	public function handle_history() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Public endpoint; uses session token auth
		$session_token = isset( $_POST['session_token'] ) ? sanitize_text_field( wp_unslash( $_POST['session_token'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$session = $this->validate_session( $session_token );
		if ( ! $session ) {
			wp_send_json_error( array( 'message' => __( 'Invalid or expired session.', 'chatprojects' ) ) );
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'chatprojects_widget_messages';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
		$messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT role, content, created_at FROM {$table} WHERE session_id = %d ORDER BY id ASC",
				$session->id
			)
		);

		$formatted = array();
		foreach ( $messages as $msg ) {
			$formatted[] = array(
				'role'    => $msg->role,
				'content' => $msg->content,
			);
		}

		wp_send_json_success( array( 'messages' => $formatted ) );
	}

	/**
	 * Clean up expired widget sessions and their messages.
	 */
	public function cleanup_expired_sessions() {
		global $wpdb;

		$sessions_table = $wpdb->prefix . 'chatprojects_widget_sessions';
		$messages_table = $wpdb->prefix . 'chatprojects_widget_messages';
		$now            = current_time( 'mysql' );

		// Get expired session IDs.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup query
		$expired_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$sessions_table} WHERE expires_at < %s",
				$now
			)
		);

		if ( ! empty( $expired_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $expired_ids ), '%d' ) );

			// Delete messages for expired sessions.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic placeholder count
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$messages_table} WHERE session_id IN ({$placeholders})",
					...$expired_ids
				)
			);

			// Delete expired sessions.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic placeholder count
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$sessions_table} WHERE id IN ({$placeholders})",
					...$expired_ids
				)
			);
		}
	}

	// ==================== SESSION MANAGEMENT ====================

	/**
	 * Create a new widget session.
	 *
	 * @param string $ip         Client IP address.
	 * @param int    $project_id Project ID (0 = use global default).
	 * @return string|\WP_Error Session token on success, error on failure.
	 */
	private function create_session( $ip, $project_id = 0 ) {
		global $wpdb;

		$table          = $wpdb->prefix . 'chatprojects_widget_sessions';
		$session_token  = bin2hex( random_bytes( 32 ) );
		$project_id     = absint( $project_id );
		$session_hours  = absint( get_option( 'chatprojects_widget_session_duration', self::DEFAULT_SESSION_HOURS ) );
		$now            = current_time( 'mysql' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public endpoint
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$user_agent = mb_substr( $user_agent, 0, 255 );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert
		$inserted = $wpdb->insert(
			$table,
			array(
				'session_token' => $session_token,
				'project_id'    => $project_id > 0 ? $project_id : null,
				'ip_address'    => $ip,
				'user_agent'    => $user_agent,
				'message_count' => 0,
				'created_at'    => $now,
				'expires_at'    => gmdate( 'Y-m-d H:i:s', time() + ( $session_hours * HOUR_IN_SECONDS ) ),
			),
			array( '%s', '%d', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new \WP_Error( 'session_failed', __( 'Failed to create chat session.', 'chatprojects' ) );
		}

		return $session_token;
	}

	/**
	 * Validate a session token.
	 *
	 * @param string $token Session token.
	 * @return object|false Session row object or false if invalid/expired.
	 */
	private function validate_session( $token ) {
		global $wpdb;

		if ( empty( $token ) || strlen( $token ) !== 64 ) {
			return false;
		}

		$table = $wpdb->prefix . 'chatprojects_widget_sessions';
		$now   = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Session validation must be fresh
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE session_token = %s AND expires_at > %s",
				$token,
				$now
			)
		);
	}

	// ==================== MESSAGE STORAGE ====================

	/**
	 * Store a widget chat message.
	 *
	 * @param int    $session_id Session ID.
	 * @param string $role       Message role (user or assistant).
	 * @param string $content    Message content.
	 * @param array  $metadata   Optional metadata (response_id, sources, etc.).
	 */
	private function store_widget_message( $session_id, $role, $content, $metadata = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'chatprojects_widget_messages';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert
		$wpdb->insert(
			$table,
			array(
				'session_id' => absint( $session_id ),
				'role'       => sanitize_key( $role ),
				'content'    => $content,
				'metadata'   => ! empty( $metadata ) ? wp_json_encode( $metadata ) : null,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Update session activity timestamp and message count.
	 *
	 * @param int $session_id Session ID.
	 */
	private function update_session_activity( $session_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'chatprojects_widget_sessions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Session update
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET message_count = message_count + 1, last_message_at = %s WHERE id = %d",
				current_time( 'mysql' ),
				absint( $session_id )
			)
		);
	}

	/**
	 * Get the previous response ID for conversation continuity.
	 *
	 * @param int $session_id Session ID.
	 * @return string|null Previous response ID or null.
	 */
	private function get_previous_response_id( $session_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'chatprojects_widget_messages';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup
		$metadata = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT metadata FROM {$table} WHERE session_id = %d AND role = 'assistant' ORDER BY id DESC LIMIT 1",
				absint( $session_id )
			)
		);

		if ( ! empty( $metadata ) ) {
			$decoded = json_decode( $metadata, true );
			if ( isset( $decoded['response_id'] ) ) {
				return $decoded['response_id'];
			}
		}

		return null;
	}

	// ==================== VALIDATION ====================

	/**
	 * Validate that a project ID is a valid, published project with a vector store.
	 *
	 * @param int $project_id Project ID to validate.
	 * @return bool True if valid, false otherwise.
	 */
	private function validate_project( $project_id ) {
		return self::is_project_public( $project_id );
	}

	/**
	 * Whether a project may be queried by anonymous widget visitors.
	 *
	 * A project is public when the owner ticked "Allow public chat widget" on it,
	 * or when it is the globally configured widget project and the widget is enabled.
	 * Anything else is private and must never be reachable without login.
	 *
	 * @param int $project_id Project ID.
	 * @return bool
	 */
	public static function is_project_public( $project_id ) {
		$project_id = absint( $project_id );
		if ( empty( $project_id ) ) {
			return false;
		}

		$post = get_post( $project_id );
		if ( ! $post || 'chatpr_project' !== $post->post_type || 'publish' !== $post->post_status ) {
			return false;
		}

		if ( empty( get_post_meta( $project_id, '_cp_vector_store_id', true ) ) ) {
			return false;
		}

		$opted_in = '1' === (string) get_post_meta( $project_id, '_cp_widget_enabled', true );
		$is_global = get_option( 'chatprojects_widget_enabled', false )
			&& $project_id === absint( get_option( 'chatprojects_widget_project_id', 0 ) );

		/**
		 * Filter whether a project is exposed to the public chat widget.
		 *
		 * @param bool $public     Default decision.
		 * @param int  $project_id Project ID.
		 */
		return (bool) apply_filters( 'chatprojects_widget_project_public', $opted_in || $is_global, $project_id );
	}

	// ==================== HELPERS ====================

	/**
	 * Send a widget SSE event.
	 *
	 * @param string $type    Event type (content, sources, error, done).
	 * @param string $content Event content.
	 * @param array  $extra   Extra data to include.
	 */
	private function send_widget_sse( $type, $content = '', $extra = array() ) {
		$data = array( 'type' => $type );

		if ( ! empty( $content ) ) {
			$data['content'] = $content;
		}

		if ( ! empty( $extra ) ) {
			$data = array_merge( $data, $extra );
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SSE protocol output
		echo 'data: ' . wp_json_encode( $data ) . "\n\n";
		flush();
	}

	/**
	 * Validate the HTTP Referrer header matches the site URL.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	private function validate_referrer( $strict = false ) {
		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );

		// Origin is sent by browsers on every cross-site and same-site POST and cannot be set by page scripts.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Origin check, not nonce
		$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? sanitize_url( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';
		if ( ! empty( $origin ) ) {
			return wp_parse_url( $origin, PHP_URL_HOST ) === $site_host;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Referrer check, not nonce
		$referrer = isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_url( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		if ( ! empty( $referrer ) ) {
			return wp_parse_url( $referrer, PHP_URL_HOST ) === $site_host;
		}

		// No Origin and no Referer: tolerated for session init (privacy extensions), never for messages.
		return ! $strict;
	}

	/**
	 * Get public widget configuration (safe to expose to visitors).
	 *
	 * @return array Configuration data.
	 */
	private function get_public_config() {
		return array(
			'welcome_message' => get_option( 'chatprojects_widget_welcome_message', __( 'Hi! How can I help you today?', 'chatprojects' ) ),
			'placeholder'     => get_option( 'chatprojects_widget_placeholder', __( 'Type your message...', 'chatprojects' ) ),
			'primary_color'   => sanitize_hex_color( get_option( 'chatprojects_widget_primary_color', '#2563eb' ) ),
			'position'        => sanitize_key( get_option( 'chatprojects_widget_position', 'bottom-right' ) ),
			'show_branding'   => defined( 'CHATPROJECTS_PRO_VERSION' ) ? (bool) get_option( 'chatprojects_widget_show_branding', true ) : true,
			'site_name'       => get_bloginfo( 'name' ),
		);
	}
}
