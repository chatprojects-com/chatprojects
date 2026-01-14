<?php
/**
 * Modern Chat Interface Template
 * Uses Alpine.js for reactivity
 *
 * @package ChatProjects
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$chatprojects_chat_context = array(
    'chat_mode' => isset($cp_chat_mode) ? $cp_chat_mode : null,
    'project_id' => isset($chatpr_project_id) ? $chatpr_project_id : null,
);

call_user_func(function () use ($chatprojects_chat_context) {
    $cp_chat_mode = $chatprojects_chat_context['chat_mode'];
    $chatpr_project_id = $chatprojects_chat_context['project_id'];

    if (!isset($cp_chat_mode)) {
        $cp_chat_mode = 'project';
    }

    if ($cp_chat_mode === 'project' && !isset($chatpr_project_id)) {
        $chatpr_project_id = get_the_ID();
    } elseif (!isset($chatpr_project_id)) {
        $chatpr_project_id = null;
    }

    $chatpr_project_instructions = '';
    if ($cp_chat_mode === 'project' && $chatpr_project_id) {
        $chatpr_project_instructions = get_post_meta($chatpr_project_id, '_cp_instructions', true);
    }

    $cp_x_data_param1 = is_null($chatpr_project_id) ? 'null' : esc_js($chatpr_project_id);
    $cp_x_data_full = "chat({$cp_x_data_param1}, null, '" . esc_js($cp_chat_mode) . "')";
    // Styles (model dropdown, thinking wave animation) live in templates.css per WP guidelines
    ?>

<!-- Chat Interface with Alpine.js -->
<div
    x-data="<?php echo esc_attr( $cp_x_data_full ); ?>"
    class="flex flex-col h-full bg-white dark:bg-dark-bg"
>
    <?php if ($cp_chat_mode === 'general'): ?>
    <!-- Provider/Model Selection Toolbar (Pro Chat Only) -->
    <div class="flex-shrink-0 border-b border-neutral-200 dark:border-dark-border bg-white dark:bg-dark-surface relative overflow-visible"
         x-data="{
            modelSelectorOpen: false,
            modelNotification: '',
            showModelNotification: false,
            notifyModelChange(model) {
                this.modelNotification = 'Model: ' + model;
                this.showModelNotification = true;
                setTimeout(() => { this.showModelNotification = false; }, 2000);
            }
         }"
         @model-changed.window="notifyModelChange($event.detail.model)">

        <!-- Model Change Notification -->
        <div
            x-show="showModelNotification"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 transform -translate-y-2"
            x-transition:enter-end="opacity-100 transform translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="absolute top-full left-1/2 transform -translate-x-1/2 mt-2 z-50"
        >
            <div class="cp-model-notification px-3 py-1.5 text-xs rounded-full shadow-xl flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <span x-text="modelNotification"></span>
            </div>
        </div>

        <!-- Mobile: Compact header showing current selection (visible < md) -->
        <div class="md:hidden flex items-center justify-between px-4 py-2">
            <div class="flex items-center gap-2 text-sm text-neutral-700 dark:text-neutral-300 min-w-0">
                <svg class="w-4 h-4 flex-shrink-0 text-neutral-500 dark:text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                <!-- Loading placeholder until providers load -->
                <span x-show="!availableProviders || availableProviders.length === 0" class="truncate text-neutral-400 dark:text-neutral-500"><?php esc_html_e('Loading...', 'chatprojects'); ?></span>
                <!-- Actual provider/model display -->
                <span x-show="availableProviders && availableProviders.length > 0" x-cloak class="truncate" x-text="(availableProviders.find(p => p.id === selectedProvider)?.name || selectedProvider) + ' / ' + selectedModel"></span>
            </div>
            <button @click="modelSelectorOpen = !modelSelectorOpen"
                    class="md:hidden p-2 -mr-2 rounded-lg hover:bg-neutral-100 dark:hover:bg-dark-hover text-neutral-500 dark:text-neutral-400 transition-colors"
                    :aria-expanded="modelSelectorOpen"
                    aria-label="<?php esc_attr_e('Toggle model selector', 'chatprojects'); ?>">
                <svg class="w-5 h-5 transition-transform duration-200" :class="{ 'rotate-180': modelSelectorOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
        </div>

        <!-- Full selector: always visible on desktop (md+), collapsible on mobile -->
        <div class="px-4 py-3 cp-model-selector"
             :class="{ 'is-open': modelSelectorOpen }">
            <div class="flex items-center gap-3">
                <!-- Provider Dropdown -->
                <div class="flex-1">
                    <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-400 mb-1">
                        <?php esc_html_e('AI Provider', 'chatprojects'); ?>
                    </label>
                    <select
                        x-model="selectedProvider"
                        @change="handleProviderChange()"
                        class="w-full px-3 py-2 text-sm border border-neutral-300 dark:border-dark-border rounded-lg bg-white dark:bg-dark-bg text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                    >
                        <template x-for="provider in availableProviders" :key="provider.id">
                            <option :value="provider.id" x-text="provider.name"></option>
                        </template>
                    </select>
                </div>

                <!-- Model Dropdown with Filter -->
                <div class="flex-1">
                    <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-400 mb-1">
                        <?php esc_html_e('Model', 'chatprojects'); ?>
                    </label>
                    <div class="relative" x-data="{ modelFilter: '', showModelDropdown: false }">
                        <!-- Filter Input (shown when many models) -->
                        <template x-if="currentProviderModels.length > 10">
                            <input
                                type="text"
                                x-model="modelFilter"
                                @focus="showModelDropdown = true"
                                @click.away="showModelDropdown = false"
                                :placeholder="selectedModel || '<?php esc_attr_e('Search models...', 'chatprojects'); ?>'"
                                class="cp-model-filter-input w-full px-3 py-2 text-sm border border-neutral-300 dark:border-dark-border rounded-lg bg-white dark:bg-dark-bg text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                            />
                        </template>
                        <!-- Dropdown list (shown when filtering with many models) -->
                        <div
                            x-show="showModelDropdown && currentProviderModels.length > 10"
                            x-transition
                            class="absolute z-50 w-full mt-1 max-h-60 overflow-y-auto bg-white dark:bg-dark-surface border border-neutral-300 dark:border-dark-border rounded-lg shadow-lg"
                        >
                            <template x-for="model in currentProviderModels.filter(m => m.toLowerCase().includes(modelFilter.toLowerCase()))" :key="model">
                                <button
                                    type="button"
                                    @click="selectedModel = model; modelFilter = ''; showModelDropdown = false; handleModelChange(); window.dispatchEvent(new CustomEvent('model-changed', { detail: { model: model } }));"
                                    class="cp-model-dropdown-item w-full px-3 py-2 text-left text-sm text-neutral-900 dark:text-neutral-100"
                                    :class="{ 'bg-blue-600 text-white font-medium': selectedModel === model }"
                                    x-text="model"
                                ></button>
                            </template>
                            <template x-if="currentProviderModels.filter(m => m.toLowerCase().includes(modelFilter.toLowerCase())).length === 0">
                                <div class="px-3 py-2 text-sm text-neutral-500 dark:text-neutral-400">
                                    <?php esc_html_e('No models found', 'chatprojects'); ?>
                                </div>
                            </template>
                        </div>
                        <!-- Standard select (for fewer models) -->
                        <template x-if="currentProviderModels.length <= 10">
                            <select
                                x-model="selectedModel"
                                @change="handleModelChange(); window.dispatchEvent(new CustomEvent('model-changed', { detail: { model: selectedModel } }));"
                                class="w-full px-3 py-2 text-sm border border-neutral-300 dark:border-dark-border rounded-lg bg-white dark:bg-dark-bg text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                            >
                                <template x-for="model in currentProviderModels" :key="model">
                                    <option :value="model" x-text="model"></option>
                                </template>
                            </select>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Messages Container -->
    <div
        x-ref="messagesContainer"
        class="flex-1 overflow-y-auto px-4 py-6 space-y-6"
    >
        <!-- Welcome Message (shown when no messages) -->
        <template x-if="messages.length === 0 && !loading">
            <div class="flex flex-col items-center justify-center h-full text-center">
                <div class="w-16 h-16 mb-4 rounded-full bg-primary-100 dark:bg-primary-900/20 flex items-center justify-center">
                    <svg class="w-8 h-8 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-neutral-900 dark:text-neutral-100 mb-2">
                    <?php
                    if ($cp_chat_mode === 'general') {
                        esc_html_e('AI Chat', 'chatprojects');
                    } else {
                        esc_html_e('Ask me anything about your project', 'chatprojects');
                    }
                    ?>
                </h3>
                <p class="text-neutral-700 dark:text-neutral-400 max-w-md mb-4">
                    <?php
                    if ($cp_chat_mode === 'general') {
                        esc_html_e('Load provider API keys in the backend, and chat with them here!', 'chatprojects');
                    } else {
                        esc_html_e('I can help you understand your files and answer questions. Get started by uploading project documents (PDF or Office) in the Files page.', 'chatprojects');
                    }
                    ?>
                </p>

                <?php if ($cp_chat_mode === 'project' && !empty($chatpr_project_instructions)): ?>
                <div class="vp-instructions-preview bg-neutral-50 dark:bg-dark-surface border border-neutral-200 dark:border-dark-border rounded-xl p-4 mb-4 max-w-lg text-left">
                    <p class="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wide mb-2">
                        <?php esc_html_e('Current Instructions', 'chatprojects'); ?>
                    </p>
                    <p class="vp-instructions-text text-sm text-neutral-700 dark:text-neutral-300 italic">
                        <?php echo esc_html(wp_trim_words($chatpr_project_instructions, 30, '...')); ?>
                    </p>
                </div>
                <?php elseif ($cp_chat_mode === 'project'): ?>
                <div class="vp-welcome-no-instructions text-sm text-neutral-500 dark:text-neutral-400 mb-4">
                    <?php esc_html_e('No custom instructions set for this project.', 'chatprojects'); ?>
                </div>
                <?php endif; ?>

                <?php if ($cp_chat_mode === 'project'): ?>
                <a href="#" id="vp-edit-instructions-link" class="inline-flex items-center gap-1 text-sm text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    <?php esc_html_e('Edit Instructions', 'chatprojects'); ?>
                </a>
                <?php endif; ?>
            </div>
        </template>

        <!-- Loading State -->
        <template x-if="loading">
            <div class="flex items-center justify-center h-full">
                <div class="flex flex-col items-center gap-3">
                    <svg class="animate-spin h-8 w-8 text-primary-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="text-sm text-neutral-700 dark:text-neutral-400">
                        <?php esc_html_e('Loading messages...', 'chatprojects'); ?>
                    </p>
                </div>
            </div>
        </template>

        <!-- Messages -->
        <template x-for="(message, index) in messages" :key="index">
            <div
                :class="message.role === 'user' ? 'flex justify-end' : 'flex justify-start'"
                class="message-wrapper"
            >
                <!-- Message Bubble -->
                <div
                    :class="message.role === 'user'
                        ? 'bg-primary-600 text-white max-w-[70%]'
                        : 'bg-neutral-100 dark:bg-dark-surface border border-neutral-200 dark:border-dark-border text-neutral-900 dark:text-neutral-100 max-w-[80%]'"
                    class="rounded-2xl px-4 py-2.5 relative group"
                >
                    <!-- User Message -->
                    <template x-if="message.role === 'user'">
                        <div>
                            <!-- Message images (if any) -->
                            <template x-if="message.images && message.images.length > 0">
                                <div class="flex flex-wrap gap-2 mb-2">
                                    <template x-for="(img, imgIndex) in message.images" :key="imgIndex">
                                        <img
                                            :src="img"
                                            @click="openImageModal(img)"
                                            class="max-w-[150px] max-h-[100px] rounded-lg cursor-pointer hover:opacity-90 transition-opacity object-cover"
                                            alt="Attached image"
                                        >
                                    </template>
                                </div>
                            </template>
                            <div class="whitespace-pre-wrap break-words text-sm" x-text="message.content"></div>
                        </div>
                    </template>

                    <!-- Assistant Message (with markdown rendering) -->
                    <template x-if="message.role === 'assistant'">
                        <div>
                            <!-- Streaming indicator - Gradient Wave -->
                            <template x-if="message.streaming">
                                <div class="flex items-center justify-center py-4">
                                    <div class="thinking-wave">
                                        <span></span><span></span><span></span><span></span><span></span>
                                    </div>
                                </div>
                            </template>

                            <!-- Message content with markdown -->
                            <div
                                class="prose dark:prose-invert prose-sm max-w-none"
                                x-html="renderMarkdown(message.content)"
                            ></div>

                            <!-- Sources Section -->
                            <template x-if="message.sources && message.sources.length > 0">
                                <div class="mt-4 pt-3 border-t border-neutral-300 dark:border-neutral-600">
                                    <div class="text-xs font-semibold text-neutral-700 dark:text-neutral-400 mb-2">
                                        <?php esc_html_e('Sources:', 'chatprojects'); ?>
                                    </div>
                                    <div class="space-y-1">
                                        <template x-for="(source, sourceIndex) in message.sources" :key="sourceIndex">
                                            <div class="flex items-center gap-2 text-xs text-neutral-700 dark:text-neutral-300">
                                                <svg class="w-3 h-3 flex-shrink-0 text-neutral-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                                </svg>
                                                <span x-text="`[${sourceIndex + 1}] ${source.filename}`"></span>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    <!-- Message Actions (show on hover for assistant messages) -->
                    <template x-if="message.role === 'assistant' && !message.streaming">
                        <div class="absolute -bottom-8 left-0 opacity-0 group-hover:opacity-100 transition-opacity flex gap-1 bg-white dark:bg-dark-surface rounded-lg shadow-lg border border-neutral-200 dark:border-dark-border p-1">
                            <!-- Copy Button -->
                            <button
                                @click="copyMessage(message.content)"
                                class="p-2 text-neutral-600 dark:text-neutral-400 hover:text-primary-600 dark:hover:text-primary-400 hover:bg-neutral-100 dark:hover:bg-dark-hover rounded transition-colors"
                                title="<?php esc_attr_e('Copy message', 'chatprojects'); ?>"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                </svg>
                            </button>

                            <!-- Regenerate Button (only for last assistant message) -->
                            <template x-if="index === messages.length - 1">
                                <button
                                    @click="regenerateResponse()"
                                    class="p-2 text-neutral-600 dark:text-neutral-400 hover:text-primary-600 dark:hover:text-primary-400 hover:bg-neutral-100 dark:hover:bg-dark-hover rounded transition-colors"
                                    title="<?php esc_attr_e('Regenerate response', 'chatprojects'); ?>"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                    </svg>
                                </button>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>

    <!-- Input Area -->
    <div class="flex-shrink-0 border-t border-neutral-200 dark:border-dark-border bg-white dark:bg-dark-surface">
        <div class="px-4 py-4">
            <!-- Stop Generation Button (show when streaming) -->
            <template x-if="streaming">
                <div class="mb-3 flex items-center justify-center">
                    <button
                        @click="stopGeneration()"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-neutral-700 dark:text-neutral-300 bg-neutral-100 dark:bg-dark-hover border border-neutral-300 dark:border-dark-border rounded-lg hover:bg-neutral-200 dark:hover:bg-neutral-700 transition-colors"
                    >
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <rect x="6" y="6" width="8" height="8" rx="1"/>
                        </svg>
                        <span><?php esc_html_e('Stop generating', 'chatprojects'); ?></span>
                    </button>
                </div>
            </template>

            <!-- Input Form -->
            <form @submit.prevent="sendMessage()" class="relative">
                <?php if ($cp_chat_mode === 'general'): ?>
                <!-- Image Preview Area (General Chat Only) -->
                <template x-if="attachedImages && attachedImages.length > 0">
                    <div class="flex flex-wrap gap-2 mb-3 p-2 bg-neutral-50 dark:bg-dark-bg rounded-lg border border-neutral-200 dark:border-dark-border">
                        <template x-for="(img, index) in attachedImages" :key="index">
                            <div class="relative group">
                                <img
                                    :src="img.dataUrl"
                                    :alt="img.name"
                                    class="w-16 h-16 object-cover rounded-lg border border-neutral-200 dark:border-dark-border"
                                >
                                <button
                                    type="button"
                                    @click="removeImage(index)"
                                    class="absolute -top-2 -right-2 w-5 h-5 bg-red-500 text-white rounded-full flex items-center justify-center text-xs opacity-0 group-hover:opacity-100 transition-opacity hover:bg-red-600"
                                    title="<?php esc_attr_e('Remove image', 'chatprojects'); ?>"
                                >
                                    &times;
                                </button>
                            </div>
                        </template>
                    </div>
                </template>

                <?php endif; ?>

                <div class="flex items-end gap-3">
                    <?php if ($cp_chat_mode === 'general'): ?>
                    <!-- Attach Image Button (General Chat Only - Free: Images Only) -->
                    <button
                        type="button"
                        @click="$refs.imageInput.click()"
                        :disabled="streaming || loading || (maxImagesPerMessage !== -1 && attachedImages && attachedImages.length >= maxImagesPerMessage)"
                        class="flex-shrink-0 p-2 self-center text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        title="<?php esc_attr_e('Attach image', 'chatprojects'); ?>"
                    >
                        <!-- Plus Circle Icon -->
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </button>
                    <input
                        type="file"
                        x-ref="imageInput"
                        @change="handleImageSelect($event)"
                        accept="image/jpeg,image/png,image/gif,image/webp"
                        :multiple="maxImagesPerMessage === -1"
                        class="hidden"
                    >
                    <?php endif; ?>

                    <div class="flex-1 relative"
                        <?php if ($cp_chat_mode === 'general'): ?>
                        :class="{ 'ring-2 ring-primary-500 rounded-xl': isDragOver }"
                        @dragover.prevent="isDragOver = true"
                        @dragleave.prevent="isDragOver = false"
                        @drop.prevent="handleDrop($event)"
                        <?php endif; ?>
                    >
                        <textarea
                            x-ref="messageInput"
                            x-model="input"
                            @keydown="handleKeydown($event)"
                            <?php if ($cp_chat_mode === 'general'): ?>
                            @paste="handlePaste($event)"
                            <?php endif; ?>
                            @focus="handleInputFocus()"
                            @blur="handleInputBlur()"
                            :disabled="streaming || loading"
                            placeholder="<?php esc_attr_e('Type your message...', 'chatprojects'); ?>"
                            rows="1"
                            class="vp-chat-textarea w-full px-4 py-3 pr-12 text-neutral-900 dark:text-neutral-100 bg-neutral-50 dark:bg-dark-bg border border-neutral-300 dark:border-dark-border rounded-xl resize-none focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                            style="max-height: 200px; min-height: 52px;"
                        ></textarea>
                    </div>

                    <!-- Send Button -->
                    <button
                        type="submit"
                        :disabled="(!input.trim() && (!attachedImages || attachedImages.length === 0)) || streaming || loading"
                        class="flex-shrink-0 p-3 bg-primary-600 text-white rounded-xl hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-dark-bg disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                    </button>
                </div>
            </form>

            <!-- Helper Text (hidden on mobile) -->
            <p class="mt-2 text-xs text-neutral-500 dark:text-neutral-400 text-center hidden md:block">
                <?php esc_html_e('Do not enter passwords, API keys, or sensitive business information.', 'chatprojects'); ?>
            </p>
        </div>
    </div>
</div>
</div>
<?php
});
