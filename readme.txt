=== My AI Chat ===
Contributors: artemlitvinov
Tags: ai, chatbot, rag, ollama, woocommerce
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Local AI chatbot for WordPress and WooCommerce. Answers questions from your site content using a self-hosted RAG stack (Ollama + Qdrant).

== Description ==

My AI Chat adds an AI-powered chat widget to your site that answers visitor questions based on your own content: WooCommerce products, posts, and pages. It implements a full Retrieval-Augmented Generation (RAG) pipeline on a fully self-hosted infrastructure — no paid third-party AI APIs are required.

**How it works:**

1. Your content (products, posts, pages) is converted into vector embeddings by a local Ollama embedding model (e.g. `nomic-embed-text`).
2. The vectors are stored in a self-hosted Qdrant vector database.
3. When a visitor asks a question, the plugin finds the most relevant content by semantic similarity and passes it as context to a local LLM (e.g. `qwen2.5:1.5b`) running in Ollama.
4. The model generates an answer with real links to your products and pages.

**Features:**

* Floating chat widget with a customizable primary color.
* Semantic (vector) search over products, posts, and pages.
* WooCommerce support: product SKU, price, categories, and attributes are indexed.
* Automatic re-indexing when content is created, updated, or deleted (via WP-Cron).
* Bulk indexing from the admin page or via WP-CLI (`wp ai-bot index`).
* Configurable system prompt, context template, and product card template.
* Optional "no-AI" mode: the bot replies with product cards only, without LLM generation.
* WP-CLI commands for testing: `wp ai-bot search "query"` and `wp ai-bot ask "question"`.
* Translation-ready (Ukrainian and Russian translations included).

== External services ==

This plugin connects to two self-hosted services that you install and control yourself. No data is sent to third-party companies.

**Ollama (local LLM server)**

* What it is used for: generating text embeddings for your content and generating chat answers.
* What data is sent: the text of your published products, posts, and pages (during indexing), and visitor chat messages together with the retrieved site context (during chat).
* When: during content indexing and on every chat message.
* Service link: [https://ollama.com](https://ollama.com) — runs on your own server; no external terms of service apply.

**Qdrant (local vector database)**

* What it is used for: storing and searching vector embeddings of your content.
* What data is sent: vector embeddings and text excerpts of your published content, and query vectors built from visitor chat messages.
* When: during content indexing and on every chat message.
* Service link: [https://qdrant.tech](https://qdrant.tech) — runs on your own server; no external terms of service apply.

== Installation ==

1. Install and run [Ollama](https://ollama.com) and pull the required models:

	ollama pull nomic-embed-text
	ollama pull qwen2.5:1.5b

2. Install and run [Qdrant](https://qdrant.tech), for example with Docker:

	docker run -d --name qdrant-local -p 6333:6333 -v qdrant_storage:/qdrant/storage qdrant/qdrant

3. Upload the plugin files to `/wp-content/plugins/my-ai-chat`, or install it through the WordPress plugins screen.
4. Activate the plugin through the "Plugins" screen in WordPress. On activation the plugin creates the Qdrant collection automatically.
5. Go to the "AI Chat" admin menu and set the Ollama URL, Qdrant URL, model names, and prompts.
6. Click "Start Re-indexing" to index your existing content (or run `wp ai-bot index` via WP-CLI).

== Frequently Asked Questions ==

= Does this plugin send my data to OpenAI, Anthropic, or other AI providers? =

No. All AI processing happens on your own infrastructure: Ollama runs the language models locally and Qdrant stores the vectors locally. Nothing leaves your servers.

= Do I need WooCommerce? =

No. WooCommerce is optional. With WooCommerce active, products are indexed with their price, SKU, categories, and attributes. Without it, the bot answers based on posts and pages.

= The chat replies "Connection error with server". What should I check? =

Make sure the Ollama server is running and the configured models are pulled (`ollama list`), and that the Ollama URL in the plugin settings is reachable from your WordPress server. If WordPress runs in Docker, use `http://host.docker.internal:11434`.

= Vectors are not saved to Qdrant. What should I check? =

Make sure the Qdrant container is running and reachable on port 6333 from WordPress, and that the embedding model (`nomic-embed-text` by default) is pulled in Ollama. Enable `WP_DEBUG` to see diagnostic messages in the PHP error log.

= Can I use a different LLM or embedding model? =

Yes. Any model available in Ollama can be configured on the settings page. If you change the embedding model, you must re-index all content, and the vector size setting must match the new model.

== Screenshots ==

1. Chat widget on the front end.
2. Plugin settings page.

== Changelog ==

= 1.1 =
* Settings page for infrastructure, prompts, and widget appearance.
* Automatic background indexing on content save and delete.
* Bulk indexing from the admin page and via WP-CLI.
* Customizable widget primary color.
* Ukrainian and Russian translations.

= 1.0 =
* Initial release.

== Upgrade Notice ==

= 1.1 =
Adds a settings page, background indexing, and widget customization.
