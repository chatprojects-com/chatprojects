<?php
/**
 * User Settings Page Template
 *
 * Frontend-only settings page for Projects Users
 *
 * @package ChatProjects
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Require login
if (!is_user_logged_in()) {
    auth_redirect();
    exit;
}

$current_user = wp_get_current_user();
$user_id = get_current_user_id();
$theme_preference = get_user_meta($user_id, 'cp_theme_preference', true) ?: 'auto';

// API key status
$openai_configured = !empty(get_option('chatprojects_openai_key', ''));
$anthropic_configured = !empty(get_option('chatprojects_anthropic_key', ''));
$gemini_configured = !empty(get_option('chatprojects_gemini_key', ''));
$chutes_configured = !empty(get_option('chatprojects_chutes_key', ''));
$openrouter_configured = !empty(get_option('chatprojects_openrouter_key', ''));
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> class="<?php echo $theme_preference === 'dark' ? 'dark' : ''; ?>">
<?php
// Apply theme from localStorage immediately (handles back-button cache)
// Using wp_print_inline_script_tag for WordPress guidelines compliance
$theme_init_script = "(function() {
    var storedTheme = localStorage.getItem('cp_theme_preference');
    var theme = storedTheme || '" . esc_js($theme_preference) . "';
    if (theme === 'dark') {
        document.documentElement.classList.add('dark');
    } else if (theme === 'auto' && window.matchMedia('(prefers-color-scheme:dark)').matches) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
})();";
wp_print_inline_script_tag($theme_init_script, array('id' => 'chatprojects-theme-init'));
?>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php esc_html_e('Settings', 'chatprojects'); ?> - <?php bloginfo('name'); ?></title>
    <?php
    // Output chatprData inline to ensure it's always available
    $chatpr_inline_data = array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('chatpr_ajax_nonce'),
        'current_user' => $current_user->display_name,
    );
    // Output chatprData via wp_print_inline_script_tag for WordPress guidelines compliance
    $chatpr_data_script = 'var chatprData = ' . wp_json_encode($chatpr_inline_data) . ';';
    wp_print_inline_script_tag($chatpr_data_script, array('id' => 'chatprojects-inline-data'));

    wp_head();
    ?>
</head>
<body class="bg-gray-100 dark:bg-dark-bg" data-theme="<?php echo esc_attr($theme_preference); ?>">
    <!-- Header -->
    <header class="bg-white dark:bg-dark-surface shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-4">
                <a href="<?php echo esc_url(home_url('/projects/')); ?>" class="text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </a>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white"><?php esc_html_e('Settings', 'chatprojects'); ?></h1>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-sm text-gray-600 dark:text-gray-300"><?php echo esc_html($current_user->display_name); ?></span>
                <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="text-sm text-red-600 hover:text-red-700">
                    <?php esc_html_e('Logout', 'chatprojects'); ?>
                </a>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-12 gap-8">
            <!-- Sidebar Navigation -->
            <div class="col-span-12 md:col-span-3">
                <nav class="bg-white dark:bg-dark-surface rounded-lg shadow-sm overflow-hidden" x-data="{ activeTab: 'profile' }">
                    <button
                        @click="activeTab = 'profile'"
                        class="w-full text-left px-4 py-3 flex items-center space-x-3 focus:outline-none border-l-4 transition-colors duration-200"
                        x-bind:class="{
                            'bg-blue-50 text-blue-700 border-blue-600': activeTab === 'profile',
                            'dark:bg-dark-elevated dark:text-blue-400': activeTab === 'profile',
                            'text-gray-700 border-transparent': activeTab !== 'profile',
                            'dark:text-gray-300': activeTab !== 'profile',
                            'hover:bg-gray-50 hover:border-gray-300': activeTab !== 'profile',
                            'dark:hover:bg-dark-elevated dark:hover:border-gray-600': activeTab !== 'profile'
                        }"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        <span class="font-medium"><?php esc_html_e('Profile', 'chatprojects'); ?></span>
                    </button>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-span-12 md:col-span-9">
                <form id="settings-form" class="space-y-6">
                    <!-- Profile Tab -->
                    <div x-show="activeTab === 'profile'" class="bg-white dark:bg-dark-surface rounded-lg shadow-sm p-6 space-y-6">
                        <div>
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4"><?php esc_html_e('Profile Information', 'chatprojects'); ?></h2>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-6"><?php esc_html_e('Update your account profile information.', 'chatprojects'); ?></p>
                        </div>

                        <!-- Display Name -->
                        <div>
                            <label for="display_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                <?php esc_html_e('Display Name', 'chatprojects'); ?>
                            </label>
                            <input
                                type="text"
                                id="display_name"
                                name="display_name"
                                value="<?php echo esc_attr($current_user->display_name); ?>"
                                class="w-full px-4 py-2 border border-gray-300 dark:border-dark-border rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-dark-bg dark:text-white"
                            >
                        </div>

                        <!-- Email -->
                        <div>
                            <label for="user_email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                <?php esc_html_e('Email Address', 'chatprojects'); ?>
                            </label>
                            <input
                                type="email"
                                id="user_email"
                                name="user_email"
                                value="<?php echo esc_attr($current_user->user_email); ?>"
                                class="w-full px-4 py-2 border border-gray-300 dark:border-dark-border rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-dark-bg dark:text-white"
                            >
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                <?php esc_html_e('Your email address for notifications and account recovery.', 'chatprojects'); ?>
                            </p>
                        </div>

                        <!-- Appearance / Theme -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                                <?php esc_html_e('Appearance', 'chatprojects'); ?>
                            </label>
                            <!-- Hidden input outside Alpine scope for reliable form submission -->
                            <input type="hidden" name="theme_preference" id="theme_preference_input" value="<?php echo esc_attr($theme_preference); ?>">
                            <div class="flex gap-3" x-data="{ theme: localStorage.getItem('cp_theme_preference') || '<?php echo esc_js($theme_preference); ?>' }" x-init="document.getElementById('theme_preference_input').value = theme">
                                <div
                                    @click="theme = 'light'; document.getElementById('theme_preference_input').value = 'light'; $dispatch('theme-changed', 'light')"
                                    class="flex-1 flex items-center gap-2 p-3 border-2 rounded-lg cursor-pointer transition-colors"
                                    :class="theme === 'light' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-dark-border hover:border-gray-300'"
                                >
                                    <svg class="w-5 h-5 text-amber-500" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 2.25a.75.75 0 01.75.75v2.25a.75.75 0 01-1.5 0V3a.75.75 0 01.75-.75zM7.5 12a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM18.894 6.166a.75.75 0 00-1.06-1.06l-1.591 1.59a.75.75 0 101.06 1.061l1.591-1.59zM21.75 12a.75.75 0 01-.75.75h-2.25a.75.75 0 010-1.5H21a.75.75 0 01.75.75zM17.834 18.894a.75.75 0 001.06-1.06l-1.59-1.591a.75.75 0 10-1.061 1.06l1.59 1.591zM12 18a.75.75 0 01.75.75V21a.75.75 0 01-1.5 0v-2.25A.75.75 0 0112 18zM7.758 17.303a.75.75 0 00-1.061-1.06l-1.591 1.59a.75.75 0 001.06 1.061l1.591-1.59zM6 12a.75.75 0 01-.75.75H3a.75.75 0 010-1.5h2.25A.75.75 0 016 12zM6.697 7.757a.75.75 0 001.06-1.06l-1.59-1.591a.75.75 0 00-1.061 1.06l1.59 1.591z"/>
                                    </svg>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300"><?php esc_html_e('Light', 'chatprojects'); ?></span>
                                </div>

                                <div
                                    @click="theme = 'dark'; document.getElementById('theme_preference_input').value = 'dark'; $dispatch('theme-changed', 'dark')"
                                    class="flex-1 flex items-center gap-2 p-3 border-2 rounded-lg cursor-pointer transition-colors"
                                    :class="theme === 'dark' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-dark-border hover:border-gray-300'"
                                >
                                    <svg class="w-5 h-5 text-indigo-500" fill="currentColor" viewBox="0 0 24 24">
                                        <path fill-rule="evenodd" d="M9.528 1.718a.75.75 0 01.162.819A8.97 8.97 0 009 6a9 9 0 009 9 8.97 8.97 0 003.463-.69.75.75 0 01.981.98 10.503 10.503 0 01-9.694 6.46c-5.799 0-10.5-4.701-10.5-10.5 0-4.368 2.667-8.112 6.46-9.694a.75.75 0 01.818.162z" clip-rule="evenodd"/>
                                    </svg>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300"><?php esc_html_e('Dark', 'chatprojects'); ?></span>
                                </div>

                                <div
                                    @click="theme = 'auto'; document.getElementById('theme_preference_input').value = 'auto'; $dispatch('theme-changed', 'auto')"
                                    class="flex-1 flex items-center gap-2 p-3 border-2 rounded-lg cursor-pointer transition-colors"
                                    :class="theme === 'auto' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-dark-border hover:border-gray-300'"
                                >
                                    <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300"><?php esc_html_e('System', 'chatprojects'); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Save Button -->
                        <div class="pt-4 border-t border-gray-200 dark:border-dark-border">
                            <button
                                type="submit"
                                id="save-profile-btn"
                                class="btn-primary px-6 py-2 inline-flex items-center gap-2"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span><?php esc_html_e('Save Changes', 'chatprojects'); ?></span>
                            </button>
                        </div>
                    </div>

                </form>

                <!-- API Provider Status -->
                <div class="bg-white dark:bg-dark-surface rounded-lg shadow-sm p-6 mt-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        <?php esc_html_e('AI Provider Status', 'chatprojects'); ?>
                    </h3>
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                        <?php
                        $providers = array(
                            array('name' => 'OpenAI', 'configured' => $openai_configured),
                            array('name' => 'Anthropic', 'configured' => $anthropic_configured),
                            array('name' => 'Gemini', 'configured' => $gemini_configured),
                            array('name' => 'Chutes', 'configured' => $chutes_configured),
                            array('name' => 'OpenRouter', 'configured' => $openrouter_configured),
                        );
                        foreach ($providers as $provider) :
                        ?>
                        <div class="flex items-center gap-2 px-3 py-2 rounded-lg <?php echo $provider['configured'] ? 'bg-green-50 dark:bg-green-900/20' : 'bg-gray-50 dark:bg-dark-elevated'; ?>">
                            <?php if ($provider['configured']) : ?>
                            <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            <?php else : ?>
                            <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                            <?php endif; ?>
                            <span class="text-sm <?php echo $provider['configured'] ? 'text-green-700 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'; ?>">
                                <?php echo esc_html($provider['name']); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (current_user_can('manage_options')) : ?>
                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                        <?php esc_html_e('API keys can be configured in', 'chatprojects'); ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=chatprojects-settings')); ?>" class="text-blue-600 hover:text-blue-700">
                            <?php esc_html_e('Settings', 'chatprojects'); ?>
                        </a>
                    </p>
                    <?php endif; ?>
                </div>

                <!-- About -->
                <div class="bg-white dark:bg-dark-surface rounded-lg shadow-sm p-6 mt-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                        <?php esc_html_e('About', 'chatprojects'); ?>
                    </h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        <strong class="text-gray-900 dark:text-white">ChatProjects</strong>
                        <?php if (defined('CHATPROJECTS_PRO_VERSION')) : ?>
                        <span class="ml-1 text-xs font-semibold text-white px-1.5 py-0.5 rounded" style="background: linear-gradient(135deg, #8b5cf6, #6366f1);">PRO</span>
                        <?php endif; ?>
                        <span class="ml-2"><?php esc_html_e('Version', 'chatprojects'); ?> <?php echo esc_html(defined('CHATPROJECTS_VERSION') ? CHATPROJECTS_VERSION : '1.0.0'); ?></span>
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        <?php esc_html_e('AI-powered chat and project management with multi-provider support.', 'chatprojects'); ?>
                    </p>
                </div>

                <?php if (!defined('CHATPROJECTS_PRO_VERSION')) : ?>
                <!-- Upgrade to Pro - Minimal Bar -->
                <div class="mt-6 px-4 py-3 rounded-lg flex items-center justify-between" style="background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(99, 102, 241, 0.1));">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                        </svg>
                        <span class="text-sm text-gray-700 dark:text-gray-300">
                            <?php esc_html_e('Unlock teams, spreadsheet import, image studio and more with Pro', 'chatprojects'); ?>
                        </span>
                    </div>
                    <a href="<?php echo esc_url(apply_filters('chatprojects_upgrade_url', 'https://chatprojects.com/')); ?>"
                       target="_blank"
                       rel="noopener"
                       class="text-sm font-medium text-purple-600 hover:text-purple-700 whitespace-nowrap">
                        <?php esc_html_e('Learn More', 'chatprojects'); ?> &rarr;
                    </a>
                </div>
                <?php endif; ?>

                <!-- Success Message -->
                <div
                    id="success-message"
                    class="hidden fixed bottom-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg flex items-center space-x-2 transition-opacity duration-300"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span><?php esc_html_e('Settings saved successfully!', 'chatprojects'); ?></span>
                </div>

                <!-- Error Message -->
                <div
                    id="error-message"
                    class="hidden fixed bottom-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg flex items-center space-x-2 transition-opacity duration-300"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    <span id="error-text"><?php esc_html_e('Failed to save settings.', 'chatprojects'); ?></span>
                </div>
            </div>
        </div>
    </div>

    <?php
    // Settings form script - using wp_print_inline_script_tag for WordPress compliance
    $saving_text = esc_js(__('Saving...', 'chatprojects'));
    $settings_script = "// Form submission
        document.getElementById('settings-form').addEventListener('submit', async function(e) {
            e.preventDefault();

            // Get the submit button that was clicked
            const submitBtn = document.activeElement.closest('button[type=\"submit\"]') ||
                              this.querySelector('button[type=\"submit\"]');
            const originalBtnText = submitBtn ? submitBtn.innerHTML : '';

            // Show loading state
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<svg class=\"w-5 h-5 animate-spin\" fill=\"none\" viewBox=\"0 0 24 24\"><circle class=\"opacity-25\" cx=\"12\" cy=\"12\" r=\"10\" stroke=\"currentColor\" stroke-width=\"4\"></circle><path class=\"opacity-75\" fill=\"currentColor\" d=\"M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z\"></path></svg><span>{$saving_text}</span>';
            }

            const formData = new FormData(this);
            formData.append('action', 'chatpr_update_user_settings');
            formData.append('nonce', chatprData.nonce);

            try {
                const response = await fetch(chatprData.ajax_url, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    // Store theme in localStorage for cross-page sync
                    const savedTheme = document.getElementById('theme_preference_input').value;
                    localStorage.setItem('cp_theme_preference', savedTheme);

                    // Show success message
                    const successMsg = document.getElementById('success-message');
                    successMsg.classList.remove('hidden');
                    setTimeout(() => successMsg.classList.add('hidden'), 3000);
                } else {
                    // Show error message
                    const errorMsg = document.getElementById('error-message');
                    document.getElementById('error-text').textContent = data.data?.message || 'Failed to save settings.';
                    errorMsg.classList.remove('hidden');
                    setTimeout(() => errorMsg.classList.add('hidden'), 3000);
                }
            } catch (error) {
                const errorMsg = document.getElementById('error-message');
                errorMsg.classList.remove('hidden');
                setTimeout(() => errorMsg.classList.add('hidden'), 3000);
            } finally {
                // Restore button state
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                }
            }
        });";
    wp_print_inline_script_tag($settings_script, array('id' => 'chatprojects-settings-form'));

    // Theme application script
    $theme_script = "// Apply theme immediately when changed
        function applyTheme(theme) {
            const html = document.documentElement;
            document.body.dataset.theme = theme;

            if (theme === 'dark') {
                html.classList.add('dark');
            } else if (theme === 'light') {
                html.classList.remove('dark');
            } else {
                // Auto - check system preference
                if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    html.classList.add('dark');
                } else {
                    html.classList.remove('dark');
                }
            }
        }

        // Listen for Alpine.js theme-changed custom event
        document.addEventListener('theme-changed', function(e) {
            applyTheme(e.detail);
        });";
    wp_print_inline_script_tag($theme_script, array('id' => 'chatprojects-theme-toggle'));
    ?>

    <?php wp_footer(); ?>
</body>
</html>
