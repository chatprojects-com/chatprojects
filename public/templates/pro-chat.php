<?php
/**
 * Pro Chat Template
 *
 * Standalone chat interface for multi-provider chat without project context
 *
 * @package ChatProjects
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

call_user_func(function () {
    if (!is_user_logged_in()) {
        auth_redirect();
        exit;
    }

    $current_user = wp_get_current_user();

    // Get theme preference
    $theme_preference = get_user_meta(get_current_user_id(), 'cp_theme_preference', true) ?: 'auto';
    $dark_class = $theme_preference === 'dark' ? 'dark' : '';
    ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> class="h-full <?php echo esc_attr($dark_class); ?>">
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover, interactive-widget=resizes-content">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title><?php esc_html_e('Chat', 'chatprojects'); ?> - <?php bloginfo('name'); ?></title>
    <?php
    // Output chatprData directly in template to ensure it's always available
    // This bypasses wp_localize_script which can fail on page navigation
    $chatpr_inline_data = array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('chatpr_ajax_nonce'),
        'current_user' => $current_user->display_name,
        'default_provider' => get_option('chatprojects_general_chat_provider', 'openai'),
        'default_model' => get_option('chatprojects_general_chat_model', 'gpt-4o'),
        'is_pro_user' => false,
        'max_images_per_message' => 1,
        'max_image_size' => \ChatProjects\Security::get_max_image_upload_size(),
        'i18n' => array(
            'sending' => __('Sending...', 'chatprojects'),
            'error' => __('An error occurred. Please try again.', 'chatprojects'),
            'transcribing' => __('Transcribing...', 'chatprojects'),
            'uploading' => __('Uploading...', 'chatprojects'),
            'copied' => __('Copied to clipboard', 'chatprojects'),
            'copy_failed' => __('Failed to copy', 'chatprojects'),
            'provider_switch_confirm' => __('Switching providers will create a new conversation. Continue?', 'chatprojects'),
            /* translators: %s: maximum file size in megabytes */
            'image_too_large' => __('Image is too large. Maximum size is %s MB.', 'chatprojects'),
            'invalid_image_type' => __('Invalid image type. Allowed: JPEG, PNG, GIF, WebP.', 'chatprojects'),
            'max_images_reached' => __('Maximum number of images reached.', 'chatprojects'),
        ),
    );

    // Output chatprData as inline script (must be before external patches script)
    $chatpr_data_json = wp_json_encode($chatpr_inline_data);
    $chatpr_data_script = 'var chatprData = ' . $chatpr_data_json . ';';
    wp_print_inline_script_tag($chatpr_data_script, array('id' => 'chatprojects-data'));

    // Register and enqueue external patches script (handles Alpine patches, nonce refresh, title updates)
    $patches_version = CHATPROJECTS_VERSION;
    if (file_exists(CHATPROJECTS_PLUGIN_DIR . 'assets/js/pro-chat-patches.js')) {
        $patches_version .= '-' . filemtime(CHATPROJECTS_PLUGIN_DIR . 'assets/js/pro-chat-patches.js');
    }
    wp_register_script(
        'chatprojects-patches',
        CHATPROJECTS_PLUGIN_URL . 'assets/js/pro-chat-patches.js',
        array(),
        $patches_version,
        false // Load in head, not footer
    );
    wp_enqueue_script('chatprojects-patches');

    wp_head();
    ?>
</head>

<body class="h-full overflow-hidden bg-neutral-50 dark:bg-dark-bg antialiased" x-data="{ sidebarOpen: window.innerWidth >= 768 }" @close-sidebar.window="sidebarOpen = false">

    <div class="flex h-full" data-pro-chat="true">

        <!-- Sidebar -->
        <aside
            x-show="sidebarOpen"
            :class="{ 'mobile-open': sidebarOpen }"
            x-transition:enter="transform transition ease-in-out duration-300"
            x-transition:enter-start="-translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transform transition ease-in-out duration-300"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="-translate-x-full"
            class="vp-chat-sidebar fixed inset-y-0 left-0 z-40 max-w-[calc(100vw-60px)] bg-sidebar-light dark:bg-dark-bg border-r border-sidebar-border dark:border-dark-border flex flex-col lg:relative lg:translate-x-0"
            x-data="sidebar()"
        >
            <!-- Sidebar Header: ChatProjects Branding -->
            <div class="cp-sidebar-header">
                <a href="<?php echo esc_url(home_url('/projects/')); ?>" class="cp-logo">
                    <span class="cp-logo-chat">Chat</span><span class="cp-logo-projects">Projects</span>
                </a>
                <div class="cp-nav-icons">
                    <!-- Projects (Grid) -->
                    <a href="<?php echo esc_url(home_url('/projects/')); ?>" class="cp-nav-icon" title="<?php esc_attr_e('Projects', 'chatprojects'); ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                        </svg>
                    </a>
                    <!-- Chat (Active) -->
                    <a href="<?php echo esc_url(home_url('/pro-chat/')); ?>" class="cp-nav-icon active" title="<?php esc_attr_e('Chat', 'chatprojects'); ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                    </a>
                </div>
            </div>

            <!-- New Chat Button -->
            <div class="px-4 pt-4 pb-2">
                <button
                    @click="window.dispatchEvent(new CustomEvent('vp:chat:new'))"
                    class="w-full flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors shadow-sm"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span><?php esc_html_e('New Chat', 'chatprojects'); ?></span>
                </button>
            </div>

            <!-- Chat History (Scrollable) -->
            <div class="flex-1 overflow-y-auto px-2 mt-2" x-data="chatHistory(null, 'general')">
                <!-- Chats Header -->
                <div class="px-3 pt-1 pb-2 mb-1">
                    <h3 class="text-xs font-semibold text-sidebar-text dark:text-white uppercase tracking-wider"><?php esc_html_e('Chat History', 'chatprojects'); ?></h3>
                </div>

                <div id="vp-chat-history" class="space-y-0.5 pb-4">
                    <!-- Loading state -->
                    <template x-if="loading && chats.length === 0">
                        <div class="flex items-center justify-center py-8 text-sidebar-textMuted dark:text-neutral-500 text-sm">
                            <svg class="animate-spin h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <?php esc_html_e('Loading chats...', 'chatprojects'); ?>
                        </div>
                    </template>

                    <!-- Empty state -->
                    <template x-if="!loading && chats.length === 0">
                        <div class="flex flex-col items-center justify-center py-8 text-sidebar-textMuted dark:text-neutral-500 text-sm">
                            <svg class="w-8 h-8 mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                            </svg>
                            <p><?php esc_html_e('No chats yet', 'chatprojects'); ?></p>
                            <p class="text-xs mt-1"><?php esc_html_e('Start a new Chat', 'chatprojects'); ?></p>
                        </div>
                    </template>

                    <!-- Chat list grouped by date -->
                    <template x-for="group in groupedChats" :key="group.name">
                        <div class="mb-3">
                            <!-- Date group heading -->
                            <div class="px-3 py-0.5 mb-0.5">
                                <h4 class="text-xs font-medium text-sidebar-textMuted dark:text-neutral-500" x-text="group.name"></h4>
                            </div>

                            <!-- Chats in this group -->
                            <template x-for="chat in group.chats" :key="chat.id">
                                <div
                                    @click="switchChat(chat.id); if (window.innerWidth < 768) $dispatch('close-sidebar')"
                                    :class="{
                                        'bg-sidebar-active dark:bg-dark-hover': activeThreadId === chat.id,
                                        'hover:bg-sidebar-hover dark:hover:bg-dark-hover': activeThreadId !== chat.id
                                    }"
                                    class="group relative flex items-center gap-2 px-3 py-1.5 rounded-md cursor-pointer transition-colors"
                                >
                                    <!-- Chat content -->
                                    <div class="flex-1 min-w-0">
                                        <!-- Editing mode -->
                                        <template x-if="editingChatId === chat.id">
                                            <div @click.stop>
                                                <input
                                                    type="text"
                                                    x-model="editingTitle"
                                                    @keydown.enter="saveTitle(chat.id, $event)"
                                                    @keydown.escape="cancelEditing()"
                                                    @blur="cancelEditing()"
                                                    class="w-full px-2 py-1 text-sm bg-gray-50 dark:bg-dark-bg border border-gray-300 dark:border-dark-border rounded text-sidebar-text dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500"
                                                    x-init="$el.focus(); $el.select();"
                                                />
                                            </div>
                                        </template>

                                        <!-- Display mode -->
                                        <template x-if="editingChatId !== chat.id">
                                            <div>
                                                <p class="text-sm text-sidebar-text dark:text-white truncate" x-text="getChatTitle(chat)"></p>
                                            </div>
                                        </template>
                                    </div>

                                    <!-- Action buttons (show on hover) -->
                                    <div class="flex-shrink-0 flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <!-- Edit button -->
                                        <button
                                            @click.stop="startEditingChat(chat, $event)"
                                            class="p-1 hover:bg-sidebar-hover dark:hover:bg-dark-bg rounded"
                                            title="<?php esc_attr_e('Rename chat', 'chatprojects'); ?>"
                                        >
                                            <svg class="w-3.5 h-3.5 text-sidebar-textLight dark:text-neutral-400 hover:text-sidebar-text dark:hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                            </svg>
                                        </button>

                                        <!-- Delete button -->
                                        <button
                                            @click.stop="deleteChat(chat.id, $event)"
                                            class="p-1 hover:bg-red-100 dark:hover:bg-red-900/30 rounded"
                                            title="<?php esc_attr_e('Delete chat', 'chatprojects'); ?>"
                                        >
                                            <svg class="w-3.5 h-3.5 text-sidebar-textLight dark:text-neutral-400 hover:text-red-600 dark:hover:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Sidebar Footer -->
            <div class="vp-sidebar-footer">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <!-- Theme Toggle -->
                    <button
                        @click="window.VPTheme?.toggle()"
                        class="vp-nav-icon"
                        title="<?php esc_attr_e('Toggle theme', 'chatprojects'); ?>"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                        </svg>
                    </button>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <!-- Settings -->
                        <a href="<?php echo esc_url(home_url('/settings/')); ?>" class="vp-nav-icon" title="<?php esc_attr_e('Settings', 'chatprojects'); ?>">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </a>
                        <!-- Logout -->
                        <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="vp-nav-icon" title="<?php esc_attr_e('Logout', 'chatprojects'); ?>">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="flex-1 flex flex-col h-full min-w-0">
            <!-- Mobile Header (shows hamburger on mobile) -->
            <header class="vp-mobile-header">
                <button @click="sidebarOpen = !sidebarOpen" class="vp-mobile-header-btn" aria-label="<?php esc_attr_e('Toggle menu', 'chatprojects'); ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                <a href="<?php echo esc_url(home_url('/projects/')); ?>" class="vp-mobile-header-logo">
                    <span class="cp-logo-chat">Chat</span><span class="cp-logo-projects">Projects</span>
                </a>
            </header>

            <!-- Chat Interface -->
            <div class="flex-1 overflow-hidden">
                <div class="h-full">
                    <?php
                    // Set context for Pro Chat mode
                    $chatpr_project_id = null;
                    $cp_chat_mode = 'general';
                    include CHATPROJECTS_PLUGIN_DIR . 'public/templates/chat-interface-modern.php';
                    ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Mobile Sidebar Overlay -->
    <div
        x-show="sidebarOpen && window.innerWidth < 1024"
        @click="sidebarOpen = false"
        x-transition:enter="transition-opacity ease-linear duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-linear duration-300"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-30 bg-black/50 backdrop-blur-sm lg:hidden"
        x-cloak
    ></div>

    <?php
    // Mobile Keyboard Detection Script
    $keyboard_script = "(function() {
        if (window.visualViewport && window.innerWidth < 768) {
            let initialHeight = window.visualViewport.height;
            let isKeyboardOpen = false;
            window.visualViewport.addEventListener('resize', function() {
                const currentHeight = window.visualViewport.height;
                const heightDiff = initialHeight - currentHeight;
                if (heightDiff > 150 && !isKeyboardOpen) {
                    isKeyboardOpen = true;
                    document.body.classList.add('vp-keyboard-open');
                    const input = document.querySelector('textarea[x-ref=\"messageInput\"]');
                    if (input && document.activeElement === input) {
                        setTimeout(function() {
                            input.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }, 100);
                    }
                } else if (heightDiff < 100 && isKeyboardOpen) {
                    isKeyboardOpen = false;
                    document.body.classList.remove('vp-keyboard-open');
                }
            });
            window.addEventListener('orientationchange', function() {
                setTimeout(function() {
                    initialHeight = window.visualViewport.height;
                }, 200);
            });
        }
    })();";
    wp_print_inline_script_tag($keyboard_script, array('id' => 'chatprojects-keyboard-detection'));

    // Handle back-forward cache (bfcache) to reinitialize Alpine components
    $bfcache_script = "(function() {
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                // Page was restored from bfcache, reload to reinitialize
                window.location.reload();
            }
        });
    })();";
    wp_print_inline_script_tag($bfcache_script, array('id' => 'chatprojects-bfcache-handler'));

    ?>

    <?php wp_footer(); ?>

    <?php
    // Fallback: If main.js didn't load via wp_enqueue, load it directly
    $main_js_url = esc_url(CHATPROJECTS_PLUGIN_URL . 'assets/dist/js/main.js');
    $main_js_version = CHATPROJECTS_VERSION . '-' . filemtime(CHATPROJECTS_PLUGIN_DIR . 'assets/dist/js/main.js');
    $fallback_script = "(function() {
        // Check if main.js is already in the DOM
        var mainJsInDom = Array.from(document.querySelectorAll('script')).some(function(s) {
            return s.src && s.src.includes('main.js');
        });

        if (mainJsInDom) {
            return;
        }

        // Only load if main.js is truly missing
        var script = document.createElement('script');
        script.type = 'module';
        script.src = '{$main_js_url}?ver={$main_js_version}';
        document.body.appendChild(script);
    })();";
    wp_print_inline_script_tag($fallback_script, array('id' => 'chatprojects-fallback-loader'));
    ?>
</body>
</html>
<?php
});
