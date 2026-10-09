=== ChatProjects ===
Contributors: chatprojects
Donate link: https://chatprojects.com/
Tags: ai, chatbot, openai, chatgpt, knowledge base
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Chat with your documents and your site's content in WordPress, using your own OpenAI, Claude, Gemini, DeepSeek or OpenRouter API keys.

== Description ==

**ChatProjects** brings AI chat into WordPress, grounded in your own documents. Create a project, upload your policies, manuals or notes, and ask questions. Answers come from your files, and each one lists the files it used.

You use your own API keys. Nothing goes through our servers, and there's no account to create.

= What you can do =

* **Chat with your documents.** Each project has its own files, instructions and chat history. Answers name the files they came from.
* **Add a chat widget to your website.** Visitors ask questions and get answers from a project's documents. Turn it on per project, then show it on every page or place it with a shortcode.
* **Answer from your posts and pages (Auto-RAG).** Index your published content into a project. Edited posts are re-indexed, and posts you unpublish or delete are removed.
* **Chat with the model you prefer.** Switch between OpenAI GPT-5.6, Anthropic Claude Opus 5.5, Google Gemini 3.8, DeepSeek V4 and hundreds of models through OpenRouter, all in one chat screen.
* **Keep projects private.** Each project belongs to the person who created it, and administrators can see all of them. Give team members the Projects User role.
* **Use it your way.** Open the full-screen app at `/chatprojects/`, or embed it on any page with a shortcode. Light and dark mode included.

= What you need =

* An **OpenAI API key** for projects, document chat, the website widget and Auto-RAG. File search runs on OpenAI vector stores.
* Optionally, keys for Anthropic, Google Gemini, Chutes or OpenRouter, to use their models in general chat.
* WordPress 6.6 or later and PHP 8.0 or later.

The plugin is free. You pay your AI provider directly for what you use.

= Supported AI providers =

1. **OpenAI**: GPT-5.6 Sol, Terra and Luna, GPT-5.5, GPT-5.4, GPT-5.4 Mini and Nano
2. **Anthropic**: Claude Opus 5.5, Sonnet 5.5, Fable 5.1, Opus 5, Sonnet 5, Opus 4.8, Sonnet 4.6, Haiku 4.5
3. **Google Gemini**: Gemini 3.8 Flash, 3.7 Flash, 3.5 Flash, 3.5 Flash Lite, 3.1 Pro (preview)
4. **Chutes**: DeepSeek V4 Flash, DeepSeek V3.2 and the other models Chutes hosts
5. **OpenRouter**: hundreds of models from many providers

Developers can add custom or fine-tuned models with the `chatprojects_models` filter.

= Shortcodes =

* `[chatprojects_main]` shows the full app on any page. Add `default_tab="chat"` or `default_tab="settings"` to choose what its main button opens (projects by default).
* `[chatprojects_widget project="123"]` shows the website chat widget for project 123.

= ChatProjects Pro =

[ChatProjects Pro](https://chatprojects.com/) adds team sharing, a prompt library, side-by-side model comparison, image generation, transcription and usage analytics. Everything in this plugin is free to use; Pro adds features, it doesn't unlock this one.

== Installation ==

1. In WordPress, go to **Plugins > Add New**, search for **ChatProjects**, then install and activate it.
2. Go to **ChatProjects > Settings** and add your OpenAI API key, plus keys for any other providers you want to use.
3. Open `https://yourdomain.com/chatprojects/`, or add `[chatprojects_main]` to any page.
4. Create a project, upload a few documents, and start asking questions.

**To add the website chat widget:** edit the project under **ChatProjects > Projects**, tick **Allow public chat widget** and save. Then either turn on the widget in **ChatProjects > Settings > Chat Widget** to show it on every page, or add the shortcode the project shows you to a page.

**To answer from your posts and pages:** edit the project and click **Index My Site** in the **Content Index (Auto-RAG)** box.

= Getting API keys =

* **OpenAI:** [platform.openai.com](https://platform.openai.com/) > API keys > Create new secret key
* **Anthropic:** [console.anthropic.com](https://console.anthropic.com/) > API keys > Create key
* **Google Gemini:** [ai.google.dev](https://ai.google.dev/) > Get API key
* **Chutes:** [chutes.ai](https://chutes.ai/) > API keys
* **OpenRouter:** [openrouter.ai](https://openrouter.ai/) > Keys > Create key

== Frequently Asked Questions ==

= Do I need all five API keys? =

No. One key is enough to start chatting. Projects, document chat, the website widget and Auto-RAG need an OpenAI key, because they use OpenAI's file search.

= What does it cost? =

The plugin is free. AI usage is billed by each provider to your own account. OpenAI also charges for storing your project files in vector stores.

= How do I stop the website widget from running up my API bill? =

The widget limits how many messages each visitor can send, and you can set a site-wide daily limit in **ChatProjects > Settings > Chat Widget**. It only answers for projects where you've ticked **Allow public chat widget**.

= Where are my API keys stored? =

Encrypted in your WordPress database. They are only sent to the AI provider they belong to, never to us.

= Is my data used to train AI models? =

ChatProjects sends nothing to us. What each AI provider does with API data is set by its own policies, linked under **External services** below.

= Who can see a project? =

Each project is private to the user who created it, and administrators can see all projects. Users need the Projects User role (or Author, Editor or Administrator) to use ChatProjects. To let site visitors use a project's knowledge, allow the chat widget for it.

= What file types can I upload? =

PDF, DOC, DOCX, TXT, MD, CSV, JSON, XML, CSS, PY, JAVA and CPP by default. Administrators can change the list in Settings. Executable and script types (such as PHP, JS, HTML and SVG) are always refused.

= Is there a file size limit? =

Yes. The default is 50 MB per file, adjustable from 1 to 512 MB in Settings.

= Does it work with ChatProjects Pro? =

Yes. Pro includes everything in this plugin. If both are installed, only Pro runs, and your projects, chats and API keys carry over.

= Can I use this on a client site? =

Yes. ChatProjects is GPL licensed, so you can use it on any WordPress site.

= How do I get support? =

Use the WordPress.org support forum, or email support@chatprojects.com.

== Screenshots ==

1. Projects: each project keeps its own documents, instructions and chats.
2. Ask a question and get an answer from your documents, with the files it used.
3. Upload files from your computer or the Media Library.
4. General chat with Claude, GPT, Gemini, DeepSeek or OpenRouter models, using your own API keys.
5. The website chat widget answers visitors from a project's documents.
6. Auto-RAG indexes your posts and pages into a project and keeps them in sync.
7. Settings: add your API keys for each provider.

== Changelog ==

= 1.3.0 =
* **Security:** A user could delete OpenAI files that didn't belong to their project; deletion is now limited to the project's own files, and file IDs are no longer sent to widget visitors.
* **Security:** General chat streaming now checks that the chat belongs to you before saving anything to it.
* **Security:** The projects page no longer includes your full user record (including the password hash) in the page source.
* **Security:** All chat, project and file actions now require a ChatProjects role (Projects User, Author, Editor or Administrator), matching the pages themselves.
* **Security:** Changing your email from ChatProjects settings now sends a confirmation link, like the WordPress profile screen.
* **Security:** Only the uploader (or someone who can edit it) can import a Media Library file into a project.
* **Security:** API keys use authenticated encryption (libsodium); existing keys are re-encrypted automatically. Keys that can't be decrypted are no longer deleted.
* **Security:** Rate limits on every AI request, plus a site-wide daily limit for the public widget (Settings > Chat Widget).
* **Fixed:** Deleting the free plugin while ChatProjects Pro is installed no longer removes Pro's data.
* **Fixed:** Project chat replies could stop arriving before the final events on PHP-FPM hosts, losing automatic chat titles.
* **Fixed:** The chat widget's stylesheet was missing from the released plugin.
* **Fixed:** Widget sessions expired early on sites in timezones ahead of UTC.
* **Fixed:** General chat now uses the chat's instructions when streaming.
* **Fixed:** Conversations recover automatically when OpenAI no longer has the previous response; history is sent when there is nothing to continue from.
* **Fixed:** Real error messages from AI providers are shown instead of "HTTP error: 400"; replies cut off by length or content filters are marked as incomplete.
* **Fixed:** Chutes now streams and receives the system prompt; Chutes and OpenRouter use each model's own temperature and length defaults.
* **Fixed:** Auto-RAG removes posts from the index when they are unpublished, made private, password-protected or deleted, and re-indexes edited posts.
* **Fixed:** Saving one settings tab no longer resets the settings on the other tabs.
* **Fixed:** Project chat shows its sources again; a failed reply no longer stays stuck; Stop now stops the AI request; reopened chats show the latest messages.
* **Fixed:** Changing your email from ChatProjects settings now completes from the confirmation link; the theme choice is remembered.
* **Fixed:** Installing ChatProjects while ChatProjects Pro is active no longer causes a fatal error.
* **Changed:** The chat widget stores its data in new tables (moved automatically on update).
* **Models:** Claude Opus 5.5 (new default) and Claude Sonnet 5.5. Claude requests opt into Anthropic's server-side fallback when a safety classifier declines.
* **Models:** Images are only sent to models that can read them.
* **Compliance:** The widget's "Powered by ChatProjects" link is now opt-in. Google Fonts are no longer loaded from Google's servers. DOMPurify's licence is included.
* **Changed:** Auto-RAG no longer stops at 100 posts per project.
* **Fixed:** The upload panel lists the file types you can actually upload.
* **Fixed:** Small display fixes on the projects list, the API key settings and the empty chat screen.
* Removed the unused REST stream endpoint, Pro-only code paths and unused source files.

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

= 1.3.0 =
Security release: fixes file deletion and chat access checks, and stops deleting ChatProjects Pro data when the free plugin is removed. Update recommended.

= 1.2.0 =
Retired AI models are replaced automatically. Project access is now limited to the project author and administrators. Requires WordPress 6.6+ and PHP 8.0+.

= 1.1.0 =
**IMPORTANT URL CHANGE:** Plugin URLs have changed to prevent conflicts. Old URLs (/projects/, /settings/, /pro-chat/) will automatically redirect to new URLs (/chatprojects/, /cp-settings/, /cp-chat/). Update your bookmarks. If you experience issues, go to Settings > Permalinks and click Save.

= 1.0.0 =
Initial release. Add your API keys and start chatting with AI!

== Privacy Policy ==

ChatProjects stores your API keys encrypted in your WordPress database. The plugin connects directly to AI provider APIs (OpenAI, Anthropic, Google, Chutes, OpenRouter) using your own API keys. No data is sent to our servers.

For more information, see our [Privacy Policy](https://chatprojects.com/privacy/).

== External services ==

This plugin connects to the external AI services listed below, using API keys the site owner provides. Nothing is sent until an administrator adds a key for a service.

= Data Transmitted =

* **Chat Messages:** Text (and attached images) a logged-in user sends is sent to the AI provider they selected, together with recent conversation history
* **Website Widget Messages:** Messages typed by site visitors into the `[chatprojects_widget]` chat are sent to OpenAI with the project's instructions
* **Uploaded Files:** File contents are sent to OpenAI when users upload or import a file into a project
* **Auto-RAG Content:** When an administrator starts indexing, the title, URL, date, author, categories and text of published, public posts and pages are uploaded to the project's OpenAI vector store in background batches. Edited posts are re-uploaded, and posts that are unpublished or deleted are removed
* **System Instructions:** Project or chat instructions are included with chat requests
* **Chat Titles:** The first message and reply of a chat are sent to OpenAI to generate a short title

= When Data Is Sent =

* When a user or widget visitor sends a chat message
* When a file is uploaded or imported into a project (OpenAI only)
* While Auto-RAG indexing runs, and when an indexed post is edited (OpenAI only)

= API Keys =

* **Site owners supply their own API keys** - this plugin does not provide access to any AI service
* Keys are encrypted (libsodium secretbox) and stored locally in the WordPress database
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

= DOMPurify =
* Version: 3.x
* License: Apache-2.0 (dual licensed Apache-2.0 / MPL-2.0)
* Source: https://github.com/cure53/DOMPurify
* License file: licenses/DOMPURIFY.txt

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
SSE streaming clears output buffers and turns off output compression for the streaming request only, not globally.
