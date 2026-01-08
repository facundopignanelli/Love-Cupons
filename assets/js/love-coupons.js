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
        }

        /**
         * Bind event listeners
         */
        bindEvents() {
            $(document).on('click', '.redeem-button', this.handleRedemption.bind(this));
            $(document).on('click', '.delete-coupon', this.handleDeleteCoupon.bind(this));
            
            // Handle keyboard navigation for terms
            $(document).on('keydown', '.coupon-terms summary', this.handleTermsKeyboard.bind(this));
            
            // Handle focus management
            $(document).on('focus', '.redeem-button', this.handleButtonFocus.bind(this));

            // Create form submit
            $(document).on('submit', '#love-create-coupon-form', this.handleCreateFormSubmit.bind(this));

            // Preferences form
            $(document).on('submit', '#love-coupons-preferences-form', this.handlePreferencesSubmit.bind(this));

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

            const fileInput = $form.find('#coupon_hero_image')[0];
            const hasFile = fileInput && fileInput.files && fileInput.files.length > 0;
            if (!hasFile && !this.croppedBlob) {
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
            formData.append('action', 'love_coupons_create');
            // Include the nonce under key expected by PHP handler
            const nonceVal = $form.find('input[name="love_create_coupon_nonce"]').val();
            formData.append('nonce', nonceVal);

            // If we have a cropped blob, use it instead of the original file
            if (this.croppedBlob) {
                formData.delete('coupon_hero_image');
                formData.append('coupon_hero_image', this.croppedBlob, 'hero-cropped.jpg');
            }

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
                $button.prop('disabled', false).removeClass('loading disabled').text(loveCouponsAjax.strings.create_coupon || 'Create Coupon');
            }
        }

        handleCreateSuccess(response, $form) {
            const $message = $form.find('.form-message');
            if (response && response.success) {
                $message.addClass('success').removeClass('error').text(loveCouponsAjax.strings.created).show();
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

        handlePreferencesSubmit(event) {
            event.preventDefault();
            const $form = $(event.currentTarget);
            const recipients = [];
            const accentChoice = $form.find('input[name="love_accent_color"]:checked').val() || '';

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
                recipients,
                accent_color: accentChoice
            };

            this.processPreferences($form, payload);
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
                        if (response.data && response.data.accent) {
                            this.applyAccentToWrappers(response.data.accent);
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

        setPreferencesLoading($button, isLoading) {
            if (isLoading) {
                $button.prop('disabled', true).addClass('loading disabled').text(loveCouponsAjax.strings.preferences_saving);
            } else {
                $button.prop('disabled', false).removeClass('loading disabled').text(loveCouponsAjax.strings.save_preferences || 'Save Preferences');
            }
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
            });
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
