/**
 * Sidebar Component (Alpine.js)
 * Handles sidebar state and interactions
 */

// Wait for Alpine to be available, then register component
document.addEventListener('alpine:init', () => {
  window.Alpine.data('sidebar', () => ({
  isOpen: true,
  isMobile: window.innerWidth < 768,

  init() {
    // Close sidebar on mobile by default
    if (this.isMobile) {
      this.isOpen = false;
    }

    // Listen for window resize
    window.addEventListener('resize', () => {
      const wasMobile = this.isMobile;
      this.isMobile = window.innerWidth < 768;

      // Auto-close on mobile, auto-open on desktop
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
