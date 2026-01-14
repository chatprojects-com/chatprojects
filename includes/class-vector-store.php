<?php
/**
 * Vector Store Class
 *
 * Handles vector store file management
 *
 * @package ChatProjects
 */

namespace ChatProjects;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Vector Store Class
 */
class Vector_Store {
    /**
     * API Handler instance
     *
     * @var API_Handler
     */
    private $api;

    /**
     * Constructor
     */
    public function __construct() {
        $this->api = new API_Handler();
    }

    /**
     * Upload file to vector store
     *
     * @param int $project_id Project ID
     * @param string $file_path Local file path (typically tmp_name from $_FILES)
     * @param string $original_filename Original filename (optional)
     * @return array|WP_Error File data or error
     */
    public function upload_file($project_id, $file_path, $original_filename = null) {
        // Check permissions
        if (!Access::can_edit_project($project_id)) {
            return new \WP_Error('permission_denied', __('You do not have permission to upload files to this project.', 'chatprojects'));
        }

        // Validate file
        if (!file_exists($file_path)) {
            return new \WP_Error('file_not_found', __('File not found.', 'chatprojects'));
        }

        // Check file size
        $file_size = filesize($file_path);
        if (!Security::validate_file_size($file_size)) {
            $max_size = get_option('chatprojects_max_file_size', 50);
            /* translators: %s: Maximum file size in megabytes */
            $size_message = __('File size exceeds the maximum allowed size of %s MB.', 'chatprojects');
            return new \WP_Error(
                'file_too_large',
                sprintf($size_message, $max_size)
            );
        }

        // Check file type (use original filename for extension detection)
        $filename_for_validation = $original_filename ? $original_filename : $file_path;
        if (!Security::validate_file_type($filename_for_validation)) {
            return new \WP_Error('invalid_file_type', __('File type is not allowed.', 'chatprojects'));
        }

        // Upload to OpenAI with original filename
        $file = $this->api->upload_file($file_path, 'assistants', $original_filename);

        if (is_wp_error($file)) {
            return $file;
        }

        // Get vector store ID
        $vector_store_id = get_post_meta($project_id, '_cp_vector_store_id', true);

        if (empty($vector_store_id)) {
            return new \WP_Error('no_vector_store', __('Project does not have a vector store.', 'chatprojects'));
        }

        // Add file to vector store
        $result = $this->api->add_file_to_vector_store($vector_store_id, $file['id']);

        if (is_wp_error($result)) {
            // Clean up uploaded file
            $this->api->delete_file($file['id']);
            return $result;
        }

        // Store file metadata
        $files = get_post_meta($project_id, '_cp_files', true);
        if (!is_array($files)) {
            $files = array();
        }

        $files[] = array(
            'file_id' => $file['id'],
            'filename' => $original_filename ? $original_filename : basename($file_path),
            'uploaded_at' => current_time('mysql'),
            'uploaded_by' => get_current_user_id(),
            'size' => $file_size,
        );

        update_post_meta($project_id, '_cp_files', $files);

        return array(
            'id' => $file['id'],
            'filename' => $original_filename ? $original_filename : basename($file_path),
            'bytes' => $file_size,
            'created_at' => current_time('mysql'),
        );
    }

    /**
     * Delete file from vector store
     *
     * @param int $project_id Project ID
     * @param string $file_id File ID
     * @return bool|WP_Error True on success, error on failure
     */
    public function delete_file($project_id, $file_id) {
        // Check permissions
        if (!Access::can_edit_project($project_id)) {
            return new \WP_Error('permission_denied', __('You do not have permission to delete files from this project.', 'chatprojects'));
        }

        // Get vector store ID to remove file reference
        $vector_store_id = get_post_meta($project_id, '_cp_vector_store_id', true);

        // Remove from vector store first (before deleting raw file)
        if (!empty($vector_store_id)) {
            $vs_result = $this->api->remove_file_from_vector_store($vector_store_id, $file_id);
            // Log error but continue - file may not be in vector store
            if (is_wp_error($vs_result)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging only when WP_DEBUG is enabled
                    error_log('ChatProjects: Failed to remove file from vector store: ' . $vs_result->get_error_message());
                }
            }
        }

        // Delete from OpenAI Files API
        $result = $this->api->delete_file($file_id);

        if (is_wp_error($result)) {
            return $result;
        }

        // Remove from metadata
        $files = get_post_meta($project_id, '_cp_files', true);
        if (is_array($files)) {
            $files = array_filter($files, function($file) use ($file_id) {
                return $file['file_id'] !== $file_id;
            });
            update_post_meta($project_id, '_cp_files', array_values($files));
        }

        return true;
    }

    /**
     * Get project files
     *
     * @param int $project_id Project ID
     * @return array|WP_Error Array of files or error
     */
    public function get_files($project_id) {
        // Check permissions
        if (!Access::can_access_project($project_id)) {
            return new \WP_Error('permission_denied', __('You do not have permission to access this project.', 'chatprojects'));
        }

        $files = get_post_meta($project_id, '_cp_files', true);

        if (!is_array($files)) {
            return array();
        }

        // Convert MySQL dates to ISO 8601 format for proper JavaScript parsing
        $wp_timezone = wp_timezone();
        foreach ($files as &$file) {
            if (!empty($file['uploaded_at'])) {
                try {
                    $date = new \DateTime($file['uploaded_at'], $wp_timezone);
                    $file['uploaded_at'] = $date->format('c');
                } catch (\Exception $e) {
                    // Keep original value if parsing fails
                }
            }
            if (!empty($file['created_at'])) {
                try {
                    $date = new \DateTime($file['created_at'], $wp_timezone);
                    $file['created_at'] = $date->format('c');
                } catch (\Exception $e) {
                    // Keep original value if parsing fails
                }
            }
        }

        return $files;
    }

    /**
     * Convert Excel file to TXT (comma-separated text format).
     *
     * @param string $excel_path Path to Excel file (.xls or .xlsx).
     * @return string|\WP_Error Path to converted TXT file or error.
     */
    private function convert_excel_to_txt($excel_path) {
        // Check if PhpSpreadsheet is available
        if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
            $vendor_path = CHATPROJECTS_PLUGIN_DIR . 'vendor/autoload.php';
            if (file_exists($vendor_path)) {
                require_once $vendor_path;
            }
        }

        if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
            return new \WP_Error('library_missing', __('PhpSpreadsheet library is not installed.', 'chatprojects'));
        }

        try {
            // Temporarily increase memory limit for large files
            $original_limit = ini_get('memory_limit');
            // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Required for large Excel file processing
            ini_set('memory_limit', '512M');

            // Load the Excel file
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($excel_path);

            // Get the first worksheet (ignore other sheets)
            $worksheet = $spreadsheet->getActiveSheet();

            // Check if worksheet is empty
            if ($worksheet->getHighestRow() < 1) {
                // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Restoring original memory limit
                ini_set('memory_limit', $original_limit);
                return new \WP_Error('empty_sheet', __('Excel file is empty.', 'chatprojects'));
            }

            // Create TXT file path (replace .xlsx/.xls with .txt)
            $txt_path = preg_replace('/\.(xlsx?|xls)$/i', '.txt', $excel_path);

            // Create CSV writer (outputs comma-separated text)
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Csv($spreadsheet);
            $writer->setDelimiter(',');           // Comma delimiter
            $writer->setEnclosure('"');           // Double-quote enclosure
            $writer->setSheetIndex(0);            // First sheet only

            // Save as TXT with CSV format (OpenAI compatible)
            $writer->save($txt_path);

            // Restore original memory limit
            // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Restoring original memory limit
            ini_set('memory_limit', $original_limit);
            return $txt_path;

        } catch (\Exception $e) {
            // Restore memory limit on error
            if (isset($original_limit)) {
                // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Restoring original memory limit
                ini_set('memory_limit', $original_limit);
            }
            /* translators: %s: Error message from Excel conversion */
            $conversion_error = __('Failed to convert Excel file: %s', 'chatprojects');
            return new \WP_Error(
                'conversion_failed',
                sprintf($conversion_error, $e->getMessage())
            );
        }
    }

    /**
     * Handle WordPress file upload
     *
     * @param int $project_id Project ID
     * @param array $file_data $_FILES array data
     * @return array|WP_Error Upload result or error
     */
    public function handle_upload($project_id, $file_data) {
        // Check permissions
        if (!Access::can_edit_project($project_id)) {
            return new \WP_Error('permission_denied', __('You do not have permission to upload files to this project.', 'chatprojects'));
        }

        // Validate upload
        if (empty($file_data['tmp_name']) || !is_uploaded_file($file_data['tmp_name'])) {
            return new \WP_Error('invalid_upload', __('Invalid file upload.', 'chatprojects'));
        }

        // Check for upload errors
        if ($file_data['error'] !== UPLOAD_ERR_OK) {
            return new \WP_Error('upload_error', __('File upload error.', 'chatprojects'));
        }

        // Sanitize filename
        $filename = Security::sanitize_filename($file_data['name']);
        $tmp_file = $file_data['tmp_name'];

        // Check if file is Excel and convert to TXT
        $original_file_path = null;
        $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($file_ext, array('xls', 'xlsx'))) {
            $original_file_path = $tmp_file; // Store original path for deletion later

            // Convert Excel to TXT
            $txt_path = $this->convert_excel_to_txt($tmp_file);

            if (is_wp_error($txt_path)) {
                wp_delete_file($tmp_file);
                return $txt_path;
            }

            // Use TXT file for upload instead of Excel
            $tmp_file = $txt_path;
            // Change filename extension to .txt
            $filename = preg_replace('/\.(xlsx?|xls)$/i', '.txt', $filename);
        }

        // Upload to vector store
        $result = $this->upload_file($project_id, $tmp_file, $filename);

        if (is_wp_error($result)) {
            wp_delete_file($tmp_file);
            if ($original_file_path && file_exists($original_file_path)) {
                wp_delete_file($original_file_path);
            }
            return $result;
        }

        // Update filename in result
        $result['filename'] = $filename;

        // Clean up temp file(s)
        wp_delete_file($tmp_file);

        // If we converted from Excel, also delete the original Excel file
        if ($original_file_path && file_exists($original_file_path)) {
            wp_delete_file($original_file_path);
        }

        return $result;
    }

    /**
     * Get file count for project
     *
     * @param int $project_id Project ID
     * @return int File count
     */
    public function get_file_count($project_id) {
        $files = get_post_meta($project_id, '_cp_files', true);
        
        if (!is_array($files)) {
            return 0;
        }

        return count($files);
    }

    /**
     * Get total files size for project
     *
     * @param int $project_id Project ID
     * @return int Total size in bytes
     */
    public function get_total_size($project_id) {
        $files = get_post_meta($project_id, '_cp_files', true);
        
        if (!is_array($files)) {
            return 0;
        }

        $total = 0;
        foreach ($files as $file) {
            if (isset($file['size'])) {
                $total += $file['size'];
            }
        }

        return $total;
    }

    /**
     * Format file size for display
     *
     * @param int $bytes Size in bytes
     * @return string Formatted size
     */
    public static function format_file_size($bytes) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Search files by filename
     *
     * @param int $project_id Project ID
     * @param string $search Search term
     * @return array Matching files
     */
    public function search_files($project_id, $search) {
        $files = $this->get_files($project_id);

        if (is_wp_error($files)) {
            return $files;
        }

        if (empty($search)) {
            return $files;
        }

        return array_filter($files, function($file) use ($search) {
            return stripos($file['filename'], $search) !== false;
        });
    }

    /**
     * Import files from WordPress Media Library
     *
     * @param int $project_id Project ID
     * @param array $attachment_ids Array of WordPress attachment IDs
     * @return array Results array with success/error for each file
     */
    public function import_from_media_library($project_id, $attachment_ids) {
        $user_id = get_current_user_id();
        $results = array();

        // Check project access
        if (!Access::can_edit_project($project_id, $user_id)) {
            return new \WP_Error('access_denied', __('You do not have permission to edit this project.', 'chatprojects'));
        }

        // Allowed document types for vector store
        $allowed_types = array('pdf', 'doc', 'docx', 'txt', 'csv', 'xls', 'xlsx', 'json', 'md', 'markdown');

        foreach ($attachment_ids as $attachment_id) {
            $attachment_id = absint($attachment_id);

            $result = array(
                'attachment_id' => $attachment_id,
                'success' => false,
            );

            // Check if user can read this attachment
            if (!current_user_can('read_post', $attachment_id)) {
                $result['error'] = __('You do not have permission to access this file.', 'chatprojects');
                $results[] = $result;
                continue;
            }

            // Get attachment data
            $attachment = get_post($attachment_id);
            if (!$attachment || $attachment->post_type !== 'attachment') {
                $result['error'] = __('Invalid attachment.', 'chatprojects');
                $results[] = $result;
                continue;
            }

            $file_path = get_attached_file($attachment_id);
            $filename = basename($file_path);
            $result['filename'] = $filename;

            // Check file exists
            if (!file_exists($file_path)) {
                $result['error'] = __('File not found on server.', 'chatprojects');
                $results[] = $result;
                continue;
            }

            // Validate file type
            $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (!in_array($file_ext, $allowed_types, true)) {
                /* translators: %s: File extension */
                $result['error'] = sprintf(__('File type .%s is not allowed for vector stores.', 'chatprojects'), esc_html($file_ext));
                $results[] = $result;
                continue;
            }

            // Check file size
            $file_size = filesize($file_path);
            if (!Security::validate_file_size($file_size)) {
                $max_size = get_option('chatprojects_max_file_size', 50);
                /* translators: %s: Maximum file size in MB */
                $result['error'] = sprintf(__('File exceeds maximum size of %s MB.', 'chatprojects'), $max_size);
                $results[] = $result;
                continue;
            }

            // Handle Excel conversion for XLS/XLSX files
            $upload_path = $file_path;
            $upload_filename = $filename;
            $temp_file = null;
            $converted_file = null;

            if (in_array($file_ext, array('xls', 'xlsx'), true)) {
                // Copy to temp location for conversion (don't modify original)
                $temp_file = wp_tempnam($filename);
                if (!copy($file_path, $temp_file)) {
                    $result['error'] = __('Failed to create temporary file.', 'chatprojects');
                    $results[] = $result;
                    continue;
                }

                $txt_path = $this->convert_excel_to_txt($temp_file);

                if (is_wp_error($txt_path)) {
                    wp_delete_file($temp_file);
                    $result['error'] = $txt_path->get_error_message();
                    $results[] = $result;
                    continue;
                }

                $upload_path = $txt_path;
                $converted_file = $txt_path;
                $upload_filename = preg_replace('/\.(xlsx?|xls)$/i', '.txt', $filename);
            }

            // Upload to vector store
            $upload_result = $this->upload_file($project_id, $upload_path, $upload_filename);

            // Clean up temp files
            if ($temp_file && file_exists($temp_file)) {
                wp_delete_file($temp_file);
            }
            if ($converted_file && file_exists($converted_file)) {
                wp_delete_file($converted_file);
            }

            if (is_wp_error($upload_result)) {
                $result['error'] = $upload_result->get_error_message();
            } else {
                $result['success'] = true;
                $result['file_data'] = $upload_result;
            }

            $results[] = $result;
        }

        return $results;
    }
}
