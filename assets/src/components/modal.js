/**
 * Modal Component (Alpine.js)
 * Reusable modal dialog system
 */

// Wait for Alpine to be available, then register component
document.addEventListener('alpine:init', () => {
  window.Alpine.data('modal', (initialOpen = false) => ({
  isOpen: initialOpen,

  init() {
    // Listen for global modal events
    window.addEventListener('chatpr:modal:open', (e) => {
      if (e.detail?.id === this.$el.id) {
        this.open();
      }
    });

    window.addEventListener('chatpr:modal:close', () => {
      this.close();
    });

    // Close on escape key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && this.isOpen) {
        this.close();
      }
    });
  },

  open() {
    this.isOpen = true;
    document.body.style.overflow = 'hidden';

    // Focus trap
    this.$nextTick(() => {
      const focusable = this.$el.querySelectorAll(
        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
      );
      if (focusable.length) {
        focusable[0].focus();
      }
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
