<?php
/**
 * Main App Shell Template
 *
 * Embeddable full application with tab navigation
 * Used by [chatprojects_main] shortcode
 *
 * Available variables:
 *   $default_tab - Default tab to show (projects, chat, settings)
 *   $app_height - CSS height for the container
 *   $show_header - Whether to show the header
 *
 * @package ChatProjects
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$chatprojects_app_context = array(
    'default_tab' => isset($default_tab) ? $default_tab : 'projects',
    'app_height' => isset($app_height) ? $app_height : 'auto',
    'show_header' => isset($show_header) ? (bool) $show_header : true,
);

call_user_func(function () use ($chatprojects_app_context) {
    $default_tab = $chatprojects_app_context['default_tab'];
    $app_height = $chatprojects_app_context['app_height'];
    $show_header = $chatprojects_app_context['show_header'];

    $current_user = wp_get_current_user();
    $user_id = get_current_user_id();

    $is_pro = defined('CHATPROJECTS_PRO_VERSION');
    $theme_preference = get_user_meta($user_id, 'cp_theme_preference', true) ?: 'auto';

    $tabs = array(
        'projects' => array(
            'label' => __('Projects', 'chatprojects'),
            'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>',
            'available' => true,
        ),
        'chat' => array(
            'label' => __('Chat', 'chatprojects'),
            'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>',
            'available' => true,
        ),
        'settings' => array(
            'label' => __('Settings', 'chatprojects'),
            'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
            'available' => true,
        ),
    );

// Pro-only tabs (only show if Pro is active)
if ($is_pro) {
    $tabs['compare'] = array(
        'label' => __('Compare', 'chatprojects'),
        'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>',
        'available' => true,
    );
}
?>

<div
    id="vp-main-app"
    class="vp-main-app-container <?php echo esc_attr($theme_preference === 'dark' ? 'dark' : ''); ?>"
    style="height: <?php echo esc_attr($app_height); ?>; display: flex; flex-direction: column; background: var(--vp-bg, #f3f4f6); border-radius: 12px; overflow: hidden; border: 1px solid var(--vp-border, #e5e7eb);"
    x-data="vpMainApp({
        defaultTab: '<?php echo esc_js($default_tab); ?>',
        isPro: <?php echo $is_pro ? 'true' : 'false'; ?>
    })"
>
    <?php if ($show_header) : ?>
    <!-- App Header -->
    <header class="vp-app-header" style="flex-shrink: 0; background: var(--vp-surface, white); border-bottom: 1px solid var(--vp-border, #e5e7eb); padding: 0.75rem 1rem;">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <!-- Logo/Brand -->
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <svg class="w-6 h-6" style="color: #2563eb;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                <span style="font-weight: 600; font-size: 1.125rem; color: var(--vp-text, #111827);">
                    <?php esc_html_e('ChatProjects', 'chatprojects'); ?>
                </span>
                <?php if ($is_pro) : ?>
                <span style="font-size: 0.625rem; font-weight: 600; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: white; padding: 0.125rem 0.375rem; border-radius: 4px; text-transform: uppercase;">
                    PRO
                </span>
                <?php endif; ?>
            </div>

            <!-- Tab Navigation -->
            <nav style="display: flex; align-items: center; gap: 0.25rem; background: var(--vp-bg-secondary, #f3f4f6); padding: 0.25rem; border-radius: 8px;">
                <?php foreach ($tabs as $tab_key => $tab) : ?>
                <?php if ($tab['available']) : ?>
                <button
                    @click="activeTab = '<?php echo esc_js($tab_key); ?>'"
                    :class="activeTab === '<?php echo esc_js($tab_key); ?>'
                        ? 'vp-tab-active'
                        : 'vp-tab-inactive'"
                    class="vp-tab-btn"
                    style="display: flex; align-items: center; gap: 0.375rem; padding: 0.5rem 0.75rem; border-radius: 6px; font-size: 0.875rem; font-weight: 500; border: none; cursor: pointer; transition: all 0.15s;"
                >
                    <?php echo wp_kses_post( $tab['icon'] ); ?>
                    <span class="hidden sm:inline"><?php echo esc_html($tab['label']); ?></span>
                </button>
                <?php endif; ?>
                <?php endforeach; ?>
            </nav>

            <!-- User Menu -->
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <button
                    @click="toggleTheme()"
                    style="padding: 0.5rem; color: var(--vp-text-muted, #6b7280); border: none; background: none; cursor: pointer; border-radius: 6px;"
                    title="<?php esc_attr_e('Toggle theme', 'chatprojects'); ?>"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                    </svg>
                </button>
                <span style="font-size: 0.875rem; color: var(--vp-text-muted, #6b7280);">
                    <?php echo esc_html($current_user->display_name); ?>
                </span>
                <a
                    href="<?php echo esc_url(wp_logout_url(get_permalink())); ?>"
                    style="font-size: 0.75rem; color: var(--vp-text-muted, #6b7280); text-decoration: none;"
                >
                    <?php esc_html_e('Logout', 'chatprojects'); ?>
                </a>
            </div>
        </div>
    </header>
    <?php endif; ?>

    <!-- Tab Content -->
    <div class="vp-app-content" style="flex: 1; overflow: hidden; position: relative;">

        <!-- Projects Tab -->
        <div
            x-show="activeTab === 'projects'"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            style="height: 100%; overflow: auto;"
        >
            <div x-data="vpProjectsPanel()">
                <?php include CHATPROJECTS_PLUGIN_DIR . 'public/templates/partials/projects-panel.php'; ?>
            </div>
        </div>

        <!-- Chat Tab -->
        <div
            x-show="activeTab === 'chat'"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            style="height: 100%; overflow: hidden;"
        >
            <?php
            // Use the same modern chat interface as /pro-chat/
            $chatpr_project_id = null;
            $cp_chat_mode = 'general';
            include CHATPROJECTS_PLUGIN_DIR . 'public/templates/chat-interface-modern.php';
            ?>
        </div>

        <!-- Settings Tab -->
        <div
            x-show="activeTab === 'settings'"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            style="height: 100%; overflow: auto;"
        >
            <div x-data="vpSettingsPanel()">
                <?php include CHATPROJECTS_PLUGIN_DIR . 'public/templates/partials/settings-panel.php'; ?>
            </div>
        </div>

        <?php if ($is_pro) : ?>
        <!-- Compare Tab (Pro Only) -->
        <div
            x-show="activeTab === 'compare'"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            style="height: 100%; overflow: hidden;"
        >
            <div x-data="vpComparePanel()" style="height: 100%;">
                <?php include CHATPROJECTS_PLUGIN_DIR . 'public/templates/partials/compare-panel.php'; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Main App Shell Alpine.js Components
$main_app_script = "document.addEventListener('alpine:init', () => {
    Alpine.data('vpMainApp', (config) => ({
        activeTab: config.defaultTab || 'projects',
        isPro: config.isPro || false,
        darkMode: document.documentElement.classList.contains('dark'),
        init() {
            const hash = window.location.hash.replace('#', '');
            if (hash && ['projects', 'chat', 'settings', 'compare'].includes(hash)) {
                this.activeTab = hash;
            }
            this.\$watch('activeTab', (value) => {
                history.replaceState(null, null, '#' + value);
            });
        },
        toggleTheme() {
            this.darkMode = !this.darkMode;
            document.querySelector('.vp-main-app-container').classList.toggle('dark', this.darkMode);
            fetch(chatprData.ajax_url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'chatpr_save_theme_preference',
                    nonce: chatprData.nonce,
                    theme: this.darkMode ? 'dark' : 'light'
                })
            });
        }
    }));
    Alpine.data('vpProjectsPanel', () => ({
        projects: [],
        loading: true,
        init() {
            this.loadProjects();
        },
        async loadProjects() {
            this.loading = true;
            try {
                const response = await fetch(chatprData.ajax_url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'chatpr_get_projects',
                        nonce: chatprData.nonce
                    })
                });
                const data = await response.json();
                if (data.success) {
                    this.projects = data.data.projects || [];
                }
            } catch (error) {
                // Error loading projects silently
            } finally {
                this.loading = false;
            }
        }
    }));
    Alpine.data('vpSettingsPanel', () => ({
        settings: {},
        init() {}
    }));
    Alpine.data('vpComparePanel', () => ({
        initialized: false,
        init() {
            this.initialized = true;
        }
    }));
});";
wp_print_inline_script_tag($main_app_script, array('id' => 'chatprojects-main-app'));
?>
<?php
});
