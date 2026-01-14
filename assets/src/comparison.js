/**
 * ChatProjects Comparison JavaScript
 * Side-by-side provider/model comparison interface
 */

// Import utilities
import { marked } from 'marked';
import hljs from 'highlight.js';
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

// Wait for Alpine to be available
document.addEventListener('alpine:init', () => {

  /**
   * Main Comparison Component
   */
  window.Alpine.data('comparison', () => ({
    // Comparison state
    comparisonId: null,
    chat1Id: null,
    chat2Id: null,

    // Provider/Model selection
    provider1: '',
    model1: '',
    provider2: '',
    model2: '',

    // Available options
    availableProviders: {},
    models1: [],
    models2: [],

    // Messages
    messages1: [],
    messages2: [],

    // UI state
    input: '',
    loading: false,
    loading1: false,
    loading2: false,

    async init() {
      console.log('Comparison component initialized');

      // Load available providers
      await this.loadAvailableProviders();

      // Listen for new comparison event
      window.addEventListener('chatpr:comparison:new', () => {
        this.resetComparison();
      });

      // Listen for comparison load event
      window.addEventListener('chatpr:comparison:load', (e) => {
        this.loadComparisonData(e.detail.comparisonId);
      });
    },

    /**
     * Load available providers and models
     */
    async loadAvailableProviders() {
      try {
        const response = await fetch(chatprComparisonData.ajax_url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: new URLSearchParams({
            action: 'chatpr_get_available_providers',
            nonce: chatprComparisonData.nonce,
          })
        });

        const data = await response.json();

        if (data.success) {
          this.availableProviders = data.data.providers;
          console.log('Available providers:', this.availableProviders);
        } else {
          toast(data.data?.message || 'Failed to load providers', 'error');
        }
      } catch (error) {
        console.error('Error loading providers:', error);
        toast('Failed to load providers', 'error');
      }
    },

    /**
     * Update available models for provider 1
     */
    updateModels1() {
      if (this.provider1 && this.availableProviders[this.provider1]) {
        this.models1 = this.availableProviders[this.provider1].models || [];
        this.model1 = ''; // Reset model selection
      } else {
        this.models1 = [];
        this.model1 = '';
      }
    },

    /**
     * Update available models for provider 2
     */
    updateModels2() {
      if (this.provider2 && this.availableProviders[this.provider2]) {
        this.models2 = this.availableProviders[this.provider2].models || [];
        this.model2 = ''; // Reset model selection
      } else {
        this.models2 = [];
        this.model2 = '';
      }
    },

    /**
     * Create a new comparison
     */
    async createComparison() {
      // Validate selections
      if (!this.provider1 || !this.model1 || !this.provider2 || !this.model2) {
        toast(chatprComparisonData.strings.select_providers || 'Please select providers and models for both sides', 'error');
        return;
      }

      this.loading = true;

      try {
        const response = await fetch(chatprComparisonData.ajax_url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: new URLSearchParams({
            action: 'chatpr_create_comparison',
            nonce: chatprComparisonData.nonce,
            provider1: this.provider1,
            model1: this.model1,
            provider2: this.provider2,
            model2: this.model2,
          })
        });

        const data = await response.json();

        if (data.success) {
          this.comparisonId = data.data.comparison_id;
          this.chat1Id = data.data.chat1_id;
          this.chat2Id = data.data.chat2_id;

          toast(chatprComparisonData.strings.comparison_created || 'Comparison created', 'success');

          // Notify history to reload
          window.dispatchEvent(new CustomEvent('chatpr:comparison:created', {
            detail: { comparisonId: this.comparisonId }
          }));
        } else {
          toast(data.data?.message || 'Failed to create comparison', 'error');
        }
      } catch (error) {
        console.error('Error creating comparison:', error);
        toast('Failed to create comparison', 'error');
      } finally {
        this.loading = false;
      }
    },

    /**
     * Load existing comparison data
     */
    async loadComparisonData(comparisonId) {
      this.loading = true;

      try {
        const response = await fetch(chatprComparisonData.ajax_url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: new URLSearchParams({
            action: 'chatpr_get_comparison_data',
            nonce: chatprComparisonData.nonce,
            comparison_id: comparisonId,
          })
        });

        const data = await response.json();

        if (data.success) {
          const compData = data.data;

          this.comparisonId = compData.comparison.id;
          this.chat1Id = compData.chat1.id;
          this.chat2Id = compData.chat2.id;
          this.provider1 = compData.chat1.provider;
          this.model1 = compData.chat1.model;
          this.provider2 = compData.chat2.provider;
          this.model2 = compData.chat2.model;
          this.messages1 = compData.chat1.messages || [];
          this.messages2 = compData.chat2.messages || [];

          // Scroll to bottom of both panels
          this.$nextTick(() => {
            this.scrollToBottom();
          });
        } else {
          toast(data.data?.message || 'Failed to load comparison', 'error');
        }
      } catch (error) {
        console.error('Error loading comparison:', error);
        toast('Failed to load comparison', 'error');
      } finally {
        this.loading = false;
      }
    },

    /**
     * Send message to both providers
     */
    async sendMessage() {
      if (!this.input.trim() || !this.comparisonId) {
        return;
      }

      const userMessage = this.input.trim();
      this.input = '';

      // Add user message to both panels immediately
      this.messages1.push({
        role: 'user',
        content: userMessage,
      });
      this.messages2.push({
        role: 'user',
        content: userMessage,
      });

      // Add placeholder assistant messages
      const assistantIndex1 = this.messages1.length;
      const assistantIndex2 = this.messages2.length;

      this.messages1.push({
        role: 'assistant',
        content: '',
      });
      this.messages2.push({
        role: 'assistant',
        content: '',
      });

      this.loading1 = true;
      this.loading2 = true;

      // Scroll to bottom
      this.$nextTick(() => {
        this.scrollToBottom();
      });

      try {
        const response = await fetch(chatprComparisonData.ajax_url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: new URLSearchParams({
            action: 'chatpr_send_comparison_message',
            nonce: chatprComparisonData.nonce,
            comparison_id: this.comparisonId,
            message: userMessage,
          })
        });

        const data = await response.json();

        if (data.success) {
          const result = data.data;

          // Update chat 1 response
          if (result.chat1.success) {
            this.messages1[assistantIndex1].content = result.chat1.response.content || result.chat1.response;
          } else {
            this.messages1[assistantIndex1].content = `Error: ${result.chat1.error}`;
            toast(`Provider 1 error: ${result.chat1.error}`, 'error');
          }

          // Update chat 2 response
          if (result.chat2.success) {
            this.messages2[assistantIndex2].content = result.chat2.response.content || result.chat2.response;
          } else {
            this.messages2[assistantIndex2].content = `Error: ${result.chat2.error}`;
            toast(`Provider 2 error: ${result.chat2.error}`, 'error');
          }

          // Scroll to bottom
          this.$nextTick(() => {
            this.scrollToBottom();
          });
        } else {
          // Remove placeholder messages on error
          this.messages1.splice(assistantIndex1, 1);
          this.messages2.splice(assistantIndex2, 1);
          toast(data.data?.message || 'Failed to send message', 'error');
        }
      } catch (error) {
        console.error('Error sending message:', error);
        // Remove placeholder messages on error
        this.messages1.splice(assistantIndex1, 1);
        this.messages2.splice(assistantIndex2, 1);
        toast('Failed to send message', 'error');
      } finally {
        this.loading1 = false;
        this.loading2 = false;
      }
    },

    /**
     * Reset comparison (start new)
     */
    resetComparison() {
      this.comparisonId = null;
      this.chat1Id = null;
      this.chat2Id = null;
      this.provider1 = '';
      this.model1 = '';
      this.provider2 = '';
      this.model2 = '';
      this.models1 = [];
      this.models2 = [];
      this.messages1 = [];
      this.messages2 = [];
      this.input = '';
    },

    /**
     * Format markdown content
     */
    formatMarkdown(content) {
      if (!content) return '';
      return marked.parse(content);
    },

    /**
     * Format provider and model name
     */
    formatProviderModel(provider, model) {
      const providerNames = {
        'openai': 'OpenAI',
        'anthropic': 'Anthropic',
        'gemini': 'Google Gemini',
        'chutes': 'Chutes.ai',
      };

      const providerName = providerNames[provider] || provider;
      return `${providerName} - ${model}`;
    },

    /**
     * Scroll both message panels to bottom
     */
    scrollToBottom() {
      // Find all message containers and scroll them
      const containers = document.querySelectorAll('[data-pro-comparison] .overflow-y-auto');
      containers.forEach(container => {
        container.scrollTop = container.scrollHeight;
      });
    },
  }));

  /**
   * Comparison History Component (Sidebar)
   */
  window.Alpine.data('comparisonHistory', () => ({
    comparisons: [],
    loading: false,
    activeComparisonId: null,

    async init() {
      console.log('Comparison history initialized');
      await this.loadComparisonList();

      // Listen for new comparison event
      window.addEventListener('chatpr:comparison:created', () => {
        this.loadComparisonList();
      });
    },

    /**
     * Load list of comparisons
     */
    async loadComparisonList() {
      this.loading = true;

      try {
        const response = await fetch(chatprComparisonData.ajax_url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: new URLSearchParams({
            action: 'chatpr_get_comparison_list',
            nonce: chatprComparisonData.nonce,
          })
        });

        const data = await response.json();

        if (data.success) {
          this.comparisons = data.data.comparisons || [];
          console.log('Loaded comparisons:', this.comparisons);
        } else {
          console.error('Failed to load comparisons:', data.data?.message);
        }
      } catch (error) {
        console.error('Error loading comparisons:', error);
      } finally {
        this.loading = false;
      }
    },

    /**
     * Load a specific comparison
     */
    loadComparison(comparisonId) {
      this.activeComparisonId = comparisonId;

      // Dispatch event to main comparison component
      window.dispatchEvent(new CustomEvent('chatpr:comparison:load', {
        detail: { comparisonId: comparisonId }
      }));
    },

    /**
     * Delete a comparison
     */
    async deleteComparison(comparisonId) {
      if (!confirm(chatprComparisonData.strings.delete_confirm || 'Are you sure you want to delete this comparison?')) {
        return;
      }

      try {
        const response = await fetch(chatprComparisonData.ajax_url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: new URLSearchParams({
            action: 'chatpr_delete_comparison',
            nonce: chatprComparisonData.nonce,
            comparison_id: comparisonId,
          })
        });

        const data = await response.json();

        if (data.success) {
          toast('Comparison deleted', 'success');

          // If this was the active comparison, reset
          if (this.activeComparisonId === comparisonId) {
            this.activeComparisonId = null;
            window.dispatchEvent(new CustomEvent('chatpr:comparison:new'));
          }

          // Reload list
          await this.loadComparisonList();
        } else {
          toast(data.data?.message || 'Failed to delete comparison', 'error');
        }
      } catch (error) {
        console.error('Error deleting comparison:', error);
        toast('Failed to delete comparison', 'error');
      }
    },
  }));
});

// Manually start Alpine after components are registered
// This ensures Alpine processes the DOM after comparison components are ready
if (window.Alpine && !window.Alpine._isStarted) {
  console.log('Starting Alpine from comparison.js');
  window.Alpine.start();
}
