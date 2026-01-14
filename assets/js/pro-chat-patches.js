/**
 * Pro Chat Patches
 *
 * Patches for the Pro Chat interface including:
 * - BFCache handling (back/forward navigation)
 * - Nonce refresh on page load
 * - Alpine.js component patches for proper async behavior
 * - Title update handlers
 *
 * Requires: chatprData global to be defined before this script loads
 *
 * @package ChatProjects
 */

(function() {
    'use strict';

    // Ensure chatprData is available
    if (typeof chatprData === 'undefined') {
        console.error('ChatProjects: chatprData not defined');
        return;
    }

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

    // Fix: Patch the chat component's loadAvailableProviders to work correctly
    // The original minified function has issues with async execution
    Object.defineProperty(window, 'Alpine', {
        configurable: true,
        set: function(alpine) {
            var originalData = alpine.data;
            alpine.data = function(name, fn) {
                var wrappedFn = function() {
                    var component = fn.apply(this, arguments);

                    // Patch chat component methods
                    if (name === 'chat') {
                        // Patch loadAvailableProviders
                        component.loadAvailableProviders = async function() {
                            try {
                                var response = await fetch(chatprData.ajax_url, {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                    body: new URLSearchParams({
                                        action: 'chatpr_get_available_providers',
                                        nonce: chatprData.nonce
                                    })
                                });
                                var data = await response.json();

                                if (data.success && data.data && data.data.providers) {
                                    this.availableProviders = Object.entries(data.data.providers).map(function(entry) {
                                        return { id: entry[0], name: entry[1].name, models: entry[1].models };
                                    });

                                    if (this.availableProviders.length > 0) {
                                        var defaultProvider = chatprData.default_provider || this.availableProviders[0].id;
                                        var foundProvider = this.availableProviders.find(function(p) { return p.id === defaultProvider; });
                                        this.selectedProvider = foundProvider ? defaultProvider : this.availableProviders[0].id;

                                        var self = this;
                                        var currentProvider = this.availableProviders.find(function(p) { return p.id === self.selectedProvider; });
                                        if (currentProvider) {
                                            var defaultModel = chatprData.default_model || currentProvider.models[0];
                                            this.selectedModel = currentProvider.models.includes(defaultModel) ? defaultModel : currentProvider.models[0];
                                        }
                                    }
                                }
                            } catch (err) {
                                // Provider loading failed silently
                            }
                        };

                        // Patch loadMessages
                        component.loadMessages = async function() {
                            var self = this;
                            self.loading = true;
                            try {
                                var action = self.mode === 'general' ? 'chatpr_get_general_chat_history' : 'chatpr_load_chat_history';
                                var response = await fetch(chatprData.ajax_url, {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
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
                                    var msg = (data.data && data.data.message) ? data.data.message : 'Failed to load messages';
                                    if (window.VPToast) window.VPToast.error(msg);
                                }
                            } catch (err) {
                                if (window.VPToast) window.VPToast.error('Failed to load messages');
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
                            this.input = '';
                            this.attachedImages = [];

                            var userMessage = {
                                role: 'user',
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

                        // Patch streamResponse for general chat mode
                        component.streamResponse = async function(message, images) {
                            var self = this;
                            images = images || [];

                            if (typeof chatprData === 'undefined' || !chatprData.ajax_url || !chatprData.nonce) {
                                if (window.VPToast) window.VPToast.error('Session expired. Please refresh the page.');
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
                                role: 'assistant',
                                content: '',
                                timestamp: new Date().toISOString(),
                                streaming: true,
                                sources: []
                            };
                            this.messages.push(assistantMessage);
                            this.currentStreamingMessage = assistantMessage;

                            try {
                                var params = {
                                    action: this.mode === 'general' ? 'chatpr_stream_general_message' : 'chatpr_stream_chat_message',
                                    nonce: chatprData.nonce,
                                    message: message,
                                    chat_id: this.threadId || '',
                                    provider: this.selectedProvider,
                                    model: this.selectedModel
                                };

                                if (this.mode !== 'general') {
                                    params.thread_id = this.threadId || '';
                                    params.project_id = this.projectId || '';
                                }

                                if (images.length > 0) {
                                    params.images_base64 = JSON.stringify(images.map(function(img) {
                                        return { dataUrl: img.dataUrl, name: img.name, type: img.type };
                                    }));
                                }

                                var response = await fetch(chatprData.ajax_url, {
                                    method: 'POST',
                                    credentials: 'same-origin',
                                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                    body: new URLSearchParams(params),
                                    signal: this.abortController.signal
                                });

                                if (!response.ok) {
                                    throw new Error('HTTP error! status: ' + response.status);
                                }

                                var reader = response.body.getReader();
                                var decoder = new TextDecoder();
                                var buffer = '';
                                var done = false;

                                while (!done) {
                                    var result = await reader.read();
                                    if (result.done) break;

                                    buffer += decoder.decode(result.value, { stream: true });
                                    var lines = buffer.split('\n');
                                    buffer = lines.pop() || '';

                                    for (var i = 0; i < lines.length; i++) {
                                        var line = lines[i];
                                        if (line.trim() && line.startsWith('data: ')) {
                                            var data = line.slice(6);
                                            if (data === '[DONE]') {
                                                done = true;
                                                break;
                                            }
                                            try {
                                                var parsed = JSON.parse(data);
                                                if (parsed.type === 'content' && parsed.content) {
                                                    assistantMessage.content += parsed.content;
                                                    // Force Alpine reactivity
                                                    var idx = self.messages.findIndex(function(m) { return m.streaming && m.role === 'assistant'; });
                                                    if (idx !== -1) {
                                                        self.messages[idx] = Object.assign({}, self.messages[idx], { content: assistantMessage.content });
                                                        self.messages = self.messages.slice();
                                                    }
                                                    self.scrollToBottom();
                                                } else if (parsed.type === 'chat_id' && parsed.chat_id) {
                                                    self.threadId = parsed.chat_id;
                                                    window.dispatchEvent(new CustomEvent('vp:chat:updated', { detail: { threadId: self.threadId } }));
                                                } else if (parsed.type === 'sources' && parsed.sources) {
                                                    assistantMessage.sources = parsed.sources;
                                                } else if (parsed.type === 'error') {
                                                    if (window.VPToast) window.VPToast.error(parsed.content || 'An error occurred');
                                                    self.messages = self.messages.filter(function(m) { return m !== assistantMessage; });
                                                    return;
                                                } else if (parsed.type === 'title_update') {
                                                    if (parsed.title) {
                                                        // Optimistic UI update: pass title directly in event
                                                        window.dispatchEvent(new CustomEvent('vp:chat:title-updated', {
                                                            detail: {
                                                                chatId: parsed.chat_id,
                                                                title: parsed.title
                                                            }
                                                        }));
                                                    }
                                                } else if (parsed.type === 'done') {
                                                    // Don't break here - wait for [DONE] signal
                                                    // The provider sends {type: 'done'} but we still need to receive title_update
                                                }
                                            } catch (parseErr) {
                                                // Ignore JSON parse errors for incomplete chunks
                                            }
                                        }
                                    }
                                }

                                // Mark streaming complete
                                self.currentStreamingMessage = null;
                                var finalIdx = self.messages.findIndex(function(m) { return m.streaming && m.role === 'assistant'; });
                                if (finalIdx !== -1) {
                                    self.messages[finalIdx] = Object.assign({}, self.messages[finalIdx], { streaming: false });
                                    self.messages = self.messages.slice();
                                }

                                window.dispatchEvent(new CustomEvent('vp:chat:updated', { detail: { threadId: self.threadId } }));

                            } catch (err) {
                                if (err.name !== 'AbortError') {
                                    var errorMsg = err.message && err.message.includes('status')
                                        ? 'Server error: ' + err.message
                                        : 'Failed to get response: ' + (err.message || err.name || 'unknown error');
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
            Object.defineProperty(window, 'Alpine', {
                configurable: true,
                writable: true,
                value: alpine
            });
        },
        get: function() { return undefined; }
    });

    // Handle direct title updates from SSE (optimistic UI update)
    window.addEventListener('vp:chat:title-updated', function(e) {
        if (!e.detail || !e.detail.chatId || !e.detail.title) return;

        var found = false;
        // Find Alpine chatHistory components and update them directly
        document.querySelectorAll('[x-data*="chatHistory"]').forEach(function(el) {
            if (el._x_dataStack && el._x_dataStack[0]) {
                var component = el._x_dataStack[0];
                var idx = component.chats.findIndex(function(c) {
                    return c.id == e.detail.chatId;
                });
                if (idx !== -1) {
                    // Update the title directly
                    component.chats[idx] = Object.assign({}, component.chats[idx], {
                        title: e.detail.title
                    });
                    // Force Alpine reactivity by creating new array reference
                    component.chats = component.chats.slice();
                    found = true;
                }
            }
        });

        // Fallback: if we couldn't find the chat, trigger a full refresh
        if (!found) {
            window.dispatchEvent(new CustomEvent('vp:chat:updated', {
                detail: { threadId: e.detail.chatId }
            }));
        }
    });
})();
