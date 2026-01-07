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
            $(document).on('submit', '#love-create-coupon-form', this.handleCreateCoupon.bind(this));
            
            // Handle keyboard navigation for terms
            $(document).on('keydown', '.coupon-terms summary', this.handleTermsKeyboard.bind(this));
            
            // Handle focus management
            $(document).on('focus', '.redeem-button', this.handleButtonFocus.bind(this));
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
         * Handle coupon creation form submission
         */
        handleCreateCoupon(event) {
            event.preventDefault();

            const $form = $(event.currentTarget);
            const $button = $form.find('[type="submit"]');
            const $message = $form.find('.form-message');

            // Disable submit button
            $button.prop('disabled', true);
            $button.text('Creating...');

            // Prepare form data
            const formData = {
                action: 'love_coupons_create',
                nonce: $form.find('[name="love_create_coupon_nonce"]').val(),
                coupon_title: $form.find('[name="coupon_title"]').val(),
                coupon_recipient: $form.find('[name="coupon_recipient"]').val(),
                coupon_terms: $form.find('[name="coupon_terms"]').val(),
                coupon_expiry_date: $form.find('[name="coupon_expiry_date"]').val(),
                coupon_usage_limit: $form.find('[name="coupon_usage_limit"]').val(),
            };

            // Send AJAX request
            $.ajax({
                url: loveCouponsAjax.ajax_url,
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: (response) => {
                    if (response.success) {
                        // Clear form
                        $form[0].reset();

                        // Show success message
                        $message.removeClass('error').addClass('success');
                        $message.text(response.data.message || 'Coupon created successfully!');
                        $message.show();

                        // Reset button
                        $button.prop('disabled', false);
                        $button.text('Create Coupon');

                        // Hide message after 3 seconds
                        setTimeout(() => {
                            $message.fadeOut();
                        }, 3000);

                        // Optionally reload the page or refresh the posted coupons list
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    } else {
                        this.showFormError($message, response.data || 'Failed to create coupon');
                        $button.prop('disabled', false);
                        $button.text('Create Coupon');
                    }
                },
                error: (xhr, status, error) => {
                    this.showFormError($message, 'Request failed: ' + error);
                    $button.prop('disabled', false);
                    $button.text('Create Coupon');
                    this.logError(error, 'Create Coupon');
                }
            });
        }

        /**
         * Show error in form message container
         */
        showFormError($messageContainer, message) {
            $messageContainer.removeClass('success').addClass('error');
            $messageContainer.text(typeof message === 'string' ? message : 'An error occurred');
            $messageContainer.show();
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
