/**
 * Instructions Modal JavaScript
 *
 * Handles the edit assistant instructions modal functionality
 *
 * @package ChatProjects
 */

(function($) {
    'use strict';

    // Character limit for OpenAI instructions
    const CHAR_LIMIT = 256000;

    /**
     * Get AJAX configuration at call time (not load time)
     * This ensures chatprData is available when the function is called
     */
    function getAjaxConfig() {
        const ajaxUrl = (typeof chatprData !== 'undefined') ? chatprData.ajax_url :
                        (typeof chatprAjax !== 'undefined') ? chatprAjax.ajaxUrl : '';
        const nonce = (typeof chatprData !== 'undefined') ? chatprData.nonce :
                      (typeof chatprAjax !== 'undefined') ? chatprAjax.nonce : '';
        return { ajaxUrl, nonce };
    }

    // Cache DOM elements
    let $modal, $textarea, $charCount, $saveBtn, $cancelBtn;

    /**
     * Initialize the instructions modal
     */
    function init() {
        // Cache elements
        $modal = $('#vp-edit-instructions-modal');
        $textarea = $('#vp-instructions-textarea');
        $charCount = $('#vp-char-count');
        $saveBtn = $('#vp-save-instructions');
        $cancelBtn = $('#vp-cancel-instructions');

        if (!$modal.length) {
            return;
        }

        // Bind events
        bindEvents();
    }

    /**
     * Bind all event handlers
     */
    function bindEvents() {
        // Open modal from sidebar button
        $(document).on('click', '#vp-edit-instructions-btn', function(e) {
            e.preventDefault();
            openModal();
        });

        // Open modal from welcome area link
        $(document).on('click', '#vp-edit-instructions-link', function(e) {
            e.preventDefault();
            openModal();
        });

        // Close modal
        $modal.find('.vp-modal-close, .vp-modal-overlay').on('click', function(e) {
            if ($(e.target).is('.vp-modal-overlay') || $(e.target).is('.vp-modal-close') || $(e.target).closest('.vp-modal-close').length) {
                closeModal();
            }
        });

        // Cancel button
        $cancelBtn.on('click', function(e) {
            e.preventDefault();
            closeModal();
        });

        // Save button
        $saveBtn.on('click', function(e) {
            e.preventDefault();
            saveInstructions();
        });

        // Character count on input
        $textarea.on('input', function() {
            updateCharCount();
        });

        // Template selection disabled in Free version (Pro feature)

        // Escape key closes modal
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && !$modal.hasClass('vp-hidden')) {
                closeModal();
            }
        });

        // Prevent modal dialog clicks from closing
        $modal.find('.vp-modal-dialog').on('click', function(e) {
            e.stopPropagation();
        });
    }

    /**
     * Open the instructions modal
     */
    function openModal() {
        // Load current instructions from hidden input
        const currentInstructions = $('#vp-current-instructions').val() || '';
        $textarea.val(currentInstructions);
        updateCharCount();

        // Show modal (supports both 'hidden' and 'vp-hidden' classes)
        $modal.removeClass('vp-hidden hidden');

        // Focus textarea after animation
        setTimeout(function() {
            $textarea.focus();
        }, 100);
    }

    /**
     * Close the instructions modal
     */
    function closeModal() {
        // Hide modal (supports both 'hidden' and 'vp-hidden' classes)
        $modal.addClass('hidden');
    }

    /**
     * Update the character count display
     */
    function updateCharCount() {
        const count = $textarea.val().length;
        $charCount.text(count.toLocaleString());

        // Add warning class if approaching limit
        if (count > CHAR_LIMIT * 0.9) {
            $charCount.addClass('vp-char-warning');
        } else {
            $charCount.removeClass('vp-char-warning');
        }

        // Add error class if over limit
        if (count > CHAR_LIMIT) {
            $charCount.addClass('vp-char-error');
            $saveBtn.prop('disabled', true);
        } else {
            $charCount.removeClass('vp-char-error');
            $saveBtn.prop('disabled', false);
        }
    }

    // Note: Prompt template functions removed - Pro feature only

    /**
     * Save instructions via AJAX
     */
    function saveInstructions() {
        const projectId = $('#vp-current-project').val();
        const instructions = $textarea.val();
        const config = getAjaxConfig();

        // Validate AJAX configuration
        if (!config.ajaxUrl || !config.nonce) {
            showNotification('Session expired. Please refresh the page.', 'error');
            return;
        }

        // Disable save button
        $saveBtn.prop('disabled', true).addClass('vp-loading');

        $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'chatpr_update_project_instructions',
                nonce: config.nonce,
                project_id: projectId,
                instructions: instructions
            },
            success: function(response) {
                if (response.success) {
                    // Update hidden input with new instructions
                    $('#vp-current-instructions').val(instructions);

                    // Update welcome area preview
                    if (response.data.preview) {
                        $('.vp-instructions-text').text(response.data.preview);
                    }

                    // Show/hide the instructions preview section
                    if (instructions.trim()) {
                        $('.vp-instructions-preview').show();
                        $('.vp-welcome-no-instructions').hide();
                    } else {
                        $('.vp-instructions-preview').hide();
                        $('.vp-welcome-no-instructions').show();
                    }

                    // Close modal
                    closeModal();

                    // Optional: Show success message
                    showNotification('Instructions updated successfully', 'success');
                } else {
                    showNotification(response.data.message || 'Failed to save instructions', 'error');
                }
            },
            error: function(xhr, status, error) {
                showNotification('An error occurred. Please try again.', 'error');
            },
            complete: function() {
                $saveBtn.prop('disabled', false).removeClass('vp-loading');
            }
        });
    }

    /**
     * Show a notification message
     */
    function showNotification(message, type) {
        // Check if there's an existing notification system
        if (typeof window.vpShowNotification === 'function') {
            window.vpShowNotification(message, type);
            return;
        }

        // Fallback: simple notification
        const $notification = $('<div class="vp-notification vp-notification-' + type + '">' + message + '</div>');
        $('body').append($notification);

        // Auto-hide after 3 seconds
        setTimeout(function() {
            $notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }

    // Initialize when DOM is ready
    $(document).ready(init);

})(jQuery);
