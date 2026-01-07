<?php
/*
Plugin Name: Love Coupons
Version: 1.1
Author: Facundo Pignanelli
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'PUP_COUPONS_VERSION', '1.1' );
define( 'PUP_COUPONS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PUP_COUPONS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PUP_COUPONS_PLUGIN_FILE', __FILE__ );

/**
 * Main Plugin Class
 */
class Pup_Coupons_Plugin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action( 'init', array( $this, 'register_cpt' ) );
        add_action( 'init', array( $this, 'load_textdomain' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_head', array( $this, 'add_pwa_manifest' ) );
        add_action( 'wp_footer', array( $this, 'register_service_worker' ) );
        add_action( 'template_redirect', array( $this, 'force_login' ) );
        
        // Admin hooks
        add_filter( 'manage_pup_coupon_posts_columns', array( $this, 'add_admin_columns' ) );
        add_action( 'manage_pup_coupon_posts_custom_column', array( $this, 'render_admin_columns' ), 10, 2 );
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post', array( $this, 'save_meta' ) );
        
        // AJAX hooks
        add_action( 'wp_ajax_pup_coupons_redeem', array( $this, 'ajax_redeem_coupon' ) );
        add_action( 'wp_ajax_nopriv_pup_coupons_redeem', array( $this, 'ajax_redeem_coupon' ) );
        
        // Shortcode
        add_shortcode( 'pup_coupons', array( $this, 'display_coupons_shortcode' ) );
        
        // Activation/Deactivation
        register_activation_hook( PUP_COUPONS_PLUGIN_FILE, array( $this, 'activate' ) );
        register_deactivation_hook( PUP_COUPONS_PLUGIN_FILE, array( $this, 'deactivate' ) );
    }
    
    /**
     * Load plugin text domain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'pup-coupons', false, dirname( plugin_basename( PUP_COUPONS_PLUGIN_FILE ) ) . '/languages' );
    }
    
    /**
     * Register custom post type
     */
    public function register_cpt() {
        $labels = array(
            'name'                  => _x( 'Coupons', 'Post type general name', 'pup-coupons' ),
            'singular_name'         => _x( 'Coupon', 'Post type singular name', 'pup-coupons' ),
            'menu_name'             => _x( 'Pup Coupons', 'Admin Menu text', 'pup-coupons' ),
            'name_admin_bar'        => _x( 'Coupon', 'Add New on Toolbar', 'pup-coupons' ),
            'add_new'               => __( 'Add New', 'pup-coupons' ),
            'add_new_item'          => __( 'Add New Coupon', 'pup-coupons' ),
            'edit_item'             => __( 'Edit Coupon', 'pup-coupons' ),
            'view_item'             => __( 'View Coupon', 'pup-coupons' ),
            'all_items'             => __( 'All Coupons', 'pup-coupons' ),
            'search_items'          => __( 'Search Coupons', 'pup-coupons' ),
            'not_found'             => __( 'No coupons found.', 'pup-coupons' ),
            'not_found_in_trash'    => __( 'No coupons found in Trash.', 'pup-coupons' ),
        );
        
        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'menu_icon'          => 'dashicons-tickets-alt',
            'supports'           => array( 'title', 'thumbnail', 'custom-fields' ),
            'show_in_rest'       => true,
        );
        
        register_post_type( 'pup_coupon', $args );
    }
    
    /**
     * Add admin columns
     */
    public function add_admin_columns( $columns ) {
        $new_columns = array();
        foreach ( $columns as $key => $value ) {
            $new_columns[ $key ] = $value;
            if ( 'title' === $key ) {
                $new_columns['redeemed'] = __( 'Status', 'pup-coupons' );
                $new_columns['redemption_date'] = __( 'Redeemed Date', 'pup-coupons' );
            }
        }
        return $new_columns;
    }
    
    /**
     * Render admin columns
     */
    public function render_admin_columns( $column, $post_id ) {
        switch ( $column ) {
            case 'redeemed':
                $redeemed = get_post_meta( $post_id, '_pup_coupon_redeemed', true );
                if ( $redeemed ) {
                    echo '<span style="color: #d63638;">&#x2713; ' . __( 'Redeemed', 'pup-coupons' ) . '</span>';
                } else {
                    echo '<span style="color: #00a32a;">&#x2713; ' . __( 'Available', 'pup-coupons' ) . '</span>';
                }
                break;
            case 'redemption_date':
                $date = get_post_meta( $post_id, '_pup_coupon_redemption_date', true );
                echo $date ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $date ) ) : '—';
                break;
        }
    }
    
    /**
     * Add meta boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'pup_coupon_details',
            __( 'Coupon Details', 'pup-coupons' ),
            array( $this, 'render_meta_box' ),
            'pup_coupon',
            'normal',
            'high'
        );
        
        add_meta_box(
            'pup_coupon_settings',
            __( 'Coupon Settings', 'pup-coupons' ),
            array( $this, 'render_settings_meta_box' ),
            'pup_coupon',
            'side',
            'high'
        );
    }
    
    /**
     * Render meta box
     */
    public function render_meta_box( $post ) {
        wp_nonce_field( 'pup_coupon_save', 'pup_coupon_nonce' );
        
        $terms = get_post_meta( $post->ID, '_pup_coupon_terms', true );
        $expiry_date = get_post_meta( $post->ID, '_pup_coupon_expiry_date', true );
        $usage_limit = get_post_meta( $post->ID, '_pup_coupon_usage_limit', true );
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="pup_coupon_terms"><?php _e( 'Terms & Conditions', 'pup-coupons' ); ?></label>
                </th>
                <td>
                    <textarea id="pup_coupon_terms" name="pup_coupon_terms" rows="4" cols="50" class="large-text"><?php echo esc_textarea( $terms ); ?></textarea>
                    <p class="description"><?php _e( 'Enter the terms and conditions for this coupon.', 'pup-coupons' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="pup_coupon_expiry_date"><?php _e( 'Expiry Date', 'pup-coupons' ); ?></label>
                </th>
                <td>
                    <input type="date" id="pup_coupon_expiry_date" name="pup_coupon_expiry_date" value="<?php echo esc_attr( $expiry_date ); ?>" />
                    <p class="description"><?php _e( 'Optional: Set an expiry date for this coupon.', 'pup-coupons' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="pup_coupon_usage_limit"><?php _e( 'Usage Limit', 'pup-coupons' ); ?></label>
                </th>
                <td>
                    <input type="number" id="pup_coupon_usage_limit" name="pup_coupon_usage_limit" value="<?php echo esc_attr( $usage_limit ?: 1 ); ?>" min="1" />
                    <p class="description"><?php _e( 'How many times can this coupon be redeemed? (Default: 1)', 'pup-coupons' ); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render settings meta box
     */
    public function render_settings_meta_box( $post ) {
        $redeemed = get_post_meta( $post->ID, '_pup_coupon_redeemed', true );
        $redemption_date = get_post_meta( $post->ID, '_pup_coupon_redemption_date', true );
        $redemption_count = get_post_meta( $post->ID, '_pup_coupon_redemption_count', true );
        
        ?>
        <p><strong><?php _e( 'Redemption Status:', 'pup-coupons' ); ?></strong></p>
        <p><?php echo $redeemed ? __( 'Redeemed', 'pup-coupons' ) : __( 'Available', 'pup-coupons' ); ?></p>
        
        <?php if ( $redemption_date ) : ?>
            <p><strong><?php _e( 'Redemption Date:', 'pup-coupons' ); ?></strong></p>
            <p><?php echo date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $redemption_date ) ); ?></p>
        <?php endif; ?>
        
        <p><strong><?php _e( 'Times Redeemed:', 'pup-coupons' ); ?></strong></p>
        <p><?php echo intval( $redemption_count ); ?></p>
        
        <?php if ( $redeemed ) : ?>
            <p>
                <button type="button" class="button button-secondary" onclick="if(confirm('<?php _e( 'Are you sure you want to reset this coupon?', 'pup-coupons' ); ?>')){
                    jQuery('#pup_coupon_reset').val('1');
                    jQuery('#post').submit();
                }"><?php _e( 'Reset Coupon', 'pup-coupons' ); ?></button>
                <input type="hidden" name="pup_coupon_reset" id="pup_coupon_reset" value="0" />
            </p>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Save meta data
     */
    public function save_meta( $post_id ) {
        // Verify nonce
        if ( ! isset( $_POST['pup_coupon_nonce'] ) || ! wp_verify_nonce( $_POST['pup_coupon_nonce'], 'pup_coupon_save' ) ) {
            return;
        }
        
        // Check autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        
        // Check post type and permissions
        if ( 'pup_coupon' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        
        // Save fields
        $fields = array(
            '_pup_coupon_terms' => 'sanitize_textarea_field',
            '_pup_coupon_expiry_date' => 'sanitize_text_field',
            '_pup_coupon_usage_limit' => 'absint',
        );
        
        foreach ( $fields as $field => $sanitizer ) {
            $post_field = str_replace( '_pup_coupon_', 'pup_coupon_', $field );
            if ( isset( $_POST[ $post_field ] ) ) {
                $value = call_user_func( $sanitizer, $_POST[ $post_field ] );
                update_post_meta( $post_id, $field, $value );
            }
        }
        
        // Handle reset
        if ( isset( $_POST['pup_coupon_reset'] ) && '1' === $_POST['pup_coupon_reset'] ) {
            delete_post_meta( $post_id, '_pup_coupon_redeemed' );
            delete_post_meta( $post_id, '_pup_coupon_redemption_date' );
            update_post_meta( $post_id, '_pup_coupon_redemption_count', 0 );
        }
    }
    
    /**
     * Enqueue assets
     */
    public function enqueue_assets() {
        // Only enqueue on pages with the shortcode or specific pages
        if ( ! $this->should_enqueue_assets() ) {
            return;
        }
        
        wp_enqueue_script(
            'pup-coupons-js',
            PUP_COUPONS_PLUGIN_URL . 'assets/js/pup-coupons.js',
            array( 'jquery' ),
            PUP_COUPONS_VERSION,
            true
        );
        
        wp_localize_script( 'pup-coupons-js', 'pupCouponsAjax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'pup_coupons_nonce' ),
            'strings'  => array(
                'redeeming'    => __( 'Redeeming...', 'pup-coupons' ),
                'redeem'       => __( 'Redeem', 'pup-coupons' ),
                'redeemed'     => __( 'Redeemed', 'pup-coupons' ),
                'error'        => __( 'Error:', 'pup-coupons' ),
                'ajax_failed'  => __( 'Request failed. Please try again.', 'pup-coupons' ),
                'confirm_redeem' => __( 'Are you sure you want to redeem this coupon?', 'pup-coupons' ),
            )
        ) );
        
        wp_enqueue_style(
            'pup-coupons-css',
            PUP_COUPONS_PLUGIN_URL . 'assets/css/pup-coupons.css',
            array(),
            PUP_COUPONS_VERSION
        );
    }
    
    /**
     * Check if assets should be enqueued
     */
    private function should_enqueue_assets() {
        global $post;
        
        if ( is_admin() ) {
            return false;
        }
        
        // Check if current page has the shortcode
        if ( $post && has_shortcode( $post->post_content, 'pup_coupons' ) ) {
            return true;
        }
        
        // Check for specific page/post IDs if needed
        // You can extend this logic as needed
        
        return false;
    }
    
    /**
     * AJAX handler for coupon redemption
     */
    public function ajax_redeem_coupon() {
        // Verify nonce
        if ( ! check_ajax_referer( 'pup_coupons_nonce', 'security', false ) ) {
            wp_send_json_error( __( 'Security check failed.', 'pup-coupons' ) );
        }
        
        // Check if user is logged in
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( __( 'You must be logged in to redeem coupons.', 'pup-coupons' ) );
        }
        
        // Validate coupon ID
        $coupon_id = isset( $_POST['coupon_id'] ) ? absint( $_POST['coupon_id'] ) : 0;
        
        if ( ! $coupon_id || 'pup_coupon' !== get_post_type( $coupon_id ) ) {
            wp_send_json_error( __( 'Invalid coupon.', 'pup-coupons' ) );
        }
        
        // Check if coupon exists and is published
        $coupon = get_post( $coupon_id );
        if ( ! $coupon || 'publish' !== $coupon->post_status ) {
            wp_send_json_error( __( 'Coupon not found or not available.', 'pup-coupons' ) );
        }
        
        // Check if already redeemed
        $redeemed = get_post_meta( $coupon_id, '_pup_coupon_redeemed', true );
        if ( $redeemed ) {
            wp_send_json_error( __( 'This coupon has already been redeemed.', 'pup-coupons' ) );
        }
        
        // Check expiry date
        $expiry_date = get_post_meta( $coupon_id, '_pup_coupon_expiry_date', true );
        if ( $expiry_date && strtotime( $expiry_date ) < time() ) {
            wp_send_json_error( __( 'This coupon has expired.', 'pup-coupons' ) );
        }
        
        // Check usage limit
        $usage_limit = get_post_meta( $coupon_id, '_pup_coupon_usage_limit', true );
        $redemption_count = get_post_meta( $coupon_id, '_pup_coupon_redemption_count', true );
        
        if ( $usage_limit && $redemption_count >= $usage_limit ) {
            wp_send_json_error( __( 'This coupon has reached its usage limit.', 'pup-coupons' ) );
        }
        
        // Process redemption
        $current_user = wp_get_current_user();
        $redemption_data = array(
            'user_id' => $current_user->ID,
            'user_email' => $current_user->user_email,
            'redemption_date' => current_time( 'mysql' ),
            'ip_address' => $this->get_user_ip(),
        );
        
        // Update post meta
        update_post_meta( $coupon_id, '_pup_coupon_redeemed', true );
        update_post_meta( $coupon_id, '_pup_coupon_redemption_date', current_time( 'mysql' ) );
        update_post_meta( $coupon_id, '_pup_coupon_redemption_count', intval( $redemption_count ) + 1 );
        update_post_meta( $coupon_id, '_pup_coupon_redemption_data', $redemption_data );
        
        // Send notification email
        $this->send_redemption_email( $coupon_id, $current_user );
        
        // Log the redemption
        $this->log_redemption( $coupon_id, $current_user );
        
        wp_send_json_success( __( 'Coupon redeemed successfully!', 'pup-coupons' ) );
    }
    
    /**
     * Send redemption notification email
     */
    private function send_redemption_email( $coupon_id, $user ) {
        $coupon = get_post( $coupon_id );
        $admin_email = get_option( 'admin_email' );
        
        $subject = sprintf( __( 'Coupon Redeemed: %s', 'pup-coupons' ), $coupon->post_title );
        
        $message = sprintf(
            __( 'A coupon has been redeemed by %s (%s).\n\nCoupon: %s (ID: %d)\nRedemption Date: %s\nUser IP: %s', 'pup-coupons' ),
            $user->display_name,
            $user->user_email,
            $coupon->post_title,
            $coupon_id,
            current_time( 'mysql' ),
            $this->get_user_ip()
        );
        
        wp_mail( $admin_email, $subject, $message );
    }
    
    /**
     * Log coupon redemption
     */
    private function log_redemption( $coupon_id, $user ) {
        error_log( sprintf(
            'Pup Coupons: Coupon %d redeemed by user %d (%s) at %s',
            $coupon_id,
            $user->ID,
            $user->user_email,
            current_time( 'mysql' )
        ) );
    }
    
    /**
     * Get user IP address
     */
    private function get_user_ip() {
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
    
    /**
     * Display coupons shortcode
     */
    public function display_coupons_shortcode( $atts ) {
        // Parse attributes
        $atts = shortcode_atts( array(
            'limit' => -1,
            'category' => '',
            'show_expired' => 'yes', // Changed default to show all coupons for sorting
        ), $atts, 'pup_coupons' );
        
        if ( ! is_user_logged_in() ) {
            return '<div class="pup-coupons-login-message"><p>' . __( 'Please log in to see the coupons!', 'pup-coupons' ) . '</p></div>';
        }
        
        $args = array(
            'post_type' => 'pup_coupon',
            'post_status' => 'publish',
            'posts_per_page' => intval( $atts['limit'] ),
        );
        
        $query = new WP_Query( $args );
        
        if ( ! $query->have_posts() ) {
            wp_reset_postdata();
            return '<div class="pup-coupons-empty"><p>' . __( 'No coupons available at the moment.', 'pup-coupons' ) . '</p></div>';
        }
        
        // Organize coupons by state
        $available_coupons = array();
        $redeemed_coupons = array();
        $expired_coupons = array();
        
        while ( $query->have_posts() ) {
            $query->the_post();
            $coupon_id = get_the_ID();
            $redeemed = get_post_meta( $coupon_id, '_pup_coupon_redeemed', true );
            $expiry_date = get_post_meta( $coupon_id, '_pup_coupon_expiry_date', true );
            $is_expired = $expiry_date && strtotime( $expiry_date ) < time();
            
            if ( $redeemed ) {
                $redeemed_coupons[] = $coupon_id;
            } elseif ( $is_expired ) {
                $expired_coupons[] = $coupon_id;
            } else {
                $available_coupons[] = $coupon_id;
            }
        }
        
        wp_reset_postdata();
        
        ob_start();
        
        echo '<div class="pup-coupons-container">';
        
        // Available Coupons Section
        if ( ! empty( $available_coupons ) ) {
            echo '<div class="pup-coupons-section">';
            echo '<h2 class="pup-coupons-section-title">' . __( 'Available Coupons', 'pup-coupons' ) . '</h2>';
            echo '<div class="pup-coupons-grid">';
            
            foreach ( $available_coupons as $coupon_id ) {
                $this->render_coupon_item( $coupon_id );
            }
            
            echo '</div>'; // .pup-coupons-grid
            echo '</div>'; // .pup-coupons-section
        }
        
        // Redeemed Coupons Section
        if ( ! empty( $redeemed_coupons ) ) {
            if ( ! empty( $available_coupons ) ) {
                echo '<hr class="pup-coupons-separator">';
            }
            
            echo '<div class="pup-coupons-section">';
            echo '<h2 class="pup-coupons-section-title">' . __( 'Redeemed Coupons', 'pup-coupons' ) . '</h2>';
            echo '<div class="pup-coupons-grid">';
            
            foreach ( $redeemed_coupons as $coupon_id ) {
                $this->render_coupon_item( $coupon_id );
            }
            
            echo '</div>'; // .pup-coupons-grid
            echo '</div>'; // .pup-coupons-section
        }
        
        // Expired Coupons Section
        if ( ! empty( $expired_coupons ) && 'yes' === $atts['show_expired'] ) {
            if ( ! empty( $available_coupons ) || ! empty( $redeemed_coupons ) ) {
                echo '<hr class="pup-coupons-separator">';
            }
            
            echo '<div class="pup-coupons-section">';
            echo '<h2 class="pup-coupons-section-title">' . __( 'Expired Coupons', 'pup-coupons' ) . '</h2>';
            echo '<div class="pup-coupons-grid">';
            
            foreach ( $expired_coupons as $coupon_id ) {
                $this->render_coupon_item( $coupon_id );
            }
            
            echo '</div>'; // .pup-coupons-grid
            echo '</div>'; // .pup-coupons-section
        }
        
        echo '</div>'; // .pup-coupons-container
        
        return ob_get_clean();
    }
    
    /**
     * Render individual coupon item
     */
    private function render_coupon_item( $coupon_id ) {
        $redeemed = get_post_meta( $coupon_id, '_pup_coupon_redeemed', true );
        $terms = get_post_meta( $coupon_id, '_pup_coupon_terms', true );
        $expiry_date = get_post_meta( $coupon_id, '_pup_coupon_expiry_date', true );
        $is_expired = $expiry_date && strtotime( $expiry_date ) < time();
        
        $classes = array( 'pup-coupon' );
        if ( $redeemed ) {
            $classes[] = 'redeemed';
        }
        if ( $is_expired ) {
            $classes[] = 'expired';
        }
        
        ?>
        <div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-coupon-id="<?php echo esc_attr( $coupon_id ); ?>">
            <?php if ( has_post_thumbnail( $coupon_id ) ) : ?>
                <div class="coupon-image">
                    <?php echo get_the_post_thumbnail( $coupon_id, 'medium' ); ?>
                </div>
            <?php endif; ?>
            
            <div class="coupon-content">
                <h3 class="coupon-title"><?php echo esc_html( get_the_title( $coupon_id ) ); ?></h3>
                
                <?php if ( $expiry_date ) : ?>
                    <div class="coupon-expiry">
                        <small><?php printf( __( 'Expires: %s', 'pup-coupons' ), date_i18n( get_option( 'date_format' ), strtotime( $expiry_date ) ) ); ?></small>
                    </div>
                <?php endif; ?>
                
                <?php if ( $terms ) : ?>
                    <details class="coupon-terms">
                        <summary><?php _e( 'Terms & Conditions', 'pup-coupons' ); ?></summary>
                        <div class="terms-content"><?php echo wp_kses_post( nl2br( $terms ) ); ?></div>
                    </details>
                <?php endif; ?>
                
                <div class="coupon-actions">
                    <?php if ( $redeemed ) : ?>
                        <button class="button button-redeemed" disabled>
                            <span class="dashicons dashicons-yes"></span>
                            <?php _e( 'Redeemed', 'pup-coupons' ); ?>
                        </button>
                    <?php elseif ( $is_expired ) : ?>
                        <button class="button button-expired" disabled>
                            <span class="dashicons dashicons-clock"></span>
                            <?php _e( 'Expired', 'pup-coupons' ); ?>
                        </button>
                    <?php else : ?>
                        <button class="button button-primary redeem-button" data-coupon-id="<?php echo esc_attr( $coupon_id ); ?>">
                            <span class="dashicons dashicons-tickets-alt"></span>
                            <?php _e( 'Redeem', 'pup-coupons' ); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Force login for coupon pages
     */
    public function force_login() {
        if ( is_admin() || is_user_logged_in() ) {
            return;
        }
        
        global $post;
        
        if ( is_page() && $post && has_shortcode( $post->post_content, 'pup_coupons' ) ) {
            auth_redirect();
        }
    }
    
    /**
     * Add PWA manifest
     */
    public function add_pwa_manifest() {
        if ( ! $this->should_load_pwa() ) {
            return;
        }
        
        echo '<link rel="manifest" href="' . esc_url( PUP_COUPONS_PLUGIN_URL . 'manifest.json' ) . '">' . "\n";
        echo '<meta name="theme-color" content="#2c6e49">' . "\n";
        echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
        echo '<meta name="apple-mobile-web-app-status-bar-style" content="default">' . "\n";
        echo '<link rel="apple-touch-icon" href="' . esc_url( PUP_COUPONS_PLUGIN_URL . 'assets/images/icon192.png' ) . '">' . "\n";
    }
    
    /**
     * Register service worker
     */
    public function register_service_worker() {
        if ( ! $this->should_load_pwa() ) {
            return;
        }
        
        ?>
        <script>
        if ( 'serviceWorker' in navigator ) {
            window.addEventListener( 'load', function() {
                navigator.serviceWorker.register( '<?php echo esc_url( PUP_COUPONS_PLUGIN_URL . 'service-worker.js' ); ?>', {
                    scope: '<?php echo esc_url( PUP_COUPONS_PLUGIN_URL ); ?>'
                } ).then( function( registration ) {
                    console.log( 'Pup Coupons SW registered: ', registration.scope );
                } ).catch( function( error ) {
                    console.warn( 'Pup Coupons SW registration failed: ', error );
                } );
            } );
        }
        </script>
        <?php
    }
    
    /**
     * Check if PWA should be loaded
     */
    private function should_load_pwa() {
        return is_user_logged_in() && $this->should_enqueue_assets();
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        $this->register_cpt();
        flush_rewrite_rules();
        
        // Create default capabilities
        $role = get_role( 'administrator' );
        if ( $role ) {
            $role->add_cap( 'manage_pup_coupons' );
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
}

// Initialize the plugin
Pup_Coupons_Plugin::get_instance();
