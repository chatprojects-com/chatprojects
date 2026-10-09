/**
 * ChatProjects - Auto-RAG metabox (project edit screen).
 *
 * Settings come from window.chatprAutorag (printed before this script).
 */
jQuery(document).ready(function($) {
    var config = window.chatprAutorag || {};
    var projectId = config.projectId;
    var nonce = config.nonce;
    var ajaxUrl = config.ajaxUrl;
    var strings = config.strings || {};
    var pollInterval = null;

    function startIndexing() {
        $.post(ajaxUrl, {
            action: 'chatpr_start_indexing',
            nonce: nonce,
            project_id: projectId
        }, function(response) {
            if (response.success) {
                $('#chatpr-autorag-start').prop('disabled', true);
                $('#chatpr-autorag-cancel').show();
                $('#chatpr-autorag-progress').show();
                $('#chatpr-autorag-errors').hide();
                startPolling();
            } else {
                alert(response.data.message);
            }
        });
    }

    function startPolling() {
        if (pollInterval) clearInterval(pollInterval);
        pollInterval = setInterval(pollProgress, 2000);
    }

    function pollProgress() {
        $.post(ajaxUrl, {
            action: 'chatpr_get_index_progress',
            nonce: nonce,
            project_id: projectId
        }, function(response) {
            if (!response.success) {
                stopPolling();
                return;
            }
            var job = response.data;
            var pct = job.total > 0 ? Math.round((job.processed / job.total) * 100) : 0;
            $('#chatpr-autorag-bar').css('width', pct + '%');
            $('#chatpr-autorag-progress-text').text(
                strings.indexing + ' ' + job.processed + ' / ' + job.total +
                ' (' + job.indexed + ' indexed, ' + job.skipped + ' skipped, ' + job.failed + ' failed)'
            );

            if (job.status === 'completed' || job.status === 'cancelled') {
                stopPolling();
                $('#chatpr-autorag-start').prop('disabled', false);
                $('#chatpr-autorag-cancel').hide();
                if (job.status === 'completed') {
                    $('#chatpr-autorag-progress-text').text(strings.complete);
                } else {
                    $('#chatpr-autorag-progress-text').text(strings.cancelled);
                }
                refreshStatus();

                if (job.errors && job.errors.length > 0) {
                    var list = $('#chatpr-autorag-error-list').empty();
                    $.each(job.errors, function(i, err) {
                        list.append($('<li>').text(err.title + ': ' + err.error));
                    });
                    $('#chatpr-autorag-errors').show();
                }
            }
        });
    }

    function stopPolling() {
        if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
    }

    function refreshStatus() {
        $.post(ajaxUrl, {
            action: 'chatpr_get_index_status',
            nonce: nonce,
            project_id: projectId
        }, function(response) {
            if (response.success) {
                $('#chatpr-autorag-indexed').text(response.data.indexed);
                $('#chatpr-autorag-available').text(response.data.available);
                $('#chatpr-autorag-clear').prop('disabled', response.data.total < 1);
            }
        });
    }

    $('#chatpr-autorag-start').on('click', startIndexing);

    $('#chatpr-autorag-cancel').on('click', function() {
        $.post(ajaxUrl, {
            action: 'chatpr_cancel_indexing',
            nonce: nonce,
            project_id: projectId
        });
    });

    $('#chatpr-autorag-clear').on('click', function() {
        if (!confirm(strings.confirmClear)) return;
        var btn = $(this);
        btn.prop('disabled', true);
        $.post(ajaxUrl, {
            action: 'chatpr_clear_index',
            nonce: nonce,
            project_id: projectId
        }, function(response) {
            if (response.success) {
                refreshStatus();
                $('#chatpr-autorag-progress').hide();
                $('#chatpr-autorag-errors').hide();
            }
            btn.prop('disabled', false);
        });
    });

    // If job was already running when page loaded, resume polling.
    if ($('#chatpr-autorag-cancel').is(':visible')) {
        startPolling();
    }
});
