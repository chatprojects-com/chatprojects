/**
 * Media Library Picker Integration
 *
 * Alpine.js component for WordPress Media Library file picker integration
 *
 * @package ChatProjects
 */

(function() {
    'use strict';

    /**
     * Alpine.js component for Media Library picker
     */
    window.mediaLibraryPicker = function(projectId) {
        return {
            projectId: projectId,
            importing: false,
            importProgress: [],
            error: null,
            mediaFrame: null,

            /**
             * Open WordPress Media Library picker
             */
            openMediaPicker() {
                this.error = null;

                // Always create a fresh media frame to avoid display issues
                // The cached frame can have stale Backbone views
                if (this.mediaFrame) {
                    this.mediaFrame.off();
                    this.mediaFrame = null;
                }

                this.mediaFrame = wp.media({
                    title: chatprData.i18n?.selectFilesForVectorStore || 'Select Files for Vector Store',
                    button: {
                        text: chatprData.i18n?.importSelectedFiles || 'Import Selected Files'
                    },
                    multiple: true
                });

                // Handle selection
                this.mediaFrame.on('select', () => {
                    const selection = this.mediaFrame.state().get('selection');
                    const attachments = selection.map((attachment) => {
                        return attachment.toJSON();
                    });

                    if (attachments.length > 0) {
                        this.importFiles(attachments);
                    }
                });

                // Force proper rendering when frame opens
                // This fixes a WordPress bug where the media library doesn't render initially
                this.mediaFrame.on('open', () => {
                    const frame = this.mediaFrame;

                    // Method 1: Listen for content:render event to force collection refresh
                    frame.on('content:render', function() {
                        const content = frame.content.get();
                        if (content && content.collection) {
                            // Force refresh the collection
                            content.collection.fetch();
                        }
                    });

                    // Method 2: Tab switch fallback with longer delay
                    // This mimics what happens when user clicks Upload > Media Library
                    setTimeout(() => {
                        if (frame.content && frame.content.mode) {
                            // Switch to upload mode
                            frame.content.mode('upload');

                            // Switch back to browse mode
                            setTimeout(() => {
                                frame.content.mode('browse');

                                // Method 3: Force refresh after mode switch
                                setTimeout(() => {
                                    const browserView = frame.content.get();
                                    if (browserView && browserView.collection) {
                                        browserView.collection.fetch();
                                    }
                                }, 100);
                            }, 200);
                        }
                    }, 300);
                });

                this.mediaFrame.open();
            },

            /**
             * Import selected files
             */
            async importFiles(attachments) {
                this.importing = true;
                this.importProgress = attachments.map(att => ({
                    name: att.filename || att.title || att.name,
                    status: 'pending',
                    error: null
                }));

                const attachmentIds = attachments.map(att => att.id);

                try {
                    const formData = new FormData();
                    formData.append('action', 'chatpr_import_from_media_library');
                    formData.append('nonce', chatprData.nonce);
                    formData.append('project_id', this.projectId);
                    formData.append('attachment_ids', JSON.stringify(attachmentIds));

                    // Update progress to show importing
                    this.importProgress.forEach(p => p.status = 'importing');

                    // Use AbortController for timeout handling
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 300000); // 5 minute timeout

                    const response = await fetch(chatprData.ajax_url, {
                        method: 'POST',
                        body: formData,
                        signal: controller.signal
                    });

                    clearTimeout(timeoutId);

                    // Get response as text first to avoid hanging on malformed JSON
                    const responseText = await response.text();

                    // Try to parse JSON
                    let result;
                    try {
                        result = JSON.parse(responseText);
                    } catch (parseError) {
                        console.error('JSON parse error. Response was:', responseText.substring(0, 500));
                        throw new Error('Invalid response from server');
                    }

                    if (result.success && Array.isArray(result.data)) {
                        // Update progress based on results
                        result.data.forEach((res, index) => {
                            if (index < this.importProgress.length) {
                                this.importProgress[index].status = res.success ? 'success' : 'error';
                                this.importProgress[index].error = res.error || null;
                            }
                        });

                        // Count successes
                        const successCount = result.data.filter(r => r.success).length;

                        if (successCount > 0) {
                            // Trigger file list refresh
                            this.$dispatch('files-imported');

                            if (window.showToast) {
                                window.showToast(
                                    (chatprData.i18n?.filesImportedSuccess || '{count} file(s) imported successfully').replace('{count}', successCount),
                                    'success'
                                );
                            }
                        }

                        // Show errors if any
                        const errorCount = result.data.filter(r => !r.success).length;
                        if (errorCount > 0 && window.showToast) {
                            window.showToast(
                                (chatprData.i18n?.filesImportFailed || '{count} file(s) failed to import').replace('{count}', errorCount),
                                'error'
                            );
                        }
                    } else {
                        throw new Error(result.data?.message || 'Import failed');
                    }
                } catch (err) {
                    console.error('Media Library import error:', err);
                    // Handle timeout/abort specifically
                    if (err.name === 'AbortError') {
                        this.error = 'Import timed out. The file may be too large or the server is slow. Please try again.';
                    } else {
                        this.error = err.message || 'Failed to import files';
                    }
                    this.importProgress.forEach(p => {
                        if (p.status === 'importing' || p.status === 'pending') {
                            p.status = 'error';
                            p.error = 'Import failed';
                        }
                    });

                    if (window.showToast) {
                        window.showToast(this.error, 'error');
                    }
                } finally {
                    this.importing = false;

                    // Clear progress after delay
                    setTimeout(() => {
                        this.importProgress = [];
                        this.error = null;
                    }, 5000);
                }
            },

            /**
             * Get status icon for import progress
             */
            getStatusIcon(status) {
                switch (status) {
                    case 'pending': return '○';
                    case 'importing': return '◐';
                    case 'success': return '✓';
                    case 'error': return '✗';
                    default: return '○';
                }
            },

            /**
             * Get status color for import progress
             */
            getStatusColor(status) {
                switch (status) {
                    case 'pending': return 'text-neutral-500';
                    case 'importing': return 'text-blue-500 animate-pulse';
                    case 'success': return 'text-green-500';
                    case 'error': return 'text-red-500';
                    default: return 'text-neutral-500';
                }
            }
        };
    };
})();
