/**
 * ChatProjects Admin JavaScript
 * WordPress admin area functionality
 */

import Alpine from 'alpinejs';
import './main.css';

// Admin-specific Alpine components
Alpine.data('settingsForm', () => ({
  saving: false,
  testingConnection: false,
  apiKey: '',
  provider: 'openai',

  async saveSettings() {
    this.saving = true;

    try {
      const formData = new FormData(this.$refs.form);
      formData.append('action', 'chatpr_save_settings');
      formData.append('nonce', chatprData.nonce);

      const response = await fetch(chatprData.ajax_url, {
        method: 'POST',
        body: formData
      });

      const data = await response.json();

      if (data.success) {
        window.VPToast?.success('Settings saved successfully');
      } else {
        window.VPToast?.error(data.data || 'Failed to save settings');
      }
    } catch (error) {
      console.error('Error saving settings:', error);
      window.VPToast?.error('Failed to save settings');
    } finally {
      this.saving = false;
    }
  },

  async testConnection() {
    if (!this.apiKey) {
      window.VPToast?.warning('Please enter an API key first');
      return;
    }

    this.testingConnection = true;

    try {
      const response = await fetch(chatprData.ajax_url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: 'chatpr_test_connection',
          nonce: chatprData.nonce,
          api_key: this.apiKey,
          provider: this.provider
        })
      });

      const data = await response.json();

      if (data.success) {
        window.VPToast?.success('Connection successful!');
      } else {
        window.VPToast?.error(data.data || 'Connection failed');
      }
    } catch (error) {
      console.error('Error testing connection:', error);
      window.VPToast?.error('Connection test failed');
    } finally {
      this.testingConnection = false;
    }
  }
}));

// Assistant Instructions Modal Component
Alpine.data('assistantInstructionsModal', () => ({
  isOpen: false,

  open() {
    this.isOpen = true;
  },

  close() {
    this.isOpen = false;
  }
}));

// Prompt Selector for Assistant Instructions
Alpine.data('assistantPromptSelector', () => ({
  showPrompts: false,
  prompts: [],
  loading: false,
  selectedCategory: 'Assistant',

  async loadPrompts() {
    this.loading = true;
    this.showPrompts = true;

    try {
      const formData = new FormData();
      formData.append('action', 'chatpr_get_prompts');
      formData.append('nonce', chatprAjax?.nonce || chatprData?.nonce);
      formData.append('category', this.selectedCategory);

      const response = await fetch(chatprAjax?.ajaxUrl || chatprData?.ajax_url, {
        method: 'POST',
        body: formData
      });

      const data = await response.json();

      if (data.success) {
        this.prompts = data.data;
      } else {
        window.VPToast?.error('Failed to load prompts');
      }
    } catch (error) {
      console.error('Error loading prompts:', error);
      window.VPToast?.error('Failed to load prompts');
    } finally {
      this.loading = false;
    }
  },

  selectPrompt(prompt) {
    const textarea = document.getElementById('chatprojects_assistant_instructions');
    if (textarea) {
      textarea.value = prompt.content;
    }
    this.showPrompts = false;
    window.VPToast?.success('Prompt loaded successfully');
  },

  close() {
    this.showPrompts = false;
  }
}));

// Make Alpine available globally
window.Alpine = Alpine;

// Prevent double initialization
if (!window._alpineStarted) {
  window._alpineStarted = true;
  Alpine.start();
}

console.log('ChatProjects Admin initialized');

// Initialize assistant instructions buttons after DOM is ready
document.addEventListener('DOMContentLoaded', function() {
  const loadPromptBtn = document.getElementById('load-assistant-prompt-btn');
  const infoBtn = document.getElementById('assistant-instructions-info-btn');

  if (loadPromptBtn) {
    loadPromptBtn.addEventListener('click', function() {
      const event = new CustomEvent('open-prompt-selector');
      window.dispatchEvent(event);
    });
  }

  if (infoBtn) {
    infoBtn.addEventListener('click', function() {
      const event = new CustomEvent('open-instructions-info');
      window.dispatchEvent(event);
    });
  }
});
