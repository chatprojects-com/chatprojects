/**
 * Chat History Component
 * Loads and manages chat history sidebar
 */

import { toast } from '../utils/toast.js';

// Wait for Alpine to be available, then register chat history component
document.addEventListener('alpine:init', () => {
  window.Alpine.data('chatHistory', (projectId = null, chatMode = null) => ({
    projectId: projectId,
    chatMode: chatMode, // 'general' for Pro Chat, null for project chats
    chats: [],
    activeThreadId: null,
    loading: false,
    editingChatId: null,
    editingTitle: '',

    init() {
      // Load chat list on init
      this.loadChatList();

      // Listen for chat updates to refresh the list
      window.addEventListener('chatpr:chat:updated', (e) => {
        this.loadChatList();
        // Update active thread if provided
        if (e.detail && e.detail.threadId) {
          this.activeThreadId = e.detail.threadId;
        }
      });

      // Listen for new chat events
      window.addEventListener('chatpr:chat:new', () => {
        this.activeThreadId = null;
      });

      // Listen for chat switch events to update active state
      window.addEventListener('chatpr:chat:switch', (e) => {
        if (e.detail && e.detail.threadId) {
          this.activeThreadId = e.detail.threadId;
        }
      });
    },

    async loadChatList() {
      this.loading = true;

      try {
        const params = {
          action: 'chatpr_get_chat_list',
          nonce: chatprData.nonce,
          project_id: this.projectId || ''
        };

        // Add chat_mode filter for Pro Chat
        if (this.chatMode) {
          params.chat_mode = this.chatMode;
        }

        const response = await fetch(chatprData.ajax_url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: new URLSearchParams(params)
        });

        const data = await response.json();

        if (data.success) {
          this.chats = data.data.chats || [];
        } else {
          console.error('Failed to load chat list:', data.data);
          const errorMsg = typeof data.data === 'string' ? data.data : (data.data?.message || 'Failed to load chat history');
          toast(errorMsg, 'error');
        }
      } catch (error) {
        console.error('Error loading chat list:', error);
        toast('Failed to load chat history', 'error');
      } finally {
        this.loading = false;
      }
    },

    switchChat(threadId) {
      this.activeThreadId = threadId;

      // Dispatch event for chat component to listen to
      window.dispatchEvent(new CustomEvent('chatpr:chat:switch', {
        detail: { threadId: threadId }
      }));
    },

    startEditingChat(chat, event) {
      event.stopPropagation(); // Prevent chat switching
      this.editingChatId = chat.id;
      this.editingTitle = chat.title || chat.first_message || 'Untitled Chat';
    },

    cancelEditing() {
      this.editingChatId = null;
      this.editingTitle = '';
    },

    async saveTitle(chatId, event) {
      event.stopPropagation(); // Prevent chat switching

      if (!this.editingTitle.trim()) {
        toast('Chat title cannot be empty', 'error');
        return;
      }

      console.log('Renaming chat:', chatId, 'to:', this.editingTitle.trim());

      try {
        const response = await fetch(chatprData.ajax_url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: new URLSearchParams({
            action: 'chatpr_rename_chat',
            nonce: chatprData.nonce,
            chat_id: chatId,
            title: this.editingTitle.trim()
          })
        });

        const data = await response.json();
        console.log('Rename response:', data);

        if (data.success) {
          toast('Chat renamed successfully', 'success', 2000);
          this.loadChatList(); // Reload to show updated title
        } else {
          const errorMsg = typeof data.data === 'string' ? data.data : (data.data?.message || 'Failed to rename chat');
          console.error('Rename failed:', errorMsg, data);
          toast(errorMsg, 'error');
        }
      } catch (error) {
        console.error('Error renaming chat:', error);
        toast('Failed to rename chat', 'error');
      } finally {
        this.cancelEditing();
      }
    },

    async deleteChat(chatId, event) {
      event.stopPropagation(); // Prevent chat switching

      if (!confirm('Are you sure you want to delete this chat? This action cannot be undone.')) {
        return;
      }

      console.log('Deleting chat:', chatId);

      try {
        const response = await fetch(chatprData.ajax_url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: new URLSearchParams({
            action: 'chatpr_delete_chat',
            nonce: chatprData.nonce,
            chat_id: chatId
          })
        });

        const data = await response.json();
        console.log('Delete response:', data);

        if (data.success) {
          toast('Chat deleted successfully', 'success', 2000);

          // If deleted chat was active, clear it
          if (this.activeThreadId === chatId) {
            this.activeThreadId = null;
            window.dispatchEvent(new CustomEvent('chatpr:chat:new'));
          }

          this.loadChatList(); // Reload chat list
        } else {
          const errorMsg = typeof data.data === 'string' ? data.data : (data.data?.message || 'Failed to delete chat');
          console.error('Delete failed:', errorMsg, data);
          toast(errorMsg, 'error');
        }
      } catch (error) {
        console.error('Error deleting chat:', error);
        toast('Failed to delete chat', 'error');
      }
    },

    formatDate(dateString) {
      if (!dateString) return '';

      const date = new Date(dateString);
      const now = new Date();
      const diffTime = Math.abs(now - date);
      const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));

      if (diffDays === 0) {
        return 'Today';
      } else if (diffDays === 1) {
        return 'Yesterday';
      } else if (diffDays < 7) {
        return `${diffDays} days ago`;
      } else {
        return date.toLocaleDateString();
      }
    },

    getChatTitle(chat) {
      return chat.title || chat.first_message || 'Untitled Chat';
    },

    truncateText(text, maxLength = 40) {
      if (!text) return '';
      return text.length > maxLength ? text.substring(0, maxLength) + '...' : text;
    },

    getDateGroup(dateString) {
      if (!dateString) return 'Older';

      const date = new Date(dateString);
      const now = new Date();
      const diffTime = Math.abs(now - date);
      const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));

      if (diffDays === 0) {
        return 'Today';
      } else if (diffDays === 1) {
        return 'Yesterday';
      } else if (diffDays < 7) {
        return 'Last 7 Days';
      } else if (diffDays < 30) {
        return 'Last 30 Days';
      } else {
        return 'Older';
      }
    },

    get groupedChats() {
      const groups = {};

      this.chats.forEach(chat => {
        const group = this.getDateGroup(chat.updated_at);
        if (!groups[group]) {
          groups[group] = [];
        }
        groups[group].push(chat);
      });

      // Return in specific order
      const order = ['Today', 'Yesterday', 'Last 7 Days', 'Last 30 Days', 'Older'];
      const result = [];

      order.forEach(groupName => {
        if (groups[groupName] && groups[groupName].length > 0) {
          result.push({
            name: groupName,
            chats: groups[groupName]
          });
        }
      });

      return result;
    }
  }));
});
