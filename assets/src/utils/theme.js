/**
 * Theme Management (Light/Dark Mode)
 * Handles theme switching and persistence
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

  getStoredTheme() {
    return localStorage.getItem('chatpr-theme');
  }

  getSystemTheme() {
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }

  applyTheme(theme) {
    const html = document.documentElement;

    if (theme === 'dark') {
      html.classList.add('dark');
    } else {
      html.classList.remove('dark');
    }

    this.theme = theme;
    localStorage.setItem('chatpr-theme', theme);

    // Dispatch event for other components
    window.dispatchEvent(new CustomEvent('chatpr:theme:changed', {
      detail: { theme }
    }));
  }

  toggle() {
    const newTheme = this.theme === 'dark' ? 'light' : 'dark';
    this.applyTheme(newTheme);
    return newTheme;
  }

  setTheme(theme) {
    if (theme === 'dark' || theme === 'light') {
      this.applyTheme(theme);
    }
  }

  setupListeners() {
    // Listen for system theme changes
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
      if (!this.getStoredTheme()) {
        this.applyTheme(e.matches ? 'dark' : 'light');
      }
    });
  }

  getCurrentTheme() {
    return this.theme;
  }
}

// Initialize and export
let themeInstance = null;

export function initTheme() {
  if (!themeInstance) {
    themeInstance = new ThemeManager();
  }

  // Make available globally
  window.VPTheme = themeInstance;

  return themeInstance;
}

export function toggleTheme() {
  if (!themeInstance) {
    initTheme();
  }
  return themeInstance.toggle();
}

export function setTheme(theme) {
  if (!themeInstance) {
    initTheme();
  }
  themeInstance.setTheme(theme);
}

export function getCurrentTheme() {
  if (!themeInstance) {
    initTheme();
  }
  return themeInstance.getCurrentTheme();
}

export default { initTheme, toggleTheme, setTheme, getCurrentTheme };
