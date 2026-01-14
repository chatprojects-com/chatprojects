/**
 * ChatProjects - Chat Interface JavaScript
 *
 * This file contains the chat functionality including:
 * - Chat component with SSE streaming
 * - Message handling and rendering
 * - Provider/model selection
 * - Image attachments
 *
 * @package ChatProjects
 * @version 1.0.0
 */

import { marked } from 'marked';
import hljs from 'highlight.js';
import { showToast } from './index.js';

// Configure marked with syntax highlighting
marked.setOptions({
    highlight: function(code, lang) {
        if (lang && hljs.getLanguage(lang)) {
            return hljs.highlight(code, { language: lang }).value;
        }
        return hljs.highlightAuto(code).value;
    },
    breaks: true,
    gfm: true
});

/**
 * Chat Component
 * Main chat interface with streaming support
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('chat', (projectId = null, threadId = null, mode = 'project') => ({
        // Configuration
        projectId: projectId,
        threadId: threadId,
        mode: mode, // 'project' or 'general'

        // Provider settings
        provider: 'openai',
        model: 'gpt-4o',
        availableProviders: [],
        selectedProvider: null,
        selectedModel: null,
        currentChatProvider: null,
        currentChatModel: null,

        // Chat state
        messages: [],
        input: '',
        loading: false,
        streaming: false,
        currentStreamingMessage: null,
        abortController: null,

        // Image attachments
        attachedImages: [],
        isDragOver: false,
        isProUser: (typeof chatprData !== 'undefined' && chatprData.is_pro_user) || false,
        maxImagesPerMessage: (typeof chatprData !== 'undefined' && parseInt(chatprData.max_images_per_message)) || 1,

        /**
         * Initialize chat component
         */
        async init() {
            // Load existing messages if thread exists
            if (this.threadId) {
                this.loadMessages();
            }

            // Load providers for general chat mode
            if (this.mode === 'general') {
                await this.loadAvailableProviders();
            }

            // Listen for new chat event
            window.addEventListener('vp:chat:new', () => {
                this.startNewChat();
            });

            // Listen for chat switch event
            window.addEventListener('vp:chat:switch', (e) => {
                this.switchChat(e.detail.threadId);
            });

            // Auto-resize textarea
            this.$watch('input', () => {
                this.autoResizeTextarea();
            });
        },

        /**
         * Load chat messages from server
         */
        async loadMessages() {
            // Wait for nonce if needed
            if (chatprData.nonceReady) {
                try {
                    await chatprData.nonceReady;
                } catch (e) {
                    // Continue anyway
                }
            }

            this.loading = true;

            try {
                const action = this.mode === 'general'
                    ? 'chatpr_get_general_chat_history'
                    : 'chatpr_load_chat_history';

                const response = await fetch(chatprData.ajax_url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: action,
                        nonce: chatprData.nonce,
                        chat_id: this.threadId
                    })
                });

                const data = await response.json();

                if (data.success) {
                    this.messages = data.data.messages || data.data;
                    this.scrollToBottom();
                } else {
                    showToast(data.data?.message || 'Failed to load messages', 'error');
                }
            } catch (error) {
                showToast('Failed to load messages', 'error');
            } finally {
                this.loading = false;
            }
        },

        /**
         * Send a message
         */
        async sendMessage() {
            const hasText = this.input.trim().length > 0;
            const hasImages = this.attachedImages.length > 0;

            if ((!hasText && !hasImages) || this.loading || this.streaming) {
                return;
            }

            const messageText = this.input.trim();
            const images = [...this.attachedImages];

            // Clear input
            this.input = '';
            this.attachedImages = [];

            // Add user message to display
            const userMessage = {
                role: 'user',
                content: messageText,
                timestamp: new Date().toISOString()
            };

            if (images.length > 0) {
                userMessage.images = images.map(img => img.dataUrl);
            }

            this.messages.push(userMessage);
            this.scrollToBottom();

            // Stream the response
            await this.streamResponse(messageText, images);
        },

        /**
         * Stream response from AI provider
         * @param {string} message - User message
         * @param {Array} images - Attached images
         */
        async streamResponse(message, images = []) {
            console.log('[ChatProjects] streamResponse called, mode:', this.mode);

            // Validate chatprData
            if (typeof chatprData === 'undefined' || !chatprData.ajax_url || !chatprData.nonce) {
                showToast('Session expired. Please refresh the page.', 'error');
                return;
            }

            console.log('[ChatProjects] chatprData check passed');

            // Wait for nonce if needed
            if (chatprData.nonceReady) {
                console.log('[ChatProjects] Waiting for nonceReady...');
                try {
                    await chatprData.nonceReady;
                    console.log('[ChatProjects] nonceReady resolved');
                } catch (e) {
                    console.log('[ChatProjects] nonceReady error:', e);
                }
            }

            this.streaming = true;
            this.abortController = new AbortController();

            // Add placeholder for assistant message
            const assistantMessage = {
                role: 'assistant',
                content: '',
                timestamp: new Date().toISOString(),
                streaming: true,
                sources: []
            };

            this.messages.push(assistantMessage);
            this.currentStreamingMessage = assistantMessage;

            try {
                if (this.mode === 'general') {
                    await this.streamGeneralChat(message, images, assistantMessage);
                } else {
                    await this.streamProjectChat(message, assistantMessage);
                }
            } catch (error) {
                if (error.name !== 'AbortError') {
                    console.error('[ChatProjects] Stream error:', error);
                    const msg = error.message && error.message.includes('status')
                        ? 'Server error: ' + error.message
                        : 'Failed to get response: ' + (error.message || error.name || 'unknown error');
                    showToast(msg, 'error');
                    this.messages = this.messages.filter(m => m !== assistantMessage);
                }
            } finally {
                this.streaming = false;

                // Mark message as not streaming
                const idx = this.messages.findIndex(m => m.streaming && m.role === 'assistant');
                if (idx !== -1) {
                    this.messages[idx] = { ...this.messages[idx], streaming: false };
                    this.messages = [...this.messages];
                }

                this.abortController = null;
            }
        },

        /**
         * Stream general chat (multi-provider)
         */
        async streamGeneralChat(message, images, assistantMessage) {
            console.log('[ChatProjects] Preparing general mode fetch...');

            const params = {
                action: 'chatpr_stream_general_message',
                nonce: chatprData.nonce,
                message: message,
                chat_id: this.threadId || '',
                provider: this.selectedProvider,
                model: this.selectedModel
            };

            console.log('[ChatProjects] Fetch params:', {
                action: params.action,
                provider: params.provider,
                model: params.model
            });

            // Add images if present
            if (images.length > 0) {
                params.images_base64 = JSON.stringify(images.map(img => ({
                    dataUrl: img.dataUrl,
                    name: img.name,
                    type: img.type
                })));
            }

            console.log('[ChatProjects] About to fetch...');

            const response = await fetch(chatprData.ajax_url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(params),
                signal: this.abortController.signal
            });

            console.log('[ChatProjects] Fetch completed, status:', response.status);

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            await this.processSSEStream(response, assistantMessage);

            // Update chat list
            window.dispatchEvent(new CustomEvent('vp:chat:updated', {
                detail: { threadId: this.threadId }
            }));

            // Refresh chat list after delay
            setTimeout(() => {
                window.dispatchEvent(new CustomEvent('vp:chat:updated', {
                    detail: { threadId: this.threadId }
                }));
            }, 2500);
        },

        /**
         * Stream project chat (with file search)
         */
        async streamProjectChat(message, assistantMessage) {
            const response = await fetch(chatprData.stream_url || chatprData.ajax_url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'chatpr_stream_chat_message',
                    nonce: chatprData.nonce,
                    message: message,
                    thread_id: this.threadId || '',
                    project_id: this.projectId || ''
                }),
                signal: this.abortController.signal
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            await this.processSSEStream(response, assistantMessage);

            // Update chat list
            window.dispatchEvent(new CustomEvent('vp:chat:updated', {
                detail: { threadId: this.threadId }
            }));

            setTimeout(() => {
                window.dispatchEvent(new CustomEvent('vp:chat:updated', {
                    detail: { threadId: this.threadId }
                }));
            }, 2500);
        },

        /**
         * Process SSE stream from response
         */
        async processSSEStream(response, assistantMessage) {
            const reader = response.body.getReader();
            const decoder = new TextDecoder();

            let buffer = '';
            let done = false;

            while (!done) {
                const { done: readerDone, value } = await reader.read();

                if (readerDone) break;

                buffer += decoder.decode(value, { stream: true });

                const lines = buffer.split('\n');
                buffer = lines.pop() || '';

                for (const line of lines) {
                    if (!line.trim() || !line.startsWith('data: ')) continue;

                    const data = line.slice(6);

                    if (data === '[DONE]') {
                        done = true;
                        break;
                    }

                    try {
                        const parsed = JSON.parse(data);

                        if (parsed.type === 'content' && parsed.content) {
                            console.log('[ChatProjects] Received content chunk:', parsed.content.substring(0, 50));

                            assistantMessage.content += parsed.content;

                            // Update message in array to trigger reactivity
                            const idx = this.messages.findIndex(m => m.streaming && m.role === 'assistant');
                            if (idx !== -1) {
                                this.messages[idx] = { ...this.messages[idx], content: assistantMessage.content };
                                this.messages = [...this.messages];
                            }

                            this.scrollToBottom();
                        } else if (parsed.type === 'sources' && parsed.sources) {
                            assistantMessage.sources = parsed.sources;
                        } else if (parsed.type === 'chat_id' && parsed.chat_id) {
                            this.threadId = parsed.chat_id;
                            window.dispatchEvent(new CustomEvent('vp:chat:updated', {
                                detail: { threadId: this.threadId }
                            }));
                        } else if (parsed.type === 'error') {
                            showToast(parsed.content || 'An error occurred', 'error');
                            this.messages = this.messages.filter(m => m !== assistantMessage);
                            return;
                        } else if (parsed.type === 'done') {
                            done = true;
                            break;
                        }
                    } catch (e) {
                        // Ignore JSON parse errors for partial data
                    }
                }
            }

            this.currentStreamingMessage = null;

            // Mark as not streaming
            const idx = this.messages.findIndex(m => m.streaming && m.role === 'assistant');
            if (idx !== -1) {
                this.messages[idx] = { ...this.messages[idx], streaming: false };
                this.messages = [...this.messages];
            }
        },

        /**
         * Stop message generation
         */
        stopGeneration() {
            if (this.abortController) {
                this.abortController.abort();
                this.streaming = false;

                const idx = this.messages.findIndex(m => m.streaming && m.role === 'assistant');
                if (idx !== -1) {
                    this.messages[idx] = { ...this.messages[idx], streaming: false };
                    this.messages = [...this.messages];
                }

                this.currentStreamingMessage = null;
                showToast('Generation stopped', 'info', 3000);
            }
        },

        /**
         * Regenerate the last response
         */
        async regenerateResponse() {
            if (this.messages.length < 2) return;

            // Remove last assistant message
            this.messages.pop();

            // Find last user message
            const lastUserMessage = [...this.messages].reverse().find(m => m.role === 'user');

            if (lastUserMessage) {
                await this.streamResponse(lastUserMessage.content);
            }
        },

        /**
         * Render markdown content
         * @param {string} content - Markdown content
         * @returns {string} HTML content
         */
        renderMarkdown(content) {
            return marked.parse(content);
        },

        /**
         * Copy message content to clipboard
         * @param {string} content - Content to copy
         */
        copyMessage(content) {
            navigator.clipboard.writeText(content).then(() => {
                showToast('Copied to clipboard', 'success', 2000);
            });
        },

        /**
         * Start a new chat
         */
        startNewChat() {
            this.threadId = null;
            this.messages = [];
            this.input = '';

            if (this.mode === 'general') {
                this.currentChatProvider = null;
                this.currentChatModel = null;
            }
        },

        /**
         * Switch to a different chat
         * @param {string} chatId - Chat ID to switch to
         */
        async switchChat(chatId) {
            this.threadId = chatId;
            this.messages = [];
            await this.loadMessages();

            if (this.mode === 'general') {
                await this.loadChatMetadata();
            }
        },

        /**
         * Auto-resize textarea based on content
         */
        autoResizeTextarea() {
            this.$nextTick(() => {
                const textarea = this.$refs.messageInput;
                if (textarea) {
                    textarea.style.height = 'auto';
                    textarea.style.height = Math.min(textarea.scrollHeight, 200) + 'px';
                }
            });
        },

        /**
         * Scroll messages container to bottom
         */
        scrollToBottom() {
            this.$nextTick(() => {
                const container = this.$refs.messagesContainer;
                if (container) {
                    container.scrollTop = container.scrollHeight;
                }
            });
        },

        /**
         * Handle keyboard events in input
         * @param {KeyboardEvent} event - Keyboard event
         */
        handleKeydown(event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                this.sendMessage();
            }
        },

        /**
         * Handle input focus (mobile keyboard handling)
         */
        handleInputFocus() {
            if (window.innerWidth < 768) {
                document.body.classList.add('vp-keyboard-open');

                const input = this.$refs.messageInput;
                if (input) {
                    setTimeout(() => {
                        input.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        this.scrollToBottom();
                    }, 300);
                }
            }
        },

        /**
         * Handle input blur
         */
        handleInputBlur() {
            document.body.classList.remove('vp-keyboard-open');
        },

        // ====================================================================
        // PROVIDER MANAGEMENT (General Chat Mode)
        // ====================================================================

        /**
         * Load available AI providers
         */
        async loadAvailableProviders() {
            if (chatprData.nonceReady) {
                try {
                    await chatprData.nonceReady;
                } catch (e) {
                    // Continue anyway
                }
            }

            try {
                const response = await fetch(chatprData.ajax_url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'chatpr_get_available_providers',
                        nonce: chatprData.nonce
                    })
                });

                const data = await response.json();

                if (data.success) {
                    this.availableProviders = Object.entries(data.data.providers).map(([id, provider]) => ({
                        id: id,
                        name: provider.name,
                        models: provider.models
                    }));

                    if (this.availableProviders.length > 0) {
                        // Set default provider
                        const defaultProviderId = chatprData.default_provider || this.availableProviders[0].id;
                        const defaultProvider = this.availableProviders.find(p => p.id === defaultProviderId);
                        this.selectedProvider = defaultProvider ? defaultProviderId : this.availableProviders[0].id;

                        // Set default model
                        const provider = this.availableProviders.find(p => p.id === this.selectedProvider);
                        if (provider) {
                            const defaultModel = chatprData.default_model || provider.models[0];
                            this.selectedModel = provider.models.includes(defaultModel) ? defaultModel : provider.models[0];
                        }
                    }
                }
            } catch (error) {
                showToast('Failed to load AI providers', 'error');
            }
        },

        /**
         * Get models for current provider
         */
        get currentProviderModels() {
            const provider = this.availableProviders.find(p => p.id === this.selectedProvider);
            return provider ? provider.models : [];
        },

        /**
         * Handle provider change
         */
        async handleProviderChange() {
            const provider = this.availableProviders.find(p => p.id === this.selectedProvider);
            if (provider && provider.models.length > 0) {
                this.selectedModel = provider.models[0];
            }

            // Confirm if switching mid-conversation
            if (this.threadId && this.messages.length > 0) {
                await this.confirmProviderSwitch();
            }
        },

        /**
         * Handle model change
         */
        async handleModelChange() {
            if (this.threadId && this.messages.length > 0) {
                await this.confirmProviderSwitch();
            }
        },

        /**
         * Confirm provider/model switch mid-conversation
         */
        async confirmProviderSwitch() {
            const currentProvider = this.currentChatProvider || this.selectedProvider;
            const currentModel = this.currentChatModel || this.selectedModel;

            if (currentProvider === this.selectedProvider && currentModel === this.selectedModel) {
                return;
            }

            const providerName = this.availableProviders.find(p => p.id === this.selectedProvider)?.name;

            const confirmed = confirm(
                `Switching to ${providerName} (${this.selectedModel}) will create a new conversation. ` +
                `Your current chat history will be preserved in the sidebar.\n\nContinue?`
            );

            if (confirmed) {
                await this.createNewChatWithProvider();
            } else {
                // Revert selection
                this.selectedProvider = currentProvider;
                this.selectedModel = currentModel;
            }
        },

        /**
         * Create new chat with selected provider
         */
        async createNewChatWithProvider() {
            try {
                const response = await fetch(chatprData.ajax_url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'chatpr_create_general_chat',
                        nonce: chatprData.nonce,
                        provider: this.selectedProvider,
                        model: this.selectedModel
                    })
                });

                const data = await response.json();

                if (data.success) {
                    this.threadId = data.data.chat_id;
                    this.messages = [];
                    this.currentChatProvider = this.selectedProvider;
                    this.currentChatModel = this.selectedModel;

                    window.dispatchEvent(new CustomEvent('vp:chat:updated', {
                        detail: { threadId: this.threadId }
                    }));

                    showToast('New conversation created', 'success', 2000);
                } else {
                    showToast(data.data?.message || 'Failed to create conversation', 'error');
                }
            } catch (error) {
                showToast('Failed to create new conversation', 'error');
            }
        },

        /**
         * Load metadata for current chat
         */
        async loadChatMetadata() {
            try {
                const response = await fetch(chatprData.ajax_url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'chatpr_get_chat_metadata',
                        nonce: chatprData.nonce,
                        chat_id: this.threadId
                    })
                });

                const data = await response.json();

                if (data.success) {
                    this.currentChatProvider = data.data.provider;
                    this.currentChatModel = data.data.model;
                    this.selectedProvider = data.data.provider;
                    this.selectedModel = data.data.model;
                }
            } catch (error) {
                // Silently fail
            }
        },

        // ====================================================================
        // IMAGE ATTACHMENTS (General Chat Mode)
        // ====================================================================

        /**
         * Handle image file selection
         * @param {Event} event - File input change event
         */
        async handleImageSelect(event) {
            if (this.mode !== 'general') return;

            const files = Array.from(event.target.files);
            await this.addImages(files);
            event.target.value = '';
        },

        /**
         * Handle paste event for images
         * @param {ClipboardEvent} event - Paste event
         */
        async handlePaste(event) {
            if (this.mode !== 'general') return;

            const items = event.clipboardData?.items;
            if (!items) return;

            const imageFiles = [];
            for (let i = 0; i < items.length; i++) {
                if (items[i].type.startsWith('image/')) {
                    const file = items[i].getAsFile();
                    if (file) imageFiles.push(file);
                }
            }

            if (imageFiles.length > 0) {
                event.preventDefault();
                await this.addImages(imageFiles);
            }
        },

        /**
         * Handle drop event for images
         * @param {DragEvent} event - Drop event
         */
        async handleDrop(event) {
            if (this.mode !== 'general') return;

            this.isDragOver = false;

            const files = Array.from(event.dataTransfer.files).filter(f => f.type.startsWith('image/'));
            if (files.length > 0) {
                await this.addImages(files);
            }
        },

        /**
         * Add images to attachment list
         * @param {Array} files - Image files
         */
        async addImages(files) {
            const maxAllowed = this.maxImagesPerMessage === -1 ? Infinity : this.maxImagesPerMessage;
            const remaining = maxAllowed - this.attachedImages.length;

            if (remaining <= 0) {
                showToast(
                    chatprData.i18n?.maxImagesReached || 'Maximum images reached',
                    'warning'
                );
                return;
            }

            const filesToAdd = files.slice(0, remaining);

            for (const file of filesToAdd) {
                // Validate type
                if (!['image/jpeg', 'image/png', 'image/gif', 'image/webp'].includes(file.type)) {
                    showToast(
                        chatprData.i18n?.invalidImageType || 'Invalid image type. Allowed: JPEG, PNG, GIF, WebP.',
                        'error'
                    );
                    continue;
                }

                // Validate size
                const maxSize = chatprData.max_image_size || 10 * 1024 * 1024;
                if (file.size > maxSize) {
                    const sizeMB = Math.round(maxSize / (1024 * 1024));
                    showToast(
                        (chatprData.i18n?.imageTooLarge || 'Image exceeds {size} MB limit').replace('{size}', sizeMB),
                        'error'
                    );
                    continue;
                }

                try {
                    const dataUrl = await this.fileToDataUrl(file);
                    this.attachedImages.push({
                        name: file.name,
                        type: file.type,
                        size: file.size,
                        dataUrl: dataUrl
                    });
                } catch (error) {
                    showToast(
                        chatprData.i18n?.imageProcessError || 'Failed to process image',
                        'error'
                    );
                }
            }
        },

        /**
         * Convert file to data URL
         * @param {File} file - File to convert
         * @returns {Promise<string>} Data URL
         */
        fileToDataUrl(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = () => resolve(reader.result);
                reader.onerror = () => reject(new Error('Failed to read file'));
                reader.readAsDataURL(file);
            });
        },

        /**
         * Remove image from attachments
         * @param {number} index - Index to remove
         */
        removeImage(index) {
            this.attachedImages.splice(index, 1);
        },

        /**
         * Open image in new window
         * @param {string} dataUrl - Image data URL
         */
        openImageModal(dataUrl) {
            window.open(dataUrl, '_blank');
        }
    }));
});
