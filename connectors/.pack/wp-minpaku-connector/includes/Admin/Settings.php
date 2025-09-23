<?php
/**
 * Admin Settings Class
 *
 * @package WP_Minpaku_Connector
 */

namespace MinpakuConnector\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class MPC_Admin_Settings {

    public static function init() {
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('wp_ajax_mpc_test_connection', array(__CLASS__, 'ajax_test_connection'));
        add_action('wp_ajax_mpc_diagnostic_step_a', array(__CLASS__, 'ajax_diagnostic_step_a'));
        add_action('wp_ajax_mpc_diagnostic_step_b', array(__CLASS__, 'ajax_diagnostic_step_b'));
        add_action('wp_ajax_mpc_diagnostic_step_c', array(__CLASS__, 'ajax_diagnostic_step_c'));
    }

    /**
     * Register settings
     */
    public static function register_settings() {
        register_setting(
            'wp_minpaku_connector_settings',
            'wp_minpaku_connector_settings',
            array(__CLASS__, 'sanitize_settings')
        );

        add_settings_section(
            'mpc_connection_section',
            __('Portal Connection', 'wp-minpaku-connector'),
            array(__CLASS__, 'connection_section_callback'),
            'wp-minpaku-connector'
        );

        add_settings_field(
            'portal_url',
            __('Portal Base URL', 'wp-minpaku-connector'),
            array(__CLASS__, 'portal_url_callback'),
            'wp-minpaku-connector',
            'mpc_connection_section'
        );

        add_settings_field(
            'site_id',
            __('Site ID', 'wp-minpaku-connector'),
            array(__CLASS__, 'site_id_callback'),
            'wp-minpaku-connector',
            'mpc_connection_section'
        );

        add_settings_field(
            'api_key',
            __('API Key', 'wp-minpaku-connector'),
            array(__CLASS__, 'api_key_callback'),
            'wp-minpaku-connector',
            'mpc_connection_section'
        );

        add_settings_field(
            'secret',
            __('Secret', 'wp-minpaku-connector'),
            array(__CLASS__, 'secret_callback'),
            'wp-minpaku-connector',
            'mpc_connection_section'
        );
    }

    /**
     * Sanitize settings
     */
    public static function sanitize_settings($input) {
        $sanitized = array();

        if (isset($input['portal_url'])) {
            list($ok, $normalized, $msg) = self::normalize_and_validate_portal_url($input['portal_url']);
            if ($ok) {
                $sanitized['portal_url'] = $normalized;
            } else {
                // Keep the original value and show error
                $sanitized['portal_url'] = esc_url_raw(trim($input['portal_url']));
                \add_settings_error(
                    'wp_minpaku_connector_settings',
                    'invalid_portal_url',
                    esc_html($msg),
                    'error'
                );
            }
        }

        if (isset($input['site_id'])) {
            $sanitized['site_id'] = sanitize_text_field(trim($input['site_id']));
        }

        if (isset($input['api_key'])) {
            $sanitized['api_key'] = sanitize_text_field(trim($input['api_key']));
        }

        if (isset($input['secret'])) {
            $sanitized['secret'] = sanitize_text_field(trim($input['secret']));
        }

        return $sanitized;
    }

    /**
     * Normalize and validate portal URL - improved version
     */
    private static function normalize_and_validate_portal_url($raw): array {
        // 1) 正規化：trim / 全角スペース除去 / esc_url_raw / スラ削除
        $s = preg_replace('/\x{3000}/u', ' ', trim((string)$raw)); // 全角→半角
        $s = esc_url_raw($s);
        if ($s === '') {
            return [false, '', \__('ポータルURLが空です。', 'wp-minpaku-connector')];
        }
        $s = untrailingslashit($s);

        // 2) WordPress 標準のURL検証 + 開発環境フォールバック
        $wp_valid = \wp_http_validate_url($s);
        if (!$wp_valid) {
            // WordPressの標準検証が失敗した場合、開発用ドメインかチェック
            $parts = \wp_parse_url($s);
            if (!$parts || !isset($parts['scheme']) || !isset($parts['host'])) {
                return [false, $s, \__('ポータルURLが無効です。http(s)のURLを指定してください。', 'wp-minpaku-connector')];
            }

            $scheme = $parts['scheme'];
            $host = $parts['host'];

            // http/httpsスキームかつ開発用ドメイン/IPv4なら許可
            $is_dev_scheme = in_array($scheme, ['http', 'https'], true);
            $is_dev_host = false;

            // IPv4 address check
            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $is_dev_host = true;
            }
            // localhost without dots
            else if (!str_contains($host, '.')) {
                $is_dev_host = ($host === 'localhost');
            }
            // Domain with development TLD
            else {
                $tld = substr(strrchr($host, '.'), 1);
                $is_dev_host = in_array($tld, ['local','test','localdomain'], true);
            }

            if (!($is_dev_scheme && $is_dev_host)) {
                return [false, $s, \__('ポータルURLが無効です。http(s)のURLを指定してください。', 'wp-minpaku-connector')];
            }
        }

        // 3) 最終チェック：スキームとホストの再確認（既に上でチェック済みだが念のため）
        $parts = \wp_parse_url($s);
        $scheme = $parts['scheme'] ?? '';
        $host = $parts['host'] ?? '';

        // デバッグログ
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[minpaku-connector] URL validation: ' . $s . ' | wp_valid: ' . ($wp_valid ? 'true' : 'false') . ' | scheme: ' . $scheme . ' | host: ' . $host);
        }

        // スキーム再確認
        if (!in_array($scheme, ['http','https'], true)) {
            return [false, $s, \__('ポータルURLのスキームは http/https にしてください。', 'wp-minpaku-connector')];
        }

        // ホスト再確認（開発用ドメインまたは通常ドメイン + ポート対応）
        $ok_host = false;

        // IP address validation (IPv4)
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ok_host = true; // Allow all IPv4 addresses
        }
        // localhost without dots
        else if ($host && !str_contains($host, '.')) {
            $ok_host = ($host === 'localhost');
        }
        // Domain with TLD
        else if ($host && str_contains($host, '.')) {
            $tld = substr(strrchr($host, '.'), 1);
            $ok_host = ($tld && (in_array($tld, ['local','test','localdomain'], true) || preg_match('/^[a-z]{2,63}$/i', (string)$tld)));
        }

        if (!$ok_host) {
            return [false, $s, \__('ポータルURLのドメインが許可されていません（.local/.test/localhost/IPv4対応）。', 'wp-minpaku-connector')];
        }

        return [true, $s, ''];
    }

    /**
     * Legacy function for backward compatibility - calls the new validation
     */
    public static function normalize_portal_url($url) {
        list($ok, $normalized, $msg) = self::normalize_and_validate_portal_url($url);

        // Debug log for legacy function calls
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[minpaku-connector] Legacy normalize_portal_url() called - URL: ' . $url . ' | Result: ' . ($ok ? $normalized : 'false') . ' | Message: ' . $msg);
        }

        return $ok ? $normalized : false;
    }

    /**
     * Check if the given host is a development domain
     */
    private static function is_development_domain($host) {
        // IPv4 addresses
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return true;
        }

        // localhost
        if ($host === 'localhost') {
            return true;
        }

        // Development TLDs
        if (strpos($host, '.') !== false) {
            $tld = substr(strrchr($host, '.'), 1);
            return in_array($tld, ['local', 'test', 'localdomain'], true);
        }

        return false;
    }

    /**
     * AJAX handler for connection test
     */
    public static function ajax_test_connection() {
        check_ajax_referer('mpc_test_connection', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'wp-minpaku-connector'));
        }

        // Log connection test attempt with URL validation
        $settings = \WP_Minpaku_Connector::get_settings();
        $portal_url = $settings['portal_url'] ?? '';
        list($url_ok, $normalized_url, $url_msg) = self::normalize_and_validate_portal_url($portal_url);

        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[minpaku-connector] Connection test initiated by user: ' . get_current_user_id());
            error_log('[minpaku-connector] Portal URL validation - Original: ' . $portal_url . ' | Valid: ' . ($url_ok ? 'true' : 'false') . ' | Message: ' . $url_msg);
        }

        // If URL validation fails, return error immediately
        if (!$url_ok) {
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('[minpaku-connector] Connection test aborted due to invalid URL: ' . $url_msg);
            }
            wp_send_json_error(array(
                'message' => $url_msg,
                'type' => 'url_validation',
                'original' => $portal_url
            ));
        }

        $api = new \MinpakuConnector\Client\MPC_Client_Api();
        $result = $api->test_connection();

        // Enhanced error handling for specific cases
        if (!$result['success']) {
            $message = $result['message'];
            $error_type = 'general';

            // Check for specific error patterns
            if (strpos($message, '401') !== false || strpos($message, 'Unauthorized') !== false) {
                $message = __('Authentication failed. Please check your API Key and Secret.', 'wp-minpaku-connector');
                $error_type = 'auth';
            } elseif (strpos($message, '403') !== false || strpos($message, 'Forbidden') !== false) {
                $message = __('Access denied. Please check that your domain is added to the allowed domains list in the portal.', 'wp-minpaku-connector');
                $error_type = 'permission';
            } elseif (strpos($message, '404') !== false || strpos($message, 'Not Found') !== false) {
                $message = __('Portal endpoint not found. Please check your Portal Base URL.', 'wp-minpaku-connector');
                $error_type = 'url';
            } elseif (strpos($message, '408') !== false || strpos($message, 'timeout') !== false) {
                $message = __('Connection timeout. Please check your portal server and network connectivity.', 'wp-minpaku-connector');
                $error_type = 'timeout';
            } elseif (strpos($message, '5') === 0 || strpos($message, 'Internal Server Error') !== false) {
                $message = __('Portal server error. Please contact your portal administrator.', 'wp-minpaku-connector');
                $error_type = 'server';
            } elseif (strpos($message, 'time') !== false && strpos($message, 'sync') !== false) {
                $message = __('Server time synchronization issue. Please check your server clock or contact your hosting provider.', 'wp-minpaku-connector');
                $error_type = 'time';
            }

            // Log detailed error for debugging
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('[minpaku-connector] Connection test failed (' . $error_type . '): ' . $result['message']);
            }

            wp_send_json_error(array(
                'message' => $message,
                'type' => $error_type,
                'original' => $result['message']
            ));
        }

        // Success case - check for time sync warnings
        $warnings = array();
        if (isset($result['data']['server_time'])) {
            $server_time = strtotime($result['data']['server_time']);
            $local_time = time();
            $time_diff = abs($server_time - $local_time);

            if ($time_diff > 300) { // More than 5 minutes difference
                $warnings[] = sprintf(
                    __('Warning: Server time difference detected (%d seconds). Consider checking time synchronization.', 'wp-minpaku-connector'),
                    $time_diff
                );
            }
        }

        // Log successful connection
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[minpaku-connector] Connection test successful to: ' . (isset($result['data']['portal_url']) ? $result['data']['portal_url'] : 'unknown'));
        }

        wp_send_json_success(array(
            'message' => $result['message'],
            'warnings' => $warnings,
            'data' => $result['data']
        ));
    }

    /**
     * AJAX handler for diagnostic Step A: URL normalization & DNS
     */
    public static function ajax_diagnostic_step_a() {
        check_ajax_referer('mpc_diagnostic', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'wp-minpaku-connector'));
        }

        $settings = \WP_Minpaku_Connector::get_settings();
        $portal_url = $settings['portal_url'] ?? '';

        // URL normalization
        list($url_ok, $normalized_url, $url_msg) = self::normalize_and_validate_portal_url($portal_url);

        $result = array(
            'original_url' => $portal_url,
            'normalized_url' => $normalized_url,
            'validation' => array(
                'valid' => $url_ok,
                'message' => $url_msg
            )
        );

        // DNS resolution attempt
        if ($url_ok) {
            $parts = wp_parse_url($normalized_url);
            $host = $parts['host'] ?? '';

            if ($host) {
                $ip = gethostbyname($host);
                $result['dns'] = array(
                    'host' => $host,
                    'resolved_ip' => $ip !== $host ? $ip : false,
                    'success' => $ip !== $host
                );
            }
        }

        if ($url_ok) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX handler for diagnostic Step B: Anonymous ping test
     */
    public static function ajax_diagnostic_step_b() {
        check_ajax_referer('mpc_diagnostic', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'wp-minpaku-connector'));
        }

        $settings = \WP_Minpaku_Connector::get_settings();
        $portal_url = $settings['portal_url'] ?? '';

        list($url_ok, $normalized_url, $url_msg) = self::normalize_and_validate_portal_url($portal_url);

        if (!$url_ok) {
            wp_send_json_error(array('message' => $url_msg));
        }

        $ping_url = trailingslashit($normalized_url) . 'wp-json/minpaku/v1/connector/ping';

        // Check if this is a development domain
        $parsed_url = wp_parse_url($ping_url);
        $host = $parsed_url['host'] ?? '';
        $is_dev_domain = self::is_development_domain($host);

        $args = array(
            'timeout' => 8,
            'redirection' => 2,
            'user-agent' => 'WPMC/1.0',
            'sslverify' => true
        );

        if ($is_dev_domain) {
            $args['reject_unsafe_urls'] = false;

            // Debug logging for development domain
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('[minpaku-connector] Development domain detected in ping test: ' . $host);
            }
        }

        $start_time = microtime(true);

        // For development domains, temporarily disable external blocking
        if ($is_dev_domain && defined('WP_HTTP_BLOCK_EXTERNAL') && WP_HTTP_BLOCK_EXTERNAL) {
            add_filter('pre_option_wp_http_block_external', '__return_false', 999);
        }

        $response = wp_remote_get($ping_url, $args);

        // Restore external blocking if it was disabled
        if ($is_dev_domain && defined('WP_HTTP_BLOCK_EXTERNAL') && WP_HTTP_BLOCK_EXTERNAL) {
            remove_filter('pre_option_wp_http_block_external', '__return_false', 999);
        }

        $request_time = round((microtime(true) - $start_time) * 1000);

        $result = array(
            'url' => $ping_url,
            'request_time_ms' => $request_time
        );

        if (is_wp_error($response)) {
            $result['success'] = false;
            $result['error'] = $response->get_error_message();
            $result['error_data'] = $response->get_error_data();
            wp_send_json_error($result);
        } else {
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            $result['success'] = ($status_code === 200);
            $result['status_code'] = $status_code;
            $result['response'] = substr($body, 0, 500); // First 500 chars
            $result['parsed_data'] = $data;

            if ($status_code === 200) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
        }
    }

    /**
     * AJAX handler for diagnostic Step C: Authenticated verify test
     */
    public static function ajax_diagnostic_step_c() {
        check_ajax_referer('mpc_diagnostic', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'wp-minpaku-connector'));
        }

        $settings = \WP_Minpaku_Connector::get_settings();
        $portal_url = $settings['portal_url'] ?? '';

        list($url_ok, $normalized_url, $url_msg) = self::normalize_and_validate_portal_url($portal_url);

        if (!$url_ok) {
            wp_send_json_error(array('message' => $url_msg));
        }

        $api = new \MinpakuConnector\Client\MPC_Client_Api();
        $detailed_result = $api->test_connection_detailed();

        $result = array(
            'url' => $detailed_result['request_url'],
            'request_time_ms' => $detailed_result['request_time_ms'],
            'http_status' => $detailed_result['http_status'],
            'response_body_preview' => $detailed_result['response_body_preview'],
            'request_headers_sent' => $detailed_result['request_headers_sent'],
            'success' => $detailed_result['success'],
            'wp_http_block_external_warning' => $detailed_result['wp_http_block_external_warning'] ?? false,
            'signature_debug' => $detailed_result['signature_debug'] ?? array()
        );

        // Handle WP_Error cases with enhanced details
        if ($detailed_result['wp_error']) {
            $result['wp_error'] = $detailed_result['wp_error'];

            // Add cURL error details if available
            if ($detailed_result['curl_error']) {
                $result['curl_error'] = $detailed_result['curl_error'];
            }

            $error_code = $detailed_result['wp_error']['code'];

            // Enhanced guidance for WP_Error cases
            if ($error_code === 'http_request_failed') {
                $result['guidance'] = __('http_request_failed: ネットワーク/URL制限の可能性があります。接続設定をご確認ください。', 'wp-minpaku-connector');

                // Check for specific error patterns in the message
                $error_message = $detailed_result['wp_error']['message'];
                if (strpos($error_message, 'URL is external') !== false || strpos($error_message, 'reject_unsafe_urls') !== false) {
                    $result['guidance'] = __('URL制限エラー: 開発環境への接続が制限されています。設定を確認してください。', 'wp-minpaku-connector');
                } elseif (strpos($error_message, 'cURL error') !== false) {
                    $result['guidance'] = __('cURL接続エラー: ネットワーク接続またはSSL設定を確認してください。', 'wp-minpaku-connector');
                }
            } elseif (strpos($error_code, 'timeout') !== false) {
                $result['guidance'] = __('タイムアウトエラー: ネットワーク接続とポータルサーバーの状態を確認してください。', 'wp-minpaku-connector');
            } elseif ($error_code === 'http_request_not_executed') {
                $result['guidance'] = __('リクエスト実行エラー: WP_HTTP_BLOCK_EXTERNAL設定を確認してください。', 'wp-minpaku-connector');
            } else {
                $result['guidance'] = sprintf(__('ネットワークエラー (%s): 再度お試しください。', 'wp-minpaku-connector'), $error_code);
            }

            wp_send_json_error($result);
            return;
        }

        // Handle HTTP status-based guidance with enhanced details
        if (!$detailed_result['success']) {
            $status = $detailed_result['http_status'];

            if ($status === 401) {
                $result['guidance'] = __('認証エラー: APIキー形式、シークレットキー、サイトIDを確認してください。', 'wp-minpaku-connector');

                // Try to extract more specific error info from response
                if ($detailed_result['parsed_data'] && isset($detailed_result['parsed_data']['code'])) {
                    $error_code = $detailed_result['parsed_data']['code'];
                    if ($error_code === 'invalid_signature') {
                        $result['guidance'] = __('HMAC署名検証エラー: シークレットキーまたは時刻同期を確認してください。', 'wp-minpaku-connector');
                    } elseif ($error_code === 'invalid_api_key') {
                        $result['guidance'] = __('APIキーエラー: 正しいAPIキー形式か確認してください。', 'wp-minpaku-connector');
                    } elseif ($error_code === 'invalid_timestamp') {
                        $result['guidance'] = __('タイムスタンプエラー: サーバーの時刻同期を確認してください。', 'wp-minpaku-connector');
                    }
                }
            } elseif ($status === 403) {
                $result['guidance'] = __('アクセス拒否: ポータル設定の許可ドメインリストを確認してください。', 'wp-minpaku-connector');
            } elseif ($status === 404) {
                $result['guidance'] = __('エンドポイント不明: ポータルベースURLのスペルと接続性を確認してください。', 'wp-minpaku-connector');
            } elseif ($status === 408) {
                $result['guidance'] = __('リクエストタイムアウト: ネットワーク接続とポータルサーバーの状態を確認してください。', 'wp-minpaku-connector');
            } elseif ($status >= 500) {
                $result['guidance'] = sprintf(__('サーバーエラー (HTTP %d): ポータルサーバーの状態を確認してください。', 'wp-minpaku-connector'), $status);
            } else {
                $result['guidance'] = sprintf(__('HTTP %dエラーが発生しました。ポータルサーバーの状態を確認してください。', 'wp-minpaku-connector'), $status);
            }
        }

        // Include parsed response data if available
        if ($detailed_result['parsed_data']) {
            $result['parsed_data'] = $detailed_result['parsed_data'];
        }

        // Add WP_HTTP_BLOCK_EXTERNAL warning if needed
        if ($result['wp_http_block_external_warning']) {
            if (!isset($result['warnings'])) {
                $result['warnings'] = array();
            }
            $result['warnings'][] = __('WP_HTTP_BLOCK_EXTERNALが有効です。外部接続が制限される可能性があります。', 'wp-minpaku-connector');
        }

        if ($detailed_result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Connection section callback
     */
    public static function connection_section_callback() {
        echo '<p>' . esc_html__('Enter your Minpaku Suite portal connection details. You can get these from your portal admin under Minpaku › Settings › Connector.', 'wp-minpaku-connector') . '</p>';
    }

    /**
     * Portal URL field callback
     */
    public static function portal_url_callback() {
        $settings = \WP_Minpaku_Connector::get_settings();
        echo '<input type="url" id="portal_url" name="wp_minpaku_connector_settings[portal_url]" value="' . esc_attr($settings['portal_url']) . '" class="regular-text" placeholder="https://your-portal.com" required />';
        echo '<p class="description">' . esc_html__('The base URL of your Minpaku Suite portal (e.g., https://yoursite.com)', 'wp-minpaku-connector') . '</p>';
    }

    /**
     * Site ID field callback
     */
    public static function site_id_callback() {
        $settings = \WP_Minpaku_Connector::get_settings();
        echo '<input type="text" id="site_id" name="wp_minpaku_connector_settings[site_id]" value="' . esc_attr($settings['site_id']) . '" class="regular-text" required />';
        echo '<p class="description">' . esc_html__('The Site ID generated in your portal connector settings.', 'wp-minpaku-connector') . '</p>';
    }

    /**
     * API Key field callback
     */
    public static function api_key_callback() {
        $settings = \WP_Minpaku_Connector::get_settings();
        echo '<input type="text" id="api_key" name="wp_minpaku_connector_settings[api_key]" value="' . esc_attr($settings['api_key']) . '" class="regular-text" required />';
        echo '<p class="description">' . esc_html__('The API Key generated in your portal connector settings.', 'wp-minpaku-connector') . '</p>';
    }

    /**
     * Secret field callback
     */
    public static function secret_callback() {
        $settings = \WP_Minpaku_Connector::get_settings();
        echo '<input type="password" id="secret" name="wp_minpaku_connector_settings[secret]" value="' . esc_attr($settings['secret']) . '" class="regular-text" required />';
        echo '<p class="description">' . esc_html__('The Secret key generated in your portal connector settings.', 'wp-minpaku-connector') . '</p>';
    }

    /**
     * Render admin page
     */
    public static function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'wp-minpaku-connector'));
        }

        $settings = \WP_Minpaku_Connector::get_settings();
        $is_configured = !empty($settings['portal_url']) && !empty($settings['api_key']) && !empty($settings['secret']) && !empty($settings['site_id']);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Minpaku Connector Settings', 'wp-minpaku-connector'); ?></h1>

            <p><?php echo esc_html__('Connect your WordPress site to a Minpaku Suite portal to display property listings and availability calendars.', 'wp-minpaku-connector'); ?></p>

            <?php settings_errors(); ?>

            <form method="post" action="options.php">
                <?php
                settings_fields('wp_minpaku_connector_settings');
                do_settings_sections('wp-minpaku-connector');
                submit_button();
                ?>
            </form>

            <?php if ($is_configured): ?>
                <hr>
                <h2><?php echo esc_html__('Connection Test', 'wp-minpaku-connector'); ?></h2>
                <p><?php echo esc_html__('Test your connection to the Minpaku Suite portal.', 'wp-minpaku-connector'); ?></p>
                <p>
                    <button type="button" id="test-connection" class="button button-secondary">
                        <?php echo esc_html__('Test Connection', 'wp-minpaku-connector'); ?>
                    </button>
                    <span id="test-result"></span>
                </p>

                <hr>
                <h2><?php echo esc_html__('Connection Diagnostics', 'wp-minpaku-connector'); ?></h2>
                <p><?php echo esc_html__('Perform step-by-step diagnostics to troubleshoot connection issues.', 'wp-minpaku-connector'); ?></p>
                <p>
                    <button type="button" id="run-diagnostics" class="button button-secondary">
                        <?php echo esc_html__('Run Diagnostics', 'wp-minpaku-connector'); ?>
                    </button>
                </p>

                <div id="diagnostic-results" style="display:none; margin-top: 15px;">
                    <h3><?php echo esc_html__('Diagnostic Steps', 'wp-minpaku-connector'); ?></h3>

                    <div class="diagnostic-step" id="step-a">
                        <h4><?php echo esc_html__('Step A: URL Normalization & DNS', 'wp-minpaku-connector'); ?></h4>
                        <div class="diagnostic-content">
                            <span class="spinner"></span>
                            <span class="diagnostic-status"><?php echo esc_html__('Checking...', 'wp-minpaku-connector'); ?></span>
                        </div>
                    </div>

                    <div class="diagnostic-step" id="step-b">
                        <h4><?php echo esc_html__('Step B: Basic Connectivity (/connector/ping)', 'wp-minpaku-connector'); ?></h4>
                        <div class="diagnostic-content">
                            <span class="spinner"></span>
                            <span class="diagnostic-status"><?php echo esc_html__('Waiting...', 'wp-minpaku-connector'); ?></span>
                        </div>
                    </div>

                    <div class="diagnostic-step" id="step-c">
                        <h4><?php echo esc_html__('Step C: Authentication (/connector/verify)', 'wp-minpaku-connector'); ?></h4>
                        <div class="diagnostic-content">
                            <span class="spinner"></span>
                            <span class="diagnostic-status"><?php echo esc_html__('Waiting...', 'wp-minpaku-connector'); ?></span>
                        </div>
                    </div>
                </div>

                <hr>
                <h2><?php echo esc_html__('使用方法', 'wp-minpaku-connector'); ?></h2>
                <p><?php echo esc_html__('以下のショートコードを使用して、Minpaku Suite ポータルからコンテンツを表示できます：', 'wp-minpaku-connector'); ?></p>

                <h3><?php echo esc_html__('物件一覧', 'wp-minpaku-connector'); ?></h3>
                <code>[minpaku_connector type="properties" limit="12" columns="3"]</code>
                <p class="description"><?php echo esc_html__('物件一覧をグリッド表示します。パラメータ: limit（表示件数）、columns（列数）', 'wp-minpaku-connector'); ?></p>

                <h3><?php echo esc_html__('空室カレンダー', 'wp-minpaku-connector'); ?></h3>
                <code>[minpaku_connector type="availability" property_id="123" months="2"]</code>
                <p class="description"><?php echo esc_html__('指定した物件の空室カレンダーを表示します。パラメータ: property_id（必須）、months（表示月数）', 'wp-minpaku-connector'); ?></p>

                <h3><?php echo esc_html__('物件詳細', 'wp-minpaku-connector'); ?></h3>
                <code>[minpaku_connector type="property" property_id="123"]</code>
                <p class="description"><?php echo esc_html__('指定した物件の詳細情報を表示します。パラメータ: property_id（必須）', 'wp-minpaku-connector'); ?></p>

                <h4><?php echo esc_html__('追加オプション', 'wp-minpaku-connector'); ?></h4>
                <ul>
                    <li><strong>show_prices</strong>: <?php echo esc_html__('価格表示の有効/無効（"true"/"false"、デフォルト: "true"）', 'wp-minpaku-connector'); ?></li>
                    <li><strong>adults</strong>: <?php echo esc_html__('大人の人数（デフォルト: 2）', 'wp-minpaku-connector'); ?></li>
                    <li><strong>children</strong>: <?php echo esc_html__('子供の人数（デフォルト: 0）', 'wp-minpaku-connector'); ?></li>
                    <li><strong>currency</strong>: <?php echo esc_html__('通貨単位（デフォルト: "JPY"）', 'wp-minpaku-connector'); ?></li>
                </ul>

                <h4><?php echo esc_html__('使用例', 'wp-minpaku-connector'); ?></h4>
                <code>[minpaku_connector type="availability" property_id="123" months="3" show_prices="true" adults="4"]</code>
                <p class="description"><?php echo esc_html__('物件ID 123 の空室カレンダーを3ヶ月分、大人4名での価格付きで表示', 'wp-minpaku-connector'); ?></p>

            <?php else: ?>
                <div class="notice notice-warning">
                    <p><?php echo esc_html__('Please complete the connection settings above before using shortcodes.', 'wp-minpaku-connector'); ?></p>
                </div>
            <?php endif; ?>

            <hr>
            <h2><?php echo esc_html__('Setup Instructions', 'wp-minpaku-connector'); ?></h2>
            <ol>
                <li><?php echo esc_html__('Log in to your Minpaku Suite portal admin area.', 'wp-minpaku-connector'); ?></li>
                <li><?php echo esc_html__('Go to Minpaku › Settings › Connector.', 'wp-minpaku-connector'); ?></li>
                <li><?php echo esc_html__('Enable the connector and add your WordPress site domain to the allowed domains list.', 'wp-minpaku-connector'); ?></li>
                <li><?php echo esc_html__('Generate new API keys for this site.', 'wp-minpaku-connector'); ?></li>
                <li><?php echo esc_html__('Copy the Portal Base URL, Site ID, API Key, and Secret to the form above.', 'wp-minpaku-connector'); ?></li>
                <li><?php echo esc_html__('Save the settings and test the connection.', 'wp-minpaku-connector'); ?></li>
                <li><?php echo esc_html__('Use the shortcodes on your pages and posts to display content.', 'wp-minpaku-connector'); ?></li>
            </ol>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#test-connection').on('click', function() {
                var button = $(this);
                var result = $('#test-result');

                button.prop('disabled', true).text('<?php echo esc_js(__('Testing...', 'wp-minpaku-connector')); ?>');
                result.removeClass('success error warning').html('<span class="spinner is-active" style="float:none;margin:0 5px 0 0;"></span><?php echo esc_js(__('Testing connection...', 'wp-minpaku-connector')); ?>');

                $.post(ajaxurl, {
                    action: 'mpc_test_connection',
                    nonce: '<?php echo wp_create_nonce('mpc_test_connection'); ?>'
                })
                .done(function(response) {
                    if (response.success) {
                        var html = '<span style="color: #00a32a; font-weight: bold;">✓ ' + response.data.message + '</span>';

                        // Add warnings if any
                        if (response.data.warnings && response.data.warnings.length > 0) {
                            html += '<br><span style="color: #dba617; font-size: 0.9em;">⚠ ' + response.data.warnings.join('<br>⚠ ') + '</span>';
                        }

                        result.removeClass('error warning').addClass('success').html(html);

                        // Show additional success info
                        if (response.data.data && response.data.data.version) {
                            result.append('<br><small style="color: #666;"><?php echo esc_js(__('Portal version:', 'wp-minpaku-connector')); ?> ' + response.data.data.version + '</small>');
                        }
                    } else {
                        var errorHtml = '<span style="color: #d63638; font-weight: bold;">✗ ' + response.data.message + '</span>';

                        // Add specific error guidance
                        if (response.data.type === 'auth') {
                            errorHtml += '<br><small style="color: #666;"><?php echo esc_js(__('Check: API Key format, Secret key, Site ID', 'wp-minpaku-connector')); ?></small>';
                        } else if (response.data.type === 'permission') {
                            errorHtml += '<br><small style="color: #666;"><?php echo esc_js(__('Check: Allowed domains list in portal settings', 'wp-minpaku-connector')); ?></small>';
                        } else if (response.data.type === 'url') {
                            errorHtml += '<br><small style="color: #666;"><?php echo esc_js(__('Check: Portal Base URL spelling and accessibility', 'wp-minpaku-connector')); ?></small>';
                        } else if (response.data.type === 'timeout') {
                            errorHtml += '<br><small style="color: #666;"><?php echo esc_js(__('Check: Network connectivity and portal server status', 'wp-minpaku-connector')); ?></small>';
                        }

                        result.removeClass('success warning').addClass('error').html(errorHtml);

                        // Log error for debugging
                        if (window.console && response.data.original) {
                            console.log('WMC Connection Test Error:', response.data.original);
                        }
                    }
                })
                .fail(function(xhr, status, error) {
                    var errorMsg = '<?php echo esc_js(__('Network error occurred. Please try again.', 'wp-minpaku-connector')); ?>';
                    if (xhr.responseJSON && xhr.responseJSON.data) {
                        errorMsg = xhr.responseJSON.data;
                    }
                    result.removeClass('success warning').addClass('error').html('<span style="color: #d63638; font-weight: bold;">✗ ' + errorMsg + '</span>');

                    // Log error for debugging
                    if (window.console) {
                        console.log('WMC AJAX Error:', xhr, status, error);
                    }
                })
                .always(function() {
                    button.prop('disabled', false).text('<?php echo esc_js(__('Test Connection', 'wp-minpaku-connector')); ?>');
                });
            });

            // Diagnostic functionality
            $('#run-diagnostics').on('click', function() {
                var button = $(this);
                var resultsDiv = $('#diagnostic-results');

                button.prop('disabled', true).text('<?php echo esc_js(__('Running...', 'wp-minpaku-connector')); ?>');
                resultsDiv.show();

                // Reset all steps
                $('.diagnostic-step').removeClass('step-success step-error');
                $('.diagnostic-step .spinner').removeClass('is-active');
                $('.diagnostic-status').text('<?php echo esc_js(__('Waiting...', 'wp-minpaku-connector')); ?>');

                // Run Step A
                runDiagnosticStep('a', function(success) {
                    if (success) {
                        // Run Step B
                        runDiagnosticStep('b', function(success) {
                            if (success) {
                                // Run Step C
                                runDiagnosticStep('c', function(success) {
                                    button.prop('disabled', false).text('<?php echo esc_js(__('Run Diagnostics', 'wp-minpaku-connector')); ?>');
                                });
                            } else {
                                button.prop('disabled', false).text('<?php echo esc_js(__('Run Diagnostics', 'wp-minpaku-connector')); ?>');
                            }
                        });
                    } else {
                        button.prop('disabled', false).text('<?php echo esc_js(__('Run Diagnostics', 'wp-minpaku-connector')); ?>');
                    }
                });
            });

            function runDiagnosticStep(step, callback) {
                var stepDiv = $('#step-' + step);
                var statusSpan = stepDiv.find('.diagnostic-status');
                var spinner = stepDiv.find('.spinner');

                stepDiv.removeClass('step-success step-error');
                spinner.addClass('is-active');
                statusSpan.text('<?php echo esc_js(__('Running...', 'wp-minpaku-connector')); ?>');

                $.post(ajaxurl, {
                    action: 'mpc_diagnostic_step_' + step,
                    nonce: '<?php echo wp_create_nonce('mpc_diagnostic'); ?>'
                })
                .done(function(response) {
                    spinner.removeClass('is-active');

                    if (response.success) {
                        stepDiv.addClass('step-success');
                        var html = '<span style="color: #00a32a; font-weight: bold;">✓ <?php echo esc_js(__('Success', 'wp-minpaku-connector')); ?></span>';

                        // Add step-specific success information
                        if (step === 'a') {
                            html += '<br><small>URL: ' + (response.data.normalized_url || '<?php echo esc_js(__('None', 'wp-minpaku-connector')); ?>') + '</small>';
                            if (response.data.dns && response.data.dns.success) {
                                html += '<br><small>IP: ' + response.data.dns.resolved_ip + '</small>';
                            }
                        } else if (step === 'b') {
                            html += '<br><small>Status: ' + response.data.status_code + ' (' + response.data.request_time_ms + 'ms)</small>';
                            if (response.data.parsed_data && response.data.parsed_data.site) {
                                html += '<br><small>Site: ' + response.data.parsed_data.site + '</small>';
                            }
                        } else if (step === 'c') {
                            html += '<br><small>HTTP ' + (response.data.http_status || 'N/A') + ' (' + response.data.request_time_ms + 'ms)</small>';
                            if (response.data.parsed_data && response.data.parsed_data.version) {
                                html += '<br><small>Version: ' + response.data.parsed_data.version + '</small>';
                            }
                            if (response.data.request_headers_sent) {
                                var headerCount = Object.keys(response.data.request_headers_sent).length;
                                html += '<br><small>Headers sent: ' + headerCount + '</small>';
                            }
                        }

                        statusSpan.html(html);
                        callback(true);
                    } else {
                        stepDiv.addClass('step-error');
                        var html = '<span style="color: #d63638; font-weight: bold;">✗ <?php echo esc_js(__('Failed', 'wp-minpaku-connector')); ?></span>';

                        // Add error details
                        if (response.data && response.data.message) {
                            html += '<br><small>' + response.data.message + '</small>';
                        }

                        // Add step-specific error information
                        if (step === 'b' && response.data) {
                            if (response.data.status_code) {
                                html += '<br><small>HTTP ' + response.data.status_code + '</small>';
                            }
                            if (response.data.error) {
                                html += '<br><small>' + response.data.error + '</small>';
                            }
                        } else if (step === 'c' && response.data) {
                            // Show detailed HTTP information
                            if (response.data.http_status) {
                                html += '<br><small>HTTP ' + response.data.http_status + ' (' + response.data.request_time_ms + 'ms)</small>';
                            }

                            // Show WP_Error details
                            if (response.data.wp_error) {
                                html += '<br><small>Error: ' + response.data.wp_error.code + '</small>';
                                html += '<br><small>' + response.data.wp_error.message + '</small>';

                                // Show cURL error details if available
                                if (response.data.curl_error) {
                                    if (response.data.curl_error.errno) {
                                        html += '<br><small>cURL errno: ' + response.data.curl_error.errno + '</small>';
                                    }
                                    if (response.data.curl_error.error) {
                                        html += '<br><small>cURL error: ' + response.data.curl_error.error + '</small>';
                                    }
                                }
                            }

                            // Show response body preview
                            if (response.data.response_body_preview) {
                                var preview = response.data.response_body_preview;
                                if (preview.length > 100) {
                                    preview = preview.substring(0, 100) + '...';
                                }
                                html += '<br><small>Response: ' + preview + '</small>';
                            }

                            // Show request details
                            if (response.data.request_headers_sent) {
                                var headerCount = Object.keys(response.data.request_headers_sent).length;
                                html += '<br><small>Headers sent: ' + headerCount + '</small>';
                            }

                            // Show signature debug information for 401 errors
                            if (response.data.signature_debug && response.data.http_status === 401) {
                                html += '<br><details style="margin-top: 5px;"><summary style="cursor: pointer; font-size: 11px;">🔍 署名デバッグ情報</summary>';
                                html += '<div style="font-family: monospace; font-size: 10px; margin: 5px 0; background: #f5f5f5; padding: 5px; border-radius: 3px;">';
                                if (response.data.signature_debug.method) {
                                    html += 'Method: ' + response.data.signature_debug.method + '<br>';
                                }
                                if (response.data.signature_debug.path) {
                                    html += 'Path: ' + response.data.signature_debug.path + '<br>';
                                }
                                if (response.data.signature_debug.timestamp) {
                                    html += 'Timestamp: ' + response.data.signature_debug.timestamp + '<br>';
                                }
                                if (response.data.signature_debug.body_hash) {
                                    html += 'Body Hash: ' + response.data.signature_debug.body_hash + '<br>';
                                }
                                if (response.data.signature_debug.string_to_sign) {
                                    html += 'String to Sign Length: ' + response.data.signature_debug.string_to_sign_length + '<br>';
                                }
                                if (response.data.signature_debug.signature_full) {
                                    html += 'Signature: ' + response.data.signature_debug.signature_full.substring(0, 16) + '...<br>';
                                }
                                html += '</div></details>';
                            }

                            // Show warnings
                            if (response.data.warnings && response.data.warnings.length > 0) {
                                for (var i = 0; i < response.data.warnings.length; i++) {
                                    html += '<br><small style="color: #dba617;">⚠ ' + response.data.warnings[i] + '</small>';
                                }
                            }

                            // Show guidance
                            if (response.data.guidance) {
                                html += '<br><small style="color: #666;">' + response.data.guidance + '</small>';
                            }
                        }

                        statusSpan.html(html);
                        callback(false);
                    }
                })
                .fail(function(xhr, status, error) {
                    spinner.removeClass('is-active');
                    stepDiv.addClass('step-error');
                    statusSpan.html('<span style="color: #d63638; font-weight: bold;">✗ <?php echo esc_js(__('Network error', 'wp-minpaku-connector')); ?></span>');
                    callback(false);
                });
            }
        });
        </script>

        <style>
        #test-result.success {
            color: #00a32a;
            font-weight: bold;
            margin-left: 10px;
        }

        #test-result.error {
            color: #d63638;
            font-weight: bold;
            margin-left: 10px;
        }

        .wrap code {
            background: #f0f0f0;
            padding: 8px 12px;
            border-radius: 4px;
            display: inline-block;
            margin: 5px 0;
            font-family: Consolas, Monaco, 'Courier New', monospace;
        }

        .wrap h3 {
            margin-top: 25px;
            margin-bottom: 5px;
        }

        .wrap .description {
            font-style: italic;
            margin-top: 5px;
            margin-bottom: 15px;
        }

        /* Diagnostic styles */
        .diagnostic-step {
            margin: 15px 0;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #f9f9f9;
        }

        .diagnostic-step h4 {
            margin: 0 0 10px 0;
            color: #333;
        }

        .diagnostic-step.step-success {
            border-color: #00a32a;
            background: #f0f9f0;
        }

        .diagnostic-step.step-error {
            border-color: #d63638;
            background: #fdf0f0;
        }

        .diagnostic-content {
            display: flex;
            align-items: center;
        }

        .diagnostic-status {
            margin-left: 10px;
        }

        .diagnostic-step .spinner {
            float: none;
            margin: 0;
            visibility: hidden;
        }

        .diagnostic-step .spinner.is-active {
            visibility: visible;
        }
        </style>
        <?php
    }
}