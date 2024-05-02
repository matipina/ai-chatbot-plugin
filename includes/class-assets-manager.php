<?php
// Ensure no direct access
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class AssetManager
 * Manages the enqueuing of scripts and styles for the plugin.
 */
class AssetManager {
    public function register() {
        add_action('wp_enqueue_scripts', [$this, 'enqueuePublicAssets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
    }

    public function enqueuePublicAssets() {
        $this->enqueueFontAwesome();
        $this->enqueueBootstrap();
        $this->enqueueGoogleFonts();
        $this->enqueueChatbotScripts();
    }

    public function enqueueAdminAssets() {
        $this->enqueueAdminDarkModeStyle();
        $this->enqueueAdminChatbotStyles();
    }

    private function enqueueFontAwesome() {
        wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css');
    }

    private function enqueueAdminDarkModeStyle() {
        wp_enqueue_style('myplugin-admin-dark-mode', plugins_url('admin-dark-mode.css', __FILE__));
    }

    private function enqueueAdminChatbotStyles() {
        wp_enqueue_style('ai-chatbot-css', plugins_url('/ai-chatbot-style.css', __FILE__));
    }

    private function enqueueBootstrap() {
        wp_enqueue_style('bootstrap-css', 'https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css');
        wp_enqueue_script('bootstrap-js', 'https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.bundle.min.js', ['jquery'], null, true);
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);
    }

    private function enqueueGoogleFonts() {
        wp_enqueue_style('myplugin-dm-sans-font', 'https://fonts.googleapis.com/css2?family=DM+Sans&display=swap');
    }

    private function enqueueChatbotScripts() {
        wp_enqueue_script('ai-chatbot-js', plugins_url('/ai-chatbot.js', __FILE__), ['jquery'], '1.0.0', true);
        wp_enqueue_style('ai-chatbot-css', plugins_url('/ai-chatbot-style.css', __FILE__));
    }
}

