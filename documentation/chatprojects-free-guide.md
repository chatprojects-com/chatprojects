# ChatProjects
## Complete User Guide & Documentation

---

**Version:** 1.0.0
**Website:** https://chatprojects.com
**Support:** https://wordpress.org/support/plugin/chatprojects/
**License:** GPLv2 or later

---

# Own Your Chat

ChatProjects is a powerful WordPress plugin that brings AI-powered project management and chat capabilities directly to your website. Use your own API keys to chat with multiple AI providers including OpenAI (GPT-4/GPT-5), Anthropic (Claude), Google (Gemini), Chutes (DeepSeek), and OpenRouter (100+ models).

**Your keys. Your data. Your server.**

---

\pagebreak

# Table of Contents

1. Introduction & Overview
2. System Requirements
3. Installation Guide
4. Configuration & Settings
5. AI Providers & Models Reference
6. Getting Started Tutorial
7. Using the Chat Interface
8. Project Management
9. File Management & Vector Stores
10. Shortcode Reference
11. Security & Privacy
12. Free vs Pro Comparison
13. Third-Party Services
14. Third-Party Libraries
15. Troubleshooting & FAQ
16. Support Resources
17. Changelog
18. Credits & License

---

\pagebreak

# 1. Introduction & Overview

## What is ChatProjects?

ChatProjects is a WordPress plugin that enables AI-powered conversations directly on your website. Unlike SaaS chat tools that require monthly subscriptions and store your data on external servers, ChatProjects lets you:

- **Use your own API keys** - Pay only for what you use, directly to AI providers
- **Keep data on your server** - All conversations stored in your WordPress database
- **Chat with multiple AI providers** - Switch between GPT-4, Claude, Gemini, and more
- **Create knowledge-based projects** - Upload documents and chat with their contents

## Key Features

| Feature | Description |
|---------|-------------|
| **Multi-Provider Chat** | Chat with GPT-4, Claude, Gemini, DeepSeek, and 100+ models via OpenRouter |
| **Project Management** | Create projects with OpenAI Vector Store integration for document search |
| **File Upload** | Upload PDFs, Word docs, text files, code files (up to 512MB configurable) |
| **File Search** | AI searches your uploaded documents to provide contextual answers |
| **Modern Interface** | Clean, responsive design with dark mode support |
| **Privacy First** | API keys encrypted with AES-256, stored locally on your server |
| **Easy Embedding** | Simple shortcode: `[chatprojects_main]` |
| **Chat History** | All conversations saved and searchable |

## How It Works

1. **Add API Keys** - Enter your API keys in the WordPress admin settings
2. **Create a Page** - Add the ChatProjects shortcode to any WordPress page
3. **Create Projects** - Build knowledge bases by uploading documents
4. **Start Chatting** - Ask questions and get AI-powered responses based on your documents

---

\pagebreak

# 2. System Requirements

## Minimum Requirements

| Requirement | Version |
|-------------|---------|
| WordPress | 5.8 or higher |
| PHP | 7.4 or higher |
| MySQL | 5.6 or higher |

## Server Requirements

- **OpenSSL Extension** - Required for API key encryption
- **cURL Extension** - Required for API communication
- **Memory Limit** - 128MB minimum (256MB recommended)
- **Max Upload Size** - Should match your desired file upload limit

## Browser Support

ChatProjects works in all modern browsers:
- Chrome 80+
- Firefox 75+
- Safari 13+
- Edge 80+

## API Key Requirements

You need **at least one** API key to use ChatProjects:

| Provider | Required For |
|----------|-------------|
| OpenAI | Projects, Vector Stores, File Search |
| Anthropic | Chat with Claude models (optional) |
| Google Gemini | Chat with Gemini models (optional) |
| Chutes | Chat with DeepSeek models (optional) |
| OpenRouter | Chat with 100+ models (optional) |

> **Note:** OpenAI is required for project features (Vector Stores and file search). Other providers are optional and only needed if you want to use their models for chat.

---

\pagebreak

# 3. Installation Guide

## Method 1: WordPress Plugin Directory

1. Go to **Plugins > Add New** in your WordPress admin
2. Search for "ChatProjects"
3. Click **Install Now**
4. Click **Activate**

## Method 2: Manual Upload

1. Download the plugin ZIP file from WordPress.org
2. Go to **Plugins > Add New > Upload Plugin**
3. Choose the ZIP file and click **Install Now**
4. Click **Activate**

## Method 3: FTP Upload

1. Download and extract the plugin ZIP file
2. Upload the `chatprojects` folder to `/wp-content/plugins/`
3. Go to **Plugins** in WordPress admin
4. Click **Activate** next to ChatProjects

## Post-Installation Checklist

After activating the plugin:

- [ ] Go to ChatProjects > Settings
- [ ] Add at least one API key
- [ ] Configure default provider and model
- [ ] Create a page with the shortcode
- [ ] Test the chat interface

---

\pagebreak

# 4. Configuration & Settings

## Accessing Settings

Navigate to **WordPress Admin > ChatProjects > Settings**

## API Keys Section

All API keys are encrypted using AES-256 before storage in your database.

### OpenAI API Key

**Required for:** Projects, Vector Stores, File Search, GPT models

**How to get your key:**
1. Visit https://platform.openai.com/
2. Sign up or log in to your account
3. Go to **API Keys** section
4. Click **Create new secret key**
5. Copy the key (starts with `sk-`)

**Pricing:** Pay-per-use based on tokens. See https://openai.com/pricing

### Anthropic API Key

**Required for:** Claude models

**How to get your key:**
1. Visit https://console.anthropic.com/
2. Sign up or log in
3. Go to **API Keys**
4. Create a new key (starts with `sk-ant-`)

**Pricing:** Pay-per-use based on tokens. See https://anthropic.com/pricing

### Google Gemini API Key

**Required for:** Gemini models

**How to get your key:**
1. Visit https://ai.google.dev/
2. Sign up or log in with Google account
3. Click **Get API Key**
4. Create key in new or existing project (starts with `AIza`)

**Pricing:** Free tier available. See https://ai.google.dev/pricing

### Chutes API Key

**Required for:** DeepSeek models

**How to get your key:**
1. Visit https://chutes.ai/
2. Sign up or log in
3. Navigate to API settings
4. Generate your API key (starts with `cpat_` or `cpk_`)

### OpenRouter API Key

**Required for:** 100+ models from multiple providers

**How to get your key:**
1. Visit https://openrouter.ai/
2. Sign up or log in
3. Go to **Keys** section
4. Create new key (starts with `sk-or-`)

**Pricing:** Pay-per-use, varies by model. See https://openrouter.ai/pricing

---

## General Chat Settings

### Default Chat Provider

Select the AI provider used by default when starting new chats:
- OpenAI
- Anthropic
- Gemini
- Chutes
- OpenRouter

### Default Chat Model

Select the default model for the chosen provider. Available models depend on which provider is selected.

---

## Assistant Settings

### Assistant Instructions

Enter custom system instructions for the OpenAI Assistant used in project chats. This defines how the AI should behave when answering questions about your documents.

**Example:**
```
You are a helpful product support assistant. Answer questions based on the uploaded documentation. Be concise and cite specific documents when possible.
```

### Default Model for Projects

Select the default OpenAI model used for project chats with file search:
- GPT-4o (recommended)
- GPT-4o-mini (faster, cheaper)
- GPT-4-turbo
- GPT-3.5-turbo

---

## File Upload Settings

### Maximum File Size

Set the maximum file size for uploads in megabytes (MB).

- **Default:** 50 MB
- **Maximum:** 512 MB
- **Note:** Also limited by your server's `upload_max_filesize` and `post_max_size` PHP settings

### Allowed File Types

Select which file types users can upload:

**Documents:**
- PDF (.pdf)
- Microsoft Word (.doc, .docx)
- Text (.txt)
- Markdown (.md)

**Data:**
- CSV (.csv)
- JSON (.json)
- XML (.xml)
- HTML (.html)

**Code:**
- JavaScript (.js)
- Python (.py)
- PHP (.php)
- CSS (.css)
- Java (.java)
- C++ (.cpp)

**Spreadsheets:**
- Excel (.xls, .xlsx)

---

\pagebreak

# 5. AI Providers & Models Reference

## OpenAI Models

| Model | Description | Best For |
|-------|-------------|----------|
| **GPT-5.1 Chat** | Latest flagship model | Complex reasoning, analysis |
| **GPT-5.1** | Advanced reasoning | General purpose, coding |
| **GPT-5.1 Codex** | Code-specialized | Programming tasks |
| **GPT-5.1 Codex Mini** | Faster code model | Quick code tasks |
| **o1 Preview** | Reasoning model | Complex problem solving |
| **o1 Mini** | Faster reasoning | Quick analysis |
| **GPT-4o** | Multimodal flagship | General purpose, images |
| **GPT-4o-mini** | Fast and affordable | Everyday tasks |
| **GPT-4 Turbo** | High performance | Complex tasks |
| **GPT-4** | Original GPT-4 | Reliable performance |
| **GPT-3.5 Turbo** | Fast and cheap | Simple tasks, high volume |

**API Base:** https://api.openai.com/v1/

---

## Anthropic Claude Models

| Model | Description | Best For |
|-------|-------------|----------|
| **Claude Sonnet 4.5** | Latest Claude | Balanced performance |
| **Claude 3.5 Sonnet** | Fast and capable | General tasks |
| **Claude 3.5 Haiku** | Fastest Claude | Quick responses |
| **Claude 3 Opus** | Most capable | Complex analysis |
| **Claude 3 Sonnet** | Balanced | General purpose |
| **Claude 3 Haiku** | Quick | Simple tasks |

**API Base:** https://api.anthropic.com/v1/

---

## Google Gemini Models

| Model | Description | Best For |
|-------|-------------|----------|
| **Gemini 2.5 Pro** | Latest flagship | Complex tasks |
| **Gemini 2.0 Flash** | Fast experimental | Quick tasks |
| **Gemini 1.5 Pro** | High capability | Analysis, long context |
| **Gemini 1.5 Flash** | Fast and efficient | Everyday use |

**API Base:** https://generativelanguage.googleapis.com/v1beta/

---

## Chutes Models (DeepSeek)

| Model | Description | Best For |
|-------|-------------|----------|
| **DeepSeek V3** | Latest DeepSeek | General purpose |
| **DeepSeek R1** | Reasoning model | Complex analysis |

**API Base:** https://llm.chutes.ai/

---

## OpenRouter

OpenRouter provides access to **100+ models** from multiple providers through a single API. Popular models include:

- All OpenAI models
- All Anthropic Claude models
- All Google models
- Mistral models
- Meta Llama models
- And many more

**API Base:** https://openrouter.ai/api/v1/

---

\pagebreak

# 6. Getting Started Tutorial

## Step 1: Add Your API Keys

1. Go to **ChatProjects > Settings** in WordPress admin
2. Enter your OpenAI API key (required for projects)
3. Optionally add keys for other providers
4. Click **Save Settings**

> **Tip:** Start with just OpenAI. Add other providers later as needed.

---

## Step 2: Create a ChatProjects Page

1. Go to **Pages > Add New**
2. Give the page a title (e.g., "AI Chat" or "Knowledge Base")
3. Add this shortcode to the content:

```
[chatprojects_main]
```

4. Click **Publish**

---

## Step 3: Create Your First Project

1. Navigate to your ChatProjects page
2. Click **Projects** in the navigation
3. Click **New Project**
4. Fill in:
   - **Title:** Name your project (e.g., "Product Documentation")
   - **Description:** What this project contains
   - **Instructions:** Custom AI behavior (optional)
5. Click **Create Project**

> **Behind the scenes:** ChatProjects creates an OpenAI Vector Store to index your documents.

---

## Step 4: Upload Files

1. Open your project
2. Go to the **Files** tab
3. Drag and drop files, or click to browse
4. Wait for files to upload and index

> **Supported formats:** PDF, DOC, DOCX, TXT, MD, CSV, JSON, XML, HTML, CSS, JS, PY, PHP, and more.

---

## Step 5: Start Chatting

1. Go to the **Chat** tab
2. Select your **AI Provider** (if you have multiple configured)
3. Select the **Model** you want to use
4. Type your question and press Enter
5. The AI will search your uploaded documents to answer

**Tips:**
- Switch providers mid-conversation to compare responses
- Reference specific documents in your question
- Use the dark mode toggle for comfortable viewing

---

\pagebreak

# 7. Using the Chat Interface

## Chat Modes

ChatProjects offers two chat modes:

### General Chat (No Project)

- Direct conversation with AI models
- No document search capability
- Can switch between any configured provider
- Chat history saved locally

**Best for:** General questions, brainstorming, coding help

### Project Chat (With File Search)

- Conversations within a specific project context
- AI searches uploaded documents to answer questions
- Uses OpenAI Assistants API
- All project users share the same assistant

**Best for:** Questions about uploaded documents, knowledge base queries

---

## Selecting Providers and Models

At the top of the chat interface:

1. **Provider Dropdown** - Select from configured AI providers
2. **Model Dropdown** - Select from available models for that provider

> **Tip:** You can switch providers mid-conversation. Each message uses the currently selected provider/model.

---

## Managing Chat History

### Starting New Conversations

- Click the **New Chat** button to start fresh
- Previous conversations are saved automatically

### Renaming Chats

- Click the chat name in the sidebar
- Enter a new name
- Press Enter to save

### Deleting Chats

- Hover over a chat in the sidebar
- Click the delete icon
- Confirm deletion

> **Note:** Deleted chats cannot be recovered.

---

## Chat Interface Features

| Feature | Description |
|---------|-------------|
| **Real-time Streaming** | See responses as they're generated |
| **Markdown Support** | Formatted text, code blocks, tables |
| **Code Highlighting** | Syntax highlighting for code snippets |
| **Dark Mode** | Toggle between light and dark themes |
| **Auto-generated Titles** | Chat titles generated from first message |

---

\pagebreak

# 8. Project Management

## Creating Projects

1. Click **Projects** in the navigation
2. Click **New Project**
3. Enter project details:

| Field | Description | Required |
|-------|-------------|----------|
| Title | Project name | Yes |
| Description | What the project contains | No |
| Instructions | Custom AI behavior | No |

4. Click **Create Project**

---

## Project Settings

### Custom Instructions

Define how the AI should behave when chatting about this project:

**Examples:**

```
You are a technical support agent. Answer questions based on the uploaded product documentation. Always cite the relevant document name.
```

```
You are a legal assistant. Provide information from the uploaded contracts. Always note that this is not legal advice.
```

### Sharing Modes

| Mode | Description |
|------|-------------|
| **Private** | Only you can access |
| **Shared** | Specific users can access |
| **Public** | All logged-in users can access |

> **Free Version:** All projects are shared by all users.

---

## Editing Projects

1. Open the project
2. Click **Settings** or the edit icon
3. Update title, description, or instructions
4. Click **Save**

---

## Deleting Projects

1. Open the project
2. Click **Delete Project**
3. Confirm deletion

> **Warning:** Deleting a project also deletes:
> - All uploaded files
> - The OpenAI Vector Store
> - All chat history for that project

---

## Free Version Limits

| Limit | Value |
|-------|-------|
| Maximum Projects | 5 |
| Project Ownership | Shared (all users) |

---

\pagebreak

# 9. File Management & Vector Stores

## How Vector Stores Work

When you upload files to a project:

1. Files are uploaded to OpenAI's servers
2. OpenAI creates a Vector Store for your project
3. Files are processed and indexed for semantic search
4. When you chat, AI searches the index for relevant content
5. AI uses found content to answer your questions

---

## Supported File Types

### Documents
| Type | Extensions | Description |
|------|------------|-------------|
| PDF | .pdf | Portable Document Format |
| Word | .doc, .docx | Microsoft Word documents |
| Text | .txt | Plain text files |
| Markdown | .md | Markdown formatted text |

### Data Files
| Type | Extensions | Description |
|------|------------|-------------|
| CSV | .csv | Comma-separated values |
| JSON | .json | JavaScript Object Notation |
| XML | .xml | Extensible Markup Language |
| HTML | .html | Web pages |

### Code Files
| Type | Extensions | Description |
|------|------------|-------------|
| JavaScript | .js | JavaScript source |
| Python | .py | Python source |
| PHP | .php | PHP source |
| CSS | .css | Stylesheets |
| Java | .java | Java source |
| C++ | .cpp | C++ source |

### Spreadsheets
| Type | Extensions | Description |
|------|------------|-------------|
| Excel | .xls, .xlsx | Microsoft Excel |

---

## File Size Limits

| Setting | Default | Maximum |
|---------|---------|---------|
| Per File | 50 MB | 512 MB |

> **Note:** Actual limit depends on your server's PHP configuration (`upload_max_filesize` and `post_max_size`).

---

## Upload Process

1. Open your project
2. Go to the **Files** tab
3. Drag files onto the upload area, or click to browse
4. Wait for upload progress to complete
5. Files are automatically indexed

---

## Managing Files

### Viewing Files

- All uploaded files listed in the Files tab
- See file name, size, and upload date

### Deleting Files

1. Click the delete icon next to a file
2. Confirm deletion
3. File removed from Vector Store

> **Note:** Deleted files cannot be recovered.

---

\pagebreak

# 10. Shortcode Reference

## Main Shortcode

```
[chatprojects_main]
```

Renders the full ChatProjects application including:
- Navigation menu
- Projects list
- Chat interface
- Settings panel

---

## Shortcode Attributes

| Attribute | Values | Default | Description |
|-----------|--------|---------|-------------|
| `default_tab` | chat, projects | chat | Initial tab to display |
| `height` | Any CSS value | 80vh | Container height |

---

## Examples

### Basic Usage

```
[chatprojects_main]
```

### Start on Projects Tab

```
[chatprojects_main default_tab="projects"]
```

### Custom Height

```
[chatprojects_main height="600px"]
```

### Combined Options

```
[chatprojects_main default_tab="chat" height="90vh"]
```

---

## Alternative Shortcode

```
[chatprojects_workspace]
```

Alias for `[chatprojects_main]` - identical functionality.

---

\pagebreak

# 11. Security & Privacy

## API Key Encryption

All API keys are encrypted before storage:

| Feature | Implementation |
|---------|---------------|
| Algorithm | AES-256-CBC |
| Library | OpenSSL |
| Key Derivation | WordPress AUTH_KEY |
| Storage | WordPress options table |

**Your API keys are:**
- Never stored in plain text
- Decrypted only when making API calls
- Never transmitted to ChatProjects servers

---

## Data Storage

| Data | Location | Encryption |
|------|----------|------------|
| API Keys | wp_options | AES-256 |
| Chat History | wp_chatprojects_chats | None (local) |
| Messages | wp_chatprojects_messages | None (local) |
| Projects | wp_posts (chatpr_project) | None (local) |
| Uploaded Files | OpenAI servers | Provider managed |

---

## Data Transmission

ChatProjects connects to external AI providers only when you:
- Send a chat message
- Upload files to a project
- Create a new project

**Data sent to AI providers:**
- Chat messages (to selected provider)
- Uploaded file contents (to OpenAI only)
- System instructions (if configured)

**Data NOT sent anywhere:**
- Your API keys (used only for authentication)
- WordPress user data
- Other website data

---

## Security Features

| Feature | Description |
|---------|-------------|
| Nonce Verification | All AJAX requests verified |
| Capability Checks | Permission validation for all actions |
| Input Sanitization | All user input sanitized |
| Output Escaping | All output properly escaped |
| Rate Limiting | Built-in rate limiting support |
| File Validation | File type and size verification |

---

\pagebreak

# 12. Free vs Pro Comparison

## Feature Comparison

| Feature | Free | Pro |
|---------|:----:|:---:|
| **AI Providers** | | |
| OpenAI (GPT-4, GPT-5) | Yes | Yes |
| Anthropic (Claude) | Yes | Yes |
| Google (Gemini) | Yes | Yes |
| Chutes (DeepSeek) | Yes | Yes |
| OpenRouter (100+ models) | Yes | Yes |
| **Projects** | | |
| Number of Projects | 5 | Unlimited |
| Project Ownership | Shared | Per-User |
| Project Sharing | - | Yes |
| **Features** | | |
| File Upload | Yes | Yes |
| Vector Store Search | Yes | Yes |
| Chat History | Yes | Yes |
| Dark Mode | Yes | Yes |
| Encrypted API Keys | Yes | Yes |
| Model Comparison | - | Yes |
| Audio Transcription | - | Yes |
| Prompt Library | - | Yes |
| **Support** | | |
| WordPress.org Forum | Yes | Yes |
| Priority Email Support | - | Yes |

---

## When to Upgrade

Consider ChatProjects Pro if you need:

- **More than 5 projects**
- **Per-user project ownership** - Each user manages their own projects
- **Project sharing** - Share specific projects with team members
- **Model comparison** - Compare responses from different AI models side-by-side
- **Audio transcription** - Transcribe audio files using Whisper
- **Prompt library** - Save and reuse common prompts
- **Priority support** - Direct email support with faster response times

**Upgrade at:** https://chatprojects.com/

---

\pagebreak

# 13. Third-Party Services

ChatProjects connects to external AI services when you use their features. Your data is transmitted according to each provider's privacy policy.

## OpenAI API

**Used for:** AI chat, file analysis, Vector Store, Assistants API

| | |
|--|--|
| Service URL | https://api.openai.com/ |
| Privacy Policy | https://openai.com/privacy/ |
| Terms of Service | https://openai.com/terms/ |

**Data transmitted:** Chat messages, uploaded files, system instructions

---

## Anthropic Claude API

**Used for:** AI chat with Claude models

| | |
|--|--|
| Service URL | https://api.anthropic.com/ |
| Privacy Policy | https://www.anthropic.com/privacy |
| Terms of Service | https://www.anthropic.com/terms |

**Data transmitted:** Chat messages

---

## Google Gemini API

**Used for:** AI chat with Gemini models

| | |
|--|--|
| Service URL | https://generativelanguage.googleapis.com/ |
| Privacy Policy | https://policies.google.com/privacy |
| Terms of Service | https://policies.google.com/terms |

**Data transmitted:** Chat messages

---

## Chutes API

**Used for:** AI chat with DeepSeek models

| | |
|--|--|
| Service URL | https://llm.chutes.ai/ |
| Privacy Policy | https://chutes.ai/privacy |
| Terms of Service | https://chutes.ai/terms |

**Data transmitted:** Chat messages

---

## OpenRouter API

**Used for:** AI chat with 100+ models

| | |
|--|--|
| Service URL | https://openrouter.ai/api/ |
| Privacy Policy | https://openrouter.ai/privacy |
| Terms of Service | https://openrouter.ai/terms |

**Data transmitted:** Chat messages

---

## Your Control

- You choose which API providers to configure
- Only providers with valid API keys receive any data
- You can remove API keys at any time to stop data transmission

---

\pagebreak

# 14. Third-Party Libraries

ChatProjects includes the following open-source JavaScript libraries:

## Alpine.js

| | |
|--|--|
| Version | 3.x |
| License | MIT |
| Source | https://github.com/alpinejs/alpine |
| Purpose | UI interactivity and reactivity |

---

## highlight.js

| | |
|--|--|
| Version | 11.x |
| License | BSD-3-Clause |
| Source | https://github.com/highlightjs/highlight.js |
| Purpose | Syntax highlighting for code blocks |

---

## markdown-it

| | |
|--|--|
| Version | 14.x |
| License | MIT |
| Source | https://github.com/markdown-it/markdown-it |
| Purpose | Markdown rendering in chat messages |

---

\pagebreak

# 15. Troubleshooting & FAQ

## Frequently Asked Questions

### Do I need all 5 API keys?

**No.** You only need one API key to start chatting. OpenAI is required for project features (Vector Stores and file search). Add other providers only if you want to use their models.

---

### Where are my API keys stored?

Your API keys are stored **encrypted** in your WordPress database using AES-256 encryption. They never leave your server except when making API calls to the respective providers.

---

### Can multiple users access projects?

**Yes.** In the Free version, there are 5 shared projects accessible to all logged-in users with appropriate permissions. The Pro version adds per-user projects and sharing controls.

---

### What are the file size limits?

- **Default:** 50 MB per file
- **Maximum:** 512 MB per file (configurable in Settings)
- **Note:** Also limited by your server's PHP settings

---

### What file types can I upload?

PDF, DOC, DOCX, TXT, MD, CSV, JSON, XML, HTML, CSS, JS, PY, PHP, Java, C++, XLS, XLSX

---

### Can I use ChatProjects on client sites?

**Yes.** The Free version is GPL licensed, so you can use it on any site including client sites. For commercial use with advanced features, consider the Pro version.

---

### How do I get support?

| Version | Support Channel |
|---------|----------------|
| Free | WordPress.org support forum |
| Pro | Priority email: support@chatprojects.com |

---

## Common Issues

### "API key invalid" Error

**Causes:**
- Key was copied incorrectly
- Key has been revoked
- Key doesn't have required permissions

**Solutions:**
1. Verify the key in your provider's dashboard
2. Generate a new key if needed
3. Ensure the key has API access enabled

---

### Files Not Uploading

**Causes:**
- File too large
- File type not allowed
- Server configuration limits

**Solutions:**
1. Check file size against your configured limit
2. Verify file type is in allowed list
3. Check PHP `upload_max_filesize` setting
4. Check PHP `post_max_size` setting

---

### Chat Not Responding

**Causes:**
- No API key configured
- API key invalid
- Provider service down
- Rate limit exceeded

**Solutions:**
1. Verify API key is saved in Settings
2. Check API key validity in provider dashboard
3. Check provider status page
4. Wait and try again (rate limits reset)

---

### Vector Store Search Not Working

**Causes:**
- No OpenAI key configured
- Files not fully indexed
- Project not properly created

**Solutions:**
1. Ensure OpenAI API key is configured
2. Wait for file indexing to complete
3. Try uploading files again
4. Create a new project if needed

---

\pagebreak

# 16. Support Resources

## Documentation

| Resource | URL |
|----------|-----|
| Official Docs | https://chatprojects.com/docs/ |
| This Guide | Included with plugin |

---

## Support Channels

| Version | Channel | URL |
|---------|---------|-----|
| Free | WordPress Forum | https://wordpress.org/support/plugin/chatprojects/ |
| Pro | Email Support | support@chatprojects.com |

---

## Links

| Resource | URL |
|----------|-----|
| Website | https://chatprojects.com |
| Pricing | https://chatprojects.com/ |
| Privacy Policy | https://chatprojects.com/privacy/ |

---

\pagebreak

# 17. Changelog

## Version 1.0.0

*Initial Release*

### Features
- Multi-provider chat (OpenAI, Anthropic, Gemini, Chutes, OpenRouter)
- Project management with OpenAI Assistants
- File upload to Vector Stores
- Vector Store file search
- Modern chat interface
- Real-time response streaming
- Dark/Light theme support
- Shortcode embedding `[chatprojects_main]`
- AES-256 API key encryption
- Local chat history storage
- Custom assistant instructions
- Configurable file upload limits

### Supported File Types
PDF, DOC, DOCX, TXT, MD, CSV, JSON, XML, HTML, CSS, JS, PY, PHP, Java, C++, XLS, XLSX

---

\pagebreak

# 18. Credits & License

## Developer

**ChatProjects** is developed by GPTAdviser.

| | |
|--|--|
| Website | https://chatprojects.com |
| Developer | GPTAdviser |

---

## License

ChatProjects is released under the **GNU General Public License v2 or later** (GPLv2+).

This means you can:
- Use on unlimited sites
- Modify the source code
- Distribute copies
- Use commercially

Full license: https://www.gnu.org/licenses/gpl-2.0.html

---

## Open Source Libraries

ChatProjects uses the following open source libraries:

| Library | License |
|---------|---------|
| Alpine.js | MIT |
| highlight.js | BSD-3-Clause |
| markdown-it | MIT |

---

## Acknowledgments

Thank you to:
- The WordPress community
- OpenAI, Anthropic, Google, and other AI providers
- All users and contributors

---

\pagebreak

---

**ChatProjects v1.0.0**

*Own Your Chat*

https://chatprojects.com

---
