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
        $user_meta_restrictions = get_user_meta( $user_id, '_love_coupons_allowed_recipients', true );

        $allowed = array();
        if ( ! empty( $user_meta_restrictions ) && is_array( $user_meta_restrictions ) ) {
            $allowed = $user_meta_restrictions;
        } elseif ( isset( $user_restrictions[ $user_id ] ) ) {
            $allowed = $user_restrictions[ $user_id ];
        }

        if ( empty( $allowed ) ) {
            return array_filter( $all_users, function( $uid ) use ( $user_id ) { return $uid !== $user_id; });
        }

        if ( in_array( 'all', $allowed, true ) ) {
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

    public static function log_redemption( $coupon_id, $user ) {
        error_log( sprintf(
            'Love Coupons: Coupon %d redeemed by user %d (%s) at %s',
            $coupon_id,
            $user->ID,
            $user->user_email,
            current_time( 'mysql' )
        ) );
    }

    /**
     * Get the active theme's accent palette (prefer Twenty Twenty-Five accent colors).
     * Falls back to a small curated list if the theme does not expose colors.
     *
     * @return array[] List of palette entries [ slug, name, color ].
     */
    public static function get_theme_accent_palette() {
        $settings = function_exists( 'wp_get_global_settings' ) ? wp_get_global_settings() : array();

        $palette = array();
        if ( isset( $settings['color']['palette']['theme'] ) && is_array( $settings['color']['palette']['theme'] ) ) {
            $palette = $settings['color']['palette']['theme'];
        } elseif ( isset( $settings['color']['palette'] ) && is_array( $settings['color']['palette'] ) ) {
            // Older WP versions may expose palette directly under color.palette.
            $palette = $settings['color']['palette'];
        }

        $normalized = array();
        foreach ( (array) $palette as $entry ) {
            if ( empty( $entry['color'] ) || empty( $entry['slug'] ) ) {
                continue;
            }

            $slug  = sanitize_title( $entry['slug'] );
            $color = self::normalize_hex_color( $entry['color'] );
            if ( ! $color ) {
                continue;
            }

            $name        = isset( $entry['name'] ) ? wp_strip_all_tags( $entry['name'] ) : ucfirst( $slug );
            $is_accent   = false !== strpos( $slug, 'accent' ) || false !== stripos( $name, 'accent' );
            $normalized[] = array(
                'slug'  => $slug,
                'name'  => $name,
                'color' => $color,
                'is_accent' => $is_accent,
            );
        }

        // Prefer entries explicitly flagged as accent colors; otherwise return the theme palette as-is.
        $accent_entries = array_filter( $normalized, function( $item ) {
            return ! empty( $item['is_accent'] );
        } );

        if ( ! empty( $accent_entries ) ) {
            return array_values( $accent_entries );
        }

        if ( ! empty( $normalized ) ) {
            return array_values( $normalized );
        }

        return self::get_fallback_accent_palette();
    }

    /**
     * Fallback palette used when the theme does not expose accent colors.
     *
     * @return array[]
     */
    public static function get_fallback_accent_palette() {
        return array(
            array( 'slug' => 'forest', 'name' => __( 'Forest', 'love-coupons' ), 'color' => '#2c6e49' ),
            array( 'slug' => 'sunset', 'name' => __( 'Sunset', 'love-coupons' ), 'color' => '#d9480f' ),
            array( 'slug' => 'ocean',  'name' => __( 'Ocean', 'love-coupons' ),  'color' => '#155b9a' ),
            array( 'slug' => 'rose',   'name' => __( 'Rose', 'love-coupons' ),   'color' => '#b83280' ),
            array( 'slug' => 'gold',   'name' => __( 'Gold', 'love-coupons' ),   'color' => '#c58a00' ),
        );
    }

    /**
     * Get a user's accent color, defaulting to the first palette entry when unset.
     *
     * @param int|null $user_id
     * @return array{slug:string,name:string,color:string}|null
     */
    public static function get_user_accent_color( $user_id ) {
        $palette = self::get_theme_accent_palette();
        $default = self::get_default_accent( $palette );
        if ( ! $user_id ) {
            return $default;
        }

        $saved = sanitize_text_field( (string) get_user_meta( $user_id, '_love_coupons_accent_color', true ) );
        $accent = self::find_accent_in_palette( $saved, $palette );

        return $accent ? $accent : $default;
    }

    /**
     * Ensure the provided accent slug maps to a palette entry; otherwise return a default slug.
     *
     * @param string $requested_slug
     * @return string
     */
    public static function sanitize_accent_choice( $requested_slug ) {
        $palette = self::get_theme_accent_palette();
        $accent  = self::find_accent_in_palette( $requested_slug, $palette );
        if ( $accent ) {
            return $accent['slug'];
        }

        $default = self::get_default_accent( $palette );
        return $default ? $default['slug'] : '';
    }

    /**
     * Build an inline style attribute string for the given accent colors.
     *
     * @param array{slug:string,name:string,color:string}|null $accent
     * @return string CSS custom property declarations.
     */
    public static function build_accent_style_value( $accent ) {
        if ( empty( $accent['color'] ) ) {
            return '';
        }

        $base      = $accent['color'];
        $style_map = array(
            '--love-accent'         => $base,
            '--love-accent-strong'  => self::adjust_color_brightness( $base, -18 ),
            '--love-accent-soft'    => self::adjust_color_brightness( $base, 26 ),
            '--love-accent-contrast'=> self::get_contrast_color( $base ),
        );

        $pairs = array();
        foreach ( $style_map as $key => $value ) {
            if ( $value ) {
                $pairs[] = $key . ': ' . $value;
            }
        }

        return implode( '; ', $pairs );
    }

    /**
     * Build HTML attributes (style plus data-* markers) for a user's accent.
     *
     * @param int|null $user_id
     * @param bool     $include_data Whether to include data-accent attributes.
     * @return string
     */
    public static function get_accent_attributes_for_user( $user_id, $include_data = true ) {
        $accent    = self::get_user_accent_color( $user_id );
        $style_val = self::build_accent_style_value( $accent );
        $attrs     = array();

        if ( $include_data && $user_id ) {
            $attrs[] = 'data-accent-user="' . absint( $user_id ) . '"';
        }

        if ( $include_data && ! empty( $accent['slug'] ) ) {
            $attrs[] = 'data-accent-slug="' . esc_attr( $accent['slug'] ) . '"';
        }

        if ( $style_val ) {
            $attrs[] = 'style="' . esc_attr( $style_val ) . '"';
        }

        return implode( ' ', $attrs );
    }

    /**
     * Locate a palette entry by slug or hex color.
     *
     * @param string $value
     * @param array  $palette
     * @return array|null
     */
    public static function find_accent_in_palette( $value, $palette ) {
        if ( empty( $value ) || empty( $palette ) ) {
            return null;
        }

        $value = trim( strtolower( $value ) );
        foreach ( $palette as $entry ) {
            if ( empty( $entry['slug'] ) || empty( $entry['color'] ) ) {
                continue;
            }
            if ( $value === strtolower( $entry['slug'] ) || $value === strtolower( ltrim( $entry['color'], '#' ) ) || $value === strtolower( $entry['color'] ) ) {
                return array(
                    'slug'  => $entry['slug'],
                    'name'  => $entry['name'],
                    'color' => self::normalize_hex_color( $entry['color'] ),
                );
            }
        }

        return null;
    }

    /**
     * Get the first palette entry as a default accent.
     *
     * @param array $palette
     * @return array|null
     */
    public static function get_default_accent( $palette ) {
        if ( empty( $palette ) || ! is_array( $palette ) ) {
            return null;
        }
        $first = array_values( $palette );
        $first = reset( $first );
        if ( empty( $first['color'] ) || empty( $first['slug'] ) ) {
            return null;
        }
        return array(
            'slug'  => $first['slug'],
            'name'  => isset( $first['name'] ) ? $first['name'] : $first['slug'],
            'color' => self::normalize_hex_color( $first['color'] ),
        );
    }

    /**
     * Normalize a hex color string to #rrggbb.
     *
     * @param string $color
     * @return string
     */
    public static function normalize_hex_color( $color ) {
        $color = trim( (string) $color );
        if ( '' === $color ) {
            return '';
        }

        // Allow values already prefixed with #.
        if ( '#' !== $color[0] ) {
            $color = '#' . $color;
        }

        // Expand short hex (#abc -> #aabbcc).
        if ( preg_match( '/^#([a-f0-9]{3})$/i', $color, $matches ) ) {
            $color = '#' . $matches[1][0] . $matches[1][0] . $matches[1][1] . $matches[1][1] . $matches[1][2] . $matches[1][2];
        }

        if ( ! preg_match( '/^#([a-f0-9]{6})$/i', $color ) ) {
            return '';
        }

        return strtolower( $color );
    }

    /**
     * Choose a readable contrast color (black/white) for a given hex.
     *
     * @param string $hex
     * @return string
     */
    public static function get_contrast_color( $hex ) {
        $hex = ltrim( self::normalize_hex_color( $hex ), '#' );
        if ( 6 !== strlen( $hex ) ) {
            return '#ffffff';
        }

        $r = hexdec( substr( $hex, 0, 2 ) );
        $g = hexdec( substr( $hex, 2, 2 ) );
        $b = hexdec( substr( $hex, 4, 2 ) );

        // Perceived brightness formula.
        $luminance = ( 0.299 * $r + 0.587 * $g + 0.114 * $b ) / 255;
        return ( $luminance > 0.55 ) ? '#111111' : '#ffffff';
    }

    /**
     * Lighten or darken a hex color by a percentage.
     *
     * @param string $hex
     * @param int    $percent Negative to darken, positive to lighten.
     * @return string
     */
    public static function adjust_color_brightness( $hex, $percent ) {
        $hex = ltrim( self::normalize_hex_color( $hex ), '#' );
        if ( 6 !== strlen( $hex ) ) {
            return '';
        }

        $percent = max( -100, min( 100, (int) $percent ) );
        $factor  = ( 100 + $percent ) / 100;

        $rgb = array();
        foreach ( array( substr( $hex, 0, 2 ), substr( $hex, 2, 2 ), substr( $hex, 4, 2 ) ) as $component ) {
            $value     = hexdec( $component );
            $adjusted  = max( 0, min( 255, (int) round( $value * $factor ) ) );
            $rgb[]     = str_pad( dechex( $adjusted ), 2, '0', STR_PAD_LEFT );
        }

        return '#' . implode( '', $rgb );
    }
}
