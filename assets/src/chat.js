/**
 * ChatProjects Chat JavaScript
 * Chat interface with streaming support
 */

// DO NOT import Alpine - use the global instance from main.js to prevent double initialization
import { marked } from 'marked';
import hljs from 'highlight.js';

// Import main styles (shared)
import './main.css';

// Chat-specific imports
import { toast } from './utils/toast.js';

// Configure marked for markdown rendering
marked.setOptions({
  highlight: function(code, lang) {
    if (lang && hljs.getLanguage(lang)) {
      return hljs.highlight(code, { language: lang }).value;
    }
    return hljs.highlightAuto(code).value;
  },
  breaks: true,
  gfm: true,
});

// Wait for Alpine to be available, then register chat component
document.addEventListener('alpine:init', () => {
  window.Alpine.data('chat', (projectId = null, threadId = null, mode = 'project') => ({
  projectId: projectId,
  threadId: threadId,
  mode: mode, // 'project' or 'general'
  provider: 'openai',
  model: 'gpt-4o',
  messages: [],
  input: '',
  loading: false,
  streaming: false,
  currentStreamingMessage: null,
  abortController: null,

  // Provider/Model Management (Pro Chat only)
  availableProviders: [],
  selectedProvider: null,
  selectedModel: null,
  currentChatProvider: null,  // Track what provider the active chat uses
  currentChatModel: null,

  // Image Upload (General Chat only)
  attachedImages: [],
  isDragOver: false,
  isProUser: typeof chatprData !== 'undefined' ? (chatprData.is_pro_user || false) : false,
  maxImagesPerMessage: typeof chatprData !== 'undefined' ? (parseInt(chatprData.max_images_per_message) || 1) : 1,

  async init() {
    if (this.threadId) {
      this.loadMessages();
    }

    // Load available providers for Pro Chat
    if (this.mode === 'general') {
      await this.loadAvailableProviders();
    }

    // Listen for new chat event
    window.addEventListener('chatpr:chat:new', () => {
      this.startNewChat();
    });

    // Listen for chat switch event
    window.addEventListener('chatpr:chat:switch', (e) => {
      this.switchChat(e.detail.threadId);
    });

    // Auto-resize textarea
    this.$watch('input', () => {
      this.autoResizeTextarea();
    });
  },

  async loadMessages() {
    this.loading = true;
    console.log('[DEBUG] loadMessages() starting - mode:', this.mode, 'threadId:', this.threadId);

    try {
      // Use correct endpoint based on chat mode
      const action = this.mode === 'general'
        ? 'chatpr_get_general_chat_history'  // For Pro Chat
        : 'chatpr_load_chat_history';         // For project chats

      console.log('[DEBUG] Using action:', action, 'chat_id:', this.threadId);

      const response = await fetch(chatprData.ajax_url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: action,
          nonce: chatprData.nonce,
          chat_id: this.threadId
        })
      });

      console.log('[DEBUG] Response status:', response.status);
      const data = await response.json();
      console.log('[DEBUG] Response data:', data);

      if (data.success) {
        // Handle different response formats
        this.messages = data.data.messages || data.data;
        console.log('[DEBUG] Loaded messages:', this.messages.length);
        this.scrollToBottom();
      } else {
        console.error('[DEBUG] Load failed:', data.data?.message);
        toast(data.data?.message || 'Failed to load messages', 'error');
      }
    } catch (error) {
      console.error('[DEBUG] Exception in loadMessages:', error);
      toast('Failed to load messages', 'error');
    } finally {
      this.loading = false;
      console.log('[DEBUG] loadMessages() complete');
    }
  },

  async sendMessage() {
    // Allow sending with just images (no text) in general mode
    const hasText = this.input.trim().length > 0;
    const hasImages = this.attachedImages.length > 0;

    if ((!hasText && !hasImages) || this.loading || this.streaming) {
      return;
    }

    const userMessage = this.input.trim();
    const userImages = [...this.attachedImages]; // Copy images
    this.input = '';
    this.attachedImages = []; // Clear attached images

    // Add user message to UI immediately (with images)
    const messageObj = {
      role: 'user',
      content: userMessage,
      timestamp: new Date().toISOString()
    };

    // Add images to message object for display
    if (userImages.length > 0) {
      messageObj.images = userImages.map(img => img.dataUrl);
    }

    this.messages.push(messageObj);
    this.scrollToBottom();

    // Start streaming response (with images)
    await this.streamResponse(userMessage, userImages);
  },

  async streamResponse(userMessage, userImages = []) {
    this.streaming = true;
    this.abortController = new AbortController();

    // Add placeholder assistant message
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
      // For general chat mode, use non-streaming endpoint (for now)
      if (this.mode === 'general') {
        // Build request body
        const requestBody = {
          action: 'chatpr_send_general_message',
          nonce: chatprData.nonce,
          message: userMessage,
          chat_id: this.threadId || '',
          provider: this.selectedProvider,
          model: this.selectedModel
        };

        // Add images if present (as JSON array of base64 data URLs)
        if (userImages.length > 0) {
          requestBody.images_base64 = JSON.stringify(userImages.map(img => ({
            dataUrl: img.dataUrl,
            name: img.name,
            type: img.type
          })));
        }

        const response = await fetch(chatprData.ajax_url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: new URLSearchParams(requestBody),
          signal: this.abortController.signal
        });

        const data = await response.json();

        if (data.success) {
          assistantMessage.content = data.data.response;
          assistantMessage.streaming = false;

          // Force Alpine.js to detect the change by updating the array reference
          const messageIndex = this.messages.indexOf(assistantMessage);
          if (messageIndex !== -1) {
            // Create a new object to trigger reactivity
            this.messages[messageIndex] = Object.assign({}, assistantMessage);
          }

          // Update threadId if this was a new chat
          if (data.data.chat_id && !this.threadId) {
            this.threadId = data.data.chat_id;
            // Notify chat history to update
            window.dispatchEvent(new CustomEvent('chatpr:chat:updated', {
              detail: { threadId: this.threadId }
            }));
          }

          this.scrollToBottom();
        } else {
          throw new Error(data.data?.message || 'Failed to send message');
        }

        this.streaming = false;
        this.currentStreamingMessage = null;
        return;
      }

      // For project mode, use streaming endpoint
      const response = await fetch(chatprData.ajax_url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: 'chatpr_stream_chat_message',
          nonce: chatprData.nonce,
          message: userMessage,
          thread_id: this.threadId || '',
          project_id: this.projectId || ''
        }),
        signal: this.abortController.signal
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      // Handle SSE streaming response
      const reader = response.body.getReader();
      const decoder = new TextDecoder();
      let buffer = '';
      let streamComplete = false;

      while (true && !streamComplete) {
        const { done, value } = await reader.read();

        if (done) {
          break;
        }

        // Decode chunk and add to buffer
        const decoded = decoder.decode(value, { stream: true });
        buffer += decoded;

        // Process complete lines
        const lines = buffer.split('\n');
        buffer = lines.pop() || ''; // Keep incomplete line in buffer

        for (const line of lines) {
          if (!line.trim()) continue;

          // Handle SSE format: "data: {json}"
          if (line.startsWith('data: ')) {
            const data = line.slice(6);

            if (data === '[DONE]') {
              streamComplete = true;
              break;
            }

            try {
              const parsed = JSON.parse(data);

              if (parsed.type === 'content' && parsed.content) {
                // Append content chunk
                assistantMessage.content += parsed.content;
                this.scrollToBottom();
              } else if (parsed.type === 'sources' && parsed.sources) {
                // Add sources to message
                assistantMessage.sources = parsed.sources;
              } else if (parsed.type === 'error') {
                // Handle error
                console.error('SSE error:', parsed.content);
                toast(parsed.content || 'An error occurred', 'error');
                this.messages = this.messages.filter(m => m !== assistantMessage);
                return;
              } else if (parsed.type === 'done') {
                streamComplete = true;
                break;
              }
            } catch (e) {
              console.warn('Failed to parse SSE data:', data, e);
            }
          }
        }
      }

      // Mark streaming complete - force Alpine reactivity
      assistantMessage.streaming = false;
      this.currentStreamingMessage = null;

      // Force Alpine.js to detect the change by updating the array reference
      const messageIndex = this.messages.indexOf(assistantMessage);
      if (messageIndex !== -1) {
        // Create a new object to trigger reactivity
        this.messages[messageIndex] = Object.assign({}, assistantMessage);
      }

      // Trigger chat history update
      window.dispatchEvent(new CustomEvent('chatpr:chat:updated', {
        detail: { threadId: this.threadId }
      }));

      // Trigger again after a short delay to catch auto-generated title
      // (auto-naming happens on the backend after streaming completes)
      setTimeout(() => {
        window.dispatchEvent(new CustomEvent('chatpr:chat:updated', {
          detail: { threadId: this.threadId }
        }));
      }, 2500);

    } catch (error) {
      if (error.name !== 'AbortError') {
        console.error('Streaming error:', error);
        toast('Failed to get response', 'error');

        // Remove failed message
        this.messages = this.messages.filter(m => m !== assistantMessage);
      }
    } finally {
      this.streaming = false;
      this.abortController = null;
    }
  },

  stopGeneration() {
    if (this.abortController) {
      this.abortController.abort();
      this.streaming = false;

      if (this.currentStreamingMessage) {
        this.currentStreamingMessage.streaming = false;
        this.currentStreamingMessage = null;
      }

      toast('Generation stopped', 'info', 3000);
    }
  },

  async regenerateResponse() {
    if (this.messages.length < 2) return;

    // Remove last assistant message
    this.messages.pop();

    // Get last user message
    const lastUserMessage = [...this.messages].reverse().find(m => m.role === 'user');

    if (lastUserMessage) {
      await this.streamResponse(lastUserMessage.content);
    }
  },

  renderMarkdown(content) {
    return marked.parse(content);
  },

  copyMessage(content) {
    navigator.clipboard.writeText(content).then(() => {
      toast('Copied to clipboard', 'success', 2000);
    });
  },

  startNewChat() {
    this.threadId = null;
    this.messages = [];
    this.input = '';

    // For Pro Chat, reset to allow creating new chat with current selection
    if (this.mode === 'general') {
      this.currentChatProvider = null;
      this.currentChatModel = null;
      // Keep selectedProvider and selectedModel as is (user's current selection)
    }
  },

  async switchChat(threadId) {
    console.log('[DEBUG] switchChat called with threadId:', threadId, 'mode:', this.mode);
    this.threadId = threadId;
    this.messages = [];
    console.log('[DEBUG] About to call loadMessages()');
    await this.loadMessages();
    console.log('[DEBUG] loadMessages() completed, messages count:', this.messages.length);

    // Load chat metadata to update provider/model selection (Pro Chat only)
    if (this.mode === 'general') {
      console.log('[DEBUG] Loading chat metadata for general mode');
      await this.loadChatMetadata();
    }
  },

  autoResizeTextarea() {
    this.$nextTick(() => {
      const textarea = this.$refs.messageInput;
      if (textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 200) + 'px';
      }
    });
  },

  scrollToBottom() {
    this.$nextTick(() => {
      const container = this.$refs.messagesContainer;
      if (container) {
        container.scrollTop = container.scrollHeight;
      }
    });
  },

  handleKeydown(event) {
    // Send on Enter (without Shift)
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      this.sendMessage();
    }
  },

  // Provider/Model Management Methods

  async loadAvailableProviders() {
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
        this.availableProviders = Object.entries(data.data.providers).map(([id, info]) => ({
          id,
          name: info.name,
          models: info.models
        }));

        // Set default selection (use admin defaults if available, otherwise first provider)
        if (this.availableProviders.length > 0) {
          const defaultProvider = chatprData.default_provider || this.availableProviders[0].id;
          const providerExists = this.availableProviders.find(p => p.id === defaultProvider);

          this.selectedProvider = providerExists ? defaultProvider : this.availableProviders[0].id;

          const provider = this.availableProviders.find(p => p.id === this.selectedProvider);
          if (provider) {
            const defaultModel = chatprData.default_model || provider.models[0];
            this.selectedModel = provider.models.includes(defaultModel) ? defaultModel : provider.models[0];
          }
        }
      }
    } catch (error) {
      console.error('Failed to load providers:', error);
      toast('Failed to load AI providers', 'error');
    }
  },

  get currentProviderModels() {
    const provider = this.availableProviders.find(p => p.id === this.selectedProvider);
    return provider ? provider.models : [];
  },

  async handleProviderChange() {
    // Update model dropdown to first model of new provider
    const provider = this.availableProviders.find(p => p.id === this.selectedProvider);
    if (provider && provider.models.length > 0) {
      this.selectedModel = provider.models[0];
    }

    // If there's an active chat with messages, warn about creating new conversation
    if (this.threadId && this.messages.length > 0) {
      await this.confirmProviderSwitch();
    }
  },

  async handleModelChange() {
    // If there's an active chat, confirm model change
    if (this.threadId && this.messages.length > 0) {
      await this.confirmProviderSwitch();
    }
  },

  async confirmProviderSwitch() {
    const currentProvider = this.currentChatProvider || this.selectedProvider;
    const currentModel = this.currentChatModel || this.selectedModel;

    // Check if actually changing
    if (currentProvider === this.selectedProvider && currentModel === this.selectedModel) {
      return;
    }

    const providerName = this.availableProviders.find(p => p.id === this.selectedProvider)?.name;
    const confirmed = confirm(
      `Switching to ${providerName} (${this.selectedModel}) will create a new conversation. ` +
      `Your current chat history will be preserved in the sidebar.\n\nContinue?`
    );

    if (confirmed) {
      // Create new chat with new provider/model
      await this.createNewChatWithProvider();
    } else {
      // Revert to current chat's provider/model
      this.selectedProvider = currentProvider;
      this.selectedModel = currentModel;
    }
  },

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
        // Switch to new chat
        this.threadId = data.data.chat_id;
        this.messages = [];
        this.currentChatProvider = this.selectedProvider;
        this.currentChatModel = this.selectedModel;

        // Notify chat history to refresh
        window.dispatchEvent(new CustomEvent('chatpr:chat:updated', {
          detail: { threadId: this.threadId }
        }));

        toast('New conversation created', 'success', 2000);
      } else {
        toast(data.data?.message || 'Failed to create conversation', 'error');
      }
    } catch (error) {
      console.error('Failed to create new chat:', error);
      toast('Failed to create new conversation', 'error');
    }
  },

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
      console.error('Failed to load chat metadata:', error);
    }
  },

  // Image Upload Methods (General Chat only)

  async handleImageSelect(event) {
    if (this.mode !== 'general') return;

    const files = Array.from(event.target.files);
    await this.addImages(files);
    event.target.value = ''; // Reset input for re-selection
  },

  async handlePaste(event) {
    if (this.mode !== 'general') return;

    // Check for image data in clipboard
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

  async handleDrop(event) {
    if (this.mode !== 'general') return;

    this.isDragOver = false;

    const files = Array.from(event.dataTransfer.files).filter(f => f.type.startsWith('image/'));
    if (files.length > 0) {
      await this.addImages(files);
    }
  },

  async addImages(files) {
    // Check remaining slots
    const remaining = this.maxImagesPerMessage === -1 ? Infinity : this.maxImagesPerMessage - this.attachedImages.length;
    if (remaining <= 0) {
      toast(chatprData.i18n?.maxImagesReached || 'Maximum images reached', 'warning');
      return;
    }

    const toProcess = files.slice(0, remaining);

    for (const file of toProcess) {
      // Validate type
      const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
      if (!allowedTypes.includes(file.type)) {
        toast(chatprData.i18n?.invalidImageType || 'Invalid image type. Allowed: JPEG, PNG, GIF, WebP.', 'error');
        continue;
      }

      // Validate size (10MB default)
      const maxSize = chatprData.max_image_size || (10 * 1024 * 1024);
      if (file.size > maxSize) {
        const maxMB = Math.round(maxSize / (1024 * 1024));
        toast((chatprData.i18n?.imageTooLarge || 'Image exceeds {size} MB limit').replace('{size}', maxMB), 'error');
        continue;
      }

      // Convert to data URL
      try {
        const dataUrl = await this.fileToDataUrl(file);
        this.attachedImages.push({
          name: file.name,
          type: file.type,
          size: file.size,
          dataUrl: dataUrl
        });
      } catch (err) {
        console.error('Failed to process image:', err);
        toast(chatprData.i18n?.imageProcessError || 'Failed to process image', 'error');
      }
    }
  },

  fileToDataUrl(file) {
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = () => resolve(reader.result);
      reader.onerror = () => reject(new Error('Failed to read file'));
      reader.readAsDataURL(file);
    });
  },

  removeImage(index) {
    this.attachedImages.splice(index, 1);
  },

  openImageModal(imageUrl) {
    // Simple image modal - open in new tab for now
    // Could be enhanced with a proper modal later
    window.open(imageUrl, '_blank');
  }
  }));
});

// Export utilities
export { marked, hljs, toast };
