=== ChatProjects ===
Contributors: chatprojects
Donate link: https://chatprojects.com/
Tags: ai, chatgpt, openai, chatbot, project management, vector store, responses api
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered project management with multi-provider chat. Vector store file search. Chat with GPT, Claude, Gemini and more.

== Description ==

**ChatProjects** is the easiest way to chat with your files and documents in WordPress. AI-powered project chat with OpenAI Responses API vector store backend for intelligent file search.

Use your own API keys to chat with multiple AI providers including OpenAI (GPT-5.6), Anthropic (Claude Opus 5), Google (Gemini 3.8), Chutes (DeepSeek V4), and OpenRouter.

= Key Features =

* **Multi-Provider Chat** - Chat with GPT-5.6, Claude Opus 5, Gemini 3.8, DeepSeek V4, and 100+ models via OpenRouter
* **Project Management** - Create projects with OpenAI's file search capability
* **File Upload** - Upload documents (PDF, TXT, DOC) to your project's vector store
* **Custom Instructions** - Set custom assistant instructions for each project
* **Shared Chatbots** - Create project-based chatbots that can be shared with your team
* **Modern Interface** - Clean, responsive chat interface with dark mode support
* **Embeddable** - Use shortcodes to embed the full application on any page
* **Privacy First** - Your API keys stay on your server, not ours

= Supported AI Providers =

1. **OpenAI** - GPT-5.6 Sol / Terra / Luna, GPT-5.5, GPT-5.4, GPT-5.4 Mini, GPT-5.4 Nano
2. **Anthropic** - Claude Opus 5, Claude Fable 5.1, Claude Opus 4.8, Claude Sonnet 5, Claude Sonnet 4.6, Claude Haiku 4.5
3. **Google Gemini** - Gemini 3.8 Flash, Gemini 3.7 Flash, Gemini 3.5 Flash, Gemini 3.5 Flash Lite, Gemini 3.1 Pro (Preview)
4. **Chutes** - DeepSeek V4 Flash, DeepSeek V3.2, plus every model Chutes hosts
5. **OpenRouter** - Access 100+ models from various providers

= Shortcodes =

**Full Application:**
`[chatprojects_main]`

**With Options:**
`[chatprojects_main default_tab="chat" height="80vh"]`

= Requirements =

* WordPress 5.8 or higher
* PHP 7.4 or higher
* At least one API key (OpenAI, Anthropic, Gemini, Chutes, or OpenRouter)

== Installation ==

1. Upload the `chatprojects` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to ChatProjects > Settings to add your API keys
4. Access the interface on https://yourdomain.com/chatprojects/ or Create a page and add `[chatprojects_main]` shortcode
5. Start chatting!

= Getting API Keys =

**OpenAI:**
1. Visit [platform.openai.com](https://platform.openai.com/)
2. Sign up or log in
3. Go to API Keys section
4. Create a new secret key

**Anthropic:**
1. Visit [console.anthropic.com](https://console.anthropic.com/)
2. Sign up or log in
3. Go to API Keys
4. Create a new key

**Google Gemini:**
1. Visit [ai.google.dev](https://ai.google.dev/)
2. Sign up or log in
3. Get your API key

**Chutes:**
1. Visit [chutes.ai](https://chutes.ai/)
2. Sign up or log in
3. Get your API key

== Frequently Asked Questions ==

= Do I need all 5 API keys? =

No! You only need one API key to start chatting. Add more providers as needed. Note: An OpenAI API key is required for Projects and document chat (vector store) features.

= Where are my API keys stored? =

Your API keys are stored encrypted in your WordPress database. They never leave your server.

= Can multiple users access the project? =

Yes! Projects are accessible to all logged-in users. Administrators can configure sharing controls in settings.

= What file types can I upload? =

Supported file types: PDF, DOC, DOCX, TXT, MD, CSV, JSON, XML, HTML, CSS, JS, PY, PHP

= Is there a file size limit? =

Yes, the default limit is 50MB per file (can be adjusted in settings).

= Can I use this on a client site? =

Yes! ChatProjects is GPL licensed. You can use it on any WordPress site.

= How do I get support? =

Use the WordPress.org support forum or email support@chatprojects.com

== Screenshots ==

1. Project management dashboard
2. Main chat interface with provider selection
3. Project assistant with ResponsesAPI powered OpenAI Vector Store chat
4. Edit project assistant instructions 
5. Upload files to OpenAI Vector Store from device or media library
6. Chat administration settings and adding API keys

== Changelog ==

= 1.2.0 =
* **Models:** Replaced every retired model with current ones — GPT-5.6 Sol/Terra/Luna, GPT-5.5, GPT-5.4 family; Claude Opus 5, Fable 5.1, Opus 4.8, Sonnet 5, Sonnet 4.6, Haiku 4.5; Gemini 3.8/3.7/3.5 Flash and 3.1 Pro; DeepSeek V4. Existing settings, projects and chats are migrated automatically to the closest current model.
* **New:** Single model registry with `chatprojects_models` filter for adding custom or fine-tuned models.
* **New:** OpenAI reasoning effort support (`chatprojects_reasoning_effort` filter); temperature is no longer sent to models that reject it.
* **New:** Auto-RAG content indexing — index your posts and pages into a project vector store.
* **New:** Public chat widget (`[chatprojects_widget project="123"]`) with per-project opt-in, session tokens and rate limiting.
* **New:** "Keep data on uninstall" option.
* **Security:** Projects, chats and files are now only readable by their author and administrators.
* **Security:** Rendered markdown is sanitised with DOMPurify; code blocks and links are escaped.
* **Security:** API keys are never sent back to the browser; use "Remove this key" to clear one.
* **Security:** Stream endpoints authenticate before sending any output; rate limits on chat and uploads.
* **Security:** Executable file types can no longer be uploaded; MIME type is verified server-side.
* **Fixed:** Database tables are now created/updated on plugin update, not only on activation.
* **Fixed:** Gemini API key is sent as a header instead of in the URL.
* **Fixed:** Claude responses that start with a thinking block; refusals are reported instead of silently returning nothing.
* **Compatibility:** Tested with WordPress 7.1 and PHP 8.4/8.5. Requires WordPress 6.6+ and PHP 8.0+.
* Removed dead code, duplicate asset copies and Excel (.xls/.xlsx) upload, which required a library that was never bundled.

= 1.1.5 =
* Fixed /settings URL redirect hijacking other pages on sites
* Improved old slug redirect to use exact path matching only

= 1.1.4 =
* Fixed WordPress media library modal text invisible in dark mode
* Fixed wp.media dependency loading on ChatProjects pages

= 1.1.3 =
* Fixed theme CSS conflicts (Astra, Elementor, and others) breaking ChatProjects UI
* ChatProjects pages now fully isolate from all theme and plugin styles
* Fixed missing dark/light toggle and wrong button colors on themed sites

= 1.1.2 =
* Fixed Elementor CSS conflicts causing invisible dark/light toggle button
* Fixed Elementor popup/form HTML rendering on ChatProjects pages
* Improved button hover colors and styles
* Added comprehensive Elementor asset blocking on ChatProjects pages
* Better plugin compatibility with page builders

= 1.1.1 =
* Fixed Alpine.js initialization timing issue that prevented UI components from loading
* Improved JavaScript module loading reliability

= 1.1.0 =
* **IMPORTANT:** Changed plugin URLs to prevent conflicts with existing WordPress pages
* New URLs: /chatprojects/ (was /projects/), /cp-settings/ (was /settings/), /cp-chat/ (was /pro-chat/)
* Old URLs automatically redirect to new locations (backwards compatible)
* Added conflict detection for URL slugs on plugin activation
* Added developer filter hooks for slug customization
* Improved deactivation cleanup

= 1.0.0 =
* Initial release
* Multi-provider chat (OpenAI, Anthropic, Gemini, Chutes)
* Project management with OpenAI Assistants
* File upload to vector stores
* Modern chat interface
* Dark/Light theme support
* Shortcode embedding [chatprojects_main]

== Upgrade Notice ==

= 1.2.0 =
Retired AI models are replaced automatically. Project access is now limited to the project author and administrators. Requires WordPress 6.6+ and PHP 8.0+.

= 1.1.0 =
**IMPORTANT URL CHANGE:** Plugin URLs have changed to prevent conflicts. Old URLs (/projects/, /settings/, /pro-chat/) will automatically redirect to new URLs (/chatprojects/, /cp-settings/, /cp-chat/). Update your bookmarks. If you experience issues, go to Settings > Permalinks and click Save.

= 1.0.0 =
Initial release. Add your API keys and start chatting with AI!

== Privacy Policy ==

ChatProjects stores your API keys encrypted in your WordPress database. The plugin connects directly to AI provider APIs (OpenAI, Anthropic, Google, Chutes) using your own API keys. No data is sent to our servers.

For more information, see our [Privacy Policy](https://chatprojects.com/privacy/).

== Third-Party Services ==

This plugin connects to external AI services when users interact with chat features. **No data is sent automatically** - transmission only occurs when users explicitly take action.

= Data Transmitted =

* **Chat Messages:** User-entered text is sent to the selected AI provider when the user submits a message
* **Uploaded Files:** File contents are sent to OpenAI when users upload to a project with Vector Store enabled
* **System Instructions:** Project-configured system prompts are included with chat requests

= When Data Is Sent =

* On chat message submission (user clicks send or presses Enter)
* On file upload to a project (OpenAI only)
* On file search/retrieval operations (OpenAI only)

= API Keys =

* **Site owners supply their own API keys** - this plugin does not provide access to any AI service
* Keys are encrypted using AES-256-CBC and stored locally in the WordPress database
* Keys are never transmitted to chatprojects.com or any third party

= Service Providers =

**OpenAI API**
Used for AI chat, file analysis via Responses API, and vector store functionality.
* Service URL: https://api.openai.com/
* Privacy Policy: https://openai.com/privacy/
* Terms of Service: https://openai.com/terms/

**Anthropic Claude API**
Optional AI provider for chat features.
* Service URL: https://api.anthropic.com/
* Privacy Policy: https://www.anthropic.com/privacy
* Terms of Service: https://www.anthropic.com/terms

**Google Gemini API**
Optional AI provider for chat features.
* Service URL: https://generativelanguage.googleapis.com/
* Privacy Policy: https://policies.google.com/privacy
* Terms of Service: https://policies.google.com/terms

**Chutes API (DeepSeek)**
Optional AI provider for chat features using DeepSeek models.
* Service URL: https://llm.chutes.ai/
* Privacy Policy: https://chutes.ai/privacy
* Terms of Service: https://chutes.ai/terms

**OpenRouter API**
Optional AI provider giving access to 100+ models from various providers.
* Service URL: https://openrouter.ai/api/
* Privacy Policy: https://openrouter.ai/privacy
* Terms of Service: https://openrouter.ai/terms
* Note: When using OpenRouter, your site URL and site name are sent in HTTP headers as required by OpenRouter's API for attribution and rate limiting purposes.

= Your Control =

You choose which API providers to configure. Only providers with valid API keys configured will receive any data. Each provider handles transmitted data according to their own privacy policies linked above.

== Third-Party Libraries ==

This plugin includes the following third-party JavaScript libraries:

= Alpine.js =
* Version: 3.x
* License: MIT
* Source: https://github.com/alpinejs/alpine
* License file: licenses/ALPINE.txt

= highlight.js =
* Version: 11.x
* License: BSD-3-Clause
* Source: https://github.com/highlightjs/highlight.js
* License file: licenses/HIGHLIGHT.txt

= marked =
* Version: 16.x
* License: MIT
* Source: https://github.com/markedjs/marked
* License file: licenses/MARKED.txt

== Development ==

= Source Code =

The uncompressed source code for all JavaScript and CSS files is available at:
https://github.com/chatprojects-com/chatprojects

= Build Instructions =

1. Clone the repository: `git clone https://github.com/chatprojects-com/chatprojects.git`
2. Install dependencies: `npm install`
3. Build for production: `npm run build`

The source files are located in `assets/src/` and compile to `assets/dist/`.

= Technical Notes =

**Streaming via WordPress HTTP API:**
This plugin uses the WordPress HTTP API (`wp_remote_post`) for AI provider streaming. For real-time SSE chunk handling, it leverages the `http_api_curl` action hook to attach a `CURLOPT_WRITEFUNCTION` callback only when the WordPress HTTP API selects the cURL transport. This preserves WordPress compatibility (proxy settings, transport fallback, and security hooks) while still enabling low-latency streaming.

If cURL is not available, the HTTP API will fall back to other transports and the request will still complete (though streaming callbacks are only available when cURL is the active transport).

**PHP Configuration:**
SSE streaming requires specific PHP settings (disabled output buffering, compression off) which are set only within the streaming endpoint functions, not globally.
