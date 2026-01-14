/**
 * Markdown Rendering Utilities
 * Integrates Marked.js with Highlight.js for code syntax highlighting
 */

import { marked } from 'marked';

// Lazy load highlight.js and languages as needed
let hljsCore = null;
const loadedLanguages = new Set();

/**
 * Load highlight.js core
 */
async function loadHighlightJS() {
  if (!hljsCore) {
    hljsCore = await import('highlight.js/lib/core');
  }
  return hljsCore.default;
}

/**
 * Load a specific language for highlight.js
 */
async function loadLanguage(lang) {
  if (loadedLanguages.has(lang)) {
    return;
  }

  const hljs = await loadHighlightJS();

  try {
    // Map common aliases to actual language modules
    const langMap = {
      'js': 'javascript',
      'ts': 'typescript',
      'py': 'python',
      'sh': 'bash',
      'yml': 'yaml',
      'md': 'markdown',
    };

    const actualLang = langMap[lang] || lang;

    // Dynamically import the language
    const langModule = await import(`highlight.js/lib/languages/${actualLang}`);
    hljs.registerLanguage(actualLang, langModule.default);

    // Also register the alias
    if (langMap[lang]) {
      hljs.registerLanguage(lang, langModule.default);
    }

    loadedLanguages.add(lang);
    loadedLanguages.add(actualLang);
  } catch (error) {
    console.warn(`Failed to load language: ${lang}`, error);
  }
}

/**
 * Highlight code with syntax highlighting
 */
async function highlightCode(code, lang) {
  if (!lang) {
    return code; // No language specified, return plain text
  }

  try {
    await loadLanguage(lang);
    const hljs = await loadHighlightJS();

    if (hljs.getLanguage(lang)) {
      return hljs.highlight(code, { language: lang }).value;
    }
  } catch (error) {
    console.warn('Highlighting failed:', error);
  }

  return code; // Fallback to plain text
}

/**
 * Configure marked with custom renderer
 */
function configureMarked() {
  const renderer = new marked.Renderer();

  // Custom code block renderer
  const originalCode = renderer.code.bind(renderer);
  renderer.code = function(code, language) {
    const validLang = language || 'plaintext';
    const langLabel = validLang.charAt(0).toUpperCase() + validLang.slice(1);

    // Create a unique ID for this code block
    const blockId = 'code-' + Math.random().toString(36).substr(2, 9);

    return `
      <div class="chatpr-code-block" data-language="${validLang}">
        <div class="chatpr-code-header">
          <span class="chatpr-code-language">${langLabel}</span>
          <button
            class="chatpr-code-copy"
            data-code-id="${blockId}"
            onclick="window.ChatPRCopyCode('${blockId}')"
            title="Copy code"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
            </svg>
            <span>Copy</span>
          </button>
        </div>
        <pre class="chatpr-code-content"><code id="${blockId}" class="language-${validLang}" data-highlighted="false">${code}</code></pre>
      </div>
    `;
  };

  // Custom link renderer (open external links in new tab)
  const originalLink = renderer.link.bind(renderer);
  renderer.link = function(href, title, text) {
    const html = originalLink(href, title, text);

    // Add target="_blank" for external links
    if (href && (href.startsWith('http://') || href.startsWith('https://'))) {
      return html.replace('<a', '<a target="_blank" rel="noopener noreferrer"');
    }

    return html;
  };

  // Custom table renderer (add wrapper for responsiveness)
  const originalTable = renderer.table.bind(renderer);
  renderer.table = function(header, body) {
    const table = originalTable(header, body);
    return `<div class="chatpr-table-wrapper">${table}</div>`;
  };

  marked.setOptions({
    renderer: renderer,
    breaks: true, // GFM line breaks
    gfm: true, // GitHub Flavored Markdown
    pedantic: false,
    sanitize: false, // We'll handle XSS with DOMPurify if needed
  });
}

// Configure marked on module load
configureMarked();

/**
 * Render markdown to HTML
 */
export async function renderMarkdown(markdown) {
  if (!markdown) return '';

  // Parse markdown to HTML
  const html = marked.parse(markdown);

  return html;
}

/**
 * Highlight all code blocks in a container
 */
export async function highlightCodeBlocks(container) {
  const codeBlocks = container.querySelectorAll('code[data-highlighted="false"]');

  for (const block of codeBlocks) {
    const lang = block.className.replace('language-', '');
    const code = block.textContent;

    if (lang && lang !== 'plaintext') {
      const highlighted = await highlightCode(code, lang);
      block.innerHTML = highlighted;
      block.setAttribute('data-highlighted', 'true');
    }
  }
}

/**
 * Copy code to clipboard
 */
export function copyCodeToClipboard(codeId) {
  const codeElement = document.getElementById(codeId);

  if (!codeElement) {
    console.error('Code element not found:', codeId);
    return;
  }

  const code = codeElement.textContent;

  navigator.clipboard.writeText(code).then(() => {
    // Show success feedback
    if (window.VPToast) {
      window.VPToast.success('Code copied to clipboard!', 2000);
    }

    // Update button state
    const button = document.querySelector(`[data-code-id="${codeId}"]`);
    if (button) {
      const originalHTML = button.innerHTML;
      button.innerHTML = `
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        <span>Copied!</span>
      `;
      button.classList.add('chatpr-code-copy-success');

      setTimeout(() => {
        button.innerHTML = originalHTML;
        button.classList.remove('chatpr-code-copy-success');
      }, 2000);
    }
  }).catch(err => {
    console.error('Failed to copy code:', err);
    if (window.VPToast) {
      window.VPToast.error('Failed to copy code');
    }
  });
}

// Make copy function globally available
window.ChatPRCopyCode = copyCodeToClipboard;

export default {
  renderMarkdown,
  highlightCodeBlocks,
  copyCodeToClipboard,
};
