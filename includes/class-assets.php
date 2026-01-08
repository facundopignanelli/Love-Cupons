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

        wp_localize_script( 'love-coupons-js', 'loveCouponsAjax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'love_coupons_nonce' ),
            'strings'  => array(
                'redeeming'    => __( 'Redeeming...', 'love-coupons' ),
                'redeem'       => __( 'Redeem', 'love-coupons' ),
                'redeemed'     => __( 'Redeemed', 'love-coupons' ),
                'error'        => __( 'Error:', 'love-coupons' ),
                'ajax_failed'  => __( 'Request failed. Please try again.', 'love-coupons' ),
                'confirm_redeem' => __( 'Are you sure you want to redeem this coupon?', 'love-coupons' ),
                'creating'     => __( 'Creating...', 'love-coupons' ),
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
        echo '<link rel="manifest" href="' . esc_url( LOVE_COUPONS_PLUGIN_URL . 'manifest.json' ) . '">' . "\n";
        echo '<meta name="theme-color" content="#2c6e49">' . "\n";
        echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
        echo '<meta name="apple-mobile-web-app-status-bar-style" content="default">' . "\n";
        echo '<link rel="apple-touch-icon" href="' . esc_url( LOVE_COUPONS_PLUGIN_URL . 'assets/images/icon192.png' ) . '">' . "\n";
    }

    public function register_service_worker() {
        if ( ! $this->should_load_pwa() ) { return; }
        ?>
        <script>
        if ( 'serviceWorker' in navigator ) {
            window.addEventListener( 'load', function() {
                navigator.serviceWorker.register( '<?php echo esc_url( LOVE_COUPONS_PLUGIN_URL . 'service-worker.js' ); ?>', {
                    scope: '<?php echo esc_url( LOVE_COUPONS_PLUGIN_URL ); ?>'
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
}
