<?php
/*
Plugin Name: AI ChatBot Plugin
Plugin URI: http://yourwebsite.com/
Description: A plugin for integrating AI ChatBot into WordPress.
Version: 1.0
Author: Tiago Aragona
Author URI: http://yourwebsite.com/
*/

// Test function to confirm the plugin is activated
function ai_chatbot_plugin_test_function() {
    echo 'AI ChatBot Plugin is activated and working!';
}
add_action('admin_notices', 'ai_chatbot_plugin_test_function');

// Shortcode function to display the chat interface
function ai_chatbot_shortcode() {
    $content = '';
    $content .= '<form id="ai-chatbot-form">';
    $content .= '<textarea id="ai-chatbot-input" name="ai-chatbot-input"></textarea>';
    $content .= '<input type="submit" value="Ask">';
    $content .= '</form>';
    $content .= '<div id="ai-chatbot-response"></div>';
    return $content;
}

add_shortcode('ai_chatbot', 'ai_chatbot_shortcode');

// Function to enqueue scripts and styles
function ai_chatbot_enqueue_scripts() {
    wp_enqueue_script('ai-chatbot-script', plugins_url('ai-chatbot.js', __FILE__), array(), '1.0.0', true);
    wp_localize_script('ai-chatbot-script', 'ajax_object', array('ajaxurl' => admin_url('admin-ajax.php')));
    wp_enqueue_style('ai-chatbot-style', plugins_url('ai-chatbot-style.css', __FILE__));
}
add_action('wp_enqueue_scripts', 'ai_chatbot_enqueue_scripts');

// AJAX handlers for logged-in and logged-out users
add_action('wp_ajax_ai_chatbot_response', 'ai_chatbot_handle_request');
add_action('wp_ajax_nopriv_ai_chatbot_response', 'ai_chatbot_handle_request');

function ai_chatbot_handle_request() {
    error_log('Received AJAX request');
    // Get the message from the AJAX request
    $message = sanitize_text_field($_POST['message']);

    // Your OpenAI API key
    $openai_api_key = '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';

    // Set up the request to OpenAI
    $response = wp_remote_post('https://api.openai.com/v1/engines/davinci/completions', array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $openai_api_key
        ),
        'body' => json_encode(array(
            'prompt' => $message,
            'max_tokens' => 150
        ))
    ));

    if (is_wp_error($response)) {
        wp_send_json_error(array('response' => 'Error communicating with OpenAI'));
        return;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    $ai_response = $body['choices'][0]['text'];

    // Send a JSON response back
    wp_send_json_success(array('response' => $ai_response));
}
