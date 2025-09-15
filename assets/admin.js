/**
 * Minpaku Suite Admin JavaScript
 *
 * @package MinpakuSuite
 */

(function($) {
    'use strict';

    const MCSAdmin = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Copy URL buttons
            $(document).on('click', '.mcs-copy-btn', this.copyUrl);

            // Regenerate mappings button
            $('#mcs-regen-btn').on('click', this.regenerateMappings);

            // Sync now button
            $('#mcs-sync-btn').on('click', this.syncNow);
        },

        copyUrl: function(e) {
            e.preventDefault();

            const $btn = $(this);
            const url = $btn.data('url');
            const originalText = $btn.text();

            if (!url) {
                alert(mcsAdmin.strings.copyFailed);
                return;
            }

            $btn.text(mcsAdmin.strings.copying).prop('disabled', true);

            // Use the modern Clipboard API if available
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(url).then(function() {
                    MCSAdmin.showCopySuccess($btn, originalText);
                }).catch(function() {
                    MCSAdmin.fallbackCopy(url, $btn, originalText);
                });
            } else {
                MCSAdmin.fallbackCopy(url, $btn, originalText);
            }
        },

        fallbackCopy: function(text, $btn, originalText) {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();

            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    MCSAdmin.showCopySuccess($btn, originalText);
                } else {
                    MCSAdmin.showCopyError($btn, originalText);
                }
            } catch (err) {
                MCSAdmin.showCopyError($btn, originalText);
            }

            document.body.removeChild(textArea);
        },

        showCopySuccess: function($btn, originalText) {
            $btn.text(mcsAdmin.strings.copied).removeClass('button-secondary').addClass('button-primary');

            setTimeout(function() {
                $btn.text(originalText).removeClass('button-primary').addClass('button-secondary').prop('disabled', false);
            }, 2000);
        },

        showCopyError: function($btn, originalText) {
            $btn.text(mcsAdmin.strings.copyFailed).removeClass('button-secondary').addClass('button-danger');

            setTimeout(function() {
                $btn.text(originalText).removeClass('button-danger').addClass('button-secondary').prop('disabled', false);
            }, 2000);
        },

        regenerateMappings: function(e) {
            e.preventDefault();

            if (!confirm(mcsAdmin.strings.confirmRegen)) {
                return;
            }

            const $btn = $(this);
            const originalText = $btn.text();

            $btn.text(mcsAdmin.strings.processing).prop('disabled', true);

            $.ajax({
                url: mcsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mcs_regenerate_mappings',
                    nonce: mcsAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        MCSAdmin.showNotice('success', response.data.message);
                        // Reload the page to show updated mappings
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        MCSAdmin.showNotice('error', response.data.message || mcsAdmin.strings.error);
                        $btn.text(originalText).prop('disabled', false);
                    }
                },
                error: function() {
                    MCSAdmin.showNotice('error', mcsAdmin.strings.error);
                    $btn.text(originalText).prop('disabled', false);
                }
            });
        },

        syncNow: function(e) {
            e.preventDefault();

            if (!confirm(mcsAdmin.strings.confirmSync)) {
                return;
            }

            const $btn = $(this);
            const originalText = $btn.text();

            $btn.text(mcsAdmin.strings.processing).prop('disabled', true);

            $.ajax({
                url: mcsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mcs_sync_now',
                    nonce: mcsAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        MCSAdmin.showNotice('success', response.data.message);
                        $btn.text(originalText).prop('disabled', false);
                    } else {
                        MCSAdmin.showNotice('error', response.data.message || mcsAdmin.strings.error);
                        $btn.text(originalText).prop('disabled', false);
                    }
                },
                error: function() {
                    MCSAdmin.showNotice('error', mcsAdmin.strings.error);
                    $btn.text(originalText).prop('disabled', false);
                }
            });
        },

        showNotice: function(type, message) {
            // Remove existing notices
            $('.mcs-notice').remove();

            const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
            const $notice = $('<div class="notice ' + noticeClass + ' is-dismissible mcs-notice"><p>' + message + '</p></div>');

            // Add dismiss button functionality
            $notice.append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>');

            // Insert notice after the page title
            $('.wrap h1').after($notice);

            // Handle dismiss button
            $notice.on('click', '.notice-dismiss', function() {
                $notice.fadeOut();
            });

            // Auto-dismiss success notices after 5 seconds
            if (type === 'success') {
                setTimeout(function() {
                    $notice.fadeOut();
                }, 5000);
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        MCSAdmin.init();
    });

})(jQuery);