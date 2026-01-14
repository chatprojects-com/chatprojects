<?php
/**
 * Project Metabox Template
 *
 * @package ChatProjects
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="chatprojects-project-meta">
    <?php if (!empty($vector_store_id)) : ?>
        <div class="notice notice-success inline">
            <p>
                <strong><?php esc_html_e('Vector Store:', 'chatprojects'); ?></strong>
                <code><?php echo esc_html($vector_store_id); ?></code>
            </p>
        </div>
    <?php endif; ?>

    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="cp_model"><?php esc_html_e('Model', 'chatprojects'); ?></label>
            </th>
            <td>
                <select name="cp_model" id="cp_model" class="regular-text">
                    <option value="gpt-5.2" <?php selected($model, 'gpt-5.2'); ?>>GPT-5.2 (Recommended)</option>
                    <option value="gpt-5.2-pro" <?php selected($model, 'gpt-5.2-pro'); ?>>GPT-5.2 Pro</option>
                    <option value="gpt-5.1" <?php selected($model, 'gpt-5.1'); ?>>GPT-5.1</option>
                    <option value="gpt-4o" <?php selected($model, 'gpt-4o'); ?>>GPT-4o</option>
                    <option value="gpt-4o-mini" <?php selected($model, 'gpt-4o-mini'); ?>>GPT-4o Mini</option>
                    <option value="gpt-4-turbo" <?php selected($model, 'gpt-4-turbo'); ?>>GPT-4 Turbo</option>
                    <option value="gpt-3.5-turbo" <?php selected($model, 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo</option>
                </select>
                <p class="description">
                    <?php esc_html_e('AI model to use for this project.', 'chatprojects'); ?>
                </p>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="cp_instructions"><?php esc_html_e('Instructions', 'chatprojects'); ?></label>
            </th>
            <td>
                <textarea name="cp_instructions" 
                          id="cp_instructions" 
                          rows="10" 
                          class="large-text code"
                          placeholder="<?php esc_attr_e('Enter assistant instructions...', 'chatprojects'); ?>"><?php echo esc_textarea($instructions); ?></textarea>
                <p class="description">
                    <?php esc_html_e('System instructions for the AI assistant. This defines the assistant\'s behavior and capabilities.', 'chatprojects'); ?>
                </p>
            </td>
        </tr>
    </table>

    <?php if (empty($vector_store_id)) : ?>
        <div class="notice notice-info inline">
            <p>
                <?php esc_html_e('Vector Store will be created automatically when you publish this project.', 'chatprojects'); ?>
            </p>
        </div>
    <?php endif; ?>
</div>