/**
 * Markdown Rendering Utilities
 *
 * marked (GFM) + highlight.js for code blocks, with every rendered fragment
 * passed through DOMPurify before it reaches innerHTML / x-html. Model output
 * and uploaded content are untrusted: never render markdown without sanitize().
 */

import { marked } from 'marked';
import DOMPurify from 'dompurify';
import hljs from 'highlight.js/lib/common';

const LANGUAGE_ALIASES = {
  js: 'javascript',
  ts: 'typescript',
  py: 'python',
  sh: 'bash',
  shell: 'bash',
  yml: 'yaml',
  md: 'markdown',
};

/**
 * Escape text for safe insertion into HTML.
 */
function escapeHtml(text) {
  return String(text)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

/**
 * Normalise a fence info-string into a highlight.js language id (or 'plaintext').
 */
function normaliseLanguage(lang) {
  const raw = String(lang || '').trim().toLowerCase().split(/\s+/)[0];
  if (!raw || !/^[a-z0-9+#._-]+$/.test(raw)) {
    return 'plaintext';
  }
  return LANGUAGE_ALIASES[raw] || raw;
}

/**
 * Highlight code synchronously. Output is HTML-escaped by highlight.js.
 */
function highlightCode(code, lang) {
  if (lang && lang !== 'plaintext' && hljs.getLanguage(lang)) {
    try {
      return hljs.highlight(code, { language: lang, ignoreIllegals: true }).value;
    } catch (error) {
      // fall through to plain escape
    }
  }
  return escapeHtml(code);
}

/**
 * Custom code-block renderer (marked >= 13 signature: a token object).
 */
function renderCodeBlock({ text, lang }) {
  const validLang = normaliseLanguage(lang);
  const langLabel = validLang.charAt(0).toUpperCase() + validLang.slice(1);
  const blockId = 'code-' + Math.random().toString(36).slice(2, 11);
  const body = highlightCode(text, validLang);

  return `
    <div class="chatpr-code-block" data-language="${escapeHtml(validLang)}">
      <div class="chatpr-code-header">
        <span class="chatpr-code-language">${escapeHtml(langLabel)}</span>
        <button type="button" class="chatpr-code-copy" data-code-id="${blockId}" title="Copy code">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
          </svg>
          <span>Copy</span>
        </button>
      </div>
      <pre class="chatpr-code-content"><code id="${blockId}" class="language-${escapeHtml(validLang)}" data-highlighted="true">${body}</code></pre>
    </div>
  `;
}

marked.use({
  gfm: true,
  breaks: true,
  pedantic: false,
  renderer: {
    code: renderCodeBlock,
  },
});

// Links: open external URLs in a new tab, always with rel=noopener; strip unsafe schemes.
DOMPurify.addHook('afterSanitizeAttributes', (node) => {
  if (node.tagName === 'A') {
    const href = node.getAttribute('href') || '';
    if (/^\s*(https?:)?\/\//i.test(href)) {
      node.setAttribute('target', '_blank');
      node.setAttribute('rel', 'noopener noreferrer');
    }
  }
});

const PURIFY_CONFIG = {
  USE_PROFILES: { html: true },
  ADD_ATTR: ['target', 'data-code-id', 'data-language', 'data-highlighted'],
  FORBID_TAGS: ['style', 'form', 'input', 'textarea', 'select', 'iframe', 'object', 'embed'],
  FORBID_ATTR: ['style', 'onerror', 'onload', 'onclick'],
  ALLOW_DATA_ATTR: false,
};

/**
 * Sanitize rendered HTML. Exported so other renderers can reuse the policy.
 */
export function sanitizeHtml(html) {
  return DOMPurify.sanitize(html, PURIFY_CONFIG);
}

/**
 * Render markdown to sanitized HTML (synchronous; safe for Alpine x-html).
 */
export function renderMarkdownSync(markdown) {
  if (!markdown) return '';
  let html = marked.parse(String(markdown));
  // Responsive wrapper for tables.
  html = html.replace(/<table>/g, '<div class="chatpr-table-wrapper"><table>').replace(/<\/table>/g, '</table></div>');
  return sanitizeHtml(html);
}

/**
 * Async alias kept for callers that await it.
 */
export async function renderMarkdown(markdown) {
  return renderMarkdownSync(markdown);
}

/**
 * Highlight any code blocks that were rendered without highlighting.
 */
export async function highlightCodeBlocks(container) {
  const codeBlocks = container.querySelectorAll('code[data-highlighted="false"]');
  for (const block of codeBlocks) {
    const lang = normaliseLanguage(block.className.replace('language-', ''));
    block.innerHTML = highlightCode(block.textContent, lang);
    block.setAttribute('data-highlighted', 'true');
  }
}

/**
 * Copy code to clipboard
 */
export function copyCodeToClipboard(codeId) {
  const codeElement = document.getElementById(codeId);

  if (!codeElement) {
    return;
  }

  const code = codeElement.textContent;

  navigator.clipboard.writeText(code).then(() => {
    if (window.VPToast) {
      window.VPToast.success('Code copied to clipboard!', 2000);
    }

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
  }).catch(() => {
    if (window.VPToast) {
      window.VPToast.error('Failed to copy code');
    }
  });
}

// Copy buttons are plain markup (no inline handlers survive sanitizing); delegate clicks.
if (typeof document !== 'undefined') {
  document.addEventListener('click', (event) => {
    const button = event.target.closest('.chatpr-code-copy[data-code-id]');
    if (button) {
      event.preventDefault();
      copyCodeToClipboard(button.getAttribute('data-code-id'));
    }
  });
}

window.ChatPRCopyCode = copyCodeToClipboard;

export default {
  renderMarkdown,
  renderMarkdownSync,
  sanitizeHtml,
  highlightCodeBlocks,
  copyCodeToClipboard,
};
