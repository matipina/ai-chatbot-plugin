document.addEventListener('DOMContentLoaded', function () {
    const chatForm = document.getElementById('ai-chatbot-form');
    const chatInput = document.getElementById('ai-chatbot-input');
    const chatConversation = document.getElementById('ai-chatbot-conversation');

    function appendMessage(text, className) {
        const messageElement = document.createElement('div');
        messageElement.className = 'ai-chatbot-message ' + className;
        messageElement.textContent = text;
        chatConversation.appendChild(messageElement);
        chatConversation.scrollTop = chatConversation.scrollHeight; // Scroll to the latest message
    }

    chatForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const message = chatInput.value.trim();

        if (message) {
            appendMessage(message, 'user-message'); // Append user message

            fetch(aiChatbot.ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=utf-8'
                },
                body: 'action=ai_chatbot_handle_request&message=' + encodeURIComponent(message)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.data.response) {
                    appendMessage(data.data.response, 'bot-message'); // Append bot response
                } else {
                    appendMessage('No response received', 'bot-message');
                }
            })
            .catch(error => {
                appendMessage('Error: ' + error.message, 'bot-message');
            });

            chatInput.value = ''; // Clear input field
        }
    });
});
