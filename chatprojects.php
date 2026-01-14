<?php
/**
 * Plugin Name: ChatProjects
 * Plugin URI: https://chatprojects.com/chatprojects
 * Description: AI-powered project management with multi-provider chat support. Vector store chat with OpenAI Responses API. Chat with GPT-5, Claude, Gemini, and more using your own API keys.
 * Version: 1.0.0
 * Author: chatprojects.com
 * Author URI: https://chatprojects.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: chatprojects
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package ChatProjects
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CHATPROJECTS_VERSION', '1.0.0');
define('CHATPROJECTS_PLUGIN_FILE', __FILE__);
define('CHATPROJECTS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CHATPROJECTS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CHATPROJECTS_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Define minimum requirements
define('CHATPROJECTS_MIN_PHP_VERSION', '7.4');
define('CHATPROJECTS_MIN_WP_VERSION', '5.8');

// Project settings
define('CHATPROJECTS_MAX_PROJECTS', 999); // Practical limit for performance

/**
 * Check if Pro version is active
 * If so, show notice and don't load Free version to avoid conflicts
 */
function chatprojects_check_pro_version() {
    if (defined('CHATPROJECTS_PRO_VERSION')) {
        add_action('admin_notices', 'chatprojects_pro_active_notice');
        return true;
    }
    return false;
}

/**
 * Notice when Pro version is active
 */
function chatprojects_pro_active_notice() {
    ?>
    <div class="notice notice-info">
        <p>
            <strong><?php esc_html_e('ChatProjects Pro is active', 'chatprojects'); ?></strong>
        </p>
        <p>
            <?php esc_html_e('The Pro version includes all Free features plus more. You can safely deactivate the Free version.', 'chatprojects'); ?>
        </p>
    </div>
    <?php
}

// Check if Pro version is active - if so, don't load Free version
if (chatprojects_check_pro_version()) {
    return;
}

/**
 * Check if requirements are met
 */
function chatprojects_requirements_met() {
    global $wp_version;

    if (version_compare(PHP_VERSION, CHATPROJECTS_MIN_PHP_VERSION, '<')) {
        add_action('admin_notices', 'chatprojects_php_version_notice');
        return false;
    }

    if (version_compare($wp_version, CHATPROJECTS_MIN_WP_VERSION, '<')) {
        add_action('admin_notices', 'chatprojects_wp_version_notice');
        return false;
    }

    return true;
}

/**
 * PHP version notice
 */
function chatprojects_php_version_notice() {
    ?>
    <div class="notice notice-error">
        <p>
            <?php
            printf(
                /* translators: %1$s: Required PHP version, %2$s: Current PHP version */
                esc_html__('ChatProjects requires PHP version %1$s or higher. You are running version %2$s.', 'chatprojects'),
                esc_html(CHATPROJECTS_MIN_PHP_VERSION),
                esc_html(PHP_VERSION)
            );
            ?>
        </p>
    </div>
    <?php
}

/**
 * WordPress version notice
 */
function chatprojects_wp_version_notice() {
    global $wp_version;
    ?>
    <div class="notice notice-error">
        <p>
            <?php
            printf(
                /* translators: %1$s: Required WordPress version, %2$s: Current WordPress version */
                esc_html__('ChatProjects requires WordPress version %1$s or higher. You are running version %2$s.', 'chatprojects'),
                esc_html(CHATPROJECTS_MIN_WP_VERSION),
                esc_html($wp_version)
            );
            ?>
        </p>
    </div>
    <?php
}

// Check requirements before loading
if (!chatprojects_requirements_met()) {
    return;
}

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'ChatProjects\\';
    $base_dir = CHATPROJECTS_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . 'class-' . str_replace('\\', '/', strtolower(str_replace('_', '-', $relative_class))) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Include core files
require_once CHATPROJECTS_PLUGIN_DIR . 'includes/class-chatprojects.php';
require_once CHATPROJECTS_PLUGIN_DIR . 'includes/class-installer.php';

/**
 * Initialize the plugin
 */
function chatprojects_init() {
    // Check for capability migration (cp_ to chatpr_ prefix change)
    chatprojects_maybe_migrate_capabilities();

    ChatProjects\ChatProjects::get_instance();
}
add_action('plugins_loaded', 'chatprojects_init');

/**
 * Migrate capabilities from old cp_ prefix to new chatpr_ prefix
 * This runs once after the prefix update
 */
function chatprojects_maybe_migrate_capabilities() {
    $migration_version = get_option('chatprojects_capability_migration', '0');

    // Force re-run for 1.0.3 migration (can be removed after migration is confirmed)
    if ($migration_version === '1.0.1' || $migration_version === '1.0.2') {
        delete_option('chatprojects_capability_migration');
        $migration_version = '0';
    }

    // Version 1.0.3 = capability prefix migration + chatpr_projects_user role
    if (version_compare($migration_version, '1.0.3', '<')) {
        // Add new capabilities directly (don't call Installer::activate which registers post types too early)
        $new_caps = array(
            'edit_chatpr_project', 'read_chatpr_project', 'delete_chatpr_project',
            'edit_chatpr_projects', 'edit_others_chatpr_projects', 'publish_chatpr_projects',
            'read_private_chatpr_projects', 'delete_chatpr_projects', 'delete_others_chatpr_projects',
            'delete_published_chatpr_projects', 'delete_private_chatpr_projects',
            'edit_published_chatpr_projects', 'edit_private_chatpr_projects',
            'manage_chatprojects_settings',
        );

        // Add to administrator
        $admin = get_role('administrator');
        if ($admin) {
            foreach ($new_caps as $cap) {
                $admin->add_cap($cap);
            }
        }

        // Add to editor (subset)
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

        // Add to author (own posts only)
        $author = get_role('author');
        if ($author) {
            $author->add_cap('edit_chatpr_project');
            $author->add_cap('read_chatpr_project');
            $author->add_cap('delete_chatpr_project');
            $author->add_cap('edit_chatpr_projects');
            $author->add_cap('publish_chatpr_projects');
        }

        // Add to custom project user roles (including variants from different versions)
        $custom_roles = array('chatpr_projects_user', 'chatbot_project_user', 'cp_projects_user');
        foreach ($custom_roles as $role_name) {
            $projects_user = get_role($role_name);
            if ($projects_user) {
                $projects_user->add_cap('edit_chatpr_project');
                $projects_user->add_cap('read_chatpr_project');
                $projects_user->add_cap('delete_chatpr_project');
                $projects_user->add_cap('edit_chatpr_projects');
                $projects_user->add_cap('publish_chatpr_projects');
            }
        }

        // Remove old capabilities from roles
        $old_caps = array(
            'edit_cp_project', 'read_cp_project', 'delete_cp_project',
            'edit_cp_projects', 'edit_others_cp_projects', 'publish_cp_projects',
            'read_private_cp_projects', 'delete_cp_projects', 'delete_others_cp_projects',
            'delete_published_cp_projects', 'delete_private_cp_projects',
            'edit_published_cp_projects', 'edit_private_cp_projects',
        );

        $roles = array('administrator', 'editor', 'author', 'cp_projects_user');
        foreach ($roles as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($old_caps as $cap) {
                    $role->remove_cap($cap);
                }
            }
        }

        // Remove old role if exists
        remove_role('cp_projects_user');

        update_option('chatprojects_capability_migration', '1.0.3');
    }
}

/**
 * Plugin activation hook
 */
function chatprojects_activate() {
    if (!chatprojects_requirements_met()) {
        wp_die(
            esc_html__('ChatProjects could not be activated due to unmet requirements.', 'chatprojects'),
            esc_html__('Plugin Activation Error', 'chatprojects'),
            array('back_link' => true)
        );
    }

    ChatProjects\Installer::activate();

    // Create the single shared project for Free version
    chatprojects_create_shared_project();

    // Set activation flag
    update_option('chatprojects_activated', time());
}
register_activation_hook(__FILE__, 'chatprojects_activate');

/**
 * Create shared project for Free version
 * All users share this single project
 */
function chatprojects_create_shared_project() {
    // Check if shared project already exists
    $existing = get_posts(array(
        'post_type' => 'chatpr_project',
        'posts_per_page' => 1,
        'post_status' => 'publish',
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required to find shared project
        'meta_key' => '_cp_shared_project',
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Required to find shared project
        'meta_value' => '1',
    ));

    if (!empty($existing)) {
        return; // Already exists
    }

    // Get first admin user as author
    $admins = get_users(array('role' => 'administrator', 'number' => 1));
    $author_id = !empty($admins) ? $admins[0]->ID : 1;

    // Create the shared project
    $project_id = wp_insert_post(array(
        'post_type' => 'chatpr_project',
        'post_title' => __('Shared Project', 'chatprojects'),
        'post_content' => __('This is the shared project for all users. Upload files and chat with AI assistants.', 'chatprojects'),
        'post_status' => 'publish',
        'post_author' => $author_id,
    ));

    if ($project_id && !is_wp_error($project_id)) {
        // Mark as shared project
        update_post_meta($project_id, '_cp_shared_project', '1');

        // Initialize OpenAI Assistant and Vector Store will happen on first use
    }
}

/**
 * Plugin deactivation hook
 */
function chatprojects_deactivate() {
    ChatProjects\Installer::deactivate();
}
register_deactivation_hook(__FILE__, 'chatprojects_deactivate');

