/**
 * ChatProjects Main JavaScript
 * Alpine.js + Modern utilities
 */

// Import Alpine.js
import Alpine from 'alpinejs';

// Import main styles
import './main.css';

// Import utilities
import { initToast } from './utils/toast.js';
import { initTheme } from './utils/theme.js';
import { initKeyboardShortcuts } from './utils/shortcuts.js';

// Make Alpine available globally BEFORE importing components
window.Alpine = Alpine;

// Import Alpine components (they will register using alpine:init event)
import './components/sidebar.js';
import './components/project-switcher.js';
import './components/modal.js';
import './components/dropdown.js';
import './components/chat-history.js';
import './components/file-manager.js';
import './components/transcriber.js';
import './components/prompt-library.js';
import './chat.js';

// Initialize utilities immediately (before DOM ready)
initToast();
initTheme();
initKeyboardShortcuts();

// Check if we're on a page that will load additional Alpine components via modules
// If so, delay Alpine.start() to let those modules register first
const hasComparisonPage = document.querySelector('[data-pro-comparison]');

if (!hasComparisonPage) {
  // Start Alpine immediately for regular pages
  Alpine.start();
  console.log('ChatProjects initialized with Alpine.js');
} else {
  // On comparison page, Alpine will be started by comparison.js after components register
  console.log('ChatProjects loaded, waiting for comparison components...');
}

// Export for global access
export { Alpine };
