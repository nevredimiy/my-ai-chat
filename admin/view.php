<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$my_ai_chat_system_prompt          = get_option( 'my_ai_chat_system_prompt', my_ai_chat_default_system_prompt() );
$my_ai_chat_product_card_template  = get_option( 'my_ai_chat_product_card_template', my_ai_chat_default_product_card_template() );
$my_ai_chat_context_template       = get_option( 'my_ai_chat_context_template', my_ai_chat_default_context_template() );
$my_ai_chat_ollama_url             = get_option( 'my_ai_chat_ollama_url', 'http://host.docker.internal:11434' );
$my_ai_chat_model_name             = get_option( 'my_ai_chat_model_name', 'qwen2.5:1.5b' );
$my_ai_chat_temperature            = get_option( 'my_ai_chat_temperature', '0.3' );
$my_ai_chat_qdrant_api_url         = get_option( 'my_ai_chat_qdrant_api_url', 'http://host.docker.internal:6333' );
$my_ai_chat_qdrant_collection_name = get_option( 'my_ai_chat_qdrant_collection_name', 'wp_products_collection' );
$my_ai_chat_embedding_vector_size  = get_option( 'my_ai_chat_embedding_vector_size', 768 );
$my_ai_chat_model_embed            = get_option( 'my_ai_chat_model_embed', 'nomic-embed-text' );
$my_ai_chat_engine                 = get_option( 'my_ai_chat_engine', 'ollama' );
$my_ai_chat_openai_api_key         = get_option( 'my_ai_chat_openai_api_key', '' );
$my_ai_chat_openai_model           = get_option( 'my_ai_chat_openai_model', 'gpt-4o-mini' );
$my_ai_chat_openai_model_embed     = get_option( 'my_ai_chat_openai_model_embed', 'text-embedding-3-small' );
$my_ai_chat_qdrant_api_key         = get_option( 'my_ai_chat_qdrant_api_key', '' );
$my_ai_chat_use_system_answer      = get_option( 'my_ai_chat_use_system_answer', '1' );
$my_ai_chat_primary_color          = get_option( 'my_ai_chat_primary_color', '#0073aa' );
if ( empty( $my_ai_chat_primary_color ) ) {
    $my_ai_chat_primary_color = '#0073aa';
}
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
                                <option value="ollama" <?php selected( $my_ai_chat_engine, 'ollama' ); ?>><?php esc_html_e( 'Ollama (Local Qwen)', 'my-ai-chat' ); ?></option>
                                <option value="gpt" <?php selected( $my_ai_chat_engine, 'gpt' ); ?>><?php esc_html_e( 'OpenAI API (ChatGPT)', 'my-ai-chat' ); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e( 'Which neural network will formulate the final answer for the customer.', 'my-ai-chat' ); ?></p>
                        </div>

                        <div class="ai-chat-row mac-engine-ollama">
                            <label for="my_ai_chat_ollama_url"><?php esc_html_e( 'Ollama API URL', 'my-ai-chat' ); ?></label>
                            <input type="url" id="my_ai_chat_ollama_url" name="my_ai_chat_ollama_url" value="<?php echo esc_url( $my_ai_chat_ollama_url ); ?>" class="code-font">
                            <p class="description"><?php esc_html_e( 'For Docker containers, usually use', 'my-ai-chat' ); ?> <code>http://host.docker.internal:11434</code></p>
                        </div>

                        <div class="ai-chat-row mac-engine-openai">
                            <label for="my_ai_chat_openai_api_key"><?php esc_html_e( 'OpenAI API Key', 'my-ai-chat' ); ?></label>
                            <input type="password" id="my_ai_chat_openai_api_key" name="my_ai_chat_openai_api_key" value="<?php echo esc_attr( $my_ai_chat_openai_api_key ); ?>" class="code-font" autocomplete="new-password" placeholder="sk-...">
                            <p class="description"><?php esc_html_e( 'Required when the OpenAI engine is selected. Create a key at platform.openai.com.', 'my-ai-chat' ); ?></p>
                        </div>

                        <div class="ai-chat-row mac-engine-openai">
                            <label for="my_ai_chat_openai_model"><?php esc_html_e( 'OpenAI Chat Model', 'my-ai-chat' ); ?></label>
                            <input type="text" id="my_ai_chat_openai_model" name="my_ai_chat_openai_model" value="<?php echo esc_attr( $my_ai_chat_openai_model ); ?>" class="code-font">
                            <p class="description"><?php esc_html_e( 'For example:', 'my-ai-chat' ); ?> <code>gpt-4o-mini</code>, <code>gpt-4o</code></p>
                        </div>

                        <div class="ai-chat-row mac-engine-openai">
                            <label for="my_ai_chat_openai_model_embed"><?php esc_html_e( 'OpenAI Embedding Model', 'my-ai-chat' ); ?></label>
                            <input type="text" id="my_ai_chat_openai_model_embed" name="my_ai_chat_openai_model_embed" value="<?php echo esc_attr( $my_ai_chat_openai_model_embed ); ?>" class="code-font">
                            <p class="description"><?php esc_html_e( 'For example:', 'my-ai-chat' ); ?> <code>text-embedding-3-small</code>. <?php esc_html_e( 'When changing the engine or embedding model, update the vector size, re-create the Qdrant collection and run re-indexing!', 'my-ai-chat' ); ?></p>
                        </div>

                        <div class="ai-chat-row">
                            <label for="my_ai_chat_temperature"><?php esc_html_e( 'Creativity Temperature (Temperature)', 'my-ai-chat' ); ?></label>
                            <input type="number" id="my_ai_chat_temperature" name="my_ai_chat_temperature" value="<?php echo esc_attr( $my_ai_chat_temperature ); ?>" step="0.1" min="0" max="1">
                            <p class="description"><?php esc_html_e( 'Lower values (e.g. 0.2) make the bot stick more closely to the context. Higher values add creativity.', 'my-ai-chat' ); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Card: Qdrant Settings -->
                <div class="ai-chat-card">
                    <h3 class="ai-chat-card-title"><span class="dashicons dashicons-database"></span> <?php esc_html_e( 'Qdrant Connection Settings', 'my-ai-chat' ); ?></h3>

                    <div class="ai-chat-row">
                        <label for="my_ai_chat_qdrant_api_url"><?php esc_html_e( 'Qdrant API URL', 'my-ai-chat' ); ?></label>
                        <input type="url" id="my_ai_chat_qdrant_api_url" name="my_ai_chat_qdrant_api_url" value="<?php echo esc_url( $my_ai_chat_qdrant_api_url ); ?>" class="code-font">
                        <p class="description">
    <?php esc_html_e( 'For Docker containers, usually use', 'my-ai-chat' ); ?> 
    <code>http://host.docker.internal:6333</code>. 
    <?php 
    echo sprintf(
        /* translators: %s: URL to Qdrant Cloud cluster overview */
        esc_html__( 'For %s, use your cluster URL.', 'my-ai-chat' ),
        '<a href="https://cloud.qdrant.io/" target="_blank" rel="noopener">' . esc_html__( 'Qdrant Cloud', 'my-ai-chat' ) . '</a>'
    ); 
    ?>
</p>

<p class="description" style="margin-top: 5px; font-style: italic; color: #64748b;">
    <strong><?php esc_html_e( 'Quick Setup:', 'my-ai-chat' ); ?></strong> 
    <?php 
    echo sprintf(
        /* translators: 1: Link to sign up, 2: Closing link tag */
        esc_html__( 'Sign up at %1$sQdrant Cloud Accounts%2$s, create a cluster, and you will find your Endpoint (Cluster URL) and API Key in the cluster overview dashboard.', 'my-ai-chat' ),
        '<a href="https://cloud.qdrant.io/" target="_blank" rel="noopener">',
        '</a>'
    );
    ?>
</p>
                    </div>

                    <div class="ai-chat-row" style="margin-top: 16px;">
                        <label for="my_ai_chat_qdrant_api_key"><?php esc_html_e( 'Qdrant API Key', 'my-ai-chat' ); ?></label>
                        <input type="password" id="my_ai_chat_qdrant_api_key" name="my_ai_chat_qdrant_api_key" value="<?php echo esc_attr( $my_ai_chat_qdrant_api_key ); ?>" class="code-font" autocomplete="new-password">
                        <p class="description"><?php esc_html_e( 'Leave empty for a local Qdrant without authentication. Required for Qdrant Cloud.', 'my-ai-chat' ); ?></p>
                    </div>

                    <div class="ai-chat-grid" style="margin-top: 16px;">
                        <div class="ai-chat-row">
                            <label for="my_ai_chat_qdrant_collection_name"><?php esc_html_e( 'Qdrant Collection Name', 'my-ai-chat' ); ?></label>
                            <input type="text" id="my_ai_chat_qdrant_collection_name" name="my_ai_chat_qdrant_collection_name" value="<?php echo esc_attr( $my_ai_chat_qdrant_collection_name ); ?>" class="code-font">
                            <p class="description"><?php esc_html_e( 'For example:', 'my-ai-chat' ); ?> <code>wp_products_collection</code></p>
                        </div>

                        <div class="ai-chat-row">
                            <label for="my_ai_chat_embedding_vector_size"><?php esc_html_e( 'Embedding Vector Size', 'my-ai-chat' ); ?></label>
                            <input type="number" id="my_ai_chat_embedding_vector_size" name="my_ai_chat_embedding_vector_size" value="<?php echo esc_attr( $my_ai_chat_embedding_vector_size ); ?>" step="1" min="1">
                            <p class="description"><code>768</code> — nomic-embed-text (Ollama), <code>1536</code> — text-embedding-3-small (OpenAI)</p>
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

                    <div class="ai-chat-row mac-engine-ollama">
                        <label for="my_ai_chat_model_name"><?php esc_html_e( 'LLM Model Name', 'my-ai-chat' ); ?></label>
                        <input type="text" id="my_ai_chat_model_name" name="my_ai_chat_model_name" value="<?php echo esc_attr( $my_ai_chat_model_name ); ?>" class="code-font" <?php if ( ! $my_ai_chat_use_system_answer ) { echo 'readonly style="opacity: 0.5; background-color: #f0f0f1; cursor: not-allowed;"'; } ?>>
                        <p class="description"><?php esc_html_e( 'For example:', 'my-ai-chat' ); ?> <code>qwen2.5:1.5b</code>, <code>llama3:8b</code></p>
                    </div>

                    <div class="ai-chat-row">
                        <label for="my_ai_chat_system_prompt"><?php esc_html_e( 'System Prompt', 'my-ai-chat' ); ?></label>
                        <textarea id="my_ai_chat_system_prompt" name="my_ai_chat_system_prompt" rows="6" class="code-font" <?php if ( ! $my_ai_chat_use_system_answer ) { echo 'readonly style="opacity: 0.5; background-color: #f0f0f1; cursor: not-allowed;"'; } ?>><?php echo esc_textarea( $my_ai_chat_system_prompt ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Instructions for the model: its role, behavior, formatting rules for links, and the language of communication.', 'my-ai-chat' ); ?></p>
                    </div>

                    <div class="ai-chat-row" style="margin-top: 16px;">
                        <label for="my_ai_chat_context_template"><?php esc_html_e( 'RAG Context Template', 'my-ai-chat' ); ?></label>
                        <textarea id="my_ai_chat_context_template" name="my_ai_chat_context_template" rows="3" class="code-font" <?php if ( ! $my_ai_chat_use_system_answer ) { echo 'readonly style="opacity: 0.5; background-color: #f0f0f1; cursor: not-allowed;"'; } ?>><?php echo esc_textarea( $my_ai_chat_context_template ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'This text will be added at the very beginning of the data block that the bot retrieved from Qdrant.', 'my-ai-chat' ); ?></p>
                    </div>

                    <div class="ai-chat-row" style="margin-top: 16px;">
                        <label for="my_ai_chat_product_card_template"><?php esc_html_e( 'Product Card Template (No-AI Mode)', 'my-ai-chat' ); ?></label>
                        <textarea id="my_ai_chat_product_card_template" name="my_ai_chat_product_card_template" rows="5" class="code-font"><?php echo esc_textarea( $my_ai_chat_product_card_template ); ?></textarea>
                        <p class="description">
                            <?php esc_html_e( 'HTML template for displaying a product in chat when AI is disabled. Available placeholders:', 'my-ai-chat' ); ?><br>
                            <code>{title}</code> — <?php esc_html_e( 'product name', 'my-ai-chat' ); ?> &nbsp;|&nbsp;
                            <code>{price}</code> — <?php esc_html_e( 'price', 'my-ai-chat' ); ?> &nbsp;|&nbsp;
                            <code>{permalink}</code> — <?php esc_html_e( 'link to product', 'my-ai-chat' ); ?>
                        </p>
                    </div>
                </div>

                <!-- Card: Widget Appearance -->
                <div class="ai-chat-card">
                    <h3 class="ai-chat-card-title"><span class="dashicons dashicons-admin-appearance"></span> <?php esc_html_e( 'Widget Customization', 'my-ai-chat' ); ?></h3>

                    <div class="ai-chat-row">
                        <label for="my_ai_chat_primary_color"><?php esc_html_e( 'Primary Color', 'my-ai-chat' ); ?></label>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <input type="color" id="my_ai_chat_primary_color_picker" value="<?php echo esc_attr( $my_ai_chat_primary_color ); ?>" style="width: 50px; height: 30px; padding: 0; border: 1px solid #8c8f94; border-radius: 4px; cursor: pointer;">
                            <input type="text" id="my_ai_chat_primary_color" name="my_ai_chat_primary_color" value="<?php echo esc_attr( get_option( 'my_ai_chat_primary_color', '#0073aa' ) ); ?>" class="code-font" style="width: 120px;" placeholder="#0073aa">
                        </div>
                        <p class="description"><?php esc_html_e( 'Choose the primary color for the chat widget header and toggle button. If left empty, #0073aa will be used.', 'my-ai-chat' ); ?></p>
                    </div>
                </div>

                <!-- Card: Danger Zone -->
                <div class="ai-chat-danger-card">
                    <h4><span class="dashicons dashicons-warning"></span> <?php esc_html_e( 'Danger Zone (Vector Index)', 'my-ai-chat' ); ?></h4>
                    <p><?php esc_html_e( 'The settings below determine how product texts are converted into mathematical vectors. Changing the embedding model will make the old database incompatible!', 'my-ai-chat' ); ?></p>

                    <div class="ai-chat-row mac-engine-ollama">
                        <label for="my_ai_chat_model_embed"><?php esc_html_e( 'Embedding Model:', 'my-ai-chat' ); ?></label>
                        <input type="text" name="my_ai_chat_model_embed" id="my_ai_chat_model_embed" value="<?php echo esc_attr( $my_ai_chat_model_embed ); ?>" class="code-font">
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
    const modelNameInput = document.getElementById('my_ai_chat_model_name');
    const systemPromptTextarea = document.getElementById('my_ai_chat_system_prompt');
    const contextTemplateTextarea = document.getElementById('my_ai_chat_context_template');

    function toggleFields() {
        const isChecked = useSystemAnswerCheckbox.checked;
        if (isChecked) {
            systemPromptTextarea.removeAttribute('readonly');
            systemPromptTextarea.style.opacity = '1';
            systemPromptTextarea.style.backgroundColor = '';
            systemPromptTextarea.style.cursor = '';

            modelNameInput.removeAttribute('readonly');
            modelNameInput.style.opacity = '1';
            modelNameInput.style.backgroundColor = '';
            modelNameInput.style.cursor = '';

            contextTemplateTextarea.removeAttribute('readonly');
            contextTemplateTextarea.style.opacity = '1';
            contextTemplateTextarea.style.backgroundColor = '';
            contextTemplateTextarea.style.cursor = '';
        } else {
            systemPromptTextarea.setAttribute('readonly', 'readonly');
            systemPromptTextarea.style.opacity = '0.5';
            systemPromptTextarea.style.backgroundColor = '#f0f0f1';
            systemPromptTextarea.style.cursor = 'not-allowed';

            modelNameInput.setAttribute('readonly', 'readonly');
            modelNameInput.style.opacity = '0.5';
            modelNameInput.style.backgroundColor = '#f0f0f1';
            modelNameInput.style.cursor = 'not-allowed';

            contextTemplateTextarea.setAttribute('readonly', 'readonly');
            contextTemplateTextarea.style.opacity = '0.5';
            contextTemplateTextarea.style.backgroundColor = '#f0f0f1';
            contextTemplateTextarea.style.cursor = 'not-allowed';
        }
    }

    if (useSystemAnswerCheckbox && systemPromptTextarea && contextTemplateTextarea && modelNameInput) {
        useSystemAnswerCheckbox.addEventListener('change', toggleFields);
    }

    // Show/hide engine-specific fields (Ollama vs OpenAI)
    const engineSelect = document.getElementById('my_ai_chat_engine');

    function toggleEngineFields() {
        const isGpt = engineSelect.value === 'gpt';
        document.querySelectorAll('.mac-engine-openai').forEach(function(el) {
            el.style.display = isGpt ? '' : 'none';
        });
        document.querySelectorAll('.mac-engine-ollama').forEach(function(el) {
            el.style.display = isGpt ? 'none' : '';
        });
    }

    if (engineSelect) {
        engineSelect.addEventListener('change', toggleEngineFields);
        toggleEngineFields();
    }

    // Sync primary color picker and text input
    const colorPicker = document.getElementById('my_ai_chat_primary_color_picker');
    const colorInput = document.getElementById('my_ai_chat_primary_color');

    if (colorPicker && colorInput) {
        colorPicker.addEventListener('input', function() {
            colorInput.value = colorPicker.value;
        });
        colorInput.addEventListener('input', function() {
            const hex = colorInput.value.trim();
            if (/^#[0-9A-F]{6}$/i.test(hex)) {
                colorPicker.value = hex;
            }
        });
    }
});
</script>
