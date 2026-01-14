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
     * Encryption method
     */
    const ENCRYPTION_METHOD = 'AES-256-CBC';

    /**
     * Cached encryption key for request consistency
     *
     * @var string|null
     */
    private static $cached_encryption_key = null;

    /**
     * Get encryption key
     *
     * Uses a deterministic key derived from WordPress constants to ensure
     * consistency across all requests without any database/cache dependencies.
     *
     * @return string
     */
    private static function get_encryption_key() {
        // Return cached key if available (ensures consistency within request)
        if (self::$cached_encryption_key !== null) {
            return self::$cached_encryption_key;
        }

        // Allow override via constant
        if (defined('CHATPROJECTS_ENCRYPTION_KEY')) {
            self::$cached_encryption_key = CHATPROJECTS_ENCRYPTION_KEY;
            return self::$cached_encryption_key;
        }

        // Use deterministic key derived from WordPress constants
        // This ensures the same key is always generated without any database dependency
        $seed = '';

        // Use AUTH_KEY if available (most reliable)
        if (defined('AUTH_KEY') && !empty(AUTH_KEY) && AUTH_KEY !== 'put your unique phrase here') {
            $seed = AUTH_KEY;
        }
        // Fallback to SECURE_AUTH_KEY
        elseif (defined('SECURE_AUTH_KEY') && !empty(SECURE_AUTH_KEY) && SECURE_AUTH_KEY !== 'put your unique phrase here') {
            $seed = SECURE_AUTH_KEY;
        }
        // Last resort: use plugin directory + DB_NAME (location-agnostic)
        else {
            $plugin_path = defined('CHATPROJECTS_PLUGIN_FILE') ? plugin_dir_path(CHATPROJECTS_PLUGIN_FILE) : __DIR__;
            $seed = $plugin_path . (defined('DB_NAME') ? DB_NAME : 'chatprojects');
        }

        // Generate a consistent 32-character key
        self::$cached_encryption_key = substr(hash('sha256', 'chatprojects_' . $seed), 0, 32);

        return self::$cached_encryption_key;
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

        $key = self::get_encryption_key();
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::ENCRYPTION_METHOD));

        $encrypted = openssl_encrypt($data, self::ENCRYPTION_METHOD, $key, 0, $iv);

        if ($encrypted === false) {
            return false;
        }

        // Combine IV and encrypted data
        return base64_encode($iv . $encrypted);
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

        $key = self::get_encryption_key();
        $data = base64_decode($encrypted_data, true);

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
     * @param int $user_id User ID (optional, defaults to current user)
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
     * @param string $file_path File path
     * @param array $allowed_types Allowed file extensions
     * @return bool
     */
    public static function validate_file_type($file_path, $allowed_types = array()) {
        if (empty($allowed_types)) {
            $allowed_types = get_option('chatprojects_allowed_file_types', array());
        }

        // If still empty, use default allowed types
        if (empty($allowed_types)) {
            $allowed_types = array(
                'pdf', 'doc', 'docx', 'txt', 'md',
                'xls', 'xlsx',  // Excel files
                'csv', 'json', 'xml', 'html', 'css',
                'js', 'py', 'php', 'java', 'cpp'
            );
        }

        // Check file extension.
        $file_type = wp_check_filetype($file_path);
        $extension = $file_type['ext'];

        if (!in_array($extension, $allowed_types, true)) {
            return false;
        }

        // Also validate actual MIME type using finfo if available.
        if (function_exists('finfo_open') && file_exists($file_path)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detected_mime = finfo_file($finfo, $file_path);
            finfo_close($finfo);

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

            if (isset($mime_map[$extension])) {
                if (!in_array($detected_mime, $mime_map[$extension], true)) {
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
     * @param int $user_id User ID
     * @param int $limit Number of attempts allowed
     * @param int $period Time period in seconds
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
     * Debug log helper - disabled for production
     *
     * @param string $message Message to log
     */
    public static function debug_log($message) {
        // Debug logging disabled for production
    }

    /**
     * Log security event - disabled for production
     *
     * @param string $event Event description
     * @param string $severity Severity level (info, warning, error)
     * @param array $context Additional context
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
        $ip = '';

        if ( isset( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
        } elseif ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            // X-Forwarded-For can contain multiple IPs, get the first one
            $forwarded = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
            $ip_list = explode( ',', $forwarded );
            $ip = trim( $ip_list[0] );
        } elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
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
     * @param int $max_size_mb Maximum file size in MB (default 10)
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
            $message = isset($error_messages[$error_code])
                ? $error_messages[$error_code]
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
        finfo_close($finfo);

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
     * @param int $max_size_mb Maximum decoded size in MB (default 10)
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
        $decoded = base64_decode($base64_data, true);
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
            finfo_close($finfo);
        }

        $contents = file_get_contents($file_path);
        if ($contents === false) {
            return false;
        }

        return 'data:' . $mime_type . ';base64,' . base64_encode($contents);
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
