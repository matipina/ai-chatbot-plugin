jQuery(document).ready(function($) {
    // Function to convert hex color to RGBA
    function hexToRGBA(hex, opacity) {
        let r = parseInt(hex.slice(1, 3), 16),
            g = parseInt(hex.slice(3, 5), 16),
            b = parseInt(hex.slice(5, 7), 16);
        return `rgba(${r}, ${g}, ${b}, ${opacity})`;
    }

    // Trigger start chat session AJAX call
    function startChatSession() {
        $.ajax({
            url: aiChatbotSettings.ajaxurl, // Ensure ajaxurl is defined in your PHP and passed to JS
            method: 'POST',
            data: {
                action: 'start_chat_session', // This should match with your PHP AJAX action
            },
            success: function(response) {
                if (response && response.success) {
                    console.log('Chat session started:', response.data.sessionId);
                    localStorage.setItem('aiChatbotSessionId', response.data.sessionId);
                } else {
                    console.error('Failed to start chat session');
                }
            },
            error: function(xhr, status, error) {
                console.error('Start chat session failed:', error);
            }
        });
    }

    // Call to start the chat session
    startChatSession();

    // Existing functionality to update chatbot appearance based on settings
    const chatbotImagePlaceholder = document.querySelector('.chatbot-image-placeholder');
    let chatbotMinimized = document.querySelector('.chatbot-minimized');
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
        const userMessageColor = hexToRGBA(aiChatbotSettings.primary_color, 0.3); // 30% opacity
        document.querySelectorAll('.user-message').forEach(function(message) {
            message.style.backgroundColor = userMessageColor;
        });
    }

    const chatForm = document.getElementById('ai-chatbot-form');
    const chatInput = document.getElementById('ai-chatbot-input');
    const chatConversation = document.getElementById('ai-chatbot-conversation');
    const chatbotContainer = document.getElementById('ai-chatbot');
    const chatbotToggle = document.getElementById('chatbot-toggle');

    if (chatbotMinimized) {
        chatbotContainer.classList.add('minimized');
        chatbotMinimized.style.display = 'block'; // Show the minimized icon by default
    }

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

    chatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const message = chatInput.value.trim();
        const sessionId = localStorage.getItem('aiChatbotSessionId');

        if (message) {
            appendMessage(message, 'user-message');

            fetch(aiChatbotSettings.ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=utf-8',
                },
                body: `action=ai_chatbot_handle_request&message=${encodeURIComponent(message)}&sessionId=${encodeURIComponent(sessionId)}`,
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.response) {
                    appendMessage(data.data.response, 'bot-message');
                } else {
                    const errorMessage = aiChatbotSettings.custom_bot_down_message ? aiChatbotSettings.custom_bot_down_message :
                        'We apologize for the inconvenience, but our chatbot is currently unavailable. Please try again later. Thank you for your patience and understanding.';
                    appendMessage(errorMessage, 'bot-message');
                }
            })
            .catch(error => {
                appendMessage('Error: ' + error.message, 'bot-message');
            });

            chatInput.value = ''; // Clear input field after sending
        }
    });

    chatbotToggle.addEventListener('click', function() {
        chatbotContainer.classList.toggle('minimized');
        chatbotMinimized.style.display = chatbotContainer.classList.contains('minimized') ? 'block' : 'none';
    });

    // Re-check in case the chatbotMinimized was dynamically added
    chatbotMinimized = document.querySelector('.chatbot-minimized');
    if (chatbotMinimized) {
        chatbotMinimized.addEventListener('click', function() {
            chatbotContainer.classList.remove('minimized');
            chatbotMinimized.style.display = 'none'; // Hide the minimized icon
        });
    }
});