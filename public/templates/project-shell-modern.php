<?php
/**
 * Modern Project Shell Template
 *
 * ChatGPT/Claude-style interface with Tailwind CSS and Alpine.js
 *
 * @package ChatProjects
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

call_user_func(function () {
    // Check if user is logged in
    if (!is_user_logged_in()) {
        // Show beautiful login page with redirect back to project.
        // Set redirect URL via variable that login-page.php will check.
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Scoped variable for template inclusion
        $chatprojects_login_redirect_to = get_permalink();
        include CHATPROJECTS_PLUGIN_DIR . 'public/templates/login-page.php';
        return;
    }

    $project_id = get_the_ID();
    $project = get_post($project_id);

    // Check access
    if (!ChatProjects\Access::can_access_project($project_id)) {
        wp_die(esc_html__('You do not have permission to access this project.', 'chatprojects'));
    }

    // Get project metadata (no assistant_id needed with Responses API)
    $vector_store_id = get_post_meta($project_id, '_cp_vector_store_id', true);

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
    <title><?php echo esc_html($project->post_title); ?> - <?php bloginfo('name'); ?></title>
    <?php
    // Output chatprData via wp_add_inline_script for WP guidelines compliance
    $current_user = wp_get_current_user();
    $chatpr_inline_data = array(
        'ajax_url' => admin_url('admin-ajax.php'),
        // stream_url temporarily removed to test with admin-ajax.php
        'nonce' => wp_create_nonce('chatpr_ajax_nonce'),
        'current_user' => $current_user->display_name,
        'is_pro_user' => false,
    );

    // Alpine.js patch script - fixes chat component methods after navigation
    $chatpr_alpine_patch = '
    // chatprData defined for availability
    var chatprData = ' . wp_json_encode($chatpr_inline_data) . ';

    // Fix: Reload page if restored from bfcache (back/forward navigation)
    window.addEventListener("pageshow", function(event) {
        if (event.persisted) {
            window.location.reload();
        }
    });

    // Fix: Refresh nonce on page load to handle cached pages
    // Store the promise so operations can wait for it
    chatprData.nonceReady = fetch(chatprData.ajax_url, {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({ action: "chatpr_refresh_nonce" })
    })
    .then(function(r) {
        return r.json();
    })
    .then(function(data) {
        if (data.success && data.data && data.data.nonce) {
            chatprData.nonce = data.data.nonce;
        }
        return true;
    })
    .catch(function(e) {
        return true; // Continue anyway with existing nonce
    });

    // Fix: Patch the chat component methods to work correctly after navigation
    (function() {
        Object.defineProperty(window, "Alpine", {
            configurable: true,
            set: function(alpine) {
                var originalData = alpine.data;
                alpine.data = function(name, fn) {
                    var wrappedFn = function() {
                        var component = fn.apply(this, arguments);

                        // Patch chat component methods
                        if (name === "chat") {
                            // Patch loadMessages
                            component.loadMessages = async function() {
                                var self = this;
                                self.loading = true;
                                try {
                                    var action = self.mode === "general" ? "chatpr_get_general_chat_history" : "chatpr_load_chat_history";
                                    var response = await fetch(chatprData.ajax_url, {
                                        method: "POST",
                                        headers: { "Content-Type": "application/x-www-form-urlencoded" },
                                        body: new URLSearchParams({
                                            action: action,
                                            nonce: chatprData.nonce,
                                            chat_id: self.threadId
                                        })
                                    });
                                    var data = await response.json();
                                    if (data.success) {
                                        self.messages = data.data.messages || data.data || [];
                                        self.scrollToBottom();
                                    } else {
                                        var msg = (data.data && data.data.message) ? data.data.message : "Failed to load messages";
                                        if (window.VPToast) window.VPToast.error(msg);
                                    }
                                } catch (err) {
                                    if (window.VPToast) window.VPToast.error("Failed to load messages");
                                } finally {
                                    self.loading = false;
                                }
                            };

                            // Patch sendMessage to reset stale state
                            component.sendMessage = async function() {
                                // Reset stale state that might be left over from bfcache
                                if (this.streaming && !this.abortController) {
                                    this.streaming = false;
                                }

                                var hasText = this.input.trim().length > 0;
                                var hasImages = this.attachedImages && this.attachedImages.length > 0;

                                if ((!hasText && !hasImages) || this.loading || this.streaming) {
                                    return;
                                }

                                var messageText = this.input.trim();
                                var images = this.attachedImages ? [...this.attachedImages] : [];
                                this.input = "";
                                this.attachedImages = [];

                                var userMessage = {
                                    role: "user",
                                    content: messageText,
                                    timestamp: new Date().toISOString()
                                };
                                if (images.length > 0) {
                                    userMessage.images = images.map(function(img) { return img.dataUrl; });
                                }

                                this.messages.push(userMessage);
                                this.scrollToBottom();

                                await this.streamResponse(messageText, images);
                            };

                            // Patch streamResponse for project chat mode
                            component.streamResponse = async function(message, images) {
                                var self = this;
                                images = images || [];

                                if (typeof chatprData === "undefined" || !chatprData.ajax_url || !chatprData.nonce) {
                                    if (window.VPToast) window.VPToast.error("Session expired. Please refresh the page.");
                                    return;
                                }

                                // Wait for nonce refresh if in progress
                                if (chatprData.nonceReady) {
                                    try {
                                        await chatprData.nonceReady;
                                    } catch (e) {
                                        // Continue anyway
                                    }
                                }

                                this.streaming = true;
                                this.abortController = new AbortController();

                                var assistantMessage = {
                                    role: "assistant",
                                    content: "",
                                    timestamp: new Date().toISOString(),
                                    streaming: true,
                                    sources: []
                                };
                                this.messages.push(assistantMessage);
                                this.currentStreamingMessage = assistantMessage;

                                try {
                                    var params = {
                                        action: this.mode === "general" ? "chatpr_stream_general_message" : "chatpr_stream_chat_message",
                                        nonce: chatprData.nonce,
                                        message: message,
                                        thread_id: this.threadId || "",
                                        project_id: this.projectId || ""
                                    };

                                    var response = await fetch(chatprData.ajax_url, {
                                        method: "POST",
                                        credentials: "same-origin",
                                        headers: { "Content-Type": "application/x-www-form-urlencoded" },
                                        body: new URLSearchParams(params),
                                        signal: this.abortController.signal
                                    });

                                    if (!response.ok) {
                                        throw new Error("HTTP error! status: " + response.status);
                                    }

                                    var reader = response.body.getReader();
                                    var decoder = new TextDecoder();
                                    var buffer = "";
                                    var done = false;

                                    while (!done) {
                                        var result = await reader.read();
                                        if (result.done) break;

                                        buffer += decoder.decode(result.value, { stream: true });
                                        var lines = buffer.split("\\n");
                                        buffer = lines.pop() || "";

                                        for (var i = 0; i < lines.length; i++) {
                                            var line = lines[i];
                                            if (line.trim() && line.startsWith("data: ")) {
                                                var data = line.slice(6);
                                                if (data === "[DONE]") {
                                                    done = true;
                                                    break;
                                                }
                                                try {
                                                    var parsed = JSON.parse(data);
                                                    if (parsed.type === "content" && parsed.content) {
                                                        assistantMessage.content += parsed.content;
                                                        // Force Alpine reactivity
                                                        var idx = self.messages.findIndex(function(m) { return m.streaming && m.role === "assistant"; });
                                                        if (idx !== -1) {
                                                            self.messages[idx] = Object.assign({}, self.messages[idx], { content: assistantMessage.content });
                                                            self.messages = self.messages.slice();
                                                        }
                                                        self.scrollToBottom();
                                                    } else if (parsed.type === "chat_id" && parsed.chat_id) {
                                                        self.threadId = parsed.chat_id;
                                                        window.dispatchEvent(new CustomEvent("vp:chat:updated", { detail: { threadId: self.threadId } }));
                                                    } else if (parsed.type === "sources" && parsed.sources) {
                                                        assistantMessage.sources = parsed.sources;
                                                    } else if (parsed.type === "error") {
                                                        if (window.VPToast) window.VPToast.error(parsed.content || "An error occurred");
                                                        self.messages = self.messages.filter(function(m) { return m !== assistantMessage; });
                                                        return;
                                                    } else if (parsed.type === "title_update" && parsed.title) {
                                                        // Handle title update from SSE
                                                        window.dispatchEvent(new CustomEvent("vp:chat:title-updated", {
                                                            detail: { chatId: parsed.chat_id, title: parsed.title }
                                                        }));
                                                    }
                                                    // Note: We do NOT break on parsed.type === "done" because
                                                    // our endpoint sends chat_id and title_update AFTER the API
                                                    // stream completes. We only break on data: [DONE] marker.
                                                } catch (parseErr) {
                                                    // Ignore JSON parse errors for incomplete chunks
                                                }
                                            }
                                        }
                                    }

                                    // Mark streaming complete
                                    self.currentStreamingMessage = null;
                                    var finalIdx = self.messages.findIndex(function(m) { return m.streaming && m.role === "assistant"; });
                                    if (finalIdx !== -1) {
                                        self.messages[finalIdx] = Object.assign({}, self.messages[finalIdx], { streaming: false });
                                        self.messages = self.messages.slice();
                                    }

                                    window.dispatchEvent(new CustomEvent("vp:chat:updated", { detail: { threadId: self.threadId } }));

                                } catch (err) {
                                    if (err.name !== "AbortError") {
                                        var errorMsg = err.message && err.message.includes("status")
                                            ? "Server error: " + err.message
                                            : "Failed to get response: " + (err.message || err.name || "unknown error");
                                        if (window.VPToast) window.VPToast.error(errorMsg);
                                        self.messages = self.messages.filter(function(m) { return m !== assistantMessage; });
                                    }
                                } finally {
                                    self.streaming = false;
                                    self.abortController = null;
                                }
                            };
                        }
                        return component;
                    };
                    return originalData.call(this, name, wrappedFn);
                };
                Object.defineProperty(window, "Alpine", {
                    configurable: true,
                    writable: true,
                    value: alpine
                });
            },
            get: function() { return undefined; }
        });

        // Handle title updates from SSE
        window.addEventListener("vp:chat:title-updated", function(e) {
            if (!e.detail || !e.detail.chatId || !e.detail.title) return;
            // Find Alpine chatHistory components and update them
            document.querySelectorAll("[x-data*=\\"chatHistory\\"]").forEach(function(el) {
                if (el._x_dataStack && el._x_dataStack[0]) {
                    var component = el._x_dataStack[0];
                    var idx = component.chats.findIndex(function(c) {
                        return c.id == e.detail.chatId;
                    });
                    if (idx !== -1) {
                        component.chats[idx].title = e.detail.title;
                        component.chats = component.chats.slice();
                    }
                }
            });
        });
    })();';

    // Enqueue jQuery and add inline script using WordPress functions
    wp_enqueue_script('jquery');
    wp_add_inline_script('jquery', $chatpr_alpine_patch);
    ?><?php
    // Styles are enqueued via class-chatprojects.php (templates.css)
    // Theme init script is output via wp_head action in class-chatprojects.php
    // Ensure type="module" is added to our scripts (fixes ES module error)
    add_filter('script_loader_tag', function($tag, $handle) {
        if (in_array($handle, array('chatprojects-frontend', 'chatprojects-main'))) {
            if (strpos($tag, 'type="module"') === false && strpos($tag, "type='module'") === false) {
                $tag = preg_replace('/<script\s/', '<script type="module" ', $tag);
            }
        }
        return $tag;
    }, 10, 2);

    wp_head();
    ?>
</head>

<body class="h-full overflow-hidden bg-neutral-50 dark:bg-dark-bg antialiased" x-data="{ sidebarOpen: window.innerWidth >= 768, currentTab: 'chat' }" @vp-switch-to-chat.window="currentTab = 'chat'">

    <div class="flex h-full" data-project-id="<?php echo esc_attr($project_id); ?>">

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
            class="vp-project-sidebar fixed inset-y-0 left-0 max-w-[calc(100vw-60px)] bg-sidebar-light dark:bg-dark-bg border-r border-sidebar-border dark:border-dark-border flex flex-col lg:relative lg:translate-x-0" style="z-index: 60;"
            x-data="sidebar()"
        >
            <!-- ChatProjects Header with Navigation -->
            <div class="cp-sidebar-header flex-shrink-0">
                <a href="<?php echo esc_url(home_url('/projects/')); ?>" class="cp-logo">
                    <span class="cp-logo-chat">Chat</span><span class="cp-logo-projects">Projects</span>
                </a>
                <div class="cp-nav-icons">
                    <!-- Projects (Grid) - active since we're in a project -->
                    <a href="<?php echo esc_url(home_url('/projects/')); ?>" class="cp-nav-icon active" title="<?php esc_attr_e('Projects', 'chatprojects'); ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                        </svg>
                    </a>
                    <!-- Chat -->
                    <a href="<?php echo esc_url(home_url('/pro-chat/')); ?>" class="cp-nav-icon" title="<?php esc_attr_e('Chat', 'chatprojects'); ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                    </a>
                </div>
            </div>

            <!-- Sidebar Header: Project Switcher -->
            <div class="flex-shrink-0 p-4 border-b border-sidebar-border dark:border-dark-border bg-white dark:bg-dark-elevated">
                <div x-data="projectSwitcher(<?php echo esc_attr($project_id); ?>)">
                    <button
                        @click="toggle()"
                        :class="isOpen
                            ? 'bg-primary-500 text-white border-primary-500 hover:bg-primary-600'
                            : 'text-gray-800 dark:text-white hover:bg-gray-100 dark:hover:bg-dark-hover border-gray-200 dark:border-transparent hover:border-gray-300 dark:hover:border-transparent'"
                        class="w-full flex items-center justify-between gap-2 px-3 py-2 rounded-lg transition-colors border"
                    >
                        <div class="flex items-center gap-2 flex-1 min-w-0">
                            <svg :class="isOpen ? 'text-white' : 'text-gray-500 dark:text-neutral-400'" class="w-4 h-4 flex-shrink-0 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                            </svg>
                            <span :class="isOpen ? '' : 'text-gray-900 dark:text-white'" class="text-sm truncate"><?php echo esc_html($project->post_title); ?></span>
                        </div>
                        <svg
                            class="w-4 h-4 flex-shrink-0 transition-transform"
                            :class="{ 'rotate-180': isOpen, 'text-white': isOpen, 'text-gray-500 dark:text-neutral-400': !isOpen }"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <!-- Project Switcher Dropdown -->
                    <div
                        x-show="isOpen"
                        x-cloak
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-95"
                        class="absolute top-20 left-4 right-4 bg-white dark:bg-dark-elevated border border-gray-200 dark:border-dark-border rounded-xl shadow-large z-[70] overflow-hidden"
                        @click.outside="isOpen = false"
                    >
                        <div class="p-4 border-b border-gray-200 dark:border-dark-border">
                            <input
                                type="text"
                                x-model="searchQuery"
                                x-ref="searchInput"
                                placeholder="<?php esc_html_e('Search projects...', 'chatprojects'); ?>"
                                class="w-full px-3 py-2 bg-gray-50 dark:bg-dark-bg border border-gray-300 dark:border-dark-border rounded-lg text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-neutral-500 focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                            >
                        </div>

                        <div class="max-h-96 overflow-y-auto">
                            <template x-if="loading">
                                <div class="flex items-center justify-center py-8 text-gray-500 dark:text-neutral-400">
                                    <svg class="animate-spin h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span><?php esc_html_e('Loading...', 'chatprojects'); ?></span>
                                </div>
                            </template>

                            <template x-if="!loading && filteredProjects.length === 0">
                                <div class="py-8 text-center text-gray-500 dark:text-neutral-500">
                                    <?php esc_html_e('No projects found', 'chatprojects'); ?>
                                </div>
                            </template>

                            <template x-for="project in filteredProjects" :key="project.id">
                                <a
                                    :href="project.url"
                                    class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-dark-hover transition-colors"
                                    :class="{ 'bg-gray-100 dark:bg-dark-hover': project.id == currentProjectId }"
                                >
                                    <svg class="w-5 h-5 text-gray-400 dark:text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                                    </svg>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white truncate" x-text="project.title"></div>
                                        <div class="text-xs">
                                            <template x-if="project.categories && project.categories.length > 0">
                                                <span class="text-gray-500 dark:text-neutral-400 italic" x-text="project.categories[0].name"></span>
                                            </template>
                                            <template x-if="!project.categories || project.categories.length === 0">
                                                <span class="text-gray-400 dark:text-neutral-500 italic">Uncategorized</span>
                                            </template>
                                        </div>
                                    </div>
                                    <template x-if="project.id == currentProjectId">
                                        <svg class="w-5 h-5 text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </template>
                                </a>
                            </template>
                        </div>

                        <div class="p-4 border-t border-gray-200 dark:border-dark-border space-y-2">
                            <a href="<?php echo esc_url(home_url('/projects/')); ?>" class="btn-secondary w-full">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                                </svg>
                                <?php esc_html_e('View All Projects', 'chatprojects'); ?>
                            </a>
                            <button id="vp-new-project-btn" data-href="<?php echo esc_url(home_url('/projects/?action=new')); ?>" class="btn-primary w-full">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                <?php esc_html_e('New Project', 'chatprojects'); ?>
                            </button>

                            <?php if (defined('CHATPROJECTS_PRO_VERSION')) : ?>
                            <!-- Share Project Button (Pro only) -->
                            <button @click="openShareModal()" class="btn-secondary w-full">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                                </svg>
                                <?php esc_html_e('Share Project', 'chatprojects'); ?>
                            </button>
                            <?php endif; ?>

                            <!-- Divider -->
                            <div class="border-t border-gray-200 dark:border-gray-700 my-2"></div>

                            <!-- Chat Button -->
                            <a href="<?php echo esc_url(home_url('/pro-chat/')); ?>" class="btn-secondary w-full">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                </svg>
                                <?php esc_html_e('Chat', 'chatprojects'); ?>
                            </a>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Tab Navigation -->
            <nav class="flex-shrink-0 px-2 py-4 space-y-1">
                <button
                    @click="currentTab = 'chat'; if (window.innerWidth < 1024) sidebarOpen = false"
                    :class="currentTab === 'chat' ? 'tab-active' : 'tab'"
                    class="w-full"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                    </svg>
                    <span><?php esc_html_e('Project Assistant', 'chatprojects'); ?></span>
                </button>
                <button
                    @click="currentTab = 'files'; if (window.innerWidth < 1024) sidebarOpen = false"
                    :class="currentTab === 'files' ? 'tab-active' : 'tab'"
                    class="w-full"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    <span><?php esc_html_e('Files', 'chatprojects'); ?></span>
                </button>
                <?php if (defined('CHATPROJECTS_PRO_VERSION')) : ?>
                <button
                    @click="currentTab = 'transcriber'; if (window.innerWidth < 1024) sidebarOpen = false"
                    :class="currentTab === 'transcriber' ? 'tab-active' : 'tab'"
                    class="w-full"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/>
                    </svg>
                    <span><?php esc_html_e('Transcriber', 'chatprojects'); ?></span>
                </button>
                <button
                    @click="currentTab = 'prompts'; if (window.innerWidth < 1024) sidebarOpen = false"
                    :class="currentTab === 'prompts' ? 'tab-active' : 'tab'"
                    class="w-full"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                    </svg>
                    <span><?php esc_html_e('Prompts', 'chatprojects'); ?></span>
                </button>
                <?php endif; ?>
            </nav>

            <!-- New Chat Button -->
            <div class="px-4 pb-4">
                <button
                    @click="$dispatch('vp-switch-to-chat'); window.dispatchEvent(new CustomEvent('vp:chat:new'))"
                    class="w-full flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span><?php esc_html_e('New Chat', 'chatprojects'); ?></span>
                </button>
            </div>

            <!-- Chat History (Scrollable) -->
            <div class="flex-1 overflow-y-auto px-2" x-data="chatHistory(<?php echo esc_js($project_id); ?>)">
                <!-- Chats Header -->
                <div class="px-3 pt-1 pb-2 mb-1">
                    <h3 class="text-xs font-semibold text-sidebar-text dark:text-white uppercase tracking-wider"><?php esc_html_e('Chats', 'chatprojects'); ?></h3>
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
                            <p class="text-xs mt-1"><?php esc_html_e('Start a conversation to begin', 'chatprojects'); ?></p>
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
                                    @click="$dispatch('vp-switch-to-chat'); switchChat(chat.id)"
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
                                            title="<?php esc_html_e('Rename chat', 'chatprojects'); ?>"
                                        >
                                            <svg class="w-3.5 h-3.5 text-sidebar-textLight dark:text-neutral-400 hover:text-sidebar-text dark:hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                            </svg>
                                        </button>

                                        <!-- Delete button -->
                                        <button
                                            @click.stop="deleteChat(chat.id, $event)"
                                            class="p-1 hover:bg-red-100 dark:hover:bg-red-900/30 rounded"
                                            title="<?php esc_html_e('Delete chat', 'chatprojects'); ?>"
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
                        title="<?php esc_html_e('Toggle theme', 'chatprojects'); ?>"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                        </svg>
                    </button>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <!-- Settings -->
                        <a
                            href="<?php echo esc_url(home_url('/settings/')); ?>"
                            class="vp-nav-icon"
                            title="<?php esc_html_e('Settings', 'chatprojects'); ?>"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </a>
                        <!-- Logout -->
                        <a
                            href="<?php echo esc_url(wp_logout_url(home_url())); ?>"
                            class="vp-nav-icon"
                            title="<?php esc_html_e('Logout', 'chatprojects'); ?>"
                        >
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

            <!-- Tab Content -->
            <div class="flex-1 overflow-hidden">
                <!-- Chat Tab -->
                <div x-show="currentTab === 'chat'" class="h-full">
                    <?php include CHATPROJECTS_PLUGIN_DIR . 'public/templates/chat-interface-modern.php'; ?>
                </div>

                <!-- Files Tab -->
                <div x-show="currentTab === 'files'" x-cloak class="h-full">
                    <?php include CHATPROJECTS_PLUGIN_DIR . 'public/templates/file-manager-ui.php'; ?>
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

    <?php if (defined('CHATPROJECTS_PRO_VERSION')) : ?>
    <!-- Share Project Modal (Pro only) -->
    <div x-data="shareModal(<?php echo esc_attr($project_id); ?>)" @open-share-modal.window="openModal()" x-cloak>
        <div x-show="show" class="fixed inset-0 z-[9999] overflow-y-auto" @keydown.escape.window="closeModal()">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <!-- Backdrop -->
                <div x-show="show"
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     @click="closeModal()"
                     class="fixed inset-0 transition-opacity bg-black/50 backdrop-blur-sm"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

                <!-- Modal -->
                <div x-show="show"
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     class="inline-block align-bottom bg-white dark:bg-dark-surface rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"
                     @click.stop>

                    <!-- Header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-dark-border">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white"><?php esc_html_e('Share Project', 'chatprojects'); ?></h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1"><?php echo esc_html($project->post_title); ?></p>
                        </div>
                        <button @click="closeModal()" class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-dark-hover transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="px-6 py-4 space-y-4">
                        <!-- Search Users -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                <?php esc_html_e('Search Users', 'chatprojects'); ?>
                            </label>
                            <div>
                                <input
                                    type="text"
                                    x-model="searchQuery"
                                    @input.debounce.300ms="searchUsers()"
                                    class="block w-full px-4 py-2.5 border border-gray-300 dark:border-dark-border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-dark-bg dark:text-white text-sm"
                                    placeholder="<?php esc_html_e('Type to search users...', 'chatprojects'); ?>"
                                >
                            </div>

                            <!-- Search Results -->
                            <div x-show="searchResults.length > 0" class="mt-2 border border-gray-200 dark:border-dark-border rounded-lg max-h-48 overflow-y-auto">
                                <template x-for="user in searchResults" :key="user.id">
                                    <button
                                        @click="addUser(user)"
                                        class="w-full px-4 py-2 text-left hover:bg-gray-50 dark:hover:bg-dark-hover flex items-center justify-between border-b border-gray-100 dark:border-dark-border last:border-0"
                                    >
                                        <div>
                                            <div class="text-sm font-medium text-gray-900 dark:text-white" x-text="user.display_name"></div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400" x-text="user.email"></div>
                                        </div>
                                        <svg class="w-5 h-5 text-green-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <!-- Loading indicator -->
                        <div x-show="loading" class="flex items-center justify-center py-4">
                            <svg class="animate-spin h-5 w-5 text-primary-600" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="ml-2 text-sm text-gray-500"><?php esc_html_e('Loading...', 'chatprojects'); ?></span>
                        </div>

                        <!-- Currently Shared With -->
                        <div x-show="sharedUsers.length > 0 && !loading">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                <?php esc_html_e('Currently Shared With', 'chatprojects'); ?>
                            </label>
                            <div class="space-y-2">
                                <template x-for="user in sharedUsers" :key="user.id">
                                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-dark-bg rounded-lg">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900 dark:text-white" x-text="user.display_name"></div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400" x-text="user.email"></div>
                                        </div>
                                        <button
                                            @click="removeUser(user)"
                                            class="p-1 text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition-colors"
                                            :disabled="removing === user.id"
                                        >
                                            <svg x-show="removing !== user.id" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                            <svg x-show="removing === user.id" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- No users shared -->
                        <div x-show="sharedUsers.length === 0 && !loading" class="text-center py-4">
                            <svg class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            <p class="text-sm text-gray-500 dark:text-gray-400"><?php esc_html_e('Not shared with anyone yet', 'chatprojects'); ?></p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1"><?php esc_html_e('Search for users above to share this project', 'chatprojects'); ?></p>
                        </div>

                        <!-- Error Message -->
                        <div x-show="error" x-transition class="p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                            <p class="text-sm text-red-600 dark:text-red-400" x-text="error"></p>
                        </div>

                        <!-- Success Message -->
                        <div x-show="success" x-transition class="p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                <p class="text-sm text-green-600 dark:text-green-400" x-text="success"></p>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-6 py-4 bg-gray-50 dark:bg-dark-bg border-t border-gray-200 dark:border-dark-border flex justify-end">
                        <button
                            type="button"
                            @click="closeModal()"
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-dark-surface border border-gray-300 dark:border-dark-border rounded-lg hover:bg-gray-50 dark:hover:bg-dark-hover transition-colors"
                        >
                            <?php esc_html_e('Close', 'chatprojects'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
    // Share modal Alpine component - using wp_print_inline_script_tag for WordPress compliance
    $share_modal_script = "// Share modal component
    document.addEventListener('alpine:init', () => {
        Alpine.data('shareModal', (projectId) => ({
            show: false,
            projectId: projectId,
            searchQuery: '',
            searchResults: [],
            sharedUsers: [],
            loading: false,
            removing: null,
            error: '',
            success: '',

            openModal() {
                this.show = true;
                this.searchQuery = '';
                this.searchResults = [];
                this.error = '';
                this.success = '';
                this.loadSharedUsers();
            },

            closeModal() {
                this.show = false;
            },

            async loadSharedUsers() {
                this.loading = true;
                try {
                    const response = await fetch(chatprData.ajax_url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'chatpr_get_shared_users',
                            nonce: chatprData.nonce,
                            project_id: this.projectId
                        })
                    });
                    const data = await response.json();
                    if (data.success) {
                        this.sharedUsers = data.data.users || [];
                    }
                } catch (e) {
                    // Error loading shared users silently
                } finally {
                    this.loading = false;
                }
            },

            async searchUsers() {
                if (!this.searchQuery || this.searchQuery.length < 2) {
                    this.searchResults = [];
                    return;
                }
                try {
                    const response = await fetch(chatprData.ajax_url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'chatpr_search_users',
                            nonce: chatprData.nonce,
                            search: this.searchQuery,
                            exclude_project: this.projectId
                        })
                    });
                    const data = await response.json();
                    if (data.success) {
                        const sharedIds = this.sharedUsers.map(u => u.id);
                        this.searchResults = (data.data.users || []).filter(u => !sharedIds.includes(u.id));
                    }
                } catch (e) {
                    // Error searching users silently
                }
            },

            async addUser(user) {
                this.error = '';
                this.success = '';
                try {
                    const response = await fetch(chatprData.ajax_url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'chatpr_share_project',
                            nonce: chatprData.nonce,
                            project_id: this.projectId,
                            user_id: user.id
                        })
                    });
                    const data = await response.json();
                    if (data.success) {
                        this.sharedUsers.push(user);
                        this.searchResults = this.searchResults.filter(u => u.id !== user.id);
                        this.searchQuery = '';
                        this.success = 'Project shared successfully';
                        setTimeout(() => this.success = '', 3000);
                    } else {
                        this.error = data.data?.message || 'Failed to share project.';
                    }
                } catch (e) {
                    this.error = 'An error occurred. Please try again.';
                }
            },

            async removeUser(user) {
                this.removing = user.id;
                this.error = '';
                this.success = '';
                try {
                    const response = await fetch(chatprData.ajax_url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'chatpr_unshare_project',
                            nonce: chatprData.nonce,
                            project_id: this.projectId,
                            user_id: user.id
                        })
                    });
                    const data = await response.json();
                    if (data.success) {
                        this.sharedUsers = this.sharedUsers.filter(u => u.id !== user.id);
                        this.success = 'Access removed successfully';
                        setTimeout(() => this.success = '', 3000);
                    } else {
                        this.error = data.data?.message || 'Failed to remove access.';
                    }
                } catch (e) {
                    this.error = 'An error occurred. Please try again.';
                } finally {
                    this.removing = null;
                }
            }
        }));
    });";
    wp_print_inline_script_tag($share_modal_script, array('id' => 'chatprojects-share-modal'));
    endif; // End Pro-only share modal ?>

    <!-- Hidden inputs for Instructions Modal -->
    <?php
    $project_instructions = get_post_meta($project_id, '_cp_instructions', true);
    ?>
    <input type="hidden" id="vp-current-project" value="<?php echo esc_attr($project_id); ?>">
    <input type="hidden" id="vp-current-instructions" value="<?php echo esc_attr($project_instructions); ?>">

    <!-- Edit Instructions Modal -->
    <div id="vp-edit-instructions-modal" class="fixed inset-0 z-[9999] hidden">
        <div class="vp-modal-overlay fixed inset-0 bg-black/50 backdrop-blur-sm"></div>
        <div class="fixed inset-0 flex items-center justify-center p-4">
            <div class="vp-modal-dialog bg-white dark:bg-dark-surface rounded-xl shadow-xl max-w-2xl w-full max-h-[90vh] overflow-hidden">
                <!-- Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-dark-border">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        <?php esc_html_e('Edit Assistant Instructions', 'chatprojects'); ?>
                    </h2>
                    <button class="vp-modal-close p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-dark-hover transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Body -->
                <div class="px-6 py-4 space-y-4 overflow-y-auto" style="max-height: calc(90vh - 140px);">
                    <!-- Instructions Textarea -->
                    <div class="vp-instructions-editor">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            <?php esc_html_e('Instructions:', 'chatprojects'); ?>
                        </label>
                        <textarea
                            id="vp-instructions-textarea"
                            rows="12"
                            class="block w-full px-4 py-3 border border-gray-300 dark:border-dark-border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-dark-bg dark:text-white text-sm resize-y"
                            placeholder="<?php esc_html_e('Enter instructions for the AI assistant...', 'chatprojects'); ?>"
                        ></textarea>
                        <div class="flex justify-end mt-2">
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                <span id="vp-char-count">0</span> / 256,000 <?php esc_html_e('characters', 'chatprojects'); ?>
                            </span>
                        </div>
                    </div>

                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        <?php esc_html_e('These instructions guide how the AI responds in this project.', 'chatprojects'); ?>
                        <br>
                        <span class="text-xs"><?php esc_html_e('Note: Only project owners and admins can edit instructions.', 'chatprojects'); ?></span>
                    </p>
                </div>

                <!-- Footer -->
                <div class="px-6 py-4 bg-gray-50 dark:bg-dark-bg border-t border-gray-200 dark:border-dark-border flex justify-end gap-3">
                    <button
                        type="button"
                        id="vp-cancel-instructions"
                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-dark-surface border border-gray-300 dark:border-dark-border rounded-lg hover:bg-gray-50 dark:hover:bg-dark-hover transition-colors"
                    >
                        <?php esc_html_e('Cancel', 'chatprojects'); ?>
                    </button>
                    <button
                        type="button"
                        id="vp-save-instructions"
                        class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors"
                    >
                        <?php esc_html_e('Save Instructions', 'chatprojects'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Keyboard Detection Script -->
    <?php
    // New project button handler - using wp_print_inline_script_tag for WordPress compliance
    $new_project_script = "document.getElementById('vp-new-project-btn')?.addEventListener('click', function() { window.location.href = this.dataset.href; });";
    wp_print_inline_script_tag($new_project_script, array('id' => 'chatprojects-new-project-btn'));

    // Mobile keyboard detection script - using wp_print_inline_script_tag for WordPress compliance
    $keyboard_script = "(function() {
        // Visual Viewport API for reliable keyboard detection
        if (window.visualViewport && window.innerWidth < 768) {
            let initialHeight = window.visualViewport.height;
            let isKeyboardOpen = false;

            window.visualViewport.addEventListener('resize', function() {
                const currentHeight = window.visualViewport.height;
                const heightDiff = initialHeight - currentHeight;

                // Keyboard is likely open if viewport shrunk by more than 150px
                if (heightDiff > 150 && !isKeyboardOpen) {
                    isKeyboardOpen = true;
                    document.body.classList.add('vp-keyboard-open');
                    // Scroll input into view
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

            // Update initial height on orientation change
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
        var mainJsInDom = Array.from(document.querySelectorAll('script')).some(function(s) {
            return s.src && s.src.includes('main.js');
        });
        if (mainJsInDom) {
            return;
        }
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
