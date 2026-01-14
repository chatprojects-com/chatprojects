/**
 * Keyboard Shortcuts
 * Global keyboard shortcuts for ChatProjects
 */

class ShortcutManager {
  constructor() {
    this.shortcuts = new Map();
    this.isEnabled = true;
    this.init();
  }

  init() {
    this.registerDefaultShortcuts();
    this.setupListeners();
  }

  registerDefaultShortcuts() {
    // Command Palette
    this.register('mod+k', () => {
      this.openCommandPalette();
    }, 'Open command palette');

    // New chat
    this.register('mod+n', () => {
      window.dispatchEvent(new CustomEvent('chatpr:chat:new'));
    }, 'Start new chat');

    // Theme toggle
    this.register('mod+shift+l', () => {
      window.VPTheme?.toggle();
    }, 'Toggle light/dark mode');

    // Focus search
    this.register('mod+/', () => {
      const searchInput = document.querySelector('[data-search-input]');
      if (searchInput) {
        searchInput.focus();
      }
    }, 'Focus search');

    // Show shortcuts
    this.register('?', () => {
      this.showShortcutsModal();
    }, 'Show keyboard shortcuts');

    // Escape to close modals
    this.register('escape', () => {
      window.dispatchEvent(new CustomEvent('chatpr:modal:close'));
    }, 'Close modal/dialog');
  }

  register(combination, callback, description = '') {
    this.shortcuts.set(combination, {
      callback,
      description,
      keys: this.parseKeyCombination(combination)
    });
  }

  parseKeyCombination(combination) {
    return combination.toLowerCase().split('+').map(key => {
      // Normalize modifier keys
      if (key === 'mod') {
        return navigator.platform.includes('Mac') ? 'meta' : 'ctrl';
      }
      return key;
    });
  }

  setupListeners() {
    document.addEventListener('keydown', (e) => {
      if (!this.isEnabled) return;

      // Don't trigger shortcuts when typing in inputs
      const isInput = ['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName);
      const isContentEditable = e.target.isContentEditable;

      if (isInput || isContentEditable) {
        // Allow escape in inputs
        if (e.key === 'Escape') {
          e.target.blur();
          return;
        }
        // Don't process other shortcuts in inputs
        if (e.key !== '?') return;
      }

      // Check each registered shortcut
      this.shortcuts.forEach((shortcut, combination) => {
        if (this.matchesShortcut(e, shortcut.keys)) {
          e.preventDefault();
          shortcut.callback(e);
        }
      });
    });
  }

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

    // Check if pressed keys match shortcut keys
    if (pressedKeys.length !== keys.length) return false;

    return keys.every(key => pressedKeys.includes(key));
  }

  openCommandPalette() {
    window.dispatchEvent(new CustomEvent('chatpr:command-palette:open'));
  }

  showShortcutsModal() {
    const shortcuts = Array.from(this.shortcuts.entries()).map(([combo, data]) => ({
      combination: this.formatCombination(combo),
      description: data.description
    }));

    window.dispatchEvent(new CustomEvent('chatpr:shortcuts:show', {
      detail: { shortcuts }
    }));
  }

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
    return Array.from(this.shortcuts.entries()).map(([combo, data]) => ({
      combination: this.formatCombination(combo),
      description: data.description
    }));
  }
}

// Initialize and export
let shortcutInstance = null;

export function initKeyboardShortcuts() {
  if (!shortcutInstance) {
    shortcutInstance = new ShortcutManager();
  }

  // Make available globally
  window.VPShortcuts = shortcutInstance;

  return shortcutInstance;
}

export function registerShortcut(combination, callback, description) {
  if (!shortcutInstance) {
    initKeyboardShortcuts();
  }
  shortcutInstance.register(combination, callback, description);
}

export default { initKeyboardShortcuts, registerShortcut };
