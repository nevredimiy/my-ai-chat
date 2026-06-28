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

            const data = await response.json();
            botMessageElement.innerHTML = data.answer || 'Нет ответа.';
            messagesContainer.scrollTop = messagesContainer.scrollHeight;

        } catch (error) {
            botMessageElement.innerText = 'Ошибка соединения с сервером.';
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