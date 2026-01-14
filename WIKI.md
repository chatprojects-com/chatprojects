# ChatProjects Wiki

> AI-powered project management with multi-provider chat for WordPress

[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPLv2-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

---

## Table of Contents

- [Home](#home)
- [Installation](#installation)
- [Configuration](#configuration)
- [Supported Providers](#supported-providers)
- [Usage Guide](#usage-guide)
- [Projects & Files](#projects--files)
- [Security](#security)
- [Troubleshooting](#troubleshooting)
- [FAQ](#faq)
- [Development](#development)

---

# Home

**ChatProjects** brings powerful AI chat capabilities directly to your WordPress site. Chat with multiple AI providers using your own API keys, manage projects with intelligent file search, and enjoy a modern, responsive interface.

## Key Features

- **Multi-Provider Chat** - Switch between GPT, Claude, Gemini, DeepSeek, and 100+ models via OpenRouter
- **Project Management** - Create projects with OpenAI's vector store for intelligent file search
- **File Upload** - Upload documents (PDF, DOC, TXT, etc.) for AI-powered analysis
- **Custom Instructions** - Set custom assistant personas for each project
- **Real-Time Streaming** - See AI responses as they're generated
- **Modern Interface** - Clean, responsive design with dark mode support
- **Privacy First** - Your API keys stay encrypted on your server
- **Embeddable** - Use shortcodes to add AI chat to any page

## Quick Links

| Page | Description |
|------|-------------|
| [Installation](#installation) | Get started in 5 minutes |
| [Configuration](#configuration) | Set up your API keys |
| [Usage Guide](#usage-guide) | Learn the interface |
| [Supported Providers](#supported-providers) | See available AI models |

---

# Installation

## Requirements

| Requirement | Minimum | Recommended |
|-------------|---------|-------------|
| WordPress | 5.8+ | 6.0+ |
| PHP | 7.4+ | 8.0+ |
| MySQL | 5.6+ | 8.0+ |
| Memory Limit | 128MB | 256MB |

**Required PHP Extensions:**
- OpenSSL (for API key encryption)
- cURL (for API communication)
- JSON (for data handling)

## Installation Methods

### Method 1: WordPress Plugin Directory (Recommended)

1. Go to **Plugins > Add New** in WordPress admin
2. Search for "ChatProjects"
3. Click **Install Now**
4. Click **Activate**

### Method 2: Manual Upload

1. Download the plugin ZIP file
2. Go to **Plugins > Add New > Upload Plugin**
3. Choose the ZIP file and click **Install Now**
4. Click **Activate**

### Method 3: FTP/SFTP

1. Extract the ZIP file
2. Upload the `chatprojects` folder to `/wp-content/plugins/`
3. Go to **Plugins** in WordPress admin
4. Find ChatProjects and click **Activate**

## Post-Installation

1. Navigate to **ChatProjects > Settings**
2. Add at least one API key (see [Configuration](#configuration))
3. Create a page and add the shortcode: `[chatprojects_main]`
4. Start chatting!

---

# Configuration

## Accessing Settings

Navigate to **WordPress Admin > ChatProjects > Settings**

## API Key Setup

You need at least one API key to use ChatProjects. Each provider offers different models and capabilities.

### OpenAI

**Best for:** GPT models, file search with vector stores

1. Visit [platform.openai.com](https://platform.openai.com/)
2. Sign up or log in
3. Go to **API Keys** section
4. Click **Create new secret key**
5. Copy the key (starts with `sk-`)
6. Paste into ChatProjects Settings > OpenAI API Key

**Note:** OpenAI is required for project file search functionality.

### Anthropic (Claude)

**Best for:** Long context conversations, coding assistance

1. Visit [console.anthropic.com](https://console.anthropic.com/)
2. Sign up or log in
3. Go to **API Keys**
4. Create a new key
5. Copy and paste into ChatProjects Settings

### Google Gemini

**Best for:** Multimodal capabilities, Google integration

1. Visit [ai.google.dev](https://ai.google.dev/)
2. Sign up with Google account
3. Get your API key from the console
4. Copy and paste into ChatProjects Settings

### Chutes (DeepSeek)

**Best for:** Cost-effective reasoning models

1. Visit [chutes.ai](https://chutes.ai/)
2. Sign up or log in
3. Get your API key
4. Copy and paste into ChatProjects Settings

### OpenRouter

**Best for:** Access to 100+ models from one API key

1. Visit [openrouter.ai](https://openrouter.ai/)
2. Sign up or log in
3. Go to **Keys** section
4. Create a new key
5. Copy and paste into ChatProjects Settings

## Default Settings

| Setting | Description |
|---------|-------------|
| Default Provider | Which AI provider to use by default |
| Default Model | Which model to select by default |
| Max File Size | Maximum upload size (1-512MB) |
| Allowed File Types | Which file extensions can be uploaded |

---

# Supported Providers

## OpenAI

| Model | Description | Context |
|-------|-------------|---------|
| GPT-5.2 | Latest flagship model | 128K |
| GPT-5.2 Codex | Optimized for code | 128K |
| GPT-4o | Multimodal (text + vision) | 128K |
| GPT-4o-mini | Fast and affordable | 128K |
| GPT-4 Turbo | High capability | 128K |
| o1 Preview | Advanced reasoning | 128K |
| o1 Mini | Fast reasoning | 128K |

## Anthropic (Claude)

| Model | Description | Context |
|-------|-------------|---------|
| Claude Sonnet 4 | Latest balanced model | 200K |
| Claude 3.5 Sonnet | Previous generation | 200K |
| Claude 3.5 Haiku | Fast responses | 200K |
| Claude 3 Opus | Most capable | 200K |

## Google Gemini

| Model | Description | Context |
|-------|-------------|---------|
| Gemini 3 Pro Preview | Latest flagship | 1M |
| Gemini 2.5 Pro | Previous flagship | 1M |
| Gemini 2.0 Flash | Fast responses | 1M |
| Gemini 1.5 Pro | Previous generation | 1M |
| Gemini 1.5 Flash | Budget option | 1M |

## Chutes (DeepSeek)

| Model | Description | Context |
|-------|-------------|---------|
| DeepSeek V3 | Latest general model | 64K |
| DeepSeek R1 | Reasoning model | 64K |

## OpenRouter

Access 100+ models from various providers including:
- Meta Llama 3.1 (8B, 70B, 405B)
- Mistral (7B, Mixtral)
- Qwen models
- And many more

Visit [openrouter.ai/models](https://openrouter.ai/models) for the full list.

---

# Usage Guide

## Embedding ChatProjects

Add the chat interface to any WordPress page using shortcodes:

### Basic Usage

```
[chatprojects_main]
```

### With Options

```
[chatprojects_main default_tab="chat" height="80vh"]
```

| Option | Values | Description |
|--------|--------|-------------|
| `default_tab` | `chat`, `projects` | Which tab opens first |
| `height` | CSS value | Interface height (e.g., `600px`, `80vh`) |

### Examples

```
[chatprojects_main default_tab="projects"]
[chatprojects_main height="600px"]
[chatprojects_main default_tab="chat" height="90vh"]
```

## Chat Interface

### Starting a Chat

1. Select a provider from the dropdown (OpenAI, Claude, etc.)
2. Select a model
3. Type your message in the input box
4. Press Enter or click Send

### Provider Switching

Switch providers mid-conversation:
1. Click the provider dropdown
2. Select a new provider
3. Continue chatting (history is preserved)

### Image Upload (Vision Models)

For models that support vision (GPT-4o, Claude 3, Gemini):
1. Click the attachment icon
2. Select an image
3. Add your question about the image
4. Send

## Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| `Enter` | Send message |
| `Shift + Enter` | New line in message |
| `Ctrl + /` | Toggle dark mode |
| `Escape` | Close modals |

---

# Projects & Files

## What Are Projects?

Projects are workspaces that combine:
- Custom AI instructions (persona/behavior)
- Uploaded files for AI-powered search
- OpenAI's vector store for semantic search

## Creating a Project

1. Go to the **Projects** tab
2. Click **New Project**
3. Enter a project name
4. Add custom instructions (optional)
5. Click **Create**

## Custom Instructions

Set how the AI should behave for this project:

```
You are a helpful assistant for [Company Name].
You specialize in [topic].
Always respond in a professional tone.
When referencing uploaded documents, cite the source.
```

## Uploading Files

### Supported File Types

| Category | Extensions |
|----------|------------|
| Documents | PDF, DOC, DOCX, TXT, MD |
| Data | CSV, JSON, XML |
| Code | JS, PY, PHP, CSS, HTML, Java, C++ |
| Spreadsheets | XLS, XLSX |

### Upload Process

1. Open a project
2. Click **Upload Files**
3. Select files (or drag & drop)
4. Wait for processing (files are indexed)
5. Start asking questions about your files

### File Size Limits

- Default: 50MB per file
- Maximum: 512MB (configurable in Settings)

## Vector Store Search

When you chat in a project with uploaded files:
1. Your question is analyzed
2. Relevant file sections are found automatically
3. The AI uses these sections to answer
4. Sources are cited in responses

---

# Security

## API Key Protection

### Encryption
- **Algorithm:** AES-256-CBC
- **Key Derivation:** WordPress AUTH_KEY
- **Storage:** WordPress options table (encrypted)

Your API keys are:
- Encrypted before storage
- Decrypted only when making API calls
- Never exposed in browser/frontend code
- Never transmitted to chatprojects.com

## Data Transmission

| Data | When Sent | Destination |
|------|-----------|-------------|
| Chat messages | User clicks Send | Selected AI provider |
| Uploaded files | User uploads to project | OpenAI (vector store) |
| System prompts | With each chat request | Selected AI provider |

**No data is sent automatically** - transmission only occurs on explicit user action.

## WordPress Security

| Feature | Implementation |
|---------|----------------|
| Nonce Verification | All AJAX requests verified |
| Capability Checks | User permissions validated |
| Input Sanitization | All input sanitized |
| Output Escaping | All output escaped |
| ABSPATH Protection | All PHP files protected |

## File Upload Security

- File type validation (whitelist)
- File size limits enforced
- MIME type verification
- Secure upload handling

---

# Troubleshooting

## Common Issues

### "Invalid API Key" Error

**Cause:** API key is incorrect or expired

**Solution:**
1. Go to ChatProjects > Settings
2. Re-enter your API key
3. Click Save
4. Try the "Test Connection" button

### Streaming Not Working

**Cause:** Server configuration blocking SSE

**Solution:**
1. Check if output buffering is enabled
2. Verify no caching plugins are interfering
3. Check server timeout settings
4. Try a different browser

### File Upload Fails

**Cause:** File too large or wrong type

**Solution:**
1. Check file size (default max: 50MB)
2. Verify file type is supported
3. Check PHP upload_max_filesize setting
4. Check WordPress media upload limits

### "Permission Denied" Error

**Cause:** User lacks required capabilities

**Solution:**
1. Verify user is logged in
2. Check user role has access
3. Verify project permissions

### Chat History Not Loading

**Cause:** Database or caching issue

**Solution:**
1. Clear browser cache
2. Clear any WordPress caching
3. Check database connection
4. Verify tables exist (deactivate/reactivate plugin)

## Debug Mode

Enable WordPress debug mode for detailed errors:

```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check `/wp-content/debug.log` for error details.

## Getting Help

- **Free Version:** [WordPress.org Support Forum](https://wordpress.org/support/plugin/chatprojects/)
- **Pro Version:** support@chatprojects.com
- **Documentation:** [chatprojects.com/docs](https://chatprojects.com/docs/)

---

# FAQ

## General Questions

### Do I need all 5 API keys?

**No.** You only need one API key to start. Add more providers as needed.

### Where are my API keys stored?

API keys are encrypted with AES-256-CBC and stored in your WordPress database. They never leave your server.

### Can multiple users access ChatProjects?

Yes. All logged-in users can access the chat interface. Project access can be configured in settings.

### Is there a message limit?

No artificial limits. Your usage is limited only by your API provider's rate limits and your API credits.

## Files & Projects

### What file types can I upload?

PDF, DOC, DOCX, TXT, MD, CSV, JSON, XML, HTML, CSS, JS, PY, PHP, XLS, XLSX

### What's the maximum file size?

Default is 50MB. Can be increased up to 512MB in Settings.

### How does file search work?

Files are processed into a vector store. When you ask a question, relevant sections are automatically retrieved and provided to the AI for context.

### Are my files sent to AI providers?

Only to OpenAI for vector store indexing. Other providers don't receive file contents unless you paste them in chat.

## Technical Questions

### Does this work with caching plugins?

Yes, but you may need to exclude the chat page from caching for real-time features to work properly.

### Can I use this with multisite?

Yes. Install and activate on each site where needed.

### Is this GDPR compliant?

The plugin stores data locally in WordPress. For full compliance, review the privacy policies of your chosen AI providers.

---

# Development

## Source Code

Full source code is available at:
**https://github.com/chatprojects-com/chatprojects**

## Project Structure

```
chatprojects/
├── assets/
│   ├── src/           # Source files (JS, CSS)
│   └── dist/          # Compiled files
├── includes/          # PHP classes
│   ├── providers/     # AI provider classes
│   └── ...
├── public/            # Frontend templates
├── admin/             # Admin templates
├── documentation/     # Guides
├── package.json       # Build dependencies
├── vite.config.js     # Build configuration
└── chatprojects.php   # Main plugin file
```

## Build Instructions

```bash
# Clone the repository
git clone https://github.com/chatprojects-com/chatprojects.git

# Install dependencies
npm install

# Build for production
npm run build

# Watch for changes (development)
npm run watch
```

## Architecture

### Core Classes

| Class | Purpose |
|-------|---------|
| `ChatProjects` | Main orchestrator |
| `API_Handler` | OpenAI API wrapper |
| `Security` | Encryption & validation |
| `Access` | Permission control |
| `Message_Store` | Chat history storage |
| `Vector_Store` | File management |
| `Project_Manager` | Project CRUD |

### Provider Interface

All AI providers implement `AI_Provider_Interface`:

```php
interface AI_Provider_Interface {
    public function run_completion($messages, $model, $options);
    public function stream_completion($messages, $model, $callback, $options);
    public function get_available_models();
    public function validate_api_key($api_key);
}
```

## Third-Party Libraries

| Library | Version | License |
|---------|---------|---------|
| Alpine.js | 3.x | MIT |
| highlight.js | 11.x | BSD-3-Clause |
| marked | 14.x | MIT |

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

Please follow WordPress coding standards.

---

## License

ChatProjects is licensed under GPLv2 or later.

---

**Website:** [chatprojects.com](https://chatprojects.com)
**Support:** [WordPress.org Forum](https://wordpress.org/support/plugin/chatprojects/)
**GitHub:** [chatprojects-com/chatprojects](https://github.com/chatprojects-com/chatprojects)
