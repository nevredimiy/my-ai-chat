document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('ai-chat-input');
    const button = document.getElementById('ai-chat-send');
    const messagesContainer = document.getElementById('ai-chat-messages');
    const widget = document.getElementById('ai-chat-widget');
    const toggleBtn = document.getElementById('ai-chat-toggle');
    const minimizeBtn = document.getElementById('ai-chat-minimize');

    if (!button || !input || !messagesContainer || !widget) return;

    // Восстановление состояния чата из localStorage
    const isChatOpen = localStorage.getItem('ai_chat_open') === 'true';
    if (isChatOpen) {
        widget.classList.remove('collapsed');
    } else {
        widget.classList.add('collapsed');
    }

    function openChat() {
        widget.classList.remove('collapsed');
        localStorage.setItem('ai_chat_open', 'true');
        setTimeout(() => {
            input.focus();
        }, 300); // Фокус после завершения анимации раскрытия
    }

    function closeChat() {
        widget.classList.add('collapsed');
        localStorage.setItem('ai_chat_open', 'false');
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', openChat);
    }

    if (minimizeBtn) {
        minimizeBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            closeChat();
        });
    }

    button.addEventListener('click', sendMessage);
    input.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') sendMessage();
    });

    async function sendMessage() {
        const question = input.value.trim();
        if (!question) return;

        appendMessage(question, 'user');
        input.value = '';
        button.disabled = true;

        // Создаем пустое сообщение бота, в которое будем потихоньку дописывать текст
        const botMessageId = appendMessage('', 'bot');
        const botMessageElement = document.getElementById(botMessageId);
        botMessageElement.innerText = '...'; // Статус ожидания

        try {
            const apiEndpoint = window.location.origin + '/index.php?rest_route=/aibot/v1/chat';
            const response = await fetch(apiEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ question: question })
            });

            if (!response.ok) throw new Error('Ошибка сервера');

            const reader = response.body.getReader();
            const decoder = new TextDecoder('utf-8');
            let isFirstChunk = true;
            let buffer = '';

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;

                // Декодируем бинарные данные в строку
                buffer += decoder.decode(value, { stream: true });
                
                // Разбиваем буфер на строки, так как SSE присылает данные построчно
                const lines = buffer.split('\n');
                // Оставляем последний незавершенный кусок в буфере
                buffer = lines.pop(); 

                for (const line of lines) {
                    const cleanedLine = line.trim();
                    if (!cleanedLine || cleanedLine === 'data: [DONE]') continue;

                    if (cleanedLine.startsWith('data:')) {
                        try {
                            const jsonStr = cleanedLine.replace(/^data:\s*/, '');
                            const parsed = JSON.parse(jsonStr);
                            const content = parsed.choices[0]?.delta?.content || '';

                            if (content) {
                                if (isFirstChunk) {
                                    botMessageElement.innerText = ''; // Очищаем '...'
                                    isFirstChunk = false;
                                }
                                // Дописываем кусочек текста прямо в элемент на экране
                                botMessageElement.innerText += content;
                                
                                // Скроллим чат вниз по мере наполнения текстом
                                messagesContainer.scrollTop = messagesContainer.scrollHeight;
                            }
                        } catch (e) {
                            // Игнорируем ошибки парсинга неполных JSON-строк
                        }
                    }
                }
            }

        } catch (error) {
            if (botMessageElement.innerText === '...') {
                botMessageElement.innerText = 'Ошибка соединения с сервером.';
            } else {
                botMessageElement.innerText += '\n[Связь оборвалась]';
            }
        } finally {
            button.disabled = false;
        }
    }

    function appendMessage(text, sender) {
        const msgId = 'msg-' + Date.now();
        const msgDiv = document.createElement('div');
        msgDiv.id = msgId;
        msgDiv.classList.add('ai-message', sender);
        msgDiv.innerText = text;
        messagesContainer.appendChild(msgDiv);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
        return msgId;
    }
});