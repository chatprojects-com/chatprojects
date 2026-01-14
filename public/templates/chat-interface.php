<?php
/**
 * Chat Interface Template
 *
 * @package ChatProjects
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

call_user_func(function () {
    $project_id = get_the_ID();
    ?>
    <div id="vp-chat-interface" class="vp-chat-container">
        <!-- Hidden inputs for JavaScript -->
        <input type="hidden" id="vp-current-project" value="<?php echo esc_attr($project_id); ?>">
        <input type="hidden" id="vp-current-chat" value="">
        <input type="hidden" id="vp-chat-mode" value="project">

        <!-- Main Chat Area (Sidebar is now in project-shell.php) -->
        <div class="vp-chat-main vp-chat-main-fullwidth">
            <!-- Chat Mode Toggle -->
            <div class="vp-chat-mode-toggle">
                <button class="vp-mode-btn active" data-mode="project" id="vp-mode-project">
                    <span class="dashicons dashicons-portfolio"></span>
                    <span><?php esc_html_e('Project Assistant', 'chatprojects'); ?></span>
                </button>
                <button class="vp-mode-btn" data-mode="general" id="vp-mode-general">
                    <span class="dashicons dashicons-admin-comments"></span>
                    <span><?php esc_html_e('Chat', 'chatprojects'); ?></span>
                </button>
            </div>

            <!-- Provider Selector (for general chat mode) -->
            <div id="vp-provider-selector" class="vp-provider-selector" style="display: none;">
                <div class="vp-provider-controls">
                    <div class="vp-provider-dropdown">
                        <label for="vp-chat-provider"><?php esc_html_e('Provider:', 'chatprojects'); ?></label>
                        <select id="vp-chat-provider" name="provider">
                            <option value="openai"><?php esc_html_e('OpenAI', 'chatprojects'); ?></option>
                            <?php
                            $gemini_key = get_option('cp_gemini_api_key');
                            if (!empty($gemini_key)) :
                            ?>
                            <option value="gemini"><?php esc_html_e('Google Gemini', 'chatprojects'); ?></option>
                            <?php endif; ?>
                            <?php
                            $anthropic_key = get_option('cp_anthropic_api_key');
                            if (!empty($anthropic_key)) :
                            ?>
                            <option value="anthropic"><?php esc_html_e('Anthropic Claude', 'chatprojects'); ?></option>
                            <?php endif; ?>
                            <?php
                            $chutes_key = get_option('cp_chutes_api_key');
                            if (!empty($chutes_key)) :
                            ?>
                            <option value="chutes"><?php esc_html_e('Chutes.ai', 'chatprojects'); ?></option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="vp-model-dropdown">
                        <label for="vp-chat-model"><?php esc_html_e('Model:', 'chatprojects'); ?></label>
                        <select id="vp-chat-model" name="model">
                            <option value="gpt-4o">GPT-4o</option>
                            <option value="gpt-4o-mini">GPT-4o Mini</option>
                            <option value="gpt-4-turbo">GPT-4 Turbo</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="vp-chat-header">
                <div class="vp-chat-title-container">
                    <h3 id="vp-current-chat-title"><?php esc_html_e('New Chat', 'chatprojects'); ?></h3>
                    <button id="vp-rename-chat-btn" class="vp-btn-icon" title="<?php esc_attr_e('Rename chat', 'chatprojects'); ?>" style="display: none;">
                        <span class="dashicons dashicons-edit"></span>
                    </button>
                </div>
            </div>

            <div id="vp-chat-messages" class="vp-messages">
                <div class="vp-welcome-message">
                    <span class="dashicons dashicons-format-chat"></span>
                    <h4><?php esc_html_e('Welcome!', 'chatprojects'); ?></h4>
                    <p><?php esc_html_e('Start a conversation with your AI assistant.', 'chatprojects'); ?></p>
                </div>
            </div>

            <div class="vp-chat-input-container">
                <form id="vp-chat-form">
                    <textarea
                        id="vp-chat-input"
                        name="message"
                        placeholder="<?php esc_attr_e('Type your message...', 'chatprojects'); ?>"
                        rows="3"
                        required
                    ></textarea>
                    <button type="submit" id="vp-chat-submit" class="vp-btn-primary">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="white" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a.75.75 0 01-.75-.75V4.66L7.3 6.76a.75.75 0 11-1.1-1.02l3.25-3.5a.75.75 0 011.1 0l3.25 3.5a.75.75 0 01-1.1 1.02l-1.95-2.1v12.59A.75.75 0 0110 18z" clip-rule="evenodd"/>
                        </svg>
                        <span class="vp-sr-only"><?php esc_html_e('Send', 'chatprojects'); ?></span>
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php
});
