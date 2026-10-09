/**
 * Minimal Markdown Renderer for ChatProjects Widget.
 *
 * Supports: bold, italic, inline code, code blocks, links, lists, line breaks.
 * Intentionally lightweight (~2KB) to keep widget bundle small.
 *
 * @package ChatProjects
 */

/**
 * Render markdown text to HTML.
 *
 * @param {string} text Raw markdown text.
 * @returns {string} HTML string.
 */
export function renderMarkdown( text ) {
	if ( ! text ) {
		return '';
	}

	let html = escapeHtml( text );

	// Pull code out first so bold/italic/list rules never touch its contents.
	const codeSpans = [];
	const stash = ( markup ) => {
		codeSpans.push( markup );
		return '\u0000' + ( codeSpans.length - 1 ) + '\u0000';
	};

	// Code blocks (``` ... ```) — must be before inline code.
	html = html.replace( /```(\w*)\n?([\s\S]*?)```/g, function( match, lang, code ) {
		return stash( '<pre class="cpw-code-block"><code>' + code.trim() + '</code></pre>' );
	} );

	// Inline code (`code`).
	html = html.replace( /`([^`\n]+)`/g, function( match, code ) {
		return stash( '<code class="cpw-inline-code">' + code + '</code>' );
	} );

	// Bold (**text** or __text__).
	html = html.replace( /\*\*(.+?)\*\*/g, '<strong>$1</strong>' );
	html = html.replace( /__(.+?)__/g, '<strong>$1</strong>' );

	// Italic (*text* or _text_).
	html = html.replace( /(^|[^*])\*(?!\s)([^*\n]+?)\*(?!\*)/g, '$1<em>$2</em>' );
	html = html.replace( /(?<!\w)_(.+?)_(?!\w)/g, '<em>$1</em>' );

	// Links [text](url) — only http(s) and mailto schemes become anchors.
	html = html.replace( /\[([^\]]+)\]\(([^)\s]+)\)/g, function( match, label, url ) {
		if ( ! isSafeUrl( url ) ) {
			return label;
		}
		return '<a href="' + url + '" target="_blank" rel="noopener noreferrer" class="cpw-link">' + label + '</a>';
	} );

	// Unordered lists (lines starting with - or *).
	html = html.replace( /^[ \t]*[-*][ \t]+(.+)$/gm, '<li data-cpw="ul">$1</li>' );

	// Ordered lists (lines starting with 1. 2. etc.).
	html = html.replace( /^[ \t]*\d+\.[ \t]+(.+)$/gm, '<li data-cpw="ol">$1</li>' );

	// Wrap consecutive items of the same kind in <ul> / <ol>.
	html = html.replace( /(?:<li data-cpw="ul">.*<\/li>\n?)+/g, function( items ) {
		return '<ul class="cpw-list">' + items.replace( / data-cpw="ul"/g, '' ).replace( /\n/g, '' ) + '</ul>';
	} );
	html = html.replace( /(?:<li data-cpw="ol">.*<\/li>\n?)+/g, function( items ) {
		return '<ol class="cpw-list">' + items.replace( / data-cpw="ol"/g, '' ).replace( /\n/g, '' ) + '</ol>';
	} );

	// Paragraphs (double newlines).
	html = html.replace( /\n\n/g, '</p><p>' );

	// Single newlines to <br>.
	html = html.replace( /\n/g, '<br>' );

	// Put code back (its own newlines are preserved inside <pre>).
	html = html.replace( /\u0000(\d+)\u0000/g, function( match, index ) {
		return codeSpans[ Number( index ) ];
	} );

	// Wrap in paragraph if not already wrapped.
	if ( ! html.startsWith( '<' ) ) {
		html = '<p>' + html + '</p>';
	}

	return html;
}

/**
 * Whether a (already HTML-escaped) URL uses a scheme we allow in links.
 *
 * @param {string} url Candidate URL.
 * @returns {boolean}
 */
function isSafeUrl( url ) {
	const trimmed = url.trim().toLowerCase();
	if ( trimmed.startsWith( '/' ) && ! trimmed.startsWith( '//' ) ) {
		return true;
	}
	return /^(https?:\/\/|mailto:)/.test( trimmed );
}

/**
 * Escape HTML entities.
 *
 * @param {string} text Raw text.
 * @returns {string} Escaped text.
 */
function escapeHtml( text ) {
	const map = {
		'&': '&amp;',
		'<': '&lt;',
		'>': '&gt;',
		'"': '&quot;',
		"'": '&#039;',
	};
	return text.replace( /[&<>"']/g, function( m ) {
		return map[ m ];
	} );
}
