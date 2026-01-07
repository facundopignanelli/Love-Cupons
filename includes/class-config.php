<?php
/**
 * Plugin Configuration
 * Centralized configuration for the Love Coupons plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Pup_Coupons_Config {
    
    /**
     * Plugin settings
     */
    const SETTINGS = array(
        'version' => '1.1',
        'min_wp_version' => '5.0',
        'min_php_version' => '7.4',
        'text_domain' => 'pup-coupons',
        'cache_group' => 'pup_coupons',
        'option_prefix' => 'pup_coupons_',
    );
    
    /**
     * Post type configuration
     */
    const POST_TYPE = array(
        'name' => 'pup_coupon',
        'slug' => 'coupons',
        'capability_type' => 'post',
        'supports' => array( 'title', 'thumbnail', 'custom-fields' ),
    );
    
    /**
     * Meta field configuration
     */
    const META_FIELDS = array(
        '_pup_coupon_terms' => array(
            'type' => 'textarea',
            'sanitize' => 'sanitize_textarea_field',
            'label' => 'Terms & Conditions',
        ),
        '_pup_coupon_expiry_date' => array(
            'type' => 'date',
            'sanitize' => 'sanitize_text_field',
            'label' => 'Expiry Date',
        ),
        '_pup_coupon_usage_limit' => array(
            'type' => 'number',
            'sanitize' => 'absint',
            'label' => 'Usage Limit',
            'default' => 1,
        ),
        '_pup_coupon_redeemed' => array(
            'type' => 'boolean',
            'sanitize' => 'rest_sanitize_boolean',
            'label' => 'Redeemed Status',
        ),
        '_pup_coupon_redemption_date' => array(
            'type' => 'datetime',
            'sanitize' => 'sanitize_text_field',
            'label' => 'Redemption Date',
        ),
        '_pup_coupon_redemption_count' => array(
            'type' => 'number',
            'sanitize' => 'absint',
            'label' => 'Redemption Count',
            'default' => 0,
        ),
        '_pup_coupon_redemption_data' => array(
            'type' => 'array',
            'sanitize' => array( 'Pup_Coupons_Config', 'sanitize_redemption_data' ),
            'label' => 'Redemption Data',
        ),
    );
    
    /**
     * Capability configuration
     */
    const CAPABILITIES = array(
        'manage_pup_coupons',
        'edit_pup_coupon',
        'read_pup_coupon',
        'delete_pup_coupon',
        'edit_pup_coupons',
        'edit_others_pup_coupons',
        'publish_pup_coupons',
        'read_private_pup_coupons',
    );
    
    /**
     * Email configuration
     */
    const EMAIL_SETTINGS = array(
        'from_name' => 'Pup Coupons',
        'content_type' => 'text/html',
        'charset' => 'UTF-8',
    );
    
    /**
     * Cache configuration
     */
    const CACHE_SETTINGS = array(
        'default_expiration' => 12 * HOUR_IN_SECONDS,
        'coupon_list_expiration' => 5 * MINUTE_IN_SECONDS,
        'user_coupons_expiration' => 15 * MINUTE_IN_SECONDS,
    );
    
    /**
     * Security configuration
     */
    const SECURITY_SETTINGS = array(
        'nonce_lifetime' => 12 * HOUR_IN_SECONDS,
        'max_redemption_attempts' => 5,
        'rate_limit_window' => 15 * MINUTE_IN_SECONDS,
    );
    
    /**
     * Get plugin setting
     */
    public static function get_setting( $key, $default = null ) {
        return isset( self::SETTINGS[ $key ] ) ? self::SETTINGS[ $key ] : $default;
    }
    
    /**
     * Get meta field configuration
     */
    public static function get_meta_field( $key ) {
        return isset( self::META_FIELDS[ $key ] ) ? self::META_FIELDS[ $key ] : null;
    }
    
    /**
     * Get all meta field keys
     */
    public static function get_meta_field_keys() {
        return array_keys( self::META_FIELDS );
    }
    
    /**
     * Sanitize redemption data
     */
    public static function sanitize_redemption_data( $data ) {
        if ( ! is_array( $data ) ) {
            return array();
        }
        
        $sanitized = array();
        
        if ( isset( $data['user_id'] ) ) {
            $sanitized['user_id'] = absint( $data['user_id'] );
        }
        
        if ( isset( $data['user_email'] ) ) {
            $sanitized['user_email'] = sanitize_email( $data['user_email'] );
        }
        
        if ( isset( $data['redemption_date'] ) ) {
            $sanitized['redemption_date'] = sanitize_text_field( $data['redemption_date'] );
        }
        
        if ( isset( $data['ip_address'] ) ) {
            $sanitized['ip_address'] = sanitize_text_field( $data['ip_address'] );
        }
        
        if ( isset( $data['user_agent'] ) ) {
            $sanitized['user_agent'] = sanitize_text_field( $data['user_agent'] );
        }
        
        return $sanitized;
    }
    
    /**
     * Get default plugin options
     */
    public static function get_default_options() {
        return array(
            'notification_email' => get_option( 'admin_email' ),
            'enable_notifications' => true,
            'enable_expiry_dates' => true,
            'enable_usage_limits' => true,
            'enable_logging' => true,
            'enable_rate_limiting' => true,
            'cache_enabled' => true,
            'pwa_enabled' => true,
        );
    }
    
    /**
     * Get allowed HTML for coupon content
     */
    public static function get_allowed_html() {
        return array(
            'p' => array(),
            'br' => array(),
            'strong' => array(),
            'em' => array(),
            'ul' => array(),
            'ol' => array(),
            'li' => array(),
            'a' => array(
                'href' => array(),
                'title' => array(),
                'target' => array(),
            ),
        );
    }
}
