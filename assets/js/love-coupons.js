/**
 * Love Coupons JavaScript
 * Enhanced with modern JavaScript practices, error handling, and accessibility
 */

(function($) {
    'use strict';

    /**
     * Love Coupons Handler Class
     */
    class LoveCouponsHandler {
        constructor() {
            this.init();
        }

        /**
         * Initialize the handler
         */
        init() {
            this.bindEvents();
            this.setupAccessibility();
        }

        /**
         * Bind event listeners
         */
        bindEvents() {
            $(document).on('click', '.redeem-button', this.handleRedemption.bind(this));
            
            // Handle keyboard navigation for terms
            $(document).on('keydown', '.coupon-terms summary', this.handleTermsKeyboard.bind(this));
            
            // Handle focus management
            $(document).on('focus', '.redeem-button', this.handleButtonFocus.bind(this));

            // Create form submit
            $(document).on('submit', '#love-create-coupon-form', this.handleCreateFormSubmit.bind(this));

            // Image ratio warning
            $(document).on('change', '#coupon_hero_image', this.warnImageRatio.bind(this));
        }

        /**
         * Setup accessibility features
         */
        setupAccessibility() {
            // Add ARIA labels to buttons
            $('.redeem-button').each(function() {
                const $button = $(this);
                const couponTitle = $button.closest('.love-coupon').find('.coupon-title').text();
                $button.attr('aria-label', loveCouponsAjax.strings.redeem + ' ' + couponTitle);
            });

            // Add ARIA expanded to terms summaries
            $('.coupon-terms summary').attr('aria-expanded', 'false');
            
            // Update aria-expanded when details are toggled
            $(document).on('toggle', '.coupon-terms', function() {
                const $summary = $(this).find('summary');
                $summary.attr('aria-expanded', this.open.toString());
            });
        }

        /**
         * Handle keyboard navigation for terms
         */
        handleTermsKeyboard(event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                const $details = $(event.target).closest('details');
                $details.prop('open', !$details.prop('open')).trigger('toggle');
            }
        }

        /**
         * Handle button focus for accessibility
         */
        handleButtonFocus(event) {
            const $button = $(event.target);
            if ($button.hasClass('disabled') || $button.prop('disabled')) {
                // Move focus to next focusable element if button is disabled
                this.moveFocusToNext($button);
            }
        }

        /**
         * Move focus to next focusable element
         */
        moveFocusToNext($element) {
            const focusableElements = $(':focusable');
            const currentIndex = focusableElements.index($element);
            const nextElement = focusableElements.eq(currentIndex + 1);
            
            if (nextElement.length) {
                nextElement.focus();
            }
        }

        /**
         * Handle coupon redemption
         */
        handleRedemption(event) {
            event.preventDefault();

            const $button = $(event.currentTarget);
            const couponId = $button.data('coupon-id');

            // Validate coupon ID
            if (!couponId || !this.isValidCouponId(couponId)) {
                this.showError(loveCouponsAjax.strings.error + ' Invalid coupon ID.');
                return;
            }

            // Check if button is already disabled
            if ($button.prop('disabled') || $button.hasClass('disabled')) {
                return;
            }

            // Show confirmation dialog
            if (!this.confirmRedemption()) {
                return;
            }

            this.processRedemption($button, couponId);
        }

        /**
         * Validate coupon ID
         */
        isValidCouponId(couponId) {
            return /^\d+$/.test(couponId) && parseInt(couponId) > 0;
        }

        /**
         * Show confirmation dialog
         */
        confirmRedemption() {
            return confirm(loveCouponsAjax.strings.confirm_redeem);
        }

        /**
         * Process the actual redemption
         */
        processRedemption($button, couponId) {
            const originalText = $button.text();
            
            // Set loading state
            this.setButtonLoading($button, true);

            // Prepare AJAX data
            const ajaxData = {
                action: 'love_coupons_redeem',
                security: loveCouponsAjax.nonce,
                coupon_id: couponId
            };

            // Make AJAX request with timeout
            const xhr = $.ajax({
                url: loveCouponsAjax.ajax_url,
                type: 'POST',
                data: ajaxData,
                timeout: 30000, // 30 second timeout
                dataType: 'json'
            });

            xhr.done((response) => {
                this.handleRedemptionSuccess(response, $button);
            });

            xhr.fail((jqXHR, textStatus, errorThrown) => {
                this.handleRedemptionError(jqXHR, textStatus, errorThrown, $button, originalText);
            });

            xhr.always(() => {
                this.setButtonLoading($button, false);
            });
        }

        /**
         * Set button loading state
         */
        setButtonLoading($button, isLoading) {
            if (isLoading) {
                $button
                    .prop('disabled', true)
                    .addClass('loading disabled')
                    .text(loveCouponsAjax.strings.redeeming)
                    .attr('aria-busy', 'true');
            } else {
                $button
                    .removeClass('loading')
                    .attr('aria-busy', 'false');
            }
        }

        /**
         * Handle successful redemption
         */
        handleRedemptionSuccess(response, $button) {
            if (response.success) {
                this.animateSuccess($button);
                
                // Delay reload to show success animation
                setTimeout(() => {
                    if (this.shouldReloadPage()) {
                        window.location.reload();
                    } else {
                        this.updateCouponUI($button);
                    }
                }, 1000);
                
                // Show success message
                this.showSuccess(response.data || loveCouponsAjax.strings.redeemed);
            } else {
                this.handleRedemptionError(null, 'server_error', response.data, $button);
            }
        }

        /**
         * Handle redemption errors
         */
        handleRedemptionError(jqXHR, textStatus, errorThrown, $button, originalText = null) {
            let errorMessage;

            switch (textStatus) {
                case 'timeout':
                    errorMessage = 'Request timed out. Please try again.';
                    break;
                case 'server_error':
                    errorMessage = errorThrown || 'Server error occurred.';
                    break;
                case 'abort':
                    errorMessage = 'Request was cancelled.';
                    break;
                case 'parsererror':
                    errorMessage = 'Invalid server response.';
                    break;
                default:
                    errorMessage = loveCouponsAjax.strings.ajax_failed;
            }

            this.showError(loveCouponsAjax.strings.error + ' ' + errorMessage);
            
            // Reset button state
            if (originalText) {
                $button
                    .prop('disabled', false)
                    .removeClass('disabled loading')
                    .text(originalText);
            }
        }

        /**
         * Animate success state
         */
        animateSuccess($button) {
            $button
                .removeClass('loading')
                .addClass('button-redeemed')
                .html('<span class="dashicons dashicons-yes"></span>' + loveCouponsAjax.strings.redeemed)
                .prop('disabled', true);
        }

        /**
         * Update coupon UI without page reload
         */
        updateCouponUI($button) {
            const $coupon = $button.closest('.love-coupon');
            
            $coupon
                .addClass('redeemed')
                .find('.redeem-button')
                .replaceWith('<button class="button button-redeemed" disabled><span class="dashicons dashicons-yes"></span>' + loveCouponsAjax.strings.redeemed + '</button>');

            // Announce to screen readers
            this.announceToScreenReader('Coupon redeemed successfully');
        }

        /**
         * Check if page should reload
         */
        shouldReloadPage() {
            // You can add logic here to determine if page reload is necessary
            // For now, we'll reload to ensure consistency
            return true;
        }

        /**
         * Show success message
         */
        showSuccess(message) {
            this.showNotification(message, 'success');
        }

        /**
         * Show error message
         */
        showError(message) {
            this.showNotification(message, 'error');
        }

        /**
         * Show notification
         */
        showNotification(message, type = 'info') {
            // Create notification element
            const $notification = $('<div>', {
                class: `love-coupon-notification love-coupon-notification-${type}`,
                text: message,
                role: 'alert',
                'aria-live': 'polite'
            });

            // Add styles
            $notification.css({
                position: 'fixed',
                top: '20px',
                right: '20px',
                padding: '1rem 1.5rem',
                borderRadius: '8px',
                backgroundColor: type === 'error' ? '#dc3545' : '#28a745',
                color: 'white',
                fontWeight: '600',
                zIndex: 9999,
                maxWidth: '300px',
                boxShadow: '0 4px 12px rgba(0,0,0,0.2)'
            });

            // Add to page
            $('body').append($notification);

            // Auto remove after 5 seconds
            setTimeout(() => {
                $notification.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);

            // Allow manual dismissal
            $notification.on('click', function() {
                $(this).fadeOut(300, function() {
                    $(this).remove();
                });
            });
        }

        /**
         * Announce message to screen readers
         */
        announceToScreenReader(message) {
            const $announcement = $('<div>', {
                'aria-live': 'polite',
                'aria-atomic': 'true',
                class: 'sr-only',
                text: message
            }).css({
                position: 'absolute',
                left: '-10000px',
                width: '1px',
                height: '1px',
                overflow: 'hidden'
            });

            $('body').append($announcement);

            setTimeout(() => {
                $announcement.remove();
            }, 1000);
        }

        /**
         * Utility method to log errors for debugging
         */
        logError(error, context = '') {
            if (console && console.error) {
                console.error('Love Coupons Error' + (context ? ' (' + context + ')' : '') + ':', error);
            }
        }

        /**
         * Handle create form submit
         */
        handleCreateFormSubmit(event) {
            event.preventDefault();
            const $form = $(event.currentTarget);

            const title = $form.find('#coupon_title').val().trim();
            const recipient = $form.find('#coupon_recipient').val();
            if (!title) {
                this.showError(loveCouponsAjax.strings.error + ' Title is required.');
                return;
            }
            if (!recipient) {
                this.showError(loveCouponsAjax.strings.error + ' Please select a recipient.');
                return;
            }

            const formData = new FormData($form[0]);
            formData.append('action', 'love_coupons_create');
            // Include the nonce under key expected by PHP handler
            const nonceVal = $form.find('input[name="love_create_coupon_nonce"]').val();
            formData.append('nonce', nonceVal);

            this.processCreateCoupon($form, formData);
        }

        /**
         * Submit create coupon AJAX
         */
        processCreateCoupon($form, formData) {
            const $submit = $form.find('button[type="submit"]');
            const $message = $form.find('.form-message');

            // Loading state
            this.setFormLoading($submit, true);
            $message.hide().removeClass('success error').text('');

            $.ajax({
                url: loveCouponsAjax.ajax_url,
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                dataType: 'json',
                timeout: 60000
            })
            .done((response) => this.handleCreateSuccess(response, $form))
            .fail((jqXHR, textStatus, errorThrown) => this.handleCreateError(jqXHR, textStatus, errorThrown, $form))
            .always(() => this.setFormLoading($submit, false));
        }

        setFormLoading($button, isLoading) {
            if (isLoading) {
                $button.prop('disabled', true).addClass('loading disabled').text(loveCouponsAjax.strings.creating);
            } else {
                $button.prop('disabled', false).removeClass('loading disabled').text('Create Coupon');
            }
        }

        handleCreateSuccess(response, $form) {
            const $message = $form.find('.form-message');
            if (response && response.success) {
                $message.addClass('success').removeClass('error').text(loveCouponsAjax.strings.created).show();
                // Reload to show new coupon in lists
                setTimeout(() => window.location.reload(), 800);
            } else {
                const msg = (response && response.data) ? response.data : loveCouponsAjax.strings.create_failed;
                $message.addClass('error').removeClass('success').text(msg).show();
            }
        }

        handleCreateError(jqXHR, textStatus, errorThrown, $form) {
            const $message = $form.find('.form-message');
            let msg = loveCouponsAjax.strings.create_failed;
            if (textStatus === 'timeout') msg = 'Request timed out. Please try again.';
            else if (errorThrown) msg = errorThrown;
            $message.addClass('error').removeClass('success').text(msg).show();
        }

        /**
         * Warn if image ratio differs from 16:9
         */
        warnImageRatio(event) {
            const file = event.target.files && event.target.files[0];
            const $note = $('#coupon_hero_image_note');
            $note.hide().text('');
            if (!file || !file.type.startsWith('image/')) return;
            const img = new Image();
            img.onload = () => {
                const ratio = img.width / img.height;
                const target = 16 / 9;
                if (Math.abs(ratio - target) > 0.02) {
                    $note.text(loveCouponsAjax.strings.image_ratio_warn).show();
                }
            };
            const reader = new FileReader();
            reader.onload = e => { img.src = e.target.result; };
            reader.readAsDataURL(file);
        }
    }

    /**
     * Initialize when DOM is ready
     */
    $(document).ready(function() {
        // Check if our AJAX object exists
        if (typeof loveCouponsAjax === 'undefined') {
            console.warn('Love Coupons: AJAX configuration not found');
            return;
        }

        // Initialize the handler
        new LoveCouponsHandler();

        // Add loading indicator for page reloads
        $(window).on('beforeunload', function() {
            $('.redeem-button.loading').each(function() {
                $(this).text('Loading...');
            });
        });
    });

    /**
     * Expose handler to global scope for debugging (development only)
     */
    if (window.location.hostname === 'localhost' || window.location.hostname.includes('dev')) {
        window.LoveCouponsHandler = LoveCouponsHandler;
    }

})(jQuery);
