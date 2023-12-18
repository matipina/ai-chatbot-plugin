<?php
/**
 * Plugin Name: AI Chatbot
 * Description: A WordPress plugin for integrating an AI Chatbot using OpenAI's API.
 * Version: 1.0.0
 * Author: Your Name
 */

session_start();

if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = array();
}

function update_chat_history($user_message, $bot_message) {
    $_SESSION['chat_history'][] = array("user" => $user_message, "bot" => $bot_message);
    if (count($_SESSION['chat_history']) > 10) {
        array_shift($_SESSION['chat_history']);
    }
}

function generate_ai_prompt($new_user_input, $custom_info) {
    $history = "";
    foreach ($_SESSION['chat_history'] as $message_pair) {
        $history .= "User: " . $message_pair['user'] . "\nBot: " . $message_pair['bot'] . "\n";
    }

    $instruction = "Using the information provided and past conversation history, answer the user's question accurately. Do not make up information or speculate. If the information is not available, respond with 'I don't have that information.' Information: ";
    return $instruction . $custom_info . "\n\n" . $history . "User: " . $new_user_input . "\nBot:";
}

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

function ai_chatbot_enqueue_scripts() {
    wp_enqueue_script('ai-chatbot-js', plugins_url('/ai-chatbot.js', __FILE__), array('jquery'), '1.0.0', true);
    wp_enqueue_style('ai-chatbot-css', plugins_url('/ai-chatbot-style.css', __FILE__));
    wp_localize_script('ai-chatbot-js', 'aiChatbot', array('ajaxurl' => admin_url('admin-ajax.php')));
}

add_action('wp_enqueue_scripts', 'ai_chatbot_enqueue_scripts');

function ai_chatbot_handle_request() {
    if (get_option('ai_chatbot_enabled') != '1') {
        wp_send_json_error(array('error' => 'AI ChatBot is disabled'));
        return;
    }

    global $ai_chatbot_questions;
    $custom_info = "";
    foreach ($ai_chatbot_questions as $index => $question) {
        $answer = get_option('ai_chatbot_question_' . ($index + 1), '');
        if (!empty($answer)) {
            $custom_info .= $question . " " . $answer . " ";
        }
    }

    $user_message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : '';
    $openai_api_key = get_option('ai_chatbot_openai_api_key');
    if (!$openai_api_key) {
        wp_send_json_error(array('error' => 'OpenAI API Key is missing'));
        return;
    }

    $prompt = generate_ai_prompt($user_message, $custom_info);
    $openai_url = 'https://api.openai.com/v1/engines/text-davinci-003/completions';

    $data = array('prompt' => $prompt, 'max_tokens' => 150);
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
        return;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    $bot_response = $body['choices'][0]['text'];

    update_chat_history($user_message, $bot_response);

    wp_send_json_success(array('response' => $bot_response));
}

add_action('wp_ajax_ai_chatbot_handle_request', 'ai_chatbot_handle_request');
add_action('wp_ajax_nopriv_ai_chatbot_handle_request', 'ai_chatbot_handle_request');

function ai_chatbot_shortcode() {
    if (get_option('ai_chatbot_enabled') != '1') {
        return ''; // Return nothing if the chatbot is disabled
    }

    ob_start();
    ?>
    <div id="ai-chatbot">
    <div class="chatbot-header">
    <div class="chatbot-image-placeholder"></div>
    <div>
        <span class="chatbot-name">AIBuddy</span>
        <span class="chatbot-status">
            <span class="chatbot-status-dot"></span>
            <span class="chatbot-status-text">Online Now</span>
        </span>
    </div>
</div>
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

add_action('admin_menu', 'ai_chatbot_add_admin_menu');

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
    register_setting('ai_chatbot_plugin_settings', 'ai_chatbot_enabled');
    register_setting('ai_chatbot_plugin_settings', 'ai_chatbot_openai_api_key');

    add_settings_section(
        'ai_chatbot_plugin_settings_section',
        __('Customize your AI ChatBot', 'wordpress'),
        'ai_chatbot_settings_section_callback',
        'ai_chatbot_plugin_settings'
    );

    add_settings_field(
        'ai_chatbot_enabled',
        __('Enable AI ChatBot', 'wordpress'),
        'ai_chatbot_enabled_render',
        'ai_chatbot_plugin_settings',
        'ai_chatbot_plugin_settings_section'
    );

    add_settings_field(
        'ai_chatbot_openai_api_key',
        __('OpenAI API Key', 'wordpress'),
        'ai_chatbot_openai_api_key_render',
        'ai_chatbot_plugin_settings',
        'ai_chatbot_plugin_settings_section'
    );

    global $ai_chatbot_questions;
    foreach ($ai_chatbot_questions as $index => $question) {
        register_setting('ai_chatbot_plugin_settings', 'ai_chatbot_question_' . ($index + 1));
        add_settings_field(
            'ai_chatbot_question_' . ($index + 1),
            '', // Leave the title empty to prevent duplication
            'ai_chatbot_question_render',
            'ai_chatbot_plugin_settings',
            'ai_chatbot_plugin_settings_section',
            array(
                'label_for' => 'ai_chatbot_question_' . ($index + 1),
                'question_text' => $question,
                'index' => $index + 1
            )
        );
    }
}

add_action('admin_init', 'ai_chatbot_settings_init');

function ai_chatbot_enabled_render() {
    $options = get_option('ai_chatbot_enabled');
    ?>
    <input type='checkbox' name='ai_chatbot_enabled' <?php checked($options, 1); ?> value='1'>
    <?php
}

function ai_chatbot_openai_api_key_render() {
    $options = get_option('ai_chatbot_openai_api_key');
    ?>
    <input type='text' name='ai_chatbot_openai_api_key' value='<?php echo esc_attr($options); ?>' size='50'>
    <?php
}

function ai_chatbot_question_render($args) {
    $options = get_option($args['label_for']);
    ?>
    <div class="ai-chatbot-question">
        <p><strong>Question <?php echo esc_html($args['index']); ?>:</strong></p>
        <p><?php echo esc_html($args['question_text']); ?></p>
        <textarea cols="40" rows="5" id="<?php echo esc_attr($args['label_for']); ?>" name="<?php echo esc_attr($args['label_for']); ?>"><?php echo esc_textarea($options); ?></textarea>
    </div>
    <?php
}


function ai_chatbot_settings_section_callback() {
    echo __('Answer the following questions to customize your AI ChatBot.', 'wordpress');
}
?>
