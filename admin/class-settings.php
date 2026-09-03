<?php
/**
 * Settings Class
 *
 * Handles admin settings page
 *
 * @package ChatProjects
 */

namespace ChatProjects\Admin;

use ChatProjects\Model_Registry;
use ChatProjects\Security;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings Class
 */
class Settings {
    /**
     * Singleton instance for fallback access.
     *
     * @var Settings|null
     */
    private static $instance = null;

    /**
     * Tracks intentional saves to prevent filter from blocking our own updates
     *
     * @var string|null
     */
    private $intentional_save = null;

    /**
     * Get the Settings instance.
     *
     * @return Settings|null
     */
    public static function get_instance() {
        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        self::$instance = $this;
        // Ensure encryption key exists BEFORE any settings can be saved
        $this->ensure_encryption_key();

        // Intercept API key saves BEFORE WordPress Settings API processes them
        add_action('admin_init', array($this, 'intercept_api_key_save'), 1);

        add_action('admin_init', array($this, 'register_settings'));

        // Protection: Block unwanted overwrites of API keys with empty values
        add_filter('pre_update_option_chatprojects_openai_key', array($this, 'protect_api_key_update'), 10, 2);
        add_filter('pre_update_option_chatprojects_chutes_key', array($this, 'protect_api_key_update'), 10, 2);
        add_filter('pre_update_option_chatprojects_gemini_key', array($this, 'protect_api_key_update'), 10, 2);
        add_filter('pre_update_option_chatprojects_anthropic_key', array($this, 'protect_api_key_update'), 10, 2);
        add_filter('pre_update_option_chatprojects_openrouter_key', array($this, 'protect_api_key_update'), 10, 2);
        add_action('admin_notices', array($this, 'show_notices'));
    }

    /**
     * Protect API keys from being accidentally overwritten with empty values
     *
     * This prevents WordPress Settings API or other code from clearing saved keys
     * while allowing intentional clears and our own saves through.
     *
     * @param mixed $value New value
     * @param mixed $old_value Old value
     * @return mixed The value (possibly kept as old value to prevent overwrite)
     */
    public function protect_api_key_update($value, $old_value) {
        // Get the option name from the filter hook
        $current_filter = current_filter();
        $option = str_replace('pre_update_option_', '', $current_filter);

        // Allow our own intentional saves through
        if ($this->intentional_save === $option) {
            return $value;
        }

        // Block empty overwrites if old value looks like an encrypted key
        if (empty($value) && !empty($old_value) && strlen($old_value) > 50) {
            return $old_value;
        }

        return $value;
    }

    /**
     * Intercept API key saves before WordPress Settings API
     *
     * This runs at priority 1 on admin_init, before WordPress processes options.php
     * We manually save the API keys and remove them from $_POST so WordPress doesn't double-process
     */
    public function intercept_api_key_save() {
        // Only run on options.php POST (settings save)
        if (!isset($_POST['option_page']) || $_POST['option_page'] !== 'chatprojects_settings') {
            return;
        }

        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'chatprojects_settings-options')) {
            return;
        }

        // Check user capability
        if (!current_user_can('manage_options')) {
            return;
        }

        $api_key_fields = array(
            'chatprojects_openai_key',
            'chatprojects_gemini_key',
            'chatprojects_anthropic_key',
            'chatprojects_chutes_key',
            'chatprojects_openrouter_key',
        );

        $valid_prefixes = array('sk-', 'sk-proj-', 'AIza', 'sk-ant-', 'cpat_', 'cpk_', 'sk-or-');

        foreach ($api_key_fields as $field) {
            if (!isset($_POST[ $field ])) {
                continue;
            }

            $value = sanitize_text_field(wp_unslash($_POST[ $field ]));

            // Explicit "remove key" checkbox clears the stored key.
            if (!empty($_POST[ $field . '_clear' ])) {
                $this->intentional_save = $field;
                update_option($field, '');
                $this->intentional_save = null;
                unset($_POST[ $field ]);
                continue;
            }

            // The field is rendered empty (the key is never sent to the browser), so an
            // empty submission means "unchanged".
            if (empty($value)) {
                unset($_POST[ $field ]);
                continue;
            }

            // Check if it's a valid API key (not garbage)
            $has_valid_prefix = false;
            foreach ($valid_prefixes as $prefix) {
                if (strpos($value, $prefix) === 0) {
                    $has_valid_prefix = true;
                    break;
                }
            }

            if (!$has_valid_prefix) {
                // Invalid input - keep old value
                unset($_POST[ $field ]);
                continue;
            }

            // Encrypt the value
            $encrypted = Security::encrypt($value);
            if ($encrypted !== false) {
                // Mark that we're doing an intentional save
                $this->intentional_save = $field;

                // Delete existing value to ensure clean state
                delete_option($field);

                // Clear caches
                wp_cache_delete($field, 'options');
                wp_cache_delete('alloptions', 'options');
                wp_cache_flush();

                // Add the option fresh
                $save_result = add_option($field, $encrypted, '', 'yes');

                if (!$save_result) {
                    // If add_option failed, try update
                    update_option($field, $encrypted, 'yes');
                }

                // Clear intentional save flag
                $this->intentional_save = null;

                // Clear caches after save
                wp_cache_delete($field, 'options');
                wp_cache_delete('alloptions', 'options');

                // Remove from POST so WordPress Settings API doesn't touch this option
                unset($_POST[ $field ]);
            }
        }
    }

    /**
     * Encrypt API key before WordPress saves it
     *
     * @param mixed $value New option value
     * @param mixed $old_value Old option value
     * @return string Encrypted value or empty string
     */
    public function encrypt_api_key_on_save($value, $old_value) {
        // If empty, return empty
        if (empty($value)) {
            return '';
        }

        // If value is already encrypted (looks like base64), return as-is
        // This happens when the sanitize_callback already encrypted it
        if (preg_match('/^[A-Za-z0-9+\/=]{40,}$/', $value) && strpos($value, 'sk-') !== 0) {
            return $value;
        }

        // Validate input looks like a real API key
        $valid_prefixes = array('sk-', 'sk-proj-', 'AIza', 'sk-ant-', 'cpat_', 'cpk_', 'sk-or-');
        $has_valid_prefix = false;
        foreach ($valid_prefixes as $prefix) {
            if (strpos($value, $prefix) === 0) {
                $has_valid_prefix = true;
                break;
            }
        }

        if (!$has_valid_prefix) {
            // Not a valid API key - return old value or empty
            return !empty($old_value) ? $old_value : '';
        }

        // Encrypt and return
        $encrypted = Security::encrypt($value);
        if ($encrypted === false) {
            return !empty($old_value) ? $old_value : '';
        }

        return $encrypted;
    }

    /**
     * Ensure encryption key exists in database
     *
     * Must be called before any API keys are encrypted/decrypted
     * to prevent race condition where key is generated during save
     */
    private function ensure_encryption_key() {
        // Only generate fallback key if AUTH_KEY won't be used
        if (defined('AUTH_KEY') && AUTH_KEY !== 'put your unique phrase here' && !empty(AUTH_KEY)) {
            return; // AUTH_KEY will be used, no need for stored key
        }

        // Generate and store encryption key if it doesn't exist
        if (get_option('chatprojects_encryption_key') === false) {
            $key = bin2hex(random_bytes(16));
            update_option('chatprojects_encryption_key', $key);
        }
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // Register all settings with WordPress Settings API
        // NOTE: API keys are NOT registered here - they are handled entirely by
        // intercept_api_key_save() to avoid WordPress Settings API overwriting our values.
        // We use direct $wpdb writes for API keys.

        // Chat settings
        register_setting(
            'chatprojects_settings',
            'chatprojects_general_chat_provider',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'openai',
            )
        );

        register_setting(
            'chatprojects_settings',
            'chatprojects_general_chat_model',
            array(
                'type' => 'string',
                'sanitize_callback' => array(Model_Registry::class, 'sanitize_any_model'),
                'default' => Model_Registry::get_default('openai'),
            )
        );

        register_setting(
            'chatprojects_settings',
            'chatprojects_assistant_instructions',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
                'default' => 'You are a helpful AI assistant.',
            )
        );

        // Project settings
        register_setting(
            'chatprojects_settings',
            'chatprojects_default_model',
            array(
                'type' => 'string',
                'sanitize_callback' => array(Model_Registry::class, 'sanitize_openai_model'),
                'default' => Model_Registry::get_default('openai'),
            )
        );

        // Data retention on uninstall
        register_setting(
            'chatprojects_settings',
            'chatprojects_keep_data_on_uninstall',
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => false,
            )
        );

        // File settings
        register_setting(
            'chatprojects_settings',
            'chatprojects_max_file_size',
            array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'default' => 50,
            )
        );

        register_setting(
            'chatprojects_settings',
            'chatprojects_allowed_file_types',
            array(
                'type' => 'array',
                'sanitize_callback' => array($this, 'sanitize_file_types'),
                'default' => array('pdf', 'doc', 'docx', 'txt', 'md', 'csv', 'json', 'xml'),
            )
        );

        // ==================== TAB: API Keys ====================
        add_settings_section(
            'chatprojects_providers_settings',
            __('AI Provider API Keys', 'chatprojects'),
            array($this, 'render_providers_settings_section'),
            'chatprojects-tab-api'
        );

        // OpenAI API Key (Required)
        add_settings_field(
            'chatprojects_openai_key',
            __('OpenAI API Key', 'chatprojects') . ' <span style="color: #dc2626;">*</span>',
            array($this, 'render_openai_key_field'),
            'chatprojects-tab-api',
            'chatprojects_providers_settings'
        );

        // Google Gemini API Key (Optional)
        add_settings_field(
            'chatprojects_gemini_key',
            __('Google Gemini API Key', 'chatprojects') . ' <span style="color: #6b7280; font-weight: normal; font-size: 12px;">(' . __('Optional', 'chatprojects') . ')</span>',
            array($this, 'render_gemini_key_field'),
            'chatprojects-tab-api',
            'chatprojects_providers_settings'
        );

        // Anthropic API Key (Optional)
        add_settings_field(
            'chatprojects_anthropic_key',
            __('Anthropic API Key', 'chatprojects') . ' <span style="color: #6b7280; font-weight: normal; font-size: 12px;">(' . __('Optional', 'chatprojects') . ')</span>',
            array($this, 'render_anthropic_key_field'),
            'chatprojects-tab-api',
            'chatprojects_providers_settings'
        );

        // Chutes.ai API Key (Optional)
        add_settings_field(
            'chatprojects_chutes_key',
            __('Chutes.ai API Key', 'chatprojects') . ' <span style="color: #6b7280; font-weight: normal; font-size: 12px;">(' . __('Optional', 'chatprojects') . ')</span>',
            array($this, 'render_chutes_key_field'),
            'chatprojects-tab-api',
            'chatprojects_providers_settings'
        );

        // OpenRouter API Key (Optional)
        add_settings_field(
            'chatprojects_openrouter_key',
            __('OpenRouter API Key', 'chatprojects') . ' <span style="color: #6b7280; font-weight: normal; font-size: 12px;">(' . __('Optional', 'chatprojects') . ')</span>',
            array($this, 'render_openrouter_key_field'),
            'chatprojects-tab-api',
            'chatprojects_providers_settings'
        );

        // ==================== TAB: Chat ====================
        add_settings_section(
            'chatprojects_general_chat_settings',
            __('General Chat', 'chatprojects'),
            array($this, 'render_general_chat_settings_section'),
            'chatprojects-tab-chat'
        );

        // Default Provider for General Chat
        add_settings_field(
            'chatprojects_general_chat_provider',
            __('Default Provider', 'chatprojects'),
            array($this, 'render_general_chat_provider_field'),
            'chatprojects-tab-chat',
            'chatprojects_general_chat_settings'
        );

        // Default Model for General Chat
        add_settings_field(
            'chatprojects_general_chat_model',
            __('Default Model', 'chatprojects'),
            array($this, 'render_general_chat_model_field'),
            'chatprojects-tab-chat',
            'chatprojects_general_chat_settings'
        );

        // Assistant Instructions for General Chat
        add_settings_field(
            'chatprojects_assistant_instructions',
            __('Assistant Instructions', 'chatprojects'),
            array($this, 'render_assistant_instructions_field'),
            'chatprojects-tab-chat',
            'chatprojects_general_chat_settings'
        );

        // Project Assistant Settings Section
        add_settings_section(
            'chatprojects_project_settings',
            __('Project Assistant', 'chatprojects'),
            array($this, 'render_project_settings_section'),
            'chatprojects-tab-chat'
        );

        // Default Model for Projects
        add_settings_field(
            'chatprojects_default_model',
            __('Default Model', 'chatprojects'),
            array($this, 'render_model_field'),
            'chatprojects-tab-chat',
            'chatprojects_project_settings'
        );

        // File Settings Section
        add_settings_section(
            'chatprojects_file_settings',
            __('File Upload', 'chatprojects'),
            array($this, 'render_file_settings_section'),
            'chatprojects-tab-chat'
        );

        // Keep data on uninstall
        add_settings_field(
            'chatprojects_keep_data_on_uninstall',
            __('Keep Data on Uninstall', 'chatprojects'),
            array($this, 'render_keep_data_field'),
            'chatprojects-tab-chat',
            'chatprojects_file_settings'
        );

        // Max File Size
        add_settings_field(
            'chatprojects_max_file_size',
            __('Max File Size (MB)', 'chatprojects'),
            array($this, 'render_file_size_field'),
            'chatprojects-tab-chat',
            'chatprojects_file_settings'
        );

        // Allowed File Types
        add_settings_field(
            'chatprojects_allowed_file_types',
            __('Allowed File Types', 'chatprojects'),
            array($this, 'render_file_types_field'),
            'chatprojects-tab-chat',
            'chatprojects_file_settings'
        );

        // ==================== TAB: Chat Widget ====================
        register_setting(
            'chatprojects_settings',
            'chatprojects_widget_enabled',
            array(
                'type'              => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default'           => false,
            )
        );

        register_setting(
            'chatprojects_settings',
            'chatprojects_widget_project_id',
            array(
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'default'           => 0,
            )
        );

        register_setting(
            'chatprojects_settings',
            'chatprojects_widget_position',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => 'bottom-right',
            )
        );

        register_setting(
            'chatprojects_settings',
            'chatprojects_widget_primary_color',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_hex_color',
                'default'           => '#2563eb',
            )
        );

        register_setting(
            'chatprojects_settings',
            'chatprojects_widget_welcome_message',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
                'default'           => '',
            )
        );

        register_setting(
            'chatprojects_settings',
            'chatprojects_widget_placeholder',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            )
        );

        register_setting(
            'chatprojects_settings',
            'chatprojects_widget_auto_inject',
            array(
                'type'              => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default'           => false,
            )
        );

        register_setting(
            'chatprojects_settings',
            'chatprojects_widget_rate_limit_msgs',
            array(
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'default'           => 20,
            )
        );

        register_setting(
            'chatprojects_settings',
            'chatprojects_widget_rate_limit_sessions',
            array(
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'default'           => 5,
            )
        );

        register_setting(
            'chatprojects_settings',
            'chatprojects_widget_show_branding',
            array(
                'type'              => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default'           => true,
            )
        );

        // Widget General Section
        add_settings_section(
            'chatprojects_widget_general',
            __( 'General', 'chatprojects' ),
            array( $this, 'render_widget_settings_section' ),
            'chatprojects-tab-widget'
        );

        add_settings_field(
            'chatprojects_widget_enabled',
            __( 'Enable Widget', 'chatprojects' ),
            array( $this, 'render_widget_enabled_field' ),
            'chatprojects-tab-widget',
            'chatprojects_widget_general'
        );

        add_settings_field(
            'chatprojects_widget_project_id',
            __( 'Project', 'chatprojects' ),
            array( $this, 'render_widget_project_field' ),
            'chatprojects-tab-widget',
            'chatprojects_widget_general'
        );

        add_settings_field(
            'chatprojects_widget_auto_inject',
            __( 'Auto-inject on All Pages', 'chatprojects' ),
            array( $this, 'render_widget_auto_inject_field' ),
            'chatprojects-tab-widget',
            'chatprojects_widget_general'
        );

        // Widget Appearance Section
        add_settings_section(
            'chatprojects_widget_appearance',
            __( 'Appearance', 'chatprojects' ),
            '__return_false',
            'chatprojects-tab-widget'
        );

        add_settings_field(
            'chatprojects_widget_position',
            __( 'Position', 'chatprojects' ),
            array( $this, 'render_widget_position_field' ),
            'chatprojects-tab-widget',
            'chatprojects_widget_appearance'
        );

        add_settings_field(
            'chatprojects_widget_primary_color',
            __( 'Primary Color', 'chatprojects' ),
            array( $this, 'render_widget_color_field' ),
            'chatprojects-tab-widget',
            'chatprojects_widget_appearance'
        );

        add_settings_field(
            'chatprojects_widget_welcome_message',
            __( 'Welcome Message', 'chatprojects' ),
            array( $this, 'render_widget_welcome_field' ),
            'chatprojects-tab-widget',
            'chatprojects_widget_appearance'
        );

        add_settings_field(
            'chatprojects_widget_placeholder',
            __( 'Input Placeholder', 'chatprojects' ),
            array( $this, 'render_widget_placeholder_field' ),
            'chatprojects-tab-widget',
            'chatprojects_widget_appearance'
        );

        add_settings_field(
            'chatprojects_widget_show_branding',
            __( 'Show Branding', 'chatprojects' ),
            array( $this, 'render_widget_branding_field' ),
            'chatprojects-tab-widget',
            'chatprojects_widget_appearance'
        );

        // Widget Rate Limiting Section
        add_settings_section(
            'chatprojects_widget_limits',
            __( 'Rate Limiting', 'chatprojects' ),
            '__return_false',
            'chatprojects-tab-widget'
        );

        add_settings_field(
            'chatprojects_widget_rate_limit_msgs',
            __( 'Message Limit', 'chatprojects' ),
            array( $this, 'render_widget_rate_limit_msgs_field' ),
            'chatprojects-tab-widget',
            'chatprojects_widget_limits'
        );

        add_settings_field(
            'chatprojects_widget_rate_limit_sessions',
            __( 'Session Limit', 'chatprojects' ),
            array( $this, 'render_widget_rate_limit_sessions_field' ),
            'chatprojects-tab-widget',
            'chatprojects_widget_limits'
        );
    }

    /**
     * Render providers settings section
     */
    public function render_providers_settings_section() {
        echo '<p>' . esc_html__('Configure API keys for AI providers. OpenAI API key is required for core functionality (Projects, Vector Stores, Transcription). Other provider keys are optional.', 'chatprojects') . '</p>';
    }

    /**
     * Render general chat settings section
     */
    public function render_general_chat_settings_section() {
        echo '<p>' . esc_html__('Configure default settings for the Chat mode.', 'chatprojects') . '</p>';
    }

    /**
     * Render project settings section
     */
    public function render_project_settings_section() {
        echo '<p>' . esc_html__('Configure settings for the Project Assistant mode (uses OpenAI with vector stores).', 'chatprojects') . '</p>';
    }

    /**
     * Render file settings section
     */
    public function render_file_settings_section() {
        echo '<p>' . esc_html__('Configure file upload settings.', 'chatprojects') . '</p>';
    }

    /**
     * Render OpenAI API key field
     */
    public function render_openai_key_field() {
        $decrypted = $this->get_validated_api_key('chatprojects_openai_key');
        $masked_key = $this->mask_api_key($decrypted);
        $api_key = $decrypted; // For the empty check below
        ?>
        <input type="password"
               id="chatprojects_openai_key"
               name="chatprojects_openai_key"
               value="" autocomplete="new-password"
               class="regular-text"
               placeholder="sk-..." />
        <p class="description">
            <?php if (!empty($api_key)) : ?>
                <?php esc_html_e('Current key:', 'chatprojects'); ?>
                <code><?php echo esc_html($masked_key); ?></code><br>
                <label style="margin-left:2px;">
                    <input type="checkbox" name="chatprojects_openai_key_clear" value="1" />
                    <?php esc_html_e('Remove this key', 'chatprojects'); ?>
                </label><br>
            <?php endif; ?>
            <strong style="color: #dc2626;"><?php esc_html_e('Required.', 'chatprojects'); ?></strong>
            <?php esc_html_e('Needed for Projects, Vector Stores, and Transcription features.', 'chatprojects'); ?>
            <?php
            printf(
                /* translators: %s: Link to provider API keys page */
                esc_html__('Get your API key from %s', 'chatprojects'),
                '<a href="https://platform.openai.com/api-keys" target="_blank">OpenAI</a>'
            );
            ?>
        </p>
        <?php
    }

    /**
     * Render Gemini API key field
     */
    public function render_gemini_key_field() {
        $decrypted = $this->get_validated_api_key('chatprojects_gemini_key');
        $masked_key = $this->mask_api_key($decrypted);
        $api_key = $decrypted; // For the empty check below
        ?>
        <input type="password"
               id="chatprojects_gemini_key"
               name="chatprojects_gemini_key"
               value="" autocomplete="new-password"
               class="regular-text"
               placeholder="AIza..." />
        <p class="description">
            <?php if (!empty($api_key)) : ?>
                <?php esc_html_e('Current key:', 'chatprojects'); ?>
                <code><?php echo esc_html($masked_key); ?></code><br>
                <label style="margin-left:2px;">
                    <input type="checkbox" name="chatprojects_gemini_key_clear" value="1" />
                    <?php esc_html_e('Remove this key', 'chatprojects'); ?>
                </label><br>
            <?php endif; ?>
            <?php esc_html_e('Optional. For Chat mode only.', 'chatprojects'); ?>
            <?php
            printf(
                /* translators: %s: Link to provider API keys page */
                esc_html__('Get your API key from %s', 'chatprojects'),
                '<a href="https://makersuite.google.com/app/apikey" target="_blank">Google AI Studio</a>'
            );
            ?>
        </p>
        <?php
    }

    /**
     * Render Anthropic API key field
     */
    public function render_anthropic_key_field() {
        $decrypted = $this->get_validated_api_key('chatprojects_anthropic_key');
        $masked_key = $this->mask_api_key($decrypted);
        $api_key = $decrypted; // For the empty check below
        ?>
        <input type="password"
               id="chatprojects_anthropic_key"
               name="chatprojects_anthropic_key"
               value="" autocomplete="new-password"
               class="regular-text"
               placeholder="sk-ant-..." />
        <p class="description">
            <?php if (!empty($api_key)) : ?>
                <?php esc_html_e('Current key:', 'chatprojects'); ?>
                <code><?php echo esc_html($masked_key); ?></code><br>
                <label style="margin-left:2px;">
                    <input type="checkbox" name="chatprojects_anthropic_key_clear" value="1" />
                    <?php esc_html_e('Remove this key', 'chatprojects'); ?>
                </label><br>
            <?php endif; ?>
            <?php esc_html_e('Optional. For Chat mode only.', 'chatprojects'); ?>
            <?php
            printf(
                /* translators: %s: Link to provider API keys page */
                esc_html__('Get your API key from %s', 'chatprojects'),
                '<a href="https://console.anthropic.com/settings/keys" target="_blank">Anthropic Console</a>'
            );
            ?>
        </p>
        <?php
    }

    /**
     * Render Chutes.ai API key field
     */
    public function render_chutes_key_field() {
        $decrypted = $this->get_validated_api_key('chatprojects_chutes_key');
        $masked_key = $this->mask_api_key($decrypted);
        $api_key = $decrypted; // For the empty check below
        ?>
        <input type="password"
               id="chatprojects_chutes_key"
               name="chatprojects_chutes_key"
               value="" autocomplete="new-password"
               class="regular-text"
               placeholder="" />
        <p class="description">
            <?php if (!empty($api_key)) : ?>
                <?php esc_html_e('Current key:', 'chatprojects'); ?>
                <code><?php echo esc_html($masked_key); ?></code><br>
                <label style="margin-left:2px;">
                    <input type="checkbox" name="chatprojects_chutes_key_clear" value="1" />
                    <?php esc_html_e('Remove this key', 'chatprojects'); ?>
                </label><br>
            <?php endif; ?>
            <?php esc_html_e('Optional. For Chat mode only.', 'chatprojects'); ?>
            <?php
            printf(
                /* translators: %s: Link to provider API keys page */
                esc_html__('Get your API key from %s', 'chatprojects'),
                '<a href="https://chutes.ai" target="_blank">Chutes.ai</a>'
            );
            ?>
        </p>
        <?php
    }

    /**
     * Render OpenRouter API key field
     */
    public function render_openrouter_key_field() {
        $decrypted = $this->get_validated_api_key('chatprojects_openrouter_key');
        $masked_key = $this->mask_api_key($decrypted);
        $api_key = $decrypted; // For the empty check below
        ?>
        <input type="password"
               id="chatprojects_openrouter_key"
               name="chatprojects_openrouter_key"
               value="" autocomplete="new-password"
               class="regular-text"
               placeholder="sk-or-..." />
        <p class="description">
            <?php if (!empty($api_key)) : ?>
                <?php esc_html_e('Current key:', 'chatprojects'); ?>
                <code><?php echo esc_html($masked_key); ?></code><br>
                <label style="margin-left:2px;">
                    <input type="checkbox" name="chatprojects_openrouter_key_clear" value="1" />
                    <?php esc_html_e('Remove this key', 'chatprojects'); ?>
                </label><br>
            <?php endif; ?>
            <?php esc_html_e('Optional. For Chat mode only. Access 100+ models from various providers.', 'chatprojects'); ?>
            <?php
            printf(
                /* translators: %s: Link to provider API keys page */
                esc_html__('Get your API key from %s', 'chatprojects'),
                '<a href="https://openrouter.ai/keys" target="_blank">OpenRouter</a>'
            );
            ?>
        </p>
        <?php
    }

    /**
     * Render general chat provider field
     */
    public function render_general_chat_provider_field() {
        $provider = get_option('chatprojects_general_chat_provider', 'openai');
        $providers = Model_Registry::get_provider_names();
        ?>
        <select id="chatprojects_general_chat_provider" name="chatprojects_general_chat_provider">
            <?php foreach ($providers as $value => $label) : ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($provider, $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php esc_html_e('Default AI provider for general chat mode.', 'chatprojects'); ?>
        </p>
        <?php
    }

    /**
     * Render general chat model field
     */
    public function render_general_chat_model_field() {
        $provider = get_option('chatprojects_general_chat_provider', 'openai');
        $model = get_option('chatprojects_general_chat_model', Model_Registry::get_default($provider));

        // All static models per provider (single source of truth).
        $all_models = Model_Registry::get_labels();

        // Dynamically fetch Chutes models if API key is configured
        $chutes_models = $this->get_chutes_models_cached();
        if (!empty($chutes_models)) {
            $all_models['chutes'] = $chutes_models;
        }

        // Dynamically fetch OpenRouter models if API key is configured
        $openrouter_models = $this->get_openrouter_models_cached();
        if (!empty($openrouter_models)) {
            $all_models['openrouter'] = $openrouter_models;
        }

        // Get models for current provider
        $current_models = isset($all_models[ $provider ]) ? $all_models[ $provider ] : $all_models['openai'];

        // Keep a stored value that is not in the list selectable rather than silently switching it.
        if ('' !== $model && !isset($current_models[ $model ])) {
            $current_models = array($model => $model . ' ' . __('(unlisted)', 'chatprojects')) + $current_models;
        }

        // Encode models as JSON for JavaScript
        $models_json = wp_json_encode($all_models);
        ?>
        <select id="chatprojects_general_chat_model" name="chatprojects_general_chat_model">
            <?php foreach ($current_models as $value => $label) : ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($model, $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php esc_html_e('Default model for general chat mode. Users can change this per conversation.', 'chatprojects'); ?>
        </p>
        <?php
        // Provider/model selector script - using wp_print_inline_script_tag for WordPress compliance
        $provider_model_script = '(function($) {
            $(document).ready(function() {
                var allModels = ' . wp_json_encode(json_decode($models_json, true)) . ";
                var \$providerSelect = $('#chatprojects_general_chat_provider');
                var \$modelSelect = $('#chatprojects_general_chat_model');

                // Store current model value
                var currentModel = \$modelSelect.val();

                // Update model dropdown when provider changes
                \$providerSelect.on('change', function() {
                    var provider = $(this).val();
                    var models = allModels[provider] || allModels['openai'];

                    // Clear and repopulate model dropdown
                    \$modelSelect.empty();

                    $.each(models, function(value, label) {
                        \$modelSelect.append($('<option>', {
                            value: value,
                            text: label
                        }));
                    });

                    // Try to keep the same model if it exists in the new provider
                    if (models[currentModel]) {
                        \$modelSelect.val(currentModel);
                    } else {
                        // Otherwise select the first model
                        \$modelSelect.prop('selectedIndex', 0);
                    }
                });

                // Update currentModel when manually changed
                \$modelSelect.on('change', function() {
                    currentModel = $(this).val();
                });
            });
        })(jQuery);";
        wp_print_inline_script_tag($provider_model_script, array('id' => 'chatprojects-provider-model-script'));
    }

    /**
     * Render model field
     */
    public function render_model_field() {
        $model = get_option('chatprojects_default_model', Model_Registry::get_default('openai'));
        $models = Model_Registry::get_labels('openai');
        if ('' !== $model && !isset($models[ $model ])) {
            $models = array($model => $model . ' ' . __('(unlisted)', 'chatprojects')) + $models;
        }
        ?>
        <select id="chatprojects_default_model" name="chatprojects_default_model">
            <?php foreach ($models as $value => $label) : ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($model, $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php esc_html_e('Default model for new projects.', 'chatprojects'); ?>
        </p>
        <?php
    }

    /**
     * Render keep-data-on-uninstall field
     */
    public function render_keep_data_field() {
        $keep = (bool) get_option('chatprojects_keep_data_on_uninstall', false);
        ?>
        <label for="chatprojects_keep_data_on_uninstall">
            <input type="checkbox" id="chatprojects_keep_data_on_uninstall" name="chatprojects_keep_data_on_uninstall" value="1" <?php checked($keep); ?> />
            <?php esc_html_e('Keep projects, chats, settings and API keys when the plugin is deleted.', 'chatprojects'); ?>
        </label>
        <p class="description">
            <?php esc_html_e('When unchecked, deleting the plugin removes all ChatProjects data from this site.', 'chatprojects'); ?>
        </p>
        <?php
    }

    /**
     * Render file size field
     */
    public function render_file_size_field() {
        $size = get_option('chatprojects_max_file_size', 50);
        ?>
        <input type="number" 
               id="chatprojects_max_file_size" 
               name="chatprojects_max_file_size" 
               value="<?php echo esc_attr($size); ?>" 
               min="1" 
               max="512" 
               class="small-text" />
        <p class="description">
            <?php esc_html_e('Maximum file size in megabytes (1-512 MB).', 'chatprojects'); ?>
        </p>
        <?php
    }

    /**
     * Render file types field
     */
    public function render_file_types_field() {
        $types = get_option('chatprojects_allowed_file_types', array('pdf', 'doc', 'docx', 'txt', 'md', 'csv', 'json', 'xml'));
        $types_string = is_array($types) ? implode(', ', $types) : '';
        ?>
        <input type="text" 
               id="chatprojects_allowed_file_types" 
               name="chatprojects_allowed_file_types" 
               value="<?php echo esc_attr($types_string); ?>" 
               class="regular-text" />
        <p class="description">
            <?php esc_html_e('Comma-separated list of allowed file extensions (e.g., pdf, doc, txt).', 'chatprojects'); ?>
        </p>
        <?php
    }

    /**
     * Render assistant instructions field
     */
    public function render_assistant_instructions_field() {
        $instructions = get_option('chatprojects_assistant_instructions', '');
        ?>
        <textarea id="chatprojects_assistant_instructions"
                  name="chatprojects_assistant_instructions"
                  rows="6"
                  class="large-text code"
                  placeholder="You are a helpful AI assistant..."><?php echo esc_textarea($instructions); ?></textarea>
        <p class="description">
            <?php esc_html_e('Default instructions for the AI assistant. These will be included in every chat session unless overridden by project-specific instructions.', 'chatprojects'); ?>
        </p>
        <?php
    }

    /**
     * Render widget settings section
     */
    public function render_widget_settings_section() {
        echo '<p>' . esc_html__( 'Configure the default chat widget. These settings apply to the global floating widget and serve as defaults for shortcode-based chatbots. Use shortcodes to embed project-specific chatbots on any page — see the reference below.', 'chatprojects' ) . '</p>';
    }

    /**
     * Render widget enabled toggle
     */
    public function render_widget_enabled_field() {
        $enabled = get_option( 'chatprojects_widget_enabled', false );
        ?>
        <label>
            <input type="checkbox"
                   id="chatprojects_widget_enabled"
                   name="chatprojects_widget_enabled"
                   value="1"
                   <?php checked( $enabled ); ?> />
            <?php esc_html_e( 'Enable the chat widget on the frontend', 'chatprojects' ); ?>
        </label>
        <?php
    }

    /**
     * Render widget project selector
     */
    public function render_widget_project_field() {
        $project_id = get_option( 'chatprojects_widget_project_id', 0 );
        $projects   = get_posts(
            array(
                'post_type'      => 'chatpr_project',
                'post_status'    => 'publish',
                'posts_per_page' => 100,
                'orderby'        => 'title',
                'order'          => 'ASC',
            )
        );
        ?>
        <select id="chatprojects_widget_project_id" name="chatprojects_widget_project_id">
            <option value="0"><?php esc_html_e( '— Select a project —', 'chatprojects' ); ?></option>
            <?php foreach ( $projects as $project ) : ?>
                <option value="<?php echo esc_attr( $project->ID ); ?>" <?php selected( $project_id, $project->ID ); ?>>
                    <?php echo esc_html( $project->post_title ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php esc_html_e( 'Select the project whose indexed content the widget will use. The project must have a vector store with indexed content.', 'chatprojects' ); ?>
        </p>
        <?php
    }

    /**
     * Render widget position field
     */
    public function render_widget_position_field() {
        $position = get_option( 'chatprojects_widget_position', 'bottom-right' );
        ?>
        <select id="chatprojects_widget_position" name="chatprojects_widget_position">
            <option value="bottom-right" <?php selected( $position, 'bottom-right' ); ?>>
                <?php esc_html_e( 'Bottom Right', 'chatprojects' ); ?>
            </option>
            <option value="bottom-left" <?php selected( $position, 'bottom-left' ); ?>>
                <?php esc_html_e( 'Bottom Left', 'chatprojects' ); ?>
            </option>
        </select>
        <p class="description">
            <?php esc_html_e( 'Position of the chat bubble on the page.', 'chatprojects' ); ?>
        </p>
        <?php
    }

    /**
     * Render widget primary color field
     */
    public function render_widget_color_field() {
        $color = get_option( 'chatprojects_widget_primary_color', '#2563eb' );
        ?>
        <input type="text"
               id="chatprojects_widget_primary_color"
               name="chatprojects_widget_primary_color"
               value="<?php echo esc_attr( $color ); ?>"
               class="regular-text"
               placeholder="#2563eb" />
        <p class="description">
            <?php esc_html_e( 'Primary color for the widget (hex format, e.g. #2563eb).', 'chatprojects' ); ?>
        </p>
        <?php
    }

    /**
     * Render widget welcome message field
     */
    public function render_widget_welcome_field() {
        $message = get_option( 'chatprojects_widget_welcome_message', '' );
        ?>
        <textarea id="chatprojects_widget_welcome_message"
                  name="chatprojects_widget_welcome_message"
                  rows="3"
                  class="large-text"
                  placeholder="<?php esc_attr_e( 'Hello! How can I help you today?', 'chatprojects' ); ?>"><?php echo esc_textarea( $message ); ?></textarea>
        <p class="description">
            <?php esc_html_e( 'Message displayed when a visitor first opens the chat widget. Leave blank for default.', 'chatprojects' ); ?>
        </p>
        <?php
    }

    /**
     * Render widget placeholder field
     */
    public function render_widget_placeholder_field() {
        $placeholder = get_option( 'chatprojects_widget_placeholder', '' );
        ?>
        <input type="text"
               id="chatprojects_widget_placeholder"
               name="chatprojects_widget_placeholder"
               value="<?php echo esc_attr( $placeholder ); ?>"
               class="regular-text"
               placeholder="<?php esc_attr_e( 'Type your message...', 'chatprojects' ); ?>" />
        <p class="description">
            <?php esc_html_e( 'Placeholder text in the message input field. Leave blank for default.', 'chatprojects' ); ?>
        </p>
        <?php
    }

    /**
     * Render widget auto-inject toggle
     */
    public function render_widget_auto_inject_field() {
        $auto_inject = get_option( 'chatprojects_widget_auto_inject', false );
        ?>
        <label>
            <input type="checkbox"
                   id="chatprojects_widget_auto_inject"
                   name="chatprojects_widget_auto_inject"
                   value="1"
                   <?php checked( $auto_inject ); ?> />
            <?php esc_html_e( 'Automatically display the widget on all frontend pages', 'chatprojects' ); ?>
        </label>
        <p class="description">
            <?php esc_html_e( 'When disabled, use the [chatprojects_widget] shortcode to place the widget on specific pages.', 'chatprojects' ); ?>
        </p>
        <?php
    }

    /**
     * Render widget message rate limit field
     */
    public function render_widget_rate_limit_msgs_field() {
        $limit = get_option( 'chatprojects_widget_rate_limit_msgs', 20 );
        ?>
        <input type="number"
               id="chatprojects_widget_rate_limit_msgs"
               name="chatprojects_widget_rate_limit_msgs"
               value="<?php echo esc_attr( $limit ); ?>"
               min="1"
               max="200"
               class="small-text" />
        <span><?php esc_html_e( 'messages per session per hour', 'chatprojects' ); ?></span>
        <p class="description">
            <?php esc_html_e( 'Maximum number of messages a visitor can send per hour.', 'chatprojects' ); ?>
        </p>
        <?php
    }

    /**
     * Render widget session rate limit field
     */
    public function render_widget_rate_limit_sessions_field() {
        $limit = get_option( 'chatprojects_widget_rate_limit_sessions', 5 );
        ?>
        <input type="number"
               id="chatprojects_widget_rate_limit_sessions"
               name="chatprojects_widget_rate_limit_sessions"
               value="<?php echo esc_attr( $limit ); ?>"
               min="1"
               max="50"
               class="small-text" />
        <span><?php esc_html_e( 'new sessions per IP per hour', 'chatprojects' ); ?></span>
        <p class="description">
            <?php esc_html_e( 'Maximum number of new chat sessions a single IP address can create per hour.', 'chatprojects' ); ?>
        </p>
        <?php
    }

    /**
     * Render widget branding toggle
     */
    public function render_widget_branding_field() {
        $show_branding = get_option( 'chatprojects_widget_show_branding', true );
        $is_pro        = defined( 'CHATPROJECTS_PRO' ) && CHATPROJECTS_PRO;
        ?>
        <label>
            <input type="checkbox"
                   id="chatprojects_widget_show_branding"
                   name="chatprojects_widget_show_branding"
                   value="1"
                   <?php checked( $show_branding ); ?>
                   <?php disabled( ! $is_pro ); ?> />
            <?php esc_html_e( 'Display "Powered by ChatProjects" in the widget', 'chatprojects' ); ?>
        </label>
        <?php if ( ! $is_pro ) : ?>
            <p class="description">
                <?php esc_html_e( 'Branding is always shown in the free version. Upgrade to Pro to remove it.', 'chatprojects' ); ?>
            </p>
        <?php endif; ?>
        <?php
    }

    /**
     * Show admin notices
     */
    public function show_notices() {
        // Check if API key is set
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only, no form processing
        if ( isset( $_GET['page'] ) && sanitize_text_field( wp_unslash( $_GET['page'] ) ) === 'chatprojects-settings' ) {
            $api_key = get_option('chatprojects_openai_key', '');

            if (empty($api_key)) {
                ?>
                <div class="notice notice-warning">
                    <p>
                        <?php esc_html_e('Please configure your OpenAI API key to use ChatProjects.', 'chatprojects'); ?>
                    </p>
                </div>
                <?php
            }
        }

        // Show success message after save
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only, no form processing
        if ( isset( $_GET['settings-updated'] ) && sanitize_text_field( wp_unslash( $_GET['settings-updated'] ) ) === 'true' ) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e('Settings saved successfully.', 'chatprojects'); ?></p>
            </div>
            <?php
        }
    }

    /**
     * Sanitize file types input
     *
     * @param string|array $input File types input (comma-separated string or array)
     * @return array Sanitized array of file types
     */
    public function sanitize_file_types($input) {
        // If it's a string, convert to array
        if (is_string($input)) {
            $types = array_map('trim', explode(',', $input));
        } elseif (is_array($input)) {
            $types = $input;
        } else {
            return array('pdf', 'doc', 'docx', 'txt', 'md', 'csv', 'json', 'xml');
        }

        // Sanitize each type - only allow alphanumeric characters
        // Don't use sanitize_file_name() as it adds "unnamed-file." prefix to bare extensions
        $types = array_map(function($type) {
            // Remove any dots, spaces, and special characters - keep only alphanumeric
            $clean = preg_replace('/[^a-zA-Z0-9]/', '', strtolower(trim($type)));
            return $clean;
        }, $types);

        // Remove empty values
        $types = array_filter($types);

        // Return unique values
        return array_values(array_unique($types));
    }

    /**
     * Mask API key for display
     *
     * @param string $key API key
     * @return string Masked key
     */
    private function mask_api_key($key) {
        if (empty($key) || strlen($key) < 12) {
            return '';
        }

        $visible_start = 7;  // Show "sk-proj" or similar
        $visible_end = 4;    // Show last 4 characters

        $start = substr($key, 0, $visible_start);
        $end = substr($key, -$visible_end);
        $masked_length = strlen($key) - $visible_start - $visible_end;

        return $start . str_repeat('•', min($masked_length, 20)) . $end;
    }

    /**
     * Get and validate decrypted API key
     *
     * Handles decryption failures by clearing corrupted values.
     * This fixes the race condition where first-save encryption key
     * differs from subsequent decryption key.
     *
     * @param string $option_name The option name storing the encrypted key
     * @return string Decrypted API key or empty string if invalid
     */
    private function get_validated_api_key($option_name) {
        $encrypted = get_option($option_name, '');

        if (empty($encrypted)) {
            return '';
        }

        $decrypted = Security::decrypt($encrypted);

        // Check for decryption failure
        if ($decrypted === false) {
            // Clear corrupted value
            delete_option($option_name);
            return '';
        }

        // Check for obvious garbage - if decryption used wrong key, result is binary/unprintable
        // Valid API keys should only contain printable ASCII characters
        if (!empty($decrypted) && preg_match('/[^\x20-\x7E]/', $decrypted)) {
            // Clear corrupted value
            delete_option($option_name);
            return '';
        }

        // Validate that decrypted value looks like a valid API key
        // When decrypting with wrong key, OpenSSL may return printable garbage
        if (!empty($decrypted)) {
            $valid_prefixes = array('sk-', 'sk-proj-', 'AIza', 'sk-ant-', 'cpat_', 'cpk_', 'sk-or-');
            $has_valid_prefix = false;
            foreach ($valid_prefixes as $prefix) {
                if (strpos($decrypted, $prefix) === 0) {
                    $has_valid_prefix = true;
                    break;
                }
            }

            if (!$has_valid_prefix) {
                // Clear corrupted value
                delete_option($option_name);
                return '';
            }
        }

        return $decrypted;
    }

    /**
     * Get Chutes.ai models with caching
     *
     * @return array Array of models (id => name) or empty array if unavailable
     */
    private function get_chutes_models_cached() {
        // Check cache first (5 minute TTL)
        $cached = get_transient('chatprojects_chutes_models');
        if ($cached !== false) {
            return $cached;
        }

        // Check if Chutes API key is configured (stored encrypted)
        $encrypted_key = get_option('chatprojects_chutes_key', '');
        if (empty($encrypted_key)) {
            return array();
        }

        // Decrypt the API key
        $api_key = Security::decrypt($encrypted_key);
        if (empty($api_key)) {
            return array();
        }

        // Fetch models from Chutes API
        $response = wp_remote_get(
            'https://llm.chutes.ai/v1/models',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                ),
                'timeout' => 10,
            )
        );

        if (is_wp_error($response)) {
            return array();
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        $model_list = $data['data'] ?? $data['models'] ?? null;

        if (isset($model_list) && is_array($model_list)) {
            $models = array();
            foreach ($model_list as $model) {
                $id = $model['id'] ?? $model['name'] ?? '';
                $name = $model['id'] ?? $model['name'] ?? '';
                if ($id && $name) {
                    $models[ $id ] = $name;
                }
            }
            if (!empty($models)) {
                // Sort alphabetically
                asort($models);
                // Cache for 5 minutes
                set_transient('chatprojects_chutes_models', $models, 5 * MINUTE_IN_SECONDS);
                return $models;
            }
        }

        return array();
    }

    /**
     * Get OpenRouter models with caching
     *
     * @return array Array of models (id => name) or empty array if unavailable
     */
    private function get_openrouter_models_cached() {
        // Check cache first (5 minute TTL)
        $cached = get_transient('chatprojects_openrouter_models');
        if ($cached !== false) {
            return $cached;
        }

        // Check if OpenRouter API key is configured (stored encrypted)
        $encrypted_key = get_option('chatprojects_openrouter_key', '');
        if (empty($encrypted_key)) {
            return array();
        }

        // Decrypt the API key
        $api_key = Security::decrypt($encrypted_key);
        if (empty($api_key)) {
            return array();
        }

        // Fetch models from OpenRouter API
        $response = wp_remote_get(
            'https://openrouter.ai/api/v1/models',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                ),
                'timeout' => 15,
            )
        );

        if (is_wp_error($response)) {
            return array();
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        $model_list = $data['data'] ?? $data['models'] ?? null;

        if (isset($model_list) && is_array($model_list)) {
            $models = array();
            foreach ($model_list as $model) {
                $id = $model['id'] ?? $model['name'] ?? '';
                $name = $model['name'] ?? $model['id'] ?? '';
                if ($id && $name) {
                    $models[ $id ] = $name;
                }
            }
            if (!empty($models)) {
                // Sort alphabetically
                asort($models);
                // Cache for 5 minutes
                set_transient('chatprojects_openrouter_models', $models, 5 * MINUTE_IN_SECONDS);
                return $models;
            }
        }

        return array();
    }
}
