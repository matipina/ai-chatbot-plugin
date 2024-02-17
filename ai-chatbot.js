jQuery(document).ready(function($) {
    // Function to convert hex color to RGBA
    function hexToRGBA(hex, opacity) {
        let r = parseInt(hex.slice(1, 3), 16),
            g = parseInt(hex.slice(3, 5), 16),
            b = parseInt(hex.slice(5, 7), 16);

        return `rgba(${r}, ${g}, ${b}, ${opacity})`;
    }

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

    // Update primary color for chatbot elements and user messages
    if (aiChatbotSettings.primary_color) {
        const root = document.documentElement;
        root.style.setProperty('--chatbot-primary-color', aiChatbotSettings.primary_color);

        const userMessageColor = hexToRGBA(aiChatbotSettings.primary_color, 0.3); // 30% opacity
        document.querySelectorAll('.user-message').forEach(function (message) {
            message.style.backgroundColor = userMessageColor;
        });
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

        if (className === 'user-message') {
            messageElement.style.backgroundColor = aiChatbotSettings.primary_color ?
                hexToRGBA(aiChatbotSettings.primary_color, 0.3) : 'transparent';
        }
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
            .then(response => response.json())
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