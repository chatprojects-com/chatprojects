<?php
/**
 * Main ChatProjects Class (Free Version)
 *
 * Stripped down version without Pro features:
 * - No Transcriber
 * - No Prompt Library
 * - No Comparison Mode
 * - Single shared project for all users
 *
 * @package ChatProjects
 */

namespace ChatProjects;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main ChatProjects Class
 */
class ChatProjects {
    /**
     * Instance of this class
     *
     * @var ChatProjects
     */
    private static $instance = null;

    /**
     * API Handler instance
     *
     * @var API_Handler
     */
    public $api;

    /**
     * Project Manager instance
     *
     * @var Project_Manager
     */
    public $projects;

    /**
     * Vector Store instance
     *
     * @var Vector_Store
     */
    public $vector_store;

    /**
     * Chat Interface instance
     *
     * @var Chat_Interface
     */
    public $chat;

    /**
     * Get singleton instance
     *
     * @return ChatProjects
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get filterable slug configurations
     * Allows developers to customize slugs via filters
     *
     * @return array Slug mappings
     */
    public static function get_slugs() {
        return array(
            'projects'   => apply_filters('chatprojects_slug_projects', 'chatprojects'),
            'settings'   => apply_filters('chatprojects_slug_settings', 'cp-settings'),
            'chat'       => apply_filters('chatprojects_slug_chat', 'cp-chat'),
        );
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->load_dependencies();
        $this->init_components();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Translations are loaded automatically by WordPress 4.6+ for plugins hosted on wordpress.org

        // Initialize admin
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_notices', array($this, 'show_slug_conflict_notice'));
        add_action('admin_notices', array($this, 'show_slug_migration_notice'));
        add_action('admin_notices', array($this, 'show_model_migration_notice'));

        // Initialize frontend
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('init', array($this, 'register_post_types'));

        // Projects are private workspaces: keep them out of sitemaps and embeds.
        add_filter('wp_sitemaps_post_types', array($this, 'exclude_projects_from_sitemaps'));
        add_action('template_redirect', array($this, 'block_project_embeds'));
        add_filter('oembed_request_post_id', array($this, 'block_project_oembed'));

        // Add type="module" attribute to our scripts (must be registered early)
        add_filter('script_loader_tag', array($this, 'add_module_type_to_scripts'), 10, 2);

        // Add plugin action links
        if (defined('CHATPROJECTS_PLUGIN_BASENAME')) {
            add_filter('plugin_action_links_' . CHATPROJECTS_PLUGIN_BASENAME, array($this, 'add_action_links'));
        }

        // Block admin project creation (free version - projects created via frontend only)
        add_action('admin_enqueue_scripts', array($this, 'hide_add_new_button'));
        add_action('load-post-new.php', array($this, 'redirect_from_new_project'));

        // Theme initialization is handled directly in project-shell-modern.php template
        // add_action('wp_head', array($this, 'output_theme_init_script'), 1);
    }

    /**
     * Remove projects from the core XML sitemaps.
     *
     * @param array $post_types Post type objects keyed by name.
     * @return array
     */
    public function exclude_projects_from_sitemaps($post_types) {
        unset($post_types['chatpr_project']);
        return $post_types;
    }

    /**
     * Projects have no public embed; core's embed template would show their
     * title and description to anyone.
     */
    public function block_project_embeds() {
        if (is_embed() && is_singular('chatpr_project')) {
            global $wp_query;
            $wp_query->set_404(); // Also clears is_embed, so the theme's 404 template is used.
            status_header(404);
            nocache_headers();
        }
    }

    /**
     * The oEmbed API (/wp-json/oembed/1.0/embed) would return a project's title
     * and author to anyone; treat projects as not embeddable.
     *
     * @param int $post_id Post ID resolved from the requested URL.
     * @return int
     */
    public function block_project_oembed($post_id) {
        return 'chatpr_project' === get_post_type($post_id) ? 0 : $post_id;
    }

    /**
     * Output theme initialization script via wp_head
     * This runs before body to prevent flash of unstyled content
     * Output directly (not via wp_add_inline_script) to ensure it runs IMMEDIATELY
     */
    public function output_theme_init_script() {
        // Only output on ChatProjects pages
        if (!is_singular('chatpr_project') && !$this->is_chatprojects_page()) {
            return;
        }

        // Output script directly in head for immediate execution (prevents FOUC)
        ?>
        <script>
        (function() {
            var theme = localStorage.getItem('cp_theme_preference');
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
            } else if (theme === 'auto') {
                if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            } else if (theme === 'light') {
                document.documentElement.classList.remove('dark');
            }
            // If no theme set, leave as server-rendered
        })();
        </script>
        <?php
    }

    /**
     * Check if current page is a ChatProjects custom page
     */
    private function is_chatprojects_page() {
        $chatpr_page = get_query_var('chatpr_page');
        return !empty($chatpr_page);
    }

    /**
     * Add type="module" attribute to our scripts
     */
    public function add_module_type_to_scripts($tag, $handle) {
        $module_handles = array(
            'chatprojects-admin',
            'chatprojects-frontend',
            'chatprojects-main',
        );

        $is_module = in_array($handle, $module_handles, true)
            || 'module' === wp_scripts()->get_data($handle, 'type');

        if (!$is_module) {
            return $tag;
        }

        // $tag can also contain the handle's inline "before"/"after" scripts and
        // translations. Only the external <script src> is the module; marking the
        // first <script> instead leaves main.js as a classic script, which then
        // fails on its import statements.
        $processor = new \WP_HTML_Tag_Processor($tag);
        while ($processor->next_tag('script')) {
            if (null !== $processor->get_attribute('src')) {
                $processor->set_attribute('type', 'module');
            }
        }

        return $processor->get_updated_html();
    }

    /**
     * Translatable strings used by the bundled JavaScript.
     *
     * @return array
     */
    public static function frontend_i18n() {
        return array(
            'filesImportedSuccess'      => __('Files imported successfully.', 'chatprojects'),
            'filesImportFailed'         => __('Failed to import files.', 'chatprojects'),
            'imageProcessError'         => __('Could not process that image.', 'chatprojects'),
            'imageTooLarge'             => __('Image is too large.', 'chatprojects'),
            'importSelectedFiles'       => __('Import selected files', 'chatprojects'),
            'invalidImageType'          => __('Unsupported image type.', 'chatprojects'),
            'maxImagesReached'          => __('Maximum number of images reached.', 'chatprojects'),
            'selectFilesForVectorStore' => __('Select files to add to this project', 'chatprojects'),
            'sending'                   => __('Sending...', 'chatprojects'),
            'error'                     => __('An error occurred. Please try again.', 'chatprojects'),
            'newChat'                   => __('New Chat', 'chatprojects'),
        );
    }

    /**
     * Cache-busting version string for a plugin asset.
     *
     * @param string $relative_path Path relative to the plugin directory.
     * @return string
     */
    public static function asset_version($relative_path) {
        $path = CHATPROJECTS_PLUGIN_DIR . ltrim($relative_path, '/');
        $mtime = file_exists($path) ? (int) filemtime($path) : 0;
        return $mtime ? CHATPROJECTS_VERSION . '-' . $mtime : CHATPROJECTS_VERSION;
    }

    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        // Core classes
        require_once CHATPROJECTS_PLUGIN_DIR . 'includes/class-model-registry.php';
        require_once CHATPROJECTS_PLUGIN_DIR . 'includes/class-api-handler.php';
        require_once CHATPROJECTS_PLUGIN_DIR . 'includes/class-project-manager.php';
        require_once CHATPROJECTS_PLUGIN_DIR . 'includes/class-vector-store.php';
        require_once CHATPROJECTS_PLUGIN_DIR . 'includes/class-chat-interface.php';
        require_once CHATPROJECTS_PLUGIN_DIR . 'includes/class-user-roles.php';
        require_once CHATPROJECTS_PLUGIN_DIR . 'includes/class-security.php';
        require_once CHATPROJECTS_PLUGIN_DIR . 'includes/class-access.php';
        require_once CHATPROJECTS_PLUGIN_DIR . 'includes/class-content-indexer.php';
        require_once CHATPROJECTS_PLUGIN_DIR . 'includes/class-rate-limiter.php';
        require_once CHATPROJECTS_PLUGIN_DIR . 'includes/class-widget-handler.php';

        // Provider classes - All 5 providers available in Free version
        require_once CHATPROJECTS_PLUGIN_DIR . 'includes/providers/interface-ai-provider.php';
        require_once CHATPROJECTS_PLUGIN_DIR . 'includes/providers/class-base-provider.php';
        require_once CHATPROJECTS_PLUGIN_DIR . 'includes/providers/class-openai-provider.php';
        require_once CHATPROJECTS_PLUGIN_DIR . 'includes/providers/class-gemini-provider.php';
        require_once CHATPROJECTS_PLUGIN_DIR . 'includes/providers/class-anthropic-provider.php';
        require_once CHATPROJECTS_PLUGIN_DIR . 'includes/providers/class-chutes-provider.php';
        require_once CHATPROJECTS_PLUGIN_DIR . 'includes/providers/class-openrouter-provider.php';

        // Admin classes
        require_once CHATPROJECTS_PLUGIN_DIR . 'admin/class-settings.php';
        require_once CHATPROJECTS_PLUGIN_DIR . 'admin/class-metaboxes.php';

        // Frontend classes
        require_once CHATPROJECTS_PLUGIN_DIR . 'public/class-frontend.php';
        require_once CHATPROJECTS_PLUGIN_DIR . 'public/ajax-handlers.php';
        require_once CHATPROJECTS_PLUGIN_DIR . 'includes/class-general-chat-ajax.php';

        // Note: Removed in Free version:
        // - class-transcriber.php
        // - class-prompt-library.php
        // - class-comparison-interface.php
        // - class-comparison-ajax.php
    }

    /**
     * Initialize plugin components
     */
    private function init_components() {
        // Initialize API handler first
        $this->api = new API_Handler();

        // Initialize other components
        $this->projects = new Project_Manager();
        $this->vector_store = new Vector_Store();
        $this->chat = new Chat_Interface();

        // Content Indexer for Auto-RAG
        new Content_Indexer();

        // Widget handler for public-facing chat widget
        new Widget_Handler();

        // Note: Removed in Free version:
        // $this->transcriber = new Transcriber();
        // $this->prompts = new Prompt_Library();

        // Initialize user roles
        User_Roles::init();

        // Initialize admin
        if (is_admin()) {
            new Admin\Settings();
            new Admin\Metaboxes();
        }

        // Initialize frontend
        new Frontend();
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('ChatProjects', 'chatprojects'),
            __('ChatProjects', 'chatprojects'),
            'manage_options',
            'chatprojects',
            array($this, 'render_main_page'),
            'dashicons-share-alt2',
            30
        );

        // Projects submenu (redirects to custom post type)
        add_submenu_page(
            'chatprojects',
            __('Projects', 'chatprojects'),
            __('Projects', 'chatprojects'),
            'edit_posts',
            'edit.php?post_type=chatpr_project'
        );

        // Settings submenu (last in menu)
        add_submenu_page(
            'chatprojects',
            __('Settings', 'chatprojects'),
            __('Settings', 'chatprojects'),
            'manage_options',
            'chatprojects-settings',
            array($this, 'render_settings_page')
        );

        // Note: Prompts submenu is a Pro feature
    }

    /**
     * Render main page
     */
    public function render_main_page() {
        include CHATPROJECTS_PLUGIN_DIR . 'admin/views/main-page.php';
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        include CHATPROJECTS_PLUGIN_DIR . 'admin/views/settings-page.php';
    }

    /**
     * Register plugin settings with WordPress Settings API.
     *
     * Registers option names so WordPress allows them to be saved via options.php.
     * Sections and fields are handled by Admin\Settings::register_settings().
     */
    public function register_settings() {
        // API keys (needed for options.php allowed_options whitelist).
        $api_keys = array(
            'chatprojects_openai_key',
            'chatprojects_gemini_key',
            'chatprojects_anthropic_key',
            'chatprojects_chutes_key',
            'chatprojects_openrouter_key',
        );
        foreach ( $api_keys as $key ) {
            register_setting(
                'chatprojects_settings',
                $key,
                array(
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'default'           => '',
                )
            );
        }

        // General settings.
        register_setting( 'chatprojects_settings', 'chatprojects_general_chat_provider', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'openai',
        ) );
        register_setting( 'chatprojects_settings', 'chatprojects_general_chat_model', array(
            'type'              => 'string',
            'sanitize_callback' => array( Model_Registry::class, 'sanitize_any_model' ),
            'default'           => Model_Registry::get_default( 'openai' ),
        ) );
        register_setting( 'chatprojects_settings', 'chatprojects_assistant_instructions', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default'           => '',
        ) );
        register_setting( 'chatprojects_settings', 'chatprojects_default_model', array(
            'type'              => 'string',
            'sanitize_callback' => array( Model_Registry::class, 'sanitize_openai_model' ),
            'default'           => Model_Registry::get_default( 'openai' ),
        ) );
        // chatprojects_max_file_size and chatprojects_allowed_file_types are
        // registered (with their sanitizers) by Admin\Settings.
    }

    /**
     * Register post types
     */
    public function register_post_types() {
        // Register Projects post type
        register_post_type('chatpr_project', array(
            'labels' => array(
                'name' => __('Projects', 'chatprojects'),
                'singular_name' => __('Project', 'chatprojects'),
                'add_new' => __('Add New', 'chatprojects'),
                'add_new_item' => __('Add New Project', 'chatprojects'),
                'edit_item' => __('Edit Project', 'chatprojects'),
                'new_item' => __('New Project', 'chatprojects'),
                'view_item' => __('View Project', 'chatprojects'),
                'search_items' => __('Search Projects', 'chatprojects'),
                'not_found' => __('No projects found', 'chatprojects'),
                'not_found_in_trash' => __('No projects found in trash', 'chatprojects'),
            ),
            'public' => true,
            'exclude_from_search' => true,
            'has_archive' => false,
            'show_in_menu' => false,
            'show_in_rest' => false, // Disable block editor to prevent canvas template
            'supports' => array('title', 'editor', 'author'),
            'rewrite' => array(
                'slug' => 'chatpr_project',
                'with_front' => false,
            ),
            'capability_type' => 'chatpr_project',
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
            'map_meta_cap' => true,
        ));

        // Note: Prompts post type removed in Free version
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'chatprojects') === false && get_post_type() !== 'chatpr_project') {
            return;
        }

        wp_enqueue_style(
            'chatprojects-admin',
            CHATPROJECTS_PLUGIN_URL . 'assets/dist/css/main.css',
            array(),
            self::asset_version('assets/dist/css/main.css')
        );

        wp_enqueue_script(
            'chatprojects-admin',
            CHATPROJECTS_PLUGIN_URL . 'assets/dist/js/admin.js',
            array('jquery'),
            self::asset_version('assets/dist/js/admin.js'),
            true
        );

        wp_localize_script('chatprojects-admin', 'chatprData', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('chatpr_admin_nonce'),
        ));
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts() {
        // Check if we're on a ChatProjects page
        if (!is_singular('chatpr_project') && !is_post_type_archive('chatpr_project') && !$this->is_chatprojects_page()) {
            return;
        }

        // Ensure jQuery is loaded
        wp_enqueue_script('jquery');

        // Main compiled CSS
        wp_enqueue_style(
            'chatprojects-frontend',
            CHATPROJECTS_PLUGIN_URL . 'assets/dist/css/main.css',
            array(),
            \ChatProjects\ChatProjects::asset_version('assets/dist/css/main.css')
        );

        // Template-specific CSS (previously inline styles)
        $templates_css_path = CHATPROJECTS_PLUGIN_DIR . 'assets/css/templates.css';
        wp_enqueue_style(
            'chatprojects-templates',
            CHATPROJECTS_PLUGIN_URL . 'assets/css/templates.css',
            array('chatprojects-frontend'),
            \ChatProjects\ChatProjects::asset_version(str_replace(CHATPROJECTS_PLUGIN_DIR, '', $templates_css_path))
        );

        wp_enqueue_script(
            'chatprojects-frontend',
            CHATPROJECTS_PLUGIN_URL . 'assets/dist/js/main.js',
            array('jquery'),
            \ChatProjects\ChatProjects::asset_version('assets/dist/js/main.js'),
            true
        );

        // Note: type="module" attribute is added globally via add_module_type_to_scripts()

        wp_localize_script('chatprojects-frontend', 'chatprData', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('chatpr_ajax_nonce'),
            'plugin_url' => CHATPROJECTS_PLUGIN_URL,
            'is_pro' => false, // Always false in Free version
            'is_pro_user' => false,
            'max_images_per_message' => 1,
            'max_image_size' => Security::get_max_image_upload_size(),
            'default_provider' => get_option('chatprojects_general_chat_provider', 'openai'),
            'default_model' => get_option('chatprojects_general_chat_model', Model_Registry::get_default(get_option('chatprojects_general_chat_provider', 'openai'))),
            'i18n' => self::frontend_i18n(),
        ));

        // Enqueue instructions modal script (jQuery-based)
        $modal_js_path = CHATPROJECTS_PLUGIN_DIR . 'assets/js/instructions-modal.js';
        wp_enqueue_script(
            'chatprojects-instructions-modal',
            CHATPROJECTS_PLUGIN_URL . 'assets/js/instructions-modal.js',
            array('jquery', 'chatprojects-frontend'), // Depends on jQuery and frontend script (for chatprData)
            \ChatProjects\ChatProjects::asset_version(str_replace(CHATPROJECTS_PLUGIN_DIR, '', $modal_js_path)),
            true // Load in footer
        );

        // Enqueue WordPress Media Library for file picker
        wp_enqueue_media();

        // Add CSS overrides to fix media library display conflicts
        // Match Pro version exactly
        $media_library_css = '
            /* Fix WordPress Media Library display in ChatProjects */
            .media-modal,
            .media-modal * {
                box-sizing: content-box !important;
            }
            .media-modal .attachments-browser .attachments {
                display: flex !important;
                flex-wrap: wrap !important;
                overflow: auto !important;
                visibility: visible !important;
                opacity: 1 !important;
            }
            .media-modal .attachment {
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
                width: 150px !important;
                height: 150px !important;
                margin: 8px !important;
                box-sizing: border-box !important;
            }
            .media-modal .attachment .thumbnail {
                display: block !important;
                visibility: visible !important;
                width: 100% !important;
                height: 100% !important;
            }
            .media-modal .attachment .thumbnail img {
                display: block !important;
                max-width: 100% !important;
                max-height: 100% !important;
            }
            .media-modal .attachment-preview {
                display: block !important;
                visibility: visible !important;
            }
            .media-modal .attachments-browser {
                overflow: visible !important;
            }
            .media-modal ul.attachments {
                list-style: none !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .media-modal li.attachment {
                float: left !important;
            }
            /* Fix dropdown cutoff in media toolbar */
            .media-modal .media-toolbar,
            .media-modal .media-toolbar-secondary,
            .media-modal .media-toolbar-primary {
                overflow: visible !important;
            }
            .media-modal .attachment-filters {
                max-width: 200px !important;
            }
            .media-modal .media-frame-content {
                overflow: visible !important;
            }
        ';
        wp_add_inline_style('media-views', $media_library_css);

        // Enqueue Media Library picker script
        $media_picker_js_path = CHATPROJECTS_PLUGIN_DIR . 'assets/js/media-library-picker.js';
        if (file_exists($media_picker_js_path)) {
            wp_enqueue_script(
                'chatprojects-media-library-picker',
                CHATPROJECTS_PLUGIN_URL . 'assets/js/media-library-picker.js',
                array('chatprojects-frontend'),
                \ChatProjects\ChatProjects::asset_version(str_replace(CHATPROJECTS_PLUGIN_DIR, '', $media_picker_js_path)),
                true
            );
        }

        // Override formatDate to use simple date format instead of relative dates
        // Must run BEFORE main.js so the listener is registered before Alpine.start()
        $date_format_override = '
            document.addEventListener("alpine:init", function() {
                // Override fileManager to use simple date format
                var originalFileManagerFactory = null;
                var originalData = window.Alpine.data.bind(window.Alpine);

                window.Alpine.data = function(name, factory) {
                    if (name === "fileManager" && factory) {
                        // Wrap the factory to override formatDate
                        var wrappedFactory = function(projectId) {
                            var component = factory(projectId);
                            // Override formatDate to show simple date in local timezone
                            component.formatDate = function(date) {
                                if (!date) return "";
                                var d = new Date(date);
                                if (isNaN(d.getTime())) return "";
                                return d.toLocaleDateString("en-US", {
                                    year: "numeric",
                                    month: "short",
                                    day: "numeric",
                                    hour: "numeric",
                                    minute: "2-digit"
                                });
                            };
                            return component;
                        };
                        return originalData(name, wrappedFactory);
                    }
                    return originalData(name, factory);
                };
            });
        ';
        wp_add_inline_script('chatprojects-frontend', $date_format_override, 'before');
    }

    /**
     * Add plugin action links
     */
    public function add_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=chatprojects-settings') . '">' . __('Settings', 'chatprojects') . '</a>';
        $upgrade_link = '<a href="https://chatprojects.com/" target="_blank" style="color: #f0b849; font-weight: bold;">' . __('Go Pro', 'chatprojects') . '</a>';

        array_unshift($links, $settings_link);
        array_push($links, $upgrade_link);

        return $links;
    }

    /**
     * Hide "Add New" button on projects list page (free version)
     */
    public function hide_add_new_button() {
        global $typenow;
        if ($typenow === 'chatpr_project') {
            wp_add_inline_style('chatprojects-admin', '.page-title-action { display: none !important; }');
        }
    }

    /**
     * Redirect from post-new.php for projects to enforce frontend-only creation
     */
    public function redirect_from_new_project() {
        global $typenow;
        if ($typenow === 'chatpr_project') {
            wp_safe_redirect(admin_url('edit.php?post_type=chatpr_project'));
            exit;
        }
    }

    /**
     * Show admin notice if there are URL slug conflicts
     */
    public function show_slug_conflict_notice() {
        $conflicts = get_option('chatprojects_slug_conflicts');

        if (empty($conflicts) || !is_array($conflicts)) {
            return;
        }

        // Re-check if conflicts still exist (user may have fixed them)
        $still_conflicting = array();
        foreach ($conflicts as $conflict) {
            $page = get_page_by_path($conflict['slug']);
            if ($page && $page->post_status === 'publish') {
                $still_conflicting[] = $conflict;
            }
        }

        // Update or clear the option based on current state
        if (empty($still_conflicting)) {
            delete_option('chatprojects_slug_conflicts');
            return;
        }

        update_option('chatprojects_slug_conflicts', $still_conflicting);

        echo '<div class="notice notice-warning">';
        echo '<p><strong>' . esc_html__('ChatProjects:', 'chatprojects') . '</strong> ';
        echo esc_html__('The following pages have URLs that conflict with plugin pages:', 'chatprojects') . '</p>';
        echo '<ul style="margin-left: 20px; list-style: disc;">';

        foreach ($still_conflicting as $conflict) {
            $edit_link = get_edit_post_link($conflict['page_id']);
            echo '<li>';
            echo '"<strong>' . esc_html($conflict['title']) . '</strong>" ';
            echo '(/' . esc_html($conflict['slug']) . ') &mdash; ';
            if ($edit_link) {
                echo '<a href="' . esc_url($edit_link) . '">' . esc_html__('Edit page', 'chatprojects') . '</a>';
            }
            echo '</li>';
        }

        echo '</ul>';
        echo '<p>' . esc_html__('Please change these page slugs to avoid conflicts. The original pages are currently inaccessible at their URLs.', 'chatprojects') . '</p>';
        echo '</div>';
    }

    /**
     * Show admin notice after slug migration
     */
    public function show_slug_migration_notice() {
        if (!get_transient('chatprojects_slug_migration_notice')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $slugs = self::get_slugs();
        ?>
        <div class="notice notice-info is-dismissible">
            <h3><?php esc_html_e('ChatProjects URLs Updated', 'chatprojects'); ?></h3>
            <p><?php esc_html_e('ChatProjects has updated its URL structure to avoid conflicts with existing pages:', 'chatprojects'); ?></p>
            <ul style="list-style: disc; margin-left: 2em;">
                <li><strong><?php esc_html_e('Projects:', 'chatprojects'); ?></strong> <?php echo esc_html(home_url('/' . $slugs['projects'] . '/')); ?></li>
                <li><strong><?php esc_html_e('Chat:', 'chatprojects'); ?></strong> <?php echo esc_html(home_url('/' . $slugs['chat'] . '/')); ?></li>
                <li><strong><?php esc_html_e('Settings:', 'chatprojects'); ?></strong> <?php echo esc_html(home_url('/' . $slugs['settings'] . '/')); ?></li>
            </ul>
            <p><?php esc_html_e('Old URLs will automatically redirect to the new locations. Please update your bookmarks.', 'chatprojects'); ?></p>
        </div>
        <?php

        delete_transient('chatprojects_slug_migration_notice');
    }

    /**
     * Show admin notice after retired model IDs were remapped on upgrade
     */
    public function show_model_migration_notice() {
        $summary = get_transient('chatprojects_model_migration_notice');
        if (empty($summary) || !is_array($summary)) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $map = isset($summary['map']) && is_array($summary['map']) ? $summary['map'] : array();
        ?>
        <div class="notice notice-info is-dismissible">
            <h3><?php esc_html_e('ChatProjects AI models updated', 'chatprojects'); ?></h3>
            <p><?php esc_html_e('Some AI models you were using have been retired by their providers. Your settings, projects and chats were moved to the closest current model:', 'chatprojects'); ?></p>
            <?php if (!empty($map)) : ?>
            <ul style="list-style: disc; margin-left: 2em;">
                <?php foreach ($map as $old => $new) : ?>
                    <li><code><?php echo esc_html($old); ?></code> &rarr; <code><?php echo esc_html($new); ?></code></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=chatprojects-settings')); ?>"><?php esc_html_e('Review model settings', 'chatprojects'); ?></a>
            </p>
        </div>
        <?php

        delete_transient('chatprojects_model_migration_notice');
    }
}
