/**
 * ChatProjects Chat Images Module
 * Handles image upload, validation, and processing for chat
 *
 * @package ChatProjects
 */

(function() {
    'use strict';

    // Default settings
    const defaults = {
        maxSize: 10 * 1024 * 1024, // 10MB
        maxCount: -1, // unlimited
        allowedTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp']
    };

    // Module
    window.VPChatImages = {
        maxSize: defaults.maxSize,
        maxCount: defaults.maxCount,
        allowedTypes: defaults.allowedTypes,

        /**
         * Initialize with settings from PHP
         * @param {Object} settings Settings object
         */
        init: function(settings) {
            if (settings.maxImageSize) {
                this.maxSize = parseInt(settings.maxImageSize, 10);
            }
            if (settings.maxImagesPerMessage !== undefined) {
                this.maxCount = parseInt(settings.maxImagesPerMessage, 10);
            }
        },

        /**
         * Validate a single image file
         * @param {File} file File object to validate
         * @returns {Object} {valid: boolean, error: string|null}
         */
        validateImage: function(file) {
            // Check type
            if (!this.allowedTypes.includes(file.type)) {
                return {
                    valid: false,
                    error: chatprChatSettings.i18n?.invalidImageType || 'Invalid image type. Allowed: JPEG, PNG, GIF, WebP.'
                };
            }

            // Check size
            if (file.size > this.maxSize) {
                const maxMB = Math.round(this.maxSize / (1024 * 1024) * 10) / 10;
                return {
                    valid: false,
                    error: (chatprChatSettings.i18n?.imageTooLarge || 'Image exceeds maximum size of {size} MB.').replace('{size}', maxMB)
                };
            }

            return { valid: true, error: null };
        },

        /**
         * Check if more images can be added
         * @param {number} currentCount Current number of attached images
         * @param {number} addCount Number of images to add
         * @returns {boolean}
         */
        canAddImages: function(currentCount, addCount) {
            if (this.maxCount === -1) {
                return true; // Unlimited
            }
            return (currentCount + addCount) <= this.maxCount;
        },

        /**
         * Get remaining image slots
         * @param {number} currentCount Current number of attached images
         * @returns {number} Remaining slots (-1 for unlimited)
         */
        getRemainingSlots: function(currentCount) {
            if (this.maxCount === -1) {
                return -1; // Unlimited
            }
            return Math.max(0, this.maxCount - currentCount);
        },

        /**
         * Convert a file to data URL
         * @param {File} file File to convert
         * @returns {Promise<string>} Promise resolving to data URL
         */
        fileToDataUrl: function(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = () => resolve(reader.result);
                reader.onerror = () => reject(new Error('Failed to read file'));
                reader.readAsDataURL(file);
            });
        },

        /**
         * Process multiple files
         * @param {FileList|Array} files Files to process
         * @param {Array} currentImages Current attached images
         * @returns {Promise<Array>} Promise resolving to array of {name, type, size, dataUrl}
         */
        processFiles: async function(files, currentImages) {
            const results = [];
            const remaining = this.getRemainingSlots(currentImages.length);
            const filesToProcess = remaining === -1 ? Array.from(files) : Array.from(files).slice(0, remaining);

            for (const file of filesToProcess) {
                // Validate
                const validation = this.validateImage(file);
                if (!validation.valid) {
                    // Show error to user
                    if (window.VPToast) {
                        window.VPToast.error(validation.error);
                    }
                    continue;
                }

                try {
                    const dataUrl = await this.fileToDataUrl(file);
                    results.push({
                        name: file.name,
                        type: file.type,
                        size: file.size,
                        dataUrl: dataUrl
                    });
                } catch (err) {
                    // File processing failed silently
                }
            }

            return results;
        },

        /**
         * Extract images from clipboard data
         * @param {DataTransfer} clipboardData Clipboard data from paste event
         * @returns {Array<File>} Array of image files
         */
        extractImagesFromClipboard: function(clipboardData) {
            const images = [];

            if (!clipboardData || !clipboardData.items) {
                return images;
            }

            for (let i = 0; i < clipboardData.items.length; i++) {
                const item = clipboardData.items[i];

                if (item.kind === 'file' && item.type.startsWith('image/')) {
                    const file = item.getAsFile();
                    if (file) {
                        images.push(file);
                    }
                }
            }

            return images;
        },

        /**
         * Extract images from drag-drop data
         * @param {DataTransfer} dataTransfer DataTransfer from drop event
         * @returns {Array<File>} Array of image files
         */
        extractImagesFromDrop: function(dataTransfer) {
            const images = [];

            if (!dataTransfer || !dataTransfer.files) {
                return images;
            }

            for (let i = 0; i < dataTransfer.files.length; i++) {
                const file = dataTransfer.files[i];

                if (file.type.startsWith('image/')) {
                    images.push(file);
                }
            }

            return images;
        }
    };

    // Auto-initialize if settings are available
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof chatprChatSettings !== 'undefined') {
            VPChatImages.init(chatprChatSettings);
        }
    });

})();
