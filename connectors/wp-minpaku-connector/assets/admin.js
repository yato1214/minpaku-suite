/**
 * Minpaku Connector Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Initialize admin functionality
        initConnectionTest();
        initFormValidation();
    });

    /**
     * Initialize connection test functionality
     */
    function initConnectionTest() {
        $('#test-connection').on('click', function() {
            var $button = $(this);
            var $result = $('#test-result');

            // Disable button and show loading state
            $button.prop('disabled', true).text(wmcAdmin.strings.testing);
            $result.removeClass('success error').text('');

            // Make AJAX request
            $.post(wmcAdmin.ajaxUrl, {
                action: 'wmc_test_connection',
                nonce: wmcAdmin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    $result.addClass('success').html('✓ ' + wmcAdmin.strings.success);
                    if (response.data && response.data.message) {
                        $result.append('<br><small>' + escapeHtml(response.data.message) + '</small>');
                    }
                } else {
                    var message = response.data && response.data.message ? response.data.message : wmcAdmin.strings.error;
                    $result.addClass('error').html('✗ ' + escapeHtml(message));
                }
            })
            .fail(function(xhr, status, error) {
                console.error('Connection test failed:', error);
                $result.addClass('error').html('✗ ' + wmcAdmin.strings.error);
            })
            .always(function() {
                // Re-enable button
                $button.prop('disabled', false).text($button.data('original-text') || 'Test Connection');
            });
        });

        // Store original button text
        var $testButton = $('#test-connection');
        if ($testButton.length) {
            $testButton.data('original-text', $testButton.text());
        }
    }

    /**
     * Initialize form validation
     */
    function initFormValidation() {
        var $form = $('.wrap form');
        if (!$form.length) return;

        $form.on('submit', function(e) {
            var isValid = true;
            var errors = [];

            // Validate required fields
            var requiredFields = {
                'wp_minpaku_connector_settings[portal_url]': 'Portal Base URL',
                'wp_minpaku_connector_settings[site_id]': 'Site ID',
                'wp_minpaku_connector_settings[api_key]': 'API Key',
                'wp_minpaku_connector_settings[secret]': 'Secret'
            };

            $.each(requiredFields, function(name, label) {
                var $field = $form.find('[name="' + name + '"]');
                var value = $field.val().trim();

                if (!value) {
                    isValid = false;
                    errors.push(label + ' is required.');
                    $field.addClass('error-field');
                } else {
                    $field.removeClass('error-field');
                }
            });

            // Validate URL format
            var $urlField = $form.find('[name="wp_minpaku_connector_settings[portal_url]"]');
            var urlValue = $urlField.val().trim();
            if (urlValue && !isValidUrl(urlValue)) {
                isValid = false;
                errors.push('Portal Base URL must be a valid URL.');
                $urlField.addClass('error-field');
            }

            // Validate API key format
            var $apiKeyField = $form.find('[name="wp_minpaku_connector_settings[api_key]"]');
            var apiKeyValue = $apiKeyField.val().trim();
            if (apiKeyValue && !apiKeyValue.match(/^mcs_[a-zA-Z0-9]{32}$/)) {
                isValid = false;
                errors.push('API Key format appears to be invalid.');
                $apiKeyField.addClass('error-field');
            }

            if (!isValid) {
                e.preventDefault();
                showErrors(errors);
            }
        });

        // Clear error styling on field focus
        $('.wrap input').on('focus', function() {
            $(this).removeClass('error-field');
        });
    }

    /**
     * Show validation errors
     */
    function showErrors(errors) {
        // Remove existing error notices
        $('.wrap .validation-errors').remove();

        if (errors.length === 0) return;

        var $notice = $('<div class="notice notice-error validation-errors"><p><strong>Please correct the following errors:</strong></p><ul></ul></div>');
        var $list = $notice.find('ul');

        $.each(errors, function(index, error) {
            $list.append('<li>' + escapeHtml(error) + '</li>');
        });

        $('.wrap h1').after($notice);

        // Scroll to top to show errors
        $('html, body').animate({
            scrollTop: $('.wrap').offset().top - 50
        }, 300);
    }

    /**
     * Validate URL format
     */
    function isValidUrl(string) {
        try {
            new URL(string);
            return true;
        } catch (_) {
            return false;
        }
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

})(jQuery);