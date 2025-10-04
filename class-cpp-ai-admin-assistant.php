<?php
/**
 * AI Admin Assistant with OnlyMatt Avatar Integration.
 *
 * Provides the admin page, AJAX handlers and contextual intelligence for the
 * Content Protect Pro control panel.
 *
 * @package ContentProtectPro
 * @since   3.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/includes/wp-stubs.php';

class CPP_AI_Admin_Assistant
{
    private const GATEWAY_URL = 'https://api.onlymatt.ca';
    private const ADMIN_SESSION_PREFIX = 'cpp_admin_';
    private const RATE_LIMIT = 50;
    private const RATE_WINDOW = 3600;
    private const MENU_SLUG = 'ai-onlymatt-assistant';
    private const SETTINGS_SLUG = 'ai-onlymatt-assistant-settings';

    /**
     * Register WordPress hooks.
     */
    public static function init(): void
    {
        add_action('admin_menu', [__CLASS__, 'register_admin_page']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);

        add_action('wp_ajax_cpp_admin_chat', [__CLASS__, 'handle_admin_chat']);
        add_action('wp_ajax_cpp_admin_clear_history', [__CLASS__, 'handle_clear_history']);
        add_action('wp_ajax_cpp_admin_get_context', [__CLASS__, 'handle_get_context']);
    }

    /**
     * Register the AI assistant top-level menu and subpages.
     */
    public static function register_admin_page(): void
    {
        add_menu_page(
            __('AI Assistant', 'ai-onlymatt'),
            __('AI Assistant', 'ai-onlymatt'),
            'manage_options',
            self::MENU_SLUG,
            [__CLASS__, 'render_admin_page'],
            'dashicons-robot',
            56
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('AI Assistant Settings', 'ai-onlymatt'),
            __('Settings', 'ai-onlymatt'),
            'manage_options',
            self::SETTINGS_SLUG,
            [__CLASS__, 'render_settings_page']
        );
    }

    /**
     * Load scripts and styles required for the assistant interface.
     */
    public static function enqueue_admin_assets(string $hook): void
    {
        if (strpos($hook, self::MENU_SLUG) === false) {
            return;
        }

        $module_url = plugin_dir_url(__FILE__);

        wp_enqueue_script(
            'onlymatt-avatar',
            $module_url . 'generate_matt_audio/avatar.js',
            ['jquery'],
            AI_ONLYMATT_ASSISTANT_VERSION,
            true
        );

        wp_enqueue_style(
            'cpp-ai-assistant',
            $module_url . 'admin/css/cpp-ai-assistant.css',
            [],
            AI_ONLYMATT_ASSISTANT_VERSION
        );

        wp_enqueue_script(
            'cpp-ai-assistant',
            $module_url . 'admin/js/cpp-ai-assistant.js',
            ['jquery', 'onlymatt-avatar'],
            AI_ONLYMATT_ASSISTANT_VERSION,
            true
        );

        $current_user = wp_get_current_user();

        wp_localize_script('cpp-ai-assistant', 'cppAiVars', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cpp_admin_ai_chat'),
            'user_id' => get_current_user_id(),
            'user_name' => $current_user->display_name,
            'avatar_clips_url' => $module_url . 'generate_matt_audio/clips.json',
            'avatar_base_url' => $module_url . 'generate_matt_audio/',
            'strings' => [
                'sending' => __('Sending...', 'content-protect-pro'),
                'thinking' => __('Matt is thinking...', 'content-protect-pro'),
                'error' => __('Error communicating with AI', 'content-protect-pro'),
                'rate_limit' => __('Too many requests. Please wait.', 'content-protect-pro'),
                'cleared' => __('Chat history cleared', 'content-protect-pro'),
            ],
        ]);
    }

    /**
     * Render the assistant admin page.
     */
    public static function render_admin_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'content-protect-pro'));
        }

        $context = self::build_admin_context();
        $current_user = wp_get_current_user();
        $api_key = class_exists('CPP_Settings_AI') ? CPP_Settings_AI::get_api_key() : null;
        $assistant_enabled = (bool) get_option('cpp_ai_assistant_enabled', true);
        $cpp_ai_module_url = plugin_dir_url(__FILE__);

        $cpp_ai_context = $context;
        $cpp_ai_current_user = $current_user;
        $cpp_ai_assistant_enabled = $assistant_enabled;
        $cpp_ai_api_key_configured = !empty($api_key);

        include plugin_dir_path(__FILE__) . 'admin/partials/cpp-admin-ai-assistant-display.php';
    }

    /**
     * Render the assistant settings page.
     */
    public static function render_settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'content-protect-pro'));
        }

        $cpp_ai_module_url = plugin_dir_url(__FILE__);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('AI Assistant Settings', 'ai-onlymatt') . '</h1>';
        echo '<form method="post" action="options.php">';

        if (class_exists('CPP_Settings_AI')) {
            settings_fields(CPP_Settings_AI::get_option_group());
        }

        include plugin_dir_path(__FILE__) . 'admin/partials/cpp-settings-ai-integration.php';

        submit_button();
        echo '</form>';
        echo '</div>';
    }

    /**
     * Handle chat requests from the assistant window.
     */
    public static function handle_admin_chat(): void
    {
        if (!check_ajax_referer('cpp_admin_ai_chat', 'nonce', false)) {
            wp_send_json_error([
                'message' => __('Security check failed', 'content-protect-pro'),
            ], 403);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Unauthorized access', 'content-protect-pro'),
            ], 403);
        }

        $user_id = get_current_user_id();

        if (!self::check_rate_limit($user_id)) {
            wp_send_json_error([
                'message' => __('Rate limit exceeded. Please wait before sending more messages.', 'content-protect-pro'),
            ], 429);
        }

        $raw_message = $_POST['message'] ?? '';
        $message = sanitize_textarea_field(function_exists('wp_unslash') ? wp_unslash($raw_message) : $raw_message);

        if ($message === '') {
            wp_send_json_error([
                'message' => __('Message cannot be empty', 'content-protect-pro'),
            ], 400);
        }

        $context = self::build_admin_context();
        $response = self::send_to_gateway($message, $context, $user_id);

        if (!$response['success']) {
            wp_send_json_error([
                'message' => $response['error'],
            ], 500);
        }

        self::log_conversation($user_id, $message, $response['reply']);

        wp_send_json_success([
            'reply' => $response['reply'],
            'avatar_clip' => self::get_avatar_clip_for_message($response['reply']),
            'metadata' => [
                'timestamp' => current_time('mysql'),
                'model' => $response['model'] ?? 'onlymatt',
                'tokens' => $response['tokens'] ?? null,
            ],
        ]);
    }

    /**
     * Clear the chat history for the current administrator.
     */
    public static function handle_clear_history(): void
    {
        check_ajax_referer('cpp_admin_ai_chat', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'content-protect-pro')], 403);
        }

        $user_id = get_current_user_id();
        $session_id = self::ADMIN_SESSION_PREFIX . $user_id;
        $api_key = class_exists('CPP_Settings_AI') ? CPP_Settings_AI::get_api_key() : null;

        if (!empty($api_key)) {
            wp_remote_post(self::GATEWAY_URL . '/history', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-OM-KEY' => $api_key,
                ],
                'body' => wp_json_encode([
                    'session' => $session_id,
                    'action' => 'clear',
                ]),
                'timeout' => 20,
            ]);
        }

        wp_send_json_success(['message' => __('Chat history cleared', 'content-protect-pro')]);
    }

    /**
     * Return the diagnostic context payload.
     */
    public static function handle_get_context(): void
    {
        check_ajax_referer('cpp_admin_ai_chat', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'content-protect-pro')], 403);
        }

        wp_send_json_success(self::build_admin_context());
    }

    /**
     * Assemble the AI context array.
     */
    private static function build_admin_context(): array
    {
        global $wpdb;

        $current_user = wp_get_current_user();
        $plugin_dir = plugin_dir_path(__FILE__);

        $stats = [
            'active_codes' => 0,
            'total_codes' => 0,
            'protected_videos' => 0,
            'active_sessions' => 0,
            'total_redemptions' => 0,
        ];

        $recent_errors = [];
        $recent_codes = [];
        $protected_videos = [];

        $tables = null;

        if (isset($wpdb) && is_object($wpdb)) {
            $tables = [
                'giftcodes' => $wpdb->prefix . 'cpp_giftcodes',
                'sessions' => $wpdb->prefix . 'cpp_sessions',
                'protected_videos' => $wpdb->prefix . 'cpp_protected_videos',
                'analytics' => $wpdb->prefix . 'cpp_analytics',
            ];

            foreach ($tables as $key => $table_name) {
                if (!self::table_exists($wpdb, $table_name)) {
                    $tables[$key] = null;
                }
            }

            if ($tables['giftcodes']) {
                $stats['active_codes'] = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$tables['giftcodes']}
                     WHERE status IN (%s, %s)
                     AND (expires_at IS NULL OR expires_at > NOW())",
                    'unused',
                    'redeemed'
                ));

                $stats['total_codes'] = (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM {$tables['giftcodes']}"
                );

                $stats['total_redemptions'] = (int) $wpdb->get_var(
                    "SELECT COALESCE(SUM(redemption_count), 0) FROM {$tables['giftcodes']}"
                );

                $recent_codes = $wpdb->get_results(
                    "SELECT code, duration_minutes, status, created_at, redemption_count
                     FROM {$tables['giftcodes']}
                     ORDER BY created_at DESC
                     LIMIT 5",
                    ARRAY_A
                ) ?: [];
            }

            if ($tables['protected_videos']) {
                $stats['protected_videos'] = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$tables['protected_videos']}
                     WHERE status = %s",
                    'active'
                ));

                $protected_videos = $wpdb->get_results(
                    "SELECT video_id, presto_player_id, required_minutes, integration_type, status
                     FROM {$tables['protected_videos']}
                     ORDER BY created_at DESC
                     LIMIT 5",
                    ARRAY_A
                ) ?: [];
            }

            if ($tables['sessions']) {
                $stats['active_sessions'] = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$tables['sessions']}
                     WHERE status = %s AND expires_at > NOW()",
                    'active'
                ));
            }

            if ($tables['analytics']) {
                $recent_errors = $wpdb->get_results(
                    "SELECT event_type, metadata, created_at
                     FROM {$tables['analytics']}
                     WHERE (event_type LIKE '%error%'
                        OR event_type = 'session_ip_mismatch'
                        OR event_type = 'validation_failed')
                     ORDER BY created_at DESC
                     LIMIT 5",
                    ARRAY_A
                ) ?: [];
            }
        }

        $integrations = [
            'presto_player' => class_exists('CPP_Presto_Integration'),
            'analytics' => class_exists('CPP_Analytics'),
            'onlymatt_gateway' => !empty(get_option('cpp_onlymatt_api_key')),
        ];

        $file_structure = self::scan_plugin_files($plugin_dir);

        return [
            'role' => 'admin',
            'user' => [
                'id' => $current_user->ID,
                'name' => $current_user->display_name,
                'email' => $current_user->user_email,
            ],
            'site' => [
                'url' => home_url(),
                'name' => get_bloginfo('name'),
                'wp_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION,
            ],
            'plugin' => [
                'version' => defined('CPP_VERSION') ? CPP_VERSION : (defined('AI_ONLYMATT_ASSISTANT_VERSION') ? AI_ONLYMATT_ASSISTANT_VERSION : '0.2.0'),
                'path' => $plugin_dir,
                'active_integrations' => $integrations,
                'file_structure' => $file_structure,
                'stats' => $stats,
            ],
            'database' => [
                'tables' => $tables,
                'prefix' => isset($wpdb) ? $wpdb->prefix : 'wp_',
            ],
            'recent_activity' => [
                'errors' => array_map(static function ($error) {
                    $metadata = json_decode($error['metadata'] ?? '{}', true);
                    $created_at = isset($error['created_at']) ? strtotime($error['created_at']) : time();
                    $diff_string = function_exists('human_time_diff')
                        ? human_time_diff($created_at, current_time('timestamp')) . ' ' . __('ago', 'content-protect-pro')
                        : '';

                    return [
                        'time' => $diff_string,
                        'type' => $error['event_type'] ?? 'unknown',
                        'details' => $metadata,
                    ];
                }, $recent_errors),
                'recent_codes' => $recent_codes,
                'protected_videos' => $protected_videos,
            ],
        ];
    }

    /**
     * Scan plugin directories for reference.
     */
    private static function scan_plugin_files(string $dir): array
    {
        $structure = [
            'includes' => [],
            'admin' => [],
            'public' => [],
        ];

        $includes_dir = $dir . 'includes';
        if (is_dir($includes_dir)) {
            foreach (scandir($includes_dir) as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                    $structure['includes'][] = $file;
                }
            }
        }

        $admin_partials = $dir . 'admin/partials';
        if (is_dir($admin_partials)) {
            foreach (scandir($admin_partials) as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                    $structure['admin'][] = 'partials/' . $file;
                }
            }
        }

        $public_dir = $dir . 'public';
        if (is_dir($public_dir)) {
            foreach (scandir($public_dir) as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                    $structure['public'][] = $file;
                }
            }
        }

        return $structure;
    }

    private static function table_exists($wpdb, string $table_name): bool
    {
        if (!is_object($wpdb) || empty($table_name)) {
            return false;
        }

        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));

        return $found === $table_name;
    }

    /**
     * Send the admin request to the OnlyMatt gateway.
     */
    private static function send_to_gateway(string $message, array $context, int $user_id): array
    {
        if (!class_exists('CPP_Settings_AI')) {
            return [
                'success' => false,
                'error' => __('AI settings unavailable.', 'content-protect-pro'),
            ];
        }

        $api_key = CPP_Settings_AI::get_api_key();

        if (empty($api_key)) {
            return [
                'success' => false,
                'error' => __('OnlyMatt Gateway API key not configured.', 'content-protect-pro'),
            ];
        }

        if (!function_exists('wp_remote_post')) {
            return [
                'success' => false,
                'error' => __('WordPress HTTP API unavailable', 'content-protect-pro'),
            ];
        }

        $payload = [
            'session' => self::ADMIN_SESSION_PREFIX . $user_id,
            'provider' => 'ollama',
            'model' => 'onlymatt',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => self::build_system_prompt($context),
                ],
                [
                    'role' => 'user',
                    'content' => $message,
                ],
            ],
            'keep' => 30,
        ];

        $response = wp_remote_post(self::GATEWAY_URL . '/chat', [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-OM-KEY' => $api_key,
            ],
            'body' => wp_json_encode($payload),
            'timeout' => 45,
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => sprintf(__('Gateway error: %s', 'content-protect-pro'), $response->get_error_message()),
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return [
                'success' => false,
                'error' => sprintf(__('Gateway returned error code: %d', 'content-protect-pro'), $status_code),
            ];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['reply'])) {
            return [
                'success' => false,
                'error' => __('Invalid response from gateway', 'content-protect-pro'),
            ];
        }

        return [
            'success' => true,
            'reply' => $data['reply'],
            'model' => $data['model'] ?? 'onlymatt',
            'tokens' => $data['tokens_used'] ?? null,
        ];
    }

    /**
     * Build the system prompt injected into the gateway conversation.
     */
    private static function build_system_prompt(array $context): string
    {
        $instructions_file = plugin_dir_path(__FILE__) . '.github/copilot-instructions.md';
        $copilot_instructions = file_exists($instructions_file)
            ? file_get_contents($instructions_file)
            : '';

        $stats = $context['plugin']['stats'];

        $prompt = "# Content Protect Pro - AI Admin Assistant\n\n";
        $prompt .= "## YOUR ROLE\n";
        $prompt .= "You are Matt's AI assistant helping manage Content Protect Pro.\n\n";
        $prompt .= "**ADMIN MODE**: Speaking to {$context['user']['name']} with FULL SYSTEM ACCESS.\n\n";

        $prompt .= "## LIVE SYSTEM STATE\n\n";
        $prompt .= "### Statistics\n";
        $prompt .= "- Active Codes: {$stats['active_codes']} / {$stats['total_codes']}\n";
        $prompt .= "- Protected Videos: {$stats['protected_videos']}\n";
        $prompt .= "- Active Sessions: {$stats['active_sessions']}\n";
        $prompt .= "- Total Redemptions: {$stats['total_redemptions']}\n\n";

        $prompt .= "### Integrations\n";
        foreach ($context['plugin']['active_integrations'] as $name => $active) {
            $icon = $active ? '✅' : '❌';
            $label = ucfirst(str_replace('_', ' ', $name));
            $prompt .= "- {$icon} {$label}\n";
        }
        $prompt .= "\n";

        $prompt .= "### Files (includes/)\n";
        foreach ($context['plugin']['file_structure']['includes'] as $file) {
            $prompt .= "- `{$file}`\n";
        }
        $prompt .= "\n";

        if (!empty($context['recent_activity']['errors'])) {
            $prompt .= "### ⚠️ RECENT ERRORS\n";
            foreach ($context['recent_activity']['errors'] as $error) {
                $prompt .= "- [{$error['time']}] {$error['type']}\n";
            }
            $prompt .= "\n";
        }

        $prompt .= "## YOUR CAPABILITIES\n";
        $prompt .= "- Write PHP code with proper WordPress standards\n";
    $prompt .= '- Generate SQL with $wpdb->prepare()' . "\n";
        $prompt .= "- Debug Presto Player integration\n";
        $prompt .= "- Analyze analytics data\n";
        $prompt .= "- Provide file paths and security checks\n\n";

        if (!empty($copilot_instructions)) {
            $prompt .= "## FULL ARCHITECTURE\n\n";
            $prompt .= $copilot_instructions . "\n\n";
        }

        $prompt .= "Now help with the admin's question using this context.\n";

        return $prompt;
    }

    /**
     * Choose an avatar clip for the assistant response.
     */
    private static function get_avatar_clip_for_message(string $message): string
    {
        $message_lower = strtolower($message);

        if (strpos($message_lower, 'error') !== false || strpos($message_lower, 'problem') !== false) {
            return 'icitupeuxdem_uetuveux.mp4';
        }

        if (strpos($message_lower, 'success') !== false || strpos($message_lower, 'works') !== false) {
            return 'fuckyeababy.mp4';
        }

        if (strpos($message_lower, 'code') !== false || strpos($message_lower, 'query') !== false) {
            return 'tachetetonco_piscestca.mp4';
        }

        return 'yocestonlymatt.mp4';
    }

    /**
     * Simple rate limiting per admin using transients.
     */
    private static function check_rate_limit(int $user_id): bool
    {
        $transient_key = 'cpp_admin_ai_rate_' . $user_id;
        $attempts = get_transient($transient_key);

        if ($attempts === false) {
            set_transient($transient_key, 1, self::RATE_WINDOW);
            return true;
        }

        if ($attempts >= self::RATE_LIMIT) {
            return false;
        }

        set_transient($transient_key, $attempts + 1, self::RATE_WINDOW);
        return true;
    }

    /**
     * Log the conversation in the analytics module when available.
     */
    private static function log_conversation(int $user_id, string $message, string $reply): void
    {
        if (!class_exists('CPP_Analytics')) {
            return;
        }

        try {
            $analytics = new CPP_Analytics();
            $analytics->log_event(
                'admin_ai_chat',
                'admin',
                $user_id,
                [
                    'message_length' => strlen($message),
                    'reply_length' => strlen($reply),
                    'timestamp' => current_time('mysql'),
                ]
            );
        } catch (Throwable $exception) {
            error_log('CPP AI Assistant analytics logging failed: ' . $exception->getMessage());
        }
    }
}