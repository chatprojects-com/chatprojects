<?php
/**
 * Message Store Class
 *
 * Manages local conversation history in WordPress database
 * Replaces OpenAI Threads API for message storage
 *
 * @package ChatProjects
 */

namespace ChatProjects;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Message Store Class
 */
class Message_Store {
    /**
     * Table name
     *
     * @var string
     */
    private $table_name;
    /**
     * Escaped table name for SQL statements
     *
     * @var string
     */
    private $table_name_sql;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'chatprojects_messages';
        $this->table_name_sql = esc_sql($this->table_name);
    }

    /**
     * Save a message to the database
     *
     * @param int    $chat_id Chat ID
     * @param string $role    Message role (user, assistant, system)
     * @param string $content Message content
     * @param array  $metadata Optional metadata (sources, annotations, etc.)
     * @return int|WP_Error Message ID or error
     */
    public function save_message($chat_id, $role, $content, $metadata = array()) {
        global $wpdb;

        // Validate inputs
        if (empty($chat_id) || !is_numeric($chat_id)) {
            return new \WP_Error('invalid_chat_id', __('Invalid chat ID.', 'chatprojects'));
        }

        $valid_roles = array('user', 'assistant', 'system');
        if (!in_array($role, $valid_roles, true)) {
            return new \WP_Error('invalid_role', __('Invalid message role.', 'chatprojects'));
        }

        if (empty($content)) {
            return new \WP_Error('empty_content', __('Message content cannot be empty.', 'chatprojects'));
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table requires direct query
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'chat_id' => absint($chat_id),
                'role' => sanitize_text_field($role),
                'content' => $content, // Allow full content, sanitized at output
                'metadata' => !empty($metadata) ? wp_json_encode($metadata) : null,
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            return new \WP_Error('db_error', __('Failed to save message.', 'chatprojects'));
        }

        $message_id = $wpdb->insert_id;

        // Update chat's message count and timestamp
        $this->update_chat_stats($chat_id);

        return $message_id;
    }

    /**
     * Get messages for a chat
     *
     * @param int $chat_id Chat ID
     * @param int $limit   Number of messages to retrieve (0 = all)
     * @param int $offset  Offset for pagination
     * @return array Array of message objects
     */
    public function get_messages($chat_id, $limit = 0, $offset = 0) {
        global $wpdb;

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name from $wpdb->prefix, custom table requires direct query
        if ($limit > 0) {
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$this->table_name_sql} WHERE chat_id = %d ORDER BY created_at ASC LIMIT %d OFFSET %d",
                    absint($chat_id),
                    absint($limit),
                    absint($offset)
                ),
                ARRAY_A
            );
        } else {
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$this->table_name_sql} WHERE chat_id = %d ORDER BY created_at ASC",
                    absint($chat_id)
                ),
                ARRAY_A
            );
        }
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        // Decode metadata JSON
        foreach ($results as &$row) {
            if (!empty($row['metadata'])) {
                $row['metadata'] = json_decode($row['metadata'], true);
            }
        }

        return $results ?: array();
    }

    /**
     * Get the last N messages for a chat (for context window)
     *
     * @param int $chat_id Chat ID
     * @param int $limit   Number of recent messages to retrieve
     * @return array Array of message objects (oldest first)
     */
    public function get_recent_messages($chat_id, $limit = 20) {
        global $wpdb;

        // Get the last N messages, then reverse to get chronological order
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table prefix is safe, custom table requires direct query
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name_sql}
                 WHERE chat_id = %d
                 ORDER BY created_at DESC
                 LIMIT %d",
                absint($chat_id),
                absint($limit)
            ),
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        // Decode metadata and reverse to chronological order
        $messages = array();
        if ($results) {
            $results = array_reverse($results);
            foreach ($results as $row) {
                if (!empty($row['metadata'])) {
                    $row['metadata'] = json_decode($row['metadata'], true);
                }
                $messages[] = $row;
            }
        }

        return $messages;
    }

    /**
     * Get messages formatted for API input (Responses API format)
     *
     * @param int    $chat_id      Chat ID
     * @param int    $limit        Number of recent messages
     * @param string $instructions System instructions to prepend
     * @return array Messages array for API
     */
    public function get_messages_for_api($chat_id, $limit = 20, $instructions = '') {
        $messages = $this->get_recent_messages($chat_id, $limit);
        $api_messages = array();

        // Add system instructions if provided
        if (!empty($instructions)) {
            $api_messages[] = array(
                'role' => 'system',
                'content' => $instructions,
            );
        }

        // Convert to API format
        foreach ($messages as $message) {
            // Skip system messages from history (instructions are added separately)
            if ($message['role'] === 'system') {
                continue;
            }

            $api_messages[] = array(
                'role' => $message['role'],
                'content' => $message['content'],
            );
        }

        return $api_messages;
    }

    /**
     * Delete all messages for a chat (privacy compliance)
     *
     * @param int $chat_id Chat ID
     * @return bool True on success
     */
    public function delete_chat_messages($chat_id) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query
        $result = $wpdb->delete(
            $this->table_name,
            array('chat_id' => absint($chat_id)),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Delete a single message
     *
     * @param int $message_id Message ID
     * @return bool True on success
     */
    public function delete_message($message_id) {
        global $wpdb;

        // Get chat_id before deleting for stats update
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table prefix is safe, custom table requires direct query
        $message = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT chat_id FROM {$this->table_name_sql} WHERE id = %d",
                absint($message_id)
            )
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query
        $result = $wpdb->delete(
            $this->table_name,
            array('id' => absint($message_id)),
            array('%d')
        );

        // Update chat stats after deletion
        if ($result && $message) {
            $this->update_chat_stats($message->chat_id);
        }

        return $result !== false;
    }

    /**
     * Count messages in a chat
     *
     * @param int $chat_id Chat ID
     * @return int Message count
     */
    public function count_messages($chat_id) {
        global $wpdb;

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table prefix is safe, custom table requires direct query
        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name_sql} WHERE chat_id = %d",
                absint($chat_id)
            )
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        return $count;
    }

    /**
     * Get the last message for a chat
     *
     * @param int    $chat_id Chat ID
     * @param string $role    Optional role filter
     * @return array|null Message data or null
     */
    public function get_last_message($chat_id, $role = null) {
        global $wpdb;

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table prefix is safe, custom table requires direct query
        if (!empty($role)) {
            $result = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$this->table_name_sql} WHERE chat_id = %d AND role = %s ORDER BY created_at DESC LIMIT 1",
                    absint($chat_id),
                    sanitize_text_field($role)
                ),
                ARRAY_A
            );
        } else {
            $result = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$this->table_name_sql} WHERE chat_id = %d ORDER BY created_at DESC LIMIT 1",
                    absint($chat_id)
                ),
                ARRAY_A
            );
        }
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        if ($result && !empty($result['metadata'])) {
            $result['metadata'] = json_decode($result['metadata'], true);
        }

        return $result;
    }

    /**
     * Update chat statistics after message changes
     *
     * @param int $chat_id Chat ID
     * @return void
     */
    private function update_chat_stats($chat_id) {
        global $wpdb;

        $count = $this->count_messages($chat_id);
        $chats_table = $wpdb->prefix . 'chatprojects_chats';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query
        $wpdb->update(
            $chats_table,
            array(
                'message_count' => $count,
                'updated_at' => current_time('mysql'),
            ),
            array('id' => absint($chat_id)),
            array('%d', '%s'),
            array('%d')
        );
    }

    /**
     * Truncate messages to fit within token limit
     * Keeps the most recent messages within approximate token budget
     *
     * @param int $chat_id          Chat ID
     * @param int $max_tokens       Maximum tokens for context
     * @param int $avg_chars_per_token Average characters per token (default 4)
     * @return array Truncated messages array for API
     */
    public function get_messages_within_token_limit($chat_id, $max_tokens = 8000, $avg_chars_per_token = 4) {
        $max_chars = $max_tokens * $avg_chars_per_token;
        $messages = $this->get_recent_messages($chat_id, 100); // Get up to 100 recent messages

        $result = array();
        $total_chars = 0;

        // Work backwards from most recent, keeping messages until we hit the limit
        $reversed = array_reverse($messages);
        foreach ($reversed as $message) {
            $msg_chars = strlen($message['content']);
            if ($total_chars + $msg_chars > $max_chars && !empty($result)) {
                break;
            }
            array_unshift($result, $message);
            $total_chars += $msg_chars;
        }

        return $result;
    }

    /**
     * Search messages by content
     *
     * @param int    $chat_id Chat ID
     * @param string $query   Search query
     * @param int    $limit   Maximum results
     * @return array Matching messages
     */
    public function search_messages($chat_id, $query, $limit = 10) {
        global $wpdb;

        $search = '%' . $wpdb->esc_like($query) . '%';

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table prefix is safe, custom table requires direct query
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name_sql}
                 WHERE chat_id = %d AND content LIKE %s
                 ORDER BY created_at DESC
                 LIMIT %d",
                absint($chat_id),
                $search,
                absint($limit)
            ),
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        return $results ?: array();
    }
}
