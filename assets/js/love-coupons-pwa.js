(function($) {
    'use strict';

    // Lightweight PWA/Push controller with logging to debug Android PWA issues.
    const PWA = {
        init() {
            this.statusEl = $('#love-notification-status-value');
            this.messageEl = $('#love-notification-message');
            this.enableBtn = $('#love-enable-notifications-btn');

            if (!this.statusEl.length) return;

            if (!this.isSupported()) {
                this.setStatus('Not supported', 'Push notifications are not supported in this browser.', true);
                return;
            }

            this.enableBtn.off('click').on('click', () => this.requestPermission());
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
                const registration = await navigator.serviceWorker.getRegistration('/') || await navigator.serviceWorker.ready;
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
                console.log('[Love Coupons PWA] Requesting notification permission...');
                const permission = await Notification.requestPermission();
                console.log('[Love Coupons PWA] Permission result:', permission);

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
                const errorMsg = e.message || e.toString();
                this.setStatus('Error', `Failed: ${errorMsg.substring(0, 80)}`, true);
                this.enableBtn.prop('disabled', false).text('Enable Notifications');
            }
        },

        async subscribe() {
            console.log('[Love Coupons PWA] Starting subscription process...');

            let registration = await navigator.serviceWorker.getRegistration('/');
            if (!registration) {
                console.log('[Love Coupons PWA] No registration at /, waiting for ready');
                registration = await navigator.serviceWorker.ready;
            }

            if (!registration) {
                throw new Error('Service worker not registered');
            }

            console.log('[Love Coupons PWA] Using SW registration:', registration.scope);

            let sub = await registration.pushManager.getSubscription();
            if (sub) {
                console.log('[Love Coupons PWA] Already subscribed:', sub.endpoint);
            } else {
                const vapidKey = (loveCouponsAjax && loveCouponsAjax.vapid_public_key) ? loveCouponsAjax.vapid_public_key : '';
                if (!vapidKey) {
                    throw new Error('VAPID public key not configured');
                }

                console.log('[Love Coupons PWA] VAPID key length:', vapidKey.length);
                const convertedKey = this.urlBase64ToUint8Array(vapidKey);
                console.log('[Love Coupons PWA] Converted key length:', convertedKey.length);

                sub = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: convertedKey
                });

                console.log('[Love Coupons PWA] Subscription created:', sub.endpoint);
            }

            console.log('[Love Coupons PWA] Sending subscription to server...');
            await this.sendSubscriptionToServer(sub);
            console.log('[Love Coupons PWA] Subscription saved successfully!');
        },

        async sendSubscriptionToServer(subscription) {
            return new Promise((resolve, reject) => {
                console.log('[Love Coupons PWA] Sending AJAX request to:', loveCouponsAjax.ajax_url);
                $.ajax({
                    url: loveCouponsAjax.ajax_url,
                    method: 'POST',
                    data: {
                        action: 'love_coupons_save_push_subscription',
                        nonce: loveCouponsAjax.nonce,
                        subscription: JSON.stringify(subscription)
                    },
                    success: (response) => {
                        console.log('[Love Coupons PWA] Server response:', response);
                        if (response && response.success) {
                            resolve(true);
                        } else {
                            const error = response && response.data ? response.data : 'Failed to save subscription';
                            console.error('[Love Coupons PWA] Save failed:', error);
                            reject(error);
                        }
                    },
                    error: (xhr, status, error) => {
                        console.error('[Love Coupons PWA] AJAX error:', status, error);
                        console.error('[Love Coupons PWA] Response text:', xhr && xhr.responseText);
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
