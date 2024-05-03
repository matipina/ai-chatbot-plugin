<?php
// Ensure no direct access
if (!defined('ABSPATH')) {
    exit;
}

class SettingsManager
{
    public function __construct()
    {
        add_action('admin_init', [$this, 'initSettings']);
    }

    public function initSettings()
    {
        $this->registerSettings();
        $this->addSettingsSection();
        $this->addSettingsFields();
    }

    private function registerSettings()
    {
        register_setting('ai_chatbot_plugin_settings', 'ai_chatbot_enabled');
        register_setting('ai_chatbot_plugin_settings', 'ai_chatbot_gemini_api_key');
        register_setting('ai_chatbot_plugin_settings', 'ai_chatbot_image_url');
        register_setting('ai_chatbot_plugin_settings', 'ai_chatbot_primary_color');
        register_setting('ai_chatbot_plugin_settings', 'chatbot_name');
        register_setting('ai_chatbot_plugin_settings', 'custom_bot_down_message');
        register_setting('ai_chatbot_plugin_settings', 'ai_chatbot_default_message');
    }

    private function addSettingsSection()
    {
        add_settings_section(
            'ai_chatbot_plugin_settings_section',
            __('Customize your AI ChatBot', 'wordpress'),
            [$this, 'settingsSectionCallback'],
            'ai_chatbot_plugin_settings'
        );
    }

    private function addSettingsFields()
    {
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
    }

    public function settingsSectionCallback()
    {
        echo __('Answer the following questions to customize your AI ChatBot.', 'wordpress');
    }

    public function renderEnabledField()
    {
        $options = get_option('ai_chatbot_enabled');
        echo "<input type='checkbox' name='ai_chatbot_enabled' " . checked(1, $options, false) . " value='1'>";
    }
}
