(function($) {
    'use strict';

    /**
     * Amazon Product Importer Admin JS
     */
    var AmazonImporter = {
        
        init: function() {
            this.bindEvents();
            this.initComponents();
        },

        bindEvents: function() {
            // Settings form validation
            $('#amazon-importer-settings-form').on('submit', this.validateSettingsForm);
            
            // Test API connection
            $('#test-api-connection').on('click', this.testApiConnection);
            
            // Clear logs
            $('#clear-logs').on('click', this.clearLogs);
            
            // Tab navigation
            $('.nav-tab').on('click', this.handleTabClick);
        },

        initComponents: function() {
            // Initialize tooltips
            this.initTooltips();
            
            // Initialize color picker if present
            if ($.fn.wpColorPicker) {
                $('.color-picker').wpColorPicker();
            }
        },

        initTooltips: function() {
            $('.amazon-tooltip').each(function() {
                var $this = $(this);
                var title = $this.attr('title');
                
                if (title) {
                    $this.removeAttr('title').hover(
                        function() {
                            var tooltip = $('<div class="amazon-tooltip-content">' + title + '</div>');
                            $('body').append(tooltip);
                            
                            var pos = $this.offset();
                            tooltip.css({
                                position: 'absolute',
                                top: pos.top - tooltip.outerHeight() - 10,
                                left: pos.left + ($this.outerWidth() / 2) - (tooltip.outerWidth() / 2),
                                zIndex: 9999
                            });
                        },
                        function() {
                            $('.amazon-tooltip-content').remove();
                        }
                    );
                }
            });
        },

        validateSettingsForm: function(e) {
            var $form = $(this);
            var accessKey = $form.find('input[name="amazon_importer_api_access_key_id"]').val();
            var secretKey = $form.find('input[name="amazon_importer_api_secret_access_key"]').val();
            var associateTag = $form.find('input[name="amazon_importer_api_associate_tag"]').val();
            
            if (!accessKey || !secretKey || !associateTag) {
                alert(amazon_importer_admin.strings.credentials_required);
                return false;
            }
            
            return true;
        },

        testApiConnection: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $status = $('#api-connection-status');
            
            $button.prop('disabled', true).text(amazon_importer_admin.strings.testing);
            $status.html('<span class="spinner is-active"></span>');
            
            var data = {
                action: 'test_amazon_api_connection',
                nonce: amazon_importer_ajax.nonce,
                access_key_id: $('input[name="amazon_importer_api_access_key_id"]').val(),
                secret_access_key: $('input[name="amazon_importer_api_secret_access_key"]').val(),
                associate_tag: $('input[name="amazon_importer_api_associate_tag"]').val(),
                region: $('select[name="amazon_importer_api_region"]').val()
            };

            $.post(amazon_importer_ajax.ajax_url, data)
                .done(function(response) {
                    if (response.success) {
                        $status.html('<span class="amazon-message success">' + response.data.message + '</span>');
                    } else {
                        $status.html('<span class="amazon-message error">' + response.data.message + '</span>');
                    }
                })
                .fail(function() {
                    $status.html('<span class="amazon-message error">' + amazon_importer_admin.strings.connection_error + '</span>');
                })
                .always(function() {
                    $button.prop('disabled', false).text(amazon_importer_admin.strings.test_connection);
                });
        },

        clearLogs: function(e) {
            e.preventDefault();
            
            if (!confirm(amazon_importer_admin.strings.confirm_clear_logs)) {
                return;
            }
            
            var $button = $(this);
            $button.prop('disabled', true);
            
            var data = {
                action: 'clear_amazon_logs',
                nonce: amazon_importer_ajax.nonce
            };

            $.post(amazon_importer_ajax.ajax_url, data)
                .done(function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(amazon_importer_admin.strings.clear_logs_error);
                    }
                })
                .fail(function() {
                    alert(amazon_importer_admin.strings.clear_logs_error);
                })
                .always(function() {
                    $button.prop('disabled', false);
                });
        },

        handleTabClick: function(e) {
            e.preventDefault();
            
            var $tab = $(this);
            var target = $tab.attr('href');
            
            // Update active tab
            $('.nav-tab').removeClass('nav-tab-active');
            $tab.addClass('nav-tab-active');
            
            // Show/hide tab content
            $('.amazon-tab-content').hide();
            $(target).show();
            
            // Update URL hash
            if (history.pushState) {
                history.pushState(null, null, target);
            }
        },

        showMessage: function(message, type) {
            type = type || 'info';
            
            var $message = $('<div class="amazon-message ' + type + '">' + message + '</div>');
            
            $('.amazon-importer-content').prepend($message);
            
            setTimeout(function() {
                $message.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        AmazonImporter.init();
        
        // Handle initial tab from URL hash
        if (window.location.hash) {
            $('.nav-tab[href="' + window.location.hash + '"]').click();
        }
    });

})(jQuery);