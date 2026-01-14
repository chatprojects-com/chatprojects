=== ChatProjects ===
Contributors: gptadviser, chatprojects
Donate link: https://chatprojects.com/
Tags: ai, chatgpt, openai, chatbot, project management
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered project management with multi-provider chat. Vector store file search. Chat with GPT, Claude, Gemini and more.

== Description ==

**ChatProjects** is a powerful WordPress plugin that brings AI-powered project management and chat capabilities directly to your website. Features vector store chat with OpenAI's Responses API for intelligent file search. Use your own API keys to chat with multiple AI providers including OpenAI (GPT-5.2), Anthropic (Claude), Google (Gemini 3 Pro), Chutes (DeepSeek), and OpenRouter.

= Key Features =

* **Multi-Provider Chat** - Chat with GPT-5.2, Claude, Gemini 3 Pro, DeepSeek, and 100+ models via OpenRouter
* **Project Management** - Create projects with OpenAI's file search capability
* **File Upload** - Upload documents (PDF, TXT, DOC) to your project's vector store
* **Custom Instructions** - Set custom assistant instructions for each project
* **Shared Chatbots** - Create project-based chatbots that can be shared with your team
* **Modern Interface** - Clean, responsive chat interface with dark mode support
* **Embeddable** - Use shortcodes to embed the full application on any page
* **Privacy First** - Your API keys stay on your server, not ours

= Supported AI Providers =

1. **OpenAI** - GPT-5.2, GPT-5.1, GPT-4o, GPT-4o-mini, GPT-4-turbo, GPT-3.5-turbo
2. **Anthropic** - Claude Sonnet 4, Claude 3.5 Sonnet, Claude 3 Haiku
3. **Google Gemini** - Gemini 3 Pro, Gemini 2.5 Pro, Gemini 2.0 Flash
4. **Chutes** - DeepSeek V3, DeepSeek R1, Qwen, Mistral, Llama
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
4. Access the interface on https://yourdomain.com/projects/ or Create a page and add `[chatprojects_main]` shortcode
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

= Do I need all 4 API keys? =

No! You only need one API key to start chatting. Add more providers as needed.

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

1. Main chat interface with provider selection
2. Project management dashboard
3. File upload and vector store management
4. Settings page with API key configuration
5. Dark mode support
6. Mobile responsive design

== Changelog ==

= 1.0.0 =
* Initial release
* Multi-provider chat (OpenAI, Anthropic, Gemini, Chutes)
* Project management with OpenAI Assistants
* File upload to vector stores
* Modern chat interface
* Dark/Light theme support
* Shortcode embedding [chatprojects_main]

== Upgrade Notice ==

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

= markdown-it =
* Version: 14.x
* License: MIT
* Source: https://github.com/markdown-it/markdown-it
* License file: licenses/MARKDOWN-IT.txt

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

**cURL Usage for SSE Streaming:**
This plugin uses cURL directly (instead of WordPress HTTP API) for AI provider streaming responses. This is necessary because:

1. Server-Sent Events (SSE) require real-time chunk-by-chunk data processing
2. WordPress HTTP API (`wp_remote_*`) waits for the complete response before returning
3. cURL's `CURLOPT_WRITEFUNCTION` callback enables processing each chunk as it arrives
4. This provides users with real-time streaming chat responses instead of waiting for complete API responses

The WordPress HTTP API does not support streaming callbacks, making cURL the only viable option for this functionality.

**PHP Configuration:**
SSE streaming requires specific PHP settings (disabled output buffering, compression off) which are set only within the streaming endpoint functions, not globally.
