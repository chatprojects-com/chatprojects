<?php
/**
 * Installer Class (Free Version)
 *
 * Handles plugin activation, deactivation, and database setup
 * Stripped down version without Pro features (transcriptions, comparisons, licenses)
 *
 * @package ChatProjects
 */

namespace ChatProjects;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Installer Class
 */
class Installer {
    /**
     * Database version
     *
     * @var string
     */
    const DB_VERSION = '1.1.0';

    /**
     * Plugin activation
     */
    public static function activate() {
        // Clean up any corrupted API keys from previous installs
        // This fixes the issue where old encrypted values persist after plugin deletion
        self::cleanup_corrupted_keys();

        // Register post types first (needed for flush_rewrite_rules)
        self::register_post_types_for_activation();

        // Create database tables
        self::create_tables();

        // Set default options
        self::set_default_options();

        // Create custom user role
        User_Roles::add_custom_role();

        // Add capabilities to existing roles
        self::add_capabilities_to_roles();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Store activation time
        update_option('chatprojects_activated', time());
        update_option('chatprojects_db_version', self::DB_VERSION);
    }

    /**
     * Register post types during activation
     */
    private static function register_post_types_for_activation() {
        // Register Projects post type - must match class-chatprojects.php
        register_post_type('chatpr_project', array(
            'labels' => array(
                'name' => __('Projects', 'chatprojects'),
                'singular_name' => __('Project', 'chatprojects'),
            ),
            'public' => true,
            'has_archive' => false,
            'show_in_rest' => false,
            'rewrite' => array(
                'slug' => 'chatpr_project',
                'with_front' => false,
            ),
            'capability_type' => 'chatpr_project',
            'map_meta_cap' => true,
            'capabilities' => array(
                'edit_post' => 'edit_chatpr_project',
                'read_post' => 'read_chatpr_project',
                'delete_post' => 'delete_chatpr_project',
                'edit_posts' => 'edit_chatpr_projects',
                'edit_others_posts' => 'edit_others_chatpr_projects',
                'publish_posts' => 'publish_chatpr_projects',
                'read_private_posts' => 'read_private_chatpr_projects',
                'delete_posts' => 'delete_chatpr_projects',
                'delete_private_posts' => 'delete_private_chatpr_projects',
                'delete_published_posts' => 'delete_published_chatpr_projects',
                'delete_others_posts' => 'delete_others_chatpr_projects',
                'edit_private_posts' => 'edit_private_chatpr_projects',
                'edit_published_posts' => 'edit_published_chatpr_projects',
            ),
        ));

        // Note: Prompts post type removed in Free version
    }

    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        flush_rewrite_rules();
        wp_clear_scheduled_hook('chatprojects_cleanup_transients');
    }

    /**
     * Create custom database tables
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Chat threads table (thread_id kept for backward compat but no longer used)
        $chats_table = esc_sql($wpdb->prefix . 'chatprojects_chats');
        $chats_sql = "CREATE TABLE IF NOT EXISTS {$chats_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            chat_mode VARCHAR(20) DEFAULT 'project',
            provider VARCHAR(50) DEFAULT 'openai',
            model VARCHAR(100) DEFAULT 'gpt-4o',
            project_id BIGINT UNSIGNED DEFAULT NULL,
            thread_id VARCHAR(255) DEFAULT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) DEFAULT NULL,
            instructions TEXT DEFAULT NULL,
            message_count INT DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX project_idx (project_id),
            INDEX user_idx (user_id),
            INDEX thread_idx (thread_id),
            INDEX mode_idx (chat_mode),
            INDEX provider_idx (provider)
        ) $charset_collate;";

        // Messages table for Responses API
        $messages_table = esc_sql($wpdb->prefix . 'chatprojects_messages');
        $messages_sql = "CREATE TABLE IF NOT EXISTS {$messages_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            chat_id BIGINT UNSIGNED NOT NULL,
            role VARCHAR(20) NOT NULL,
            content LONGTEXT NOT NULL,
            metadata TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            INDEX chat_idx (chat_id),
            INDEX role_idx (role),
            INDEX created_idx (created_at)
        ) $charset_collate;";

        // Execute table creation
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($chats_sql);
        dbDelta($messages_sql);

        // Note: Transcriptions, Comparisons, and Licenses tables not created in Free version

        if (!empty($wpdb->last_error)) {
        }
    }

    /**
     * Set default options
     */
    private static function set_default_options() {
        // Initialize encryption key BEFORE any API keys can be saved
        // This prevents the race condition where key is generated during first save
        if (get_option('chatprojects_encryption_key') === false) {
            $key = bin2hex(random_bytes(16));
            update_option('chatprojects_encryption_key', $key);
        }

        // Only set if not already set
        if (get_option('chatprojects_openai_key') === false) {
            update_option('chatprojects_openai_key', '');
        }

        if (get_option('chatprojects_gemini_key') === false) {
            update_option('chatprojects_gemini_key', '');
        }

        if (get_option('chatprojects_anthropic_key') === false) {
            update_option('chatprojects_anthropic_key', '');
        }

        if (get_option('chatprojects_chutes_key') === false) {
            update_option('chatprojects_chutes_key', '');
        }

        if (get_option('chatprojects_general_chat_provider') === false) {
            update_option('chatprojects_general_chat_provider', 'openai');
        }

        if (get_option('chatprojects_general_chat_model') === false) {
            update_option('chatprojects_general_chat_model', 'gpt-5.2-chat-latest');
        }

        if (get_option('chatprojects_assistant_instructions') === false) {
            update_option('chatprojects_assistant_instructions', 'You are a helpful AI assistant. Answer questions based on the provided files and context.');
        }

        if (get_option('chatprojects_default_model') === false) {
            update_option('chatprojects_default_model', 'gpt-5.2');
        }

        if (get_option('chatprojects_max_file_size') === false) {
            update_option('chatprojects_max_file_size', 50);
        }

        if (get_option('chatprojects_allowed_file_types') === false) {
            update_option('chatprojects_allowed_file_types', array(
                'pdf', 'doc', 'docx', 'txt', 'md', 'csv', 'json', 'xml', 'html', 'css', 'js', 'py', 'php'
            ));
        }
    }

    /**
     * Clean up corrupted API keys from previous installs
     *
     * When plugin is deleted and reinstalled, the wp_options table keeps old values.
     * If those values were encrypted with a different key, they become garbage
     * that pollutes the form fields.
     */
    private static function cleanup_corrupted_keys() {
        $api_key_options = array(
            'chatprojects_openai_key',
            'chatprojects_gemini_key',
            'chatprojects_anthropic_key',
            'chatprojects_chutes_key',
            'chatprojects_openrouter_key',
        );

        foreach ($api_key_options as $option_name) {
            $value = get_option($option_name, '');
            if (empty($value)) {
                continue;
            }

            // Try to decrypt
            $decrypted = Security::decrypt($value);

            // Check if decryption failed or produced garbage
            $is_garbage = false;

            if ($decrypted === false) {
                $is_garbage = true;
            } elseif (!empty($decrypted)) {
                // Valid API keys start with known prefixes
                $valid_prefixes = array('sk-', 'sk-proj-', 'AIza', 'sk-ant-', 'cpat_', 'cpk_', 'sk-or-');
                $has_valid_prefix = false;
                foreach ($valid_prefixes as $prefix) {
                    if (strpos($decrypted, $prefix) === 0) {
                        $has_valid_prefix = true;
                        break;
                    }
                }

                // If it doesn't start with a valid prefix and is longer than 20 chars,
                // it's likely garbage from a failed decryption
                if (!$has_valid_prefix && strlen($decrypted) > 20) {
                    $is_garbage = true;
                }
            }

            if ($is_garbage) {
                delete_option($option_name);
            }
        }

        // Clean up debug options from previous debugging sessions
        delete_option('chatprojects_last_encrypt_fingerprint');
        delete_option('chatprojects_debug_last_encrypt');
        delete_option('chatprojects_debug_update_log');
        delete_option('chatprojects_debug_delete_log');
        delete_option('chatprojects_debug_sanitize_returned');
        delete_option('chatprojects_debug_intercept');
        // Clean up per-option validation debug
        foreach ($api_key_options as $opt) {
            delete_option('chatprojects_debug_validate_' . $opt);
        }
    }

    /**
     * Add capabilities to existing roles
     */
    private static function add_capabilities_to_roles() {
        // Administrator gets all capabilities
        $admin = get_role('administrator');
        if ($admin) {
            // Project capabilities
            $admin->add_cap('edit_chatpr_project');
            $admin->add_cap('read_chatpr_project');
            $admin->add_cap('delete_chatpr_project');
            $admin->add_cap('edit_chatpr_projects');
            $admin->add_cap('edit_others_chatpr_projects');
            $admin->add_cap('publish_chatpr_projects');
            $admin->add_cap('read_private_chatpr_projects');
            $admin->add_cap('delete_chatpr_projects');
            $admin->add_cap('delete_others_chatpr_projects');
            $admin->add_cap('delete_published_chatpr_projects');
            $admin->add_cap('delete_private_chatpr_projects');
            $admin->add_cap('edit_published_chatpr_projects');
            $admin->add_cap('edit_private_chatpr_projects');

            // Settings capability
            $admin->add_cap('manage_chatprojects_settings');
        }

        // Editor gets most capabilities
        $editor = get_role('editor');
        if ($editor) {
            $editor->add_cap('edit_chatpr_project');
            $editor->add_cap('read_chatpr_project');
            $editor->add_cap('delete_chatpr_project');
            $editor->add_cap('edit_chatpr_projects');
            $editor->add_cap('edit_others_chatpr_projects');
            $editor->add_cap('publish_chatpr_projects');
            $editor->add_cap('read_private_chatpr_projects');
        }

        // Author gets own capabilities
        $author = get_role('author');
        if ($author) {
            $author->add_cap('edit_chatpr_project');
            $author->add_cap('read_chatpr_project');
            $author->add_cap('delete_chatpr_project');
            $author->add_cap('edit_chatpr_projects');
            $author->add_cap('publish_chatpr_projects');
        }
    }
}
