<?php
/**
 * Main plugin class file.
 *
 * @package WordPress Plugin Template/Includes
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin class.
 */
class AiChatbot
{
    /**
     * The list of questions for the chatbot to use.
     */
    private $questions;
    private $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->initializeQuestions();
        $this->registerHooks();
    }

    /**
     * Initializes the chatbot questions.
     */
    private function initializeQuestions()
    {
        $this->questions = [
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
        ];
    }


    /**
     * Registers WordPress hooks for actions and filters.
     */
    private function registerHooks()
    {
        register_activation_hook(__FILE__, 'ai_chatbot_create_emotion_logs_table');
        register_activation_hook(__FILE__, 'ai_chatbot_create_conversations_table');
        add_action('init', [$this, 'initializeSession']);
        add_action('wp_ajax_start_chat_session', [$this, 'handle_start_chat_session']);
        add_action('wp_ajax_nopriv_start_chat_session', [$this, 'handle_start_chat_session']);

        add_action('admin_enqueue_scripts', [$this, 'enqueue_styles_and_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'ai_chatbot_enqueue_scripts']);
        add_filter('the_content', [$this, 'automatic_integration_callback']);
        add_action('admin_init', [$this, 'ai_chatbot_settings_init']);

        //add_action('admin_enqueue_scripts', [$this, 'myplugin_enqueue_font_awesome']);
        //add_action('admin_enqueue_scripts', [$this, 'myplugin_enqueue_admin_dark_mode_style'], 100);
        //add_action('admin_enqueue_scripts', [$this, 'ai_chatbot_enqueue_admin_styles']);
        //add_action('admin_enqueue_scripts', [$this, 'myplugin_enqueue_bootstrap']);
        //add_action('admin_enqueue_scripts', [$this, 'myplugin_enqueue_google_fonts']);

        add_action('wp_ajax_fetch_conversations', [$this, 'fetch_conversations']);
        add_action('wp_ajax_nopriv_fetch_conversations', [$this, 'fetch_conversations']);
        add_action('wp_ajax_ai_chatbot_handle_request', [$this, 'ai_chatbot_handle_request']);
        add_action('wp_ajax_nopriv_ai_chatbot_handle_request', [$this, 'ai_chatbot_handle_request']);

        add_action('admin_menu', [$this, 'ai_chatbot_add_admin_menu']);
        add_filter('rocket_exclude_js', [$this, 'exclude_files_from_wp_rocket']);
        add_filter('rocket_exclude_css', [$this, 'exclude_files_from_wp_rocket']);
    }

    // Method to initialize session
    public function initializeSession()
    {
        if (!session_id()) {
            session_start();
        }
        if (!isset($_SESSION['chat_history'])) {
            $_SESSION['chat_history'] = [];
        }
        $this->sessionHistory = &$_SESSION['chat_history'];
    }

    public function handle_start_chat_session() {
        wp_send_json_success(['sessionId' => session_id()]);
    }

    public function enqueue_styles_and_scripts() {
        wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css');
        wp_enqueue_style('myplugin-admin-dark-mode', plugins_url('admin-dark-mode.css', __FILE__));
        wp_enqueue_style('ai-chatbot-css', plugins_url('/ai-chatbot-style.css', __FILE__));
        wp_enqueue_style('myplugin-dm-sans-font', 'https://fonts.googleapis.com/css2?family=DM+Sans&display=swap');
        wp_enqueue_style('bootstrap-css', 'https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css');
        wp_enqueue_script('bootstrap-js', 'https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.bundle.min.js', ['jquery'], null, true);
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);
        wp_enqueue_script('emotions-chart-js', plugins_url('/emotions-chart.js', __FILE__), ['chart-js'], '1.0.0', true);

        // Assume get_last_7_days_emotion_data() fetches the required data
        //$emotion_data = $this->get_last_7_days_emotion_data(); // Make sure this function exists and fetches data correctly
        //wp_localize_script('emotions-chart-js', 'aiChatbotEmotionData', $emotion_data);
    }

    public function ai_chatbot_create_conversations_table() {
        $table_name = $this->wpdb->prefix . 'sessions';
        $charset_collate = $this->wpdb->get_charset_collate();
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

    public function ai_chatbot_create_emotion_logs_table() {
        $charset_collate = $this->wpdb->get_charset_collate();
        $table_name = $this->wpdb->prefix . 'messages';
        $sessions_table = $this->wpdb->prefix . 'sessions';
    
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
    }
}