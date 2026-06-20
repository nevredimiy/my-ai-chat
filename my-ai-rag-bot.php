<?php
/**
 * Plugin Name: My Custom AI RAG Bot
 * Description: Самописный чат-бот с ответами по базе сайта и товарам WooCommerce.
 * Version: 1.1
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// 1. Правильно подключаем стили и скрипты в WordPress
add_action( 'wp_enqueue_scripts', 'ai_chat_enqueue_assets' );
function ai_chat_enqueue_assets() {
    // Подключаем CSS файл
    wp_enqueue_style( 
        'ai-chat-style', 
        plugins_url( 'css/chat-style.css', __FILE__ ), 
        array(), 
        '1.1' 
    );

    // Подключаем JS файл (true означает загрузить в футере сайта)
    wp_enqueue_script( 
        'ai-chat-script', 
        plugins_url( 'js/chat-script.js', __FILE__ ), 
        array(), 
        '1.1', 
        true 
    );
}

// 2. Выводим чистый HTML-шаблон в футер
add_action( 'wp_footer', 'render_ai_chat_widget' );
function render_ai_chat_widget() {
    include plugin_dir_path( __FILE__ ) . 'chat-template.php';
}

// 3. Создаем эндпоинт для чата
add_action( 'rest_api_init', function () {
    register_rest_route( 'aibot/v1', '/chat', array(
        'methods'             => 'POST',
        'callback'            => 'handle_ai_chat_request',
        'permission_callback' => '__return_true',
    ) );
} );

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

    $ai_url = 'http://localhost:11434/v1/chat/completions';
    $api_key = 'your-key'; 

    $system_prompt = "Ты — полезный ассистент на сайте интернет-магазина. Ответь на вопрос пользователя, опираясь ТОЛЬКО на предоставленный ниже контекст. Если в контексте нет ответа, ответь: 'Я не нашел информации на сайте'.\n\nКОНТЕКСТ:\n" . $context;

    // Включаем правильные заголовки для потокового ответа
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no'); // Отключаем буферизацию в Nginx

    $body = array(
        'model' => 'qwen2.5:1.5b',
        'messages' => array(
            array( 'role' => 'system', 'content' => $system_prompt ),
            array( 'role' => 'user', 'content' => $user_question )
        ),
        'temperature' => 0.3,
        'stream' => true // Включаем стриминг в Ollama!
    );

    $ch = curl_init('http://localhost:11434/v1/chat/completions');
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false); // Мы сами будем выводить данные
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);

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
   