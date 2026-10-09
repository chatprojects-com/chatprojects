<?php
/**
 * Plugin Name: ChatProjects
 * Plugin URI: https://chatprojects.com/chatprojects
 * Description: AI-powered project management with multi-provider chat support. Vector store chat with OpenAI Responses API. Chat with GPT-5.6, Claude Opus 5.5, Gemini 3.8, DeepSeek V4 and more using your own API keys.
 * Version: 1.3.0
 * Author: chatprojects.com
 * Author URI: https://chatprojects.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: chatprojects
 * Domain Path: /languages
 * Requires at least: 6.6
 * Requires PHP: 8.0
 *
 * @package ChatProjects
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/*
 * ChatProjects Pro includes everything in Free and shares its data, function,
 * constant and class names. When Pro is loaded, Free must declare nothing
 * (it would fatal with "Cannot redeclare"), so this check comes first and
 * all function declarations live in includes/bootstrap.php.
 */
if (defined('CHATPROJECTS_PRO_VERSION')) {
    add_action('admin_notices', static function () {
        if (!current_user_can('activate_plugins')) {
            return;
        }
        echo '<div class="notice notice-info"><p><strong>' . esc_html__('ChatProjects Pro is active', 'chatprojects') . '</strong></p><p>'
            . esc_html__('The Pro version includes all Free features plus more. You can safely deactivate the Free version.', 'chatprojects')
            . '</p></div>';
    });
    return;
}

// Define plugin constants
define('CHATPROJECTS_VERSION', '1.3.0');
define('CHATPROJECTS_PLUGIN_FILE', __FILE__);
define('CHATPROJECTS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CHATPROJECTS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CHATPROJECTS_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Define minimum requirements
define('CHATPROJECTS_MIN_PHP_VERSION', '8.0');
define('CHATPROJECTS_MIN_WP_VERSION', '6.6');

// Project settings
define('CHATPROJECTS_MAX_PROJECTS', 999); // Practical limit for performance

require_once __DIR__ . '/includes/bootstrap.php';
