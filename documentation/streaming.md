# ChatProjects Streaming Implementation Guide

This document explains how real-time streaming works in the ChatProjects plugin, covering both Project Chat and General Chat modes.

## Overview

ChatProjects uses Server-Sent Events (SSE) to stream AI responses to the browser in real-time. This allows users to see text appear word-by-word instead of waiting for the entire response.

## Architecture

```
┌─────────────┐     SSE Stream      ┌──────────────┐     cURL Stream     ┌─────────────┐
│   Browser   │ ◄────────────────── │  WordPress   │ ◄────────────────── │  OpenAI API │
│  (Alpine.js)│                     │  (PHP/AJAX)  │                     │             │
└─────────────┘                     └──────────────┘                     └─────────────┘
```

### Two Chat Modes

1. **Project Chat** (`chatpr_stream_chat_message`)
   - Uses `API_Handler->stream_response_with_filesearch()`
   - Supports vector store file search
   - Located in: `public/ajax-handlers.php`

2. **General Chat** (`chatpr_stream_general_message`)
   - Uses provider's `stream_completion()` method
   - Supports multiple AI providers (OpenAI, Anthropic, Gemini, etc.)
   - Located in: `includes/class-general-chat-ajax.php`

---

## Backend Implementation

### SSE Headers (Critical)

Both endpoints must set these headers to disable server buffering:

```php
// Disable ALL output buffering
while (ob_get_level()) {
    ob_end_clean();
}

// SSE headers
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate, private');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Accel-Buffering: no');  // nginx
header('Connection: keep-alive');
header('Transfer-Encoding: chunked');

// LiteSpeed specific
header('X-LiteSpeed-Cache-Control: no-cache, no-store, esi=off');
header('X-LiteSpeed-Tag: no-cache');
header('X-LiteSpeed-Purge: *');

// Cloudflare
header('X-CF-Buffering: off');

// PHP settings
@ini_set('zlib.output_compression', '0');
@ini_set('implicit_flush', '1');
@ini_set('output_buffering', '0');
@ob_implicit_flush(true);

// CRITICAL: Send padding to flush server buffer (8KB+)
echo ':' . str_repeat(' ', 8192) . "\n\n";
@flush();
```

### SSE Data Format

Each chunk must be sent in SSE format:

```php
// Content chunk
echo "data: " . wp_json_encode(['type' => 'content', 'content' => $text]) . "\n\n";

// Chat ID (sent once when new chat created)
echo "data: " . wp_json_encode(['type' => 'chat_id', 'chat_id' => $id]) . "\n\n";

// Sources (for file search)
echo "data: " . wp_json_encode(['type' => 'sources', 'sources' => $sources]) . "\n\n";

// Error
echo "data: " . wp_json_encode(['type' => 'error', 'content' => $message]) . "\n\n";

// Done signal
echo "data: [DONE]\n\n";
```

### Flushing (Critical)

After each `echo`, you MUST flush:

```php
if (function_exists('litespeed_flush')) {
    litespeed_flush();
}
@ob_flush();
@flush();
```

### Provider Streaming Implementations

All providers use cURL with `CURLOPT_WRITEFUNCTION` for true streaming. Each has a different SSE format:

#### OpenAI Provider

The OpenAI provider (`includes/providers/class-openai-provider.php`):

```php
public function stream_completion($messages, $model, $callback, $options = array()) {
    $url = self::API_BASE_URL . 'chat/completions';

    $data = [
        'model' => $model,
        'messages' => $this->format_messages_for_chat_api($messages),
        'stream' => true,
    ];

    $ch = curl_init($url);
    $buffer = '';

    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => wp_json_encode($data),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $this->api_key,
            'Content-Type: application/json',
            'Accept: text/event-stream',
        ],
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_TIMEOUT        => 300,
        CURLOPT_BUFFERSIZE     => 128,
        CURLOPT_WRITEFUNCTION  => function($ch, $chunk) use ($callback, &$buffer) {
            $buffer .= $chunk;

            // Process complete SSE events
            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $event = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);

                if (preg_match('/^data: (.+)$/m', $event, $matches)) {
                    $json_data = trim($matches[1]);
                    if ($json_data === '[DONE]') continue;

                    $parsed = json_decode($json_data, true);
                    if ($parsed && isset($parsed['choices'][0]['delta']['content'])) {
                        $callback(['type' => 'content', 'content' => $parsed['choices'][0]['delta']['content']]);
                    }
                }
            }
            return strlen($chunk);
        },
    ]);

    curl_exec($ch);
    curl_close($ch);
    $callback(['type' => 'done']);
}
```

**SSE Format:** `data: {"choices":[{"delta":{"content":"text"}}]}`

#### Gemini Provider

The Gemini provider (`includes/providers/class-gemini-provider.php`):

```php
$url = self::API_BASE_URL . "models/{$model}:streamGenerateContent?alt=sse&key=" . $this->api_key;

// In CURLOPT_WRITEFUNCTION callback:
if ($parsed && isset($parsed['candidates'][0]['content']['parts'][0]['text'])) {
    $content = $parsed['candidates'][0]['content']['parts'][0]['text'];
    $callback(['type' => 'content', 'content' => $content]);
}
```

**SSE Format:** `data: {"candidates":[{"content":{"parts":[{"text":"hello"}]}}]}`

#### Anthropic/Claude Provider

The Anthropic provider (`includes/providers/class-anthropic-provider.php`):

```php
$url = self::API_BASE_URL . 'messages';
// Headers must include: x-api-key, anthropic-version

// In CURLOPT_WRITEFUNCTION callback:
// Anthropic uses event types - look for 'content_block_delta'
if ($event_type === 'content_block_delta' && isset($parsed['delta']['text'])) {
    $callback(['type' => 'content', 'content' => $parsed['delta']['text']]);
}
```

**SSE Format:**
```
event: content_block_delta
data: {"type":"content_block_delta","delta":{"type":"text_delta","text":"Hello"}}
```

---

## Frontend Implementation

### Alpine.js Reactivity Issue

Alpine.js doesn't detect deep mutations in arrays. Simply doing `message.content += text` won't trigger a re-render.

**Solution:** Create a new object and reassign the array:

```javascript
// WRONG - won't trigger re-render
assistantMessage.content += parsed.content;

// CORRECT - forces Alpine to detect the change
assistantMessage.content += parsed.content;
var idx = this.messages.findIndex(function(m) {
    return m.streaming && m.role === 'assistant';
});
if (idx !== -1) {
    this.messages[idx] = Object.assign({}, this.messages[idx], { content: assistantMessage.content });
    this.messages = this.messages.slice();  // Create new array reference
}
```

### ES Module Caching Issue

ES modules are cached aggressively by browsers. On page navigation (not hard refresh), the browser may use cached JavaScript that doesn't have the latest fixes.

**Solution:** Patch Alpine components inline in PHP templates:

```javascript
// In pro-chat.php and project-shell-modern.php
(function() {
    Object.defineProperty(window, 'Alpine', {
        configurable: true,
        set: function(alpine) {
            var originalData = alpine.data;
            alpine.data = function(name, fn) {
                var wrappedFn = function() {
                    var component = fn.apply(this, arguments);

                    if (name === 'chat') {
                        // Override sendMessage
                        component.sendMessage = async function() { /* ... */ };

                        // Override streamResponse
                        component.streamResponse = async function(message, images) { /* ... */ };
                    }
                    return component;
                };
                return originalData.call(this, name, wrappedFn);
            };
            // ... rest of property definition
        }
    });
})();
```

### Back-Forward Cache (bfcache) Issue

When navigating back/forward, browsers may restore pages from bfcache with stale JavaScript state. The `streaming` flag might be stuck as `true`.

**Solutions:**

1. **Force reload on bfcache restore:**
```javascript
window.addEventListener("pageshow", function(event) {
    if (event.persisted) {
        window.location.reload();
    }
});
```

2. **Reset stale state in sendMessage:**
```javascript
if (this.streaming && !this.abortController) {
    console.log('Resetting stale streaming state');
    this.streaming = false;
}
```

### Nonce Refresh

WordPress nonces expire. Cached pages may have stale nonces.

**Solution:** Refresh nonce on page load:

```javascript
chatprData.nonceReady = fetch(chatprData.ajax_url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ action: 'chatpr_refresh_nonce' })
})
.then(r => r.json())
.then(data => {
    if (data.success && data.data.nonce) {
        chatprData.nonce = data.data.nonce;
    }
    return true;
});

// In streamResponse, wait for nonce refresh:
if (chatprData.nonceReady) {
    await chatprData.nonceReady;
}
```

---

## Server Configuration

### .htaccess (LiteSpeed/Apache)

Place in plugin directory (`wp-content/plugins/chatprojects/.htaccess`):

```apache
# Disable output buffering for SSE streaming
<IfModule LiteSpeed>
    SetEnv noabort 1
    SetEnv no-gzip 1
</IfModule>

# Disable compression for event-stream
<IfModule mod_deflate.c>
    SetEnvIfNoCase Request_URI \.php$ no-gzip dont-vary
</IfModule>

# PHP settings for streaming
<IfModule mod_php.c>
    php_flag output_buffering Off
    php_flag zlib.output_compression Off
</IfModule>
```

### Site Root .htaccess

Add rules for admin-ajax.php streaming:

```apache
# Disable buffering for SSE/streaming endpoints
<IfModule LiteSpeed>
    RewriteEngine On
    RewriteCond %{QUERY_STRING} action=chatpr_stream [NC]
    RewriteRule .* - [E=noabort:1,E=no-gzip:1]
</IfModule>

<IfModule mod_deflate.c>
    SetEnvIfNoCase Request_URI admin-ajax\.php$ no-gzip dont-vary
</IfModule>
```

---

## File Reference

| File | Purpose |
|------|---------|
| `public/ajax-handlers.php` | Project chat streaming endpoint |
| `includes/class-general-chat-ajax.php` | General chat streaming endpoint |
| `includes/class-api-handler.php` | OpenAI API streaming (file search) |
| `includes/providers/class-openai-provider.php` | OpenAI Chat Completions streaming |
| `public/templates/pro-chat.php` | General chat template with Alpine patches |
| `public/templates/project-shell-modern.php` | Project chat template with Alpine patches |
| `assets/dist/js/chat.js` | Chat Alpine component (ES module) |

---

## Troubleshooting

### Text appears all at once (not streaming)

1. Check SSE headers are being sent
2. Verify 8KB padding is sent before content
3. Ensure `@ob_flush()` and `@flush()` after each chunk
4. Check server buffering (.htaccess rules)
5. Verify provider's `stream_completion()` uses cURL streaming

### "Failed to get response" after navigation

1. Check nonce refresh is working (console logs)
2. Verify Alpine patches are applied (look for `[Patched]` logs)
3. Check `streaming` state isn't stuck true

### Streaming works on refresh but not navigation

1. Ensure `pageshow` event handler is present
2. Verify Alpine patches in PHP templates
3. Check for stale state reset in sendMessage

### Console shows no `[ChatProjects]` logs

Browser is using cached ES module. The PHP template patches should override this, but verify they're being applied by checking for `[Patched]` prefix in logs.

---

## Testing Checklist

- [ ] Project chat streams incrementally
- [ ] General chat streams incrementally
- [ ] Chat works after navigating away and back
- [ ] Chat works after using browser back/forward buttons
- [ ] Chat works after page has been open for extended time
- [ ] Multiple messages in a row work correctly
- [ ] Stop generation button works
- [ ] Error messages display correctly

---

*Last Updated: December 2024*
