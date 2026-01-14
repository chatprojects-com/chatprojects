/**
 * Prompt Library Component
 * Manages saved prompts with search and categorization
 */

import { toast } from '../utils/toast.js';

// Wait for Alpine to be available, then register prompt library component
document.addEventListener('alpine:init', () => {
  window.Alpine.data('promptLibrary', (projectId = null) => ({
    projectId: projectId,
    prompts: [],
    filteredPrompts: [],
    loading: false,
    searchQuery: '',
    categoryFilter: '',
    showModal: false,
    editingPrompt: null,
    formData: {
      title: '',
      content: '',
      category: 'general'
    },
    saving: false,
    enhancing: false,

    init() {
      this.loadPrompts();

      // Watch for search/filter changes
      this.$watch('searchQuery', () => this.filterPrompts());
      this.$watch('categoryFilter', () => this.filterPrompts());
    },

    async loadPrompts() {
      this.loading = true;

      try {
        const response = await fetch(chatprData.ajax_url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: new URLSearchParams({
            action: 'chatpr_list_prompts',
            nonce: chatprData.nonce,
            project_id: this.projectId || ''
          })
        });

        const data = await response.json();

        if (data.success) {
          this.prompts = data.data.prompts || [];
          this.filterPrompts();
        } else {
          console.error('Failed to load prompts:', data.data);
        }
      } catch (error) {
        console.error('Error loading prompts:', error);
        toast('Failed to load prompts', 'error');
      } finally {
        this.loading = false;
      }
    },

    filterPrompts() {
      let filtered = [...this.prompts];

      // Apply search filter
      if (this.searchQuery.trim()) {
        const query = this.searchQuery.toLowerCase();
        filtered = filtered.filter(prompt =>
          prompt.title.toLowerCase().includes(query) ||
          prompt.content.toLowerCase().includes(query)
        );
      }

      // Apply category filter
      if (this.categoryFilter) {
        filtered = filtered.filter(prompt => prompt.category === this.categoryFilter);
      }

      this.filteredPrompts = filtered;
    },

    openCreateModal() {
      this.editingPrompt = null;
      this.formData = {
        title: '',
        content: '',
        category: 'general'
      };
      this.showModal = true;
    },

    openEditModal(prompt) {
      this.editingPrompt = prompt;
      this.formData = {
        title: prompt.title,
        content: prompt.content,
        category: prompt.category || 'general'
      };
      this.showModal = true;
    },

    closeModal() {
      this.showModal = false;
      this.editingPrompt = null;
      this.formData = {
        title: '',
        content: '',
        category: 'general'
      };
    },

    async savePrompt() {
      if (!this.formData.title.trim()) {
        toast('Please enter a title', 'error');
        return;
      }

      if (!this.formData.content.trim()) {
        toast('Please enter prompt content', 'error');
        return;
      }

      this.saving = true;

      try {
        const response = await fetch(chatprData.ajax_url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: new URLSearchParams({
            action: 'chatpr_save_prompt',
            nonce: chatprData.nonce,
            project_id: this.projectId || '',
            prompt_id: this.editingPrompt?.id || '',
            title: this.formData.title,
            content: this.formData.content,
            category: this.formData.category
          })
        });

        const data = await response.json();

        if (data.success) {
          toast(this.editingPrompt ? 'Prompt updated successfully' : 'Prompt created successfully', 'success', 2000);
          this.closeModal();
          this.loadPrompts(); // Reload list
        } else {
          toast(data.data?.message || 'Failed to save prompt', 'error');
        }
      } catch (error) {
        console.error('Save error:', error);
        toast('Failed to save prompt', 'error');
      } finally {
        this.saving = false;
      }
    },

    async deletePrompt(promptId) {
      if (!confirm('Are you sure you want to delete this prompt? This action cannot be undone.')) {
        return;
      }

      try {
        const response = await fetch(chatprData.ajax_url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: new URLSearchParams({
            action: 'chatpr_delete_prompt',
            nonce: chatprData.nonce,
            prompt_id: promptId,
            project_id: this.projectId || ''
          })
        });

        const data = await response.json();

        if (data.success) {
          toast('Prompt deleted successfully', 'success', 2000);
          this.prompts = this.prompts.filter(p => p.id !== promptId);
          this.filterPrompts();
        } else {
          toast(data.data?.message || 'Failed to delete prompt', 'error');
        }
      } catch (error) {
        console.error('Delete error:', error);
        toast('Failed to delete prompt', 'error');
      }
    },

    async enhancePrompt() {
      if (!this.formData.content.trim()) {
        toast('Please enter prompt content first', 'error');
        return;
      }

      this.enhancing = true;

      try {
        const response = await fetch(chatprData.ajax_url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: new URLSearchParams({
            action: 'chatpr_enhance_prompt',
            nonce: chatprData.nonce,
            prompt: this.formData.content
          })
        });

        const data = await response.json();

        if (data.success) {
          this.formData.content = data.data.enhanced || data.data.text;
          toast('Prompt enhanced successfully', 'success', 2000);
        } else {
          toast(data.data?.message || 'Failed to enhance prompt', 'error');
        }
      } catch (error) {
        console.error('Enhance error:', error);
        toast('Failed to enhance prompt', 'error');
      } finally {
        this.enhancing = false;
      }
    },

    usePrompt(prompt) {
      // Copy prompt content to clipboard
      navigator.clipboard.writeText(prompt.content).then(() => {
        toast('Prompt copied to clipboard', 'success', 2000);
      }).catch(() => {
        toast('Failed to copy prompt', 'error');
      });
    },

    getCategoryLabel(category) {
      const labels = {
        'general': 'General',
        'code': 'Code Generation',
        'content': 'Content Writing',
        'analysis': 'Analysis'
      };
      return labels[category] || category;
    },

    getCategoryColor(category) {
      const colors = {
        'general': 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
        'code': 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
        'content': 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
        'analysis': 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300'
      };
      return colors[category] || colors['general'];
    },

    extractVariables(content) {
      const regex = /\{\{(\w+)\}\}/g;
      const variables = [];
      let match;

      while ((match = regex.exec(content)) !== null) {
        if (!variables.includes(match[1])) {
          variables.push(match[1]);
        }
      }

      return variables;
    },

    truncateText(text, maxLength = 150) {
      if (!text) return '';
      return text.length > maxLength ? text.substring(0, maxLength) + '...' : text;
    }
  }));
});
