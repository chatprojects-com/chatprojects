/**
 * ChatProjects - Admin Settings JavaScript
 *
 * This file contains the admin settings functionality:
 * - Settings form handling
 * - API connection testing
 * - Prompt selector
 *
 * @package ChatProjects
 * @version 1.0.0
 */

import Alpine from 'alpinejs';

// ============================================================================
// ALPINE.JS ADMIN COMPONENTS
// ============================================================================

/**
 * Settings Form Component
 * Handles saving settings and testing API connections
 */
Alpine.data('settingsForm', () => ({
    saving: false,
    testingConnection: false,
    apiKey: '',
    provider: 'openai',

    /**
     * Save settings to server
     */
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

    /**
     * Test API connection
     */
    async testConnection() {
        if (!this.apiKey) {
            window.VPToast?.warning('Please enter an API key first');
            return;
        }

        this.testingConnection = true;

        try {
            const response = await fetch(chatprData.ajax_url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
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

/**
 * Assistant Instructions Modal Component
 */
Alpine.data('assistantInstructionsModal', () => ({
    isOpen: false,

    open() {
        this.isOpen = true;
    },

    close() {
        this.isOpen = false;
    }
}));

/**
 * Assistant Prompt Selector Component
 * Allows selecting predefined prompts for assistant instructions
 */
Alpine.data('assistantPromptSelector', () => ({
    showPrompts: false,
    prompts: [],
    loading: false,
    selectedCategory: 'Assistant',

    /**
     * Load prompts from server
     */
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

    /**
     * Select a prompt and populate the instructions field
     * @param {Object} prompt - Selected prompt object
     */
    selectPrompt(prompt) {
        const instructionsField = document.getElementById('chatprojects_assistant_instructions');
        if (instructionsField) {
            instructionsField.value = prompt.content;
        }
        this.showPrompts = false;
        window.VPToast?.success('Prompt loaded successfully');
    },

    close() {
        this.showPrompts = false;
    }
}));

// ============================================================================
// INITIALIZATION
// ============================================================================

// Make Alpine globally available
window.Alpine = Alpine;

// Start Alpine.js (only once)
if (!window._alpineStarted) {
    window._alpineStarted = true;
    Alpine.start();
}

// ============================================================================
// DOM READY HANDLERS
// ============================================================================

document.addEventListener('DOMContentLoaded', function() {
    // Load prompt button handler
    const loadPromptBtn = document.getElementById('load-assistant-prompt-btn');
    if (loadPromptBtn) {
        loadPromptBtn.addEventListener('click', function() {
            window.dispatchEvent(new CustomEvent('open-prompt-selector'));
        });
    }

    // Instructions info button handler
    const infoBtn = document.getElementById('assistant-instructions-info-btn');
    if (infoBtn) {
        infoBtn.addEventListener('click', function() {
            window.dispatchEvent(new CustomEvent('open-instructions-info'));
        });
    }
});
