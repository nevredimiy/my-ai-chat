<?php
/**
 * Plugin Name: My Custom AI RAG Bot
 * Description: Самописный чат-бот с ответами по базе сайта и товарам WooCommerce.
 * Version: 1.1
 */

if ( ! defined( 'ABSPATH' ) ) exit;

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

/**
 * Проверяет, установлена ли конкретная модель в Ollama.
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

// Хук активации плагина
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
        error_log( 'AI RAG Bot Error: Не удалось связаться с Qdrant: ' . $response->get_error_message() );
    }
}

// Подключаем стили и скрипты
add_action( 'wp_enqueue_scripts', 'ai_chat_enqueue_assets' );
function ai_chat_enqueue_assets() {
    wp_enqueue_style( 'ai-chat-style', plugins_url( 'css/chat-style.css', __FILE__ ), array(), filemtime( plugin_dir_path( __FILE__ ) . 'css/chat-style.css' ) );
    wp_enqueue_script( 'ai-chat-script', plugins_url( 'js/chat-script.js', __FILE__ ), array(), filemtime( plugin_dir_path( __FILE__ ) . 'js/chat-script.js' ), true );
}

// Выводим HTML-шаблон в футер
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

// Создаем эндпоинт для чата
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
        return new WP_REST_Response( array( 'answer' => 'Вопрос пуст' ), 400 );
    }

    $bot_answer = ai_chat_generate_rag_response( $user_question );
    return new WP_REST_Response( array( 'answer' => $bot_answer ), 200 );
}

// ==========================================
// АВТОМАТИЧЕСКАЯ СИНХРОНИЗАЦИЯ (КРОН И ХУКИ)
// ==========================================

// 1. Хуки планирования при сохранении контента
add_action( 'save_post', 'ai_chat_handle_post_save_schedule', 10, 3 );
add_action( 'woocommerce_update_product', 'ai_chat_handle_product_save_schedule', 10, 1 ); // Родной хук для товаров

function ai_chat_handle_post_save_schedule( $post_id, $post, $update ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( wp_is_post_revision( $post_id ) ) return;
    if ( ! $post || $post->post_status !== 'publish' ) return;

    // Для товаров используем отдельный хук woocommerce_update_product
    if ( $post->post_type === 'product' ) return;

    $allowed_types = array( 'post', 'page' );
    if ( ! in_array( $post->post_type, $allowed_types ) ) return;

    // Планируем задачу (если её ещё нет в очереди)
    if ( ! wp_next_scheduled( 'my_ai_bot_index_single_post_cron', array( $post_id ) ) ) {
        wp_schedule_single_event( time(), 'my_ai_bot_index_single_post_cron', array( $post_id ) );
    }
}

// Отдельная функция для отлова сохранения товаров WooCommerce
function ai_chat_handle_product_save_schedule( $product_id ) {
    $product = wc_get_product( $product_id );
    if ( ! $product || $product->get_status() !== 'publish' ) return;

    if ( ! wp_next_scheduled( 'my_ai_bot_index_single_post_cron', array( $product_id ) ) ) {
        wp_schedule_single_event( time(), 'my_ai_bot_index_single_post_cron', array( $product_id ) );
    }
}

// 2. Выполнение фоновой индексации
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
            
            $text_to_embed = "Товар: {$title}. ";
            if ( ! empty( $sku ) ) $text_to_embed .= "Артикул: {$sku}. ";
            $text_to_embed .= "Цена: {$price}. Описание: {$content}"; // Исправили 'Prices:' на 'Цена:' как в CLI
        }
    } else {
        $type_label = ( $post->post_type === 'page' ) ? 'Страница' : 'Статья';
        $text_to_embed = "{$type_label}: {$title}. Текст: {$content}";
    }

    if ( empty( $text_to_embed ) ) {
        error_log("AI Bot Cron Error: Текст для эмбеддинга пуст (ID {$post_id})");
        return;
    }

    // Запрос в Ollama
    $ollama_response = wp_remote_post( get_my_ai_chat_ollama_url() . '/embeddings', array(
        'headers' => array( 'Content-Type' => 'application/json' ),
        'body'    => json_encode( array(
            'model'  => get_option( 'my_ai_chat_model_embed', 'nomic-embed-text' ), // Перевели на дефолтную опцию
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
        error_log( 'AI Bot Cron Error: Ollama не вернула вектор для ID ' . $post_id );
        return;
    }
    
    // Отправка в Qdrant
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
        'method'  => 'PUT', // ИЗМЕНИЛИ НА PUT, чтобы Qdrant принимал /points
        'headers' => array( 'Content-Type' => 'application/json' ),
        'body'    => json_encode( $qdrant_body ),
        'timeout' => 15
    ) );

    if ( is_wp_error( $qdrant_response ) ) {
        error_log( "AI Bot Cron Qdrant Error для ID {$post_id}: " . $qdrant_response->get_error_message() );
    } else {
        $code = wp_remote_retrieve_response_code( $qdrant_response );
        if ( $code !== 200 ) {
            $body = wp_remote_retrieve_body( $qdrant_response );
            error_log( "AI Bot Cron Qdrant Error: Код {$code} для ID {$post_id}. Ответ: {$body}" );
        }
    }
}

// 3. Автоматическое УДАЛЕНИЕ из векторной базы при удалении в WP
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
    error_log( "AI Bot: Объект ID {$post_id} удален из Qdrant через хук." );
}

// ==========================================
// РЕГИСТРАЦИЯ И СТРУКТУРА WP-CLI КОМАНДЫ
// ==========================================

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    WP_CLI::add_command( 'ai-bot', 'AI_Bot_CLI_Commands' );
}

class AI_Bot_CLI_Commands {

    public function index( $args, $assoc_args ) {
        $embedding_model = get_option( 'my_ai_chat_model_embed', 'nomic-embed-text' );
        $llm_model       = get_option( 'my_ai_chat_model_name', 'qwen2.5:1.5b' );

        WP_CLI::line( "Проверка готовности окружения..." );

        if ( ! my_ai_chat_check_ollama_model( $embedding_model ) ) {
            WP_CLI::error( "Критическая ошибка: Модель эмбеддингов '{$embedding_model}' не найдена!\nВыполните: ollama pull {$embedding_model}" );
        }
        if ( ! my_ai_chat_check_ollama_model( $llm_model ) ) {
            WP_CLI::error( "Критическая ошибка: Модель '{$llm_model}' не найдена!\nВыполните: ollama pull {$llm_model}" );
        }

        WP_CLI::line( "Проверяем/создаем коллекцию в Qdrant..." );
        ai_chat_initialize_vector_db();

        $batch_size = isset( $assoc_args['batch_size'] ) ? intval( $assoc_args['batch_size'] ) : 50;
        if ( $batch_size <= 0 ) $batch_size = 50;
        
        WP_CLI::log( WP_CLI::colorize( "%BЗапуск массовой индексации контента в Qdrant...%n" ) );
        
        $query_args = array(
            'post_type'              => array( 'post', 'page', 'product' ),
            'post_status'            => 'publish',
            'posts_per_page'         => -1,
            'fields'                 => 'ids',
            'no_found_rows'          => true, // Отключаем подсчет страниц для ускорения
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        );
        $products_query = new WP_Query( $query_args );
        $all_post_ids   = $products_query->posts;
        $total_count    = count( $all_post_ids );

        if ( $total_count === 0 ) {
            WP_CLI::error( "Нет опубликованных объектов для индексации." );
        }

        WP_CLI::log( "Найдено объектов для обработки: " . $total_count );
        $progress = \WP_CLI\Utils\make_progress_bar( 'Индексация', $total_count );
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
                        $text_to_embed = "Товар: {$title}. ";
                        if ( ! empty( $sku ) ) $text_to_embed .= "Артикул: {$sku}. ";
                        $text_to_embed .= "Цена: {$price}. Описание: {$content}";
                    }
                } else {
                    $type_label = ( $post->post_type === 'page' ) ? 'Страница' : 'Статья';
                    $text_to_embed = "{$type_label}: {$title}. Текст: {$content}";
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

                // ФОРМИРУЕМ ПРАВИЛЬНОЕ ТЕЛО ДЛЯ QDRANT
                $qdrant_body = array(
                    'points' => array(
                        array(
                            'id'      => intval( $post_id ),
                            'vector'  => $vector,
                            'payload' => array(
                                'post_type'  => $post->post_type,
                                'post_title' => $title, // Сохраняем оригинальное название для вывода в поиске
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
                    WP_CLI::warning( "\nОшибка cURL для ID {$post_id}: " . $qdrant_response->get_error_message() );
                } else {
                    $code = wp_remote_retrieve_response_code( $qdrant_response );
                    if ( $code !== 200 ) {
                        $body = wp_remote_retrieve_body( $qdrant_response );
                        WP_CLI::warning( "\nQdrant вернул код {$code} для ID {$post_id}. Ответ: {$body}" );
                    }
                }

                $progress->tick();
            }
            usleep( 50000 );
        }

        $progress->finish();
        WP_CLI::success( "Индексация успешно завершена!" );
    }

    public function search( $args, $assoc_args ) {
        if ( empty( $args[0] ) ) {
            WP_CLI::error( "Ви забыли вказати пошуковий запит." );
        }
        $query_text = $args[0];
        WP_CLI::log( "Ищем: " . $query_text );
        $results = ai_chat_search_similar_content( $query_text, 3 );
        if ( empty( $results ) ) {
            WP_CLI::error( "Ничего не найдено." );
        }
        foreach ( $results as $item ) {
            WP_CLI::log( sprintf( "- [ID %d] %s (Схожесть: %0.4f)", $item['id'], $item['title'], $item['score'] ) );
        }
    }

    public function ask( $args, $assoc_args ) {
        $question = $args[0];
        WP_CLI::log( "Вопрос боту: " . $question );
        $response = ai_chat_generate_rag_response( $question );
        WP_CLI::log( "\n================ ОТВЕТ БОТА ================" );
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
            'title' => $point['payload']['title'] ?? 'Без названия'
        );
    }
    return $found_items;
}

function ai_chat_generate_rag_response( $user_question ) {
    if ( ! function_exists( 'wc_get_product' ) && function_exists( 'WC' ) ) {
        include_once WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
    }

    error_log( "== AI BOT REST API START ==" );
    error_log( "Вопрос пользователя: " . $user_question );

    $similar_items = ai_chat_search_similar_content( $user_question, 3 );
    error_log( "Результаты поиска из Qdrant: " . print_r( $similar_items, true ) );
    
    if ( empty( $similar_items ) ) {
        return "К сожалению, я не нашёл информации по вашему запросу на сайте.";
    }

   $context = '';
    foreach ( $similar_items as $item ) {
        $post_id = $item['id'];
        $permalink = get_permalink( $post_id );
        
        if ( get_post_type( $post_id ) === 'product' ) {
            $product = wc_get_product( $post_id );
            
            if ( $product ) {
                // Очищаем цену от лишней разметки woo, оставляя чистый текст (например, "30.00 ₴")
                $price = wp_strip_all_tags( $product->get_price_html() );
                $price = html_entity_decode( $price, ENT_QUOTES, 'UTF-8' );
                
                $title = get_the_title( $post_id );

                // Формируем красивый HTML-блок для фронтенда
                $context .= '<strong>Есть такой товар!</strong><br>';
                $context .= 'Товар: ' . esc_html( $title ) . '<br>';
                $context .= 'Цена: ' . esc_html( $price ) . '<br>';
                $context .= 'Ссылка: <a href="' . esc_url( $permalink ) . '" target="_blank">Перейти к товару</a><br><br>';
            }
        }
    }

    return !empty($context) ? $context : 'К сожалению, ничего не найдено.';

    // 1. Собираем контекст из найденных объектов Qdrant
    $context_text = "";
    foreach ( $similar_items as $item ) {
        $post_id = $item['id'];
        $permalink = get_permalink( $post_id );
        
        // Берем текст из payload (или title, если текста нет)
        $payload_text = $item['text'] ?? $item['title'];
        
        $context_text .= "Документ/Товар (Ссылка: {$permalink}):\n{$payload_text}\n\n";
    }

    // 2. Берём настройки промпта и модели из опций WP
    $system_prompt = get_option( 'my_ai_chat_system_prompt', 'Ты — полезный ассистент магазина. Отвечай коротко и по делу. Если в контексте есть подходящие товары, обязательно давай на них прямые ссылки.' );
    $llm_model     = get_option( 'my_ai_chat_model_name', 'qwen2.5:1.5b' );

    // Формируем финальный промпт для Ollama /api/generate
    $full_prompt = "Системная инструкция: {$system_prompt}\n\nКонтекст сайта:\n{$context_text}\nВопрос пользователя: {$user_question}\nОтвет:";

    // 3. Делаем запрос к Ollama
    $ollama_url = rtrim( get_my_ai_chat_ollama_url(), '/' ) . '/generate';

    $request_body = array(
        'model'  => $llm_model,
        'prompt' => $full_prompt,
        'stream' => false, // Ждем ответ целиком
    );

    error_log("AI Bot Sending to Ollama URL: " . $ollama_url);
    error_log("AI Bot Sending Body: " . json_encode($request_body, JSON_UNESCAPED_UNICODE));

    $ollama_response = wp_remote_request( $ollama_url, array(
        'method'      => 'POST', // Явно задаем метод POST через wp_remote_request
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
        return 'Извините, произошла ошибка связи с нейросети.';
    }

    $response_code = wp_remote_retrieve_response_code( $ollama_response );
    $response_body = wp_remote_retrieve_body( $ollama_response );

    error_log("AI Bot Ollama Response Code: " . $response_code);
    error_log("AI Bot Ollama Response Body: " . $response_body);

    if ( $response_code !== 200 ) {
        return "Ошибка Ollama (Код {$response_code}): " . substr($response_body, 0, 100);
    }

    $ollama_data = json_decode( $response_body, true );
    $bot_response = $ollama_data['response'] ?? null;

    if ( empty( $bot_response ) ) {
        return 'Не удалось распарсить ответ от модели.';
    }

    return trim( $bot_response );
}

add_action( 'wp_ajax_ai_chat_message', 'ai_chat_ajax_handler' );
add_action( 'wp_ajax_nopriv_ai_chat_message', 'ai_chat_ajax_handler' ); // Чтобы работало и для гостей

function ai_chat_ajax_handler() {
    // Проверка nonce-защиты для безопасности
    check_ajax_referer( 'ai_chat_nonce', 'nonce' );

    $message = isset( $_POST['message'] ) ? sanitize_text_field( $_POST['message'] ) : '';
    
    if ( empty( $message ) ) {
        wp_send_json_error( 'Пустое сообщение' );
    }

    // Вызываем наш готовый RAG
    $response = ai_chat_generate_rag_response( $message );

    wp_send_json_success( $response );
}

// Регистрируем меню в админке
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

// Регистрируем опции в базе данных WordPress
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
    
}

// Рендеринг страницы настроек через файл view.php
function wporg_my_ai_chat_options_page_html() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    include plugin_dir_path(__FILE__) . 'admin/view.php';
}

// Хук для обработки нажатия кнопки в админке
add_action( 'admin_init', 'ai_chat_handle_mass_indexing_button' );

function ai_chat_handle_mass_indexing_button() {
    // Проверяем, была ли нажата именно наша кнопка
    if ( ! isset( $_POST['ai_bot_start_mass_index'] ) ) {
        return;
    }

    // Проверяем безопасность (nonce) и права пользователя
    if ( ! check_admin_referer( 'ai_bot_mass_index_action', 'ai_bot_nonce' ) || ! current_user_can( 'manage_options' ) ) {
        wp_die( 'У вас недостаточно прав для выполнения этого действия.' );
    }

    // Получаем ID всех опубликованных постов, страниц и товаров
    $args = array(
        'post_type'      => array( 'post', 'page', 'product' ),
        'post_status'    => 'publish',
        'posts_per_page' => -1, // Берем абсолютно все
        'fields'         => 'ids', // Нам нужны только ID, чтобы не забивать память
    );

    $all_post_ids = get_posts( $args );

    if ( ! empty( $all_post_ids ) ) {
        $count = 0;
        foreach ( $all_post_ids as $post_id ) {
            // Планируем одиночный крон-таск на индексацию для каждого ID.
            // WordPress (или Action Scheduler от WooCommerce) сам распределит нагрузку 
            // и выполнит их один за другим в фоне, не вешая твой ноутбук.
            if ( ! wp_next_scheduled( 'my_ai_bot_index_single_post_cron', array( $post_id ) ) ) {
                wp_schedule_single_event( time() + $count, 'my_ai_bot_index_single_post_cron', array( $post_id ) );
                $count++; // Добавляем секундную задержку между задачами, чтобы они шли пачкой
            }
        }

        // Выводим красивое админ-уведомление об успехе
        add_action( 'admin_notices', function() use ( $count ) {
            echo '<div class="notice notice-success is-dismissible"><p><strong>AI Bot:</strong> Успешно запланирована индексация для объектов в количестве: ' . $count . '. Задачи выполняются в фоне.</p></div>';
        });
    } else {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-warning is-dismissible"><p><strong>AI Bot:</strong> Не найдено контента для индексации.</p></div>';
        });
    }
}
