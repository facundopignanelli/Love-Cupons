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
        
        // Use WordPress HTTP API to send push notification
        // This is a placeholder - you would need a proper Web Push library
        // or integrate with a push notification service like OneSignal, Firebase, etc.
        do_action( 'love_coupons_send_push_notification', $subscription_data, $notification_data, $coupon_id, $user );
        
        return true;
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
