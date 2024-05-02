<?php
/**
 * Plugin Name: AI Chatbot Generator
 * Description: A WordPress plugin for generating custom AI Chatbots using Google Gemini API.
 * Version: 0.1.0
 * Author: Tiago Aragona & Matias Piña
 */



if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use GeminiAPI\Client;
use GeminiAPI\Resources\Parts\TextPart;

$ai_chatbot_questions = array(
    "Describe your products or services in depth. (Include names, prices, and more)",
    "What is your business's name? How does it usually communicate with the users?",
    "What are your most frequently asked questions?",
    "Can you describe your target audience?",
    "What are your business hours and location?",
    "What payment methods do you accept?",
    "Are there any ongoing promotions or discounts?",
    "How does your shipping and return process work?",
    "What guarantees or warranties do you offer?",
    "How can customers contact you for support?"
);

function handle_start_chat_session()
{
    // Your code to handle the request
    wp_send_json_success(array('sessionId' => session_id()));
}
function myplugin_enqueue_font_awesome()
{
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css');
}

function myplugin_enqueue_admin_dark_mode_style()
{
    wp_enqueue_style('myplugin-admin-dark-mode', plugins_url('admin-dark-mode.css', __FILE__));
}

function ai_chatbot_enqueue_admin_styles()
{
    wp_enqueue_style('ai-chatbot-css', plugins_url('/ai-chatbot-style.css', __FILE__));
}

function myplugin_enqueue_bootstrap()
{
    // Enqueue Bootstrap CSS
    wp_enqueue_style('bootstrap-css', 'https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css');

    // Enqueue Bootstrap JS
    wp_enqueue_script('bootstrap-js', 'https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.bundle.min.js', array('jquery'), null, true);
    wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), null, true);

    // Enqueue emotions-chart.js with Chart.js as a dependency
    wp_enqueue_script('emotions-chart-js', plugins_url('/emotions-chart.js', __FILE__), array('chart-js'), '1.0.0', true);

    // Assume get_last_7_days_emotion_data() fetches the required data
    $emotion_data = get_last_7_days_emotion_data(); // Make sure this function exists and fetches data correctly
    wp_localize_script('emotions-chart-js', 'aiChatbotEmotionData', $emotion_data);

}

function myplugin_enqueue_google_fonts()
{
    wp_enqueue_style('myplugin-dm-sans-font', 'https://fonts.googleapis.com/css2?family=DM+Sans&display=swap');
}

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
    global $wpdb;
    $table_name = $wpdb->prefix . 'sessions';

    // Assume session_id is available. Generate or retrieve it accordingly.
    $session_id = session_id(); // Example, use actual logic to generate/retrieve session ID.

    // Capture the user's IP address
    $user_ip = $_SERVER['REMOTE_ADDR'];

    // Define file path in the WordPress uploads directory
    $upload_dir = wp_upload_dir();
    $file_name = $session_id . '.txt';
    $file_path = $upload_dir['basedir'] . '/sessions/' . $file_name;

    // Ensure the directory exists
    if (!file_exists($upload_dir['basedir'] . '/sessions')) {
        wp_mkdir_p($upload_dir['basedir'] . '/sessions');
    }

    // Append the current conversation to the file
    $conversation_content = "User: $user_message\nBot: $bot_message\n";
    file_put_contents($file_path, $conversation_content, FILE_APPEND);

    // Save or update the file URL and user IP in the database
    $file_url = $upload_dir['baseurl'] . '/sessions/' . $file_name;

    // Check if a record already exists for this session_id, update if it does, insert if it doesn't
    $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE session_id = %s", $session_id));
    if ($exists > 0) {
        // Update existing record to include user_ip
        $wpdb->update(
            $table_name,
            [
                'conversation' => $file_url, // new value
                'user_ip' => $user_ip // include user_ip here
            ],
            ['session_id' => $session_id] // condition
        );
    } else {
        // Insert new record including user_ip
        $wpdb->insert(
            $table_name,
            [
                'session_id' => $session_id,
                'conversation' => $file_url,
                'user_ip' => $user_ip // include user_ip here
            ]
        );
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
    $instructionFilePath = plugin_dir_path(__FILE__) . 'instruction.txt';
    $instruction = file_get_contents($instructionFilePath);

    // Combine instruction, custom information, and conversation history
    $prompt = $instruction . "\n" . $custom_info . "\n\n" . $history . "User: " . $new_user_input . "\nBot:";
    return $prompt;
}

function ai_chatbot_create_conversations_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'sessions';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        session_id VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
        conversation LONGTEXT NOT NULL,
        user_ip VARCHAR(45) NOT NULL, /* Support for IPv6 */
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

function ai_chatbot_create_emotion_logs_table()
{
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . 'messages';
    $sessions_table = $wpdb->prefix . 'sessions';

    // Create the messages table without foreign key constraints
    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        session_id VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        emotion VARCHAR(50) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Check if the table was successfully created before attempting to add a foreign key
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") == $table_name) {
        // SQL to add foreign key constraint
        $fk_sql = "ALTER TABLE $table_name ADD CONSTRAINT fk_session_id FOREIGN KEY (session_id) REFERENCES $sessions_table(session_id)";
        $fk_result = $wpdb->query($fk_sql);
        if (false === $fk_result) {
            error_log("Failed to add foreign key to {$table_name}: " . $wpdb->last_error);
        }
    } else {
        error_log("Failed to create the table {$table_name}.");
    }
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

function insert_emotion_data($session_id, $message, $emotion)
{
    global $wpdb;
    $wpdb->insert(
        $wpdb->prefix . 'messages',
        array(
            'session_id' => $session_id,
            'message' => $message,
            'emotion' => $emotion,
            'created_at' => current_time('Y-m-d H:i:s')
        ),
        array('%s', '%s', '%s', '%s')
    );
}

function ai_chatbot_enqueue_scripts()
{
    wp_enqueue_script('jquery');
    // Enqueue the AI Chatbot JavaScript file with jQuery as a dependency
    wp_enqueue_script('ai-chatbot-js', plugins_url('/ai-chatbot.js', __FILE__), array('jquery'), '1.0.0', true);

    // Enqueue the AI Chatbot CSS file
    wp_enqueue_style('ai-chatbot-css', plugins_url('/ai-chatbot-style.css', __FILE__));

    // Enqueue Font Awesome for chatbot toggle icon
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css');

    // Combine and pass settings to the JavaScript file


    $chatbot_settings = array(
        'ajaxurl' => admin_url('admin-ajax.php'), // AJAX URL for WordPress
        'image_url' => get_option('ai_chatbot_image_url'), // Pass the image URL
        'primary_color' => get_option('ai_chatbot_primary_color', '#007bff'), // Default blue color
        'custom_bot_down_message' => get_option('custom_bot_down_message'), // Custom bot down message
        'defaultMessage' => get_option('ai_chatbot_default_message', 'Hello! I\'m here to help you. What can I do for you today?'),
    );


    wp_localize_script('ai-chatbot-js', 'aiChatbotSettings', $chatbot_settings);

    // Add a script to wait for DOMContentLoaded
    wp_add_inline_script('ai-chatbot-js', '
         document.addEventListener("DOMContentLoaded", function() {
         });
     ', 'after');
}


/**
 * Handles the AI Chatbot request, processes user input, and communicates with the gemini API.
 *
 * This function is responsible for managing AI Chatbot requests. It checks if the AI Chatbot is enabled,
 * retrieves custom information from settings, processes user input, communicates with the gemini API,
 * and updates the chat history. The response from the gemini API is sent back as a JSON-encoded success
 * or error message.
 *
 * @return void
 */

function plugin_custom_log($message)
{
    $log_file = plugin_dir_path(__FILE__) . 'ai-chatbot-debug.log';
    $current_time = date('Y-m-d H:i:s');
    $log_message = $current_time . ' - ' . $message . "\n";
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

function ai_chatbot_handle_request()
{
    $_SESSION['emotion_analysis_done'] = false;
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
            $custom_info .= $question . "There is no information available. ";
        }
    }

    // Process user input
    $user_message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : '';

    // Retrieve Gemini API key
    $gemini_api_key = get_option('ai_chatbot_gemini_api_key'); // Ensure you have this option available
    if (!$gemini_api_key) {
        wp_send_json_error(array('error' => 'Gemini API Key is missing'));
        return;
    }

    // Initialize Gemini API Client

    $client = new Client($gemini_api_key);

    $prompt = generate_ai_prompt($user_message, $custom_info);

    // Generate response using Gemini API
    $response = $client->geminiPro()->generateContent(
        new TextPart($prompt),
    );

    $bot_response = $response->text(); // Assuming the text() method retrieves the response text

    // Perform emotional analysis on the user's message
    $emotion_category = perform_emotional_analysis($user_message);
    if ($emotion_category !== false) {
        // Emotion analysis successful, handle accordingly
        update_emotion_counters($emotion_category);
    }

    if ($emotion_category !== false) {
        $session_id = session_id();
        insert_emotion_data($session_id, $user_message, $emotion_category);
    }

    // Update the chat history with the user's message and the bot's response
    update_chat_history($user_message, $bot_response);

    // Send a JSON-encoded success response with just the bot's response
    wp_send_json_success(array('response' => $bot_response));
}


function perform_emotional_analysis($user_message)
{
    // Check if emotional analysis has already been performed for this message
    if (!empty($_SESSION['emotion_analysis_done'])) {
        return false; // Skip the analysis to avoid double counting
    }

    // Retrieve Gemini API key
    $gemini_api_key = get_option('ai_chatbot_gemini_api_key'); // Keep the variable name for the API key
    if (!$gemini_api_key) {
        return false; // Return false indicating failure
    }

    // Define the prompt for emotional analysis
    $emotion_analysis_prompt = "Analyze the emotion of the following message: \"$user_message\". Answer only one word: happiness, sadness, anger, fear, neutral";

    // Initialize Gemini API Client with the API key
    $client = new Client($gemini_api_key); // Assume a generic client initialization for the Gemini API

    // Prepare the prompt for emotional analysis request using the predefined prompt and user message
    // This part might vary depending on how you usually prepare your prompts for Gemini API
    $prompt = $emotion_analysis_prompt; // Directly using the emotional analysis prompt

    // Generate response using Gemini API
    $response = $client->geminiPro()->generateContent(
        new TextPart($prompt),
    );

    // Assuming the text() method retrieves the response text
    $emotion_text = $response->text();

    // Check if we received a valid response
    if (!empty($emotion_text)) {
        // Categorize the emotion based on the response
        $emotion_category = categorize_emotion($emotion_text); // Assuming this function parses the one-word emotion from the response

        // After performing emotional analysis successfully,
        // set a session flag to prevent duplicate analysis
        $_SESSION['emotion_analysis_done'] = true;

        return $emotion_category; // Return the categorized emotion
    }

    return false; // Return false indicating failure
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
    $chatbot_name = get_option('chatbot_name');

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
                    <span class="chatbot-name"><?php echo esc_html($chatbot_name); ?></span>
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
function categorize_emotion($emotion_text)
{
    $emotion_text = strtolower($emotion_text);
    if (strpos($emotion_text, 'happiness') !== false)
        return 'happiness';
    if (strpos($emotion_text, 'sadness') !== false)
        return 'sadness';
    if (strpos($emotion_text, 'anger') !== false)
        return 'anger';
    if (strpos($emotion_text, 'fear') !== false)
        return 'fear';
    // Default to 'Neutral' if no specific emotion is detected
    return 'neutral';
}

function update_emotion_counters($emotion_category)
{
    // Option name for storing serialized emotion data
    $option_name = 'ai_chatbot_emotion_daily_' . $emotion_category;
    $emotion_data = get_option($option_name, array());
    $today = date('Y-m-d');

    // Check if there's already an entry for today, if not, create or increment
    if (!array_key_exists($today, $emotion_data)) {
        $emotion_data[$today] = 1;
    } else {
        $emotion_data[$today]++;
    }

    // Save the updated array back to the options table
    update_option($option_name, $emotion_data);
}


function get_last_7_days_emotion_data()
{
    global $wpdb;
    $start_of_week = date('Y-m-d', strtotime('Monday this week')); // Gets the date for Monday this week
    $end_of_week = date('Y-m-d', strtotime('Sunday this week')); // Gets the date for Sunday this week

    // Adjust the query to fetch data from Monday to Sunday of the current week
    $results = $wpdb->get_results("
        SELECT emotion, DATE(created_at) as created_date, COUNT(*) as count
        FROM {$wpdb->prefix}messages
        WHERE created_at BETWEEN '$start_of_week' AND '$end_of_week 23:59:59'
        GROUP BY DATE(created_at), emotion
        ORDER BY DATE(created_at) ASC
    ", ARRAY_A);

    // Initialize the structure for aiChatbotEmotionData
    $formattedData = [
        'labels' => [],
        'emotions' => [
            'happiness' => [],
            'sadness' => [],
            'anger' => [],
            'fear' => [],
            'neutral' => []
        ]
    ];

    // Prepopulate labels for each day of the week and set initial counts to zero
    for ($i = 0; $i < 7; $i++) {
        $date = date('Y-m-d', strtotime("+$i day", strtotime($start_of_week)));
        $dayName = date('D', strtotime($date)); // Converts dates to day names (Mon, Tue, etc.)
        $formattedData['labels'][] = $dayName;
        foreach ($formattedData['emotions'] as &$emotionCounts) {
            $emotionCounts[] = 0; // Initialize counts to zero for all emotions
        }
    }

    // Populate the data structure with actual counts from the database
    foreach ($results as $result) {
        $date = $result['created_date'];
        $dayName = date('D', strtotime($date));
        $index = array_search($dayName, $formattedData['labels']); // Find the index by day name

        if ($index !== false) {
            $formattedData['emotions'][$result['emotion']][$index] = $result['count'];
        }
    }

    return $formattedData;
}



function ai_chatbot_settings_page()
{
    ?>
    <div class="wrap">
        <div id="ai-chatbot-admin-container" style="background-color: #FAFAFA;">
            <div class="ai-chatbot-content"
                style="padding-left: 100px; padding-right: 100px; padding-top: 50px; padding-bottom: 50px;">
                <!-- Nav tabs -->
                <div class="ai-chatbot-header">
                    <div class="ai-chatbot-logo">
                        <img src="/wordpress/wp-content/plugins/ai-chatbot-plugin/assets/echoslogo3@2x.png"
                            alt="Echos Logo">
                    </div>
                    <!-- Nav tabs -->
                    <ul class="nav nav-tabs" id="aiChatbotTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link" id="custom-questions-tab" data-toggle="tab" href="#customQuestions"
                                role="tab" aria-controls="customQuestions" aria-selected="false">Settings</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" id="emotion-counters-tab" data-toggle="tab" href="#emotionCounters"
                                role="tab" aria-controls="emotionCounters" aria-selected="true">Analytics</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="conversations-tab" data-toggle="tab" href="#conversations" role="tab"
                                aria-controls="conversations" aria-selected="false">Conversations</a>
                        </li>
                    </ul>
                </div>

                <!-- Tab panes -->
                <div class="tab-content" style="margin-top: 20px;">
                    <div class="tab-pane fade show active" id="emotionCounters" role="tabpanel"
                        aria-labelledby="emotion-counters-tab">
                        <?php display_emotion_counters_admin(); ?>
                        <?php display_sessions_chart(); // Call the function here ?>
                        <?php display_emotions_chart(); // Call the function here ?>

                    </div>
                    <div class="tab-pane fade" id="customQuestions" role="tabpanel" aria-labelledby="custom-questions-tab">
                        <form method="post" action="options.php">
                            <?php display_ai_chatbot_settings_form(); ?>
                        </form>
                    </div>
                    <div class="tab-pane fade" id="conversations" role="tabpanel" aria-labelledby="conversations-tab">
                        <?php display_conversations_admin(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function display_conversations_admin()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'sessions';

    // Pagination setup
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page = 10; // Adjust the number of items per page as needed
    $offset = ($current_page - 1) * $per_page;

    // Fetch the total number of conversations
    $total_query = "SELECT COUNT(*) FROM $table_name";
    $total = $wpdb->get_var($total_query);

    // Calculate the total number of pages
    $total_pages = ceil($total / $per_page);

    // Fetch limited conversations for the current page
    $conversations = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset));

    // Start output for conversations
    echo '<div class="ai-chatbot-box">';
    echo '<h3 style="text-align: center;">Conversation Records</h3>';
    echo '<p style="text-align: center; font-size: 16px; margin-top: 0; margin-bottom: 20px;">Browse and download the history of your conversations for in-depth analysis and records.</p>';

    // Start the table
    echo '<div id="conversation-table-container">'; // Container for dynamic content
    echo '<table class="ai-chatbot-admin-table">';
    echo '<thead><tr><th>User ID</th><th>Date</th><th>Conversation File</th></tr></thead>';
    echo '<tbody>';

    // Loop through the conversations and display them
    foreach ($conversations as $conversation) {
        echo '<tr>';
        echo '<td>' . esc_html($conversation->session_id) . '</td>';
        echo '<td>' . esc_html($conversation->created_at) . '</td>';
        echo '<td><a href="' . esc_url($conversation->conversation) . '" class="ai-chatbot-admin-link"><i class="fas fa-arrow-circle-down"></i></i> Download Conversation</a></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</div>'; // End of conversation-table-container

    // Pagination
    echo '<div class="pagination-container" style="text-align:center;">'; // Center pagination
    if ($total_pages > 1) {
        echo '<nav aria-label="Page navigation example"><ul class="pagination justify-content-center">';

        // Previous button
        if ($current_page > 1) {
            echo '<li class="page-item"><a class="page-link" href="' . admin_url('admin.php?page=ai_chatbot&paged=' . ($current_page - 1)) . '">Prev</a></li>';
        } else {
            echo '<li class="page-item disabled"><span class="page-link">Prev</span></li>';
        }

        // Page numbers
        for ($i = 1; $i <= $total_pages; $i++) {
            echo '<li class="page-item' . ($current_page === $i ? ' active' : '') . '"><a class="page-link" href="' . admin_url('admin.php?page=ai_chatbot&paged=' . $i) . '">' . $i . '</a></li>';
        }

        // Next button
        if ($current_page < $total_pages) {
            echo '<li class="page-item"><a class="page-link" href="' . admin_url('admin.php?page=ai_chatbot&paged=' . ($current_page + 1)) . '">Next</a></li>';
        } else {
            echo '<li class="page-item disabled"><span class="page-link">Next</span></li>';
        }

        echo '</ul></nav>';
    }
    echo '</div>'; // End of pagination-container

    echo '</div>'; // End of ai-chatbot-box

    // Pagination
    ?>

    <script type="text/javascript">
        jQuery(document).ready(function ($) {
            $('.pagination-container a.page-link').on('click', function (e) {
                e.preventDefault();

                // Remove active class from all page buttons
                $('.pagination-container a.page-link').parent().removeClass('active');

                // Add active class to the clicked page button
                $(this).parent().addClass('active');

                var page = $(this).text(); // Get the page number from the link text
                var data = {
                    action: 'fetch_conversations',
                    page: page
                };

                $.get(ajaxurl, data, function (response) {
                    $('#conversation-table-container').html(response);
                });
            });
        });
    </script>

    <?php
}

function fetch_conversations()
{
    global $wpdb;

    $current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = 10;
    $offset = ($current_page - 1) * $per_page;

    $table_name = $wpdb->prefix . 'sessions';
    $conversations = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name ORDER BY created DESC LIMIT %d OFFSET %d", $per_page, $offset));

    $html = '<table class="ai-chatbot-admin-table">';
    $html .= '<thead><tr><th>User ID</th><th>Date</th><th>Conversation File</th></tr></thead>';
    $html .= '<tbody>';

    foreach ($conversations as $conversation) {
        $html .= '<tr>';
        $html .= '<td>' . esc_html($conversation->session_id) . '</td>';
        $html .= '<td>' . esc_html($conversation->created_at) . '</td>';
        $html .= '<td><a href="' . esc_url($conversation->conversation) . '" class="ai-chatbot-admin-link">Download Conversation</a></td>';
        $html .= '</tr>';
    }

    $html .= '</tbody></table>';

    echo $html;

    wp_die();
}

function ai_chatbot_settings_init()
{
    register_setting('ai_chatbot_plugin_settings', 'ai_chatbot_enabled');
    register_setting('ai_chatbot_plugin_settings', 'ai_chatbot_gemini_api_key');
    register_setting('ai_chatbot_plugin_settings', 'ai_chatbot_image_url');
    register_setting('ai_chatbot_plugin_settings', 'ai_chatbot_primary_color');
    register_setting('ai_chatbot_plugin_settings', 'chatbot_name');
    register_setting('ai_chatbot_plugin_settings', 'custom_bot_down_message');
    register_setting('ai_chatbot_plugin_settings', 'ai_chatbot_default_message');

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
        'ai_chatbot_primary_color',
        __('Primary Color', 'wordpress'),
        'ai_chatbot_primary_color_render',
        'ai_chatbot_plugin_settings',
        'ai_chatbot_plugin_settings_section'
    );

    add_settings_field(
        'ai_chatbot_image_url',
        __('Chatbot Image URL', 'wordpress'),
        'ai_chatbot_image_url_render',
        'ai_chatbot_plugin_settings',
        'ai_chatbot_plugin_settings_section'
    );

    add_settings_field(
        'ai_chatbot_gemini_api_key',
        __('Gemini API Key', 'wordpress'),
        'ai_chatbot_gemini_api_key_render',
        'ai_chatbot_plugin_settings',
        'ai_chatbot_plugin_settings_section'
    );

    add_settings_field(
        'ai_chatbot_default_message',
        __('Default Introductory Message', 'wordpress'),
        'ai_chatbot_default_message_render',
        'ai_chatbot_plugin_settings',
        'ai_chatbot_plugin_settings_section'
    );

    global $ai_chatbot_questions;
    foreach ($ai_chatbot_questions as $index => $question) {
        register_setting('ai_chatbot_plugin_settings', 'ai_chatbot_question_' . ($index + 1));
        add_settings_field(
            'ai_chatbot_question_' . ($index + 1),
            '&nbsp;', // Use a non-breaking space
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

function custom_bot_down_message_render()
{
    $options = get_option('custom_bot_down_message');
    echo "<input type='text' name='custom_bot_down_message' value='" . esc_attr($options) . "' size='50'>";
}

function ai_chatbot_default_message_render()
{
    $options = get_option('ai_chatbot_default_message');
    echo "<input type='text' name='ai_chatbot_default_message' value='" . esc_attr($options) . "' size='50'>";
}

function ai_chatbot_enabled_render()
{
    $options = get_option('ai_chatbot_enabled');
    ?>
    <input type='checkbox' name='ai_chatbot_enabled' <?php checked($options, 1); ?> value='1'>
    <?php
}

function ai_chatbot_gemini_api_key_render()
{
    $options = get_option('ai_chatbot_gemini_api_key');
    ?>
    <input type='text' name='ai_chatbot_gemini_api_key' value='<?php echo esc_attr($options); ?>' size='50'>
    <?php
}
function get_emotion_chart_data()
{
    $emotion_data = get_last_7_days_emotion_data(); // Assume this returns data correctly
    $chart_data = [
        'labels' => [], // For storing dates
        'datasets' => [] // For storing emotion data
    ];

    // Initialize datasets array with emotion types
    $emotions = ['happiness', 'sadness', 'anger', 'fear', 'neutral'];
    foreach ($emotions as $emotion) {
        $chart_data['datasets'][$emotion] = array_fill(0, 7, 0); // Initialize with zeros for 7 days
    }

    // Assume $emotion_data is an array of arrays with 'emotion', 'created_at', and 'count'
    foreach ($emotion_data as $data) {
        $date = $data['created_at'];
        $emotion = $data['emotion'];
        $count = $data['count'];

        // Ensure the date is in the labels array
        if (!in_array($date, $chart_data['labels'])) {
            $chart_data['labels'][] = $date;
        }

        // Update the count for the specific emotion and date
        $date_index = array_search($date, $chart_data['labels']);
        $chart_data['datasets'][$emotion][$date_index] = $count;
    }

    return $chart_data;
}

function display_emotions_chart()
{
    $chart_data = get_emotion_chart_data();
    wp_localize_script('emotions-chart-js', 'emotionChartData', $chart_data);
}


function ai_chatbot_question_render($args)
{
    $options = get_option($args['label_for']);
    ?>
    <div class="ai-chatbot-question">
        <span class="question-number">Question <?php echo esc_html($args['index']); ?>:</span>
        <span class="question-text"><?php echo esc_html($args['question_text']); ?></span>
        <textarea class="chatbot-input" cols="40" rows="5" id="<?php echo esc_attr($args['label_for']); ?>"
            name="<?php echo esc_attr($args['label_for']); ?>"><?php echo esc_textarea($options); ?></textarea>
    </div>
    <?php
}

function ai_chatbot_settings_section_callback()
{
    echo __('Answer the following questions to customize your AI ChatBot.', 'wordpress');
}

function get_sessions_data_current_week()
{
    global $wpdb;
    date_default_timezone_set('America/New_York'); // Adjust to your server's timezone
    // Calculate the start and end dates of the current week
    $start_of_week = date('Y-m-d', strtotime('Monday this week')) . ' 00:00:00';
    $end_of_week = date('Y-m-d', strtotime('Sunday this week')) . ' 23:59:59';

    // SQL query to fetch session counts grouped by day
    $query = "
        SELECT DATE_FORMAT(created_at, '%Y-%m-%d') AS session_date, COUNT(*) AS session_count
        FROM {$wpdb->prefix}sessions
        WHERE created_at >= '{$start_of_week}' AND created_at <= '{$end_of_week}'
        GROUP BY session_date
        ORDER BY session_date ASC
    ";
    $results = $wpdb->get_results($query, ARRAY_A);
    
    if (empty($results)) {
        error_log('No results found: ' . $wpdb->last_error);
        return []; // Return an empty array if no results
    }

    // Initialize the session data for each day of the current week
    $session_data = [];
    for ($date = strtotime($start_of_week); $date <= strtotime($end_of_week); $date += 86400) {
        $formatted_date = date('Y-m-d', $date);
        $session_data[$formatted_date] = 0; // Initialize each day with zero sessions        
    }

    // Populate the session data with actual counts from the database
    foreach ($results as $row) {
        $session_data[$row['session_date']] = (int) $row['session_count'];
    }

    return $session_data;
}

function display_emotion_counters_admin()
{
    global $wpdb;

    // Define the emotions and their corresponding colors
    $emotion_colors = array(
        'happiness' => '#FFE69C',
        'sadness' => '#9EEAF9',
        'anger' => '#F8D7DA',
        'fear' => '#A6E9D5',
        'neutral' => '#E9ECEF',
        // Add more emotions and their colors as needed
    );

    // Define the list of emotions
    $emotions = array_keys($emotion_colors);

    // Initialize total messages count
    $totalMessages = 0;

    // Start of the box for emotion counters
    echo '<div class="ai-chatbot-box mb-4">';
    echo '<div class="row">'; // Bootstrap row for a responsive grid layout of emotion counters

    // Loop through each emotion to display its count
    foreach ($emotions as $emotion) {
        // Query the database to get the count for the current emotion
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}messages WHERE emotion = %s",
                $emotion
            )
        );

        // Add the count to the total messages count
        $totalMessages += $count;

        // Get the color for the current emotion
        $emotionColor = isset($emotion_colors[$emotion]) ? $emotion_colors[$emotion] : '#FFFFFF';

        // Display the emotion card with its count
        echo '<div class="col-md-4 mb-4">';
        echo '<div class="emotion-card card">';
        echo '<div class="card-body">';
        echo '<div class="emotion-name-group" style="background-color: ' . $emotionColor . ';">';
        echo '<h5 class="emotion-name">' . ucfirst($emotion) . '</h5>';
        echo '</div>';
        echo '<div class="number-messages-group">';
        echo '<p class="emotion-count">' . $count . '</p>';
        echo '<p class="emotion-messages">Messages</p>';
        echo '</div>'; // Close number-messages-group
        echo '</div>'; // Close card-body
        echo '</div>'; // Close emotion-card
        echo '</div>'; // Close column
    }

    // Display total messages card
    echo '<div class="col-md-4 mb-4">';
    echo '<div class="total-card card">';
    echo '<div class="total-body">';
    echo '<div class="number-messages-group">';
    echo '<p class="total-count" style="color: white;">' . $totalMessages . '</p>';
    echo '<p class="total-message" style="color: white;">Total Messages</p>';
    echo '</div>'; // Close number-messages-group
    echo '</div>'; // Close card-body
    echo '</div>'; // Close emotion-card
    echo '</div>'; // Close column

    echo '</div>'; // Close row for emotion counters
    echo '</div>'; // Close the box for emotion counters

    echo '<div class="row">'; // Bootstrap row for aligning charts horizontally

    // Box for the Sessions Chart
    echo '<div class="col-md-6">'; // Half-width column for the second chart
    echo '<div class="ai-chatbot-box" style="height: 500px; width: 100%;">'; // Apply styling to the Emotions Chart box
    echo '<h3>Sessions Chart</h3>'; // Title on top
    echo '<canvas id="sessionsChart" style="height: 90%; width: 100%;"></canvas>'; // Chart below the title
    echo '</div>'; // Close the box for the Emotions Chart
    echo '</div>'; // Close the column for the Emotions Chart

    // Box for the Emotions Chart
    echo '<div class="col-md-6">'; // Half-width column for the second chart
    echo '<div class="ai-chatbot-box" style="height: 500px; width: 100%;">'; // Apply styling to the Emotions Chart box
    echo '<h3>Emotion Analysis Chart</h3>'; // Title on top
    echo '<canvas id="emotionsChart" style="height: 100%; width: 100%;"></canvas>'; // Chart below the title
    echo '</div>'; // Close the box for the Emotions Chart
    echo '</div>'; // Close the column for the Emotions Chart

    echo '</div>'; // Close the row for charts
}


function get_sessions_data_last_7_days()
{
    global $wpdb;
    // Example query, adjust according to your actual data storage and structure
    $results = $wpdb->get_results("
        SELECT DATE_FORMAT(created_at, '%Y-%m-%d') AS session_date, COUNT(*) AS session_count
        FROM {$wpdb->prefix}sessions
        WHERE created_at >= CURDATE() - INTERVAL 7 DAY
        GROUP BY session_date
        ORDER BY session_date ASC
    ", ARRAY_A);

    return $results;
}

function display_sessions_chart()
{
    $session_data = get_sessions_data_current_week();
    $formatted_labels = array_map(function ($day) {
        return date('D', strtotime($day));
    }, array_keys($session_data));

    $data_for_chart = [
        'labels' => $formatted_labels,
        'datasets' => [
            [
                'label' => 'Sessions',
                'data' => array_values($session_data),
                'backgroundColor' => 'rgb(82, 39, 204)',
                'borderColor' => 'rgb(75, 192, 192)',
                'barPercentage' => 0.5,
                'categoryPercentage' => 0.8,
                'borderRadius' => ['topLeft' => 20, 'topRight' => 20],
                'tension' => 0.1
            ]
        ]
    ];

    echo "<script>
    document.addEventListener('DOMContentLoaded', function() {
        var ctx = document.getElementById('sessionsChart');
        if (ctx) {
            var ctxSessions = ctx.getContext('2d');
            if (window.myChartInstance) {
                window.myChartInstance.destroy();
            }
            console.log(" . json_encode($data_for_chart) . "); // Log the data for inspection
            window.myChartInstance = new Chart(ctxSessions, {
                type: 'bar',
                data: " . json_encode($data_for_chart) . ",
                options: {
                    scales: { y: { beginAtZero: true } },
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: true } } // Ensure the legend is displayed for debugging
                }
            });
        } else {
            console.error('SessionsChart element not found');
        }
    });
    </script>";
}


function ai_chatbot_image_url_render()
{
    $options = get_option('ai_chatbot_image_url');
    ?>
    <input type='text' name='ai_chatbot_image_url' value='<?php echo esc_attr($options); ?>' size='50'>
    <?php
}

function ai_chatbot_primary_color_render()
{
    $color = get_option('ai_chatbot_primary_color', '#007bff'); // Default blue color
    echo '<input type="color" name="ai_chatbot_primary_color" value="' . esc_attr($color) . '">';
}


function display_ai_chatbot_settings_form()
{
    echo '<div id="chatbot-box">'; // Start of existing settings box
    // New outer div for styling
    echo '<div class="white-box" style="background-color: white; padding: 80px; border-radius: 20px;">';
    echo '<form method="post" action="options.php">';
    settings_fields('ai_chatbot_plugin_settings');
    do_settings_sections('ai_chatbot_plugin_settings');

    // Add a new field for the chatbot name
    echo '<label for="chatbot_name">Chatbot Name:</label>';
    echo '<input type="text" id="chatbot_name" name="chatbot_name" value="' . esc_attr(get_option('chatbot_name', 'Chatbot')) . '"/><br/><br/>';

    // Add a new field for the down message
    echo '<label for="custom_bot_down_message">Message to show if the bot goes down:</label>';
    echo '<input type="text" id="custom_bot_down_message" name="custom_bot_down_message" value="' . esc_attr(get_option('custom_bot_down_message', 'I am sorry, the chatbot is down for the moment. Try again later!')) . '"/><br/><br/>';

    echo '<button type="submit" name="submit" id="submit" class="custom-submit-button">Save Changes</button>';
    echo '</form>';
    echo '</div>'; // Close new white-box
    echo '</div>'; // End of chatbot-box
}


function exclude_files_from_wp_rocket($excluded_files)
{
    $excluded_files[] = '/wp-content/plugins/ai-chatbot-plugin/ai-chatbot.js';
    $excluded_files[] = '/wp-content/plugins/ai-chatbot-plugin/ai-chatbot-style.css';
    return $excluded_files;
}
register_activation_hook(__FILE__, 'ai_chatbot_create_emotion_logs_table');
register_activation_hook(__FILE__, 'ai_chatbot_create_conversations_table');

session_start();

if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = array();
}

// For logged-in users
add_action('wp_ajax_start_chat_session', 'handle_start_chat_session');

// For not logged-in users
add_action('wp_ajax_nopriv_start_chat_session', 'handle_start_chat_session');

add_action('admin_enqueue_scripts', 'myplugin_enqueue_font_awesome');

add_action('admin_enqueue_scripts', 'myplugin_enqueue_admin_dark_mode_style', 100);
add_action('admin_enqueue_scripts', 'ai_chatbot_enqueue_admin_styles');
add_action('admin_enqueue_scripts', 'myplugin_enqueue_bootstrap');
add_action('admin_enqueue_scripts', 'myplugin_enqueue_google_fonts');

// AJAX handler to fetch conversations
add_action('wp_ajax_fetch_conversations', 'fetch_conversations');
add_action('wp_ajax_nopriv_fetch_conversations', 'fetch_conversations');
add_action('admin_enqueue_scripts', 'display_emotions_chart');

// Hook the ai_chatbot_enqueue_scripts function to the 'wp_enqueue_scripts' action
add_action('wp_enqueue_scripts', 'ai_chatbot_enqueue_scripts');

add_action('wp_ajax_ai_chatbot_handle_request', 'ai_chatbot_handle_request');
add_action('wp_ajax_nopriv_ai_chatbot_handle_request', 'ai_chatbot_handle_request');

// Hook the ai_chatbot_enqueue_scripts function to the 'wp_enqueue_scripts' action
add_action('wp_enqueue_scripts', 'ai_chatbot_enqueue_scripts');

add_action('wp_ajax_ai_chatbot_handle_request', 'ai_chatbot_handle_request');
add_action('wp_ajax_nopriv_ai_chatbot_handle_request', 'ai_chatbot_handle_request');
add_action('admin_menu', 'ai_chatbot_add_admin_menu');

// Hook into the_content filter
add_filter('the_content', 'automatic_integration_callback');

add_action('admin_init', 'ai_chatbot_settings_init');


add_filter('rocket_exclude_js', 'exclude_files_from_wp_rocket');
add_filter('rocket_exclude_css', 'exclude_files_from_wp_rocket');

?>