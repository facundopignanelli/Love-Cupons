(function($) {
    'use strict';

    /**
     * Lightweight PWA/Push controller, isolated from main app code.
     */
    const PWA = {
        init() {
            this.statusEl = $('#love-notification-status-value');
            this.messageEl = $('#love-notification-message');
            this.enableBtn = $('#love-enable-notifications-btn');

            // Only run on preferences page
            if (!this.statusEl.length) return;

            if (!this.isSupported()) {
                this.setStatus('Not supported', 'Push notifications are not supported in this browser.', true);
                return;
            }

            // Bind button
            this.enableBtn.off('click').on('click', () => this.requestPermission());

            // Start status check
            this.checkStatus();
        },

        isSupported() {
            return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window && typeof loveCouponsAjax !== 'undefined';
        },

        setStatus(text, message = '', isError = false) {
            if (text) this.statusEl.text(text).css('color', isError ? '#dc3232' : '');
            if (message) this.messageEl.text(message).css('color', isError ? '#dc3232' : '#23282d').show();
        },

        async checkStatus() {
            this.setStatus('Checking...');
            try {
                const registration = await navigator.serviceWorker.getRegistration('/');
                if (!registration) {
                    this.setStatus('Not available', 'Service worker not registered yet. Please refresh the page.', true);
                    return;
                }

                const permission = Notification.permission;
                if (permission === 'granted') {
                    const sub = await registration.pushManager.getSubscription();
                    if (sub) {
                        this.setStatus('✓ Enabled', 'You will receive push notifications for coupon activity.');
                        this.enableBtn.hide();
                    } else {
                        this.setStatus('Granted, but not subscribed', 'Click enable to subscribe.');
                        this.enableBtn.show();
                    }
                } else if (permission === 'denied') {
                    this.setStatus('✗ Blocked', 'Notifications are blocked in your browser settings.', true);
                    this.enableBtn.hide();
                } else {
                    this.setStatus('Disabled', 'Click enable to allow notifications.');
                    this.enableBtn.show();
                }
            } catch (e) {
                console.error('[Love Coupons PWA] Status check failed:', e);
                this.setStatus('Error', 'Could not check notification status.', true);
            }
        },

        async requestPermission() {
            this.enableBtn.prop('disabled', true).text('Requesting permission...');
            this.messageEl.hide();
            try {
                const permission = await Notification.requestPermission();
                if (permission === 'granted') {
                    await this.subscribe();
                    this.setStatus('✓ Enabled', 'Notifications enabled successfully!');
                    this.enableBtn.hide();
                } else if (permission === 'denied') {
                    this.setStatus('✗ Blocked', 'You blocked notifications. Update browser settings to enable.', true);
                    this.enableBtn.hide();
                } else {
                    this.setStatus('Disabled', 'Click enable to allow notifications.');
                    this.enableBtn.prop('disabled', false).text('Enable Notifications');
                }
            } catch (e) {
                console.error('[Love Coupons PWA] Permission request failed:', e);
                this.setStatus('Error', 'Failed to enable notifications. Please try again.', true);
                this.enableBtn.prop('disabled', false).text('Enable Notifications');
            }
        },

        async subscribe() {
            const registration = await navigator.serviceWorker.getRegistration('/');
            if (!registration) throw new Error('Service worker not registered');

            // If already subscribed, reuse
            let sub = await registration.pushManager.getSubscription();
            if (!sub) {
                const vapidKey = (loveCouponsAjax && loveCouponsAjax.vapid_public_key) ? loveCouponsAjax.vapid_public_key : '';
                const convertedKey = vapidKey ? this.urlBase64ToUint8Array(vapidKey) : undefined;
                sub = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: convertedKey
                });
            }

            await this.sendSubscriptionToServer(sub);
        },

        async sendSubscriptionToServer(subscription) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: loveCouponsAjax.ajax_url,
                    method: 'POST',
                    data: {
                        action: 'love_coupons_save_push_subscription',
                        nonce: loveCouponsAjax.nonce,
                        subscription: JSON.stringify(subscription)
                    },
                    success: (response) => {
                        if (response && response.success) {
                            resolve(true);
                        } else {
                            reject(response && response.data ? response.data : 'Failed to save subscription');
                        }
                    },
                    error: (xhr) => {
                        reject(xhr && xhr.responseText ? xhr.responseText : 'Request failed');
                    }
                });
            });
        },

        urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
            const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);
            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        }
    };

    $(document).ready(function() {
        PWA.init();
    });

})(jQuery);
