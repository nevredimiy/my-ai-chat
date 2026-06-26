<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div id="ai-chat-widget" class="collapsed">
    <div id="ai-chat-header">
        <span>AI Ассистент</span>
        <button id="ai-chat-minimize" aria-label="Свернуть">&times;</button>
    </div>
    <div id="ai-chat-messages">
        <div class="ai-message bot">Привет! Задайте мне вопрос по контенту сайта и товарам.</div>
    </div>
    <div id="ai-chat-input-area">
        <input type="text" id="ai-chat-input" placeholder="Введите вопрос..." />
        <button id="ai-chat-send">Отправить</button>
    </div>
</div>

<div id="ai-chat-toggle" title="Открыть чат">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="28" height="28" fill="currentColor">
        <path d="M12 2C6.477 2 2 6.13 2 11.2c0 2.68 1.25 5.09 3.25 6.78-.13.94-.65 2.85-1.57 3.96-.09.11-.08.27.02.37.07.07.17.1.26.07 2.1-.64 4.13-1.63 5.49-2.38 1 .25 2.06.37 3.15.37 5.523 0 10-4.13 10-9.2S17.523 2 12 2zm0 15.6c-.95 0-1.88-.11-2.77-.32-.19-.04-.39 0-.54.12-1.07.8-2.61 1.63-4.23 2.15.65-1 1.07-2.43 1.21-3.41.03-.22-.05-.43-.22-.57C3.76 14.19 3 12.35 3 10.4 3 6.32 7.03 3 12 3s9 3.32 9 7.4-4.03 7.4-9 7.4z"/>
    </svg>
</div>