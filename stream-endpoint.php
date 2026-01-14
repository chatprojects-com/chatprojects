<?php
/**
 * Direct streaming endpoint for ChatProjects
 *
 * This file handles SSE streaming requests directly, bypassing admin-ajax.php
 * buffering issues on some servers. It loads WordPress itself for authentication.
 *
 * IMPORTANT: This file is intentionally designed to be accessed directly.
 * It cannot use the standard ABSPATH check because it must load WordPress itself.
 * Security is enforced via: POST-only requests, nonce verification, and user auth.
 *
 * @package ChatProjects
 */

// phpcs:disable WordPress.Files.FileName.NotHyphenatedLowercase -- Standalone endpoint file

// Direct access protection - combines ABSPATH check with POST validation.
// This file loads WordPress itself for SSE streaming, so standard ABSPATH-only
// check cannot be used. Instead, we allow access only with valid POST request.
// Full nonce verification happens after WordPress loads (see line ~130).
// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified after WP loads
if ( ! defined( 'ABSPATH' ) ) {
	// WordPress not loaded yet - validate this is a legitimate SSE request.
	if (
		! isset( $_SERVER['REQUEST_METHOD'] ) ||
		'POST' !== $_SERVER['REQUEST_METHOD'] ||
		! isset( $_POST['nonce'] ) ||
		! isset( $_POST['project_id'] )
	) {
		http_response_code( 403 );
		exit( 'Direct access forbidden.' );
	}
	// Valid POST request - continue to load WordPress below.
} else {
	// ABSPATH defined means WordPress already loaded (file included as module).
	// This endpoint must be accessed directly, not included.
	exit;
}

// Disable output buffering immediately for SSE.
while ( ob_get_level() ) {
	ob_end_clean();
}

// Load WordPress.
$chatprojects_wp_load_paths = array(
	dirname( __FILE__ ) . '/../../../../wp-load.php',
	dirname( __FILE__ ) . '/../../../wp-load.php',
	dirname( __FILE__ ) . '/../../wp-load.php',
);

$chatprojects_wp_load_found = false;
foreach ( $chatprojects_wp_load_paths as $chatprojects_wp_load_path ) {
	if ( file_exists( $chatprojects_wp_load_path ) ) {
		require_once $chatprojects_wp_load_path;
		$chatprojects_wp_load_found = true;
		break;
	}
}

if ( ! $chatprojects_wp_load_found ) {
	header( 'Content-Type: text/event-stream; charset=utf-8' );
	// WordPress not loaded, use safe JSON encoding flags.
	$chatprojects_error = array(
		'type'    => 'error',
		'content' => 'WordPress not found',
	);
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.AlternativeFunctions.json_encode_json_encode -- WordPress not loaded yet; using safe encoding flags
	echo 'data: ' . json_encode( $chatprojects_error, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . "\n\n";
	exit;
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

// Configure PHP for streaming (scoped to this endpoint only).
if ( function_exists( 'ini_set' ) ) {
	// phpcs:disable Squiz.PHP.DiscouragedFunctions.Discouraged -- Required for SSE streaming in this endpoint
	@ini_set( 'zlib.output_compression', '0' );
	@ini_set( 'implicit_flush', '1' );
	@ini_set( 'output_buffering', '0' );
	// phpcs:enable Squiz.PHP.DiscouragedFunctions.Discouraged
}
@ob_implicit_flush( true );

// Send SSE padding to fill server buffer (spaces only - safe output).
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SSE comment with spaces only
echo ':' . str_repeat( ' ', 16384 ) . "\n\n";
@flush();

/**
 * Send SSE error message and exit.
 *
 * @param string $chatprojects_message Error message to send.
 */
function chatprojects_send_sse_error( $chatprojects_message ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SSE data format, JSON encoded
	echo 'data: ' . wp_json_encode(
		array(
			'type'    => 'error',
			'content' => $chatprojects_message,
		)
	) . "\n\n";
	@flush();
	exit;
}

/**
 * Send SSE data chunk.
 *
 * @param array $chatprojects_data Data to send.
 */
function chatprojects_send_sse_data( $chatprojects_data ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SSE data format, JSON encoded
	echo 'data: ' . wp_json_encode( $chatprojects_data ) . "\n\n";
	@flush();
}

/**
 * Generate a chat title from conversation using OpenAI.
 *
 * @param string $chatprojects_user_msg     User's message.
 * @param string $chatprojects_assistant_msg Assistant's response.
 * @return string Generated title or empty string on failure.
 */
function chatprojects_generate_chat_title( $chatprojects_user_msg, $chatprojects_assistant_msg ) {
	try {
		$chatprojects_api = new ChatProjects\API_Handler();

		$chatprojects_prompt = "Based on this conversation, generate a short, concise title (3-6 words maximum):\n\nUser: {$chatprojects_user_msg}\n\nAssistant: {$chatprojects_assistant_msg}\n\nRespond with ONLY the title, nothing else.";

		$chatprojects_messages = array(
			array(
				'role'    => 'user',
				'content' => $chatprojects_prompt,
			),
		);

		// Use create_chat_completion which is the correct method on API_Handler.
		$chatprojects_title_response = $chatprojects_api->create_chat_completion( $chatprojects_messages, 'gpt-4o-mini' );

		if ( is_wp_error( $chatprojects_title_response ) ) {
			// Fallback: use first few words of user message.
			$chatprojects_words = explode( ' ', $chatprojects_user_msg );
			$chatprojects_title = implode( ' ', array_slice( $chatprojects_words, 0, 5 ) );
			if ( count( $chatprojects_words ) > 5 ) {
				$chatprojects_title .= '...';
			}
			return $chatprojects_title;
		}

		$chatprojects_title = isset( $chatprojects_title_response['choices'][0]['message']['content'] )
			? $chatprojects_title_response['choices'][0]['message']['content']
			: '';

		// Clean up the title.
		$chatprojects_title = trim( $chatprojects_title, "\"'\n\r\t " );
		$chatprojects_title = preg_replace( '/^(Title:|title:)\s*/i', '', $chatprojects_title );

		// Ensure reasonable length.
		if ( strlen( $chatprojects_title ) > 100 ) {
			$chatprojects_title = substr( $chatprojects_title, 0, 97 ) . '...';
		}

		return $chatprojects_title;
	} catch ( \Exception $e ) {
		// Fallback: use first few words of user message.
		$chatprojects_words = explode( ' ', $chatprojects_user_msg );
		$chatprojects_title = implode( ' ', array_slice( $chatprojects_words, 0, 5 ) );
		if ( count( $chatprojects_words ) > 5 ) {
			$chatprojects_title .= '...';
		}
		return $chatprojects_title;
	}
}

// ============================================================================
// SECURITY: Nonce verification - validates request origin
// ============================================================================
$chatprojects_nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
if ( ! wp_verify_nonce( $chatprojects_nonce, 'chatpr_ajax_nonce' ) ) {
	chatprojects_send_sse_error( 'Invalid security token. Please refresh the page.' );
}

// Check user authentication.
if ( ! is_user_logged_in() ) {
	chatprojects_send_sse_error( 'You must be logged in.' );
}

// Get and sanitize parameters.
$chatprojects_project_id = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;
$chatprojects_message    = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
$chatprojects_thread_id  = isset( $_POST['thread_id'] ) ? sanitize_text_field( wp_unslash( $_POST['thread_id'] ) ) : '';

if ( empty( $chatprojects_message ) ) {
	chatprojects_send_sse_error( 'Message is required.' );
}

if ( empty( $chatprojects_project_id ) ) {
	chatprojects_send_sse_error( 'Project ID is required.' );
}

// Check access permissions.
if ( ! class_exists( 'ChatProjects\\Access' ) ) {
	chatprojects_send_sse_error( 'ChatProjects plugin not loaded properly.' );
}

if ( ! ChatProjects\Access::can_access_project( $chatprojects_project_id ) ) {
	chatprojects_send_sse_error( 'Access denied.' );
}

// Get or create chat.
global $wpdb;
$chatprojects_user_id = get_current_user_id();
$chatprojects_chat_id = null;

if ( ! empty( $chatprojects_thread_id ) ) {
	$chatprojects_chats_table     = $wpdb->prefix . 'chatprojects_chats';
	$chatprojects_chats_table_sql = esc_sql( $chatprojects_chats_table );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table
	$chatprojects_chat = $wpdb->get_row(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized
			"SELECT * FROM `{$chatprojects_chats_table_sql}` WHERE id = %d AND project_id = %d AND user_id = %d",
			intval( $chatprojects_thread_id ),
			$chatprojects_project_id,
			$chatprojects_user_id
		)
	);
	if ( $chatprojects_chat ) {
		$chatprojects_chat_id = $chatprojects_chat->id;
	}
}

if ( empty( $chatprojects_chat_id ) ) {
	$chatprojects_chat_interface = new ChatProjects\Chat_Interface();
	$chatprojects_chat_id        = $chatprojects_chat_interface->create_chat( $chatprojects_project_id );
	if ( is_wp_error( $chatprojects_chat_id ) ) {
		chatprojects_send_sse_error( $chatprojects_chat_id->get_error_message() );
	}
}

// Get project settings.
$chatprojects_vector_store_id = get_post_meta( $chatprojects_project_id, '_cp_vector_store_id', true );
if ( empty( $chatprojects_vector_store_id ) ) {
	chatprojects_send_sse_error( 'No vector store configured for this project. Please upload files first.' );
}

$chatprojects_instructions = get_post_meta( $chatprojects_project_id, '_cp_instructions', true );
if ( empty( $chatprojects_instructions ) ) {
	$chatprojects_instructions = get_option( 'chatprojects_assistant_instructions', '' );
}

$chatprojects_model = get_post_meta( $chatprojects_project_id, '_cp_model', true );
if ( empty( $chatprojects_model ) ) {
	$chatprojects_model = get_option( 'chatprojects_default_model', 'gpt-4o' );
}

// Store user message.
$chatprojects_messages_table = $wpdb->prefix . 'chatprojects_messages';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table
$wpdb->insert(
	$chatprojects_messages_table,
	array(
		'chat_id'    => $chatprojects_chat_id,
		'role'       => 'user',
		'content'    => $chatprojects_message,
		'created_at' => current_time( 'mysql' ),
	),
	array( '%d', '%s', '%s', '%s' )
);

// Stream the response.
$chatprojects_assistant_content = '';
$chatprojects_sources           = array();

$chatprojects_api_handler = new ChatProjects\API_Handler();
$chatprojects_api_handler->stream_response_with_filesearch(
	$chatprojects_message,
	$chatprojects_vector_store_id,
	function ( $chunk ) use ( &$chatprojects_assistant_content, &$chatprojects_sources ) {
		if ( isset( $chunk['type'] ) && 'content' === $chunk['type'] ) {
			$chatprojects_assistant_content .= $chunk['content'];
		} elseif ( isset( $chunk['type'] ) && 'sources' === $chunk['type'] ) {
			$chatprojects_sources = $chunk['sources'];
		}

		// Output immediately (SSE format with JSON encoding).
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SSE data format, JSON encoded
		echo 'data: ' . wp_json_encode( $chunk ) . "\n\n";

		if ( function_exists( 'litespeed_flush' ) ) {
			litespeed_flush();
		}
		@ob_flush();
		@flush();
	},
	$chatprojects_model,
	$chatprojects_instructions
);

// Store assistant message.
if ( ! empty( $chatprojects_assistant_content ) ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table
	$wpdb->insert(
		$chatprojects_messages_table,
		array(
			'chat_id'    => $chatprojects_chat_id,
			'role'       => 'assistant',
			'content'    => $chatprojects_assistant_content,
			'metadata'   => ! empty( $chatprojects_sources ) ? wp_json_encode( array( 'sources' => $chatprojects_sources ) ) : null,
			'created_at' => current_time( 'mysql' ),
		),
		array( '%d', '%s', '%s', '%s', '%s' )
	);
}

// Update message count and generate title BEFORE sending [DONE].
$chatprojects_chats_table_update     = $wpdb->prefix . 'chatprojects_chats';
$chatprojects_chats_table_update_sql = esc_sql( $chatprojects_chats_table_update );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table
$chatprojects_chat_update = $wpdb->get_row(
	$wpdb->prepare(
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized
		"SELECT * FROM `{$chatprojects_chats_table_update_sql}` WHERE id = %d",
		$chatprojects_chat_id
	)
);

if ( $chatprojects_chat_update ) {
	$chatprojects_new_count = $chatprojects_chat_update->message_count + 2;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table
	$wpdb->update(
		$chatprojects_chats_table_update,
		array(
			'message_count' => $chatprojects_new_count,
			'updated_at'    => current_time( 'mysql' ),
		),
		array( 'id' => $chatprojects_chat_id ),
		array( '%d', '%s' ),
		array( '%d' )
	);

	// Auto-generate title after first message exchange (BEFORE [DONE] so frontend receives it).
	if ( 2 === $chatprojects_new_count && ( empty( $chatprojects_chat_update->title ) || 0 === strpos( $chatprojects_chat_update->title, 'Chat ' ) ) ) {
		$chatprojects_generated_title = chatprojects_generate_chat_title( $chatprojects_message, $chatprojects_assistant_content );
		if ( ! empty( $chatprojects_generated_title ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table
			$wpdb->update(
				$chatprojects_chats_table_update,
				array( 'title' => sanitize_text_field( $chatprojects_generated_title ) ),
				array( 'id' => $chatprojects_chat_id ),
				array( '%s' ),
				array( '%d' )
			);
			// Notify frontend of title change.
			chatprojects_send_sse_data(
				array(
					'type'    => 'title_update',
					'chat_id' => $chatprojects_chat_id,
					'title'   => $chatprojects_generated_title,
				)
			);
		}
	}
}

// Send chat_id.
chatprojects_send_sse_data(
	array(
		'type'    => 'chat_id',
		'chat_id' => $chatprojects_chat_id,
	)
);

// Send done signal.
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SSE done marker
echo "data: [DONE]\n\n";
@flush();

exit;
