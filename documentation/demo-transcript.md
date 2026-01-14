# ChatProjects - 5 Minute Demo Transcript

**Duration:** 5:00 - 5:30
**Starting point:** Plugin already installed and activated

---

## INTRO (0:00 - 0:30)

> "Welcome! Today I'm going to show you ChatProjects - a WordPress plugin that lets you chat with AI using your own API keys.
>
> Not only does ChatProjects let you chat with OpenAI, Anthropic, Google, DeepSeek and more directly on your site, you can also manage openai vector stores and responses api powered chatbots. Your data stays on your server. No monthly subscriptions to yet another SaaS tool.
>
> The plugin is already installed and activated. Let me show you how to set it up."

---

## ADMIN SETUP (0:30 - 1:30)

> "First, we go to the WordPress admin sidebar and click **ChatProjects**, then **Settings**.
>
> Here's where you add your API keys. ChatProjects supports five AI providers:
> - **OpenAI** - GPT-4o, GPT-4o-mini, and the new o1 reasoning models
> - **Anthropic** - Claude Sonnet 4 and Claude 3.5
> - **Google Gemini** - Gemini 2.0 Flash and 1.5 Pro
> - **Chutes** - DeepSeek V3 and R1 reasoning models
> - **OpenRouter** - Over 100 models from various providers
>
> I'll paste in my OpenAI key here. Notice it says 'Key saved securely' - all keys are encrypted using AES-256 before storage.
>
> You can also set the maximum file upload size - default is 50 megabytes - and choose your default AI model.
>
> That's it for setup. One API key is all you need to get started."

---

## CREATE FRONTEND PAGE (1:30 - 2:00)

> "Now let's create a page where users will access the chat interface.
>
> I'll create a new WordPress page, call it 'AI Chat', and add the shortcode: `[chatprojects_main]`
>
> You can customize the default tab if you want - projects, chat, or settings. I'll publish this and view the page.
>
> Here's the ChatProjects interface. Clean, modern design with navigation cards. You'll see options for Projects, Chat, and Settings."

---

## CREATE A PROJECT (2:00 - 3:00)

> "Let's create our first project. Click **Projects**, then **New Project**.
>
> I'll give it a title: 'Product Documentation'.
>
> Add a description: 'Chat with our product docs and knowledge base.'
>
> Here's the powerful part - **Custom Instructions**. I'll type: 'You are a helpful product support assistant. Answer questions based on the uploaded documentation. Be concise and cite specific documents when possible.'
>
> Click **Create Project**.
>
> Behind the scenes, ChatProjects just created a Vector Store with OpenAI to hold our documents. The project is ready.
>


---

## UPLOAD FILES (3:00 - 3:45)

> "Now let's give our AI some knowledge to work with.
>
> I'll click into the project and go to the Files section.
>
> You can drag and drop files here, or click to browse. I'll upload three PDFs - our user guide, API reference, and FAQ document.
>
> Watch the progress bars - the files upload to WordPress, then get indexed in the Vector Store.
>
> Done. The AI can now search through these documents when answering questions. You can upload PDFs, Word docs, text files, markdown, even code files - up to 50 megabytes each."

---

## CHAT WITH AI (3:45 - 5:00)

> "This is the fun part. Let's chat.
>
> Click the **Chat** tab. At the top, I can select my AI provider - I'll stick with OpenAI - and choose the model. GPT-4o is selected.
>
> I'll type: 'What are the main features described in the user guide?'
>
> Watch the response stream in real-time...
>
> The AI found the user guide and is summarizing the key features. It's pulling directly from the document we just uploaded.
>
> Now here's something cool - I can switch providers mid-conversation. Let me change to Anthropic Claude.
>
> I'll ask: 'Can you explain the API authentication in more detail?'
>
> Same conversation, different AI. Claude is now searching the API reference document...
>
> There we go. Detailed explanation with the authentication steps from our docs.
>
> You can also attach images to your messages - just click the image icon. Useful for screenshots or diagrams."

---

## WRAP-UP (5:00 - 5:30)

> "That's ChatProjects in five minutes.
>
> To recap: You bring your own API keys - no middleman. Upload documents and chat with them using GPT-4, Claude, Gemini, or DeepSeek. All your data stays on your WordPress server.
>
> The free version gives you five shared projects and full access to all five AI providers.
>
> If you need more - unlimited projects, per-user ownership, audio transcription, or side-by-side model comparison - check out ChatProjects Pro.
>
> Thanks for watching. Download the plugin and start chatting with your documents today."

---

## Presenter Notes

- **Timing:** Aim for 5:00-5:30 total. The chat section has buffer time for AI response delays.
- **Screen flow:** Admin Settings → New Page → Frontend → New Project → Files → Chat
- **Fallback:** Have pre-uploaded files ready in case of upload delays
- **API keys:** Use real keys with low rate limits for demo safety
- **Pro mentions:** Keep brief - two quick mentions max to avoid feeling salesy

---

## Key Points to Emphasize

1. **Privacy:** "Your keys, your data, your server"
2. **Multi-provider:** 5 providers, 100+ models
3. **Vector Store:** Documents are searchable by AI
4. **Easy setup:** One shortcode, one API key
5. **Modern UI:** Dark mode, responsive design
