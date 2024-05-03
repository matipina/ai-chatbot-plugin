<?php
// Ensure no direct access
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class AssetManager
 * Manages the enqueuing of scripts and styles for the plugin.
 */
class AssetManager
{
    public function register()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueuePublicAssets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets'], 100);
    }

    public function enqueuePublicAssets()
    {
        $this->enqueueBootstrap();
        $this->enqueueGoogleFonts();
        $this->enqueueChatbotScripts();
        $this->enqueueAdminChatbotStyles();
        $this->enqueueAdminDarkModeStyle();
        $this->enqueueFontAwesome();
    }

    public function enqueueAdminAssets()
    {
        $this->enqueueFontAwesome();
        $this->enqueueAdminChatbotStyles();
        $this->enqueueAdminDarkModeStyle();
    }

    private function enqueueFontAwesome()
    {
        wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css');
    }

    private function enqueueAdminDarkModeStyle()
    {
        wp_enqueue_style('myplugin-admin-dark-mode', plugins_url('../assets/css/admin-dark-mode.css', __FILE__));
    }

    private function enqueueAdminChatbotStyles()
    {
        wp_enqueue_style('ai-chatbot-css', plugins_url('../assets/css/ai-chatbot-style.css', __FILE__));
    }

    private function enqueueBootstrap()
    {
        wp_enqueue_style('bootstrap-css', 'https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css');
        wp_enqueue_script('bootstrap-js', 'https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.bundle.min.js', ['jquery'], null, true);
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);
    }

    private function enqueueGoogleFonts()
    {
        wp_enqueue_style('myplugin-dm-sans-font', 'https://fonts.googleapis.com/css2?family=DM+Sans&display=swap');
    }

    private function enqueueChatbotScripts()
    {
        wp_enqueue_script('jquery');
        wp_enqueue_script('ai-chatbot-js', plugins_url('../assets/js/ai-chatbot.js', __FILE__), ['jquery'], '1.0.0', true);

        $chatbot_settings = array(
            'ajaxurl' => admin_url('admin-ajax.php'), // AJAX URL for WordPress
            'image_url' => get_option('ai_chatbot_image_url'),
            'primary_color' => get_option('ai_chatbot_primary_color', '#007bff'), // Default blue color
            'custom_bot_down_message' => get_option('custom_bot_down_message'), // Custom bot down message
            'defaultMessage' => get_option('ai_chatbot_default_message', 'Hello! I\'m here to help you. What can I do for you today?'),
        );
        wp_localize_script('ai-chatbot-js', 'aiChatbotSettings', $chatbot_settings);
        wp_add_inline_script('ai-chatbot-js', '
             document.addEventListener("DOMContentLoaded", function() {
             });
         ', 'after');
    }
}
