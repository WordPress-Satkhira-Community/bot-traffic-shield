(function($) {
    'use strict';

    $(document).ready(function() {

        /**
         * Tab Navigation
         */
        function initTabs() {
            var $tabs = $('.nav-tab-wrapper .nav-tab');
            var $contents = $('.btsld-tab-content');

            // Hide all content except first
            $contents.hide();
            $contents.first().show();
            $tabs.first().addClass('nav-tab-active');

            // Handle hash on page load
            var hash = window.location.hash;
            if (hash) {
                var $targetTab = $tabs.filter('[href="' + hash + '"]');
                if ($targetTab.length) {
                    $tabs.removeClass('nav-tab-active');
                    $targetTab.addClass('nav-tab-active');
                    $contents.hide();
                    $(hash).show();
                }
            }

            // Tab click handler
            $tabs.on('click', function(e) {
                e.preventDefault();
                
                var targetId = $(this).attr('href');
                
                // Update active tab
                $tabs.removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                // Show target content
                $contents.hide();
                $(targetId).show();
                
                // Update URL hash without scrolling
                if (history.pushState) {
                    history.pushState(null, null, targetId);
                } else {
                    window.location.hash = targetId;
                }
            });
        }

        /**
         * Clear Logs Button Handler
         */
        function initClearLogs() {
            $('#btsld-clear-logs-btn').on('click', function(e) {
                e.preventDefault();
                
                var $button = $(this);
                var $message = $('#btsld-clear-logs-message');
                var originalText = $button.text();
                
                // Confirm action
                if (!confirm(btsld_admin.confirm_clear)) {
                    return;
                }
                
                // Disable button and show loading state
                $button.prop('disabled', true).text(btsld_admin.clearing);
                $message.removeClass('btsld-success btsld-error').text('');
                
                // Send AJAX request
                $.ajax({
                    url: btsld_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'btsld_clear_logs',
                        nonce: btsld_admin.clear_logs_nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // Update the counter display
                            $('#btsld-blocked-count').text('0');
                            
                            // Clear the table and pagination
                            $('.btsld-log-table').remove();
                            $('.btsld-pagination').remove();
                            
                            // Show success message
                            $message.addClass('btsld-success').text(response.data.message);
                            
                            // Add empty state message if not exists
                            if ($('#btsld-empty-log-message').length === 0) {
                                $('#btsld-log-card h2').after(
                                    '<p id="btsld-empty-log-message">' + btsld_admin.empty_log_msg + '</p>'
                                );
                            }
                        } else {
                            $message.addClass('btsld-error').text(response.data.message);
                        }
                    },
                    error: function() {
                        $message.addClass('btsld-error').text(btsld_admin.error_msg);
                    },
                    complete: function() {
                        // Re-enable button
                        $button.prop('disabled', false).text(btsld_admin.clear_logs);
                    }
                });
            });
        }

        // Initialize all functions
        initTabs();
        initClearLogs();

    });

})(jQuery);