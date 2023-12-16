<?php
/**
 * Plugin Name: AI Chatbot
 * Description: A WordPress plugin for integrating an AI Chatbot using OpenAI's API.
 * Version: 1.0.0
 * Author: Your Name
 */

$ai_chatbot_questions = array(
    "Describe your products or services in depth.",
    "What is your business's unique selling point?",
    "What are your most frequently asked questions?",
    "Can you describe your target audience?",
    "What are your business hours and location?",
    "What payment methods do you accept?",
    "Are there any ongoing promotions or discounts?",
    "How does your shipping and return process work?",
    "What guarantees or warranties do you offer?",
    "How can customers contact you for support?"
);

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
    global $ai_chatbot_questions;

    $custom_info = "";
    for ($i = 1; $i <= count($ai_chatbot_questions); $i++) {
        $answer = get_option('ai_chatbot_question_' . $i, '');
        if (!empty($answer)) {
            $custom_info .= $ai_chatbot_questions[$i - 1] . " " . $answer . " ";
        }
    }

    $user_message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : '';
    $prompt = "As a professional customer representative for a business with the following information: " . $custom_info . "Answer the user's question: " . $user_message;

    $openai_url = 'https://api.openai.com/v1/engines/text-davinci-003/completions';
    $openai_api_key = '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';

    $data = array(
        'prompt' => $prompt,
        'max_tokens' => 150
    );

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

function ai_chatbot_add_admin_menu() {
    add_menu_page('AI ChatBot Settings', 'AI ChatBot', 'manage_options', 'ai_chatbot', 'ai_chatbot_settings_page');
}

function ai_chatbot_settings_page() {
    ?>
    <div class="wrap">
    <h1>AI ChatBot Settings</h1>
    <form method="post" action="options.php">
        <?php
        settings_fields('ai_chatbot_plugin_settings');
        do_settings_sections('ai_chatbot_plugin_settings');
        submit_button();
        ?>
    </form>
    </div>
    <?php
}

function ai_chatbot_settings_init() {
    global $ai_chatbot_questions;

    add_settings_section(
        'ai_chatbot_plugin_settings_section', 
        __( 'Customize your AI ChatBot', 'wordpress' ), 
        'ai_chatbot_settings_section_callback', 
        'ai_chatbot_plugin_settings'
    );

    foreach ($ai_chatbot_questions as $index => $question) {
        register_setting('ai_chatbot_plugin_settings', 'ai_chatbot_question_' . ($index + 1));
        add_settings_field( 
            'ai_chatbot_question_' . ($index + 1), 
            __( 'Question ' . ($index + 1), 'wordpress' ), 
            'ai_chatbot_question_render', 
            'ai_chatbot_plugin_settings', 
            'ai_chatbot_plugin_settings_section', 
            array('question_number' => ($index + 1), 'question_text' => $question)
        );
    }
}

function ai_chatbot_question_render($args) {
    $options = get_option('ai_chatbot_question_' . $args['question_number']);
    ?>
    <label for='ai_chatbot_question_<?php echo $args['question_number']; ?>'><?php echo esc_html($args['question_text']); ?></label>
    <textarea cols='40' rows='5' id='ai_chatbot_question_<?php echo $args['question_number']; ?>' name='ai_chatbot_question_<?php echo $args['question_number']; ?>'><?php echo esc_textarea($options); ?></textarea>
    <?php
}

function ai_chatbot_settings_section_callback() {
    echo __( 'Answer the following questions to customize your AI ChatBot.', 'wordpress' );
}

add_action('admin_menu', 'ai_chatbot_add_admin_menu');
add_action('admin_init', 'ai_chatbot_settings_init');
