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

// Check if Alpine is already loaded (e.g., by Elementor or other plugins)
if (window.Alpine && window.Alpine.version) {
    console.log('ChatProjects: Using existing Alpine.js instance (version ' + window.Alpine.version + ')');
} else {
    // Make Alpine available globally BEFORE importing components
    window.Alpine = Alpine;
    console.log('ChatProjects: Loaded bundled Alpine.js');
}

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

// Prevent Alpine from auto-starting if we're managing it
if (!window._alpineStarted && window.Alpine === Alpine) {
    window._alpineStarted = false;

    // Start Alpine after all event listeners are registered
    // Use queueMicrotask to ensure all synchronous imports have registered their listeners
    queueMicrotask(() => {
        if (!window._alpineStarted) {
            window._alpineStarted = true;
            Alpine.start();
            console.log('ChatProjects initialized with Alpine.js');
        }
    });
} else {
    // Alpine already started by another plugin, just log
    console.log('ChatProjects components registered (Alpine already running)');
}


// Export for global access
export { Alpine };
