/**
 * ChatProjects - Main Application JavaScript
 *
 * This file contains the core application functionality including:
 * - Theme management (dark/light mode)
 * - Keyboard shortcuts
 * - Alpine.js component definitions
 *
 * @package ChatProjects
 * @version 1.0.0
 */

import Alpine from 'alpinejs';
import { showToast, initToastContainer } from './index.js';
import './chat.js';

// ============================================================================
// THEME MANAGER
// ============================================================================

/**
 * ThemeManager handles dark/light mode switching and persistence
 */
class ThemeManager {
    constructor() {
        this.theme = this.getStoredTheme() || this.getSystemTheme();
        this.init();
    }

    init() {
        this.applyTheme(this.theme);
        this.setupListeners();
    }

    /**
     * Get stored theme preference from localStorage
     * @returns {string|null} Theme preference or null
     */
    getStoredTheme() {
        return localStorage.getItem('cp_theme_preference');
    }

    /**
     * Get system theme preference
     * @returns {string} 'dark' or 'light'
     */
    getSystemTheme() {
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    /**
     * Apply theme to document
     * @param {string} theme - 'dark' or 'light'
     */
    applyTheme(theme) {
        const root = document.documentElement;

        if (theme === 'dark') {
            root.classList.add('dark');
        } else {
            root.classList.remove('dark');
        }

        this.theme = theme;
        localStorage.setItem('cp_theme_preference', theme);

        // Dispatch custom event for other components
        window.dispatchEvent(new CustomEvent('vp:theme:changed', {
            detail: { theme: theme }
        }));
    }

    /**
     * Toggle between dark and light themes
     * @returns {string} The new theme
     */
    toggle() {
        const newTheme = this.theme === 'dark' ? 'light' : 'dark';
        this.applyTheme(newTheme);
        return newTheme;
    }

    /**
     * Set specific theme
     * @param {string} theme - 'dark' or 'light'
     */
    setTheme(theme) {
        if (theme === 'dark' || theme === 'light') {
            this.applyTheme(theme);
        }
    }

    /**
     * Setup system theme change listener
     */
    setupListeners() {
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
            // Only auto-switch if user hasn't set a preference
            if (!this.getStoredTheme()) {
                this.applyTheme(e.matches ? 'dark' : 'light');
            }
        });
    }

    /**
     * Get current theme
     * @returns {string} Current theme
     */
    getCurrentTheme() {
        return this.theme;
    }
}

// ============================================================================
// KEYBOARD SHORTCUTS
// ============================================================================

/**
 * KeyboardShortcuts handles global keyboard shortcut management
 */
class KeyboardShortcuts {
    constructor() {
        this.shortcuts = new Map();
        this.isEnabled = true;
        this.init();
    }

    init() {
        this.registerDefaultShortcuts();
        this.setupListeners();
    }

    /**
     * Register default keyboard shortcuts
     */
    registerDefaultShortcuts() {
        this.register('mod+k', () => {
            this.openCommandPalette();
        }, 'Open command palette');

        this.register('mod+n', () => {
            window.dispatchEvent(new CustomEvent('vp:chat:new'));
        }, 'Start new chat');

        this.register('mod+shift+l', () => {
            window.VPTheme?.toggle();
        }, 'Toggle light/dark mode');

        this.register('mod+/', () => {
            const searchInput = document.querySelector('[data-search-input]');
            if (searchInput) searchInput.focus();
        }, 'Focus search');

        this.register('?', () => {
            this.showShortcutsModal();
        }, 'Show keyboard shortcuts');

        this.register('escape', () => {
            window.dispatchEvent(new CustomEvent('vp:modal:close'));
        }, 'Close modal/dialog');
    }

    /**
     * Register a keyboard shortcut
     * @param {string} combination - Key combination (e.g., 'mod+k')
     * @param {Function} callback - Function to execute
     * @param {string} description - Human-readable description
     */
    register(combination, callback, description = '') {
        this.shortcuts.set(combination, {
            callback: callback,
            description: description,
            keys: this.parseKeyCombination(combination)
        });
    }

    /**
     * Parse key combination string into array of keys
     * @param {string} combination - Key combination string
     * @returns {Array} Array of key names
     */
    parseKeyCombination(combination) {
        return combination.toLowerCase().split('+').map(key => {
            if (key === 'mod') {
                return navigator.platform.includes('Mac') ? 'meta' : 'ctrl';
            }
            return key;
        });
    }

    /**
     * Setup keyboard event listeners
     */
    setupListeners() {
        document.addEventListener('keydown', (event) => {
            if (!this.isEnabled) return;

            // Check if user is typing in an input
            const isTyping = ['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target.tagName);
            const isContentEditable = event.target.isContentEditable;

            if (isTyping || isContentEditable) {
                // Allow Escape to blur inputs
                if (event.key === 'Escape') {
                    event.target.blur();
                    return;
                }
                // Don't trigger shortcuts while typing (except ?)
                if (event.key !== '?') return;
            }

            // Check each shortcut
            this.shortcuts.forEach((shortcut, combination) => {
                if (this.matchesShortcut(event, shortcut.keys)) {
                    event.preventDefault();
                    shortcut.callback(event);
                }
            });
        });
    }

    /**
     * Check if keyboard event matches shortcut keys
     * @param {KeyboardEvent} event - Keyboard event
     * @param {Array} keys - Array of expected keys
     * @returns {boolean} True if matches
     */
    matchesShortcut(event, keys) {
        const pressedKeys = [];

        if (event.ctrlKey) pressedKeys.push('ctrl');
        if (event.metaKey) pressedKeys.push('meta');
        if (event.shiftKey) pressedKeys.push('shift');
        if (event.altKey) pressedKeys.push('alt');

        const key = event.key.toLowerCase();
        if (!['control', 'meta', 'shift', 'alt'].includes(key)) {
            pressedKeys.push(key);
        }

        return pressedKeys.length === keys.length &&
               keys.every(k => pressedKeys.includes(k));
    }

    /**
     * Open command palette
     */
    openCommandPalette() {
        window.dispatchEvent(new CustomEvent('vp:command-palette:open'));
    }

    /**
     * Show keyboard shortcuts modal
     */
    showShortcutsModal() {
        const shortcuts = Array.from(this.shortcuts.entries()).map(([combination, shortcut]) => ({
            combination: this.formatCombination(combination),
            description: shortcut.description
        }));

        window.dispatchEvent(new CustomEvent('vp:shortcuts:show', {
            detail: { shortcuts: shortcuts }
        }));
    }

    /**
     * Format key combination for display
     * @param {string} combination - Key combination
     * @returns {string} Formatted string
     */
    formatCombination(combination) {
        const isMac = navigator.platform.includes('Mac');
        return combination
            .replace('mod', isMac ? '⌘' : 'Ctrl')
            .replace('shift', isMac ? '⇧' : 'Shift')
            .replace('alt', isMac ? '⌥' : 'Alt')
            .replace('ctrl', isMac ? '⌃' : 'Ctrl')
            .split('+')
            .map(key => key.charAt(0).toUpperCase() + key.slice(1))
            .join(isMac ? ' ' : ' + ');
    }

    enable() {
        this.isEnabled = true;
    }

    disable() {
        this.isEnabled = false;
    }

    unregister(combination) {
        this.shortcuts.delete(combination);
    }

    getShortcuts() {
        return Array.from(this.shortcuts.entries()).map(([combination, shortcut]) => ({
            combination: this.formatCombination(combination),
            description: shortcut.description
        }));
    }
}

// ============================================================================
// ALPINE.JS COMPONENTS
// ============================================================================

/**
 * Sidebar Component
 * Handles responsive sidebar visibility
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('sidebar', () => ({
        isOpen: true,
        isMobile: window.innerWidth < 768,

        init() {
            // Close sidebar on mobile by default
            if (this.isMobile) {
                this.isOpen = false;
            }

            // Handle window resize
            window.addEventListener('resize', () => {
                const wasMobile = this.isMobile;
                this.isMobile = window.innerWidth < 768;

                if (!wasMobile && this.isMobile) {
                    this.isOpen = false;
                } else if (wasMobile && !this.isMobile) {
                    this.isOpen = true;
                }
            });
        },

        toggle() {
            this.isOpen = !this.isOpen;
        },

        close() {
            this.isOpen = false;
        },

        open() {
            this.isOpen = true;
        }
    }));
});

/**
 * Project Switcher Component
 * Handles project selection dropdown
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('projectSwitcher', (currentProjectId = null) => ({
        isOpen: false,
        searchQuery: '',
        currentProjectId: currentProjectId,
        projects: [],
        loading: false,

        init() {
            this.loadProjects();

            // Close dropdown when clicking outside
            document.addEventListener('click', (e) => {
                if (!this.$el.contains(e.target)) {
                    this.isOpen = false;
                }
            });
        },

        async loadProjects() {
            this.loading = true;
            try {
                const response = await fetch(chatprData.ajax_url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'chatpr_get_projects',
                        nonce: chatprData.nonce
                    })
                });
                const data = await response.json();
                if (data.success) {
                    this.projects = data.data;
                }
            } catch (error) {
                console.error('Failed to load projects:', error);
            } finally {
                this.loading = false;
            }
        },

        get filteredProjects() {
            if (!this.searchQuery) return this.projects;
            const query = this.searchQuery.toLowerCase();
            return this.projects.filter(p => p.title.toLowerCase().includes(query));
        },

        selectProject(projectId) {
            window.location.href = `?page=chatprojects&project_id=${projectId}`;
        },

        toggle() {
            this.isOpen = !this.isOpen;
            if (this.isOpen) {
                this.$nextTick(() => {
                    this.$refs.searchInput?.focus();
                });
            }
        },

        openShareModal() {
            this.isOpen = false;
            window.dispatchEvent(new CustomEvent('open-share-modal'));
        }
    }));
});

/**
 * Modal Component
 * Generic modal dialog handling
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('modal', (initialOpen = false) => ({
        isOpen: initialOpen,

        init() {
            // Listen for modal open events
            window.addEventListener('vp:modal:open', (e) => {
                if (e.detail?.id === this.$el.id) {
                    this.open();
                }
            });

            // Listen for modal close events
            window.addEventListener('vp:modal:close', () => {
                this.close();
            });

            // Close on Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.isOpen) {
                    this.close();
                }
            });
        },

        open() {
            this.isOpen = true;
            document.body.style.overflow = 'hidden';

            // Focus first focusable element
            this.$nextTick(() => {
                const focusable = this.$el.querySelectorAll(
                    'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
                );
                if (focusable.length) focusable[0].focus();
            });
        },

        close() {
            this.isOpen = false;
            document.body.style.overflow = '';
        },

        closeOnBackdrop(event) {
            if (event.target === event.currentTarget) {
                this.close();
            }
        }
    }));
});

/**
 * Dropdown Component
 * Generic dropdown menu handling
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('dropdown', (placement = 'bottom-left') => ({
        isOpen: false,
        placement: placement,

        init() {
            // Close when clicking outside
            document.addEventListener('click', (e) => {
                if (!this.$el.contains(e.target) && this.isOpen) {
                    this.isOpen = false;
                }
            });

            // Close on Escape
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.isOpen) {
                    this.isOpen = false;
                }
            });
        },

        toggle() {
            this.isOpen = !this.isOpen;
        },

        close() {
            this.isOpen = false;
        },

        open() {
            this.isOpen = true;
        }
    }));
});

/**
 * Chat History Component
 * Handles chat list sidebar with grouping by date
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('chatHistory', (projectId = null, chatMode = null) => ({
        projectId: projectId,
        chatMode: chatMode,
        chats: [],
        activeThreadId: null,
        loading: false,
        editingChatId: null,
        editingTitle: '',

        init() {
            this.loadChatList();

            // Listen for chat updates
            window.addEventListener('vp:chat:updated', (e) => {
                this.loadChatList();
                if (e.detail && e.detail.threadId) {
                    this.activeThreadId = e.detail.threadId;
                }
            });

            // Listen for new chat
            window.addEventListener('vp:chat:new', () => {
                this.activeThreadId = null;
            });

            // Listen for chat switch
            window.addEventListener('vp:chat:switch', (e) => {
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

                if (this.chatMode) {
                    params.chat_mode = this.chatMode;
                }

                const response = await fetch(chatprData.ajax_url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams(params)
                });

                const data = await response.json();
                if (data.success) {
                    this.chats = data.data.chats || [];
                } else {
                    console.error('Failed to load chat list:', data.data);
                    const errorMsg = typeof data.data === 'string'
                        ? data.data
                        : (data.data?.message || 'Failed to load chat history');
                    showToast(errorMsg, 'error');
                }
            } catch (error) {
                console.error('Error loading chat list:', error);
                showToast('Failed to load chat history', 'error');
            } finally {
                this.loading = false;
            }
        },

        switchChat(chatId) {
            this.activeThreadId = chatId;
            window.dispatchEvent(new CustomEvent('vp:chat:switch', {
                detail: { threadId: chatId }
            }));
        },

        startEditingChat(chat, event) {
            event.stopPropagation();
            this.editingChatId = chat.id;
            this.editingTitle = chat.title || chat.first_message || 'Untitled Chat';
        },

        cancelEditing() {
            this.editingChatId = null;
            this.editingTitle = '';
        },

        async saveTitle(chatId, event) {
            event.stopPropagation();

            if (!this.editingTitle.trim()) {
                showToast('Chat title cannot be empty', 'error');
                return;
            }

            try {
                const response = await fetch(chatprData.ajax_url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'chatpr_rename_chat',
                        nonce: chatprData.nonce,
                        chat_id: chatId,
                        title: this.editingTitle.trim()
                    })
                });

                const data = await response.json();
                if (data.success) {
                    showToast('Chat renamed successfully', 'success', 2000);
                    this.loadChatList();
                } else {
                    const errorMsg = typeof data.data === 'string'
                        ? data.data
                        : (data.data?.message || 'Failed to rename chat');
                    console.error('Rename failed:', errorMsg, data);
                    showToast(errorMsg, 'error');
                }
            } catch (error) {
                console.error('Error renaming chat:', error);
                showToast('Failed to rename chat', 'error');
            } finally {
                this.cancelEditing();
            }
        },

        async deleteChat(chatId, event) {
            event.stopPropagation();

            if (!confirm('Are you sure you want to delete this chat? This action cannot be undone.')) {
                return;
            }

            try {
                const response = await fetch(chatprData.ajax_url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'chatpr_delete_chat',
                        nonce: chatprData.nonce,
                        chat_id: chatId
                    })
                });

                const data = await response.json();
                if (data.success) {
                    showToast('Chat deleted successfully', 'success', 2000);

                    if (this.activeThreadId === chatId) {
                        this.activeThreadId = null;
                        window.dispatchEvent(new CustomEvent('vp:chat:new'));
                    }

                    this.loadChatList();
                } else {
                    const errorMsg = typeof data.data === 'string'
                        ? data.data
                        : (data.data?.message || 'Failed to delete chat');
                    console.error('Delete failed:', errorMsg, data);
                    showToast(errorMsg, 'error');
                }
            } catch (error) {
                console.error('Error deleting chat:', error);
                showToast('Failed to delete chat', 'error');
            }
        },

        formatDate(dateString) {
            if (!dateString) return '';

            const date = new Date(dateString);
            const now = new Date();
            const diffMs = Math.abs(now - date);
            const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

            if (diffDays === 0) return 'Today';
            if (diffDays === 1) return 'Yesterday';
            if (diffDays < 7) return `${diffDays} days ago`;

            return date.toLocaleDateString();
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
            const diffMs = Math.abs(now - date);
            const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

            if (diffDays === 0) return 'Today';
            if (diffDays === 1) return 'Yesterday';
            if (diffDays < 7) return 'Last 7 Days';
            if (diffDays < 30) return 'Last 30 Days';
            return 'Older';
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

            // Return in order
            const orderedGroups = [];
            ['Today', 'Yesterday', 'Last 7 Days', 'Last 30 Days', 'Older'].forEach(groupName => {
                if (groups[groupName] && groups[groupName].length > 0) {
                    orderedGroups.push({
                        name: groupName,
                        chats: groups[groupName]
                    });
                }
            });

            return orderedGroups;
        }
    }));
});

/**
 * File Manager Component
 * Handles file uploads and management for projects
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('fileManager', (projectId = null) => ({
        projectId: projectId,
        files: [],
        loading: false,
        uploading: false,
        uploadQueue: [],
        selectedFiles: [],
        selectAll: false,
        isDragging: false,

        init() {
            this.loadFiles();

            // Watch selectAll changes
            this.$watch('selectAll', (value) => {
                this.selectedFiles = value ? this.files.map(f => f.id || f.file_id) : [];
            });

            // Watch selectedFiles changes
            this.$watch('selectedFiles', () => {
                if (this.selectedFiles.length === 0) {
                    this.selectAll = false;
                } else if (this.selectedFiles.length === this.files.length && this.files.length > 0) {
                    this.selectAll = true;
                }
            });
        },

        async loadFiles() {
            this.loading = true;
            try {
                const response = await fetch(chatprData.ajax_url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'chatpr_list_files',
                        nonce: chatprData.nonce,
                        project_id: this.projectId || ''
                    })
                });

                const data = await response.json();
                if (data.success) {
                    this.files = data.data.files || [];
                } else {
                    console.error('Failed to load files:', data.data);
                }
            } catch (error) {
                console.error('Error loading files:', error);
            } finally {
                this.loading = false;
            }
        },

        handleFileSelect(event) {
            const files = event.target.files;
            if (files && files.length > 0) {
                this.handleFiles(Array.from(files));
            }
            event.target.value = '';
        },

        handleDrop(event) {
            event.preventDefault();
            this.isDragging = false;

            const files = event.dataTransfer?.files;
            if (files && files.length > 0) {
                this.handleFiles(Array.from(files));
            }
        },

        handleDragOver(event) {
            event.preventDefault();
            this.isDragging = true;
        },

        handleDragLeave(event) {
            event.preventDefault();
            this.isDragging = false;
        },

        handleFiles(files) {
            files.forEach(file => {
                if (this.validateFile(file)) {
                    this.uploadFile(file);
                }
            });
        },

        validateFile(file) {
            // Check file size (50MB limit)
            if (file.size > 50 * 1024 * 1024) {
                showToast(`File "${file.name}" exceeds 50MB limit`, 'error');
                return false;
            }

            // Check file type
            const allowedTypes = [
                'application/pdf',
                'text/plain',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'text/csv',
                'application/json',
                'text/markdown',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ];

            if (!allowedTypes.includes(file.type) && !file.name.endsWith('.md')) {
                showToast(`File type "${file.type}" is not allowed`, 'error');
                return false;
            }

            return true;
        },

        async uploadFile(file) {
            const uploadId = 'upload-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
            const uploadItem = {
                id: uploadId,
                name: file.name,
                progress: 0,
                status: 'uploading'
            };

            this.uploadQueue.push(uploadItem);

            const formData = new FormData();
            formData.append('action', 'chatpr_upload_file');
            formData.append('nonce', chatprData.nonce);
            formData.append('project_id', this.projectId || '');
            formData.append('file', file);

            try {
                const xhr = new XMLHttpRequest();

                // Track upload progress
                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) {
                        const percent = (e.loaded / e.total) * 100;
                        const item = this.uploadQueue.find(i => i.id === uploadId);
                        if (item) item.progress = percent;
                    }
                });

                const responsePromise = new Promise((resolve, reject) => {
                    xhr.onload = () => {
                        if (xhr.status === 200) {
                            try {
                                const response = JSON.parse(xhr.responseText);
                                resolve(response);
                            } catch (e) {
                                reject(new Error('Invalid JSON response'));
                            }
                        } else {
                            reject(new Error(`HTTP ${xhr.status}`));
                        }
                    };
                    xhr.onerror = () => reject(new Error('Network error'));
                });

                xhr.open('POST', chatprData.ajax_url);
                xhr.send(formData);

                const response = await responsePromise;

                if (response.success) {
                    const item = this.uploadQueue.find(i => i.id === uploadId);
                    if (item) {
                        item.status = 'success';
                        item.progress = 100;
                    }
                    showToast(`File "${file.name}" uploaded successfully`, 'success', 3000);
                    this.files.unshift(response.data.file);

                    setTimeout(() => {
                        this.uploadQueue = this.uploadQueue.filter(i => i.id !== uploadId);
                    }, 2000);
                } else {
                    const item = this.uploadQueue.find(i => i.id === uploadId);
                    if (item) item.status = 'error';

                    const errorMsg = response.data?.message || 'Failed to upload file';
                    showToast(errorMsg, 'error');

                    setTimeout(() => {
                        this.uploadQueue = this.uploadQueue.filter(i => i.id !== uploadId);
                    }, 3000);
                }
            } catch (error) {
                const item = this.uploadQueue.find(i => i.id === uploadId);
                if (item) item.status = 'error';

                console.error('Upload error:', error);
                showToast(`Failed to upload "${file.name}"`, 'error');

                setTimeout(() => {
                    this.uploadQueue = this.uploadQueue.filter(i => i.id !== uploadId);
                }, 3000);
            }
        },

        async deleteFile(fileId) {
            if (!confirm('Are you sure you want to delete this file? This action cannot be undone.')) {
                return;
            }

            try {
                const response = await fetch(chatprData.ajax_url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'chatpr_delete_file',
                        nonce: chatprData.nonce,
                        file_id: fileId,
                        project_id: this.projectId || ''
                    })
                });

                const data = await response.json();
                if (data.success) {
                    showToast('File deleted successfully', 'success', 2000);
                    this.files = this.files.filter(f => (f.id || f.file_id) !== fileId);
                    this.selectedFiles = this.selectedFiles.filter(id => id !== fileId);
                } else {
                    showToast(data.data?.message || 'Failed to delete file', 'error');
                }
            } catch (error) {
                console.error('Error deleting file:', error);
                showToast('Failed to delete file', 'error');
            }
        },

        async bulkDeleteFiles() {
            const count = this.selectedFiles.length;
            if (count === 0) return;

            if (!confirm(`Are you sure you want to delete ${count} file${count > 1 ? 's' : ''}? This action cannot be undone.`)) {
                return;
            }

            const filesToDelete = [...this.selectedFiles];
            let successCount = 0;
            let failCount = 0;

            for (const fileId of filesToDelete) {
                try {
                    const response = await fetch(chatprData.ajax_url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'chatpr_delete_file',
                            nonce: chatprData.nonce,
                            file_id: fileId,
                            project_id: this.projectId || ''
                        })
                    });

                    const data = await response.json();
                    if (data.success) {
                        successCount++;
                        this.files = this.files.filter(f => (f.id || f.file_id) !== fileId);
                    } else {
                        failCount++;
                    }
                } catch (error) {
                    console.error('Error deleting file:', error);
                    failCount++;
                }
            }

            this.selectedFiles = [];
            this.selectAll = false;

            if (successCount > 0) {
                showToast(`${successCount} file${successCount > 1 ? 's' : ''} deleted successfully`, 'success');
            }
            if (failCount > 0) {
                showToast(`Failed to delete ${failCount} file${failCount > 1 ? 's' : ''}`, 'error');
            }
        },

        formatFileSize(bytes) {
            if (!bytes || bytes === 0) return '0 Bytes';
            const k = Math.floor(Math.log(bytes) / Math.log(1024));
            return Math.round(bytes / Math.pow(1024, k) * 100) / 100 + ' ' + ['Bytes', 'KB', 'MB', 'GB'][k];
        },

        formatDate(dateString) {
            if (!dateString) return '';

            const date = new Date(dateString);
            const now = new Date();
            const diffMs = Math.abs(now - date);
            const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

            if (diffDays === 0) {
                return 'Today at ' + date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
            }
            if (diffDays === 1) return 'Yesterday';
            if (diffDays < 7) return `${diffDays} days ago`;

            return date.toLocaleDateString();
        },

        getFileId(file) {
            return file.id || file.file_id;
        },

        getFileSize(file) {
            return file.bytes || file.size || 0;
        },

        getFileDate(file) {
            return file.created_at || file.uploaded_at || '';
        },

        triggerFileInput() {
            this.$refs.fileInput.click();
        }
    }));
});

// ============================================================================
// INITIALIZATION
// ============================================================================

// Make Alpine globally available
window.Alpine = Alpine;

// Initialize toast container
initToastContainer();

// Initialize Theme Manager
let themeManager = null;
if (!themeManager) {
    themeManager = new ThemeManager();
}
window.VPTheme = themeManager;

// Initialize Keyboard Shortcuts
let keyboardShortcuts = null;
if (!keyboardShortcuts) {
    keyboardShortcuts = new KeyboardShortcuts();
}
window.VPShortcuts = keyboardShortcuts;

// Start Alpine.js (only once)
if (!window._alpineStarted) {
    window._alpineStarted = true;
    Alpine.start();
}
