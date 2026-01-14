/**
 * Dropdown Component (Alpine.js)
 * Reusable dropdown menu
 */

// Wait for Alpine to be available, then register component
document.addEventListener('alpine:init', () => {
  window.Alpine.data('dropdown', (placement = 'bottom-left') => ({
  isOpen: false,
  placement: placement,

  init() {
    // Close on outside click
    document.addEventListener('click', (e) => {
      if (!this.$el.contains(e.target) && this.isOpen) {
        this.isOpen = false;
      }
    });

    // Close on escape
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
