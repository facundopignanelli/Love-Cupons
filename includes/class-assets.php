<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Love_Coupons_Assets {
    public function enqueue_assets() {
        if ( ! $this->should_enqueue_assets() ) { return; }
        wp_enqueue_script(
            'love-coupons-js',
            LOVE_COUPONS_PLUGIN_URL . 'assets/js/love-coupons.js',
            array( 'jquery' ),
            LOVE_COUPONS_VERSION,
            true
        );

        wp_enqueue_script(
            'love-coupons-pwa-js',
            LOVE_COUPONS_PLUGIN_URL . 'assets/js/love-coupons-pwa.js',
            array( 'jquery' ),
            LOVE_COUPONS_VERSION,
            true
        );

        wp_localize_script( 'love-coupons-js', 'loveCouponsAjax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'love_coupons_nonce' ),
            'created_page_url' => $this->get_created_coupons_page_url(),
            'vapid_public_key' => get_option( 'love_coupons_vapid_public_key', '' ),
            'strings'  => array(
                'redeeming'    => __( 'Redeeming...', 'love-coupons' ),
                'redeem'       => __( 'Redeem', 'love-coupons' ),
                'redeemed'     => __( 'Redeemed', 'love-coupons' ),
                'error'        => __( 'Error:', 'love-coupons' ),
                'ajax_failed'  => __( 'Request failed. Please try again.', 'love-coupons' ),
                'confirm_redeem' => __( 'Are you sure you want to redeem this coupon?', 'love-coupons' ),
                'creating'     => __( 'Creating...', 'love-coupons' ),
                'create_coupon'=> __( 'Create Coupon', 'love-coupons' ),
                'created'      => __( 'Coupon created successfully!', 'love-coupons' ),
                'create_failed'=> __( 'Failed to create coupon.', 'love-coupons' ),
                'image_ratio_warn' => __( 'Image is not 16:9. It will be center-cropped automatically.', 'love-coupons' ),
                'delete'       => __( 'Remove', 'love-coupons' ),
                'deleting'     => __( 'Removing...', 'love-coupons' ),
                'deleted'      => __( 'Coupon removed.', 'love-coupons' ),
                'delete_confirm' => __( 'Are you sure you want to remove this coupon?', 'love-coupons' ),
                'delete_failed'  => __( 'Failed to remove coupon.', 'love-coupons' ),
                'preferences_saving' => __( 'Saving preferences...', 'love-coupons' ),
                'preferences_saved'  => __( 'Preferences saved.', 'love-coupons' ),
                'preferences_failed' => __( 'Failed to save preferences.', 'love-coupons' ),
                'save_preferences'   => __( 'Save Preferences', 'love-coupons' ),
                'enable_notifications_title' => __( 'Enable Notifications', 'love-coupons' ),
                'enable_notifications_desc'  => __( 'Stay updated: allow notifications for coupon activity.', 'love-coupons' ),
                'enable_notifications_cta'   => __( 'Enable notifications', 'love-coupons' ),
                'not_now'                    => __( 'Not now', 'love-coupons' ),
                'notifications_enabled'      => __( 'Notifications enabled!', 'love-coupons' ),
                'notifications_blocked_title' => __( 'Notifications blocked', 'love-coupons' ),
                'notifications_blocked_desc'  => __( 'Please enable notifications in your browser/app settings to receive updates.', 'love-coupons' ),
            )
        ) );

        // Cropper.js for client cropping UI
        wp_enqueue_style(
            'cropper-css',
            'https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css',
            array(),
            '1.6.2'
        );
        wp_enqueue_script(
            'cropper-js',
            'https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js',
            array(),
            '1.6.2',
            true
        );

        wp_enqueue_style(
            'love-coupons-css',
            LOVE_COUPONS_PLUGIN_URL . 'assets/css/love-coupons.css',
            array(),
            LOVE_COUPONS_VERSION
        );
    }

    private function should_enqueue_assets() {
        global $post;
        if ( is_admin() ) { return false; }
        if ( $post && ( has_shortcode( $post->post_content, 'love_coupons' ) || has_shortcode( $post->post_content, 'love_coupons_submit' ) || has_shortcode( $post->post_content, 'love_coupons_created' ) || has_shortcode( $post->post_content, 'love_coupons_preferences' ) ) ) {
            return true;
        }
        return false;
    }

    public function add_pwa_manifest() {
        if ( ! $this->should_load_pwa() ) { return; }
        $accent = Love_Coupons_Core::get_user_accent_color( get_current_user_id() );
        $theme_color = ( $accent && ! empty( $accent['color'] ) ) ? $accent['color'] : '#2c6e49';
        echo '<link rel="manifest" href="' . esc_url( LOVE_COUPONS_PLUGIN_URL . 'manifest.json' ) . '">' . "\n";
        echo '<meta name="theme-color" content="' . esc_attr( $theme_color ) . '">' . "\n";
        echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
        echo '<meta name="mobile-web-app-capable" content="yes">' . "\n";
        echo '<meta name="apple-mobile-web-app-status-bar-style" content="default">' . "\n";
        echo '<link rel="apple-touch-icon" href="' . esc_url( LOVE_COUPONS_PLUGIN_URL . 'assets/images/icon192.png' ) . '">' . "\n";
    }

    public function register_service_worker() {
        if ( ! $this->should_load_pwa() ) { return; }
        $sw_url = esc_url( add_query_arg( 'love_coupons_sw', '1', site_url( '/' ) ) );
        ?>
        <script>
        if ( 'serviceWorker' in navigator ) {
            window.addEventListener( 'load', function() {
                navigator.serviceWorker.register( '<?php echo $sw_url; ?>', {
                    scope: '/'
                } ).then( function( registration ) {
                    console.log( 'Love Coupons SW registered: ', registration.scope );
                } ).catch( function( error ) {
                    console.warn( 'Love Coupons SW registration failed: ', error );
                } );
            } );
        }
        </script>
        <?php
    }

    private function should_load_pwa() {
        return is_user_logged_in() && $this->should_enqueue_assets();
    }

    private function get_created_coupons_page_url() {
        $pages = get_posts( array(
            'post_type'   => 'page',
            'post_status' => 'publish',
            'numberposts' => 1,
            's'           => '[love_coupons_created]',
        ) );

        if ( ! empty( $pages ) ) {
            return get_permalink( $pages[0]->ID );
        }

        // Fallback: search by content
        global $wpdb;
        $page_id = $wpdb->get_var(
            "SELECT ID FROM {$wpdb->posts} 
            WHERE post_type = 'page' 
            AND post_status = 'publish' 
            AND post_content LIKE '%[love_coupons_created]%' 
            LIMIT 1"
        );

        if ( $page_id ) {
            return get_permalink( $page_id );
        }

        return '';
    }
}
