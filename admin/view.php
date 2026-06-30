<?php
// Защита от прямого доступа к файлу
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Получаем текущие значения из базы или ставим дефолтные
$system_prompt          = get_option( 'my_ai_chat_system_prompt', "Ты — строгий и вежливый ассистент-консультант в интернет-магазине. Твоя задача — отвечать на вопросы пользователей на основе предоставленного КОНТЕКСТА товаров.\nПРАВИЛО ДЛЯ ССЫЛОК: Ты должен брать ссылку ТАКУЮ ЖЕ, как указано в поле 'Ссылка:'. Не изменяй её. Пиши название товара, а рядом в скобках ставь точную ссылку." );
$product_card_template  = get_option( 'my_ai_chat_product_card_template', "<strong>Есть такой товар!</strong><br>\nТовар: {title}<br>\nЦена: {price}<br>\nСсылка: <a href=\"{permalink}\" target=\"_blank\">Перейти к товару</a><br><br>" );
$context_template       = get_option( 'my_ai_chat_context_template', "Используй следующую информацию о товарах и страницах сайта для ответа на вопрос. Если в контексте нет нужного товара, скажи, что его нет в наличии." );
$ollama_url             = get_option( 'my_ai_chat_ollama_url', 'http://host.docker.internal:11434' );
$model_name             = get_option( 'my_ai_chat_model_name', 'qwen2.5:1.5b' );
$temperature            = get_option( 'my_ai_chat_temperature', '0.3' );
$qdrant_api_url         = get_option( 'my_ai_chat_qdrant_api_url', 'http://host.docker.internal:6333' );
$qdrant_collection_name = get_option( 'my_ai_chat_qdrant_collection_name', 'wp_products_collection' );
$embedding_vector_size  = get_option( 'my_ai_chat_embedding_vector_size', 768 );
$model_embed 	        = get_option( 'my_ai_chat_model_embed', 'nomic-embed-text');
$engine                 = get_option( 'my_ai_chat_engine', 'ollama' );
$use_system_answer      = get_option( 'my_ai_chat_use_system_answer', '1' );
?>

<style>
.ai-chat-admin-wrap {
    margin: 20px 20px 0 0;
    max-width: 1200px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
}
.ai-chat-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 24px;
    padding-bottom: 12px;
    border-bottom: 1px solid #dcdcde;
}
.ai-chat-header h1 {
    margin: 0;
    font-size: 23px;
    font-weight: 400;
    color: #1d2327;
    display: flex;
    align-items: center;
    gap: 10px;
}
.ai-chat-header h1 .dashicons {
    font-size: 28px;
    width: 28px;
    height: 28px;
    color: #2271b1;
    margin-top: 4px;
}
.ai-chat-layout {
    display: flex;
    gap: 24px;
    align-items: flex-start;
}
@media (max-width: 960px) {
    .ai-chat-layout {
        flex-direction: column;
    }
}
.ai-chat-main-content {
    flex: 3;
    display: flex;
    flex-direction: column;
    gap: 20px;
    min-width: 0; /* Prevents flex items from overflowing */
}
.ai-chat-sidebar {
    flex: 1.2;
    min-width: 300px;
    display: flex;
    flex-direction: column;
    gap: 20px;
}
@media (max-width: 960px) {
    .ai-chat-sidebar {
        width: 100%;
    }
}
.ai-chat-card {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    padding: 24px;
    box-sizing: border-box;
    margin-bottom: 20px;
    transition: box-shadow 0.2s ease-in-out, border-color 0.2s ease-in-out;
}
.ai-chat-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    border-color: #8c8f94;
}
.ai-chat-card-title {
    margin-top: 0;
    margin-bottom: 20px;
    padding-bottom: 10px;
    font-size: 16px;
    font-weight: 600;
    border-bottom: 1px solid #f0f0f1;
    color: #1d2327;
    display: flex;
    align-items: center;
    gap: 8px;
}
.ai-chat-card-title .dashicons {
    color: #2271b1;
    font-size: 20px;
    width: 20px;
    height: 20px;
}
.ai-chat-row {
    display: flex;
    flex-direction: column;
    margin-bottom: 16px;
}
.ai-chat-row:last-child {
    margin-bottom: 0;
}
.ai-chat-row label {
    font-weight: 600;
    margin-bottom: 6px;
    color: #2c3338;
}
.ai-chat-row input[type="text"],
.ai-chat-row input[type="url"],
.ai-chat-row input[type="number"],
.ai-chat-row select,
.ai-chat-row textarea {
    border: 1px solid #8c8f94;
    border-radius: 4px;
    background-color: #fff;
    color: #2c3338;
    box-shadow: 0 1px 2px rgba(0,0,0,0.02) inset;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    font-size: 13px;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}
.ai-chat-row input:focus,
.ai-chat-row select:focus,
.ai-chat-row textarea:focus {
    border-color: #2271b1;
    box-shadow: 0 0 0 1px #2271b1;
    outline: 2px solid transparent;
}
.ai-chat-row textarea.code-font,
.ai-chat-row input.code-font {
    font-family: Consolas, Monaco, monospace;
}
.ai-chat-row .description {
    margin-top: 6px;
    color: #646970;
    font-size: 12px;
    font-style: italic;
    line-height: 1.4;
}
.ai-chat-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
@media (max-width: 600px) {
    .ai-chat-grid {
        grid-template-columns: 1fr;
    }
}
.ai-chat-danger-card {
    background: #fffdf2;
    border: 1px solid #f5e7b8;
    border-left: 4px solid #dba617;
    border-radius: 8px;
    padding: 20px;
    box-sizing: border-box;
    margin-bottom: 20px;
}
.ai-chat-danger-card h4 {
    margin: 0 0 12px 0;
    color: #8c6b00;
    font-size: 15px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}
.ai-chat-danger-card h4 .dashicons {
    color: #dba617;
    font-size: 20px;
    width: 20px;
    height: 20px;
}
.ai-chat-danger-card p {
    margin: 0 0 16px 0;
    color: #5c4d21;
    font-size: 13px;
    line-height: 1.5;
}
.ai-chat-danger-card .warning-alert {
    color: #a87e00;
    font-weight: bold;
}
.ai-chat-save-container {
    margin-top: 10px;
    display: flex;
    justify-content: flex-start;
}
.ai-chat-action-card {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    padding: 24px;
    box-sizing: border-box;
    transition: box-shadow 0.2s ease-in-out, border-color 0.2s ease-in-out;
}
.ai-chat-action-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    border-color: #8c8f94;
}
.ai-chat-action-card h3 {
    margin-top: 0;
    margin-bottom: 12px;
    color: #1d2327;
    font-size: 16px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}
.ai-chat-action-card h3 .dashicons {
    color: #2271b1;
    font-size: 20px;
    width: 20px;
    height: 20px;
}
.ai-chat-action-card p {
    margin: 0 0 16px 0;
    color: #50575e;
    font-size: 13px;
    line-height: 1.5;
}
.ai-chat-action-card input[type="submit"] {
    width: 100%;
    justify-content: center;
    text-align: center;
}
</style>

<div class="ai-chat-admin-wrap">
    <div class="ai-chat-header">
        <h1><span class="dashicons dashicons-smart-phone"></span> Настройки локального AI чат-бота</h1>
    </div>

    <div class="ai-chat-layout">
        <!-- Левая колонка: Основные настройки -->
        <div class="ai-chat-main-content">
            <form method="post" action="options.php">
                <?php
                // Выводит скрытые поля безопасности (nonce, group_name и т.д.)
                settings_fields( 'my_ai_chat_settings_group' );
                do_settings_sections( 'my_ai_chat_settings_group' );
                ?>

                <!-- Карта: Основные настройки ИИ -->
                <div class="ai-chat-card">
                    <h3 class="ai-chat-card-title"><span class="dashicons dashicons-cpu"></span> Основные настройки Ассистента</h3>
                    
                    <div class="ai-chat-grid">
                        <div class="ai-chat-row">
                            <label for="my_ai_chat_engine">Движок ИИ (LLM Provider)</label>
                            <select name="my_ai_chat_engine" id="my_ai_chat_engine">
                                <option value="ollama" <?php selected( $engine, 'ollama' ); ?>>Ollama (Локальный Qwen)</option>
                                <option value="gpt" <?php selected( $engine, 'gpt' ); ?>>OpenAI API (ChatGPT)</option>
                            </select>
                            <p class="description">Какая нейросеть будет формулировать финальный ответ для покупателя.</p>
                        </div>

                        <div class="ai-chat-row">
                            <label for="my_ai_chat_ollama_url">Ollama API URL</label>
                            <input type="url" id="my_ai_chat_ollama_url" name="my_ai_chat_ollama_url" value="<?php echo esc_url( $ollama_url ); ?>" class="code-font">
                            <p class="description">Для Docker-контейнеров обычно используется <code>http://host.docker.internal:11434</code></p>
                        </div>

                        <div class="ai-chat-row">
                            <label for="my_ai_chat_temperature">Температура креативности (Temperature)</label>
                            <input type="number" id="my_ai_chat_temperature" name="my_ai_chat_temperature" value="<?php echo esc_attr( $temperature ); ?>" step="0.1" min="0" max="1">
                            <p class="description">Чем ниже значение (например, 0.2), тем строже бот следует контексту. Высокие значения добавят фантазии.</p>
                        </div>

                    </div>
                </div>

                <!-- Карта: Технические настройки Qdrant -->
                <div class="ai-chat-card">
                    <h3 class="ai-chat-card-title"><span class="dashicons dashicons-database"></span> Настройки подключения Qdrant</h3>
                    
                    <div class="ai-chat-row">
                        <label for="my_ai_chat_qdrant_api_url">Qdrant API URL</label>
                        <input type="url" id="my_ai_chat_qdrant_api_url" name="my_ai_chat_qdrant_api_url" value="<?php echo esc_url( $qdrant_api_url ); ?>" class="code-font">
                        <p class="description">Для Docker-контейнеров обычно используется <code>http://host.docker.internal:6333</code></p>
                    </div>

                    <div class="ai-chat-grid" style="margin-top: 16px;">
                        <div class="ai-chat-row">
                            <label for="my_ai_chat_qdrant_collection_name">Имя коллекции Qdrant</label>
                            <input type="text" id="my_ai_chat_qdrant_collection_name" name="my_ai_chat_qdrant_collection_name" value="<?php echo esc_attr( $qdrant_collection_name ); ?>" class="code-font">
                            <p class="description">Например: <code>wp_products_collection</code></p>
                        </div>

                        <div class="ai-chat-row">
                            <label for="my_ai_chat_embedding_vector_size">Размер вектора эмбеддинга</label>
                            <input type="number" id="my_ai_chat_embedding_vector_size" name="my_ai_chat_embedding_vector_size" value="<?php echo esc_attr( $embedding_vector_size ); ?>" step="1" min="1">
                            <p class="description">Например: <code>768</code></p>
                        </div>
                    </div>
                </div>

                <!-- Карта: Промпты -->
                <div class="ai-chat-card">
                    <h3 class="ai-chat-card-title"><span class="dashicons dashicons-editor-code"></span> Настройки промптов (Логика ответов)</h3>

                    <div class="ai-chat-row">
                        <label for="my_ai_chat_use_system_answer">Использовать ИИ для ответа</label>
                        <input type="checkbox" id="my_ai_chat_use_system_answer" name="my_ai_chat_use_system_answer" value="1" <?php checked( get_option( 'my_ai_chat_use_system_answer', '1' ), '1' ); ?> class="code-font">
                        <p class="description">Включить использование ИИ для ответов на вопросы. Если выключено, бот будет отвечать только из базы знаний.</p>
                    </div>

                    <div class="ai-chat-row">
                        <label for="my_ai_chat_model_name">Название модели LLM</label>
                        <input type="text" id="my_ai_chat_model_name" name="my_ai_chat_model_name" value="<?php echo esc_attr( $model_name ); ?>" class="code-font">
                        <p class="description">Например: <code>qwen2.5:1.5b</code>, <code>llama3:8b</code> или любая другая модель в Ollama.</p>
                    </div>
                    
                    <div class="ai-chat-row">
                        <label for="my_ai_chat_system_prompt">Системный промпт (System Prompt)</label>
                        <textarea id="my_ai_chat_system_prompt" name="my_ai_chat_system_prompt" rows="6" class="code-font" <?php if(!$use_system_answer){ echo 'readonly style="opacity: 0.5; background-color: #f0f0f1; cursor: not-allowed;"';}?> ><?php echo esc_textarea( $system_prompt ); ?></textarea>
                        <p class="description">Инструкции для модели: её роль, поведение, правила форматирования ссылок и язык общения.</p>
                    </div>

                    <div class="ai-chat-row" style="margin-top: 16px;">
                        <label for="my_ai_chat_context_template">Инструкция к контексту (RAG Context Template)</label>
                        <textarea id="my_ai_chat_context_template" name="my_ai_chat_context_template" rows="3" class="code-font" <?php if(!$use_system_answer){ echo 'readonly style="opacity: 0.5; background-color: #f0f0f1; cursor: not-allowed;"';}?>><?php echo esc_textarea( $context_template ); ?></textarea>
                        <p class="description">Этот текст будет добавляться в самое начало блока данных, которые бот вытащил из Qdrant.</p>
                    </div>

                    <div class="ai-chat-row" style="margin-top: 16px;">
                        <label for="my_ai_chat_product_card_template">Шаблон карточки товара (Режим без ИИ)</label>
                        <textarea id="my_ai_chat_product_card_template" name="my_ai_chat_product_card_template" rows="5" class="code-font"><?php echo esc_textarea( $product_card_template ); ?></textarea>
                        <p class="description">
                            HTML-шаблон для отображения товара в чате, когда ИИ отключён. Доступные плейсхолдеры:<br>
                            <code>{title}</code> — название товара &nbsp;|&nbsp; <code>{price}</code> — цена &nbsp;|&nbsp; <code>{permalink}</code> — ссылка на товар
                        </p>
                    </div>
                </div>

                <!-- Карта: Опасная зона (Модель эмбеддингов) -->
                <div class="ai-chat-danger-card">
                    <h4><span class="dashicons dashicons-warning"></span> Опасная зона (Векторный индекс)</h4>
                    <p>Настройки ниже определяют, как именно тексты товаров переводятся в математические векторы. Изменение модели эмбеддингов сделает старую базу данных несовместимой!</p>
                    
                    <div class="ai-chat-row">
                        <label for="my_ai_chat_model_embed">Модель для embedding:</label>
                        <input type="text" name="my_ai_chat_model_embed" id="my_ai_chat_model_embed" value="<?php echo esc_attr( $model_embed ); ?>" class="code-font">
                        <p class="description warning-alert">
                            При изменении этой модели вам ОБЯЗАТЕЛЬНО нужно запустить переиндексацию заново!
                        </p>
                    </div>
                </div>

                <div class="ai-chat-save-container">
                    <?php submit_button( 'Сохранить все настройки', 'primary', 'submit', false ); ?>
                </div>
            </form>
        </div>

        <!-- Правая колонка: Управление индексом -->
        <div class="ai-chat-sidebar">
            <div class="ai-chat-action-card">
                <h3><span class="dashicons dashicons-update"></span> Индексация контента</h3>
                <p>Запустите массовую переиндексацию всех товаров, страниц и постов в векторную базу Qdrant. Это выполняется в фоновом режиме через Cron-задачи WordPress.</p>
                
                <form method="post" action="">
                    <?php wp_nonce_field( 'ai_bot_mass_index_action', 'ai_bot_nonce' ); ?>
                    <input type="submit" name="ai_bot_start_mass_index" class="button button-primary button-large" value="Запустить переиндексацию">
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const useSystemAnswerCheckbox = document.getElementById('my_ai_chat_use_system_answer');
    const systemPromptTextarea = document.getElementById('my_ai_chat_system_prompt');
    const contextTemplateTextarea = document.getElementById('my_ai_chat_context_template');

    function toggleFields() {
        const isChecked = useSystemAnswerCheckbox.checked;
        if (isChecked) {
            systemPromptTextarea.removeAttribute('readonly');
            systemPromptTextarea.style.opacity = '1';
            systemPromptTextarea.style.backgroundColor = '';
            systemPromptTextarea.style.cursor = '';
            
            contextTemplateTextarea.removeAttribute('readonly');
            contextTemplateTextarea.style.opacity = '1';
            contextTemplateTextarea.style.backgroundColor = '';
            contextTemplateTextarea.style.cursor = '';
        } else {
            systemPromptTextarea.setAttribute('readonly', 'readonly');
            systemPromptTextarea.style.opacity = '0.5';
            systemPromptTextarea.style.backgroundColor = '#f0f0f1';
            systemPromptTextarea.style.cursor = 'not-allowed';
            
            contextTemplateTextarea.setAttribute('readonly', 'readonly');
            contextTemplateTextarea.style.opacity = '0.5';
            contextTemplateTextarea.style.backgroundColor = '#f0f0f1';
            contextTemplateTextarea.style.cursor = 'not-allowed';
        }
    }

    if (useSystemAnswerCheckbox && systemPromptTextarea && contextTemplateTextarea) {
        useSystemAnswerCheckbox.addEventListener('change', toggleFields);
    }
});
</script>