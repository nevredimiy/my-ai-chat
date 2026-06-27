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

// Хук активации плагина: сработает, когда вы переактивируете плагин в админке WordPress
register_activation_hook( __FILE__, 'ai_chat_initialize_vector_db' );

function ai_chat_initialize_vector_db() {
    $collection_url = get_my_ai_chat_qdrant_url() . '/collections/' . get_my_ai_chat_collection_name();

    // 1. Сначала проверяем, существует ли уже такая коллекция
    $check_response = wp_remote_get( $collection_url );
    
    if ( ! is_wp_error( $check_response ) && wp_remote_retrieve_response_code( $check_response ) === 200 ) {
        // Коллекция уже создана, ничего делать не нужно
        return;
    }

    // 2. Если коллекции нет, отправляем PUT-запрос на её создание
    $body = array(
        'vectors' => array(
            'size'     => get_my_ai_chat_vector_size(), // Размерность (кол-во чисел)
            'distance' => 'Cosine'               // Метрика сравнения (Косинусное сходство)
        )
    );

    $response = wp_remote_request( $collection_url, array(
        'method'  => 'PUT',
        'headers' => array( 'Content-Type' => 'application/json' ),
        'body'    => json_encode( $body ),
        'timeout' => 10
    ) );

    if ( is_wp_error( $response ) ) {
        // Запишем ошибку в лог WordPress, если Qdrant не ответил
        error_log( 'AI RAG Bot Error: Не удалось связаться с Qdrant: ' . $response->get_error_message() );
    }
}

// 1. Правильно подключаем стили и скрипты в WordPress
add_action( 'wp_enqueue_scripts', 'ai_chat_enqueue_assets' );
function ai_chat_enqueue_assets() {
    // Подключаем CSS файл
    wp_enqueue_style( 
        'ai-chat-style', 
        plugins_url( 'css/chat-style.css', __FILE__ ), 
        array(), 
        filemtime( plugin_dir_path( __FILE__ ) . 'css/chat-style.css' )
    );

    // Подключаем JS файл (true означает загрузить в футере сайта)
    wp_enqueue_script( 
        'ai-chat-script', 
        plugins_url( 'js/chat-script.js', __FILE__ ), 
        array(), 
        filemtime( plugin_dir_path( __FILE__ ) . 'js/chat-script.js' ), 
        true 
    );
}

// 2. Выводим чистый HTML-шаблон в футер
add_action( 'wp_footer', 'render_ai_chat_widget' );
function render_ai_chat_widget() {
    include plugin_dir_path( __FILE__ ) . 'chat-template.php';
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

    $response = wp_remote_get( $test_url, array(
        'timeout'      => 2,
        'redirection'  => 2,
        'httpversion'  => '1.1',
        'headers'      => array( 'Accept' => 'application/json' ),
    ) );

    if ( is_wp_error( $response ) ) {
        return false;
    }

    $code = wp_remote_retrieve_response_code( $response );
    return in_array( $code, array( 200, 301, 302 ), true );
}

// 3. Создаем эндпоинт для чата
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

    // Вызываем твою рабочую функцию генерации ответа!
    $bot_answer = ai_chat_generate_rag_response( $user_question );

    // Возвращаем JSON строго в том формате, который ожидает твой chat-script.js
    return new WP_REST_Response( array( 'answer' => $bot_answer ), 200 );
}

// 4. Логика RAG поиска и связи с Ollama/OpenAI
function handle_ai_chat_request( $request ) {
    $params = $request->get_json_params();
    $user_question = sanitize_text_field( $params['question'] );

    if ( empty( $user_question ) ) {
        return new WP_Error( 'no_question', 'Вопрос пуст', array( 'status' => 400 ) );
    }

    // Очистка вопроса от стоп-слов
    $stop_words = array('какой', 'какая', 'какое', 'сколько', 'где', 'как', 'почему', 'что', 'найди', 'подскажи');
    $words = explode(' ', preg_replace('/[^\w\s]/u', '', mb_strtolower($user_question)));
    $filtered_words = array_diff($words, $stop_words);
    $final_search_string = !empty($filtered_words) ? implode(' ', $filtered_words) : $user_question;

    // Ищем записи, страницы и товары WooCommerce
    $search_query = new WP_Query( array(
        's'              => $final_search_string,
        'posts_per_page' => 3,
        'post_type'      => array('post', 'page', 'product'),
        'post_status'    => 'publish'
    ) );

    $context = "";
    if ( $search_query->have_posts() ) {
        while ( $search_query->have_posts() ) {
            $search_query->the_post();
            
            $current_post_type = get_post_type();
            $title = get_the_title();
            $content = wp_strip_all_tags( get_the_content() );

            // Если нашли товар WooCommerce, вытаскиваем его коммерческие данные
            if ( $current_post_type === 'product' && function_exists( 'wc_get_product' ) ) {
                $product = wc_get_product( get_the_ID() );
                if ( $product ) {
                    // 1. Получаем сырой HTML цены
                    $price_html = $product->get_price_html(); 
                    
                    // 2. Очищаем от HTML-тегов (<span class="amount"> и т.д.)
                    $clean_price = wp_strip_all_tags( $price_html );
                    
                    // 3. ДЕКОДИРУЕМ HTML-сущности (&nbsp; и &#8372;) в нормальный текст UTF-8
                    $final_price = html_entity_decode( $clean_price, ENT_QUOTES, 'UTF-8' );

                    $sku = $product->get_sku(); 
                    $availability = $product->is_in_stock() ? 'В наличии' : 'Нет на складе';

                    $context .= "Товар: {$title}\n";
                    if ( !empty($sku) ) $context .= "Артикул: {$sku}\n";
                    $context .= "Цена: {$final_price}\n"; // Здесь уже будет нормальная цена со знаком ₴
                    $context .= "Статус: {$availability}\n";
                    $context .= "Описание товара: {$content}\n\n";
                    continue; 
                }
            }

            $type_label = ($current_post_type === 'page') ? 'Статья/Страница' : 'Блог';
            $context .= "{$type_label}: {$title}\nТекст: {$content}\n\n";
        }
        wp_reset_postdata();
    }

    if ( empty( $context ) ) {
        return new WP_REST_Response( array( 'answer' => 'К сожалению, на сайте нет информации по этому вопросу.' ), 200, array('Content-Type' => 'application/json; charset=utf-8') );
    }

    $system_prompt = "Ты — полезный ассистент на сайте интернет-магазина. Ответь на вопрос пользователя, опираясь ТОЛЬКО на предоставленный ниже контекст. Если в контексте нет ответа, ответь: 'Я не нашел информации на сайте'.\n\nКОНТЕКСТ:\n" . $context;

    $ai_url = my_ai_chat_get_ollama_url();
    $model = defined( 'MY_AI_CHAT_OLLAMA_MODEL' ) && MY_AI_CHAT_OLLAMA_MODEL ? MY_AI_CHAT_OLLAMA_MODEL : 'qwen2.5:1.5b';
    if ( getenv( 'MY_AI_CHAT_OLLAMA_MODEL' ) ) {
        $model = getenv( 'MY_AI_CHAT_OLLAMA_MODEL' );
    }

    $api_key = '';
    if ( defined( 'MY_AI_CHAT_OLLAMA_API_KEY' ) && MY_AI_CHAT_OLLAMA_API_KEY ) {
        $api_key = MY_AI_CHAT_OLLAMA_API_KEY;
    } elseif ( getenv( 'MY_AI_CHAT_OLLAMA_API_KEY' ) ) {
        $api_key = getenv( 'MY_AI_CHAT_OLLAMA_API_KEY' );
    }

    $headers = array( 'Content-Type: application/json' );
    if ( $api_key ) {
        $headers[] = 'Authorization: Bearer ' . $api_key;
    }

    // Включаем правильные заголовки для потокового ответа
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no'); // Отключаем буферизацию в Nginx

    $body = array(
        'model' => $model,
        'messages' => array(
            array( 'role' => 'system', 'content' => $system_prompt ),
            array( 'role' => 'user', 'content' => $user_question )
        ),
        'temperature' => 0.3,
        'stream' => true // Включаем стриминг в Ollama!
    );

    $ch = curl_init( $ai_url );
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false); // Мы сами будем выводить данные
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
    curl_setopt( $ch, CURLOPT_TIMEOUT, 120 );

    // Функция-коллбэк: вызывается каждый раз, когда от Ollama прилетает кусочек текста
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) {
        // Ollama возвращает данные в формате Server-Sent Events (data: {...})
        // Мы просто транслируем их фронтенду "как есть"
        echo $data;
        
        // Принудительно проталкиваем данные через PHP и Nginx в браузер
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
        
        return strlen($data);
    });

    curl_exec($ch);
    curl_close($ch);
    die(); // Останавливаем работу WordPress, чтобы не примешивать лишний JSON в поток
}
   
// Подключаем функцию к хуку сохранения записей, страниц и товаров
add_action( 'save_post', 'ai_chat_handle_post_save', 10, 3 );

function ai_chat_handle_post_save( $post_id, $post, $update ) {
    // Проверим, заходит ли вообще WordPress в эту функцию
    // Если при нажатии "Обновить" вы увидите этот текст — значит хук работает!
    // wp_die('Хук save_post сработал для ID: ' . $post_id);

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( wp_is_post_revision( $post_id ) ) return;
    if ( $post->post_status !== 'publish' ) return;

    $allowed_types = array( 'post', 'page', 'product' );
    if ( ! in_array( $post->post_type, $allowed_types ) ) return;

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

    if ( empty( $text_to_embed ) ) {
        wp_die('Ошибка: Сформированный текст для эмбеддинга пуст!');
    }

    // Проверим, какой текст мы подготовили для нейросети
    // wp_die('Текст для отправки: ' . $text_to_embed);

    // 3. Запрос в Ollama
    $ollama_response = wp_remote_post( get_my_ai_chat_ollama_url() . '/embeddings', array(
        'headers' => array( 'Content-Type' => 'application/json' ),
        'body'    => json_encode( array(
            'model'  => get_my_ai_chat_model_embed(),
            'prompt' => $text_to_embed
        ) ),
        'timeout' => 90
    ) );

    if ( is_wp_error( $ollama_response ) ) {
        wp_die( 'Ошибка Ollama API: ' . $ollama_response->get_error_message() );
    }

    $ollama_body = json_decode( wp_remote_retrieve_body( $ollama_response ), true );
    $vector = $ollama_body['embedding'] ?? null;

    if ( ! is_array( $vector ) ) {
        wp_die( 'Ollama не вернула вектор. Ответ сервера: ' . wp_remote_retrieve_body( $ollama_response ) );
    }

    // 4. Отправка вектора в Qdrant (Используем чистый URL без /points в конце, его добавим ниже)
    $qdrant_url = get_my_ai_chat_qdrant_url() . '/collections/' . get_my_ai_chat_collection_name() . '/points?wait=true';

    $qdrant_body = array(
        'points' => array(
            array(
                'id'      => (int) $post_id, // Явно приводим ID к числу
                'vector'  => $vector,
                'payload' => array(
                    'post_type' => $post->post_type,
                    'title'     => $title
                )
            )
        )
    );

    // Изменяем на POST — это самый надежный способ для операции Upsert в Qdrant
    $qdrant_response = wp_remote_request( $qdrant_url, array(
        'method'  => 'POST', 
        'headers' => array( 'Content-Type' => 'application/json' ),
        'body'    => json_encode( $qdrant_body ),
        'timeout' => 10
    ) );

    if ( is_wp_error( $qdrant_response ) ) {
        $err_msg = $qdrant_response->get_error_message();
        error_log( 'AI Bot Error: Не удалось отправить вектор в Qdrant: ' . $err_msg );
        WP_CLI::warning( "Ошибка cURL для ID {$post_id}: {$err_msg}" ); // Выводим в консоль WP-CLI
    } else {
        $code = wp_remote_retrieve_response_code( $qdrant_response );
        if ( $code !== 200 ) {
            $body = wp_remote_retrieve_body( $qdrant_response );
            error_log( "AI Bot Error: Qdrant вернул код ответа {$code}. Тело: {$body}" );
            WP_CLI::warning( "Qdrant отверг ID {$post_id} (Код {$code}). Ответ базы: {$body}" ); // Выводим в консоль WP-CLI
        }
    }
}

// ==========================================
// РЕГИСТРАЦИЯ И СТРУКТУРА WP-CLI КОМАНДЫ
// ==========================================

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    WP_CLI::add_command( 'ai-bot', 'AI_Bot_CLI_Commands' );
}

class AI_Bot_CLI_Commands {

    /**
     * Массовая индексация товаров, постов и страниц в векторную БД Qdrant.
     *
     * ## OPTIONS
     *
     * [--batch_size=<size>]
     * : Количество товаров, обрабатываемых за один шаг (батч). По умолчанию 50.
     *
     * ## EXAMPLES
     *
     * wp ai-bot index --batch_size=100
     *
     * @param array $args       Свободные аргументы
     * @param array $assoc_args Именованные аргументы (например, --batch_size)
     */
    public function index( $args, $assoc_args ) {

        // 1. Получаем имя модели эмбеддинга из настроек (или дефолтное)
        // Если у тебя имя модели захардкожено, замени на свою строку, например 'nomic-embed-text'
        $embedding_model = get_option( 'my_ai_chat_ollama_embedding_model', 'nomic-embed-text' );
        $llm_model       = get_option( 'my_ai_chat_model_name', 'qwen2.5:1.5b' );

        WP_CLI::line( "Проверка готовности окружения..." );

        // 2. Проверяем модель эмбеддингов
        if ( ! my_ai_chat_check_ollama_model( $embedding_model ) ) {
            WP_CLI::error( 
                "Критическая ошибка: Модель эмбеддингов '{$embedding_model}' не найдена в Ollama!\n" .
                "Пожалуйста, скачайте её на ноутбук, выполнив в терминале команду:\n" .
                "ollama pull {$embedding_model}"
            );
        }

        // 3. Заодно проверяем и основную LLM модель (Qwen)
        if ( ! my_ai_chat_check_ollama_model( $llm_model ) ) {
            WP_CLI::error( 
                "Критическая ошибка: Основная модель '{$llm_model}' не найдена в Ollama!\n" .
                "Пожалуйста, скачайте её, выполнив в терминале команду:\n" .
                "ollama pull {$llm_model}"
            );
        }

        // Подстраховка: если параметр не передан, берем 50 по умолчанию
        $batch_size = isset( $assoc_args['batch_size'] ) ? intval( $assoc_args['batch_size'] ) : 50;
        if ( $batch_size <= 0 ) {
            $batch_size = 50;
        }
        
        WP_CLI::log( WP_CLI::colorize( "%BЗапуск массовой индексации контента в Qdrant...%n" ) );
        WP_CLI::log( "Размер батча: " . $batch_size );

        // 1. Считаем, сколько всего объектов нам нужно обработать
        $query_args = array(
            'post_type'      => array( 'post', 'page', 'product' ),
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids', // Берем только ID, чтобы не забивать память
        );
        $all_post_ids = get_posts( $query_args );
        $total_count = count( $all_post_ids );

        if ( $total_count === 0 ) {
            WP_CLI::error( "Нет опубликованных постов, страниц или товаров для индексации." );
        }

        WP_CLI::log( "Найдено объектов для обработки: " . $total_count );

        // Создаем красивый прогресс-бар в консоли
        $progress = \WP_CLI\Utils\make_progress_bar( 'Индексация', $total_count );

        // 2. Бьем массив ID на порции (батчи)
        $batches = array_chunk( $all_post_ids, $batch_size );

        foreach ( $batches as $batch ) {
            foreach ( $batch as $post_id ) {
                $post = get_post( $post_id );
                if ( ! $post ) {
                    $progress->tick();
                    continue;
                }

                // Формируем текст (логика один в один как при сохранении)
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

                if ( empty( $text_to_embed ) ) {
                    $progress->tick();
                    continue;
                }

                // Запрос в Ollama за вектором
                $ollama_response = wp_remote_post( get_my_ai_chat_ollama_url() . '/embeddings', array(
                    'headers' => array( 'Content-Type' => 'application/json' ),
                    'body'    => json_encode( array(
                        'model'  => get_my_ai_chat_model_embed(),
                        'prompt' => $text_to_embed
                    ) ),
                    'timeout' => 90
                ) );

                if ( is_wp_error( $ollama_response ) ) {
                    WP_CLI::warning( "Ошибка Ollama для ID {$post_id}: " . $ollama_response->get_error_message() );
                    $progress->tick();
                    continue;
                }

                $ollama_body = json_decode( wp_remote_retrieve_body( $ollama_response ), true );
                $vector = $ollama_body['embedding'] ?? null;

                if ( ! is_array( $vector ) ) {
                    $progress->tick();
                    continue;
                }

                // Отправка вектора в Qdrant
                $qdrant_url = get_my_ai_chat_qdrant_url() . '/collections/' . get_my_ai_chat_collection_name() . '/points?wait=true';
                $qdrant_body = array(
                    'points' => array(
                        array(
                            'id'      => $post_id,
                            'vector'  => $vector,
                            'payload' => array(
                                'post_type' => $post->post_type,
                                'title'     => $title
                            )
                        )
                    )
                );

                wp_remote_request( $qdrant_url, array(
                    'method'  => 'PUT',
                    'headers' => array( 'Content-Type' => 'application/json' ),
                    'body'    => json_encode( $qdrant_body ),
                    'timeout' => 10
                ) );

                // Шаг вперед для прогресс-бара
                $progress->tick();
            }
            
            // Небольшая пауза между батчами, чтобы процессор ноутбука "отдыхал"
            usleep( 50000 ); // 0.05 секунды
        }

        $progress->finish();
        WP_CLI::success( "Индексация успешно завершена! Все данные в Qdrant." );
    }

    /**
     * Тестирование семантического поиска.
     * * ## OPTIONS
     * * <query>
     * : Поисковая фраза на естественном языке.
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function search( $args, $assoc_args ) {
        if ( empty( $args[0] ) ) {
            WP_CLI::error( "Ви забули вказати пошуковий запит. Приклад: wp ai-bot search 'шукаю кросівки'." );
        }

        $query_text = $args[0];
        WP_CLI::log( "Ищем: " . $query_text );

        $results = ai_chat_search_similar_content( $query_text, 3 );

        if ( empty( $results ) ) {
            WP_CLI::error( "Ничего не найдено или произошла ошибка." );
        }

        foreach ( $results as $item ) {
            WP_CLI::log( sprintf( "- [ID %d] %s (Схожесть: %0.4f)", $item['id'], $item['title'], $item['score'] ) );
        }
    }

    /**
     * Полноценный тест RAG-ответа (Поиск + Генерация).
     * * ## OPTIONS
     * * <question>
     * : Вопрос к боту.
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function ask( $args, $assoc_args ) {
        $question = $args[0];
        WP_CLI::log( "Вопрос боту: " . $question );
        WP_CLI::log( "Думаю..." );

        $response = ai_chat_generate_rag_response( $question );

        WP_CLI::log( "\n================ ОТВЕТ БОТА ================" );
        WP_CLI::log( $response );
        WP_CLI::log( "============================================" );
    }
}

/**
 * Семантический поиск похожих по смыслу товаров и постов в Qdrant.
 *
 * @param string $query_text Текст вопроса пользователя (например, "ищу очки")
 * @param int $limit         Количество возвращаемых результатов
 * @return array             Массив ID постов WordPress, упорядоченный по релевантности
 */
function ai_chat_search_similar_content( $query_text, $limit = 3 ) {
    if ( empty( $query_text ) ) return array();

    // 1. Получаем вектор для поискового запроса от Ollama
    $ollama_response = wp_remote_post( get_my_ai_chat_ollama_url() . '/embeddings', array(
        'headers' => array( 'Content-Type' => 'application/json' ),
        'body'    => json_encode( array(
            'model'  => get_my_ai_chat_model_embed(),
            'prompt' => $query_text
        ) ),
        'timeout' => 15
    ) );

    if ( is_wp_error( $ollama_response ) ) {
        error_log( 'AI Bot Search Error: Не удалось получить эмбеддинг запроса: ' . $ollama_response->get_error_message() );
        return array();
    }

    $ollama_body = json_decode( wp_remote_retrieve_body( $ollama_response ), true );
    $query_vector = $ollama_body['embedding'] ?? null;

    if ( ! is_array( $query_vector ) ) {
        return array();
    }

    // 2. Ищем похожие векторы в Qdrant
    $qdrant_url = get_my_ai_chat_qdrant_url() . '/collections/' . get_my_ai_chat_collection_name() . '/points/search';

    $qdrant_body = array(
        'vector'      => $query_vector,
        'limit'       => $limit,
        'with_payload'=> true // Просим вернуть метаданные (название, тип)
    );

    $qdrant_response = wp_remote_post( $qdrant_url, array(
        'headers' => array( 'Content-Type' => 'application/json' ),
        'body'    => json_encode( $qdrant_body ),
        'timeout' => 10
    ) );

    if ( is_wp_error( $qdrant_response ) ) {
        error_log( 'AI Bot Search Error: Ошибка поиска в Qdrant: ' . $qdrant_response->get_error_message() );
        return array();
    }

    $qdrant_results = json_decode( wp_remote_retrieve_body( $qdrant_response ), true );
    $points = $qdrant_results['result'] ?? array();

    $found_items = array();
    foreach ( $points as $point ) {
        // Извлекаем ID поста и оценку схожести (score от 0 до 1)
        $found_items[] = array(
            'id'    => $point['id'],
            'score' => $point['score'],
            'title' => $point['payload']['title'] ?? 'Без названия'
        );
    }

    return $found_items;
}

/**
 * Главная функция RAG: принимает вопрос, ищет контекст в Qdrant и генерирует ответ через Qwen.
 *
 * @param string $user_question Вопрос пользователя в чате
 * @return string               Итоговый ответ нейросети
 */
function ai_chat_generate_rag_response( $user_question ) {
    if ( ! function_exists( 'wc_get_product' ) && function_exists( 'WC' ) ) {
        include_once WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
    }

    // --- ДЕБАГ ЛОГ ---
    error_log( "== AI BOT REST API START ==" );
    error_log( "Вопрос пользователя: " . $user_question );

    // 1. Шаг поиска: Достаем топ-3 релевантных товара/поста
    $similar_items = ai_chat_search_similar_content( $user_question, 3 );
    
    error_log( "Результаты поиска из Qdrant: " . print_r( $similar_items, true ) );
    // -----------------
    
    $context_text = "";
    
    if ( ! empty( $similar_items ) ) {
        // Подтягиваем дефолтную инструкцию контекста из админки
        $db_context_instruction = get_option('my_ai_chat_context_template', 'Используй следующую информацию о товарах для ответа...');
        $context_text = $db_context_instruction . "\n\n";
        
        foreach ( $similar_items as $item ) {
            // Если схожесть совсем низкая (например, меньше 0.55), можем игнорировать этот объект
            if ( $item['score'] < 0.40 ) continue;

            $post_id = $item['id'];
            $permalink = get_permalink( $post_id );
            
            // Собираем чистый текст для контекста модели
            $post = get_post( $post_id );
            
            if ( $post ) {
                $context_text .= "--- \nНазвание: " . $post->post_title . "\nСсылка на товар: " . $permalink . "\n";
                
                if ( $post->post_type === 'product' && function_exists( 'wc_get_product' ) ) {
                    $product = wc_get_product( $post_id );
                    if ( $product ) {
                        $context_text .= "Цена: " . wp_strip_all_tags($product->get_price_html()) . "\n";
                    }
                }
                $context_text .= "Описание: " . wp_strip_all_tags( wp_trim_words( $post->post_content, 50 ) ) . "\n";
            }
        }
    }

    error_log( "Сформированный контекст для Qwen:\n" . $context_text );

    // 2. Шаг генерации: Меняем промпт и убираем вымышленную ссылку из примера pattern'а
    $system_prompt = get_option( 'my_ai_chat_system_prompt' );
    $model_name    = get_option( 'my_ai_chat_model_name', 'qwen2.5:1.5b' );
    $temperature   = (float) get_option( 'my_ai_chat_temperature', 0.3 );
    $ollama_base_url = get_option( 'my_ai_chat_ollama_url', 'http://host.docker.internal:11434' );

    $full_prompt = "КОНТЕКСТ ИЗ БАЗЫ ДАННЫХ:\n" . $context_text . "\n\n" .
                   "Пример формата вывода ссылки:\n" .
                   "Так, у нас є Кепка Червона ( ССЫЛКА_ИЗ_КОНТЕКСТА ).\n\n" .
                   "Вопрос пользователя: " . $user_question . "\n" .
                   "Ответ:";

    // Запрос к основной модели Qwen
    $ollama_response = wp_remote_post( get_my_ai_chat_ollama_url() . '/generate', array(
        'headers' => array( 'Content-Type' => 'application/json' ),
        'body'    => json_encode( array(
            'model'  => $model_name,
            'prompt' => $full_prompt,
            'system' => $system_prompt,
            'stream' => false,
            'options' => array(
                'temperature' => $temperature
            )
        ) ),
        'timeout' => 45
    ) );

    if ( is_wp_error( $ollama_response ) ) {
        return "Извините, произошла техническая ошибка при связи с мозгом бота: " . $ollama_response->get_error_message();
    }

    $body = json_decode( wp_remote_retrieve_body( $ollama_response ), true );
    return $body['response'] ?? "Не удалось сгенерировать ответ.";
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
    register_setting( 'my_ai_chat_settings_group', 'my_ai_chat_model_embed' );
}

// Рендеринг страницы настроек через файл view.php
function wporg_my_ai_chat_options_page_html() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    include plugin_dir_path(__FILE__) . 'admin/view.php';
}

/**
 * Проверяет, установлена ли конкретная модель в Ollama.
 *
 * @param string $model_name Название модели для проверки.
 * @return bool True, если модель найдена, иначе false.
 */
function my_ai_chat_check_ollama_model( $model_name ) {
    $ollama_url = get_my_ai_chat_ollama_url(); // Получаем URL с /api на конце
    
    // Эндпоинт Ollama для вывода списка моделей: /api/tags
    // Но так как функция get_my_ai_chat_ollama_url() уже возвращает урл с /api,
    // мы просто убираем дублирование, если оно есть.
    $tags_url = str_replace('/api/api', '/api', $ollama_url . '/tags');

    $response = wp_remote_get( $tags_url, array( 'timeout' => 10 ) );

    if ( is_wp_error( $response ) ) {
        return false; // Если Ollama вообще выключена
    }

    $body = wp_remote_retrieve_body( $response );
    $data = json_encode( json_decode( $body ), true ); // Декодируем список моделей
    $data = json_decode($body, true);

    if ( ! empty( $data['models'] ) ) {
        foreach ( $data['models'] as $model ) {
            // Ollama может возвращать имя как 'model:latest', поэтому проверяем вхождение
            if ( $model['name'] === $model_name || strpos( $model['name'], $model_name . ':' ) === 0 ) {
                return true;
            }
        }
    }

    return false;
}