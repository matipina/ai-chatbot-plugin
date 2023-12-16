<?php
/**
 * Plugin Name: AI Chatbot
 * Description: A WordPress plugin for integrating an AI Chatbot using OpenAI's API.
 * Version: 1.0.0
 * Author: Your Name
 */

function ai_chatbot_plugin_test_function() {
    echo '<div id="message" class="updated fade"><p>AI Chatbot Plugin is activated!</p></div>';
}

register_activation_hook(__FILE__, 'ai_chatbot_plugin_test_function');

function ai_chatbot_enqueue_scripts() {
    wp_enqueue_script('ai-chatbot-js', plugins_url('/ai-chatbot.js', __FILE__), array('jquery'), '1.0.0', true);
    wp_enqueue_style('ai-chatbot-css', plugins_url('/ai-chatbot-style.css', __FILE__));

    $translation_array = array(
        'ajaxurl' => admin_url('admin-ajax.php')
    );
    wp_localize_script('ai-chatbot-js', 'aiChatbot', $translation_array);
}

add_action('wp_enqueue_scripts', 'ai_chatbot_enqueue_scripts');

function ai_chatbot_handle_request() {
    $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : '';

    // OpenAI API URL
    $openai_url = 'https://api.openai.com/v1/engines/text-davinci-003/completions';

    // Replace 'Your_OpenAI_API_Key' with your actual OpenAI API Key
    $openai_api_key = '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';

    // Data for the API request
    $data = array(
        'prompt' => $message,
        'max_tokens' => 150
    );

    // API request to OpenAI
    $response = wp_remote_post($openai_url, array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $openai_api_key
        ),
        'body' => json_encode($data),
        'method' => 'POST',
        'data_format' => 'body',
    ));

    if (is_wp_error($response)) {
        wp_send_json_error(array('error' => 'Failed to connect to OpenAI API'));
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    wp_send_json_success(array('response' => $body['choices'][0]['text']));
    wp_die();
}

add_action('wp_ajax_ai_chatbot_handle_request', 'ai_chatbot_handle_request');
add_action('wp_ajax_nopriv_ai_chatbot_handle_request', 'ai_chatbot_handle_request');

function ai_chatbot_shortcode() {
    ob_start();
    ?>
    <div id="ai-chatbot">
        <div id="ai-chatbot-conversation" class="ai-chatbot-conversation">
            <!-- Messages will be dynamically inserted here -->
        </div>
        <form id="ai-chatbot-form">
            <input type="text" id="ai-chatbot-input" placeholder="Type your message here...">
            <input type="submit" id="ai-chatbot-submit" value="Send">
        </form>
    </div>
    <?php
    return ob_get_clean();
}

add_shortcode('ai_chatbot', 'ai_chatbot_shortcode');
?>
