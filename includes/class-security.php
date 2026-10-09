<?php
/**
 * Security Class
 *
 * Handles encryption, validation, and security functions
 *
 * @package ChatProjects
 */

namespace ChatProjects;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Security Class
 */
class Security {
    /**
     * Cipher used by values stored before 1.3.0 (read-only; see decrypt()).
     */
    const ENCRYPTION_METHOD = 'AES-256-CBC';

    /**
     * Prefix of values encrypted with the current scheme (libsodium secretbox:
     * XSalsa20-Poly1305, so tampered values fail to decrypt).
     */
    const ENCRYPTION_PREFIX = 'cpv2:';

    /**
     * Cached legacy key for request consistency
     *
     * @var string|null
     */
    private static $cached_encryption_key = null;

    /**
     * Secret the encryption keys are derived from.
     *
     * CHATPROJECTS_ENCRYPTION_KEY if defined, else the site's AUTH_KEY /
     * SECURE_AUTH_KEY, else (sites without salts only) ABSPATH + DB_NAME, which
     * ChatProjects Pro derives identically. The pre-1.3.0 scheme used the plugin
     * path instead, so legacy values pass $legacy = true.
     * Changing it makes stored API keys unreadable; they must be re-entered.
     *
     * @param bool $legacy Seed for the pre-1.3.0 AES scheme.
     * @return string
     */
    private static function get_key_seed($legacy = false) {
        if (defined('CHATPROJECTS_ENCRYPTION_KEY') && '' !== (string) CHATPROJECTS_ENCRYPTION_KEY) {
            return (string) CHATPROJECTS_ENCRYPTION_KEY;
        }
        if (defined('AUTH_KEY') && !empty(AUTH_KEY) && AUTH_KEY !== 'put your unique phrase here') {
            return AUTH_KEY;
        }
        if (defined('SECURE_AUTH_KEY') && !empty(SECURE_AUTH_KEY) && SECURE_AUTH_KEY !== 'put your unique phrase here') {
            return SECURE_AUTH_KEY;
        }
        $db_name = defined('DB_NAME') ? DB_NAME : 'chatprojects';
        if ($legacy) {
            $plugin_path = defined('CHATPROJECTS_PLUGIN_FILE') ? plugin_dir_path(CHATPROJECTS_PLUGIN_FILE) : __DIR__;
            return $plugin_path . $db_name;
        }
        return ABSPATH . $db_name;
    }

    /**
     * 32-byte key for the current scheme.
     *
     * @return string Raw binary key.
     */
    private static function get_secretbox_key() {
        return hash('sha256', 'chatprojects_v2|' . self::get_key_seed(), true);
    }

    /**
     * Key used by the pre-1.3.0 scheme, kept so old values can still be read.
     *
     * @return string
     */
    private static function get_encryption_key() {
        if (self::$cached_encryption_key !== null) {
            return self::$cached_encryption_key;
        }

        if (defined('CHATPROJECTS_ENCRYPTION_KEY')) {
            self::$cached_encryption_key = CHATPROJECTS_ENCRYPTION_KEY;
        } else {
            self::$cached_encryption_key = substr(hash('sha256', 'chatprojects_' . self::get_key_seed(true)), 0, 32);
        }

        return self::$cached_encryption_key;
    }

    /**
     * Whether a stored value already uses the current encryption scheme.
     *
     * @param string $value Stored value.
     * @return bool
     */
    public static function is_current_format($value) {
        return is_string($value) && 0 === strpos($value, self::ENCRYPTION_PREFIX);
    }

    /**
     * Encrypt data
     *
     * @param string $data Data to encrypt
     * @return string|false Encrypted data or false on failure
     */
    public static function encrypt($data) {
        if (empty($data)) {
            return '';
        }

        try {
            $nonce  = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cipher = sodium_crypto_secretbox((string) $data, $nonce, self::get_secretbox_key());
        } catch (\Exception $e) {
            return false;
        }

        // base64 keeps the binary value safe in wp_options.
        return self::ENCRYPTION_PREFIX . base64_encode($nonce . $cipher); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary ciphertext stored as text.
    }

    /**
     * Decrypt data
     *
     * @param string $encrypted_data Encrypted data
     * @return string|false Decrypted data or false on failure
     */
    public static function decrypt($encrypted_data) {
        if (empty($encrypted_data)) {
            return '';
        }

        if (self::is_current_format($encrypted_data)) {
            $raw = base64_decode(substr($encrypted_data, strlen(self::ENCRYPTION_PREFIX)), true); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decoding our own stored ciphertext.
            if (false === $raw || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
                return false;
            }
            try {
                $plain = sodium_crypto_secretbox_open(
                    substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
                    substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
                    self::get_secretbox_key()
                );
            } catch (\Exception $e) {
                return false;
            }
            return false === $plain ? false : $plain;
        }

        return self::decrypt_legacy($encrypted_data);
    }

    /**
     * Read a value stored before 1.3.0 (AES-256-CBC, no integrity check).
     *
     * @param string $encrypted_data Stored value.
     * @return string|false
     */
    private static function decrypt_legacy($encrypted_data) {
        $key = self::get_encryption_key();
        $data = base64_decode($encrypted_data, true); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decoding legacy stored ciphertext.

        // If base64 decode succeeded and data is long enough, try decryption
        if ($data !== false) {
            $iv_length = openssl_cipher_iv_length(self::ENCRYPTION_METHOD);
            if ($iv_length !== false && strlen($data) >= $iv_length) {
                $iv = substr($data, 0, $iv_length);
                $encrypted = substr($data, $iv_length);

                $decrypted = openssl_decrypt($encrypted, self::ENCRYPTION_METHOD, $key, 0, $iv);

                // If decryption succeeded, return the result
                if ($decrypted !== false) {
                    return $decrypted;
                }
            }
        }

        // Decryption failed - check if this looks like an unencrypted API key
        // (backwards compatibility with pre-encryption storage)
        if (preg_match('/^(sk-|sk-proj-|AIza|sk-ant-|cpat_)/', $encrypted_data)) {
            return $encrypted_data;
        }

        // If it contains characters not in base64 alphabet, it might be an unencrypted key
        if (preg_match('/[^A-Za-z0-9+\/=]/', $encrypted_data)) {
            return $encrypted_data;
        }

        // Unable to decrypt and doesn't look like a plain API key
        return false;
    }

    /**
     * Sanitize API key
     *
     * @param string $api_key API key to sanitize
     * @return string
     */
    public static function sanitize_api_key($api_key) {
        $api_key = sanitize_text_field($api_key);

        // Encrypt the API key before storing
        if (!empty($api_key)) {
            // Validate input looks like a real API key, not garbage
            $valid_prefixes = array('sk-', 'sk-proj-', 'AIza', 'sk-ant-', 'cpat_', 'cpk_', 'sk-or-');
            $has_valid_prefix = false;
            foreach ($valid_prefixes as $prefix) {
                if (strpos($api_key, $prefix) === 0) {
                    $has_valid_prefix = true;
                    break;
                }
            }

            // If input doesn't look like a valid API key, reject it
            if (!$has_valid_prefix) {
                return '';
            }

            $encrypted = self::encrypt($api_key);

            // If encryption fails, return empty string
            if ($encrypted === false) {
                return '';
            }

            return $encrypted;
        }

        return '';
    }

    /**
     * Get decrypted API key
     *
     * @return string
     */
    public static function get_api_key() {
        $encrypted_key = get_option('chatprojects_openai_key', '');

        if (empty($encrypted_key)) {
            return '';
        }

        $decrypted = self::decrypt($encrypted_key);

        // If decryption fails, return empty string
        if ($decrypted === false) {
            return '';
        }

        return $decrypted;
    }

    /**
     * Verify nonce for AJAX requests
     *
     * @param string $nonce Nonce value
     * @param string $action Nonce action
     * @return bool
     */
    public static function verify_ajax_nonce($nonce, $action = 'chatprojects_frontend') {
        if (!wp_verify_nonce($nonce, $action)) {
            return false;
        }
        return true;
    }

    /**
     * Check user capability
     *
     * @param string $capability Required capability
     * @param int    $user_id User ID (optional, defaults to current user)
     * @return bool
     */
    public static function user_can($capability, $user_id = null) {
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        return user_can($user_id, $capability);
    }

    /**
     * Sanitize file name
     *
     * @param string $filename File name to sanitize
     * @return string
     */
    public static function sanitize_filename($filename) {
        // Remove path information
        $filename = basename($filename);

        // Sanitize
        $filename = sanitize_file_name($filename);

        // Additional security: remove any remaining dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);

        return $filename;
    }

    /**
     * Validate file type
     *
     * @param string $file_path     Path to the file on disk (tmp_name for uploads).
     * @param array  $allowed_types Allowed file extensions.
     * @param string $original_name Original client filename (extension source for uploads).
     * @return bool
     */
    public static function validate_file_type($file_path, $allowed_types = array(), $original_name = '') {
        if (empty($allowed_types)) {
            $allowed_types = get_option('chatprojects_allowed_file_types', array());
        }
        if (is_string($allowed_types)) {
            $allowed_types = array_filter(array_map('trim', explode(',', strtolower($allowed_types))));
        }

        // If still empty, use default allowed types
        if (empty($allowed_types)) {
            $allowed_types = self::default_allowed_file_types();
        }

        // Executable / server-side types are never allowed, whatever the option says.
        $allowed_types = array_values(array_diff(array_map('strtolower', (array) $allowed_types), self::blocked_file_types()));

        // Check file extension (from the original filename when given; tmp uploads have none).
        $name_for_ext = '' !== $original_name ? $original_name : $file_path;
        $extension    = strtolower((string) pathinfo($name_for_ext, PATHINFO_EXTENSION));

        if ('' === $extension || !in_array($extension, $allowed_types, true)) {
            return false;
        }

        // Also validate actual MIME type using finfo if available.
        if (function_exists('finfo_open') && file_exists($file_path)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detected_mime = finfo_file($finfo, $file_path);

            // Map extensions to expected MIME types.
            $mime_map = array(
                'pdf'  => array('application/pdf'),
                'doc'  => array('application/msword'),
                'docx' => array('application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                'txt'  => array('text/plain'),
                'md'   => array('text/plain', 'text/markdown'),
                'xls'  => array('application/vnd.ms-excel'),
                'xlsx' => array('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
                'csv'  => array('text/plain', 'text/csv', 'application/csv'),
                'json' => array('application/json', 'text/plain'),
                'xml'  => array('application/xml', 'text/xml', 'text/plain'),
                'html' => array('text/html', 'text/plain'),
                'css'  => array('text/css', 'text/plain'),
                'js'   => array('application/javascript', 'text/javascript', 'text/plain'),
                'py'   => array('text/x-python', 'text/plain'),
                'php'  => array('text/x-php', 'text/plain'),
                'java' => array('text/x-java-source', 'text/plain'),
                'cpp'  => array('text/x-c++src', 'text/plain'),
            );

            if (isset($mime_map[ $extension ])) {
                if (!in_array($detected_mime, $mime_map[ $extension ], true)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Validate file size
     *
     * @param int $file_size File size in bytes
     * @return bool
     */
    public static function validate_file_size($file_size) {
        $max_size = get_option('chatprojects_max_file_size', 50) * 1024 * 1024; // Convert MB to bytes
        return $file_size <= $max_size;
    }

    /**
     * Sanitize HTML for output
     *
     * @param string $html HTML content
     * @return string
     */
    public static function sanitize_html($html) {
        return wp_kses_post($html);
    }

    /**
     * Sanitize JSON
     *
     * @param string $json JSON string
     * @return string|false
     */
    public static function sanitize_json($json) {
        $decoded = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        return wp_json_encode($decoded);
    }

    /**
     * Generate secure random string
     *
     * @param int $length Length of string
     * @return string
     */
    public static function generate_random_string($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Hash string
     *
     * @param string $string String to hash
     * @return string
     */
    public static function hash_string($string) {
        return hash('sha256', $string);
    }

    /**
     * Verify hash
     *
     * @param string $string Original string
     * @param string $hash Hash to verify
     * @return bool
     */
    public static function verify_hash($string, $hash) {
        return hash_equals(self::hash_string($string), $hash);
    }

    /**
     * Rate limit check
     *
     * @param string $action Action name
     * @param int    $user_id User ID
     * @param int    $limit Number of attempts allowed
     * @param int    $period Time period in seconds
     * @return bool True if allowed, false if rate limited
     */
    public static function check_rate_limit($action, $user_id, $limit = 10, $period = 60) {
        $transient_key = "chatprojects_ratelimit_{$action}_{$user_id}";
        $attempts = get_transient($transient_key);

        if ($attempts === false) {
            set_transient($transient_key, 1, $period);
            return true;
        }

        if ($attempts >= $limit) {
            return false;
        }

        set_transient($transient_key, $attempts + 1, $period);
        return true;
    }

    /**
     * Default upload allow-list (extensions).
     *
     * @return array
     */
    public static function default_allowed_file_types() {
        return array(
            'pdf', 'doc', 'docx', 'txt', 'md',
            'csv', 'json', 'xml', 'css',
            'py', 'java', 'cpp',
        );
    }

    /**
     * Extensions that are refused even when an administrator adds them.
     *
     * @return array
     */
    public static function blocked_file_types() {
        return array('php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'phar', 'js', 'mjs', 'html', 'htm', 'svg', 'exe', 'sh', 'bat', 'cmd');
    }

    /**
     * Debug log helper.
     *
     * Writes to the PHP error log only when WP_DEBUG and WP_DEBUG_LOG are both
     * enabled. Never pass API keys or full request/response bodies.
     *
     * @param string $message Message to log
     */
    public static function debug_log($message) {
        if (!defined('WP_DEBUG') || !WP_DEBUG || !defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
            return;
        }
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Gated on WP_DEBUG_LOG.
        error_log('[ChatProjects] ' . (is_scalar($message) ? $message : wp_json_encode($message)));
    }

    /**
     * Log security event - disabled for production
     *
     * @param string $event Event description
     * @param string $severity Severity level (info, warning, error)
     * @param array  $context Additional context
     */
    public static function log_security_event($event, $severity = 'info', $context = array()) {
        // Security logging disabled for production
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    public static function get_client_ip() {
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

        /**
         * Proxy headers to trust for the real client IP, in priority order.
         *
         * Empty by default: forwarded headers are attacker-controlled unless a
         * trusted reverse proxy in front of PHP overwrites them. Behind Cloudflare
         * return array( 'HTTP_CF_CONNECTING_IP' ); behind a generic proxy
         * array( 'HTTP_X_FORWARDED_FOR' ). CHATPROJECTS_TRUSTED_PROXY_HEADERS can
         * also be defined in wp-config.php as a comma-separated list.
         *
         * @param string[] $headers $_SERVER keys.
         */
        $trusted = apply_filters(
            'chatprojects_trusted_proxy_headers',
            defined( 'CHATPROJECTS_TRUSTED_PROXY_HEADERS' ) ? array_map( 'trim', explode( ',', CHATPROJECTS_TRUSTED_PROXY_HEADERS ) ) : array()
        );

        foreach ( (array) $trusted as $header ) {
            if ( empty( $_SERVER[ $header ] ) ) {
                continue;
            }
            $value     = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
            $candidate = trim( explode( ',', $value )[0] );
            if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
                $ip = $candidate;
                break;
            }
        }

        // Validate IP format
        if ( ! empty( $ip ) && ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            $ip = '';
        }

        return $ip;
    }

    /**
     * Validate OpenAI API key format
     *
     * @param string $api_key API key to validate
     * @return bool
     */
    public static function validate_api_key_format($api_key) {
        // OpenAI API keys start with 'sk-' and are alphanumeric
        return (bool) preg_match('/^sk-[a-zA-Z0-9]{32,}$/', $api_key);
    }

    /**
     * Allowed image MIME types for chat uploads
     */
    const ALLOWED_IMAGE_TYPES = array(
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    );

    /**
     * Get the maximum upload size for chat images
     *
     * Returns the smaller of: server upload limit or specified max
     *
     * @param int $max_mb Maximum size in megabytes (default 10)
     * @return int Maximum size in bytes
     */
    public static function get_max_image_upload_size($max_mb = 10) {
        $max_bytes = $max_mb * 1024 * 1024;
        $wp_max = wp_max_upload_size();

        return min($max_bytes, $wp_max);
    }

    /**
     * Validate an uploaded image file for chat
     *
     * @param array $file $_FILES array element
     * @param int   $max_size_mb Maximum file size in MB (default 10)
     * @return true|\WP_Error True if valid, WP_Error otherwise
     */
    public static function validate_chat_image($file, $max_size_mb = 10) {
        // Check for upload errors
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $error_messages = array(
                UPLOAD_ERR_INI_SIZE   => __('Image exceeds server upload limit.', 'chatprojects'),
                UPLOAD_ERR_FORM_SIZE  => __('Image exceeds form upload limit.', 'chatprojects'),
                UPLOAD_ERR_PARTIAL    => __('Image was only partially uploaded.', 'chatprojects'),
                UPLOAD_ERR_NO_FILE    => __('No image file was uploaded.', 'chatprojects'),
                UPLOAD_ERR_NO_TMP_DIR => __('Server missing temporary folder.', 'chatprojects'),
                UPLOAD_ERR_CANT_WRITE => __('Failed to write image to disk.', 'chatprojects'),
                UPLOAD_ERR_EXTENSION  => __('Image upload stopped by extension.', 'chatprojects'),
            );
            $error_code = isset($file['error']) ? $file['error'] : UPLOAD_ERR_NO_FILE;
            $message = isset($error_messages[ $error_code ])
                ? $error_messages[ $error_code ]
                : __('Unknown upload error.', 'chatprojects');
            return new \WP_Error('upload_error', $message);
        }

        // Check file exists
        if (!isset($file['tmp_name']) || !file_exists($file['tmp_name'])) {
            return new \WP_Error('no_file', __('Image file not found.', 'chatprojects'));
        }

        // Verify it's a real uploaded file (security)
        if (!is_uploaded_file($file['tmp_name'])) {
            return new \WP_Error('invalid_upload', __('Invalid image upload.', 'chatprojects'));
        }

        // Check file size
        $max_size = self::get_max_image_upload_size($max_size_mb);
        if ($file['size'] > $max_size) {
            $max_mb_display = round($max_size / (1024 * 1024), 1);
            return new \WP_Error(
                'file_too_large',
                sprintf(
                    /* translators: %s: maximum file size in MB */
                    __('Image exceeds maximum size of %s MB.', 'chatprojects'),
                    $max_mb_display
                )
            );
        }

        // Validate MIME type using finfo (more secure than trusting $_FILES['type'])
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);

        if (!in_array($mime_type, self::ALLOWED_IMAGE_TYPES, true)) {
            return new \WP_Error(
                'invalid_type',
                __('Invalid image type. Allowed: JPEG, PNG, GIF, WebP.', 'chatprojects')
            );
        }

        return true;
    }

    /**
     * Validate a base64 image data URL
     *
     * Used for clipboard paste images that come as base64
     *
     * @param string $data_url Base64 data URL (data:image/type;base64,...)
     * @param int    $max_size_mb Maximum decoded size in MB (default 10)
     * @return true|\WP_Error True if valid, WP_Error otherwise
     */
    public static function validate_base64_image($data_url, $max_size_mb = 10) {
        // Check format
        if (!preg_match('/^data:(image\/[a-z]+);base64,(.+)$/i', $data_url, $matches)) {
            return new \WP_Error('invalid_format', __('Invalid image data format.', 'chatprojects'));
        }

        $mime_type = strtolower($matches[1]);
        $base64_data = $matches[2];

        // Validate MIME type
        if (!in_array($mime_type, self::ALLOWED_IMAGE_TYPES, true)) {
            return new \WP_Error(
                'invalid_type',
                __('Invalid image type. Allowed: JPEG, PNG, GIF, WebP.', 'chatprojects')
            );
        }

        // Decode and check size
        $decoded = base64_decode($base64_data, true); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Validating an uploaded image data URL.
        if ($decoded === false) {
            return new \WP_Error('decode_error', __('Failed to decode image data.', 'chatprojects'));
        }

        $max_size = self::get_max_image_upload_size($max_size_mb);
        if (strlen($decoded) > $max_size) {
            $max_mb_display = round($max_size / (1024 * 1024), 1);
            return new \WP_Error(
                'file_too_large',
                sprintf(
                    /* translators: %s: maximum file size in MB */
                    __('Image exceeds maximum size of %s MB.', 'chatprojects'),
                    $max_mb_display
                )
            );
        }

        return true;
    }

    /**
     * Convert an uploaded file to a base64 data URL
     *
     * @param string $file_path Path to the file
     * @param string $mime_type MIME type of the file
     * @return string|false Base64 data URL or false on failure
     */
    public static function file_to_base64_url($file_path, $mime_type = null) {
        if (!file_exists($file_path)) {
            return false;
        }

        // Detect MIME type if not provided
        if ($mime_type === null) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file_path);
        }

        $contents = file_get_contents($file_path);
        if ($contents === false) {
            return false;
        }

        return 'data:' . $mime_type . ';base64,' . base64_encode($contents); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Building an image data URL for the AI provider.
    }

    /**
     * Extract MIME type and raw data from a base64 data URL
     *
     * @param string $data_url Base64 data URL
     * @return array|false Array with 'mime_type' and 'data' keys, or false on failure
     */
    public static function parse_base64_image($data_url) {
        if (!preg_match('/^data:(image\/[a-z]+);base64,(.+)$/i', $data_url, $matches)) {
            return false;
        }

        return array(
            'mime_type' => strtolower($matches[1]),
            'data'      => $matches[2],
        );
    }
}
