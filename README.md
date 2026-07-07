# My AI Chat (RAG-бот для WordPress и WooCommerce)

> Документация для разработчиков. Пользовательское описание для каталога WordPress.org — в файле [readme.txt](readme.txt).

Плагин чат-ассистента для WordPress и WooCommerce, реализующий архитектуру **RAG (Retrieval-Augmented Generation)** с выбором AI-движка:

* **Ollama** (по умолчанию) — полностью локальная инфраструктура, без платных API;
* **OpenAI API** — достаточно ввести API-ключ, локальный AI-сервер не нужен.

Плагин векторизует контент сайта (товары, статьи, страницы), сохраняет векторы в Qdrant (self-hosted или **Qdrant Cloud** с API-ключом) и использует LLM для генерации ответов на основе контекста с реальными ссылками на товары.

---

## 🏗️ Архитектура

1. **WordPress + WooCommerce** — источник данных (контент, цены, артикулы, атрибуты, ссылки).
2. **Эмбеддинги** — Ollama (`nomic-embed-text`, 768 измерений) или OpenAI (`text-embedding-3-small`, 1536 измерений).
3. **Qdrant** — векторная база данных, локальная или Qdrant Cloud. Коллекция (по умолчанию `wp_products_collection`) создаётся автоматически при активации плагина.
4. **LLM** — Ollama (`qwen2.5:1.5b`) или OpenAI (`gpt-4o-mini`) генерирует финальный ответ на основе контекста из Qdrant. Оба движка вызываются через OpenAI-совместимый API `/chat/completions`.

> ⚠️ Векторы разных моделей несовместимы: при смене движка или модели эмбеддингов нужно обновить размер вектора, пересоздать коллекцию Qdrant и переиндексировать контент.

---

## 🚀 Запуск локального окружения

### 1. Qdrant
```bash
docker run -d --name qdrant-local \
  -p 6333:6333 -p 6334:6334 \
  -v qdrant_storage:/qdrant/storage \
  qdrant/qdrant
```

### 2. Модели в Ollama
```bash
# Модель эмбеддингов
ollama pull nomic-embed-text

# LLM для генерации ответов
ollama pull qwen2.5:1.5b
```

### 3. Активация плагина
При активации плагин отправляет PUT-запрос в Qdrant и создаёт коллекцию с заданной размерностью векторов и косинусной метрикой (если коллекция ещё не существует).

---

## ⚙️ Конфигурация

Все параметры настраиваются в админке: **AI Chat** в меню WordPress:

* **AI Engine** — Ollama (локально) или OpenAI API (по ключу); поля переключаются автоматически
* **Ollama API URL** (по умолчанию `http://127.0.0.1:11434`; для WordPress в Docker — `http://host.docker.internal:11434`)
* **OpenAI API Key**, модель чата (`gpt-4o-mini`) и модель эмбеддингов (`text-embedding-3-small`) — для движка OpenAI
* **Qdrant API URL** (по умолчанию `http://127.0.0.1:6333`; для Qdrant Cloud — URL кластера)
* **Qdrant API Key** — пусто для локального Qdrant, обязателен для Qdrant Cloud
* Имя коллекции Qdrant и размерность векторов (768 для nomic-embed-text, 1536 для text-embedding-3-small)
* Системный промпт, шаблон контекста, шаблон карточки товара
* Режим «без ИИ» (ответ только карточками товаров из базы знаний)
* Основной цвет виджета

Настройки хранятся в опциях WordPress с префиксом `my_ai_chat_`.

---

## 🔄 Индексация данных

### Автоматическая (хуки + WP-Cron)
Плагин слушает `save_post` и `woocommerce_update_product`. При публикации/обновлении записи, страницы или товара ставится фоновая задача `my_ai_bot_index_single_post_cron`:
- контент очищается от HTML;
- для товаров добавляются SKU, цена, категории и атрибуты;
- текст отправляется в Ollama за эмбеддингом, вектор сохраняется в Qdrant (ID поста = ID точки).

При удалении записи (`wp_trash_post`, `before_delete_post`) точка удаляется из Qdrant.

### Массовая
* Кнопка **«Start Re-indexing»** на странице настроек (ставит фоновые cron-задачи).
* WP-CLI:
```bash
# Локально
wp ai-bot index --batch_size=50

# WordPress в Docker
docker exec -it wp_php_wp_68 php wp-cli.phar ai-bot index --batch_size=50 --allow-root
```

---

## 🧪 Тестирование через WP-CLI

```bash
# Семантический поиск (топ-3 по векторному сходству)
docker exec -it wp_php_wp_68 php wp-cli.phar ai-bot search "червона кепка" --allow-root

# Полный RAG-цикл: вопрос → поиск → генерация ответа LLM
docker exec -it wp_php_wp_68 php wp-cli.phar ai-bot ask "Я шукаю червону кепку, у вас є?" --allow-root
```

---

## 💬 Виджет чата

Плагин выводит виджет чата в футере всех страниц и регистрирует REST-эндпоинт:
- `POST /wp-json/aibot/v1/chat` (или `/index.php?rest_route=/aibot/v1/chat` без ЧПУ)
- Тело запроса: `{"question": "..."}`, ответ: `{"answer": "..."}`

Также есть AJAX-обработчик `ai_chat_message` (admin-ajax) с nonce-проверкой.

---

## 🛠️ Отладка

Диагностические сообщения пишутся в лог PHP **только при включённом `WP_DEBUG`** (функция `my_ai_chat_log()`).

Частые проблемы:

* **«Connection error with server» в чате** — проверьте, что Ollama запущен, модель загружена (`ollama list`) и URL из настроек доступен из контейнера WordPress.
* **Векторы не сохраняются в Qdrant** — проверьте доступность Qdrant на порту 6333 и наличие модели эмбеддингов; смотрите `wp-content/debug.log`.
* **404 на `/wp-json/...`** — отключены ЧПУ; используйте `/index.php?rest_route=/aibot/v1/chat`.
* **Сменили модель эмбеддингов** — обязательно выполните полную переиндексацию и проверьте размерность векторов.

---

## 📦 Публикация на WordPress.org

* Каноничное описание плагина — `readme.txt` (английский, формат WordPress.org).
* Перед сборкой zip исключайте `.git/` и служебные файлы.
* Проверка соответствия требованиям каталога:
```bash
docker exec wp_php_wp_68 php wp-cli.phar plugin check my-ai-chat --allow-root
```
(нужен установленный плагин [Plugin Check](https://wordpress.org/plugins/plugin-check/)).
