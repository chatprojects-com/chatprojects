<?php
/**
 * Frontend Class
 *
 * Handles frontend functionality
 *
 * @package ChatProjects
 */

namespace ChatProjects;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Frontend Class
 */
class Frontend {
    /**
     * Constructor
     */
    public function __construct() {
        // Flush rewrite rules once after custom endpoints are registered
        // This ensures WordPress recognizes our custom /projects/ and /settings/ URLs
        add_action('init', array($this, 'maybe_flush_rewrite_rules'), 999);

        add_action('init', array($this, 'init'));
        add_action('template_redirect', array($this, 'redirect_projects_users'));
        add_filter('template_include', array($this, 'load_project_template'), 999); // Very high priority to override block editor
        add_filter('the_content', array($this, 'inject_project_workspace'));
        add_shortcode('chatprojects_workspace', array($this, 'render_workspace'));
        add_shortcode('chatprojects_main', array($this, 'render_main_app'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

        // Hide admin bar on ChatProjects frontend pages
        add_filter('show_admin_bar', array($this, 'maybe_hide_admin_bar'));
    }

    /**
     * Flush rewrite rules once after custom endpoints are registered
     * This runs at very late priority (999) to ensure all rewrite rules are registered first
     */
    public function maybe_flush_rewrite_rules() {
        $version_key = 'chatprojects_rewrites_flushed';
        $current_version = '1.3.5'; // Force flush - fix rewrite rules registration

        if (get_option($version_key) !== $current_version) {
            flush_rewrite_rules();
            update_option($version_key, $current_version);
        }
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_assets() {
        // The modern template (project-shell-modern.php) handles its own asset loading
        // through the main ChatProjects class enqueue_frontend_scripts() method.
        // Individual page loaders (load_projects_page, load_pro_chat_page, etc.)
        // also enqueue assets/dist/css/main.css as needed.
        // No additional global enqueues needed here.
    }

    /**
     * Hide admin bar on ChatProjects frontend pages
     *
     * @param bool $show Whether to show the admin bar
     * @return bool
     */
    public function maybe_hide_admin_bar($show) {
        // Check if we're on a ChatProjects page
        $chatpr_page = get_query_var('chatpr_page');

        // Hide admin bar on ChatProjects custom pages
        if (!empty($chatpr_page)) {
            return false;
        }

        // Hide admin bar on chatpr_project post type
        if (is_singular('chatpr_project')) {
            return false;
        }

        return $show;
    }

    /**
     * Initialize frontend
     */
    public function init() {
        // Block Projects Users from accessing wp-admin
        add_action('admin_init', array($this, 'block_admin_access'));

        // Register custom endpoints
        $this->register_custom_endpoints();
    }

    /**
     * Register custom endpoints for frontend pages
     */
    public function register_custom_endpoints() {
        // Add rewrite rules for custom pages
        add_rewrite_rule('^projects/?$', 'index.php?chatpr_page=projects', 'top');
        add_rewrite_rule('^settings/?$', 'index.php?chatpr_page=settings', 'top');
        add_rewrite_rule('^pro-chat/?$', 'index.php?chatpr_page=pro_chat', 'top');
        add_rewrite_rule('^pro-chat/compare/?$', 'index.php?chatpr_page=pro_comparison', 'top');

        // Add query var
        add_filter('query_vars', function($vars) {
            $vars[] = 'chatpr_page';
            return $vars;
        });

        // Handle template loading for custom pages
        add_action('template_redirect', array($this, 'handle_custom_pages'));
    }

    /**
     * Load custom template for single project pages
     * Replaces theme template completely
     */
    public function load_project_template($template) {
        // Debug logging
        if (is_singular('chatpr_project')) {
            // Require login
            if (!is_user_logged_in()) {
                auth_redirect();
                exit;
            }

            // Check if user has ChatProjects permissions
            if (!User_Roles::can_use_chatprojects()) {
                $this->render_access_denied_page();
                exit;
            }

            // Prevent browser caching to ensure fresh nonces and scripts
            nocache_headers();

            $plugin_template = CHATPROJECTS_PLUGIN_DIR . 'public/templates/project-shell-modern.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            } else {
            }
        }
        return $template;
    }

    /**
     * Handle custom page requests
     */
    public function handle_custom_pages() {
        $page = get_query_var('chatpr_page');
        if (!$page) {
            return;
        }
        // Require login for all custom pages
        if (!is_user_logged_in()) {
            auth_redirect();
            exit;
        }
        // Check if user has ChatProjects permissions
        if (!User_Roles::can_use_chatprojects()) {
            $this->render_access_denied_page();
            exit;
        }
        // Load appropriate template
        switch ($page) {
            case 'projects':
                $this->load_projects_page();
                break;
            case 'settings':
                $this->load_settings_page();
                break;
            case 'pro_chat':
                $this->load_pro_chat_page();
                break;
            case 'pro_comparison':
                $this->load_pro_comparison_page();
                break;
        }
    }

    /**
     * Load projects listing page
     */
    private function load_projects_page() {
        // Prevent browser caching to ensure fresh nonces and scripts
        nocache_headers();

        // Ensure jQuery is loaded
        wp_enqueue_script('jquery');
        // Manually enqueue scripts directly
        wp_enqueue_style(
            'chatprojects-frontend',
            CHATPROJECTS_PLUGIN_URL . 'assets/dist/css/main.css',
            array(),
            CHATPROJECTS_VERSION . '-' . filemtime(CHATPROJECTS_PLUGIN_DIR . 'assets/dist/css/main.css')
        );

        // Enqueue mobile responsive CSS
        wp_enqueue_style(
            'chatprojects-mobile',
            CHATPROJECTS_PLUGIN_URL . 'assets/dist/css/mobile-responsive.css',
            array('chatprojects-frontend'),
            CHATPROJECTS_VERSION . '-' . filemtime(CHATPROJECTS_PLUGIN_DIR . 'assets/dist/css/mobile-responsive.css')
        );
        wp_enqueue_script(
            'chatprojects-main',
            CHATPROJECTS_PLUGIN_URL . 'assets/dist/js/main.js',
            array('jquery'),
            CHATPROJECTS_VERSION . '-' . filemtime(CHATPROJECTS_PLUGIN_DIR . 'assets/dist/js/main.js'),
            true // Load in footer
        );
        // Add type="module" attribute
        // The main plugin class filter doesn't fire because wp_enqueue_scripts was manually triggered
        add_filter('script_loader_tag', function($tag, $handle) {
            if ('chatprojects-main' === $handle) {
                if (strpos($tag, 'type="module"') === false && strpos($tag, "type='module'") === false) {
                    $tag = preg_replace('/<script\s/', '<script type="module" ', $tag);
                }
            }
            return $tag;
        }, 10, 2);
        // Localize script for AJAX
        wp_localize_script('chatprojects-main', 'chatprAjax', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('chatpr_ajax_nonce'),
            'current_user' => wp_get_current_user()->display_name,
            'strings' => array(
                'sending' => __('Sending...', 'chatprojects'),
                'error' => __('An error occurred. Please try again.', 'chatprojects'),
            ),
        ));
        $template = CHATPROJECTS_PLUGIN_DIR . 'public/templates/projects-list.php';
        if (file_exists($template)) {
            include $template;
            exit;
        } else {
        }
    }

    /**
     * Load user settings page
     */
    private function load_settings_page() {
        // Prevent browser caching to ensure fresh nonces and scripts
        nocache_headers();

        // Ensure jQuery is loaded
        wp_enqueue_script('jquery');

        // Manually enqueue scripts directly
        wp_enqueue_style(
            'chatprojects-frontend',
            CHATPROJECTS_PLUGIN_URL . 'assets/dist/css/main.css',
            array(),
            CHATPROJECTS_VERSION . '-' . filemtime(CHATPROJECTS_PLUGIN_DIR . 'assets/dist/css/main.css')
        );

        // Enqueue mobile responsive CSS
        wp_enqueue_style(
            'chatprojects-mobile',
            CHATPROJECTS_PLUGIN_URL . 'assets/dist/css/mobile-responsive.css',
            array('chatprojects-frontend'),
            CHATPROJECTS_VERSION . '-' . filemtime(CHATPROJECTS_PLUGIN_DIR . 'assets/dist/css/mobile-responsive.css')
        );

        wp_enqueue_script(
            'chatprojects-main',
            CHATPROJECTS_PLUGIN_URL . 'assets/dist/js/main.js',
            array('jquery'),
            CHATPROJECTS_VERSION . '-' . filemtime(CHATPROJECTS_PLUGIN_DIR . 'assets/dist/js/main.js'),
            true
        );

        // Add type="module" attribute
        // The main plugin class filter doesn't fire because wp_enqueue_scripts was manually triggered
        add_filter('script_loader_tag', function($tag, $handle) {
            if ('chatprojects-main' === $handle) {
                if (strpos($tag, 'type="module"') === false && strpos($tag, "type='module'") === false) {
                    $tag = preg_replace('/<script\s/', '<script type="module" ', $tag);
                }
            }
            return $tag;
        }, 10, 2);

        // Localize script
        wp_localize_script('chatprojects-main', 'chatprData', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('chatpr_ajax_nonce'),
            'current_user' => wp_get_current_user()->display_name,
        ));

        $template = CHATPROJECTS_PLUGIN_DIR . 'public/templates/user-settings.php';

        if (file_exists($template)) {
            include $template;
            exit;
        }
    }

    /**
     * Load Pro Chat page
     */
    private function load_pro_chat_page() {
        // Prevent browser caching to ensure fresh nonces and scripts
        nocache_headers();

        // Ensure jQuery is loaded
        wp_enqueue_script('jquery');

        // Manually enqueue scripts directly
        wp_enqueue_style(
            'chatprojects-frontend',
            CHATPROJECTS_PLUGIN_URL . 'assets/dist/css/main.css',
            array(),
            CHATPROJECTS_VERSION . '-' . filemtime(CHATPROJECTS_PLUGIN_DIR . 'assets/dist/css/main.css')
        );

        // Enqueue mobile responsive CSS
        wp_enqueue_style(
            'chatprojects-mobile',
            CHATPROJECTS_PLUGIN_URL . 'assets/dist/css/mobile-responsive.css',
            array('chatprojects-frontend'),
            CHATPROJECTS_VERSION . '-' . filemtime(CHATPROJECTS_PLUGIN_DIR . 'assets/dist/css/mobile-responsive.css')
        );

        wp_enqueue_script(
            'chatprojects-main',
            CHATPROJECTS_PLUGIN_URL . 'assets/dist/js/main.js',
            array('jquery'),
            CHATPROJECTS_VERSION . '-' . filemtime(CHATPROJECTS_PLUGIN_DIR . 'assets/dist/js/main.js'),
            true
        );

        // Add type="module" attribute
        add_filter('script_loader_tag', function($tag, $handle) {
            if ('chatprojects-main' === $handle) {
                if (strpos($tag, 'type="module"') === false && strpos($tag, "type='module'") === false) {
                    $tag = preg_replace('/<script\s/', '<script type="module" ', $tag);
                }
            }
            return $tag;
        }, 10, 2);

        // Localize script
        wp_localize_script('chatprojects-main', 'chatprData', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('chatpr_ajax_nonce'),
            'current_user' => wp_get_current_user()->display_name,
            'default_provider' => get_option('chatprojects_general_chat_provider', 'openai'),
            'default_model' => get_option('chatprojects_general_chat_model', 'gpt-5.2-chat-latest'),
            // Image upload settings (Free version: 1 image per message)
            'is_pro_user' => false,
            'max_images_per_message' => 1,
            'max_image_size' => Security::get_max_image_upload_size(),
            'strings' => array(
                'sending' => __('Sending...', 'chatprojects'),
                'error' => __('An error occurred. Please try again.', 'chatprojects'),
                'transcribing' => __('Transcribing...', 'chatprojects'),
                'uploading' => __('Uploading...', 'chatprojects'),
                'copied' => __('Copied to clipboard', 'chatprojects'),
                'copy_failed' => __('Failed to copy', 'chatprojects'),
                'provider_switch_confirm' => __('Switching providers will create a new conversation. Continue?', 'chatprojects'),
                /* translators: %s: Maximum image file size in megabytes */
                'image_too_large' => __('Image is too large. Maximum size is %s MB.', 'chatprojects'),
                'invalid_image_type' => __('Invalid image type. Allowed: JPEG, PNG, GIF, WebP.', 'chatprojects'),
                'max_images_reached' => __('Maximum number of images reached.', 'chatprojects'),
            ),
        ));

        $template = CHATPROJECTS_PLUGIN_DIR . 'public/templates/pro-chat.php';

        if (file_exists($template)) {
            include $template;
            exit;
        }
    }

    /**
     * Load Pro Comparison page
     */
    private function load_pro_comparison_page() {
        // Prevent browser caching to ensure fresh nonces and scripts
        nocache_headers();

        // Pro-only feature: redirect Free users to Pro Chat
        if (!defined('CHATPROJECTS_PRO_VERSION')) {
            wp_safe_redirect(home_url('/pro-chat/'));
            exit;
        }

        // Ensure jQuery is loaded
        wp_enqueue_script('jquery');

        // Manually enqueue scripts directly
        wp_enqueue_style(
            'chatprojects-frontend',
            CHATPROJECTS_PLUGIN_URL . 'assets/dist/css/main.css',
            array(),
            CHATPROJECTS_VERSION . '-' . filemtime(CHATPROJECTS_PLUGIN_DIR . 'assets/dist/css/main.css')
        );

        // Enqueue main JS first (for Alpine)
        wp_enqueue_script(
            'chatprojects-main',
            CHATPROJECTS_PLUGIN_URL . 'assets/dist/js/main.js',
            array('jquery'),
            CHATPROJECTS_VERSION . '-' . filemtime(CHATPROJECTS_PLUGIN_DIR . 'assets/dist/js/main.js'),
            true
        );

        // Enqueue comparison-specific JavaScript (depends on main.js for Alpine)
        // Note: This file may not exist in Free version
        $comparison_js = CHATPROJECTS_PLUGIN_DIR . 'assets/dist/js/comparison.js';
        if (file_exists($comparison_js)) {
            wp_enqueue_script(
                'chatprojects-comparison',
                CHATPROJECTS_PLUGIN_URL . 'assets/dist/js/comparison.js',
                array('chatprojects-main'),
                CHATPROJECTS_VERSION . '-' . filemtime($comparison_js),
                true
            );
        }

        // Add type="module" attribute to main and comparison scripts
        add_filter('script_loader_tag', function($tag, $handle) {
            if ('chatprojects-main' === $handle || 'chatprojects-comparison' === $handle) {
                if (strpos($tag, 'type="module"') === false && strpos($tag, "type='module'") === false) {
                    $tag = preg_replace('/<script\s/', '<script type="module" ', $tag);
                }
            }
            return $tag;
        }, 10, 2);

        // Localize script data for comparison page
        wp_localize_script('chatprojects-comparison', 'chatprComparisonData', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('chatpr_ajax_nonce'),
            'current_user' => wp_get_current_user()->display_name,
            'default_provider' => get_option('chatprojects_general_chat_provider', 'openai'),
            'default_model' => get_option('chatprojects_general_chat_model', 'gpt-5.2-chat-latest'),
            'strings' => array(
                'sending' => __('Sending...', 'chatprojects'),
                'error' => __('An error occurred. Please try again.', 'chatprojects'),
                'creating_comparison' => __('Creating comparison...', 'chatprojects'),
                'select_providers' => __('Please select providers and models for both sides.', 'chatprojects'),
                'comparison_created' => __('Comparison created successfully.', 'chatprojects'),
                'delete_confirm' => __('Are you sure you want to delete this comparison?', 'chatprojects'),
            ),
        ));

        $template = CHATPROJECTS_PLUGIN_DIR . 'public/templates/pro-comparison.php';

        if (file_exists($template)) {
            include $template;
            exit;
        }
    }

    /**
     * Block Projects Users from accessing wp-admin
     */
    public function block_admin_access() {
        $user = wp_get_current_user();

        // Check if user has chatpr_projects_user role
        if (in_array('chatpr_projects_user', $user->roles)) {
            // Allow AJAX requests
            if (defined('DOING_AJAX') && DOING_AJAX) {
                return;
            }

            // Redirect to projects page (frontend)
            wp_safe_redirect(home_url('/projects/'));
            exit;
        }
    }

    /**
     * Redirect Projects Users to projects page after login or when visiting homepage
     */
    public function redirect_projects_users() {
        // FREE VERSION: Disabled auto-redirect from homepage
        // Users should use the [chatprojects_main] shortcode instead
        // This prevents redirect loops when rewrite rules aren't properly flushed
        return;

        // Original Pro behavior kept for reference:
        // - Redirects chatpr_projects_user role from homepage to /projects/
        // - Not needed in Free version since there's only one shared project
    }

    /**
     * Inject project workspace into homepage for Projects Users
     *
     * FREE VERSION: Disabled - Users should use the [chatprojects_main] shortcode
     * or navigate directly to /projects/, /pro-chat/, etc.
     */
    public function inject_project_workspace($content) {
        // FREE VERSION: Disabled to avoid jQuery conflicts
        // The shortcode [chatprojects_main] provides a navigation hub instead
        return $content;
    }

    /**
     * Check if workspace should be shown automatically
     */
    private function should_show_workspace() {
        if (!is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();
        return in_array('chatpr_projects_user', $user->roles) || current_user_can('manage_options');
    }

    /**
     * Render project workspace
     */
    public function render_workspace() {
        if (!is_user_logged_in()) {
            return '<div class="vp-notice">Please log in to access the workspace.</div>';
        }

        $user_id = get_current_user_id();
        
        // Get user's projects
        $projects = get_posts(array(
            'post_type' => 'chatpr_project',
            'author' => $user_id,
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));

        ob_start();
        
        // Always use the default workspace (don't load project-shell.php for listings)
        $this->render_default_workspace($projects);

        return ob_get_clean();
    }

    /**
     * Render default workspace if template not found
     */
    private function render_default_workspace($projects) {
        ?>
        <div class="vp-workspace-container" style="padding: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h1>ChatProjects Workspace</h1>
                <button id="vp-create-project-btn" class="button button-primary" style="padding: 0.75rem 1.5rem;">
                    Create New Project
                </button>
            </div>
            
            <?php if (empty($projects)): ?>
                <div class="vp-empty-state" style="text-align: center; padding: 3rem; background: white; border-radius: 8px;">
                    <h2>Welcome to ChatProjects!</h2>
                    <p>You don't have any projects yet.</p>
                    <button id="vp-create-first-project-btn" class="button button-primary button-hero">
                        Create Your First Project
                    </button>
                </div>
            <?php else: ?>
                <div class="vp-projects-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem;">
                    <?php foreach ($projects as $project): ?>
                        <div class="vp-project-card" style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                            <h3><?php echo esc_html($project->post_title); ?></h3>
                            <p><?php echo esc_html( wp_trim_words( $project->post_content, 20 ) ); ?></p>
                            <div style="margin-top: 1rem;">
                                <a href="<?php echo esc_url( get_permalink( $project->ID ) ); ?>" class="button button-primary">
                                    Open Project
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Create Project Modal -->
        <div id="vp-create-project-modal" class="vp-modal">
            <div class="vp-modal-content" style="background: white; padding: 2rem; border-radius: 12px; max-width: 600px; width: 90%; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-height: 90vh; overflow-y: auto;">
                <h2 style="margin-top: 0;">Create New Project</h2>
                <form id="vp-create-project-form">
                    <div style="margin-bottom: 1.5rem;">
                        <label for="vp-project-title" style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Project Title</label>
                        <input type="text" id="vp-project-title" name="title" required style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px;">
                    </div>
                    <div style="margin-bottom: 1.5rem;">
                        <label for="vp-project-description" style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Description</label>
                        <textarea id="vp-project-description" name="description" rows="4" style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px;"></textarea>
                    </div>
                    <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                        <button type="button" id="vp-cancel-project-btn" class="button">Cancel</button>
                        <button type="submit" class="button button-primary">Create Project</button>
                    </div>
                </form>
            </div>
        </div>
        <?php
        // Modal styles are in assets/css/templates.css

        // Add modal script via WordPress enqueue system
        $modal_script = "
        jQuery(document).ready(function($) {
            // Open modal
            $('#vp-create-project-btn, #vp-create-first-project-btn').on('click', function() {
                $('#vp-create-project-modal').addClass('open');
            });

            // Close modal
            $('#vp-cancel-project-btn').on('click', function() {
                $('#vp-create-project-modal').removeClass('open');
            });

            // Close on background click
            $('#vp-create-project-modal').on('click', function(e) {
                if (e.target === this) {
                    $(this).removeClass('open');
                }
            });

            // Submit form
            $('#vp-create-project-form').on('submit', function(e) {
                e.preventDefault();

                $.ajax({
                    url: chatprData.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'chatpr_create_project',
                        nonce: chatprData.nonce,
                        title: $('#vp-project-title').val(),
                        description: $('#vp-project-description').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            window.location.reload();
                        } else {
                            alert(response.data.message || 'Failed to create project');
                        }
                    }
                });
            });
        });";

        wp_add_inline_script('jquery', $modal_script);
    }

    /**
     * Render main ChatProjects application as shortcode
     *
     * Usage: [chatprojects_main]
     * Attributes:
     *   - default_tab: projects|chat|settings (default: projects)
     *
     * Note: Due to ES module compatibility issues with shortcodes embedded in pages,
     * this shortcode now renders a navigation hub that links to the full-page views
     * instead of trying to render the full app inline.
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_main_app($atts = array()) {
        // Parse attributes
        $atts = shortcode_atts(array(
            'default_tab' => 'projects',
        ), $atts, 'chatprojects_main');

        // Check if user is logged in
        if (!is_user_logged_in()) {
            return $this->render_login_prompt();
        }

        // Get the default tab for auto-redirect option
        $default_tab = sanitize_key($atts['default_tab']);

        // Build the redirect URL based on default tab
        $redirect_urls = array(
            'projects' => home_url('/projects/'),
            'chat' => home_url('/pro-chat/'),
            'settings' => home_url('/settings/'),
        );

        $redirect_url = isset($redirect_urls[$default_tab]) ? $redirect_urls[$default_tab] : $redirect_urls['projects'];

        // Render navigation hub
        ob_start();
        ?>
        <div class="vp-shortcode-hub" style="max-width: 800px; margin: 2rem auto; padding: 2rem; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;">
            <div style="text-align: center; margin-bottom: 2rem;">
                <svg style="width: 48px; height: 48px; margin: 0 auto 1rem; color: #2563eb;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                <h2 style="margin: 0 0 0.5rem; font-size: 1.5rem; font-weight: 600; color: #111827;">
                    <?php esc_html_e('ChatProjects', 'chatprojects'); ?>
                </h2>
                <p style="margin: 0; color: #6b7280;">
                    <?php esc_html_e('AI-powered project management', 'chatprojects'); ?>
                </p>
            </div>

            <?php
            // Add dashboard card styles via wp_add_inline_style for WP guidelines compliance
            $dashboard_css = '
                .cp-dashboard-card {
                    display: block;
                    padding: 1.5rem;
                    background: #f9fafb;
                    border: 1px solid #e5e7eb;
                    border-radius: 12px;
                    text-decoration: none;
                    text-align: center;
                    transition: all 0.2s;
                }
                .cp-dashboard-card:hover {
                    border-color: #2563eb;
                    box-shadow: 0 4px 12px rgba(37,99,235,0.15);
                }
            ';
            wp_add_inline_style('chatprojects-frontend', $dashboard_css);
            ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <!-- Projects -->
                <a href="<?php echo esc_url(home_url('/projects/')); ?>" class="cp-dashboard-card">
                    <svg style="width: 32px; height: 32px; margin: 0 auto 0.75rem; color: #2563eb;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                    </svg>
                    <div style="font-weight: 600; color: #111827; margin-bottom: 0.25rem;">
                        <?php esc_html_e('Projects', 'chatprojects'); ?>
                    </div>
                    <div style="font-size: 0.875rem; color: #6b7280;">
                        <?php esc_html_e('Manage your AI projects', 'chatprojects'); ?>
                    </div>
                </a>

                <!-- Chat -->
                <a href="<?php echo esc_url(home_url('/pro-chat/')); ?>" class="cp-dashboard-card">
                    <svg style="width: 32px; height: 32px; margin: 0 auto 0.75rem; color: #2563eb;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                    </svg>
                    <div style="font-weight: 600; color: #111827; margin-bottom: 0.25rem;">
                        <?php esc_html_e('AI Chat', 'chatprojects'); ?>
                    </div>
                    <div style="font-size: 0.875rem; color: #6b7280;">
                        <?php esc_html_e('Chat with AI assistants', 'chatprojects'); ?>
                    </div>
                </a>

                <!-- Settings -->
                <a href="<?php echo esc_url(home_url('/settings/')); ?>" class="cp-dashboard-card">
                    <svg style="width: 32px; height: 32px; margin: 0 auto 0.75rem; color: #2563eb;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <div style="font-weight: 600; color: #111827; margin-bottom: 0.25rem;">
                        <?php esc_html_e('Settings', 'chatprojects'); ?>
                    </div>
                    <div style="font-size: 0.875rem; color: #6b7280;">
                        <?php esc_html_e('Configure your preferences', 'chatprojects'); ?>
                    </div>
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render login prompt for non-logged-in users
     *
     * @return string HTML output
     */
    private function render_login_prompt() {
        $login_url = wp_login_url(get_permalink());

        ob_start();
        ?>
        <div class="vp-login-prompt" style="text-align: center; padding: 3rem; background: #f9fafb; border-radius: 12px; border: 1px solid #e5e7eb;">
            <svg style="width: 48px; height: 48px; margin: 0 auto 1rem; color: #6b7280;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
            <h3 style="margin: 0 0 0.5rem; font-size: 1.25rem; font-weight: 600; color: #111827;">
                <?php esc_html_e('Login Required', 'chatprojects'); ?>
            </h3>
            <p style="margin: 0 0 1.5rem; color: #6b7280;">
                <?php esc_html_e('Please log in to access ChatProjects.', 'chatprojects'); ?>
            </p>
            <a href="<?php echo esc_url($login_url); ?>"
               style="display: inline-block; padding: 0.75rem 1.5rem; background: #2563eb; color: white; border-radius: 8px; text-decoration: none; font-weight: 500;">
                <?php esc_html_e('Log In', 'chatprojects'); ?>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render access denied page for users without ChatProjects permissions
     */
    private function render_access_denied_page() {
        // Set proper HTTP status
        status_header(403);
        nocache_headers();

        $style_handle = 'chatprojects-access-denied';
        $style_path = CHATPROJECTS_PLUGIN_DIR . 'assets/css/access-denied.css';
        $style_url = CHATPROJECTS_PLUGIN_URL . 'assets/css/access-denied.css';
        $style_version = defined('CHATPROJECTS_VERSION') ? CHATPROJECTS_VERSION : time();

        if (file_exists($style_path)) {
            $style_version .= '-' . filemtime($style_path);
        }

        wp_enqueue_style(
            $style_handle,
            $style_url,
            array(),
            $style_version
        );

        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php esc_html_e('Access Denied', 'chatprojects'); ?> - <?php bloginfo('name'); ?></title>
            <?php wp_head(); ?>
        </head>
        <body class="chatprojects-access-denied">
            <div class="access-denied-container">
                <svg class="access-denied-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <h1 class="access-denied-title"><?php esc_html_e('Access Denied', 'chatprojects'); ?></h1>
                <p class="access-denied-message">
                    <?php esc_html_e('Your user account does not have permission to access ChatProjects. Please contact an administrator to request access.', 'chatprojects'); ?>
                </p>
                <div class="access-denied-roles">
                    <h4><?php esc_html_e('Required Roles:', 'chatprojects'); ?></h4>
                    <ul>
                        <li><strong><?php esc_html_e('Administrator', 'chatprojects'); ?></strong> - <?php esc_html_e('Full access', 'chatprojects'); ?></li>
                        <li><strong><?php esc_html_e('Editor', 'chatprojects'); ?></strong> - <?php esc_html_e('Full access', 'chatprojects'); ?></li>
                        <li><strong><?php esc_html_e('Author', 'chatprojects'); ?></strong> - <?php esc_html_e('Own projects only', 'chatprojects'); ?></li>
                        <li><strong><?php esc_html_e('Projects User', 'chatprojects'); ?></strong> - <?php esc_html_e('Own projects only', 'chatprojects'); ?></li>
                    </ul>
                </div>
                <a href="<?php echo esc_url(home_url('/')); ?>" class="access-denied-button">
                    <?php esc_html_e('Return to Homepage', 'chatprojects'); ?>
                </a>
            </div>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
    }
}
