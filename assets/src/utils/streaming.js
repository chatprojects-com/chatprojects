/**
 * Streaming Utilities
 * Handle Server-Sent Events (SSE) for chat streaming
 */

/**
 * Stream chat response from server
 *
 * @param {string} url - The endpoint URL
 * @param {object} data - Request data
 * @param {Function} onChunk - Callback for each chunk
 * @param {Function} onComplete - Callback when stream completes
 * @param {Function} onError - Callback for errors
 * @returns {AbortController} Controller to stop the stream
 */
export async function streamChatResponse(url, data, onChunk, onComplete, onError) {
  const abortController = new AbortController();

  try {
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: new URLSearchParams(data),
      signal: abortController.signal,
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';

    while (true) {
      const { done, value } = await reader.read();

      if (done) {
        break;
      }

      // Decode the chunk
      buffer += decoder.decode(value, { stream: true });

      // Process complete lines
      const lines = buffer.split('\n');
      buffer = lines.pop() || ''; // Keep incomplete line in buffer

      for (const line of lines) {
        if (line.trim() === '') continue;

        // Handle SSE format: "data: {json}"
        if (line.startsWith('data: ')) {
          const data = line.slice(6); // Remove "data: " prefix

          if (data === '[DONE]') {
            onComplete?.();
            return abortController;
          }

          try {
            const parsed = JSON.parse(data);
            onChunk?.(parsed);
          } catch (error) {
            console.warn('Failed to parse SSE data:', data, error);
          }
        }
      }
    }

    onComplete?.();
  } catch (error) {
    if (error.name === 'AbortError') {
      console.log('Stream aborted by user');
    } else {
      console.error('Streaming error:', error);
      onError?.(error);
    }
  }

  return abortController;
}

/**
 * Simple polling fallback for servers that don't support streaming
 *
 * @param {string} url - The endpoint URL
 * @param {object} data - Request data
 * @param {Function} onMessage - Callback for new messages
 * @param {number} interval - Poll interval in ms
 * @returns {Function} Stop function
 */
export function pollForUpdates(url, data, onMessage, interval = 1000) {
  let polling = true;
  let lastMessageId = null;

  const poll = async () => {
    if (!polling) return;

    try {
      const response = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          ...data,
          last_message_id: lastMessageId || '',
        }),
      });

      const result = await response.json();

      if (result.success && result.data.messages) {
        result.data.messages.forEach(message => {
          onMessage(message);
          lastMessageId = message.id;
        });
      }

      if (result.data.complete) {
        polling = false;
      } else if (polling) {
        setTimeout(poll, interval);
      }
    } catch (error) {
      console.error('Polling error:', error);
      polling = false;
    }
  };

  poll();

  // Return stop function
  return () => {
    polling = false;
  };
}

export default {
  streamChatResponse,
  pollForUpdates,
};
