/**
 * ChatProjects Widget - Main Chat Widget Class.
 *
 * Standalone vanilla JS widget. No Alpine.js, no jQuery dependencies.
 * Supports two modes:
 *   - floating: Chat bubble in corner that expands into a panel (default).
 *   - inline:   Chat panel embedded directly in page content.
 *
 * @package ChatProjects
 */

import { renderMarkdown } from './WidgetMarkdown.js';
import { processStream } from './WidgetStream.js';

const STORAGE_KEY_PREFIX = 'chatpr_widget_session';

export class ChatWidget {
	/**
	 * @param {object} config Widget configuration from wp_localize_script.
	 */
	constructor( config ) {
		this.config = {
			ajaxUrl: '',
			projectId: null,
			mode: 'floating',
			position: 'bottom-right',
			primaryColor: '#2563eb',
			welcomeMessage: 'Hi! How can I help you today?',
			placeholder: 'Type your message...',
			showBranding: false,
			siteName: '',
			title: '',
			height: 500,
			targetEl: null,
			instanceId: null,
			...config,
		};

		this.sessionToken = null;
		this.messages = [];
		this.isOpen = false;
		this.isStreaming = false;
		this.isInline = this.config.mode === 'inline';
		this.abortController = null;
		this.container = null;
		this.bubble = null;
		this.panel = null;
		this.messagesEl = null;
		this.inputEl = null;

		this.init();
	}

	/**
	 * Get a per-project localStorage key for session isolation.
	 *
	 * @returns {string}
	 */
	get storageKey() {
		if ( this.config.projectId ) {
			return STORAGE_KEY_PREFIX + '_' + this.config.projectId;
		}
		return STORAGE_KEY_PREFIX;
	}

	/**
	 * Initialize the widget.
	 */
	async init() {
		if ( this.isInline ) {
			this.createInlineDOM();
		} else {
			this.createFloatingDOM();
		}
		this.bindEvents();
		await this.restoreOrCreateSession();
	}

	// ==================== DOM BUILDERS ====================

	/**
	 * Build the shared panel contents (header, messages, input, branding).
	 *
	 * @returns {object} { header, messagesEl, inputArea, brandingEl }
	 */
	buildPanelContents() {
		// Panel header.
		const headerTitle = this.config.title || this.config.siteName || 'Chat';
		const header = document.createElement( 'div' );
		header.className = 'cpw-header';
		header.innerHTML = '<span class="cpw-header-title">' + this.escapeHtml( headerTitle ) + '</span>';

		// Messages area.
		this.messagesEl = document.createElement( 'div' );
		this.messagesEl.className = 'cpw-messages';
		this.messagesEl.setAttribute( 'role', 'log' );
		this.messagesEl.setAttribute( 'aria-live', 'polite' );

		// Input area.
		const inputArea = document.createElement( 'div' );
		inputArea.className = 'cpw-input-area';

		this.inputEl = document.createElement( 'textarea' );
		this.inputEl.className = 'cpw-input';
		this.inputEl.placeholder = this.config.placeholder;
		this.inputEl.rows = 1;
		this.inputEl.setAttribute( 'aria-label', 'Message input' );

		const sendBtn = document.createElement( 'button' );
		sendBtn.className = 'cpw-send';
		sendBtn.setAttribute( 'aria-label', 'Send message' );
		sendBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>';
		sendBtn.addEventListener( 'click', () => this.sendMessage() );

		inputArea.appendChild( this.inputEl );
		inputArea.appendChild( sendBtn );

		// Branding.
		let brandingEl = null;
		if ( this.config.showBranding ) {
			brandingEl = document.createElement( 'div' );
			brandingEl.className = 'cpw-branding';
			brandingEl.innerHTML = 'Powered by <a href="https://wordpress.org/plugins/chatprojects/" target="_blank" rel="noopener noreferrer">ChatProjects</a>';
		}

		return { header, messagesEl: this.messagesEl, inputArea, brandingEl };
	}

	/**
	 * Create the floating widget DOM structure (bubble + panel).
	 */
	createFloatingDOM() {
		// Main container.
		this.container = document.createElement( 'div' );
		this.container.className = 'cpw-container cpw-' + this.config.position;
		this.container.setAttribute( 'role', 'complementary' );
		this.container.setAttribute( 'aria-label', 'Chat widget' );

		// Set CSS custom properties.
		this.container.style.setProperty( '--cpw-primary', this.config.primaryColor );
		this.container.style.setProperty( '--cpw-primary-hover', this.darkenColor( this.config.primaryColor, 15 ) );

		// Bubble button.
		this.bubble = document.createElement( 'button' );
		this.bubble.className = 'cpw-bubble';
		this.bubble.setAttribute( 'aria-label', 'Open chat' );
		this.bubble.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>';

		// Chat panel.
		this.panel = document.createElement( 'div' );
		this.panel.className = 'cpw-panel';
		this.panel.setAttribute( 'role', 'dialog' );
		this.panel.setAttribute( 'aria-label', 'Chat' );
		this.panel.style.display = 'none';

		const { header, inputArea, brandingEl } = this.buildPanelContents();

		// Close button for floating panel.
		const closeBtn = document.createElement( 'button' );
		closeBtn.className = 'cpw-close';
		closeBtn.setAttribute( 'aria-label', 'Close chat' );
		closeBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
		closeBtn.addEventListener( 'click', () => this.close() );
		header.appendChild( closeBtn );

		// Assemble panel.
		this.panel.appendChild( header );
		this.panel.appendChild( this.messagesEl );
		this.panel.appendChild( inputArea );
		if ( brandingEl ) {
			this.panel.appendChild( brandingEl );
		}

		// Assemble container.
		this.container.appendChild( this.panel );
		this.container.appendChild( this.bubble );

		document.body.appendChild( this.container );
	}

	/**
	 * Create the inline widget DOM structure (panel only, embedded in page).
	 */
	createInlineDOM() {
		const targetEl = this.config.targetEl;
		if ( ! targetEl ) {
			return;
		}

		// Main container.
		this.container = document.createElement( 'div' );
		this.container.className = 'cpw-container cpw-inline';
		this.container.setAttribute( 'role', 'complementary' );
		this.container.setAttribute( 'aria-label', 'Chat widget' );

		// Set CSS custom properties.
		this.container.style.setProperty( '--cpw-primary', this.config.primaryColor );
		this.container.style.setProperty( '--cpw-primary-hover', this.darkenColor( this.config.primaryColor, 15 ) );
		this.container.style.setProperty( '--cpw-height', this.config.height + 'px' );

		// Chat panel (always visible in inline mode).
		this.panel = document.createElement( 'div' );
		this.panel.className = 'cpw-panel cpw-panel--inline';
		this.panel.setAttribute( 'role', 'region' );
		this.panel.setAttribute( 'aria-label', 'Chat' );

		const { header, inputArea, brandingEl } = this.buildPanelContents();

		// Assemble panel.
		this.panel.appendChild( header );
		this.panel.appendChild( this.messagesEl );
		this.panel.appendChild( inputArea );
		if ( brandingEl ) {
			this.panel.appendChild( brandingEl );
		}

		// Assemble container and mount into target element.
		this.container.appendChild( this.panel );
		targetEl.appendChild( this.container );

		// Inline mode is always open.
		this.isOpen = true;
	}

	// ==================== EVENTS ====================

	/**
	 * Bind event listeners.
	 */
	bindEvents() {
		// Bubble click (floating mode only).
		if ( ! this.isInline && this.bubble ) {
			this.bubble.addEventListener( 'click', () => this.toggle() );
		}

		// Enter to send (Shift+Enter for newline).
		this.inputEl.addEventListener( 'keydown', ( e ) => {
			if ( e.key === 'Enter' && ! e.shiftKey ) {
				e.preventDefault();
				this.sendMessage();
			}
		} );

		// Auto-resize textarea.
		this.inputEl.addEventListener( 'input', () => {
			this.inputEl.style.height = 'auto';
			this.inputEl.style.height = Math.min( this.inputEl.scrollHeight, 120 ) + 'px';
		} );

		// Escape to close (floating mode only).
		if ( ! this.isInline ) {
			document.addEventListener( 'keydown', ( e ) => {
				if ( e.key === 'Escape' && this.isOpen ) {
					this.close();
				}
			} );
		}
	}

	// ==================== OPEN / CLOSE ====================

	/**
	 * Toggle the chat panel open/closed.
	 */
	toggle() {
		if ( this.isOpen ) {
			this.close();
		} else {
			this.open();
		}
	}

	/**
	 * Open the chat panel.
	 */
	open() {
		this.isOpen = true;
		this.panel.style.display = 'flex';
		if ( this.bubble ) {
			this.bubble.classList.add( 'cpw-bubble--open' );
			this.bubble.setAttribute( 'aria-label', 'Close chat' );
		}
		this.inputEl.focus();
		this.scrollToBottom();
	}

	/**
	 * Close the chat panel.
	 */
	close() {
		if ( this.isInline ) {
			return; // Inline mode cannot be closed.
		}
		this.isOpen = false;
		this.panel.style.display = 'none';
		if ( this.bubble ) {
			this.bubble.classList.remove( 'cpw-bubble--open' );
			this.bubble.setAttribute( 'aria-label', 'Open chat' );
		}
	}

	// ==================== SESSION ====================

	/**
	 * Restore an existing session or create a new one.
	 */
	async restoreOrCreateSession() {
		// Try to restore from localStorage (per-project key).
		const stored = localStorage.getItem( this.storageKey );
		let token = null;

		if ( stored ) {
			try {
				const data = JSON.parse( stored );
				token = data.token || null;
			} catch ( e ) {
				// Ignore parse errors.
			}
		}

		try {
			const params = new URLSearchParams();
			params.append( 'action', 'chatpr_widget_init' );
			if ( token ) {
				params.append( 'session_token', token );
			}
			// Send project_id so the backend creates a session for the right project.
			if ( this.config.projectId ) {
				params.append( 'project_id', this.config.projectId );
			}

			const response = await fetch( this.config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: params,
			} );

			const result = await response.json();

			if ( result.success ) {
				this.sessionToken = result.data.session_token;
				localStorage.setItem( this.storageKey, JSON.stringify( { token: this.sessionToken } ) );

				// Apply server config for floating widgets only (inline gets config from shortcode).
				if ( ! this.isInline && result.data.config ) {
					Object.assign( this.config, result.data.config );
				}

				// Add welcome message.
				if ( this.config.welcomeMessage && this.messages.length === 0 ) {
					this.addMessage( 'assistant', this.config.welcomeMessage );
				}

				// Load history if restoring session.
				if ( token === this.sessionToken ) {
					await this.loadHistory();
				}
			}
		} catch ( e ) {
			// Session init failed — widget will show but can't send messages.
		}
	}

	/**
	 * Load conversation history from the server.
	 */
	async loadHistory() {
		if ( ! this.sessionToken ) {
			return;
		}

		try {
			const params = new URLSearchParams();
			params.append( 'action', 'chatpr_widget_history' );
			params.append( 'session_token', this.sessionToken );

			const response = await fetch( this.config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: params,
			} );

			const result = await response.json();

			if ( result.success && result.data.messages && result.data.messages.length > 0 ) {
				// Clear welcome message, replace with history.
				this.messagesEl.innerHTML = '';
				this.messages = [];

				for ( const msg of result.data.messages ) {
					this.addMessage( msg.role, msg.content );
				}
			}
		} catch ( e ) {
			// History load failed — continue with empty chat.
		}
	}

	// ==================== MESSAGING ====================

	/**
	 * Send a message to the widget endpoint.
	 */
	async sendMessage() {
		const text = this.inputEl.value.trim();
		if ( ! text || this.isStreaming || ! this.sessionToken ) {
			return;
		}

		// Add user message.
		this.addMessage( 'user', text );
		this.inputEl.value = '';
		this.inputEl.style.height = 'auto';

		// Show typing indicator.
		const typingEl = this.addTypingIndicator();
		this.isStreaming = true;

		// Create abort controller.
		this.abortController = new AbortController();

		try {
			const params = new URLSearchParams();
			params.append( 'action', 'chatpr_widget_message' );
			params.append( 'session_token', this.sessionToken );
			params.append( 'message', text );

			const response = await fetch( this.config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: params,
			} );

			// Remove typing indicator.
			if ( typingEl && typingEl.parentNode ) {
				typingEl.remove();
			}

			// Refusals (rate limit, daily cap, expired session) come back as JSON, not a stream.
			const contentType = response.headers.get( 'Content-Type' ) || '';
			if ( ! response.ok || contentType.indexOf( 'application/json' ) !== -1 ) {
				let errorText = 'Sorry, something went wrong. Please try again.';
				try {
					const data = await response.json();
					if ( data && data.data && data.data.message ) {
						errorText = data.data.message;
					}
				} catch ( parseError ) {
					// Keep the generic message.
				}
				const errorEl = this.createMessageEl( 'assistant', '' );
				errorEl.querySelector( '.cpw-msg-content' ).innerHTML = '<span class="cpw-error">' + this.escapeHtml( errorText ) + '</span>';
				this.messagesEl.appendChild( errorEl );
				this.scrollToBottom();
				return;
			}

			// Create assistant message element.
			const msgEl = this.createMessageEl( 'assistant', '' );
			this.messagesEl.appendChild( msgEl );
			const contentEl = msgEl.querySelector( '.cpw-msg-content' );
			let fullContent = '';

			await processStream( response, {
				onContent: ( chunk ) => {
					fullContent += chunk;
					contentEl.innerHTML = renderMarkdown( fullContent );
					this.scrollToBottom();
				},
				onSources: ( sources ) => {
					// Optionally display sources.
					if ( sources && sources.length > 0 ) {
						const sourcesEl = document.createElement( 'div' );
						sourcesEl.className = 'cpw-sources';
						const sourceNames = sources.map( ( s ) => s.filename || 'Source' ).join( ', ' );
						sourcesEl.textContent = 'Sources: ' + sourceNames;
						msgEl.appendChild( sourcesEl );
					}
				},
				onError: ( error ) => {
					contentEl.innerHTML = '<span class="cpw-error">' + this.escapeHtml( error ) + '</span>';
				},
				onDone: () => {
					this.messages.push( { role: 'assistant', content: fullContent } );
				},
			}, this.abortController.signal );
		} catch ( e ) {
			if ( typingEl && typingEl.parentNode ) {
				typingEl.remove();
			}
			if ( e.name !== 'AbortError' ) {
				this.addMessage( 'assistant', 'Sorry, something went wrong. Please try again.' );
			}
		} finally {
			this.isStreaming = false;
			this.abortController = null;
			this.scrollToBottom();
		}
	}

	/**
	 * Add a message to the chat display.
	 *
	 * @param {string} role    Message role (user or assistant).
	 * @param {string} content Message content.
	 */
	addMessage( role, content ) {
		this.messages.push( { role, content } );
		const el = this.createMessageEl( role, content );
		this.messagesEl.appendChild( el );
		this.scrollToBottom();
	}

	/**
	 * Create a message DOM element.
	 *
	 * @param {string} role    Message role.
	 * @param {string} content Message content.
	 * @returns {HTMLElement}
	 */
	createMessageEl( role, content ) {
		const wrapper = document.createElement( 'div' );
		wrapper.className = 'cpw-msg cpw-msg--' + role;

		const contentEl = document.createElement( 'div' );
		contentEl.className = 'cpw-msg-content';

		if ( role === 'assistant' ) {
			contentEl.innerHTML = renderMarkdown( content );
		} else {
			contentEl.textContent = content;
		}

		wrapper.appendChild( contentEl );
		return wrapper;
	}

	/**
	 * Add a typing indicator and return the element.
	 *
	 * @returns {HTMLElement}
	 */
	addTypingIndicator() {
		const el = document.createElement( 'div' );
		el.className = 'cpw-msg cpw-msg--assistant cpw-typing';
		el.innerHTML = '<div class="cpw-msg-content"><span class="cpw-dot"></span><span class="cpw-dot"></span><span class="cpw-dot"></span></div>';
		this.messagesEl.appendChild( el );
		this.scrollToBottom();
		return el;
	}

	// ==================== HELPERS ====================

	/**
	 * Scroll messages to the bottom.
	 */
	scrollToBottom() {
		requestAnimationFrame( () => {
			this.messagesEl.scrollTop = this.messagesEl.scrollHeight;
		} );
	}

	/**
	 * Escape HTML entities.
	 *
	 * @param {string} text Raw text.
	 * @returns {string} Escaped text.
	 */
	escapeHtml( text ) {
		const div = document.createElement( 'div' );
		div.textContent = text;
		return div.innerHTML;
	}

	/**
	 * Darken a hex color by a percentage.
	 *
	 * @param {string} hex   Hex color string.
	 * @param {number} pct   Percentage to darken (0-100).
	 * @returns {string} Darkened hex color.
	 */
	darkenColor( hex, pct ) {
		hex = hex.replace( '#', '' );
		const num = parseInt( hex, 16 );
		const factor = 1 - pct / 100;
		const r = Math.max( 0, Math.round( ( ( num >> 16 ) & 255 ) * factor ) );
		const g = Math.max( 0, Math.round( ( ( num >> 8 ) & 255 ) * factor ) );
		const b = Math.max( 0, Math.round( ( num & 255 ) * factor ) );
		return '#' + ( ( 1 << 24 ) + ( r << 16 ) + ( g << 8 ) + b ).toString( 16 ).slice( 1 );
	}

	/**
	 * Destroy the widget and clean up.
	 */
	destroy() {
		if ( this.abortController ) {
			this.abortController.abort();
		}
		if ( this.container && this.container.parentNode ) {
			this.container.remove();
		}
	}
}
