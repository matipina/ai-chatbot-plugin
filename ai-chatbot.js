document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('ai-chatbot-form');
    document.addEventListener('DOMContentLoaded', function() {
        var form = document.getElementById('ai-chatbot-form');
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var input = document.getElementById('ai-chatbot-input').value;
    
            console.log("Sending message to server:", input); // Debugging line
    
            var data = {
                'action': 'ai_chatbot_response',
                'message': input
            };
    
            fetch(ajax_object.ajaxurl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams(data).toString()
            })
            .then(response => response.json())
            .then(data => {
                console.log("Response received from server:", data); // Debugging line
                document.getElementById('ai-chatbot-response').innerHTML = data.response;
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('ai-chatbot-response').innerHTML = 'An error occurred.';
            });
        });
    });
});
