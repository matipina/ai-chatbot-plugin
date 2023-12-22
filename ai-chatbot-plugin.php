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


/**
 * Updates the chat history with a new user and bot message.
 *
 * This function appends a new message pair (user and bot) to the 'chat_history' session variable.
 * If the number of messages exceeds 10, it removes the oldest message to maintain a history of the
 * most recent 10 messages.
 *
 * @param string $user_message The user's message to be added to the chat history.
 * @param string $bot_message The bot's response to be added to the chat history.
 * @return void
 */
function update_chat_history($user_message, $bot_message)
{
    // Create associative array with $user_message and $bot_message and append in to chat history
    $_SESSION['chat_history'][] = array("user" => $user_message, "bot" => $bot_message);
    if (count($_SESSION['chat_history']) > 10) {
        array_shift($_SESSION['chat_history']);
    }
}


/**
 * Generates a prompt for an AI system based on user input, custom information, and conversation history.
 *
 * This function constructs a prompt by combining instruction text, custom information,
 * and the history of past user and bot interactions. The instruction text is loaded from
 * an external text file specified by $instructionFilePath.
 *
 * @param string $new_user_input The new user input for which the prompt is generated.
 * @param string $custom_info Custom information to be included in the prompt.
 * @return string The generated AI prompt.
 */
function generate_ai_prompt($new_user_input, $custom_info)
{
    // Build conversation history string
    $history = "";
    foreach ($_SESSION['chat_history'] as $message_pair) {
        $history .= "User: " . $message_pair['user'] . "\nBot: " . $message_pair['bot'] . "\n";
    }

    // Load instruction text from a file
    $instructionFilePath = 'instruction.txt';
    $instruction = file_get_contents($instructionFilePath);
    // Combine instruction, custom information, and conversation history
    return $instruction . $custom_info . "\n\n" . $history . "User: " . $new_user_input . "\nBot:";
}

/**
 * Enqueues scripts and styles for the AI Chatbot plugin.
 *
 * This function is hooked into the 'wp_enqueue_scripts' action, ensuring that the necessary
 * JavaScript and CSS files are loaded on the front end when WordPress enqueues scripts.
 * It also uses wp_localize_script to make the 'ajaxurl' variable available to the enqueued
 * JavaScript file for AJAX requests.
 *
 * @return void
 */
function ai_chatbot_enqueue_scripts()
{
    // Enqueue the AI Chatbot JavaScript file with jQuery as a dependency
    wp_enqueue_script('ai-chatbot-js', plugins_url('/ai-chatbot.js', __FILE__), array('jquery'), '1.0.0', true);

    // Enqueue the AI Chatbot CSS file
    wp_enqueue_style('ai-chatbot-css', plugins_url('/ai-chatbot-style.css', __FILE__));

    // Enqueue Font Awesome for chatbot toggle icon
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css');
    // Localize the AI Chatbot JavaScript file to make 'ajaxurl' available
    wp_localize_script('ai-chatbot-js', 'aiChatbot', array('ajaxurl' => admin_url('admin-ajax.php')));
}

/**
 * Handles the AI Chatbot request, processes user input, and communicates with the OpenAI API.
 *
 * This function is responsible for managing AI Chatbot requests. It checks if the AI Chatbot is enabled,
 * retrieves custom information from settings, processes user input, communicates with the OpenAI API,
 * and updates the chat history. The response from the OpenAI API is sent back as a JSON-encoded success
 * or error message.
 *
 * @return void
 */
function ai_chatbot_handle_request()
{
    // Check if AI Chatbot is enabled
    if (get_option('ai_chatbot_enabled') != '1') {
        wp_send_json_error(array('error' => 'AI ChatBot is disabled'));
        return;
    }

    // Retrieve custom information from settings
    global $ai_chatbot_questions;
    $custom_info = "";
    foreach ($ai_chatbot_questions as $index => $question) {
        $answer = get_option('ai_chatbot_question_' . ($index + 1), '');
        if (!empty($answer)) {
            $custom_info .= $question . " " . $answer . " ";
        } else {
            // If there's no answer, add default message
            $custom_info .= $question . "There is no information available.";
        }
    }

    // Process user input
    $user_message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : '';

    // Retrieve OpenAI API key
    $openai_api_key = get_option('ai_chatbot_openai_api_key');
    if (!$openai_api_key) {
        wp_send_json_error(array('error' => 'OpenAI API Key is missing'));
        return;
    }

    // Generate prompt for the OpenAI API
    $prompt = generate_ai_prompt($user_message, $custom_info);

    // OpenAI API URL
    $openai_url = 'https://api.openai.com/v1/engines/text-davinci-003/completions';

    // Prepare data for the API request
    $data = array('prompt' => $prompt, 'max_tokens' => 150);

    // Make a POST request to the OpenAI API
    $response = wp_remote_post($openai_url, array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $openai_api_key
        ),
        'body' => json_encode($data),
        'method' => 'POST',
        'data_format' => 'body',
    ));

    // Check for API request errors
    if (is_wp_error($response)) {
        wp_send_json_error(array('error' => 'Failed to connect to OpenAI API'));
        return;
    }

    // Decode the API response and extract the bot's response
    $body = json_decode(wp_remote_retrieve_body($response), true);
    $bot_response = $body['choices'][0]['text'];

    // Update the chat history with the user's message and bot's response
    update_chat_history($user_message, $bot_response);

    // Send a JSON-encoded success response with the bot's response
    wp_send_json_success(array('response' => $bot_response));
}

/**
 * Callback function for automatically integrating the AI Chatbot into post or page content.
 *
 * This function is hooked into the `the_content` filter. It checks if the AI Chatbot plugin
 * is enabled, and if so, appends the chatbot content to the end of the post or page content.
 *
 * @param string $content The original post or page content.
 *
 * @return string Modified content with AI Chatbot integration.
 */
function automatic_integration_callback($content)
{
    // Check if the plugin is enabled
    $plugin_enabled = get_option('ai_chatbot_enabled');

    if ($plugin_enabled == 1) {
        // Start output buffering
        ob_start();
        ?>
        <div id="ai-chatbot">
            <!-- Gray Circle for Minimized State -->
            <div id="chatbot-minimized" class="chatbot-minimized"></div>

            <!-- Chatbot Header with Minimize/Expand Button -->
            <div class="chatbot-header">
                <div class="chatbot-image-placeholder"></div>
                <div>
                    <span class="chatbot-name">AIBuddy</span>
                    <span class="chatbot-status">
                        <span class="chatbot-status-dot"></span>
                        <span class="chatbot-status-text">Online Now</span>
                    </span>
                </div>
                <!-- Minimize/Expand Button with Font Awesome Icon -->
                <button id="chatbot-toggle" class="chatbot-toggle">
                    <i class="fas fa-times"></i> <!-- Font Awesome icon -->
                </button>
            </div>

            <!-- Chatbot Conversation Area -->
            <div id="ai-chatbot-conversation" class="ai-chatbot-conversation">
                <!-- Messages will be dynamically inserted here -->
            </div>

            <!-- Chatbot Input Form -->
            <form id="ai-chatbot-form">
                <input type="text" id="ai-chatbot-input" placeholder="Type your message here...">
                <input type="submit" id="ai-chatbot-submit" value="Send">
            </form>
        </div>
        <?php

        // Get the buffered content
        $plugin_content = ob_get_clean();

        // Append your plugin content to the post/page content
        $content .= $plugin_content;
    }

    return $content;
}

function ai_chatbot_add_admin_menu()
{
    add_menu_page('AI ChatBot Settings', 'AI ChatBot', 'manage_options', 'ai_chatbot', 'ai_chatbot_settings_page');
}

function ai_chatbot_settings_page()
{
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

function ai_chatbot_settings_init()
{
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

function ai_chatbot_enabled_render()
{
    $options = get_option('ai_chatbot_enabled');
    ?>
    <input type='checkbox' name='ai_chatbot_enabled' <?php checked($options, 1); ?> value='1'>
    <?php
}

function ai_chatbot_openai_api_key_render()
{
    $options = get_option('ai_chatbot_openai_api_key');
    ?>
    <input type='text' name='ai_chatbot_openai_api_key' value='<?php echo esc_attr($options); ?>' size='50'>
    <?php
}

function ai_chatbot_question_render($args)
{
    $options = get_option($args['label_for']);
    ?>
    <div class="ai-chatbot-question">
        <p><strong>Question
                <?php echo esc_html($args['index']); ?>:
            </strong></p>
        <p>
            <?php echo esc_html($args['question_text']); ?>
        </p>
        <textarea cols="40" rows="5" id="<?php echo esc_attr($args['label_for']); ?>"
            name="<?php echo esc_attr($args['label_for']); ?>"><?php echo esc_textarea($options); ?></textarea>
    </div>
    <?php
}

function ai_chatbot_settings_section_callback()
{
    echo __('Answer the following questions to customize your AI ChatBot.', 'wordpress');
}


session_start();

if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = array();
}

// Hook the ai_chatbot_enqueue_scripts function to the 'wp_enqueue_scripts' action
add_action('wp_enqueue_scripts', 'ai_chatbot_enqueue_scripts');

add_action('wp_ajax_ai_chatbot_handle_request', 'ai_chatbot_handle_request');
add_action('wp_ajax_nopriv_ai_chatbot_handle_request', 'ai_chatbot_handle_request');


add_action('admin_menu', 'ai_chatbot_add_admin_menu');

// Hook into the_content filter
add_filter('the_content', 'automatic_integration_callback');

add_action('admin_init', 'ai_chatbot_settings_init');

?>