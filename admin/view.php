<?php
// Защита от прямого доступа к файлу
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Получаем текущие значения из базы или ставим дефолтные
$system_prompt   = get_option( 'my_ai_chat_system_prompt', "Ты — строгий и вежливый ассистент-консультант в интернет-магазине. Твоя задача — отвечать на вопросы пользователей на основе предоставленного КОНТЕКСТА товаров.\nПРАВИЛО ДЛЯ ССЫЛОК: Ты должен брать ссылку ТАКУЮ ЖЕ, как указано в поле 'Ссылка:'. Не изменяй её. Пиши название товара, а рядом в скобках ставь точную ссылку." );
$context_template = get_option( 'my_ai_chat_context_template', "Используй следующую информацию о товарах и страницах сайта для ответа на вопрос. Если в контексте нет нужного товара, скажи, что его нет в наличии." );
$ollama_url      = get_option( 'my_ai_chat_ollama_url', 'http://host.docker.internal:11434' );
$model_name      = get_option( 'my_ai_chat_model_name', 'qwen2.5:1.5b' );
$temperature     = get_option( 'my_ai_chat_temperature', '0.3' );
$qdrant_api_url  = get_option( 'my_ai_chat_qdrant_api_url', 'http://host.docker.internal:6333' );
$qdrant_collection_name     = get_option( 'my_ai_chat_qdrant_collection_name', 'wp_products_collection' );
$embedding_vector_size     = get_option( 'my_ai_chat_embedding_vector_size', 768 );
$model_embed 	 = get_option( 'my_ai_chat_model_embed', 'nomic-embed-text');
?>

<div class="wrap">
    <h1><span class="dashicons dashicons-smart-phone" style="font-size: 32px; height: 32px; width: 32px; margin-right: 10px;"></span> Настройки локального AI чат-бота</h1>
    <hr>

    <form method="post" action="options.php">
        <?php
        // Выводит скрытые поля безопасности (nonce, group_name и т.д.)
        settings_fields( 'my_ai_chat_settings_group' );
        do_settings_sections( 'my_ai_chat_settings_group' );
        ?>

        <h2>🧠 Настройки Промптов (Логика ответов)</h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="my_ai_chat_system_prompt">Системный промпт (System Prompt)</label></th>
                <td>
                    <textarea id="my_ai_chat_system_prompt" name="my_ai_chat_system_prompt" rows="6" cols="80" class="large-text code"><?php echo esc_textarea( $system_prompt ); ?></textarea>
                    <p class="description">Инструкции для модели: её роль, поведение, правила форматирования ссылок и язык общения.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="my_ai_chat_context_template">Инструкция к контексту (RAG Context)</label></th>
                <td>
                    <textarea id="my_ai_chat_context_template" name="my_ai_chat_context_template" rows="3" cols="80" class="large-text code"><?php echo esc_textarea( $context_template ); ?></textarea>
                    <p class="description">Этот текст будет добавляться в самое начало блока данных, которые бот вытащил из Qdrant.</p>
                </td>
            </tr>
        </table>

        <h2>⚙️ Технические настройки Ollama</h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="my_ai_chat_ollama_url">Ollama API URL</label></th>
                <td>
                    <input type="url" id="my_ai_chat_ollama_url" name="my_ai_chat_ollama_url" value="<?php echo esc_url( $ollama_url ); ?>" class="regular-text code">
                    <p class="description">Для Docker-контейнеров обычно используется <code>http://host.docker.internal:11434</code></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="my_ai_chat_model_name">Название модели LLM</label></th>
                <td>
                    <input type="text" id="my_ai_chat_model_name" name="my_ai_chat_model_name" value="<?php echo esc_attr( $model_name ); ?>" class="regular-text code">
                    <p class="description">Например: <code>qwen2.5:1.5b</code>, <code>llama3:8b</code> или любая другая модель, скачанная на локальный сервер Ollama.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="my_ai_chat_temperature">Температура креативности (Temperature)</label></th>
                <td>
                    <input type="number" id="my_ai_chat_temperature" name="my_ai_chat_temperature" value="<?php echo esc_attr( $temperature ); ?>" step="0.1" min="0" max="1" class="small-text">
                    <p class="description">Чем ниже значение (например, 0.2), тем строже бот следует контексту и точнее выводит ссылки. Высокие значения добавят фантазии.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="my_ai_chat_temperature">Модель для embending</label></th>
                <td>
                    <input type="text" id="my_ai_chat_model_embed" name="my_ai_chat_model_embed" value="<?php echo esc_attr( $model_embed ); ?>" class="regular-text code">
                    <p class="description">МОдель для создания embed</p>
                </td>
            </tr>
        </table>
        <h2>⚙️ Технические настройки QDRant</h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="my_ai_chat_qdrant_api_url">QDRant API URL</label></th>
                <td>
                    <input type="url" id="my_ai_chat_qdrant_api_url" name="my_ai_chat_qdrant_api_url" value="<?php echo esc_url( $qdrant_api_url ); ?>" class="regular-text code">
                    <p class="description">Для Docker-контейнеров обычно используется <code>http://host.docker.internal:6333</code></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="my_ai_chat_qdrant_collection_name">QDRant Collection Name</label></th>
                <td>
                    <input type="text" id="my_ai_chat_qdrant_collection_name" name="my_ai_chat_qdrant_collection_name" value="<?php echo esc_attr( $qdrant_collection_name ); ?>" class="regular-text code">
                    <p class="description">Например, <code>wp_products_collection</code></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="my_ai_chat_embedding_vector_size">Embedding Vector Size</label></th>
                <td>
                    <input type="number" id="my_ai_chat_embedding_vector_size" name="my_ai_chat_embedding_vector_size" value="<?php echo esc_attr( $embedding_vector_size ); ?>" step="1" min="1" size="5" class="small-text">
                    <p class="description">Например, <code>768</code></p>
                </td>
            </tr>
        </table>	

        <?php submit_button( 'Сохранить настройки' ); ?>
    </form>
</div>
<div class="wrap">
    <h2>Управление индексом AI Bot</h2>
    <div class="card" style="max-width: 600px; margin-top: 20px; padding: 15px;">
        <h3>Полная переиндексация сайта</h3>
        <p>Нажмите кнопку ниже, чтобы запустить массовую переиндексацию всех товаров, страниц и записей в векторную базу Qdrant в фоновом режиме.</p>
        
        <form method="post" action="">
            <?php wp_nonce_field( 'ai_bot_mass_index_action', 'ai_bot_nonce' ); ?>
            <input type="submit" name="ai_bot_start_mass_index" id="submit" class="button button-primary button-large" value="Запустить индексацию базы данных">
        </form>
    </div>
</div>