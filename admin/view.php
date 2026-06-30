<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$system_prompt          = get_option( 'my_ai_chat_system_prompt', my_ai_chat_default_system_prompt() );
$product_card_template  = get_option( 'my_ai_chat_product_card_template', my_ai_chat_default_product_card_template() );
$context_template       = get_option( 'my_ai_chat_context_template', my_ai_chat_default_context_template() );
$ollama_url             = get_option( 'my_ai_chat_ollama_url', 'http://host.docker.internal:11434' );
$model_name             = get_option( 'my_ai_chat_model_name', 'qwen2.5:1.5b' );
$temperature            = get_option( 'my_ai_chat_temperature', '0.3' );
$qdrant_api_url         = get_option( 'my_ai_chat_qdrant_api_url', 'http://host.docker.internal:6333' );
$qdrant_collection_name = get_option( 'my_ai_chat_qdrant_collection_name', 'wp_products_collection' );
$embedding_vector_size  = get_option( 'my_ai_chat_embedding_vector_size', 768 );
$model_embed            = get_option( 'my_ai_chat_model_embed', 'nomic-embed-text' );
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
    min-width: 0;
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
        <h1><span class="dashicons dashicons-smart-phone"></span> <?php esc_html_e( 'Local AI Chatbot Settings', 'my-ai-chat' ); ?></h1>
    </div>

    <div class="ai-chat-layout">
        <!-- Left column: Main settings -->
        <div class="ai-chat-main-content">
            <form method="post" action="options.php">
                <?php
                settings_fields( 'my_ai_chat_settings_group' );
                do_settings_sections( 'my_ai_chat_settings_group' );
                ?>

                <!-- Card: AI Core Settings -->
                <div class="ai-chat-card">
                    <h3 class="ai-chat-card-title"><span class="dashicons dashicons-cpu"></span> <?php esc_html_e( 'AI Assistant Core Settings', 'my-ai-chat' ); ?></h3>

                    <div class="ai-chat-grid">
                        <div class="ai-chat-row">
                            <label for="my_ai_chat_engine"><?php esc_html_e( 'AI Engine (LLM Provider)', 'my-ai-chat' ); ?></label>
                            <select name="my_ai_chat_engine" id="my_ai_chat_engine">
                                <option value="ollama" <?php selected( $engine, 'ollama' ); ?>><?php esc_html_e( 'Ollama (Local Qwen)', 'my-ai-chat' ); ?></option>
                                <option value="gpt" <?php selected( $engine, 'gpt' ); ?>><?php esc_html_e( 'OpenAI API (ChatGPT)', 'my-ai-chat' ); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e( 'Which neural network will formulate the final answer for the customer.', 'my-ai-chat' ); ?></p>
                        </div>

                        <div class="ai-chat-row">
                            <label for="my_ai_chat_ollama_url"><?php esc_html_e( 'Ollama API URL', 'my-ai-chat' ); ?></label>
                            <input type="url" id="my_ai_chat_ollama_url" name="my_ai_chat_ollama_url" value="<?php echo esc_url( $ollama_url ); ?>" class="code-font">
                            <p class="description"><?php esc_html_e( 'For Docker containers, usually use', 'my-ai-chat' ); ?> <code>http://host.docker.internal:11434</code></p>
                        </div>

                        <div class="ai-chat-row">
                            <label for="my_ai_chat_temperature"><?php esc_html_e( 'Creativity Temperature (Temperature)', 'my-ai-chat' ); ?></label>
                            <input type="number" id="my_ai_chat_temperature" name="my_ai_chat_temperature" value="<?php echo esc_attr( $temperature ); ?>" step="0.1" min="0" max="1">
                            <p class="description"><?php esc_html_e( 'Lower values (e.g. 0.2) make the bot stick more closely to the context. Higher values add creativity.', 'my-ai-chat' ); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Card: Qdrant Settings -->
                <div class="ai-chat-card">
                    <h3 class="ai-chat-card-title"><span class="dashicons dashicons-database"></span> <?php esc_html_e( 'Qdrant Connection Settings', 'my-ai-chat' ); ?></h3>

                    <div class="ai-chat-row">
                        <label for="my_ai_chat_qdrant_api_url"><?php esc_html_e( 'Qdrant API URL', 'my-ai-chat' ); ?></label>
                        <input type="url" id="my_ai_chat_qdrant_api_url" name="my_ai_chat_qdrant_api_url" value="<?php echo esc_url( $qdrant_api_url ); ?>" class="code-font">
                        <p class="description"><?php esc_html_e( 'For Docker containers, usually use', 'my-ai-chat' ); ?> <code>http://host.docker.internal:6333</code></p>
                    </div>

                    <div class="ai-chat-grid" style="margin-top: 16px;">
                        <div class="ai-chat-row">
                            <label for="my_ai_chat_qdrant_collection_name"><?php esc_html_e( 'Qdrant Collection Name', 'my-ai-chat' ); ?></label>
                            <input type="text" id="my_ai_chat_qdrant_collection_name" name="my_ai_chat_qdrant_collection_name" value="<?php echo esc_attr( $qdrant_collection_name ); ?>" class="code-font">
                            <p class="description"><?php esc_html_e( 'For example:', 'my-ai-chat' ); ?> <code>wp_products_collection</code></p>
                        </div>

                        <div class="ai-chat-row">
                            <label for="my_ai_chat_embedding_vector_size"><?php esc_html_e( 'Embedding Vector Size', 'my-ai-chat' ); ?></label>
                            <input type="number" id="my_ai_chat_embedding_vector_size" name="my_ai_chat_embedding_vector_size" value="<?php echo esc_attr( $embedding_vector_size ); ?>" step="1" min="1">
                            <p class="description"><?php esc_html_e( 'For example:', 'my-ai-chat' ); ?> <code>768</code></p>
                        </div>
                    </div>
                </div>

                <!-- Card: Prompt Settings -->
                <div class="ai-chat-card">
                    <h3 class="ai-chat-card-title"><span class="dashicons dashicons-editor-code"></span> <?php esc_html_e( 'Prompt Settings (Response Logic)', 'my-ai-chat' ); ?></h3>

                    <div class="ai-chat-row">
                        <label for="my_ai_chat_use_system_answer"><?php esc_html_e( 'Use AI for answering', 'my-ai-chat' ); ?></label>
                        <input type="checkbox" id="my_ai_chat_use_system_answer" name="my_ai_chat_use_system_answer" value="1" <?php checked( get_option( 'my_ai_chat_use_system_answer', '1' ), '1' ); ?> class="code-font">
                        <p class="description"><?php esc_html_e( 'Enable AI usage for answering questions. If disabled, the bot will only respond from the knowledge base.', 'my-ai-chat' ); ?></p>
                    </div>

                    <div class="ai-chat-row">
                        <label for="my_ai_chat_model_name"><?php esc_html_e( 'LLM Model Name', 'my-ai-chat' ); ?></label>
                        <input type="text" id="my_ai_chat_model_name" name="my_ai_chat_model_name" value="<?php echo esc_attr( $model_name ); ?>" class="code-font">
                        <p class="description"><?php esc_html_e( 'For example:', 'my-ai-chat' ); ?> <code>qwen2.5:1.5b</code>, <code>llama3:8b</code></p>
                    </div>

                    <div class="ai-chat-row">
                        <label for="my_ai_chat_system_prompt"><?php esc_html_e( 'System Prompt', 'my-ai-chat' ); ?></label>
                        <textarea id="my_ai_chat_system_prompt" name="my_ai_chat_system_prompt" rows="6" class="code-font" <?php if ( ! $use_system_answer ) { echo 'readonly style="opacity: 0.5; background-color: #f0f0f1; cursor: not-allowed;"'; } ?>><?php echo esc_textarea( $system_prompt ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Instructions for the model: its role, behavior, formatting rules for links, and the language of communication.', 'my-ai-chat' ); ?></p>
                    </div>

                    <div class="ai-chat-row" style="margin-top: 16px;">
                        <label for="my_ai_chat_context_template"><?php esc_html_e( 'RAG Context Template', 'my-ai-chat' ); ?></label>
                        <textarea id="my_ai_chat_context_template" name="my_ai_chat_context_template" rows="3" class="code-font" <?php if ( ! $use_system_answer ) { echo 'readonly style="opacity: 0.5; background-color: #f0f0f1; cursor: not-allowed;"'; } ?>><?php echo esc_textarea( $context_template ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'This text will be added at the very beginning of the data block that the bot retrieved from Qdrant.', 'my-ai-chat' ); ?></p>
                    </div>

                    <div class="ai-chat-row" style="margin-top: 16px;">
                        <label for="my_ai_chat_product_card_template"><?php esc_html_e( 'Product Card Template (No-AI Mode)', 'my-ai-chat' ); ?></label>
                        <textarea id="my_ai_chat_product_card_template" name="my_ai_chat_product_card_template" rows="5" class="code-font"><?php echo esc_textarea( $product_card_template ); ?></textarea>
                        <p class="description">
                            <?php esc_html_e( 'HTML template for displaying a product in chat when AI is disabled. Available placeholders:', 'my-ai-chat' ); ?><br>
                            <code>{title}</code> — <?php esc_html_e( 'product name', 'my-ai-chat' ); ?> &nbsp;|&nbsp;
                            <code>{price}</code> — <?php esc_html_e( 'price', 'my-ai-chat' ); ?> &nbsp;|&nbsp;
                            <code>{permalink}</code> — <?php esc_html_e( 'link to product', 'my-ai-chat' ); ?>
                        </p>
                    </div>
                </div>

                <!-- Card: Danger Zone -->
                <div class="ai-chat-danger-card">
                    <h4><span class="dashicons dashicons-warning"></span> <?php esc_html_e( 'Danger Zone (Vector Index)', 'my-ai-chat' ); ?></h4>
                    <p><?php esc_html_e( 'The settings below determine how product texts are converted into mathematical vectors. Changing the embedding model will make the old database incompatible!', 'my-ai-chat' ); ?></p>

                    <div class="ai-chat-row">
                        <label for="my_ai_chat_model_embed"><?php esc_html_e( 'Embedding Model:', 'my-ai-chat' ); ?></label>
                        <input type="text" name="my_ai_chat_model_embed" id="my_ai_chat_model_embed" value="<?php echo esc_attr( $model_embed ); ?>" class="code-font">
                        <p class="description warning-alert">
                            <?php esc_html_e( 'When changing this model, you MUST run re-indexing again!', 'my-ai-chat' ); ?>
                        </p>
                    </div>
                </div>

                <div class="ai-chat-save-container">
                    <?php submit_button( __( 'Save All Settings', 'my-ai-chat' ), 'primary', 'submit', false ); ?>
                </div>
            </form>
        </div>

        <!-- Right column: Index management -->
        <div class="ai-chat-sidebar">
            <div class="ai-chat-action-card">
                <h3><span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Content Indexing', 'my-ai-chat' ); ?></h3>
                <p><?php esc_html_e( 'Run mass re-indexing of all products, pages and posts into the Qdrant vector database. This runs in the background via WordPress Cron jobs.', 'my-ai-chat' ); ?></p>

                <form method="post" action="">
                    <?php wp_nonce_field( 'ai_bot_mass_index_action', 'ai_bot_nonce' ); ?>
                    <input type="submit" name="ai_bot_start_mass_index" class="button button-primary button-large" value="<?php esc_attr_e( 'Start Re-indexing', 'my-ai-chat' ); ?>">
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
