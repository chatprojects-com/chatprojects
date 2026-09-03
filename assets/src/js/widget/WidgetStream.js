/**
 * SSE Stream Parser for ChatProjects Widget.
 *
 * Parses Server-Sent Events from the widget message endpoint.
 * Matches the same SSE format used by the main ChatProjects chat.
 *
 * @package ChatProjects
 */

/**
 * Process an SSE stream from a fetch Response.
 *
 * @param {Response} response   Fetch API Response object.
 * @param {object}   callbacks  Callback object with onContent, onSources, onError, onDone.
 * @param {AbortSignal} signal  Optional AbortSignal for cancellation.
 * @returns {Promise<void>}
 */
export async function processStream( response, callbacks, signal ) {
	const reader = response.body.getReader();
	const decoder = new TextDecoder();
	let buffer = '';

	try {
		while ( true ) {
			if ( signal && signal.aborted ) {
				reader.cancel();
				break;
			}

			const { done, value } = await reader.read();
			if ( done ) {
				break;
			}

			buffer += decoder.decode( value, { stream: true } );
			const lines = buffer.split( '\n' );

			// Keep the last incomplete line in the buffer.
			buffer = lines.pop() || '';

			for ( const line of lines ) {
				const trimmed = line.trim();

				// Skip empty lines and SSE comments.
				if ( ! trimmed || trimmed.startsWith( ':' ) ) {
					continue;
				}

				// Check for [DONE] signal.
				if ( trimmed === 'data: [DONE]' ) {
					if ( callbacks.onDone ) {
						callbacks.onDone();
					}
					return;
				}

				// Parse SSE data lines.
				if ( trimmed.startsWith( 'data: ' ) ) {
					const jsonStr = trimmed.slice( 6 );
					try {
						const data = JSON.parse( jsonStr );
						handleEvent( data, callbacks );
					} catch ( e ) {
						// Skip malformed JSON.
					}
				}
			}
		}
	} catch ( err ) {
		if ( err.name !== 'AbortError' ) {
			if ( callbacks.onError ) {
				callbacks.onError( err.message || 'Stream error' );
			}
		}
	}
}

/**
 * Handle a parsed SSE event.
 *
 * @param {object} data      Parsed event data.
 * @param {object} callbacks Callback object.
 */
function handleEvent( data, callbacks ) {
	if ( ! data || ! data.type ) {
		return;
	}

	switch ( data.type ) {
		case 'content':
			if ( callbacks.onContent && data.content ) {
				callbacks.onContent( data.content );
			}
			break;

		case 'sources':
			if ( callbacks.onSources && data.sources ) {
				callbacks.onSources( data.sources );
			}
			break;

		case 'error':
			if ( callbacks.onError ) {
				callbacks.onError( data.content || 'Unknown error' );
			}
			break;

		case 'done':
			if ( callbacks.onDone ) {
				callbacks.onDone();
			}
			break;
	}
}
