<?php
/**
 * Settings Panel Partial
 *
 * Embedded settings interface for main app shell
 *
 * @package ChatProjects
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

call_user_func(function () {
$user_id = get_current_user_id();
$current_user = wp_get_current_user();

// Get user preferences
$theme_preference = get_user_meta($user_id, 'cp_theme_preference', true) ?: 'auto';

// Check if user can manage settings (admin)
$can_manage_settings = current_user_can('manage_options');

// Get API key status (masked)
$openai_key = get_option('chatprojects_openai_key', '');
$anthropic_key = get_option('chatprojects_anthropic_key', '');
$gemini_key = get_option('chatprojects_gemini_key', '');
$chutes_key = get_option('chatprojects_chutes_key', '');
$openrouter_key = get_option('chatprojects_openrouter_key', '');

$openai_configured = !empty($openai_key);
$anthropic_configured = !empty($anthropic_key);
$gemini_configured = !empty($gemini_key);
$chutes_configured = !empty($chutes_key);
$openrouter_configured = !empty($openrouter_key);
?>

<div
    class="vp-settings-panel"
    style="padding: 1.5rem; max-width: 800px; margin: 0 auto;"
    x-data="{
        theme: '<?php echo esc_js($theme_preference); ?>',
        saving: false,
        message: '',
        messageType: '',

        async saveTheme() {
            this.saving = true;
            try {
                const response = await fetch(chatprData.ajax_url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'chatpr_save_theme_preference',
                        nonce: chatprData.nonce,
                        theme: this.theme
                    })
                });
                const data = await response.json();
                if (data.success) {
                    this.message = '<?php esc_attr_e('Settings saved successfully', 'chatprojects'); ?>';
                    this.messageType = 'success';
                    // Apply theme
                    document.querySelector('.vp-main-app-container')?.classList.toggle('dark', this.theme === 'dark');
                } else {
                    throw new Error(data.data?.message || 'Failed to save');
                }
            } catch (error) {
                this.message = error.message;
                this.messageType = 'error';
            } finally {
                this.saving = false;
                setTimeout(() => { this.message = ''; }, 3000);
            }
        }
    }"
>
    <h2 style="margin: 0 0 1.5rem; font-size: 1.5rem; font-weight: 600; color: var(--vp-text);">
        <?php esc_html_e('Settings', 'chatprojects'); ?>
    </h2>

    <!-- Status Message -->
    <template x-if="message">
        <div
            :style="{
                padding: '0.75rem 1rem',
                borderRadius: '8px',
                marginBottom: '1rem',
                background: messageType === 'success' ? '#ecfdf5' : '#fef2f2',
                color: messageType === 'success' ? '#065f46' : '#991b1b',
                border: '1px solid ' + (messageType === 'success' ? '#a7f3d0' : '#fecaca')
            }"
            x-text="message"
        ></div>
    </template>

    <!-- User Profile Section -->
    <section style="background: var(--vp-surface); border-radius: 12px; border: 1px solid var(--vp-border); padding: 1.5rem; margin-bottom: 1.5rem;">
        <h3 style="margin: 0 0 1rem; font-size: 1rem; font-weight: 600; color: var(--vp-text);">
            <?php esc_html_e('Profile', 'chatprojects'); ?>
        </h3>

        <div style="display: flex; align-items: center; gap: 1rem;">
            <div style="width: 56px; height: 56px; border-radius: 50%; background: linear-gradient(135deg, #2563eb, #7c3aed); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.25rem; font-weight: 600;">
                <?php echo esc_html(strtoupper(substr($current_user->display_name, 0, 1))); ?>
            </div>
            <div>
                <p style="margin: 0; font-weight: 600; color: var(--vp-text);">
                    <?php echo esc_html($current_user->display_name); ?>
                </p>
                <p style="margin: 0.25rem 0 0; font-size: 0.875rem; color: var(--vp-text-muted);">
                    <?php echo esc_html($current_user->user_email); ?>
                </p>
            </div>
        </div>
    </section>

    <!-- Appearance Section -->
    <section style="background: var(--vp-surface); border-radius: 12px; border: 1px solid var(--vp-border); padding: 1.5rem; margin-bottom: 1.5rem;">
        <h3 style="margin: 0 0 1rem; font-size: 1rem; font-weight: 600; color: var(--vp-text);">
            <?php esc_html_e('Appearance', 'chatprojects'); ?>
        </h3>

        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <label style="display: block; font-size: 0.875rem; color: var(--vp-text-muted); margin-bottom: 0.5rem;">
                <?php esc_html_e('Theme', 'chatprojects'); ?>
            </label>
            <div style="display: flex; gap: 0.75rem;">
                <label
                    style="flex: 1; display: flex; align-items: center; gap: 0.75rem; padding: 1rem; border: 2px solid var(--vp-border); border-radius: 8px; cursor: pointer; transition: border-color 0.15s;"
                    :style="{ borderColor: theme === 'light' ? 'var(--vp-primary)' : 'var(--vp-border)' }"
                >
                    <input type="radio" name="theme" value="light" x-model="theme" @change="saveTheme()" style="display: none;">
                    <svg class="w-5 h-5" style="color: #f59e0b;" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2.25a.75.75 0 01.75.75v2.25a.75.75 0 01-1.5 0V3a.75.75 0 01.75-.75zM7.5 12a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM18.894 6.166a.75.75 0 00-1.06-1.06l-1.591 1.59a.75.75 0 101.06 1.061l1.591-1.59zM21.75 12a.75.75 0 01-.75.75h-2.25a.75.75 0 010-1.5H21a.75.75 0 01.75.75zM17.834 18.894a.75.75 0 001.06-1.06l-1.59-1.591a.75.75 0 10-1.061 1.06l1.59 1.591zM12 18a.75.75 0 01.75.75V21a.75.75 0 01-1.5 0v-2.25A.75.75 0 0112 18zM7.758 17.303a.75.75 0 00-1.061-1.06l-1.591 1.59a.75.75 0 001.06 1.061l1.591-1.59zM6 12a.75.75 0 01-.75.75H3a.75.75 0 010-1.5h2.25A.75.75 0 016 12zM6.697 7.757a.75.75 0 001.06-1.06l-1.59-1.591a.75.75 0 00-1.061 1.06l1.59 1.591z"/>
                    </svg>
                    <span style="color: var(--vp-text); font-size: 0.875rem; font-weight: 500;"><?php esc_html_e('Light', 'chatprojects'); ?></span>
                </label>

                <label
                    style="flex: 1; display: flex; align-items: center; gap: 0.75rem; padding: 1rem; border: 2px solid var(--vp-border); border-radius: 8px; cursor: pointer; transition: border-color 0.15s;"
                    :style="{ borderColor: theme === 'dark' ? 'var(--vp-primary)' : 'var(--vp-border)' }"
                >
                    <input type="radio" name="theme" value="dark" x-model="theme" @change="saveTheme()" style="display: none;">
                    <svg class="w-5 h-5" style="color: #6366f1;" fill="currentColor" viewBox="0 0 24 24">
                        <path fill-rule="evenodd" d="M9.528 1.718a.75.75 0 01.162.819A8.97 8.97 0 009 6a9 9 0 009 9 8.97 8.97 0 003.463-.69.75.75 0 01.981.98 10.503 10.503 0 01-9.694 6.46c-5.799 0-10.5-4.701-10.5-10.5 0-4.368 2.667-8.112 6.46-9.694a.75.75 0 01.818.162z" clip-rule="evenodd"/>
                    </svg>
                    <span style="color: var(--vp-text); font-size: 0.875rem; font-weight: 500;"><?php esc_html_e('Dark', 'chatprojects'); ?></span>
                </label>

                <label
                    style="flex: 1; display: flex; align-items: center; gap: 0.75rem; padding: 1rem; border: 2px solid var(--vp-border); border-radius: 8px; cursor: pointer; transition: border-color 0.15s;"
                    :style="{ borderColor: theme === 'auto' ? 'var(--vp-primary)' : 'var(--vp-border)' }"
                >
                    <input type="radio" name="theme" value="auto" x-model="theme" @change="saveTheme()" style="display: none;">
                    <svg class="w-5 h-5" style="color: #10b981;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    <span style="color: var(--vp-text); font-size: 0.875rem; font-weight: 500;"><?php esc_html_e('System', 'chatprojects'); ?></span>
                </label>
            </div>
        </div>
    </section>

    <!-- API Status Section -->
    <section style="background: var(--vp-surface); border-radius: 12px; border: 1px solid var(--vp-border); padding: 1.5rem; margin-bottom: 1.5rem;">
        <h3 style="margin: 0 0 1rem; font-size: 1rem; font-weight: 600; color: var(--vp-text);">
            <?php esc_html_e('AI Provider Status', 'chatprojects'); ?>
        </h3>

        <div style="display: grid; gap: 0.75rem;">
            <?php
            $providers_status = array(
                array('name' => 'OpenAI', 'configured' => $openai_configured),
                array('name' => 'Anthropic', 'configured' => $anthropic_configured),
                array('name' => 'Google Gemini', 'configured' => $gemini_configured),
                array('name' => 'Chutes', 'configured' => $chutes_configured),
                array('name' => 'OpenRouter', 'configured' => $openrouter_configured),
            );

            foreach ($providers_status as $provider) :
            ?>
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem; background: var(--vp-bg); border-radius: 6px;">
                <span style="font-size: 0.875rem; color: var(--vp-text);">
                    <?php echo esc_html($provider['name']); ?>
                </span>
                <span style="display: flex; align-items: center; gap: 0.375rem; font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: 4px; <?php echo $provider['configured'] ? 'background: #ecfdf5; color: #065f46;' : 'background: #fef2f2; color: #991b1b;'; ?>">
                    <?php if ($provider['configured']) : ?>
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                    <?php esc_html_e('Configured', 'chatprojects'); ?>
                    <?php else : ?>
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                    <?php esc_html_e('Not configured', 'chatprojects'); ?>
                    <?php endif; ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($can_manage_settings) : ?>
        <p style="margin: 1rem 0 0; font-size: 0.75rem; color: var(--vp-text-muted);">
            <?php esc_html_e('API keys can be configured in the WordPress admin settings.', 'chatprojects'); ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=chatprojects-settings')); ?>" target="_blank" style="color: var(--vp-primary);">
                <?php esc_html_e('Go to Settings', 'chatprojects'); ?>
            </a>
        </p>
        <?php endif; ?>
    </section>

    <!-- About Section -->
    <section style="background: var(--vp-surface); border-radius: 12px; border: 1px solid var(--vp-border); padding: 1.5rem;">
        <h3 style="margin: 0 0 1rem; font-size: 1rem; font-weight: 600; color: var(--vp-text);">
            <?php esc_html_e('About', 'chatprojects'); ?>
        </h3>

        <div style="font-size: 0.875rem; color: var(--vp-text-muted);">
            <p style="margin: 0 0 0.5rem;">
                <strong style="color: var(--vp-text);">ChatProjects</strong>
                <?php if (defined('CHATPROJECTS_PRO_VERSION')) : ?>
                    <span style="font-size: 0.625rem; font-weight: 600; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: white; padding: 0.125rem 0.375rem; border-radius: 4px; margin-left: 0.25rem;">PRO</span>
                <?php endif; ?>
            </p>
            <p style="margin: 0 0 0.5rem;">
                <?php esc_html_e('Version:', 'chatprojects'); ?>
                <?php echo esc_html(defined('CHATPROJECTS_VERSION') ? CHATPROJECTS_VERSION : '1.0.0'); ?>
            </p>
            <p style="margin: 0;">
                <?php esc_html_e('AI-powered project management with multi-provider support.', 'chatprojects'); ?>
            </p>
        </div>
    </section>
<?php
});
