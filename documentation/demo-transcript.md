# ChatProjects - 5 Minute Demo Transcript

**Duration:** 5:00 - 5:30
**Starting point:** Plugin already installed and activated

---

## INTRO (0:00 - 0:30)

> "Welcome! Today I'm going to show you ChatProjects - a WordPress plugin that lets you chat with AI using your own API keys.
>
> Not only does ChatProjects let you chat with OpenAI, Anthropic, Google, DeepSeek and more directly on your site, you can also build project assistants that answer from your own documents, using OpenAI's vector stores. Your API keys stay on your server and nothing goes through a middleman. No monthly subscriptions to yet another SaaS tool.
>
> The plugin is already installed and activated. Let me show you how to set it up."

---

## ADMIN SETUP (0:30 - 1:30)

> "First, we go to the WordPress admin sidebar and click **ChatProjects**, then **Settings**.
>
> Here's where you add your API keys. ChatProjects supports five AI providers:
> - **OpenAI** - GPT-5.6 Sol, Terra and Luna, plus the GPT-5.4 family
> - **Anthropic** - Claude Opus 5.5, Sonnet 5.5 and Haiku 4.5
> - **Google Gemini** - Gemini 3.8 Flash and 3.1 Pro
> - **Chutes** - DeepSeek V4 Flash and V3.2
> - **OpenRouter** - Over 100 models from various providers
>
> I'll paste in my OpenAI key here and save. Notice it now only shows a masked version of the key - all keys are encrypted before storage.
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
> You can choose where its main button goes - projects, chat, or settings - with the `default_tab` option. I'll publish this and view the page.
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
> Here's the powerful part - **Assistant Instructions**. I'll type: 'You are a helpful product support assistant. Answer questions based on the uploaded documentation. Be concise and cite specific documents when possible.'
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
> Watch the progress bars - each file is sent to OpenAI and indexed in the project's vector store.
>
> Done. The AI can now search through these documents when answering questions. You can upload PDFs, Word docs, text files, markdown, even code files - up to 50 megabytes each."

---

## CHAT WITH YOUR DOCUMENTS (3:45 - 4:35)

> "This is the fun part. Let's chat with those documents.
>
> Still inside the project, I'll click the **Chat** tab. Project chat runs on OpenAI with file search, using the default model we picked in Settings - here, GPT-5.6 Sol. There's nothing else to choose.
>
> I'll type: 'What are the main features described in the user guide?'
>
> Watch the response stream in real-time...
>
> The AI searched our files and is summarizing the key features. Underneath the answer, it lists its **Sources** - the user guide we just uploaded.
>
> Let me ask a follow-up: 'Can you explain the API authentication in more detail?'
>
> It remembers the conversation, searches the API reference this time, and walks through the authentication steps from our docs."

---

## GENERAL CHAT (4:35 - 5:05)

> "Projects are for your documents. For everything else there's **Chat** - I'll click it in the navigation.
>
> Here I can pick any provider and model at the top. Let me switch to **Anthropic** and **Claude Opus 5.5**. Switching starts a fresh conversation - it asks first, and my earlier chats stay in the sidebar.
>
> I'll ask: 'Write a short welcome email for new customers.' Same streaming, different AI.
>
> You can also attach an image here - click the image icon or just paste a screenshot - and ask about it."

---

## WRAP-UP (5:05 - 5:30)

> "That's ChatProjects in five minutes.
>
> To recap: You bring your own API keys - no middleman. Upload documents and chat with them, and use GPT-5.6, Claude, Gemini, or DeepSeek for everything else. Your keys are encrypted and stored on your own WordPress site.
>
> The free version gives every user their own private projects and full access to all five AI providers. You can even put a project on your public site with the chat widget shortcode.
>
> If you need more - project sharing, audio transcription, or side-by-side model comparison - check out ChatProjects Pro.
>
> Thanks for watching. Download the plugin and start chatting with your documents today."

---

## Presenter Notes

- **Timing:** Aim for 5:00-5:30 total. The two chat sections have buffer time for AI response delays.
- **Screen flow:** Admin Settings → New Page → Frontend → New Project → Files → Project Chat → General Chat
- **Before recording:** Add an Anthropic key in Settings as well as OpenAI, or skip the Claude switch in General Chat. Project chat always uses OpenAI.
- **Fallback:** Have pre-uploaded files ready in case of upload delays
- **API keys:** Use real keys with low rate limits for demo safety
- **Pro mentions:** Keep brief - two quick mentions max to avoid feeling salesy

---

## Key Points to Emphasize

1. **Privacy:** "Your keys, on your server - no middleman"
2. **Multi-provider:** 5 providers, 100+ models
3. **Vector Store:** Documents are searchable by AI
4. **Easy setup:** One shortcode, one API key
5. **Modern UI:** Dark mode, responsive design
