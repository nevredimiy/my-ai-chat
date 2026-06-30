document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('ai-chat-input');
    const button = document.getElementById('ai-chat-send');
    const messagesContainer = document.getElementById('ai-chat-messages');
    const widget = document.getElementById('ai-chat-widget');
    const toggleBtn = document.getElementById('ai-chat-toggle');
    const minimizeBtn = document.getElementById('ai-chat-minimize');

    if (!button || !input || !messagesContainer || !widget) return;

    const l10n = (typeof aiChatL10n !== 'undefined') ? aiChatL10n : {
        serverError: 'Server error',
        noAnswer: 'No answer.',
        connectionError: 'Connection error with server.'
    };

    // Restore chat state from localStorage
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
        }, 300);
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

        const botMessageId = appendMessage('', 'bot');
        const botMessageElement = document.getElementById(botMessageId);
        botMessageElement.innerText = '...';

        try {
            const apiEndpoint = window.location.origin + '/index.php?rest_route=/aibot/v1/chat';
            const response = await fetch(apiEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ question: question })
            });

            if (!response.ok) throw new Error(l10n.serverError);

            const data = await response.json();
            botMessageElement.innerHTML = data.answer || l10n.noAnswer;
            messagesContainer.scrollTop = messagesContainer.scrollHeight;

        } catch (error) {
            botMessageElement.innerText = l10n.connectionError;
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
