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
            this.syncPreferencesUI();
            this.ensureAccentContrast();
            this.handleCouponFromNotification();
            
            // Check validation on page load for create form
            if ($('#love-create-coupon-form').length) {
                this.checkFormValidation();
            }
        }

        /**
         * Bind event listeners
         */
        bindEvents() {
            $(document).on('click', '.redeem-button', this.handleRedemption.bind(this));
            $(document).on('click', '.edit-coupon', this.handleEditCoupon.bind(this));
            $(document).on('click', '.delete-coupon', this.handleDeleteCoupon.bind(this));
            
            // Handle keyboard navigation for terms
            $(document).on('keydown', '.coupon-terms summary', this.handleTermsKeyboard.bind(this));
            
            // Handle focus management
            $(document).on('focus', '.redeem-button', this.handleButtonFocus.bind(this));

            // Create form submit
            $(document).on('submit', '#love-create-coupon-form', this.handleCreateFormSubmit.bind(this));

            // Preferences form
            $(document).on('submit', '#love-coupons-preferences-form', this.handlePreferencesSubmit.bind(this));
            $(document).on('submit', '#love-coupons-appearance-form', this.handleAppearanceSubmit.bind(this));

            // Image ratio warning
            $(document).on('change', '#coupon_hero_image', this.warnImageRatio.bind(this));

            // Scheduling toggle
            $(document).on('change', 'input[name="coupon_schedule_option"]', this.toggleScheduleDate.bind(this));

            // Dropzone interactions for image upload
            $(document).on('click', '#coupon_image_dropzone', this.handleDropzoneClick.bind(this));
            $(document).on('dragover dragenter', '#coupon_image_dropzone', this.handleDropzoneDrag.bind(this));
            $(document).on('dragleave dragend', '#coupon_image_dropzone', this.handleDropzoneDragLeave.bind(this));
            $(document).on('drop', '#coupon_image_dropzone', this.handleDropzoneDrop.bind(this));

            // Cropper modal buttons
            $(document).on('click', '#love-cropper-apply', this.applyCrop.bind(this));
            $(document).on('click', '#love-cropper-cancel, #love-cropper-modal [data-dismiss]', this.dismissCropper.bind(this));

            // Remove image button
            $(document).on('click', '#love-remove-image', this.removeImage.bind(this));

            // Feedback modal
            $(document).on('click', '#love-feedback-btn', this.openFeedbackModal.bind(this));
            $(document).on('click', '#love-feedback-modal [data-dismiss]', this.closeFeedbackModal.bind(this));
            $(document).on('click', '#love-feedback-submit', this.submitFeedback.bind(this));

            // Tabs in combined coupons view
            $(document).on('click', '.love-tab-button', this.handleTabClick.bind(this));

            // Validation indicators for create coupon form
            $(document).on('input change', '#love-create-coupon-form input, #love-create-coupon-form textarea', this.checkFormValidation.bind(this));
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

            // Add ARIA labels to delete buttons
            $('.delete-coupon').each(function() {
                const $button = $(this);
                const couponTitle = $button.closest('.love-coupon').find('.coupon-title').text();
                $button.attr('aria-label', loveCouponsAjax.strings.delete + ' ' + couponTitle);
            });

            // Add ARIA labels to edit buttons
            $('.edit-coupon').each(function() {
                const $button = $(this);
                const couponTitle = $button.closest('.love-coupon').find('.coupon-title').text();
                $button.attr('aria-label', 'Edit ' + couponTitle);
            });

            // Add ARIA expanded to terms summaries
            $('.coupon-terms summary').attr('aria-expanded', 'false');
            
            // Update aria-expanded when details are toggled
            $(document).on('toggle', '.coupon-terms', function() {
                const $summary = $(this).find('summary');
                $summary.attr('aria-expanded', this.open.toString());
            });

            // Initialize tab aria state
            $('.love-coupons-tabs-wrapper').each(function() {
                const $wrapper = $(this);
                const $buttons = $wrapper.find('.love-tab-button');
                const $panes = $wrapper.find('.love-tab-pane');
                $buttons.attr('aria-selected', 'false');
                $panes.attr('aria-hidden', 'true');
                const $activeBtn = $buttons.filter('.active').first();
                const $initialBtn = $activeBtn.length ? $activeBtn : $buttons.first();
                $buttons.removeClass('active');
                $initialBtn.addClass('active').attr('aria-selected', 'true');
                $panes.removeClass('active').attr('aria-hidden', 'true');
                const paneId = $initialBtn.data('target');
                if (paneId) {
                    $wrapper.find('#' + paneId).addClass('active').attr('aria-hidden', 'false');
                }
            });
        }

        handleTabClick(event) {
            event.preventDefault();
            const $btn = $(event.currentTarget);
            const targetId = $btn.data('target');
            const $wrapper = $btn.closest('.love-coupons-tabs-wrapper');
            if (!targetId || !$wrapper.length) return;

            const $buttons = $wrapper.find('.love-tab-button');
            const $panes = $wrapper.find('.love-tab-pane');

            $buttons.removeClass('active').attr('aria-selected', 'false');
            $btn.addClass('active').attr('aria-selected', 'true');

            $panes.removeClass('active').attr('aria-hidden', 'true');
            const $targetPane = $wrapper.find('#' + targetId);
            if ($targetPane.length) {
                $targetPane.addClass('active').attr('aria-hidden', 'false');
            }

            // Check validation after switching tabs
            if ($wrapper.find('#love-create-coupon-form').length) {
                this.checkFormValidation();
            }
        }

        /**
         * Check form validation and update tab error indicators
         */
        checkFormValidation() {
            const $form = $('#love-create-coupon-form');
            if (!$form.length) return;

            // Check Basic Info tab
            const titleValid = $form.find('#coupon_title').val().trim() !== '';
            const termsValid = $form.find('#coupon_terms').val().trim() !== '';
            const basicTabValid = titleValid && termsValid;

            // Check Image tab
            const fileInput = $form.find('#coupon_hero_image')[0];
            const hasFile = fileInput && fileInput.files && fileInput.files.length > 0;
            const imageTabValid = hasFile || this.croppedBlob;

            // Check Time tab
            const expiryValid = $form.find('#coupon_expiry_date').val() !== '';
            const scheduleChoice = $form.find('input[name="coupon_schedule_option"]:checked').val();
            const startDateValid = scheduleChoice !== 'schedule' || $form.find('#coupon_start_date').val() !== '';
            const timeTabValid = expiryValid && startDateValid;

            // Update tab indicators
            const $basicTab = $form.closest('.love-coupons-tabs-wrapper').find('[data-target="love-tab-basic"]');
            const $imageTab = $form.closest('.love-coupons-tabs-wrapper').find('[data-target="love-tab-image"]');
            const $timeTab = $form.closest('.love-coupons-tabs-wrapper').find('[data-target="love-tab-time"]');

            $basicTab.toggleClass('has-error', !basicTabValid);
            $imageTab.toggleClass('has-error', !imageTabValid);
            $timeTab.toggleClass('has-error', !timeTabValid);
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

        handleEditCoupon(event) {
            event.preventDefault();
            const $button = $(event.currentTarget);
            const couponId = $button.data('coupon-id');
            if (!couponId) {
                this.showError(loveCouponsAjax.strings.error + ' Invalid coupon.');
                return;
            }

            // Fetch coupon data
            $.post(loveCouponsAjax.ajax_url, {
                action: 'love_coupons_get_coupon',
                security: loveCouponsAjax.nonce,
                coupon_id: couponId
            }).done((response) => {
                if (response && response.success && response.data) {
                    this.populateEditForm(response.data, couponId);
                    // Scroll to form or navigate to edit tab
                    const $form = $('#love-create-coupon-form');
                    if ($form.length) {
                        $('html, body').animate({ scrollTop: $form.offset().top - 100 }, 300);
                    }
                } else {
                    this.showError((response && response.data) || 'Failed to load coupon data.');
                }
            }).fail(() => {
                this.showError('Failed to load coupon data.');
            });
        }

        /**
         * Populate the create form with coupon data for editing
         */
        populateEditForm(couponData, couponId) {
            const $form = $('#love-create-coupon-form');
            if (!$form.length) return;

            // Populate basic fields
            $form.find('#coupon_title').val(couponData.title || '');
            $form.find('#coupon_terms').val(couponData.terms || '');
            $form.find('#coupon_description').val(couponData.description || '');

            // Handle recipients
            if (couponData.assigned_to && Array.isArray(couponData.assigned_to)) {
                $form.find('input[name="coupon_recipients"]').each(function() {
                    const $checkbox = $(this);
                    $checkbox.prop('checked', couponData.assigned_to.includes(parseInt($checkbox.val())));
                });
            }

            // Handle dates
            if (couponData.start_date) {
                $form.find('#coupon_start_date').val(couponData.start_date);
            }
            if (couponData.expiry_date) {
                $form.find('#coupon_expiry_date').val(couponData.expiry_date);
            }

            // Handle usage limit
            if (couponData.usage_limit) {
                $form.find('#coupon_usage_limit').val(couponData.usage_limit);
            }

            // Store coupon ID for update
            $form.data('editing-coupon-id', couponId);

            // Update submit button text
            const $submitBtn = $form.find('button[type="submit"]');
            const originalText = loveCouponsAjax.strings.create_coupon || 'Create Coupon';
            $submitBtn.text('Update Coupon').data('original-text', originalText);

            // Mark form as in edit mode
            $form.addClass('love-form-editing');
        }

        handleDeleteCoupon(event) {
            event.preventDefault();
            const $button = $(event.currentTarget);
            const couponId = $button.data('coupon-id');
            if (!couponId) {
                this.showError(loveCouponsAjax.strings.error + ' Invalid coupon.');
                return;
            }
            if (!confirm(loveCouponsAjax.strings.delete_confirm)) {
                return;
            }
            this.setButtonLoading($button, true, loveCouponsAjax.strings.deleting);
            $.post(loveCouponsAjax.ajax_url, {
                action: 'love_coupons_delete',
                security: loveCouponsAjax.nonce,
                coupon_id: couponId
            }).done((response) => {
                if (response && response.success) {
                    this.showSuccess(response.data || loveCouponsAjax.strings.deleted);
                    const $card = $button.closest('.love-coupon');
                    $card.fadeOut(200, () => { $card.remove(); });
                } else {
                    this.showError((response && response.data) || loveCouponsAjax.strings.delete_failed);
                    this.setButtonLoading($button, false);
                }
            }).fail(() => {
                this.showError(loveCouponsAjax.strings.delete_failed);
                this.setButtonLoading($button, false);
            });
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
        setButtonLoading($button, isLoading, labelOverride = null) {
            if (isLoading) {
                const label = labelOverride || loveCouponsAjax.strings.redeeming;
                $button
                    .prop('disabled', true)
                    .addClass('loading disabled')
                    .text(label)
                    .attr('aria-busy', 'true');
            } else {
                $button
                    .prop('disabled', false)
                    .removeClass('loading disabled')
                    .attr('aria-busy', 'false');
            }
        }

        /**
         * Handle successful redemption
         */
        handleRedemptionSuccess(response, $button) {
            if (response.success) {
                const payload = response.data;
                let message = loveCouponsAjax.strings.redeemed;
                let remaining = null;
                let limit = null;

                if (payload && typeof payload === 'object') {
                    message = payload.message || message;
                    if (typeof payload.remaining !== 'undefined') { remaining = payload.remaining; }
                    if (typeof payload.limit !== 'undefined') { limit = payload.limit; }
                } else if (payload) {
                    message = payload;
                }

                const fullyRedeemed = limit > 0 ? (remaining !== null ? remaining <= 0 : false) : false;

                this.animateSuccess($button, fullyRedeemed, remaining, limit);
                this.showSuccess(message);
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
        animateSuccess($button, fullyRedeemed, remaining, limit) {
            const $coupon = $button.closest('.love-coupon');

            $button.removeClass('loading');

            if (fullyRedeemed) {
                $button
                    .addClass('button-redeemed')
                    .html('<span class="dashicons dashicons-yes"></span>' + loveCouponsAjax.strings.redeemed)
                    .prop('disabled', true);
                if ($coupon.length) { $coupon.addClass('redeemed'); }
            } else {
                const remainingLabel = (limit && remaining !== null) ? ` (${remaining} left)` : '';
                $button
                    .removeClass('button-redeemed disabled')
                    .addClass('button-primary')
                    .html('<span class="dashicons dashicons-tickets-alt"></span>' + loveCouponsAjax.strings.redeem + remainingLabel)
                    .prop('disabled', false);
                if ($coupon.length) { $coupon.removeClass('redeemed'); }
            }

            this.updateUsageUI($coupon, remaining, limit);
            this.announceToScreenReader('Coupon redeemed successfully');
        }

        /**
         * Update coupon UI usage text
         */
        updateUsageUI($coupon, remaining, limit) {
            if (!$coupon || !$coupon.length) return;
            if (!limit || remaining === null || typeof remaining === 'undefined') return;

            const $usage = $coupon.find('.coupon-usage small');
            if ($usage.length) {
                $usage.text('Uses left: ' + remaining + ' of ' + limit);
            }
        }

        /**
         * Check if page should reload
         */
        shouldReloadPage() {
            return false;
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
            const editingCouponId = $form.data('editing-coupon-id');

            const title = $form.find('#coupon_title').val().trim();
            if (!title) {
                this.showError(loveCouponsAjax.strings.error + ' Title is required.');
                return;
            }
            const terms = $form.find('#coupon_terms').val().trim();
            if (!terms) {
                this.showError(loveCouponsAjax.strings.error + ' Terms & Conditions are required.');
                return;
            }
            const expiry = $form.find('#coupon_expiry_date').val();
            if (!expiry) {
                this.showError(loveCouponsAjax.strings.error + ' Valid until date is required.');
                return;
            }

            // Only require image for new coupons, not for edits
            const fileInput = $form.find('#coupon_hero_image')[0];
            const hasFile = fileInput && fileInput.files && fileInput.files.length > 0;
            if (!editingCouponId && !hasFile && !this.croppedBlob) {
                this.showError(loveCouponsAjax.strings.error + ' An image is required.');
                return;
            }

            const scheduleChoice = $form.find('input[name="coupon_schedule_option"]:checked').val();
            const startDateField = $form.find('#coupon_start_date');
            if (scheduleChoice === 'schedule') {
                if (!startDateField.val()) {
                    this.showError(loveCouponsAjax.strings.error + ' Please pick a start date.');
                    return;
                }
            } else {
                startDateField.val('');
            }

            const formData = new FormData($form[0]);
            formData.append('action', editingCouponId ? 'love_coupons_update' : 'love_coupons_create');
            // Include the nonce under key expected by PHP handler
            const nonceVal = $form.find('input[name="love_create_coupon_nonce"]').val();
            formData.append('nonce', nonceVal);

            if (editingCouponId) {
                formData.append('coupon_id', editingCouponId);
            }

            // If we have a cropped blob, use it instead of the original file
            if (this.croppedBlob) {
                formData.delete('coupon_hero_image');
                formData.append('coupon_hero_image', this.croppedBlob, 'hero-cropped.jpg');
            }

            this.processCreateCoupon($form, formData, editingCouponId);
        }

        /**
         * Submit create coupon AJAX
         */
        processCreateCoupon($form, formData, editingCouponId) {
            const $submit = $form.find('button[type="submit"]');
            const $message = $form.find('.form-message');

            // Loading state
            this.setFormLoading($submit, true, editingCouponId);
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
            .done((response) => this.handleCreateSuccess(response, $form, editingCouponId))
            .fail((jqXHR, textStatus, errorThrown) => this.handleCreateError(jqXHR, textStatus, errorThrown, $form))
            .always(() => this.setFormLoading($submit, false, editingCouponId));
        }

        setFormLoading($button, isLoading, isEditing = false) {
            if (isLoading) {
                const text = isEditing ? 'Updating...' : loveCouponsAjax.strings.creating;
                $button.prop('disabled', true).addClass('loading disabled').text(text);
            } else {
                const text = isEditing 
                    ? ($button.data('original-text') || 'Update Coupon')
                    : (loveCouponsAjax.strings.create_coupon || 'Create Coupon');
                $button.prop('disabled', false).removeClass('loading disabled').text(text);
            }
        }

        handleCreateSuccess(response, $form, editingCouponId) {
            const $message = $form.find('.form-message');
            if (response && response.success) {
                const message = editingCouponId ? 'Coupon updated successfully!' : loveCouponsAjax.strings.created;
                $message.addClass('success').removeClass('error').text(message).show();
                
                if (editingCouponId) {
                    // Clear edit mode after a short delay
                    setTimeout(() => {
                        $form.removeData('editing-coupon-id');
                        $form.removeClass('love-form-editing');
                        const $submitBtn = $form.find('button[type="submit"]');
                        $submitBtn.text(loveCouponsAjax.strings.create_coupon || 'Create Coupon').removeData('original-text');
                        $form[0].reset();
                        $message.fadeOut(300);
                    }, 2000);
                } else {
                    // Redirect to created coupons page if available, otherwise reload
                setTimeout(() => {
                    if (loveCouponsAjax.created_page_url) {
                        window.location.href = loveCouponsAjax.created_page_url;
                    } else {
                        window.location.reload();
                    }
                }, 800);
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

        syncPreferencesUI() {
            // No longer needed - removed allow-all toggle
        }

        /**
         * Handle navigation to coupon from notification click
         */
        handleCouponFromNotification() {
            const urlParams = new URLSearchParams(window.location.search);
            const couponId = urlParams.get('coupon');
            
            if (couponId) {
                // Find and scroll to the coupon card
                const $coupon = $(`.love-coupon[data-coupon-id="${couponId}"]`);
                if ($coupon.length) {
                    // Scroll to the coupon
                    $('html, body').animate({
                        scrollTop: $coupon.offset().top - 100
                    }, 500, () => {
                        // Add a highlight effect
                        $coupon.addClass('love-coupon-highlight');
                        setTimeout(() => {
                            $coupon.removeClass('love-coupon-highlight');
                        }, 3000);
                    });
                    
                    // Remove the query parameter from URL for cleaner appearance
                    if (history.replaceState) {
                        history.replaceState({}, document.title, window.location.pathname);
                    }
                }
            }
        }

        handlePreferencesSubmit(event) {
            event.preventDefault();
            const $form = $(event.currentTarget);
            const recipients = [];

            $('.love-preferences-list input[type="checkbox"]:checked').each(function() {
                recipients.push($(this).val());
            });

            if (recipients.length === 0) {
                this.showError(loveCouponsAjax.strings.error + ' Please select at least one user.');
                return;
            }

            const payload = {
                action: 'love_coupons_save_preferences',
                security: loveCouponsAjax.nonce,
                recipients
            };

            this.processPreferences($form, payload);
        }

        handleAppearanceSubmit(event) {
            event.preventDefault();
            const $form = $(event.currentTarget);
            const accentChoice = $form.find('input[name="love_accent_color"]:checked').val() || '';

            if (!accentChoice) {
                this.showError(loveCouponsAjax.strings.error + ' Please select a colour.');
                return;
            }

            const payload = {
                action: 'love_coupons_save_preferences',
                security: loveCouponsAjax.nonce,
                accent_color: accentChoice
            };

            this.processAppearance($form, payload);
        }

        processPreferences($form, payload) {
            const $button = $form.find('#love-save-preferences');
            const $message = $form.find('.form-message');

            this.setPreferencesLoading($button, true);
            $message.hide().removeClass('success error').text('');

            $.post(loveCouponsAjax.ajax_url, payload)
                .done((response) => {
                    if (response && response.success) {
                        $message.addClass('success').text(loveCouponsAjax.strings.preferences_saved).show();
                        if (response.data && Array.isArray(response.data.recipients)) {
                            this.recheckRecipients(response.data.recipients);
                        }
                    } else {
                        const msg = (response && response.data) ? response.data : loveCouponsAjax.strings.preferences_failed;
                        $message.addClass('error').text(msg).show();
                    }
                })
                .fail(() => {
                    $message.addClass('error').text(loveCouponsAjax.strings.preferences_failed).show();
                })
                .always(() => this.setPreferencesLoading($button, false));
        }

        processAppearance($form, payload) {
            const $button = $form.find('#love-save-appearance');
            const $message = $form.find('.form-message-appearance');

            this.setAppearanceLoading($button, true);
            $message.hide().removeClass('success error').text('');

            $.post(loveCouponsAjax.ajax_url, payload)
                .done((response) => {
                    if (response && response.success) {
                        $message.addClass('success').text('Appearance saved successfully!').show();
                        if (response.data && response.data.accent) {
                            this.applyAccentToWrappers(response.data.accent);
                        }
                    } else {
                        const msg = (response && response.data) ? response.data : 'Failed to save appearance.';
                        $message.addClass('error').text(msg).show();
                    }
                })
                .fail(() => {
                    $message.addClass('error').text('Failed to save appearance.').show();
                })
                .always(() => this.setAppearanceLoading($button, false));
        }

        setPreferencesLoading($button, isLoading) {
            if (isLoading) {
                $button.prop('disabled', true).addClass('loading disabled').text(loveCouponsAjax.strings.preferences_saving);
            } else {
                $button.prop('disabled', false).removeClass('loading disabled').text('Save Recipients');
            }
        }

        setAppearanceLoading($button, isLoading) {
            if (isLoading) {
                $button.prop('disabled', true).addClass('loading disabled').text('Saving...');
            } else {
                $button.prop('disabled', false).removeClass('loading disabled').text('Save Appearance');
            }
        }

        /**
         * Ensure all accent-colored elements have proper contrast on page load
         */
        ensureAccentContrast() {
            const wrappers = document.querySelectorAll('.love-coupons-wrapper');
            wrappers.forEach(wrapper => {
                const accentStyle = wrapper.style.getPropertyValue('--love-accent');
                const contrastStyle = wrapper.style.getPropertyValue('--love-accent-contrast');
                
                if (accentStyle && contrastStyle) {
                    this.applyContrastToAccentElements(wrapper, contrastStyle);
                } else {
                    // Use computed styles if not set directly
                    const computedStyle = window.getComputedStyle(wrapper);
                    const accent = computedStyle.getPropertyValue('--love-accent').trim();
                    const contrast = computedStyle.getPropertyValue('--love-accent-contrast').trim();
                    
                    if (accent) {
                        const contrastColor = contrast || this.getContrastColor(accent);
                        this.applyContrastToAccentElements(wrapper, contrastColor);
                    }
                }
            });
        }

        applyAccentToWrappers(accent) {
            if (!accent || !accent.color) return;
            const wrappers = document.querySelectorAll('.love-coupons-wrapper');
            const base = accent.color;
            const strong = this.shiftColor(base, -18);
            const soft = this.shiftColor(base, 26);
            const contrast = this.getContrastColor(base);

            wrappers.forEach(wrapper => {
                wrapper.style.setProperty('--love-accent', base);
                if (strong) wrapper.style.setProperty('--love-accent-strong', strong);
                if (soft) wrapper.style.setProperty('--love-accent-soft', soft);
                if (contrast) wrapper.style.setProperty('--love-accent-contrast', contrast);
                
                // Ensure all buttons and interactive elements use contrast color
                this.applyContrastToAccentElements(wrapper, contrast);
            });
        }

        /**
         * Apply contrast text color to all elements with accent backgrounds
         */
        applyContrastToAccentElements(container, contrastColor) {
            // Find all elements that might have accent backgrounds
            const selectors = [
                '.love-tab-button.active',
                '.button-primary',
                '.love-menu-icon-btn',
                '.love-menu-nav-item',
                '.love-modal-close',
                '[style*="--love-accent"]'
            ];

            selectors.forEach(selector => {
                const elements = container.querySelectorAll(selector);
                elements.forEach(element => {
                    const bgColor = window.getComputedStyle(element).backgroundColor;
                    // Check if element has an accent-based background
                    if (element.style.background && element.style.background.includes('var(--love-accent')) {
                        this.ensureElementContrast(element, contrastColor);
                    } else if (element.classList.contains('love-tab-button') && element.classList.contains('active')) {
                        this.ensureElementContrast(element, contrastColor);
                    } else if (element.classList.contains('button-primary')) {
                        this.ensureElementContrast(element, contrastColor);
                    } else if (element.classList.contains('love-menu-icon-btn')) {
                        this.ensureElementContrast(element, contrastColor);
                    }
                });
            });
        }

        /**
         * Ensure an element and all its text content use proper contrast color
         */
        ensureElementContrast(element, contrastColor) {
            // Apply to the element itself
            element.style.color = contrastColor;

            // Apply to all text nodes and child elements
            const textNodes = this.getAllTextNodes(element);
            textNodes.forEach(node => {
                const parent = node.parentElement;
                if (parent && !parent.style.color) {
                    parent.style.color = contrastColor;
                }
            });

            // Also apply to child elements that might have color
            element.querySelectorAll('*').forEach(child => {
                const computedColor = window.getComputedStyle(child).color;
                // Only override if it's a default or neutral color
                if (!child.style.color || computedColor === 'rgb(51, 51, 51)' || computedColor === 'rgb(102, 102, 102)') {
                    child.style.color = contrastColor;
                }
            });
        }

        /**
         * Get all text nodes within an element
         */
        getAllTextNodes(element) {
            const textNodes = [];
            const walker = document.createTreeWalker(
                element,
                NodeFilter.SHOW_TEXT,
                null,
                false
            );

            let node;
            while (node = walker.nextNode()) {
                if (node.nodeValue.trim()) {
                    textNodes.push(node);
                }
            }

            return textNodes;
        }

        shiftColor(hex, percent) {
            if (!hex || typeof hex !== 'string') return '';
            let value = hex.trim();
            if (!value.startsWith('#')) value = `#${value}`;
            if (!/^#([a-fA-F0-9]{6})$/.test(value)) return '';

            const factor = (100 + percent) / 100;
            const r = Math.max(0, Math.min(255, Math.round(parseInt(value.substr(1, 2), 16) * factor)));
            const g = Math.max(0, Math.min(255, Math.round(parseInt(value.substr(3, 2), 16) * factor)));
            const b = Math.max(0, Math.min(255, Math.round(parseInt(value.substr(5, 2), 16) * factor)));

            return `#${[r, g, b].map(x => x.toString(16).padStart(2, '0')).join('')}`;
        }

        getContrastColor(hex) {
            if (!hex || typeof hex !== 'string') return '#ffffff';
            let value = hex.trim();
            if (!value.startsWith('#')) value = `#${value}`;
            if (!/^#([a-fA-F0-9]{6})$/.test(value)) return '#ffffff';

            const r = parseInt(value.substr(1, 2), 16);
            const g = parseInt(value.substr(3, 2), 16);
            const b = parseInt(value.substr(5, 2), 16);
            const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
            return luminance > 0.55 ? '#111111' : '#ffffff';
        }

        recheckRecipients(recipientIds) {
            if (!Array.isArray(recipientIds)) return;
            const ids = recipientIds.map(String);
            $('.love-preferences-list input[type="checkbox"]').each(function(){
                const checked = ids.includes($(this).val());
                $(this).prop('checked', checked);
            });
        }

        /**
         * Warn if image ratio differs from 16:9
         */
        warnImageRatio(event) {
            const file = event.target.files && event.target.files[0];
            if (!file || !file.type.startsWith('image/')) return;
            this.openCropperWithFile(file);
        }

        toggleScheduleDate(event) {
            // Use setTimeout to ensure the radio value has updated after label click
            setTimeout(() => {
                const $checkedRadio = $('input[name="coupon_schedule_option"]:checked');
                const choice = $checkedRadio.val();
                const $group = $('#schedule_date_group');
                
                if (choice === 'schedule') {
                    $group.slideDown(150);
                    $('#coupon_start_date').focus();
                } else {
                    $('#coupon_start_date').val('');
                    $group.slideUp(150);
                }
            }, 10);
        }

        handleDropzoneClick(event) {
            if ($(event.target).is('input[type="file"]')) {
                return;
            }
            event.preventDefault();
            $('#coupon_hero_image').trigger('click');
        }

        handleDropzoneDrag(event) {
            event.preventDefault();
            event.stopPropagation();
            $('#coupon_image_dropzone').addClass('is-dragover');
        }

        handleDropzoneDragLeave(event) {
            event.preventDefault();
            event.stopPropagation();
            $('#coupon_image_dropzone').removeClass('is-dragover');
        }

        /**
         * Enhance date inputs with visible placeholders on mobile/webkit
         */
        enhanceDatePlaceholders() {
            const toggle = ($input) => {
                if ($input.val()) {
                    $input.addClass('has-value');
                } else {
                    $input.removeClass('has-value');
                }
            };

            const $dates = $('input[type="date"]');
            $dates.each(function() { toggle($(this)); });
            $(document).on('change input', 'input[type="date"]', function() { toggle($(this)); });
        }

        handleDropzoneDrop(event) {
            event.preventDefault();
            event.stopPropagation();
            const dt = event.originalEvent && event.originalEvent.dataTransfer;
            const files = dt && dt.files;
            const $input = $('#coupon_hero_image');
            if (files && files.length && $input.length) {
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(files[0]);
                $input[0].files = dataTransfer.files;
                $input.trigger('change');
            }
            $('#coupon_image_dropzone').removeClass('is-dragover');
        }

        openCropperWithFile(file) {
            const modal = $('#love-cropper-modal');
            const imgEl = document.getElementById('love-cropper-image');
            const reader = new FileReader();
            reader.onload = e => {
                imgEl.src = e.target.result;
                modal.show().attr('aria-hidden', 'false');
                // Destroy previous
                if (this.cropper) {
                    try { this.cropper.destroy(); } catch(e) {}
                }
                this.cropper = new window.Cropper(imgEl, {
                    aspectRatio: 16 / 9,
                    viewMode: 1,
                    autoCropArea: 1,
                    movable: true,
                    zoomable: true,
                    rotatable: false,
                    scalable: false,
                });
            };
            reader.readAsDataURL(file);
        }

        dismissCropper() {
            const modal = $('#love-cropper-modal');
            modal.hide().attr('aria-hidden', 'true');
            if (this.cropper) {
                try { this.cropper.destroy(); } catch(e) {}
                this.cropper = null;
            }
        }

        applyCrop() {
            if (!this.cropper) return;
            const canvas = this.cropper.getCroppedCanvas({
                width: 1600,
                height: 900,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high'
            });
            canvas.toBlob((blob) => {
                if (blob) {
                    this.croppedBlob = blob;
                    // Show preview and hide dropzone
                    const url = URL.createObjectURL(blob);
                    const $preview = $('#coupon_hero_preview');
                    const $dropzone = $('#coupon_image_dropzone');
                    $preview.find('img').attr('src', url);
                    $dropzone.hide();
                    $preview.show();
                    
                    // Check validation after image is added
                    this.checkFormValidation();
                }
                this.dismissCropper();
            }, 'image/jpeg', 0.9);
        }

        removeImage() {
            // Clear the cropped blob
            this.croppedBlob = null;
            
            // Reset file input
            const $input = $('#coupon_hero_image');
            $input.val('');
            
            // Hide preview and show dropzone
            const $preview = $('#coupon_hero_preview');
            const $dropzone = $('#coupon_image_dropzone');
            $preview.hide().find('img').attr('src', '');
            $dropzone.show();
            
            // Check validation after image is removed
            this.checkFormValidation();
        }

        openFeedbackModal(event) {
            event.preventDefault();
            const $modal = $('#love-feedback-modal');
            $modal.show().attr('aria-hidden', 'false');
            // Focus on textarea
            setTimeout(() => {
                $('#feedback-message').focus();
            }, 100);
        }

        closeFeedbackModal(event) {
            if (event) {
                event.preventDefault();
            }
            const $modal = $('#love-feedback-modal');
            $modal.hide().attr('aria-hidden', 'true');
            // Clear form
            $('#love-feedback-form')[0].reset();
            $('#love-feedback-form .form-message').hide();
        }

        submitFeedback(event) {
            event.preventDefault();
            const $button = $(event.currentTarget);
            const $form = $('#love-feedback-form');
            const $message = $form.find('.form-message');
            const feedbackText = $('#feedback-message').val().trim();

            if (!feedbackText) {
                $message.text('Please enter your feedback.').css('color', '#d63638').show();
                return;
            }

            const originalText = $button.text();
            $button.prop('disabled', true).text('Sending...');
            $message.hide();

            $.ajax({
                url: loveCouponsAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'love_coupons_send_feedback',
                    security: loveCouponsAjax.nonce,
                    feedback: feedbackText
                },
                success: (response) => {
                    if (response.success) {
                        $message.text(response.data || 'Thank you for your feedback!').css('color', '#00a32a').show();
                        setTimeout(() => {
                            this.closeFeedbackModal();
                        }, 1500);
                    } else {
                        $message.text(response.data || 'Failed to send feedback. Please try again.').css('color', '#d63638').show();
                        $button.prop('disabled', false).text(originalText);
                    }
                },
                error: () => {
                    $message.text('An error occurred. Please try again.').css('color', '#d63638').show();
                    $button.prop('disabled', false).text(originalText);
                }
            });
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
