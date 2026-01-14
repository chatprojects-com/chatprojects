/**
 * Toast Notification System
 * Modern, stackable toast notifications (bottom-left positioning)
 */

class Toast {
  constructor() {
    this.container = null;
    this.toasts = [];
    this.init();
  }

  init() {
    // Create container if it doesn't exist
    if (!document.getElementById('chatpr-toast-container')) {
      this.container = document.createElement('div');
      this.container.id = 'chatpr-toast-container';
      this.container.className = 'fixed bottom-4 left-4 z-100 flex flex-col gap-2 max-w-sm';
      document.body.appendChild(this.container);
    } else {
      this.container = document.getElementById('chatpr-toast-container');
    }
  }

  show(message, type = 'info', duration = 5000) {
    const toast = this.createToast(message, type);
    this.container.appendChild(toast);
    this.toasts.push(toast);

    // Animate in
    setTimeout(() => {
      toast.classList.add('animate-slide-up');
    }, 10);

    // Auto dismiss
    if (duration > 0) {
      setTimeout(() => {
        this.dismiss(toast);
      }, duration);
    }

    return toast;
  }

  createToast(message, type) {
    const toast = document.createElement('div');
    toast.className = this.getToastClasses(type);

    const icon = this.getIcon(type);

    toast.innerHTML = `
      <div class="flex-shrink-0">
        ${icon}
      </div>
      <div class="flex-1 text-sm text-neutral-900 dark:text-neutral-100">
        ${message}
      </div>
      <button type="button" class="flex-shrink-0 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 transition-colors" onclick="this.closest('[role=alert]').remove()">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    `;

    toast.setAttribute('role', 'alert');

    return toast;
  }

  getToastClasses(type) {
    const baseClasses = 'flex items-start gap-3 p-4 bg-white border rounded-xl shadow-large transition-all duration-300 dark:bg-dark-elevated';

    const typeClasses = {
      success: 'border-success-200 dark:border-success-900/30',
      error: 'border-error-200 dark:border-error-900/30',
      warning: 'border-warning-200 dark:border-warning-900/30',
      info: 'border-primary-200 dark:border-primary-900/30',
    };

    return `${baseClasses} ${typeClasses[type] || typeClasses.info}`;
  }

  getIcon(type) {
    const icons = {
      success: `
        <svg class="w-5 h-5 text-success-600 dark:text-success-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
      `,
      error: `
        <svg class="w-5 h-5 text-error-600 dark:text-error-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
      `,
      warning: `
        <svg class="w-5 h-5 text-warning-600 dark:text-warning-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
      `,
      info: `
        <svg class="w-5 h-5 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
      `,
    };

    return icons[type] || icons.info;
  }

  dismiss(toast) {
    toast.style.opacity = '0';
    toast.style.transform = 'translateX(-100%)';

    setTimeout(() => {
      if (toast.parentNode) {
        toast.remove();
      }
      this.toasts = this.toasts.filter(t => t !== toast);
    }, 300);
  }

  success(message, duration) {
    return this.show(message, 'success', duration);
  }

  error(message, duration) {
    return this.show(message, 'error', duration);
  }

  warning(message, duration) {
    return this.show(message, 'warning', duration);
  }

  info(message, duration) {
    return this.show(message, 'info', duration);
  }

  clear() {
    this.toasts.forEach(toast => this.dismiss(toast));
  }
}

// Initialize and export
let toastInstance = null;

export function initToast() {
  if (!toastInstance) {
    toastInstance = new Toast();
  }

  // Make available globally
  window.VPToast = toastInstance;

  return toastInstance;
}

export function toast(message, type = 'info', duration = 5000) {
  if (!toastInstance) {
    initToast();
  }
  return toastInstance.show(message, type, duration);
}

export default { initToast, toast };
