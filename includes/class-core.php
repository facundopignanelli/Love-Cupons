<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Love_Coupons_Core {
    public static function user_can_access_coupon( $coupon_id, $user_id ) {
        if ( user_can( $user_id, 'manage_options' ) ) {
            return true;
        }
        $assigned_to = get_post_meta( $coupon_id, '_love_coupon_assigned_to', true );
        if ( empty( $assigned_to ) || ! is_array( $assigned_to ) ) {
            return true;
        }
        return in_array( $user_id, $assigned_to );
    }

    public static function get_allowed_recipients_for_user( $user_id ) {
        $all_users = get_users( array( 'fields' => 'ID' ) );
        $user_restrictions = get_option( 'love_coupons_posting_restrictions', array() );
        if ( empty( $user_restrictions[ $user_id ] ) ) {
            return array_filter( $all_users, function( $uid ) use ( $user_id ) { return $uid !== $user_id; });
        }
        $allowed = $user_restrictions[ $user_id ];
        if ( in_array( 'all', $allowed ) ) {
            return array_filter( $all_users, function( $uid ) use ( $user_id ) { return $uid !== $user_id; });
        }
        return array_filter( $allowed, function( $uid ) use ( $user_id, $all_users ) {
            return $uid !== $user_id && in_array( $uid, $all_users );
        });
    }

    public static function get_user_ip() {
        $ip_address = '';
        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip_address = $_SERVER['HTTP_CLIENT_IP'];
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip_address = $_SERVER['REMOTE_ADDR'];
        }
        return sanitize_text_field( $ip_address );
    }

    public static function send_redemption_email( $coupon_id, $user ) {
        $coupon = get_post( $coupon_id );
        $admin_email = get_option( 'admin_email' );
        $subject = sprintf( __( 'Coupon Redeemed: %s', 'love-coupons' ), $coupon->post_title );
        $message = sprintf(
            __( 'A coupon has been redeemed by %s (%s).\n\nCoupon: %s (ID: %d)\nRedemption Date: %s\nUser IP: %s', 'love-coupons' ),
            $user->display_name,
            $user->user_email,
            $coupon->post_title,
            $coupon_id,
            current_time( 'mysql' ),
            self::get_user_ip()
        );
        
        // Try to send push notification first, fallback to email if it fails
        $push_sent = self::send_push_notification( $coupon_id, $user );
        
        // Send email as backup or if push notifications are disabled
        if ( ! $push_sent ) {
            wp_mail( $admin_email, $subject, $message );
        }
    }

    public static function send_push_notification( $coupon_id, $user ) {
        // Get the coupon creator
        $creator_id = get_post_meta( $coupon_id, '_love_coupon_created_by', true );
        if ( ! $creator_id ) {
            return false;
        }
        
        // Get the creator's push subscription
        $subscription_data = get_user_meta( $creator_id, '_love_coupons_push_subscription', true );
        if ( empty( $subscription_data ) ) {
            return false;
        }
        
        // Get VAPID keys
        $vapid_public_key = get_option( 'love_coupons_vapid_public_key', '' );
        $vapid_private_key = get_option( 'love_coupons_vapid_private_key', '' );
        
        if ( empty( $vapid_public_key ) || empty( $vapid_private_key ) ) {
            error_log( 'Love Coupons: VAPID keys not configured' );
            return false;
        }
        
        $coupon = get_post( $coupon_id );
        
        // Prepare notification data
        $notification_data = array(
            'title' => __( 'Coupon Redeemed!', 'love-coupons' ),
            'body' => sprintf(
                __( '%s has redeemed your coupon "%s"', 'love-coupons' ),
                $user->display_name,
                $coupon->post_title
            ),
            'icon' => LOVE_COUPONS_PLUGIN_URL . 'assets/images/icon192.png',
            'badge' => LOVE_COUPONS_PLUGIN_URL . 'assets/images/icon192.png',
            'tag' => 'coupon-redeemed-' . $coupon_id,
            'url' => home_url( '/coupons/' ),
            'requireInteraction' => true
        );
        
        // Check if web-push library is available
        if ( file_exists( LOVE_COUPONS_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
            require_once LOVE_COUPONS_PLUGIN_DIR . 'vendor/autoload.php';
            
            try {
                $auth = array(
                    'VAPID' => array(
                        'subject' => home_url(),
                        'publicKey' => $vapid_public_key,
                        'privateKey' => $vapid_private_key,
                    ),
                );
                
                $webPush = new \Minishlink\WebPush\WebPush( $auth );
                $subscription = \Minishlink\WebPush\Subscription::create( json_decode( $subscription_data, true ) );
                
                $webPush->queueNotification( $subscription, json_encode( $notification_data ) );
                
                $results = $webPush->flush();
                
                foreach ( $results as $result ) {
                    if ( ! $result->isSuccess() ) {
                        error_log( 'Love Coupons: Push notification failed: ' . $result->getReason() );
                        return false;
                    }

                    /**
                     * Send a push notification directly to a specific user (admin/testing use)
                     *
                     * @param int   $user_id
                     * @param array $notification_data { title, body, icon, badge, tag, url, requireInteraction }
                     * @return bool True on success, false on failure
                     */
                    public static function send_push_notification_to_user( $user_id, $notification_data ) {
                        $user_id = absint( $user_id );
                        if ( $user_id <= 0 ) {
                            return false;
                        }

                        $subscription_data = get_user_meta( $user_id, '_love_coupons_push_subscription', true );
                        if ( empty( $subscription_data ) ) {
                            return false;
                        }

                        $vapid_public_key  = get_option( 'love_coupons_vapid_public_key', '' );
                        $vapid_private_key = get_option( 'love_coupons_vapid_private_key', '' );
                        if ( empty( $vapid_public_key ) || empty( $vapid_private_key ) ) {
                            error_log( 'Love Coupons: VAPID keys not configured' );
                            return false;
                        }

                        // Provide sane defaults
                        $defaults = array(
                            'title' => __( 'Love Coupons', 'love-coupons' ),
                            'body'  => __( 'This is a test notification.', 'love-coupons' ),
                            'icon'  => LOVE_COUPONS_PLUGIN_URL . 'assets/images/icon192.png',
                            'badge' => LOVE_COUPONS_PLUGIN_URL . 'assets/images/icon192.png',
                            'tag'   => 'love-coupons-test',
                            'url'   => home_url(),
                            'requireInteraction' => false,
                        );
                        $payload = wp_parse_args( $notification_data, $defaults );

                        // If composer autoloader is present, use the library
                        if ( file_exists( LOVE_COUPONS_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
                            require_once LOVE_COUPONS_PLUGIN_DIR . 'vendor/autoload.php';
                            try {
                                $auth = array(
                                    'VAPID' => array(
                                        'subject' => home_url(),
                                        'publicKey' => $vapid_public_key,
                                        'privateKey' => $vapid_private_key,
                                    ),
                                );
                                $webPush = new \Minishlink\WebPush\WebPush( $auth );
                                $subscription = \Minishlink\WebPush\Subscription::create( json_decode( $subscription_data, true ) );
                                $webPush->queueNotification( $subscription, json_encode( $payload ) );
                                $results = $webPush->flush();
                                foreach ( $results as $result ) {
                                    if ( ! $result->isSuccess() ) {
                                        error_log( 'Love Coupons: Test push failed: ' . $result->getReason() );
                                        return false;
                                    }
                                }
                                return true;
                            } catch ( \Exception $e ) {
                                error_log( 'Love Coupons: Test push exception: ' . $e->getMessage() );
                                return false;
                            }
                        }

                        // If library not present, indicate failure so caller can warn/admin can install deps
                        return false;
                    }
                }
                
                error_log( 'Love Coupons: Push notification sent successfully' );
                return true;
                
            } catch ( Exception $e ) {
                error_log( 'Love Coupons: Push notification error: ' . $e->getMessage() );
                return false;
            }
        }
        
        // Fallback: Use action hook for external services
        do_action( 'love_coupons_send_push_notification', $subscription_data, $notification_data, $coupon_id, $user );
        
        // Return false so email is sent as backup
        return false;
    }

    public static function log_redemption( $coupon_id, $user ) {
        error_log( sprintf(
            'Love Coupons: Coupon %d redeemed by user %d (%s) at %s',
            $coupon_id,
            $user->ID,
            $user->user_email,
            current_time( 'mysql' )
        ) );
    }
}
