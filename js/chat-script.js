document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('ai-chat-input');
    const button = document.getElementById('ai-chat-send');
    const messagesContainer = document.getElementById('ai-chat-messages');

    if (!button || !input || !messagesContainer) return;

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