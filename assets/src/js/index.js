/**
 * ChatProjects - Shared Utilities
 *
 * This file contains shared utility functions used across the application:
 * - Toast notifications
 * - Helper functions
 *
 * @package ChatProjects
 * @version 1.0.0
 */

import hljs from 'highlight.js';

// Re-export highlight.js for other modules
export { hljs as H };

// ============================================================================
// TOAST NOTIFICATIONS
// ============================================================================

/**
 * Toast notification manager
 */
class ToastManager {
    constructor() {
        this.container = null;
        this.toasts = [];
        this.init();
    }

    init() {
        // Check if container already exists
        if (document.getElementById('vp-toast-container')) {
            this.container = document.getElementById('vp-toast-container');
        } else {
            this.createContainer();
        }
    }

    createContainer() {
        this.container = document.createElement('div');
        this.container.id = 'vp-toast-container';
        this.container.className = 'fixed bottom-4 right-4 z-50 flex flex-col gap-2';
        document.body.appendChild(this.container);
    }

    /**
     * Show a toast notification
     * @param {string} message - Message to display
     * @param {string} type - Toast type: 'success', 'error', 'warning', 'info'
     * @param {number} duration - Duration in milliseconds (0 = no auto-dismiss)
     */
    show(message, type = 'info', duration = 5000) {
        if (!this.container) {
            this.init();
        }

        const toast = document.createElement('div');
        toast.className = this.getToastClasses(type);
        toast.innerHTML = `
            <div class="flex items-center gap-2">
                ${this.getIcon(type)}
                <span>${this.escapeHtml(message)}</span>
            </div>
            <button class="ml-4 text-current opacity-70 hover:opacity-100" onclick="this.parentElement.remove()">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        `;

        this.container.appendChild(toast);

        // Auto-dismiss
        if (duration > 0) {
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.classList.add('opacity-0', 'translate-x-full');
                    setTimeout(() => toast.remove(), 300);
                }
            }, duration);
        }

        return toast;
    }

    getToastClasses(type) {
        const base = 'flex items-center justify-between px-4 py-3 rounded-lg shadow-lg transition-all duration-300 transform';

        const typeClasses = {
            success: 'bg-green-500 text-white',
            error: 'bg-red-500 text-white',
            warning: 'bg-yellow-500 text-white',
            info: 'bg-blue-500 text-white'
        };

        return `${base} ${typeClasses[type] || typeClasses.info}`;
    }

    getIcon(type) {
        const icons = {
            success: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>',
            error: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>',
            warning: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>',
            info: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'
        };

        return icons[type] || icons.info;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    success(message, duration = 5000) {
        return this.show(message, 'success', duration);
    }

    error(message, duration = 5000) {
        return this.show(message, 'error', duration);
    }

    warning(message, duration = 5000) {
        return this.show(message, 'warning', duration);
    }

    info(message, duration = 5000) {
        return this.show(message, 'info', duration);
    }
}

// Global toast manager instance
let toastManager = null;

/**
 * Initialize toast container
 */
export function initToastContainer() {
    if (!toastManager) {
        toastManager = new ToastManager();
    }
    window.VPToast = toastManager;
    return toastManager;
}

/**
 * Show a toast notification (shorthand function)
 * @param {string} message - Message to display
 * @param {string} type - Toast type
 * @param {number} duration - Duration in ms
 */
export function showToast(message, type = 'info', duration = 5000) {
    if (!toastManager) {
        initToastContainer();
    }
    return toastManager.show(message, type, duration);
}

// Shorthand exports
export const t = showToast;
export const i = initToastContainer;

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Debounce function
 * @param {Function} func - Function to debounce
 * @param {number} wait - Wait time in ms
 * @returns {Function} Debounced function
 */
export function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Throttle function
 * @param {Function} func - Function to throttle
 * @param {number} limit - Time limit in ms
 * @returns {Function} Throttled function
 */
export function throttle(func, limit) {
    let inThrottle;
    return function executedFunction(...args) {
        if (!inThrottle) {
            func(...args);
            inThrottle = true;
            setTimeout(() => (inThrottle = false), limit);
        }
    };
}

/**
 * Format bytes to human readable string
 * @param {number} bytes - Number of bytes
 * @returns {string} Formatted string
 */
export function formatBytes(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

/**
 * Format date to relative time string
 * @param {string|Date} date - Date to format
 * @returns {string} Formatted string
 */
export function formatRelativeTime(date) {
    const now = new Date();
    const then = new Date(date);
    const diffMs = now - then;
    const diffSecs = Math.floor(diffMs / 1000);
    const diffMins = Math.floor(diffSecs / 60);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);

    if (diffDays > 7) {
        return then.toLocaleDateString();
    } else if (diffDays > 1) {
        return `${diffDays} days ago`;
    } else if (diffDays === 1) {
        return 'Yesterday';
    } else if (diffHours > 1) {
        return `${diffHours} hours ago`;
    } else if (diffHours === 1) {
        return '1 hour ago';
    } else if (diffMins > 1) {
        return `${diffMins} minutes ago`;
    } else if (diffMins === 1) {
        return '1 minute ago';
    } else {
        return 'Just now';
    }
}

/**
 * Generate unique ID
 * @returns {string} Unique ID
 */
export function generateId() {
    return Date.now().toString(36) + Math.random().toString(36).substr(2);
}

/**
 * Copy text to clipboard
 * @param {string} text - Text to copy
 * @returns {Promise<boolean>} Success status
 */
export async function copyToClipboard(text) {
    try {
        await navigator.clipboard.writeText(text);
        return true;
    } catch (error) {
        console.error('Failed to copy:', error);
        return false;
    }
}
