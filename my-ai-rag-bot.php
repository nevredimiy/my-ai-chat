<?php
/**
 * Plugin Name: My Custom AI RAG Bot
 * Description: AI chatbot with answers based on site content and WooCommerce products.
 * Version: 1.1
 * Text Domain: my-ai-chat
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'plugins_loaded', function() {
    load_plugin_textdomain( 'my-ai-chat', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

// ==========================================
// ДИНАМИЧЕСКИЕ НАСТРОЙКИ ИНФРАСТРУКТУРЫ
// ==========================================

function get_my_ai_chat_ollama_url() {
    $url = get_option( 'my_ai_chat_ollama_url', 'http://127.0.0.1:11434' );
    $url = untrailingslashit( $url );
    if ( substr( $url, -4 ) !== '/api' ) {
        $url .= '/api';
    }
    return $url;
}

function get_my_ai_chat_qdrant_url() {
    return untrailingslashit( get_option( 'my_ai_chat_qdrant_api_url', 'http://127.0.0.1:6333' ) );
}

function get_my_ai_chat_collection_name() {
    return get_option( 'my_ai_chat_qdrant_collection_name', 'wp_products_collection' );
}

function get_my_ai_chat_vector_size() {
    return (int) get_option( 'my_ai_chat_embedding_vector_size', 768 );
}

function get_my_ai_chat_model_embed() {
    return get_option( 'my_ai_chat_model_embed', 'nomic-embed-text' );
}

function my_ai_chat_default_system_prompt() {
    return __( "You are a strict and polite assistant-consultant in an online store. Your task is to answer user questions based on the provided product CONTEXT.\nLINK RULE: You must use the link EXACTLY as specified in the 'Link:' field. Do not modify it. Write the product name, and put the exact link in parentheses next to it.", 'my-ai-chat' );
}

function my_ai_chat_default_context_template() {
    return __( 'Use the following information about products and site pages to answer the question. If the context does not have the required product, say it is not in stock.', 'my-ai-chat' );
}

function my_ai_chat_default_product_card_template() {
    return __( "<strong>We have this product!</strong><br>\nProduct: {title}<br>\nPrice: {price}<br>\nLink: <a href=\"{permalink}\" target=\"_blank\">Go to product</a><br><br>", 'my-ai-chat' );
}

/**
 * Checks whether a specific model is installed in Ollama.
 */
function my_ai_chat_check_ollama_model( $model_name ) {
    $ollama_url = get_my_ai_chat_ollama_url();
    $tags_url = str_replace('/api/api', '/api', $ollama_url . '/tags');

    $response = wp_remote_get( $tags_url, array( 'timeout' => 5 ) );
    if ( is_wp_error( $response ) ) {
        return false;
    }

    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );

    if ( ! empty( $data['models'] ) ) {
        foreach ( $data['models'] as $model ) {
            if ( $model['name'] === $model_name || strpos( $model['name'], $model_name . ':' ) === 0 ) {
                return true;
            }
        }
    }
    return false;
}

// Plugin activation hook
register_activation_hook( __FILE__, 'ai_chat_initialize_vector_db' );

function ai_chat_initialize_vector_db() {
    $collection_url = get_my_ai_chat_qdrant_url() . '/collections/' . get_my_ai_chat_collection_name();

    $check_response = wp_remote_get( $collection_url );
    if ( ! is_wp_error( $check_response ) && wp_remote_retrieve_response_code( $check_response ) === 200 ) {
        return;
    }

    $body = array(
        'vectors' => array(
            'size'     => get_my_ai_chat_vector_size(),
            'distance' => 'Cosine'
        )
    );

    $response = wp_remote_request( $collection_url, array(
        'method'  => 'PUT',
        'headers' => array( 'Content-Type' => 'application/json' ),
        'body'    => json_encode( $body ),
        'timeout' => 10
    ) );

    if ( is_wp_error( $response ) ) {
        error_log( 'AI RAG Bot Error: Failed to connect to Qdrant: ' . $response->get_error_message() );
    }
}

// Enqueue styles and scripts
add_action( 'wp_enqueue_scripts', 'ai_chat_enqueue_assets' );
function ai_chat_enqueue_assets() {
    wp_enqueue_style( 'ai-chat-style', plugins_url( 'css/chat-style.css', __FILE__ ), array(), filemtime( plugin_dir_path( __FILE__ ) . 'css/chat-style.css' ) );
    wp_enqueue_script( 'ai-chat-script', plugins_url( 'js/chat-script.js', __FILE__ ), array(), filemtime( plugin_dir_path( __FILE__ ) . 'js/chat-script.js' ), true );
    wp_localize_script( 'ai-chat-script', 'aiChatL10n', array(
        'serverError'     => __( 'Server error', 'my-ai-chat' ),
        'noAnswer'        => __( 'No answer.', 'my-ai-chat' ),
        'connectionError' => __( 'Connection error with server.', 'my-ai-chat' ),
    ) );
}

// Output HTML template in footer
add_action( 'wp_footer', 'render_ai_chat_widget' );
function render_ai_chat_widget() {
    if ( file_exists( plugin_dir_path( __FILE__ ) . 'chat-template.php' ) ) {
        include plugin_dir_path( __FILE__ ) . 'chat-template.php';
    }
}

function my_ai_chat_get_ollama_url() {
    if ( defined( 'MY_AI_CHAT_OLLAMA_URL' ) && MY_AI_CHAT_OLLAMA_URL ) {
        return untrailingslashit( MY_AI_CHAT_OLLAMA_URL ) . '/v1/chat/completions';
    }
    if ( getenv( 'MY_AI_CHAT_OLLAMA_URL' ) ) {
        return untrailingslashit( getenv( 'MY_AI_CHAT_OLLAMA_URL' ) ) . '/v1/chat/completions';
    }

    static $cached_url = null;
    if ( $cached_url !== null ) {
        return $cached_url;
    }

    $candidates = array(
        'http://host.docker.internal:11434',
        'http://127.0.0.1:11434',
        'http://localhost:11434',
    );

    foreach ( $candidates as $base_url ) {
        if ( my_ai_chat_can_ping_ollama( $base_url . '/v1/models' ) ) {
            $cached_url = $base_url . '/v1/chat/completions';
            return $cached_url;
        }
    }

    $cached_url = 'http://localhost:11434/v1/chat/completions';
    return $cached_url;
}

function my_ai_chat_can_ping_ollama( $test_url ) {
    if ( ! function_exists( 'wp_remote_get' ) ) {
        return false;
    }
    $response = wp_remote_get( $test_url, array( 'timeout' => 2, 'redirection' => 2, 'httpversion' => '1.1', 'headers' => array( 'Accept' => 'application/json' ) ) );
    if ( is_wp_error( $response ) ) {
        return false;
    }
    $code = wp_remote_retrieve_response_code( $response );
    return in_array( $code, array( 200, 301, 302 ), true );
}

// REST API endpoint
add_action( 'rest_api_init', function () {
    register_rest_route( 'aibot/v1', '/chat', array(
        'methods'             => 'POST',
        'callback'            => 'ai_chat_rest_handle_message',
        'permission_callback' => '__return_true',
    ) );
} );

function ai_chat_rest_handle_message( WP_REST_Request $request ) {

    $params = $request->get_json_params();
    $user_question = !empty($params['question']) ? sanitize_text_field($params['question']) : '';

    if ( empty( $user_question ) ) {
        return new WP_REST_Response( array( 'answer' => __( 'Question is empty', 'my-ai-chat' ) ), 400 );
    }

    $bot_answer = ai_chat_generate_rag_response( $user_question );
    return new WP_REST_Response( array( 'answer' => $bot_answer ), 200 );
}

// ==========================================
// АВТОМАТИЧЕСКАЯ СИНХРОНИЗАЦИЯ (КРОН И ХУКИ)
// ==========================================

add_action( 'save_post', 'ai_chat_handle_post_save_schedule', 10, 3 );
add_action( 'woocommerce_update_product', 'ai_chat_handle_product_save_schedule', 10, 1 );

function ai_chat_handle_post_save_schedule( $post_id, $post, $update ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( wp_is_post_revision( $post_id ) ) return;
    if ( ! $post || $post->post_status !== 'publish' ) return;

    if ( $post->post_type === 'product' ) return;

    $allowed_types = array( 'post', 'page' );
    if ( ! in_array( $post->post_type, $allowed_types ) ) return;

    if ( ! wp_next_scheduled( 'my_ai_bot_index_single_post_cron', array( $post_id ) ) ) {
        wp_schedule_single_event( time(), 'my_ai_bot_index_single_post_cron', array( $post_id ) );
    }
}

function ai_chat_handle_product_save_schedule( $product_id ) {
    $product = wc_get_product( $product_id );
    if ( ! $product || $product->get_status() !== 'publish' ) return;

    if ( ! wp_next_scheduled( 'my_ai_bot_index_single_post_cron', array( $product_id ) ) ) {
        wp_schedule_single_event( time(), 'my_ai_bot_index_single_post_cron', array( $product_id ) );
    }
}

add_action( 'my_ai_bot_index_single_post_cron', 'ai_chat_execute_background_indexing' );
function ai_chat_execute_background_indexing( $post_id ) {
    $post = get_post( $post_id );
    if ( ! $post || $post->post_status !== 'publish' ) return;

    $title = $post->post_title;
    $content = wp_strip_all_tags( $post->post_content );
    $text_to_embed = "";

    if ( $post->post_type === 'product' && function_exists( 'wc_get_product' ) ) {
        $product = wc_get_product( $post_id );
        if ( $product ) {
            $price = wp_strip_all_tags( $product->get_price_html() );
            $price = html_entity_decode( $price, ENT_QUOTES, 'UTF-8' );
            $sku = $product->get_sku();

            $text_to_embed = __( 'Product', 'my-ai-chat' ) . ": {$title}. ";
            if ( ! empty( $sku ) ) $text_to_embed .= __( 'SKU', 'my-ai-chat' ) . ": {$sku}. ";
            $text_to_embed .= __( 'Price', 'my-ai-chat' ) . ": {$price}. " . __( 'Description', 'my-ai-chat' ) . ": {$content}";
        }
    } else {
        $type_label = ( $post->post_type === 'page' ) ? __( 'Page', 'my-ai-chat' ) : __( 'Article', 'my-ai-chat' );
        $text_to_embed = "{$type_label}: {$title}. " . __( 'Text', 'my-ai-chat' ) . ": {$content}";
    }

    if ( empty( $text_to_embed ) ) {
        error_log("AI Bot Cron Error: Empty embedding text (ID {$post_id})");
        return;
    }

    $ollama_response = wp_remote_post( get_my_ai_chat_ollama_url() . '/embeddings', array(
        'headers' => array( 'Content-Type' => 'application/json' ),
        'body'    => json_encode( array(
            'model'  => get_option( 'my_ai_chat_model_embed', 'nomic-embed-text' ),
            'prompt' => $text_to_embed
        ) ),
        'timeout' => 90
    ) );

    if ( is_wp_error( $ollama_response ) ) {
        error_log( 'AI Bot Cron Ollama Error: ' . $ollama_response->get_error_message() );
        return;
    }

    $ollama_body = json_decode( wp_remote_retrieve_body( $ollama_response ), true );
    $vector = $ollama_body['embedding'] ?? null;

    if ( ! is_array( $vector ) ) {
        error_log( 'AI Bot Cron Error: Ollama returned no vector for ID ' . $post_id );
        return;
    }

    $qdrant_url = get_my_ai_chat_qdrant_url() . '/collections/' . get_my_ai_chat_collection_name() . '/points?wait=true';
    $qdrant_body = array(
        'points' => array(
            array(
                'id'      => (int) $post_id,
                'vector'  => $vector,
                'payload' => array(
                    'post_type'  => $post->post_type,
                    'post_title' => $title,
                    'title'      => $title,
                    'text'       => $text_to_embed
                )
            )
        )
    );

    $qdrant_response = wp_remote_request( $qdrant_url, array(
        'method'  => 'PUT',
        'headers' => array( 'Content-Type' => 'application/json' ),
        'body'    => json_encode( $qdrant_body ),
        'timeout' => 15
    ) );

    if ( is_wp_error( $qdrant_response ) ) {
        error_log( "AI Bot Cron Qdrant Error for ID {$post_id}: " . $qdrant_response->get_error_message() );
    } else {
        $code = wp_remote_retrieve_response_code( $qdrant_response );
        if ( $code !== 200 ) {
            $body = wp_remote_retrieve_body( $qdrant_response );
            error_log( "AI Bot Cron Qdrant Error: Code {$code} for ID {$post_id}. Response: {$body}" );
        }
    }
}

// Auto-delete from vector DB when post deleted in WP
add_action( 'wp_trash_post', 'ai_chat_delete_post_from_qdrant' );
add_action( 'before_delete_post', 'ai_chat_delete_post_from_qdrant' );
function ai_chat_delete_post_from_qdrant( $post_id ) {
    $qdrant_url = get_my_ai_chat_qdrant_url() . '/collections/' . get_my_ai_chat_collection_name() . '/points/delete';
    $qdrant_body = array( 'points' => array( (int) $post_id ) );

    wp_remote_request( $qdrant_url, array(
        'method'  => 'POST',
        'headers' => array( 'Content-Type' => 'application/json' ),
        'body'    => json_encode( $qdrant_body ),
        'timeout' => 10
    ) );
    error_log( "AI Bot: Object ID {$post_id} deleted from Qdrant via hook." );
}

// ==========================================
// WP-CLI COMMANDS
// ==========================================

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    WP_CLI::add_command( 'ai-bot', 'AI_Bot_CLI_Commands' );
}

class AI_Bot_CLI_Commands {

    public function index( $args, $assoc_args ) {
        $embedding_model = get_option( 'my_ai_chat_model_embed', 'nomic-embed-text' );
        $llm_model       = get_option( 'my_ai_chat_model_name', 'qwen2.5:1.5b' );

        WP_CLI::line( __( 'Checking environment readiness...', 'my-ai-chat' ) );

        if ( ! my_ai_chat_check_ollama_model( $embedding_model ) ) {
            WP_CLI::error( sprintf( __( "Critical error: Embedding model '%s' not found!\nRun: ollama pull %s", 'my-ai-chat' ), $embedding_model, $embedding_model ) );
        }
        if ( ! my_ai_chat_check_ollama_model( $llm_model ) ) {
            WP_CLI::error( sprintf( __( "Critical error: Model '%s' not found!\nRun: ollama pull %s", 'my-ai-chat' ), $llm_model, $llm_model ) );
        }

        WP_CLI::line( __( 'Checking/creating collection in Qdrant...', 'my-ai-chat' ) );
        ai_chat_initialize_vector_db();

        $batch_size = isset( $assoc_args['batch_size'] ) ? intval( $assoc_args['batch_size'] ) : 50;
        if ( $batch_size <= 0 ) $batch_size = 50;

        WP_CLI::log( WP_CLI::colorize( '%B' . __( 'Starting bulk content indexing in Qdrant...', 'my-ai-chat' ) . '%n' ) );

        $query_args = array(
            'post_type'              => array( 'post', 'page', 'product' ),
            'post_status'            => 'publish',
            'posts_per_page'         => -1,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        );
        $products_query = new WP_Query( $query_args );
        $all_post_ids   = $products_query->posts;
        $total_count    = count( $all_post_ids );

        if ( $total_count === 0 ) {
            WP_CLI::error( __( 'No published items found for indexing.', 'my-ai-chat' ) );
        }

        WP_CLI::log( __( 'Items found for processing: ', 'my-ai-chat' ) . $total_count );
        $progress = \WP_CLI\Utils\make_progress_bar( __( 'Indexing', 'my-ai-chat' ), $total_count );
        $batches = array_chunk( $all_post_ids, $batch_size );

        foreach ( $batches as $batch ) {
            foreach ( $batch as $post_id ) {
                $post = get_post( $post_id );
                if ( ! $post ) { $progress->tick(); continue; }

                $title = $post->post_title;
                $content = wp_strip_all_tags( $post->post_content );
                $text_to_embed = "";

                if ( $post->post_type === 'product' && function_exists( 'wc_get_product' ) ) {
                    $product = wc_get_product( $post_id );
                    if ( $product ) {
                        $price = wp_strip_all_tags( $product->get_price_html() );
                        $price = html_entity_decode( $price, ENT_QUOTES, 'UTF-8' );
                        $sku = $product->get_sku();
                        $text_to_embed = __( 'Product', 'my-ai-chat' ) . ": {$title}. ";
                        if ( ! empty( $sku ) ) $text_to_embed .= __( 'SKU', 'my-ai-chat' ) . ": {$sku}. ";
                        $text_to_embed .= __( 'Price', 'my-ai-chat' ) . ": {$price}. " . __( 'Description', 'my-ai-chat' ) . ": {$content}";
                    }
                } else {
                    $type_label = ( $post->post_type === 'page' ) ? __( 'Page', 'my-ai-chat' ) : __( 'Article', 'my-ai-chat' );
                    $text_to_embed = "{$type_label}: {$title}. " . __( 'Text', 'my-ai-chat' ) . ": {$content}";
                }

                if ( empty( $text_to_embed ) ) { $progress->tick(); continue; }

                $ollama_response = wp_remote_post( get_my_ai_chat_ollama_url() . '/embeddings', array(
                    'headers' => array( 'Content-Type' => 'application/json' ),
                    'body'    => json_encode( array( 'model' => $embedding_model, 'prompt' => $text_to_embed ) ),
                    'timeout' => 90
                ) );

                if ( is_wp_error( $ollama_response ) ) { $progress->tick(); continue; }

                $ollama_body = json_decode( wp_remote_retrieve_body( $ollama_response ), true );
                $vector = $ollama_body['embedding'] ?? null;
                if ( ! is_array( $vector ) ) { $progress->tick(); continue; }

                $qdrant_body = array(
                    'points' => array(
                        array(
                            'id'      => intval( $post_id ),
                            'vector'  => $vector,
                            'payload' => array(
                                'post_type'  => $post->post_type,
                                'post_title' => $title,
                                'title'      => $title,
                                'text'       => $text_to_embed
                            )
                        )
                    )
                );

                $qdrant_url = get_my_ai_chat_qdrant_url() . '/collections/' . get_my_ai_chat_collection_name() . '/points?wait=true';

                $qdrant_response = wp_remote_request( $qdrant_url, array(
                    'method'  => 'PUT',
                    'headers' => array( 'Content-Type' => 'application/json' ),
                    'body'    => json_encode( $qdrant_body ),
                    'timeout' => 10
                ) );

                if ( is_wp_error( $qdrant_response ) ) {
                    WP_CLI::warning( "\n" . sprintf( __( 'cURL error for ID %d: ', 'my-ai-chat' ), $post_id ) . $qdrant_response->get_error_message() );
                } else {
                    $code = wp_remote_retrieve_response_code( $qdrant_response );
                    if ( $code !== 200 ) {
                        $body = wp_remote_retrieve_body( $qdrant_response );
                        WP_CLI::warning( "\n" . sprintf( __( 'Qdrant returned code %d for ID %d. Response: ', 'my-ai-chat' ), $code, $post_id ) . $body );
                    }
                }

                $progress->tick();
            }
            usleep( 50000 );
        }

        $progress->finish();
        WP_CLI::success( __( 'Indexing completed successfully!', 'my-ai-chat' ) );
    }

    public function search( $args, $assoc_args ) {
        if ( empty( $args[0] ) ) {
            WP_CLI::error( __( 'You forgot to specify a search query.', 'my-ai-chat' ) );
        }
        $query_text = $args[0];
        WP_CLI::log( __( 'Searching: ', 'my-ai-chat' ) . $query_text );
        $results = ai_chat_search_similar_content( $query_text, 3 );
        if ( empty( $results ) ) {
            WP_CLI::error( __( 'Nothing found.', 'my-ai-chat' ) );
        }
        foreach ( $results as $item ) {
            WP_CLI::log( sprintf( "- [ID %d] %s (Score: %0.4f)", $item['id'], $item['title'], $item['score'] ) );
        }
    }

    public function ask( $args, $assoc_args ) {
        $question = $args[0];
        WP_CLI::log( __( 'Question to bot: ', 'my-ai-chat' ) . $question );
        $response = ai_chat_generate_rag_response( $question );
        WP_CLI::log( "\n================ " . __( 'BOT ANSWER', 'my-ai-chat' ) . " ================" );
        WP_CLI::log( $response );
        WP_CLI::log( "============================================" );
    }
}

function ai_chat_search_similar_content( $query_text, $limit = 3 ) {
    if ( empty( $query_text ) ) return array();

    $ollama_response = wp_remote_post( get_my_ai_chat_ollama_url() . '/embeddings', array(
        'headers' => array( 'Content-Type' => 'application/json' ),
        'body'    => json_encode( array( 'model' => get_my_ai_chat_model_embed(), 'prompt' => $query_text ) ),
        'timeout' => 15
    ) );

    if ( is_wp_error( $ollama_response ) ) return array();

    $ollama_body = json_decode( wp_remote_retrieve_body( $ollama_response ), true );
    $query_vector = $ollama_body['embedding'] ?? null;
    if ( ! is_array( $query_vector ) ) return array();

    $qdrant_url = get_my_ai_chat_qdrant_url() . '/collections/' . get_my_ai_chat_collection_name() . '/points/search';
    $qdrant_body = array( 'vector' => $query_vector, 'limit' => $limit, 'with_payload' => true );

    $qdrant_response = wp_remote_post( $qdrant_url, array(
        'headers' => array( 'Content-Type' => 'application/json' ),
        'body'    => json_encode( $qdrant_body ),
        'timeout' => 10
    ) );

    if ( is_wp_error( $qdrant_response ) ) return array();

    $qdrant_results = json_decode( wp_remote_retrieve_body( $qdrant_response ), true );
    $points = $qdrant_results['result'] ?? array();

    $found_items = array();
    foreach ( $points as $point ) {
        $found_items[] = array(
            'id'    => $point['id'],
            'score' => $point['score'],
            'title' => $point['payload']['title'] ?? __( 'No title', 'my-ai-chat' ),
            'text'  => $point['payload']['text'] ?? '',
        );
    }
    return $found_items;
}

function ai_chat_generate_rag_response( $user_question ) {
    if ( ! function_exists( 'wc_get_product' ) && function_exists( 'WC' ) ) {
        include_once WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
    }

    error_log( "== AI BOT REST API START ==" );
    error_log( "User question: " . $user_question );

    $similar_items = ai_chat_search_similar_content( $user_question, 3 );
    error_log( "Qdrant search results: " . print_r( $similar_items, true ) );

    if ( empty( $similar_items ) ) {
        return __( 'Sorry, I found no information for your query on the site.', 'my-ai-chat' );
    }

   $context = '';
   $use_system_answer = get_option( 'my_ai_chat_use_system_answer', '1' );

    if ( ! $use_system_answer ) {
        foreach ( $similar_items as $item ) {
            $post_id = $item['id'];
            $permalink = get_permalink( $post_id );

            if ( get_post_type( $post_id ) === 'product' ) {
                $product = wc_get_product( $post_id );

                if ( $product ) {
                    $price = wp_strip_all_tags( $product->get_price_html() );
                    $price = html_entity_decode( $price, ENT_QUOTES, 'UTF-8' );

                    $title = get_the_title( $post_id );

                    $card_template = get_option(
                        'my_ai_chat_product_card_template',
                        my_ai_chat_default_product_card_template()
                    );
                    $context .= str_replace(
                        [ '{title}', '{price}', '{permalink}' ],
                        [ esc_html( $title ), esc_html( $price ), esc_url( $permalink ) ],
                        $card_template
                    );
                }
            }
        }

        return ! empty( $context ) ? $context : __( 'Sorry, nothing was found.', 'my-ai-chat' );
    } else {

        $context_text = "";
        foreach ( $similar_items as $item ) {
            $post_id = $item['id'];
            $permalink = get_permalink( $post_id );

            $payload_text = $item['text'] ?? $item['title'];

            $context_text .= sprintf( __( 'Document/Product (Link: %s):', 'my-ai-chat' ), $permalink ) . "\n{$payload_text}\n\n";
        }

        $system_prompt = get_option( 'my_ai_chat_system_prompt', my_ai_chat_default_system_prompt() );
        $llm_model     = get_option( 'my_ai_chat_model_name', 'qwen2.5:1.5b' );

        $full_prompt = __( 'System instruction:', 'my-ai-chat' ) . " {$system_prompt}\n\n" .
                       __( 'Site context:', 'my-ai-chat' ) . "\n{$context_text}\n" .
                       __( 'User question:', 'my-ai-chat' ) . " {$user_question}\n" .
                       __( 'Answer:', 'my-ai-chat' );

        $ollama_url = rtrim( get_my_ai_chat_ollama_url(), '/' ) . '/generate';

        $request_body = array(
            'model'  => $llm_model,
            'prompt' => $full_prompt,
            'stream' => false,
        );

        error_log("AI Bot Sending to Ollama URL: " . $ollama_url);
        error_log("AI Bot Sending Body: " . json_encode($request_body, JSON_UNESCAPED_UNICODE));

        $ollama_response = wp_remote_request( $ollama_url, array(
            'method'      => 'POST',
            'headers'     => array(
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json'
            ),
            'body'        => json_encode( $request_body ),
            'timeout'     => 90,
            'data_format' => 'body'
        ) );

        if ( is_wp_error( $ollama_response ) ) {
            error_log( 'AI Bot Ollama Error: ' . $ollama_response->get_error_message() );
            return __( 'Sorry, there was an error communicating with the neural network.', 'my-ai-chat' );
        }

        $response_code = wp_remote_retrieve_response_code( $ollama_response );
        $response_body = wp_remote_retrieve_body( $ollama_response );

        error_log("AI Bot Ollama Response Code: " . $response_code);
        error_log("AI Bot Ollama Response Body: " . $response_body);

        if ( $response_code !== 200 ) {
            return sprintf( __( 'Ollama Error (Code %d): ', 'my-ai-chat' ), $response_code ) . substr($response_body, 0, 100);
        }

        $ollama_data = json_decode( $response_body, true );
        $bot_response = $ollama_data['response'] ?? null;

        if ( empty( $bot_response ) ) {
            return __( 'Failed to parse the response from the model.', 'my-ai-chat' );
        }

        return trim( $bot_response );
    }
}

add_action( 'wp_ajax_ai_chat_message', 'ai_chat_ajax_handler' );
add_action( 'wp_ajax_nopriv_ai_chat_message', 'ai_chat_ajax_handler' );

function ai_chat_ajax_handler() {
    check_ajax_referer( 'ai_chat_nonce', 'nonce' );

    $message = isset( $_POST['message'] ) ? sanitize_text_field( $_POST['message'] ) : '';

    if ( empty( $message ) ) {
        wp_send_json_error( __( 'Empty message', 'my-ai-chat' ) );
    }

    $response = ai_chat_generate_rag_response( $message );

    wp_send_json_success( $response );
}

// Register admin menu
add_action( 'admin_menu', 'wp_my_ai_chat_options_page' );
function wp_my_ai_chat_options_page() {
    add_menu_page(
        'My AI Chat Settings',
        'AI Chat',
        'manage_options',
        'my_ai_chat',
        'wporg_my_ai_chat_options_page_html',
        'dashicons-format-status',
        40
    );
}

// Register options in WordPress database
add_action( 'admin_init', 'my_ai_chat_register_settings' );
function my_ai_chat_register_settings() {
    register_setting( 'my_ai_chat_settings_group', 'my_ai_chat_system_prompt' );
    register_setting( 'my_ai_chat_settings_group', 'my_ai_chat_context_template' );
    register_setting( 'my_ai_chat_settings_group', 'my_ai_chat_ollama_url' );
    register_setting( 'my_ai_chat_settings_group', 'my_ai_chat_model_name' );
    register_setting( 'my_ai_chat_settings_group', 'my_ai_chat_temperature' );
    register_setting( 'my_ai_chat_settings_group', 'my_ai_chat_qdrant_api_url' );
    register_setting( 'my_ai_chat_settings_group', 'my_ai_chat_qdrant_collection_name' );
    register_setting( 'my_ai_chat_settings_group', 'my_ai_chat_embedding_vector_size' );
    register_setting( 'my_ai_chat_settings_group', 'my_ai_chat_engine' );
    register_setting( 'my_ai_chat_settings_group', 'my_ai_chat_model_embed' );
    register_setting( 'my_ai_chat_settings_group', 'my_ai_chat_use_system_answer' );
    register_setting( 'my_ai_chat_settings_group', 'my_ai_chat_product_card_template' );
}

// Render settings page via view.php
function wporg_my_ai_chat_options_page_html() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    include plugin_dir_path(__FILE__) . 'admin/view.php';
}

// Handle mass indexing button click
add_action( 'admin_init', 'ai_chat_handle_mass_indexing_button' );

function ai_chat_handle_mass_indexing_button() {
    if ( ! isset( $_POST['ai_bot_start_mass_index'] ) ) {
        return;
    }

    if ( ! check_admin_referer( 'ai_bot_mass_index_action', 'ai_bot_nonce' ) || ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have sufficient permissions to perform this action.', 'my-ai-chat' ) );
    }

    $args = array(
        'post_type'      => array( 'post', 'page', 'product' ),
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    );

    $all_post_ids = get_posts( $args );

    if ( ! empty( $all_post_ids ) ) {
        $count = 0;
        foreach ( $all_post_ids as $post_id ) {
            if ( ! wp_next_scheduled( 'my_ai_bot_index_single_post_cron', array( $post_id ) ) ) {
                wp_schedule_single_event( time() + $count, 'my_ai_bot_index_single_post_cron', array( $post_id ) );
                $count++;
            }
        }

        add_action( 'admin_notices', function() use ( $count ) {
            echo '<div class="notice notice-success is-dismissible"><p>' .
                 sprintf( __( '<strong>AI Bot:</strong> Successfully scheduled indexing for %d objects. Tasks are running in the background.', 'my-ai-chat' ), $count ) .
                 '</p></div>';
        });
    } else {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-warning is-dismissible"><p>' .
                 __( '<strong>AI Bot:</strong> No content found for indexing.', 'my-ai-chat' ) .
                 '</p></div>';
        });
    }
}
