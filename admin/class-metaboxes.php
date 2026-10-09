<?php
/**
 * Metaboxes Class
 *
 * Handles admin metaboxes for custom post types
 *
 * @package ChatProjects
 */

namespace ChatProjects\Admin;

use ChatProjects\Access;
use ChatProjects\Model_Registry;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Metaboxes Class
 */
class Metaboxes {
    /**
     * Constructor
     */
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_metaboxes'));
        add_action('save_post', array($this, 'save_project_meta'), 10, 2);
        add_action('save_post', array($this, 'save_prompt_meta'), 10, 2);
    }

    /**
     * Add metaboxes
     */
    public function add_metaboxes() {
        // Project metaboxes
        add_meta_box(
            'chatprojects_project_settings',
            __('Project Settings', 'chatprojects'),
            array($this, 'render_project_settings'),
            'chatpr_project',
            'normal',
            'high'
        );

        // Auto-RAG Content Indexing metabox
        add_meta_box(
            'chatprojects_auto_rag',
            __( 'Content Index (Auto-RAG)', 'chatprojects' ),
            array( $this, 'render_auto_rag_metabox' ),
            'chatpr_project',
            'normal',
            'default'
        );

        // Sharing Settings metabox removed from Free version

        // Embed as Chatbot — shows copy-ready shortcodes in sidebar.
        add_meta_box(
            'chatprojects_widget_shortcode',
            __( 'Embed as Chatbot', 'chatprojects' ),
            array( $this, 'render_widget_shortcode_metabox' ),
            'chatpr_project',
            'side',
            'default'
        );

        // Prompt metaboxes
        add_meta_box(
            'chatprojects_prompt_variables',
            __('Prompt Variables', 'chatprojects'),
            array($this, 'render_prompt_variables'),
            'chatpr_prompt',
            'normal',
            'high'
        );

        // Sharing Settings metabox removed from Free version
    }

    /**
     * Render project settings metabox
     *
     * @param WP_Post $post Post object
     */
    public function render_project_settings($post) {
        wp_nonce_field('chatprojects_project_meta', 'chatprojects_project_nonce');

        // Vector store ID (no assistant needed with Responses API)
        $chatprojects_vector_store_id = get_post_meta($post->ID, '_cp_vector_store_id', true);
        $chatprojects_model = get_post_meta($post->ID, '_cp_model', true) ?: get_option('chatprojects_default_model', \ChatProjects\Model_Registry::get_default('openai'));
        $chatprojects_instructions = get_post_meta($post->ID, '_cp_instructions', true);

        include CHATPROJECTS_PLUGIN_DIR . 'admin/views/project-meta.php';
    }

    /**
     * Render sharing settings metabox
     *
     * @param WP_Post $post Post object
     */
    public function render_sharing_settings($post) {
        wp_nonce_field('chatprojects_sharing_meta', 'chatprojects_sharing_nonce');

        $sharing_mode = get_post_meta($post->ID, '_cp_sharing_mode', true) ?: 'private';
        $shared_users = get_post_meta($post->ID, '_cp_shared_users', true) ?: array();

        // Get all users for sharing selection
        $users = get_users(array(
            'exclude' => array($post->post_author),
            'orderby' => 'display_name',
        ));

        ?>
        <div class="chatprojects-sharing-settings">
            <p>
                <label>
                    <input type="radio" name="cp_sharing_mode" value="private" <?php checked($sharing_mode, 'private'); ?>>
                    <strong><?php esc_html_e('Private', 'chatprojects'); ?></strong><br>
                    <span class="description"><?php esc_html_e('Only you can access', 'chatprojects'); ?></span>
                </label>
            </p>

            <p>
                <label>
                    <input type="radio" name="cp_sharing_mode" value="shared" <?php checked($sharing_mode, 'shared'); ?>>
                    <strong><?php esc_html_e('Shared', 'chatprojects'); ?></strong><br>
                    <span class="description"><?php esc_html_e('Share with specific users', 'chatprojects'); ?></span>
                </label>
            </p>

            <div class="vp-shared-users" style="margin-left: 25px; <?php echo $sharing_mode !== 'shared' ? 'display:none;' : ''; ?>">
                <select name="cp_shared_users[]" multiple size="5" style="width: 100%;">
                    <?php foreach ($users as $user) : ?>
                        <option value="<?php echo esc_attr($user->ID); ?>" 
                                <?php echo in_array($user->ID, $shared_users) ? 'selected' : ''; ?>>
                            <?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php esc_html_e('Hold Ctrl/Cmd to select multiple users', 'chatprojects'); ?></p>
            </div>

            <p>
                <label>
                    <input type="radio" name="cp_sharing_mode" value="public" <?php checked($sharing_mode, 'public'); ?>>
                    <strong><?php esc_html_e('Public', 'chatprojects'); ?></strong><br>
                    <span class="description"><?php esc_html_e('All users can access', 'chatprojects'); ?></span>
                </label>
            </p>
        </div>
        <?php
        // Sharing mode toggle script
        $sharing_script = "jQuery(document).ready(function($) {
            $('input[name=\"cp_sharing_mode\"]').on('change', function() {
                if ($(this).val() === 'shared') {
                    $('.vp-shared-users').show();
                } else {
                    $('.vp-shared-users').hide();
                }
            });
        });";
        wp_print_inline_script_tag($sharing_script, array('id' => 'chatprojects-sharing-toggle'));
    }

    /**
     * Render prompt variables metabox
     *
     * @param WP_Post $post Post object
     */
    public function render_prompt_variables($post) {
        wp_nonce_field('chatprojects_prompt_meta', 'chatprojects_prompt_nonce');

        $variables = get_post_meta($post->ID, '_cp_variables', true) ?: array();
        ?>
        <div class="chatprojects-prompt-variables">
            <p class="description">
                <?php esc_html_e('Use {{variable_name}} in your prompt. Define variables below:', 'chatprojects'); ?>
            </p>
            
            <div id="vp-variables-list">
                <?php
                if (!empty($variables) && is_array($variables)) {
                    foreach ($variables as $index => $variable) {
                        ?>
                        <div class="vp-variable-item" style="margin-bottom: 10px;">
                            <input type="text" 
                                   name="cp_variables[]" 
                                   value="<?php echo esc_attr($variable); ?>" 
                                   placeholder="<?php esc_attr_e('variable_name', 'chatprojects'); ?>"
                                   style="width: 70%;">
                            <button type="button" class="button vp-remove-variable"><?php esc_html_e('Remove', 'chatprojects'); ?></button>
                        </div>
                        <?php
                    }
                } else {
                    ?>
                    <div class="vp-variable-item" style="margin-bottom: 10px;">
                        <input type="text" 
                               name="cp_variables[]" 
                               value="" 
                               placeholder="<?php esc_attr_e('variable_name', 'chatprojects'); ?>"
                               style="width: 70%;">
                        <button type="button" class="button vp-remove-variable"><?php esc_html_e('Remove', 'chatprojects'); ?></button>
                    </div>
                    <?php
                }
                ?>
            </div>
            
            <p>
                <button type="button" id="vp-add-variable" class="button"><?php esc_html_e('Add Variable', 'chatprojects'); ?></button>
            </p>
        </div>
        <?php
        // Variable add/remove script - using localized strings
        $variable_placeholder = esc_attr__('variable_name', 'chatprojects');
        $remove_label = esc_html__('Remove', 'chatprojects');
        $variables_script = "jQuery(document).ready(function($) {
            $('#vp-add-variable').on('click', function() {
                var html = '<div class=\"vp-variable-item\" style=\"margin-bottom: 10px;\">' +
                    '<input type=\"text\" name=\"cp_variables[]\" value=\"\" placeholder=\"{$variable_placeholder}\" style=\"width: 70%;\">' +
                    '<button type=\"button\" class=\"button vp-remove-variable\">{$remove_label}</button>' +
                    '</div>';
                $('#vp-variables-list').append(html);
            });

            $(document).on('click', '.vp-remove-variable', function() {
                $(this).closest('.vp-variable-item').remove();
            });
        });";
        wp_print_inline_script_tag($variables_script, array('id' => 'chatprojects-variables-script'));
    }

    /**
     * Render "Embed as Chatbot" sidebar metabox.
     *
     * Shows copy-ready shortcodes when the project has a vector store.
     *
     * @param \WP_Post $post Post object.
     */
    public function render_widget_shortcode_metabox( $post ) {
        $vector_store_id = get_post_meta( $post->ID, '_cp_vector_store_id', true );
        $post_id         = absint( $post->ID );
        $settings_url    = admin_url( 'admin.php?page=chatprojects-settings&tab=widget' );
        $widget_enabled  = '1' === (string) get_post_meta( $post->ID, '_cp_widget_enabled', true );
        ?>
        <p style="margin:0 0 10px;">
            <label for="cp_widget_enabled">
                <input type="checkbox" name="cp_widget_enabled" id="cp_widget_enabled" value="1" <?php checked( $widget_enabled ); ?> />
                <strong><?php esc_html_e( 'Allow public chat widget', 'chatprojects' ); ?></strong>
            </label>
            <span class="description" style="display:block;margin-top:4px;">
                <?php esc_html_e( 'Lets logged-out visitors chat with this project\'s indexed content through the embedded widget. Leave off for private projects.', 'chatprojects' ); ?>
            </span>
        </p>
        <?php
        if ( empty( $vector_store_id ) ) {
            echo '<p class="description" style="margin:0;">';
            esc_html_e( 'Index content first using Content Index (Auto-RAG), then come back here to get your embed shortcode.', 'chatprojects' );
            echo '</p>';
            return;
        }

        if ( ! $widget_enabled ) {
            echo '<p class="description" style="margin:0;">';
            esc_html_e( 'Tick "Allow public chat widget" and save to get the embed shortcode.', 'chatprojects' );
            echo '</p>';
            return;
        }
        ?>
        <style>
            .cpw-embed-row { margin-bottom: 12px; }
            .cpw-embed-row:last-of-type { margin-bottom: 8px; }
            .cpw-embed-label { display: block; font-weight: 600; font-size: 12px; color: #1d2327; margin-bottom: 4px; }
            .cpw-embed-field { display: flex; align-items: center; gap: 4px; }
            .cpw-embed-field code { flex: 1; background: #f0f0f1; padding: 6px 8px; border-radius: 3px; font-size: 11.5px; word-break: break-all; line-height: 1.4; user-select: all; }
            .cpw-embed-copy { background: none; border: 1px solid #c3c4c7; border-radius: 3px; padding: 4px 6px; cursor: pointer; color: #50575e; flex-shrink: 0; line-height: 1; }
            .cpw-embed-copy:hover { color: #2271b1; border-color: #2271b1; }
            .cpw-embed-copy .dashicons { font-size: 16px; width: 16px; height: 16px; }
            .cpw-embed-copy.cpw-copied { color: #00a32a; border-color: #00a32a; }
            .cpw-embed-note { font-size: 12px; color: #50575e; line-height: 1.5; margin-top: 8px; }
            .cpw-embed-note a { color: #2271b1; text-decoration: none; }
            .cpw-embed-note a:hover { text-decoration: underline; }
        </style>

        <div class="cpw-embed-row">
            <span class="cpw-embed-label"><?php esc_html_e( 'Inline (embedded in page)', 'chatprojects' ); ?></span>
            <div class="cpw-embed-field">
                <code>[chatprojects_widget project="<?php echo esc_attr( $post_id ); ?>"]</code>
                <button type="button" class="cpw-embed-copy" data-shortcode='[chatprojects_widget project="<?php echo esc_attr( $post_id ); ?>"]' title="<?php esc_attr_e( 'Copy', 'chatprojects' ); ?>">
                    <span class="dashicons dashicons-clipboard"></span>
                </button>
            </div>
        </div>

        <div class="cpw-embed-row">
            <span class="cpw-embed-label"><?php esc_html_e( 'Floating (chat bubble)', 'chatprojects' ); ?></span>
            <div class="cpw-embed-field">
                <code>[chatprojects_widget project="<?php echo esc_attr( $post_id ); ?>" mode="floating"]</code>
                <button type="button" class="cpw-embed-copy" data-shortcode='[chatprojects_widget project="<?php echo esc_attr( $post_id ); ?>" mode="floating"]' title="<?php esc_attr_e( 'Copy', 'chatprojects' ); ?>">
                    <span class="dashicons dashicons-clipboard"></span>
                </button>
            </div>
        </div>

        <p class="cpw-embed-note">
            <?php esc_html_e( 'Paste into any page or post.', 'chatprojects' ); ?>
            <?php
            printf(
                /* translators: %s: link to widget settings */
                esc_html__( 'Customize with color, title, height — see %s.', 'chatprojects' ),
                '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'full attribute reference', 'chatprojects' ) . '</a>'
            );
            ?>
        </p>

        <script>
            document.addEventListener('click', function(e) {
                var btn = e.target.closest('.cpw-embed-copy');
                if (!btn) return;
                var shortcode = btn.getAttribute('data-shortcode');
                navigator.clipboard.writeText(shortcode).then(function() {
                    btn.classList.add('cpw-copied');
                    var icon = btn.querySelector('.dashicons');
                    if (icon) { icon.className = 'dashicons dashicons-yes'; }
                    setTimeout(function() {
                        btn.classList.remove('cpw-copied');
                        if (icon) { icon.className = 'dashicons dashicons-clipboard'; }
                    }, 1500);
                });
            });
        </script>
        <?php
    }

    /**
     * Render Auto-RAG content indexing metabox.
     *
     * @param \WP_Post $post Post object.
     */
    public function render_auto_rag_metabox( $post ) {
        $vector_store_id = get_post_meta( $post->ID, '_cp_vector_store_id', true );

        if ( empty( $vector_store_id ) ) {
            echo '<p>' . esc_html__( 'Publish this project first to enable content indexing.', 'chatprojects' ) . '</p>';
            return;
        }

        $indexer     = new \ChatProjects\Content_Indexer();
        $status      = $indexer->get_index_status( $post->ID );
        $job         = $indexer->get_job_status( $post->ID );
        $post_types  = $indexer->get_indexable_post_types();
        $is_running  = $job && 'running' === $job['status'];
        ?>
        <div id="chatpr-autorag" data-project-id="<?php echo esc_attr( $post->ID ); ?>">
            <div class="chatpr-autorag-status" style="margin-bottom: 16px;">
                <p>
                    <strong><?php esc_html_e( 'Index Status:', 'chatprojects' ); ?></strong>
                    <span id="chatpr-autorag-indexed"><?php echo absint( $status['indexed'] ); ?></span> /
                    <span id="chatpr-autorag-available"><?php echo absint( $status['available'] ); ?></span>
                    <?php esc_html_e( 'posts indexed', 'chatprojects' ); ?>
                    <?php if ( $status['failed'] > 0 ) : ?>
                        <span style="color: #d63638;">
                            (<?php echo absint( $status['failed'] ); ?> <?php esc_html_e( 'failed', 'chatprojects' ); ?>)
                        </span>
                    <?php endif; ?>
                </p>
                <p class="description">
                    <?php
                    /* translators: %s: comma-separated list of post types */
                    printf( esc_html__( 'Indexable post types: %s', 'chatprojects' ), esc_html( implode( ', ', $post_types ) ) );
                    ?>
                </p>
            </div>

            <div id="chatpr-autorag-progress" style="display: <?php echo $is_running ? 'block' : 'none'; ?>; margin-bottom: 16px;">
                <div style="background: #f0f0f1; border-radius: 3px; overflow: hidden; height: 20px; margin-bottom: 8px;">
                    <div id="chatpr-autorag-bar" style="background: #2271b1; height: 100%; width: 0%; transition: width 0.3s;"></div>
                </div>
                <p id="chatpr-autorag-progress-text" style="margin: 0; font-style: italic;"></p>
            </div>

            <div id="chatpr-autorag-errors" style="display: none; margin-bottom: 16px;">
                <div class="notice notice-error inline" style="margin: 0;">
                    <p><strong><?php esc_html_e( 'Indexing Errors:', 'chatprojects' ); ?></strong></p>
                    <ul id="chatpr-autorag-error-list" style="margin-left: 16px; list-style: disc;"></ul>
                </div>
            </div>

            <div class="chatpr-autorag-actions">
                <button type="button" id="chatpr-autorag-start" class="button button-primary" <?php disabled( $is_running ); ?>>
                    <?php esc_html_e( 'Index My Site', 'chatprojects' ); ?>
                </button>
                <button type="button" id="chatpr-autorag-cancel" class="button" style="display: <?php echo $is_running ? 'inline-block' : 'none'; ?>;">
                    <?php esc_html_e( 'Cancel', 'chatprojects' ); ?>
                </button>
                <button type="button" id="chatpr-autorag-clear" class="button" <?php disabled( $status['total'] < 1 ); ?>>
                    <?php esc_html_e( 'Clear Index', 'chatprojects' ); ?>
                </button>
            </div>
        </div>
        <?php
        $this->enqueue_autorag_script( $post->ID );
    }

    /**
     * Enqueue the Auto-RAG metabox script with its settings.
     *
     * @param int $project_id Project ID.
     */
    private function enqueue_autorag_script( $project_id ) {
        wp_enqueue_script(
            'chatprojects-autorag',
            CHATPROJECTS_PLUGIN_URL . 'assets/js/autorag-metabox.js',
            array( 'jquery' ),
            \ChatProjects\ChatProjects::asset_version( 'assets/js/autorag-metabox.js' ),
            true
        );

        $config = array(
            'projectId' => absint( $project_id ),
            'nonce'     => wp_create_nonce( 'chatpr_ajax_nonce' ),
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'strings'   => array(
                'confirmClear' => __( 'Are you sure you want to clear all indexed content? This will remove files from the vector store.', 'chatprojects' ),
                'indexing'     => __( 'Indexing...', 'chatprojects' ),
                'complete'     => __( 'Indexing complete!', 'chatprojects' ),
                'cancelled'    => __( 'Indexing cancelled.', 'chatprojects' ),
            ),
        );
        wp_add_inline_script( 'chatprojects-autorag', 'window.chatprAutorag = ' . wp_json_encode( $config ) . ';', 'before' );
    }

    /**
     * Save project meta
     *
     * @param int     $post_id Post ID
     * @param WP_Post $post Post object
     */
    public function save_project_meta($post_id, $post) {
        // Check if this is an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check post type
        if ($post->post_type !== 'chatpr_project') {
            return;
        }

        // Check nonce
        $project_nonce = isset($_POST['chatprojects_project_nonce']) ? sanitize_text_field(wp_unslash($_POST['chatprojects_project_nonce'])) : '';
        if (empty($project_nonce) || !wp_verify_nonce($project_nonce, 'chatprojects_project_meta')) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save model
        if (isset($_POST['cp_model'])) {
            $model = Model_Registry::resolve('openai', sanitize_text_field(wp_unslash($_POST['cp_model'])), get_option('chatprojects_default_model'));
            update_post_meta($post_id, '_cp_model', $model);
        }

        // Save instructions
        if (isset($_POST['cp_instructions'])) {
            $instructions = sanitize_textarea_field(wp_unslash($_POST['cp_instructions']));
            update_post_meta($post_id, '_cp_instructions', $instructions);
        }

        // Public widget opt-in (unchecked checkbox is absent from POST).
        update_post_meta($post_id, '_cp_widget_enabled', !empty($_POST['cp_widget_enabled']) ? '1' : '0');

        // Save sharing settings
        $this->save_sharing_meta($post_id);
    }

    /**
     * Save prompt meta
     *
     * @param int     $post_id Post ID
     * @param WP_Post $post Post object
     */
    public function save_prompt_meta($post_id, $post) {
        // Check if this is an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check post type
        if ($post->post_type !== 'chatpr_prompt') {
            return;
        }

        // Check nonce
        $prompt_nonce = isset($_POST['chatprojects_prompt_nonce']) ? sanitize_text_field(wp_unslash($_POST['chatprojects_prompt_nonce'])) : '';
        if (empty($prompt_nonce) || !wp_verify_nonce($prompt_nonce, 'chatprojects_prompt_meta')) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save variables
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via array_map below
        if (isset($_POST['cp_variables']) && is_array($_POST['cp_variables'])) {
            $raw_variables = wp_unslash($_POST['cp_variables']);
            // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $variables = array_filter(array_map('sanitize_text_field', $raw_variables));
            update_post_meta($post_id, '_cp_variables', $variables);
        } else {
            delete_post_meta($post_id, '_cp_variables');
        }

        // Save sharing settings
        $this->save_sharing_meta($post_id);
    }

    /**
     * Save sharing meta
     *
     * @param int $post_id Post ID
     */
    private function save_sharing_meta($post_id) {
        // Check nonce
        $sharing_nonce = isset($_POST['chatprojects_sharing_nonce']) ? sanitize_text_field(wp_unslash($_POST['chatprojects_sharing_nonce'])) : '';
        if (empty($sharing_nonce) || !wp_verify_nonce($sharing_nonce, 'chatprojects_sharing_meta')) {
            return;
        }

        // Save sharing mode
        if (isset($_POST['cp_sharing_mode'])) {
            $sharing_mode = sanitize_text_field(wp_unslash($_POST['cp_sharing_mode']));
            if (in_array($sharing_mode, array('private', 'shared', 'public'))) {
                update_post_meta($post_id, '_cp_sharing_mode', $sharing_mode);
            }
        }

        // Save shared users
        if (isset($_POST['cp_shared_users']) && is_array($_POST['cp_shared_users'])) {
            $shared_users = array_map('absint', wp_unslash($_POST['cp_shared_users']));
            update_post_meta($post_id, '_cp_shared_users', $shared_users);
        } else {
            delete_post_meta($post_id, '_cp_shared_users');
        }
    }
}
