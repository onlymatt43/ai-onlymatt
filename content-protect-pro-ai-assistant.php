<?php
/**
 * Plugin Name: OnlyMatt AI Assistant
 * Plugin URI:  https://onlymatt.ca/
 * Description: Standalone WordPress admin coach powered by the OnlyMatt gateway. Provides troubleshooting guidance, chat history, and setup tools.
 * Version:     0.2.0
 * Author:      OnlyMatt
 * License:     GPL-2.0-or-later
 * Text Domain: ai-onlymatt
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('AI_ONLYMATT_ASSISTANT_VERSION')) {
    define('AI_ONLYMATT_ASSISTANT_VERSION', '0.2.0');
}

if (!defined('CPP_VERSION')) {
    add_action('admin_notices', static function () {
        if (!current_user_can('manage_options')) {
            return;
        }

        echo '<div class="notice notice-info"><p>' . esc_html__(
            'Content Protect Pro is not active. The OnlyMatt AI Assistant will operate in standalone mode with limited telemetry.',
            'ai-onlymatt'
        ) . '</p></div>';
    });
}

if (!function_exists('add_action')) {
    require_once __DIR__ . '/includes/wp-stubs.php';
}
require_once __DIR__ . '/includes/class-cpp-settings-ai.php';
require_once __DIR__ . '/class-cpp-ai-admin-assistant.php';

if (class_exists('CPP_Settings_AI')) {
    CPP_Settings_AI::init();
}

if (class_exists('CPP_AI_Admin_Assistant')) {
    CPP_AI_Admin_Assistant::init();
}
