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

        // Sharing Settings metabox removed from Free version

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
        $vector_store_id = get_post_meta($post->ID, '_cp_vector_store_id', true);
        $model = get_post_meta($post->ID, '_cp_model', true) ?: get_option('chatprojects_default_model', 'gpt-5.2');
        $instructions = get_post_meta($post->ID, '_cp_instructions', true);

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
     * Save project meta
     *
     * @param int $post_id Post ID
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
            $model = sanitize_text_field(wp_unslash($_POST['cp_model']));
            update_post_meta($post_id, '_cp_model', $model);
        }

        // Save instructions
        if (isset($_POST['cp_instructions'])) {
            $instructions = sanitize_textarea_field(wp_unslash($_POST['cp_instructions']));
            update_post_meta($post_id, '_cp_instructions', $instructions);
        }

        // Save sharing settings
        $this->save_sharing_meta($post_id);
    }

    /**
     * Save prompt meta
     *
     * @param int $post_id Post ID
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
