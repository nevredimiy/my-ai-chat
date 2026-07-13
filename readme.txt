=== AloChat ===
Contributors: artemlitvinov
Tags: ai, chatbot, rag, ollama, woocommerce
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI chatbot for WordPress and WooCommerce. Answers questions from your site content via a local RAG stack (Ollama + Qdrant) or your OpenAI key.

== Description ==

AloChat adds an AI-powered chat widget to your site that answers visitor questions based on your own content: WooCommerce products, posts, and pages. It implements a full Retrieval-Augmented Generation (RAG) pipeline with a choice of two AI engines:

* **Ollama (self-hosted, free)** — everything runs on your own infrastructure; no data leaves your servers and no paid APIs are required.
* **OpenAI API** — enter your API key and the plugin uses OpenAI models for embeddings and chat answers; no local AI server needed.

The vector database can be a self-hosted Qdrant instance or a managed **Qdrant Cloud** cluster (a free tier is available) — just enter its URL and API key.

**How it works:**

1. Your content (products, posts, pages) is converted into vector embeddings by the selected engine (e.g. `nomic-embed-text` in Ollama, or `text-embedding-3-small` via OpenAI).
2. The vectors are stored in a Qdrant vector database (self-hosted or Qdrant Cloud).
3. When a visitor asks a question, the plugin finds the most relevant content by semantic similarity and passes it as context to the chat model (e.g. `qwen2.5:1.5b` in Ollama, or `gpt-4o-mini` via OpenAI).
4. The model generates an answer with real links to your products and pages.

**Features:**

* Two AI engines: self-hosted Ollama or the OpenAI API (just enter your API key).
* Qdrant Cloud support: connect a managed cluster with an API key — no servers to maintain.
* Floating chat widget with a customizable primary color.
* Semantic (vector) search over products, posts, and pages.
* WooCommerce support: product SKU, price, categories, and attributes are indexed.
* Automatic re-indexing when content is created, updated, or deleted (via WP-Cron).
* Bulk indexing from the admin page or via WP-CLI (`wp alochat index`).
* Configurable system prompt, context template, and product card template.
* Optional "no-AI" mode: the bot replies with product cards only, without LLM generation.
* WP-CLI commands for testing: `wp alochat search "query"` and `wp alochat ask "question"`.
* Translation-ready (Ukrainian and Russian translations included).

== External services ==

Depending on your configuration, this plugin connects to the following services. With the default configuration (Ollama + self-hosted Qdrant) no data is sent to third-party companies.

**Ollama (local LLM server)** — used when the "Ollama" engine is selected (default).

* What it is used for: generating text embeddings for your content and generating chat answers.
* What data is sent: the text of your published products, posts, and pages (during indexing), and visitor chat messages together with the retrieved site context (during chat).
* When: during content indexing and on every chat message.
* Service link: [https://ollama.com](https://ollama.com) — runs on your own server; no external terms of service apply.

**OpenAI API (third-party service)** — used only when you select the "OpenAI API" engine and enter your API key.

* What it is used for: generating text embeddings for your content and generating chat answers.
* What data is sent to OpenAI: the text of your published products, posts, and pages (during indexing), and visitor chat messages together with the retrieved site context (during chat), along with your API key for authentication.
* When: during content indexing and on every chat message.
* Service: [https://openai.com](https://openai.com) — [Terms of use](https://openai.com/policies/terms-of-use/), [Privacy policy](https://openai.com/policies/privacy-policy/). Usage is billed to your OpenAI account.

**Qdrant (vector database, self-hosted or Qdrant Cloud)**

* What it is used for: storing and searching vector embeddings of your content.
* What data is sent: vector embeddings and text excerpts of your published content, query vectors built from visitor chat messages, and your API key (if configured) for authentication.
* When: during content indexing and on every chat message.
* Service: [https://qdrant.tech](https://qdrant.tech). Self-hosted instances run on your own server. If you connect a managed Qdrant Cloud cluster, the data above is stored on Qdrant's infrastructure — see the [Qdrant Cloud terms](https://qdrant.tech/legal/terms_and_conditions/) and [privacy policy](https://qdrant.tech/legal/privacy-policy/).

== Installation ==

**Option A — fully self-hosted (free):**

1. Install and run [Ollama](https://ollama.com) and pull the required models:

	ollama pull nomic-embed-text
	ollama pull qwen2.5:1.5b

2. Install and run [Qdrant](https://qdrant.tech), for example with Docker:

	docker run -d --name qdrant-local -p 6333:6333 -v qdrant_storage:/qdrant/storage qdrant/qdrant

**Option B — no servers, API keys only:**

1. Create an OpenAI API key at platform.openai.com.
2. Create a free Qdrant Cloud cluster at cloud.qdrant.io and copy its URL and API key.

**Then:**

1. Upload the plugin files to `/wp-content/plugins/alochat`, or install it through the WordPress plugins screen.
2. Activate the plugin through the "Plugins" screen in WordPress.
3. Go to the "AI Chat" admin menu, choose the AI engine, and enter the URLs / API keys. For OpenAI set the vector size to 1536 (for `text-embedding-3-small`).
4. Click "Start Re-indexing" to index your existing content (or run `wp alochat index` via WP-CLI).

== Frequently Asked Questions ==

= Does this plugin send my data to OpenAI, Anthropic, or other AI providers? =

Only if you choose the OpenAI engine. With the default Ollama engine all AI processing happens on your own infrastructure: Ollama runs the language models locally and Qdrant stores the vectors locally. If you select the OpenAI engine, your content and visitor questions are sent to the OpenAI API (see the "External services" section).

= What do I need to use the OpenAI engine? =

Just an OpenAI API key. Select "OpenAI API" as the engine, paste the key, and check that the embedding vector size matches the model (1536 for `text-embedding-3-small`). You still need a Qdrant database — either self-hosted or a free Qdrant Cloud cluster.

= Can I switch engines later? =

Yes, but embeddings from different models are incompatible. After switching the engine (or the embedding model), update the vector size, re-create the Qdrant collection, and run a full re-index.

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

= 1.2 =
* New: OpenAI API engine — enter your API key and use OpenAI models for embeddings and chat answers.
* New: Qdrant Cloud support — API key field for authenticated Qdrant clusters.
* Chat generation now uses the OpenAI-compatible chat API for both engines; the temperature and context template settings are now applied.
* Removed unused legacy code.

= 1.1 =
* Settings page for infrastructure, prompts, and widget appearance.
* Automatic background indexing on content save and delete.
* Bulk indexing from the admin page and via WP-CLI.
* Customizable widget primary color.
* Ukrainian and Russian translations.

= 1.0 =
* Initial release.

== Upgrade Notice ==

= 1.2 =
Adds the OpenAI API engine and Qdrant Cloud support. If you switch engines, re-create the Qdrant collection and re-index your content.
