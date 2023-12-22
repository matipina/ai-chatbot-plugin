document.addEventListener('DOMContentLoaded', function () {
    // Set chatbot image placeholder and minimized chatbot image if the URL is provided
    const chatbotImagePlaceholder = document.querySelector('.chatbot-image-placeholder');
    const chatbotMinimized = document.querySelector('.chatbot-minimized');

    if (aiChatbotSettings.image_url) {
        if (chatbotImagePlaceholder) {
            chatbotImagePlaceholder.style.backgroundImage = `url('${aiChatbotSettings.image_url}')`;
        }
        if (chatbotMinimized) {
            chatbotMinimized.style.backgroundImage = `url('${aiChatbotSettings.image_url}')`;
        }
    }

    if (aiChatbotSettings.primary_color) {
        const root = document.documentElement;
        root.style.setProperty('--chatbot-primary-color', aiChatbotSettings.primary_color);
    }

    const chatForm = document.getElementById('ai-chatbot-form');
    const chatInput = document.getElementById('ai-chatbot-input');
    const chatConversation = document.getElementById('ai-chatbot-conversation');
    const chatbotContainer = document.getElementById('ai-chatbot');
    const chatbotToggle = document.getElementById('chatbot-toggle');

    // Function to append messages to the chat conversation
    function appendMessage(text, className) {
        const messageElement = document.createElement('div');
        messageElement.className = 'ai-chatbot-message ' + className;
        messageElement.textContent = text;
        chatConversation.appendChild(messageElement);
        chatConversation.scrollTop = chatConversation.scrollHeight; // Scroll to the latest message
    }

    // Event listener for chat form submission
    chatForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const message = chatInput.value.trim();

        if (message) {
            appendMessage(message, 'user-message'); // Append user message

            fetch(aiChatbotSettings.ajaxurl, {
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

    // Event listener to handle chatbot minimization
    chatbotToggle.addEventListener('click', function() {
        chatbotContainer.classList.add('minimized');
        chatbotMinimized.style.display = 'block'; // Show the minimized icon
    });

    // Event listener to handle chatbot expansion from the minimized state
    chatbotMinimized.addEventListener('click', function() {
        chatbotContainer.classList.remove('minimized');
        chatbotMinimized.style.display = 'none'; // Hide the minimized icon
    });
});
