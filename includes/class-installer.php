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
    const DB_VERSION = '1.2.0';

    /**
     * Plugin activation
     */
    public static function activate() {
        // Clean up any corrupted API keys from previous installs
        // This fixes the issue where old encrypted values persist after plugin deletion
        self::cleanup_corrupted_keys();

        // Register post types first (needed for flush_rewrite_rules)
        self::register_post_types_for_activation();

        // Create / upgrade database tables and migrate stored data
        self::run_upgrade_steps();

        // Set default options
        self::set_default_options();

        // Create custom user role
        User_Roles::add_custom_role();

        // Add capabilities to existing roles
        self::add_capabilities_to_roles();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Run slug migration for existing installations
        self::maybe_run_slug_migration();

        // Remove dangerous generic slugs from old slug redirects
        self::maybe_cleanup_old_slugs();

        // Check for URL slug conflicts with existing pages
        self::check_slug_conflicts();

        // Store activation time
        update_option('chatprojects_activated', time());
        update_option('chatprojects_db_version', self::DB_VERSION);
    }

    /**
     * Run slug migration for existing installations
     * Only runs once per version to avoid repeated execution
     */
    private static function maybe_run_slug_migration() {
        $migration_version = '1.1.0'; // Version when slug migration was introduced
        $migrated = get_option('chatprojects_slug_migration_version', false);

        // Skip if already migrated to this version
        if ($migrated === $migration_version) {
            return;
        }

        // Get new slugs
        $slugs = \ChatProjects\ChatProjects::get_slugs();

        // Store old slugs for redirect mapping
        // Only include slugs that were actually renamed (not generic ones like 'settings' or 'projects')
        $old_slugs = array(
            'chat'       => 'pro-chat',
            'comparison' => 'pro-chat/compare',
        );

        update_option('chatprojects_old_slugs', $old_slugs);
        update_option('chatprojects_new_slugs', $slugs);

        // Flush rewrite rules to register new slugs
        flush_rewrite_rules();

        // Mark migration as complete
        update_option('chatprojects_slug_migration_version', $migration_version);

        // Set admin notice for users
        set_transient('chatprojects_slug_migration_notice', true, DAY_IN_SECONDS);
    }

    /**
     * Remove generic slugs (settings, projects) from old slug redirects.
     * These are too common and can hijack unrelated pages on other sites.
     */
    private static function maybe_cleanup_old_slugs() {
        $cleanup_version = '1.1.4';
        $cleaned = get_option('chatprojects_slug_cleanup_version', false);

        if ($cleaned === $cleanup_version) {
            return;
        }

        $old_slugs = get_option('chatprojects_old_slugs', array());
        unset($old_slugs['settings']);
        unset($old_slugs['projects']);
        update_option('chatprojects_old_slugs', $old_slugs);
        update_option('chatprojects_slug_cleanup_version', $cleanup_version);
    }

    /**
     * Check for URL slug conflicts with existing pages
     *
     * The plugin uses rewrite rules for /chatprojects, /cp-settings, and /cp-chat
     * which will override any existing WordPress pages with those slugs.
     */
    private static function check_slug_conflicts() {
        $slugs = \ChatProjects\ChatProjects::get_slugs();
        $reserved_slugs = array(
            $slugs['projects'],
            $slugs['settings'],
            $slugs['chat'],
        );
        $conflicts = array();

        foreach ($reserved_slugs as $slug) {
            $page = get_page_by_path($slug);
            if ($page && $page->post_status === 'publish') {
                $conflicts[] = array(
                    'slug'    => $slug,
                    'page_id' => $page->ID,
                    'title'   => $page->post_title
                );
            }
        }

        if (!empty($conflicts)) {
            update_option('chatprojects_slug_conflicts', $conflicts);
        } else {
            delete_option('chatprojects_slug_conflicts');
        }
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
        wp_clear_scheduled_hook('chatprojects_process_index_batch');
        wp_clear_scheduled_hook('chatprojects_cleanup_widget_sessions');

        // Delete rewrite flush flag so reinstall triggers a fresh flush
        delete_option('chatprojects_rewrites_flushed');

        // Clean up slug migration data
        delete_option('chatprojects_old_slugs');
        delete_option('chatprojects_new_slugs');
        delete_option('chatprojects_slug_migration_version');
        delete_transient('chatprojects_slug_migration_notice');
    }

    /**
     * Create custom database tables
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Chat threads table (thread_id kept for backward compat but no longer used)
        $chats_table = esc_sql($wpdb->prefix . 'chatprojects_chats');
        $chats_sql = "CREATE TABLE {$chats_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            chat_mode VARCHAR(20) DEFAULT 'project',
            provider VARCHAR(50) DEFAULT 'openai',
            model VARCHAR(100) DEFAULT '" . esc_sql( Model_Registry::get_default( 'openai' ) ) . "',
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
        $messages_sql = "CREATE TABLE {$messages_table} (
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

        // Indexed content tracking table for Auto-RAG
        $indexed_table = esc_sql( $wpdb->prefix . 'chatprojects_indexed_content' );
        $indexed_sql   = "CREATE TABLE {$indexed_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            project_id BIGINT UNSIGNED NOT NULL,
            post_id BIGINT UNSIGNED NOT NULL,
            post_type VARCHAR(50) NOT NULL,
            file_id VARCHAR(255) DEFAULT NULL,
            content_hash VARCHAR(64) NOT NULL,
            status VARCHAR(20) DEFAULT 'pending',
            error_message TEXT DEFAULT NULL,
            indexed_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY project_post (project_id, post_id),
            INDEX status_idx (status)
        ) $charset_collate;";

        // Widget sessions table
        $widget_sessions_table = esc_sql( $wpdb->prefix . 'chatprojects_widget_sessions' );
        $widget_sessions_sql   = "CREATE TABLE {$widget_sessions_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            session_token VARCHAR(64) NOT NULL,
            project_id BIGINT UNSIGNED DEFAULT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            message_count INT DEFAULT 0,
            last_message_at DATETIME DEFAULT NULL,
            lead_email VARCHAR(255) DEFAULT NULL,
            lead_name VARCHAR(255) DEFAULT NULL,
            metadata TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            INDEX session_idx (session_token),
            INDEX ip_idx (ip_address),
            INDEX expires_idx (expires_at)
        ) $charset_collate;";

        // Widget messages table
        $widget_messages_table = esc_sql( $wpdb->prefix . 'chatprojects_widget_messages' );
        $widget_messages_sql   = "CREATE TABLE {$widget_messages_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            session_id BIGINT UNSIGNED NOT NULL,
            role VARCHAR(20) NOT NULL,
            content LONGTEXT NOT NULL,
            metadata TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            INDEX session_idx (session_id),
            INDEX created_idx (created_at)
        ) $charset_collate;";

        // Execute table creation
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($chats_sql);
        dbDelta($messages_sql);
        dbDelta($indexed_sql);
        dbDelta($widget_sessions_sql);
        dbDelta($widget_messages_sql);

        // Note: Transcriptions, Comparisons, and Licenses tables not created in Free version

        if (!empty($wpdb->last_error)) {
            Security::debug_log('create_tables: ' . $wpdb->last_error);
        }
    }

    /**
     * Run schema/data upgrades when the stored DB version is behind the code.
     *
     * Hooked on plugins_loaded, so in-place plugin updates (which never fire
     * the activation hook) still get new tables and model remaps.
     */
    public static function maybe_upgrade() {
        $installed = get_option('chatprojects_db_version', '0');

        if (version_compare($installed, self::DB_VERSION, '>=')) {
            return;
        }

        // Guard against concurrent requests running the upgrade twice.
        if (get_transient('chatprojects_upgrade_lock')) {
            return;
        }
        set_transient('chatprojects_upgrade_lock', 1, MINUTE_IN_SECONDS);

        self::run_upgrade_steps();

        update_option('chatprojects_db_version', self::DB_VERSION);
        delete_transient('chatprojects_upgrade_lock');
    }

    /**
     * Idempotent upgrade steps shared by activation and maybe_upgrade().
     */
    private static function run_upgrade_steps() {
        self::create_tables();
        self::migrate_models();

        // Daily cleanup of expired widget sessions.
        if (!wp_next_scheduled('chatprojects_cleanup_widget_sessions')) {
            wp_schedule_event(time(), 'daily', 'chatprojects_cleanup_widget_sessions');
        }

        // One-time slug cleanup previously done on every front-end request.
        if (get_option('chatprojects_slug_cleanup_version', '') !== '1.1.4') {
            $old_slugs = get_option('chatprojects_old_slugs', array());
            if (is_array($old_slugs)) {
                unset($old_slugs['settings'], $old_slugs['projects']);
                update_option('chatprojects_old_slugs', $old_slugs);
            }
            update_option('chatprojects_slug_cleanup_version', '1.1.4');
        }
    }

    /**
     * Remap retired model IDs stored in options, project meta and chat rows.
     *
     * Safe to run repeatedly: Model_Registry::remap_legacy() returns current
     * IDs unchanged, so a second pass finds nothing to update.
     */
    private static function migrate_models() {
        global $wpdb;

        $summary = array(
            'options'   => 0,
            'post_meta' => 0,
            'chats'     => 0,
            'map'       => array(),
        );

        // 1. Options.
        $option_providers = array(
            'chatprojects_default_model'      => 'openai',
            'chatprojects_general_chat_model' => get_option('chatprojects_general_chat_provider', 'openai'),
        );
        foreach ($option_providers as $option => $provider) {
            $old = get_option($option, '');
            if (!is_string($old) || '' === $old) {
                continue;
            }
            $new = Model_Registry::resolve($provider, $old);
            if ($new !== $old) {
                update_option($option, $new);
                $summary['options']++;
                $summary['map'][ $old ] = $new;
            }
        }

        // 2. Project meta (_cp_model is always an OpenAI model).
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off migration.
        $meta_values = $wpdb->get_col(
            $wpdb->prepare("SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s", '_cp_model')
        );
        foreach ((array) $meta_values as $old) {
            if (!is_string($old) || '' === $old) {
                continue;
            }
            $new = Model_Registry::resolve('openai', $old);
            if ($new === $old) {
                continue;
            }
            $updated = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE meta_key = %s AND meta_value = %s",
                    $new,
                    '_cp_model',
                    $old
                )
            );
            $summary['post_meta'] += (int) $updated;
            $summary['map'][ $old ] = $new;
        }

        // 3. Chat rows (provider-aware).
        $chats_table = esc_sql($wpdb->prefix . 'chatprojects_chats');
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->prefix . 'chatprojects_chats'));
        if ($table_exists) {
            $rows = $wpdb->get_results("SELECT DISTINCT provider, model FROM {$chats_table} WHERE model IS NOT NULL AND model <> ''");
            foreach ((array) $rows as $row) {
                $provider = !empty($row->provider) ? $row->provider : 'openai';
                $old      = (string) $row->model;
                $new      = Model_Registry::resolve($provider, $old);
                if ($new === $old) {
                    continue;
                }
                $updated = $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$chats_table} SET model = %s WHERE provider = %s AND model = %s",
                        $new,
                        $row->provider,
                        $old
                    )
                );
                $summary['chats'] += (int) $updated;
                $summary['map'][ $old ] = $new;
            }
        }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        if (!empty($summary['map'])) {
            wp_cache_flush();
            set_transient('chatprojects_model_migration_notice', $summary, WEEK_IN_SECONDS);
        }

        return $summary;
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
            update_option('chatprojects_general_chat_model', Model_Registry::get_default('openai'));
        }

        if (get_option('chatprojects_assistant_instructions') === false) {
            update_option('chatprojects_assistant_instructions', 'You are a helpful AI assistant. Answer questions based on the provided files and context.');
        }

        if (get_option('chatprojects_default_model') === false) {
            update_option('chatprojects_default_model', Model_Registry::get_default('openai'));
        }

        if (get_option('chatprojects_max_file_size') === false) {
            update_option('chatprojects_max_file_size', 50);
        }

        if (get_option('chatprojects_allowed_file_types') === false) {
            update_option('chatprojects_allowed_file_types', array(
                'pdf', 'doc', 'docx', 'txt', 'md', 'csv', 'json', 'xml', 'html', 'css', 'js', 'py', 'php'
            ));
        }

        // Widget defaults.
        if ( get_option( 'chatprojects_widget_enabled' ) === false ) {
            update_option( 'chatprojects_widget_enabled', false );
        }

        if ( get_option( 'chatprojects_widget_project_id' ) === false ) {
            update_option( 'chatprojects_widget_project_id', 0 );
        }

        if ( get_option( 'chatprojects_widget_position' ) === false ) {
            update_option( 'chatprojects_widget_position', 'bottom-right' );
        }

        if ( get_option( 'chatprojects_widget_primary_color' ) === false ) {
            update_option( 'chatprojects_widget_primary_color', '#2563eb' );
        }

        if ( get_option( 'chatprojects_widget_welcome_message' ) === false ) {
            update_option( 'chatprojects_widget_welcome_message', 'Hello! How can I help you today?' );
        }

        if ( get_option( 'chatprojects_widget_placeholder' ) === false ) {
            update_option( 'chatprojects_widget_placeholder', 'Type your message...' );
        }

        if ( get_option( 'chatprojects_widget_auto_inject' ) === false ) {
            update_option( 'chatprojects_widget_auto_inject', false );
        }

        if ( get_option( 'chatprojects_widget_rate_limit_msgs' ) === false ) {
            update_option( 'chatprojects_widget_rate_limit_msgs', 20 );
        }

        if ( get_option( 'chatprojects_widget_rate_limit_sessions' ) === false ) {
            update_option( 'chatprojects_widget_rate_limit_sessions', 5 );
        }

        if ( get_option( 'chatprojects_widget_show_branding' ) === false ) {
            update_option( 'chatprojects_widget_show_branding', true );
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
