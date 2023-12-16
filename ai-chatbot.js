document.addEventListener('DOMContentLoaded', function () {
    const chatForm = document.getElementById('ai-chatbot-form');
    const chatInput = document.getElementById('ai-chatbot-input');
    const chatResponse = document.getElementById('ai-chatbot-response');

    chatForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const message = chatInput.value.trim();

        if (message) {
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
                    chatResponse.innerHTML = data.data.response;
                } else {
                    chatResponse.innerHTML = 'No response received';
                }
            })
            .catch(error => {
                chatResponse.innerHTML = 'Error: ' + error.message;
            });
        }
    });
});
