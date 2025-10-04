<?php
/**
 * Minimal WordPress stubs for non-WordPress environments.
 *
 * These definitions are intentionally lightweight and only aim to keep
 * static analysis tools from reporting missing symbols when this plugin code
 * is loaded outside of WordPress. In a real WordPress runtime the core
 * functions are already defined, so every definition here is wrapped inside
 * an existence check to avoid redeclarations.
 */

if (defined('CPP_AI_WP_STUBS_LOADED')) {
    return;
}

define('CPP_AI_WP_STUBS_LOADED', true);

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', __DIR__);
}

if (!function_exists('is_plugin_active')) {
    function is_plugin_active($plugin) {
        return false;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr__')) {
    function esc_attr__($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url) {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
}

if (!function_exists('esc_js')) {
    function esc_js($text) {
        return addslashes((string) $text);
    }
}

if (!function_exists('wp_nonce_field')) {
    function wp_nonce_field($action = -1, $name = '_wpnonce', $referer = true, $echo = true) {
        $field = '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr(wp_create_nonce($action)) . '" />';
        if ($echo) {
            echo $field;
        }
        return $field;
    }
}

if (!function_exists('settings_fields')) {
    function settings_fields($option_group) {
        $fields = '<input type="hidden" name="option_page" value="' . esc_attr($option_group) . '" />';
        echo $fields;
        return $fields;
    }
}

if (!function_exists('submit_button')) {
    function submit_button($text = null, $type = 'primary', $name = 'submit', $wrap = true, $other_attributes = null) {
        $text = $text ?? __('Save Changes');
        $button = '<p class="submit"><input type="submit" name="' . esc_attr($name) . '" id="submit" class="button button-' . esc_attr($type) . '" value="' . esc_attr($text) . '" /></p>';
        if ($wrap) {
            echo $button;
        }
        return $button;
    }
}

if (!function_exists('checked')) {
    function checked($checked, $current = true, $echo = true) {
        $result = $checked == $current ? 'checked' : '';
        if ($echo) {
            echo $result;
        }
        return $result;
    }
}

if (!function_exists('number_format_i18n')) {
    function number_format_i18n($number, $decimals = 0) {
        return number_format((float) $number, $decimals);
    }
}

if (!function_exists('get_avatar')) {
    function get_avatar($id, $size = 96, $default = '', $alt = '', $args = []) {
        $class = isset($args['class']) ? $args['class'] : 'avatar';
        return '<img src="https://via.placeholder.com/' . (int) $size . '" class="' . esc_attr($class) . '" alt="' . esc_attr($alt) . '" />';
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        private $message;

        public function __construct($code = '', $message = '', $data = null) {
            $this->message = (string) $message;
        }

        public function get_error_message(): string {
            return $this->message;
        }
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1): void {}
}

if (!function_exists('add_submenu_page')) {
    function add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback, $position = null): void {}
}

if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script($handle, $src = '', $deps = [], $ver = false, $in_footer = false): void {}
}

if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style($handle, $src = '', $deps = [], $ver = false, $media = 'all'): void {}
}

if (!function_exists('wp_localize_script')) {
    function wp_localize_script($handle, $object_name, $l10n): void {}
}

if (!function_exists('plugins_url')) {
    function plugins_url($path = '', $plugin = '') {
        return $path;
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) {
        return '';
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '') {
        return $path;
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1) {
        return md5($action . microtime(true));
    }
}

if (!function_exists('wp_die')) {
    function wp_die($message = ''): void {
        throw new RuntimeException((string) $message);
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability): bool {
        return true;
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id(): int {
        return 1;
    }
}

if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user() {
        return (object) [
            'ID' => 1,
            'display_name' => 'Admin',
            'user_email' => 'admin@example.com',
        ];
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return $GLOBALS['cpp_ai_stub_options'][$option] ?? $default;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration) {
        $GLOBALS['cpp_ai_stub_transients'][$transient] = $value;
        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient($transient) {
        return $GLOBALS['cpp_ai_stub_transients'][$transient] ?? false;
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str) {
        return trim(filter_var((string) $str, FILTER_UNSAFE_RAW));
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(filter_var((string) $str, FILTER_UNSAFE_RAW));
    }
}

if (!function_exists('current_time')) {
    function current_time($type, $gmt = 0) {
        return $type === 'timestamp' ? time() : gmdate('Y-m-d H:i:s');
    }
}

if (!function_exists('home_url')) {
    function home_url($path = '') {
        return 'https://example.com/' . ltrim($path, '/');
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show = '', $filter = 'raw') {
        $map = [
            'name' => 'Example Site',
            'version' => '6.0',
        ];

        return $map[$show] ?? '';
    }
}

if (!function_exists('human_time_diff')) {
    function human_time_diff($from, $to = null) {
        $to = $to ?? time();
        $diff = abs($to - $from);
        return $diff . ' seconds';
    }
}

if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args = []) {
        return new WP_Error('http_unavailable', 'WordPress HTTP API unavailable');
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0, $depth = 512) {
        return json_encode($data, $options | JSON_UNESCAPED_UNICODE, $depth);
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing): bool {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        return $response instanceof WP_Error ? 0 : 200;
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        return $response instanceof WP_Error ? '' : (string) ($response['body'] ?? '');
    }
}

if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer($action, $query_arg = false, $die = true) {
        return true;
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null, $status_code = null) {
        throw new RuntimeException('wp_send_json_error called outside WordPress.');
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null) {
        throw new RuntimeException('wp_send_json_success called outside WordPress.');
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value) {
        return is_array($value) ? array_map('wp_unslash', $value) : stripslashes((string) $value);
    }
}
