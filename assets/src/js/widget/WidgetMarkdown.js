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

	// Code blocks (``` ... ```) — must be before inline code.
	html = html.replace( /```(\w*)\n?([\s\S]*?)```/g, function( match, lang, code ) {
		return '<pre class="cpw-code-block"><code>' + code.trim() + '</code></pre>';
	} );

	// Inline code (`code`).
	html = html.replace( /`([^`]+)`/g, '<code class="cpw-inline-code">$1</code>' );

	// Bold (**text** or __text__).
	html = html.replace( /\*\*(.+?)\*\*/g, '<strong>$1</strong>' );
	html = html.replace( /__(.+?)__/g, '<strong>$1</strong>' );

	// Italic (*text* or _text_).
	html = html.replace( /\*(.+?)\*/g, '<em>$1</em>' );
	html = html.replace( /(?<!\w)_(.+?)_(?!\w)/g, '<em>$1</em>' );

	// Links [text](url).
	html = html.replace(
		/\[([^\]]+)\]\(([^)]+)\)/g,
		'<a href="$2" target="_blank" rel="noopener noreferrer" class="cpw-link">$1</a>'
	);

	// Unordered lists (lines starting with - or *).
	html = html.replace( /^[\s]*[-*]\s+(.+)$/gm, '<li>$1</li>' );
	html = html.replace( /(<li>.*<\/li>\n?)+/g, '<ul class="cpw-list">$&</ul>' );

	// Ordered lists (lines starting with 1. 2. etc.).
	html = html.replace( /^[\s]*\d+\.\s+(.+)$/gm, '<li>$1</li>' );

	// Paragraphs (double newlines).
	html = html.replace( /\n\n/g, '</p><p>' );

	// Single newlines to <br>.
	html = html.replace( /\n/g, '<br>' );

	// Wrap in paragraph if not already wrapped.
	if ( ! html.startsWith( '<' ) ) {
		html = '<p>' + html + '</p>';
	}

	return html;
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
