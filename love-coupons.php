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
define( 'LOVE_COUPONS_VERSION', '1.1' );
define( 'LOVE_COUPONS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LOVE_COUPONS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LOVE_COUPONS_PLUGIN_FILE', __FILE__ );

/**
 * Main Plugin Class
 */
class Love_Coupons_Plugin {
    
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
        add_filter( 'manage_love_coupon_posts_columns', array( $this, 'add_admin_columns' ) );
        add_action( 'manage_love_coupon_posts_custom_column', array( $this, 'render_admin_columns' ), 10, 2 );
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post', array( $this, 'save_meta' ) );
        add_action( 'admin_menu', array( $this, 'add_admin_settings_page' ) );
        
        // AJAX hooks
        add_action( 'wp_ajax_love_coupons_redeem', array( $this, 'ajax_redeem_coupon' ) );
        add_action( 'wp_ajax_love_coupons_create', array( $this, 'ajax_create_coupon' ) );
        add_action( 'wp_ajax_nopriv_love_coupons_redeem', array( $this, 'ajax_redeem_coupon' ) );
        
        // Shortcode
        add_shortcode( 'love_coupons', array( $this, 'display_coupons_shortcode' ) );
        
        // Activation/Deactivation
        register_activation_hook( LOVE_COUPONS_PLUGIN_FILE, array( $this, 'activate' ) );
        register_deactivation_hook( LOVE_COUPONS_PLUGIN_FILE, array( $this, 'deactivate' ) );
    }
    
    /**
     * Load plugin text domain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'love-coupons', false, dirname( plugin_basename( LOVE_COUPONS_PLUGIN_FILE ) ) . '/languages' );
    }
    
    /**
     * Register custom post type
     */
    public function register_cpt() {
        $labels = array(
            'name'                  => _x( 'Coupons', 'Post type general name', 'love-coupons' ),
            'singular_name'         => _x( 'Coupon', 'Post type singular name', 'love-coupons' ),
            'menu_name'             => _x( 'Love Coupons', 'Admin Menu text', 'love-coupons' ),
            'name_admin_bar'        => _x( 'Coupon', 'Add New on Toolbar', 'love-coupons' ),
            'add_new'               => __( 'Add New', 'love-coupons' ),
            'add_new_item'          => __( 'Add New Coupon', 'love-coupons' ),
            'edit_item'             => __( 'Edit Coupon', 'love-coupons' ),
            'view_item'             => __( 'View Coupon', 'love-coupons' ),
            'all_items'             => __( 'All Coupons', 'love-coupons' ),
            'search_items'          => __( 'Search Coupons', 'love-coupons' ),
            'not_found'             => __( 'No coupons found.', 'love-coupons' ),
            'not_found_in_trash'    => __( 'No coupons found in Trash.', 'love-coupons' ),
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
        
        register_post_type( 'love_coupon', $args );
    }
    
    /**
     * Add admin columns
     */
    public function add_admin_columns( $columns ) {
        $new_columns = array();
        foreach ( $columns as $key => $value ) {
            $new_columns[ $key ] = $value;
            if ( 'title' === $key ) {
                $new_columns['assigned_to'] = __( 'Assigned To', 'love-coupons' );
                $new_columns['redeemed'] = __( 'Status', 'love-coupons' );
                $new_columns['redemption_date'] = __( 'Redeemed Date', 'love-coupons' );
            }
        }
        return $new_columns;
    }
    
    /**
     * Render admin columns
     */
    public function render_admin_columns( $column, $post_id ) {
        switch ( $column ) {
            case 'assigned_to':
                $assigned_to = get_post_meta( $post_id, '_love_coupon_assigned_to', true );
                if ( empty( $assigned_to ) || ! is_array( $assigned_to ) ) {
                    echo '<span style="color: #666;">' . __( 'All Users', 'love-coupons' ) . '</span>';
                } else {
                    $user_names = array();
                    foreach ( $assigned_to as $user_id ) {
                        $user = get_user_by( 'id', $user_id );
                        if ( $user ) {
                            $user_names[] = esc_html( $user->display_name );
                        }
                    }
                    echo implode( ', ', $user_names );
                }
                break;
            case 'redeemed':
                $redeemed = get_post_meta( $post_id, '_love_coupon_redeemed', true );
                if ( $redeemed ) {
                    echo '<span style="color: #d63638;">&#x2713; ' . __( 'Redeemed', 'love-coupons' ) . '</span>';
                } else {
                    echo '<span style="color: #00a32a;">&#x2713; ' . __( 'Available', 'love-coupons' ) . '</span>';
                }
                break;
            case 'redemption_date':
                $date = get_post_meta( $post_id, '_love_coupon_redemption_date', true );
                echo $date ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $date ) ) : '—';
                break;
        }
    }
    
    /**
     * Add meta boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'love_coupon_details',
            __( 'Coupon Details', 'love-coupons' ),
            array( $this, 'render_meta_box' ),
            'love_coupon',
            'normal',
            'high'
        );
        
        add_meta_box(
            'love_coupon_recipients',
            __( 'Assign To Users', 'love-coupons' ),
            array( $this, 'render_recipients_meta_box' ),
            'love_coupon',
            'normal',
            'high'
        );
        
        add_meta_box(
            'love_coupon_settings',
            __( 'Coupon Settings', 'love-coupons' ),
            array( $this, 'render_settings_meta_box' ),
            'love_coupon',
            'side',
            'high'
        );
    }
    
    /**
     * Render meta box
     */
    public function render_meta_box( $post ) {
        wp_nonce_field( 'love_coupon_save', 'love_coupon_nonce' );
        
        $terms = get_post_meta( $post->ID, '_love_coupon_terms', true );
        $expiry_date = get_post_meta( $post->ID, '_love_coupon_expiry_date', true );
        $usage_limit = get_post_meta( $post->ID, '_love_coupon_usage_limit', true );
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="love_coupon_terms"><?php _e( 'Terms & Conditions', 'love-coupons' ); ?></label>
                </th>
                <td>
                    <textarea id="love_coupon_terms" name="love_coupon_terms" rows="4" cols="50" class="large-text"><?php echo esc_textarea( $terms ); ?></textarea>
                    <p class="description"><?php _e( 'Enter the terms and conditions for this coupon.', 'love-coupons' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="love_coupon_expiry_date"><?php _e( 'Expiry Date', 'love-coupons' ); ?></label>
                </th>
                <td>
                    <input type="date" id="love_coupon_expiry_date" name="love_coupon_expiry_date" value="<?php echo esc_attr( $expiry_date ); ?>" />
                    <p class="description"><?php _e( 'Optional: Set an expiry date for this coupon.', 'love-coupons' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="love_coupon_usage_limit"><?php _e( 'Usage Limit', 'love-coupons' ); ?></label>
                </th>
                <td>
                    <input type="number" id="love_coupon_usage_limit" name="love_coupon_usage_limit" value="<?php echo esc_attr( $usage_limit ?: 1 ); ?>" min="1" />
                    <p class="description"><?php _e( 'How many times can this coupon be redeemed? (Default: 1)', 'love-coupons' ); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render recipients meta box
     */
    public function render_recipients_meta_box( $post ) {
        $assigned_to = get_post_meta( $post->ID, '_love_coupon_assigned_to', true );
        if ( ! is_array( $assigned_to ) ) {
            $assigned_to = array();
        }
        
        // Get all users
        $users = get_users( array(
            'orderby' => 'display_name',
            'order' => 'ASC',
        ) );
        
        ?>
        <p><?php _e( 'Select which users this coupon should be assigned to. Leave empty to make available to all users.', 'love-coupons' ); ?></p>
        
        <div style="border: 1px solid #ddd; padding: 10px; max-height: 300px; overflow-y: auto;">
            <?php foreach ( $users as $user ) : ?>
                <label style="display: block; margin-bottom: 8px; cursor: pointer;">
                    <input type="checkbox" name="love_coupon_assigned_to[]" value="<?php echo esc_attr( $user->ID ); ?>" 
                        <?php checked( in_array( $user->ID, $assigned_to ) ); ?> />
                    <strong><?php echo esc_html( $user->display_name ); ?></strong> (<?php echo esc_html( $user->user_email ); ?>)
                </label>
            <?php endforeach; ?>
        </div>
        
        <p class="description" style="margin-top: 10px;">
            <?php _e( 'Currently assigned to: ', 'love-coupons' ); ?>
            <strong id="assigned-count"><?php echo count( $assigned_to ); ?></strong> <?php _e( 'user(s)', 'love-coupons' ); ?>
        </p>
        
        <script>
        jQuery(document).ready(function($) {
            // Update count when checkboxes change
            $('input[name="love_coupon_assigned_to[]"]').on('change', function() {
                var count = $('input[name="love_coupon_assigned_to[]"]:checked').length;
                $('#assigned-count').text(count);
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render settings meta box
     */
    public function render_settings_meta_box( $post ) {
        $redeemed = get_post_meta( $post->ID, '_love_coupon_redeemed', true );
        $redemption_date = get_post_meta( $post->ID, '_love_coupon_redemption_date', true );
        $redemption_count = get_post_meta( $post->ID, '_love_coupon_redemption_count', true );
        $created_by = get_post_meta( $post->ID, '_love_coupon_created_by', true );
        
        if ( ! $created_by && $post->post_author ) {
            $created_by = $post->post_author;
        }
        
        $creator = $created_by ? get_user_by( 'id', $created_by ) : null;
        
        ?>
        <p><strong><?php _e( 'Created By:', 'love-coupons' ); ?></strong></p>
        <p><?php echo $creator ? esc_html( $creator->display_name ) : __( 'Unknown', 'love-coupons' ); ?></p>
        
        <p><strong><?php _e( 'Redemption Status:', 'love-coupons' ); ?></strong></p>
        <p><?php echo $redeemed ? __( 'Redeemed', 'love-coupons' ) : __( 'Available', 'love-coupons' ); ?></p>
        
        <?php if ( $redemption_date ) : ?>
            <p><strong><?php _e( 'Redemption Date:', 'love-coupons' ); ?></strong></p>
            <p><?php echo date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $redemption_date ) ); ?></p>
        <?php endif; ?>
        
        <p><strong><?php _e( 'Times Redeemed:', 'love-coupons' ); ?></strong></p>
        <p><?php echo intval( $redemption_count ); ?></p>
        
        <?php if ( $redeemed ) : ?>
            <p>
                <button type="button" class="button button-secondary" onclick="if(confirm('<?php _e( 'Are you sure you want to reset this coupon?', 'love-coupons' ); ?>')){
                    jQuery('#love_coupon_reset').val('1');
                    jQuery('#post').submit();
                }"><?php _e( 'Reset Coupon', 'love-coupons' ); ?></button>
                <input type="hidden" name="love_coupon_reset" id="love_coupon_reset" value="0" />
            </p>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Save meta data
     */
    public function save_meta( $post_id ) {
        // Verify nonce
        if ( ! isset( $_POST['love_coupon_nonce'] ) || ! wp_verify_nonce( $_POST['love_coupon_nonce'], 'love_coupon_save' ) ) {
            return;
        }
        
        // Check autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        
        // Check post type and permissions
        if ( 'love_coupon' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        
        // Save basic fields
        $fields = array(
            '_love_coupon_terms' => 'sanitize_textarea_field',
            '_love_coupon_expiry_date' => 'sanitize_text_field',
            '_love_coupon_usage_limit' => 'absint',
        );
        
        foreach ( $fields as $field => $sanitizer ) {
            $post_field = str_replace( '_love_coupon_', 'love_coupon_', $field );
            if ( isset( $_POST[ $post_field ] ) ) {
                $value = call_user_func( $sanitizer, $_POST[ $post_field ] );
                update_post_meta( $post_id, $field, $value );
            }
        }
        
        // Save assigned users
        if ( isset( $_POST['love_coupon_assigned_to'] ) ) {
            $assigned_to = array();
            if ( is_array( $_POST['love_coupon_assigned_to'] ) ) {
                foreach ( $_POST['love_coupon_assigned_to'] as $user_id ) {
                    $user_id = absint( $user_id );
                    if ( $user_id > 0 && get_user_by( 'id', $user_id ) ) {
                        $assigned_to[] = $user_id;
                    }
                }
            }
            update_post_meta( $post_id, '_love_coupon_assigned_to', array_unique( $assigned_to ) );
        } else {
            // Empty assignment means available to all
            delete_post_meta( $post_id, '_love_coupon_assigned_to' );
        }
        
        // Track who created the coupon (on first save)
        if ( ! get_post_meta( $post_id, '_love_coupon_created_by', true ) ) {
            update_post_meta( $post_id, '_love_coupon_created_by', get_current_user_id() );
        }
        
        // Handle reset
        if ( isset( $_POST['love_coupon_reset'] ) && '1' === $_POST['love_coupon_reset'] ) {
            delete_post_meta( $post_id, '_love_coupon_redeemed' );
            delete_post_meta( $post_id, '_love_coupon_redemption_date' );
            update_post_meta( $post_id, '_love_coupon_redemption_count', 0 );
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
            )
        ) );
        
        wp_enqueue_style(
            'love-coupons-css',
            LOVE_COUPONS_PLUGIN_URL . 'assets/css/love-coupons.css',
            array(),
            LOVE_COUPONS_VERSION
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
        if ( $post && has_shortcode( $post->post_content, 'love_coupons' ) ) {
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
        if ( ! check_ajax_referer( 'love_coupons_nonce', 'security', false ) ) {
            wp_send_json_error( __( 'Security check failed.', 'love-coupons' ) );
        }
        
        // Check if user is logged in
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( __( 'You must be logged in to redeem coupons.', 'love-coupons' ) );
        }
        
        // Validate coupon ID
        $coupon_id = isset( $_POST['coupon_id'] ) ? absint( $_POST['coupon_id'] ) : 0;
        
        if ( ! $coupon_id || 'love_coupon' !== get_post_type( $coupon_id ) ) {
            wp_send_json_error( __( 'Invalid coupon.', 'love-coupons' ) );
        }
        
        // Check if coupon exists and is published
        $coupon = get_post( $coupon_id );
        if ( ! $coupon || 'publish' !== $coupon->post_status ) {
            wp_send_json_error( __( 'Coupon not found or not available.', 'love-coupons' ) );
        }
        
        // Check if user can access this coupon
        $current_user = wp_get_current_user();
        if ( ! $this->user_can_access_coupon( $coupon_id, $current_user->ID ) ) {
            wp_send_json_error( __( 'You do not have access to this coupon.', 'love-coupons' ) );
        }
        
        // Check if already redeemed
        $redeemed = get_post_meta( $coupon_id, '_love_coupon_redeemed', true );
        if ( $redeemed ) {
            wp_send_json_error( __( 'This coupon has already been redeemed.', 'love-coupons' ) );
        }
        
        // Check expiry date
        $expiry_date = get_post_meta( $coupon_id, '_love_coupon_expiry_date', true );
        if ( $expiry_date && strtotime( $expiry_date ) < time() ) {
            wp_send_json_error( __( 'This coupon has expired.', 'love-coupons' ) );
        }
        
        // Check usage limit
        $usage_limit = get_post_meta( $coupon_id, '_love_coupon_usage_limit', true );
        $redemption_count = get_post_meta( $coupon_id, '_love_coupon_redemption_count', true );
        
        if ( $usage_limit && $redemption_count >= $usage_limit ) {
            wp_send_json_error( __( 'This coupon has reached its usage limit.', 'love-coupons' ) );
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
        update_post_meta( $coupon_id, '_love_coupon_redeemed', true );
        update_post_meta( $coupon_id, '_love_coupon_redemption_date', current_time( 'mysql' ) );
        update_post_meta( $coupon_id, '_love_coupon_redemption_count', intval( $redemption_count ) + 1 );
        update_post_meta( $coupon_id, '_love_coupon_redemption_data', $redemption_data );
        
        // Send notification email
        $this->send_redemption_email( $coupon_id, $current_user );
        
        // Log the redemption
        $this->log_redemption( $coupon_id, $current_user );
        
        wp_send_json_success( __( 'Coupon redeemed successfully!', 'love-coupons' ) );
    }
    
    /**
     * Send redemption notification email
     */
    private function send_redemption_email( $coupon_id, $user ) {
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
            $this->get_user_ip()
        );
        
        wp_mail( $admin_email, $subject, $message );
    }
    
    /**
     * Log coupon redemption
     */
    private function log_redemption( $coupon_id, $user ) {
        error_log( sprintf(
            'Love Coupons: Coupon %d redeemed by user %d (%s) at %s',
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
     * Display coupons shortcode with tab interface
     */
    public function display_coupons_shortcode( $atts ) {
        // Parse attributes
        $atts = shortcode_atts( array(
            'limit' => -1,
            'category' => '',
            'show_expired' => 'yes',
        ), $atts, 'love_coupons' );
        
        if ( ! is_user_logged_in() ) {
            return '<div class="love-coupons-login-message"><p>' . __( 'Please log in to see the coupons!', 'love-coupons' ) . '</p></div>';
        }
        
        $current_user_id = get_current_user_id();
        
        ob_start();
        ?>
        <div class="love-coupons-wrapper">
            <!-- Tab Navigation -->
            <div class="love-coupons-tabs">
                <button class="love-tab-button active" data-tab="my-coupons" aria-selected="true">
                    <?php _e( 'My Coupons', 'love-coupons' ); ?>
                </button>
                <button class="love-tab-button" data-tab="posted-coupons" aria-selected="false">
                    <?php _e( 'Posted Coupons', 'love-coupons' ); ?>
                </button>
            </div>
            
            <!-- Tab Content -->
            <div class="love-tabs-content">
                
                <!-- My Coupons Tab -->
                <div class="love-tab-pane active" id="love-tab-my-coupons" data-tab="my-coupons">
                    <?php echo $this->render_my_coupons( $current_user_id, $atts ); ?>
                </div>
                
                <!-- Posted Coupons Tab -->
                <div class="love-tab-pane" id="love-tab-posted-coupons" data-tab="posted-coupons">
                    <?php echo $this->render_posted_coupons( $current_user_id, $atts ); ?>
                </div>
                
            </div>
        </div>
        
        <script>
        (function() {
            const tabButtons = document.querySelectorAll('.love-tab-button');
            const tabPanes = document.querySelectorAll('.love-tab-pane');
            
            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const tabName = this.getAttribute('data-tab');
                    
                    // Remove active class from all buttons and panes
                    tabButtons.forEach(btn => {
                        btn.classList.remove('active');
                        btn.setAttribute('aria-selected', 'false');
                    });
                    tabPanes.forEach(pane => pane.classList.remove('active'));
                    
                    // Add active class to clicked button and corresponding pane
                    this.classList.add('active');
                    this.setAttribute('aria-selected', 'true');
                    document.getElementById('love-tab-' + tabName).classList.add('active');
                });
            });
        })();
        </script>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render "My Coupons" tab content
     */
    private function render_my_coupons( $user_id, $atts ) {
        $args = array(
            'post_type' => 'love_coupon',
            'post_status' => 'publish',
            'posts_per_page' => intval( $atts['limit'] ),
        );
        
        $query = new WP_Query( $args );
        
        // Organize coupons by state
        $available_coupons = array();
        $redeemed_coupons = array();
        $expired_coupons = array();
        
        while ( $query->have_posts() ) {
            $query->the_post();
            $coupon_id = get_the_ID();
            
            // Check if coupon is assigned to current user
            if ( ! $this->user_can_access_coupon( $coupon_id, $user_id ) ) {
                continue;
            }
            
            $redeemed = get_post_meta( $coupon_id, '_love_coupon_redeemed', true );
            $expiry_date = get_post_meta( $coupon_id, '_love_coupon_expiry_date', true );
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
        
        if ( empty( $available_coupons ) && empty( $redeemed_coupons ) && empty( $expired_coupons ) ) {
            echo '<div class="love-coupons-empty"><p>' . __( 'No coupons available for you yet.', 'love-coupons' ) . '</p></div>';
            return ob_get_clean();
        }
        
        echo '<div class="love-coupons-container">';
        
        // Available Coupons Section
        if ( ! empty( $available_coupons ) ) {
            echo '<div class="love-coupons-section">';
            echo '<h3 class="love-coupons-section-title">' . __( 'Available', 'love-coupons' ) . '</h3>';
            echo '<div class="love-coupons-grid">';
            
            foreach ( $available_coupons as $coupon_id ) {
                $this->render_coupon_item( $coupon_id );
            }
            
            echo '</div></div>';
        }
        
        // Redeemed Coupons Section
        if ( ! empty( $redeemed_coupons ) ) {
            if ( ! empty( $available_coupons ) ) {
                echo '<hr class="love-coupons-separator">';
            }
            
            echo '<div class="love-coupons-section">';
            echo '<h3 class="love-coupons-section-title">' . __( 'Redeemed', 'love-coupons' ) . '</h3>';
            echo '<div class="love-coupons-grid">';
            
            foreach ( $redeemed_coupons as $coupon_id ) {
                $this->render_coupon_item( $coupon_id );
            }
            
            echo '</div></div>';
        }
        
        // Expired Coupons Section
        if ( ! empty( $expired_coupons ) && 'yes' === $atts['show_expired'] ) {
            if ( ! empty( $available_coupons ) || ! empty( $redeemed_coupons ) ) {
                echo '<hr class="love-coupons-separator">';
            }
            
            echo '<div class="love-coupons-section">';
            echo '<h3 class="love-coupons-section-title">' . __( 'Expired', 'love-coupons' ) . '</h3>';
            echo '<div class="love-coupons-grid">';
            
            foreach ( $expired_coupons as $coupon_id ) {
                $this->render_coupon_item( $coupon_id );
            }
            
            echo '</div></div>';
        }
        
        echo '</div>';
        
        return ob_get_clean();
    }
    
    /**
     * Render "Posted Coupons" tab content
     */
    private function render_posted_coupons( $user_id, $atts ) {
        ob_start();
        
        // Get coupons created by user
        $args = array(
            'post_type' => 'love_coupon',
            'post_status' => 'publish',
            'posts_per_page' => intval( $atts['limit'] ),
            'meta_query' => array(
                array(
                    'key' => '_love_coupon_created_by',
                    'value' => $user_id,
                    'compare' => '=',
                ),
            ),
        );
        
        $query = new WP_Query( $args );
        
        echo '<div class="love-posted-coupons-wrapper">';
        
        // New Coupon Form
        echo '<div class="love-create-coupon-form-wrapper">';
        echo '<h3>' . __( 'Create New Coupon', 'love-coupons' ) . '</h3>';
        $this->render_create_coupon_form( $user_id );
        echo '</div>';
        
        // Posted Coupons List
        echo '<div class="love-posted-coupons-list">';
        echo '<h3>' . __( 'Your Coupons', 'love-coupons' ) . '</h3>';
        
        if ( ! $query->have_posts() ) {
            echo '<p>' . __( 'You haven\'t posted any coupons yet.', 'love-coupons' ) . '</p>';
        } else {
            echo '<div class="love-coupons-grid">';
            
            while ( $query->have_posts() ) {
                $query->the_post();
                $coupon_id = get_the_ID();
                
                $redeemed = get_post_meta( $coupon_id, '_love_coupon_redeemed', true );
                $assigned_to = get_post_meta( $coupon_id, '_love_coupon_assigned_to', true );
                
                echo '<div class="love-coupon-posted-item" data-coupon-id="' . esc_attr( $coupon_id ) . '">';
                echo '<h4>' . esc_html( get_the_title() ) . '</h4>';
                
                if ( is_array( $assigned_to ) && ! empty( $assigned_to ) ) {
                    $recipient = get_user_by( 'id', $assigned_to[0] );
                    if ( $recipient ) {
                        echo '<p><strong>' . __( 'Sent to:', 'love-coupons' ) . '</strong> ' . esc_html( $recipient->display_name ) . '</p>';
                    }
                }
                
                echo '<p><strong>' . __( 'Status:', 'love-coupons' ) . '</strong> ';
                if ( $redeemed ) {
                    echo '<span style="color: #d63638;">✓ ' . __( 'Redeemed', 'love-coupons' ) . '</span>';
                } else {
                    echo '<span style="color: #00a32a;">✓ ' . __( 'Available', 'love-coupons' ) . '</span>';
                }
                echo '</p>';
                
                echo '</div>';
            }
            
            echo '</div>';
        }
        
        wp_reset_postdata();
        echo '</div></div>';
        
        return ob_get_clean();
    }
    
    /**
     * Render create coupon form
     */
    private function render_create_coupon_form( $user_id ) {
        // Get users this user can post to
        $allowed_recipients = $this->get_allowed_recipients_for_user( $user_id );
        
        if ( empty( $allowed_recipients ) ) {
            echo '<p style="color: #d63638;">' . __( 'You don\'t have permission to post coupons to any user.', 'love-coupons' ) . '</p>';
            return;
        }
        
        wp_nonce_field( 'love_create_coupon', 'love_create_coupon_nonce' );
        
        ?>
        <form class="love-create-coupon-form" id="love-create-coupon-form">
            <div class="form-group">
                <label for="coupon_title"><?php _e( 'Coupon Title', 'love-coupons' ); ?> <span class="required">*</span></label>
                <input type="text" name="coupon_title" id="coupon_title" required placeholder="<?php _e( 'Enter coupon title', 'love-coupons' ); ?>" />
            </div>
            
            <div class="form-group">
                <label for="coupon_recipient"><?php _e( 'Send To', 'love-coupons' ); ?> <span class="required">*</span></label>
                <select name="coupon_recipient" id="coupon_recipient" required>
                    <option value=""><?php _e( 'Select a user', 'love-coupons' ); ?></option>
                    <?php foreach ( $allowed_recipients as $recipient_id ) : 
                        $recipient = get_user_by( 'id', $recipient_id );
                        if ( $recipient ) :
                    ?>
                        <option value="<?php echo esc_attr( $recipient_id ); ?>">
                            <?php echo esc_html( $recipient->display_name . ' (' . $recipient->user_email . ')' ); ?>
                        </option>
                    <?php endif; endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="coupon_terms"><?php _e( 'Terms & Conditions', 'love-coupons' ); ?></label>
                <textarea name="coupon_terms" id="coupon_terms" rows="4" placeholder="<?php _e( 'Enter terms and conditions', 'love-coupons' ); ?>"></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="coupon_expiry_date"><?php _e( 'Expiry Date', 'love-coupons' ); ?></label>
                    <input type="date" name="coupon_expiry_date" id="coupon_expiry_date" />
                </div>
                
                <div class="form-group">
                    <label for="coupon_usage_limit"><?php _e( 'Usage Limit', 'love-coupons' ); ?></label>
                    <input type="number" name="coupon_usage_limit" id="coupon_usage_limit" value="1" min="1" />
                </div>
            </div>
            
            <button type="submit" class="button button-primary">
                <?php _e( 'Create Coupon', 'love-coupons' ); ?>
            </button>
            
            <div class="form-message" style="display: none;"></div>
        </form>
        <?php
    }
    
    /**
     * Get allowed recipients for a user
     */
    private function get_allowed_recipients_for_user( $user_id ) {
        $all_users = get_users( array( 'fields' => 'ID' ) );
        
        // Get admin settings for this user
        $user_restrictions = get_option( 'love_coupons_posting_restrictions', array() );
        
        // If no restrictions set, user can post to all other users
        if ( empty( $user_restrictions[ $user_id ] ) ) {
            return array_filter( $all_users, function( $uid ) use ( $user_id ) {
                return $uid !== $user_id;
            });
        }
        
        $allowed = $user_restrictions[ $user_id ];
        
        // If 'all' is set, user can post to everyone except themselves
        if ( in_array( 'all', $allowed ) ) {
            return array_filter( $all_users, function( $uid ) use ( $user_id ) {
                return $uid !== $user_id;
            });
        }
        
        // Return specific allowed recipients
        return array_filter( $allowed, function( $uid ) use ( $user_id ) {
            return $uid !== $user_id && in_array( $uid, $all_users );
        });
    }
    
    /**
     * Render individual coupon item
     */
    private function render_coupon_item( $coupon_id ) {
        $redeemed = get_post_meta( $coupon_id, '_love_coupon_redeemed', true );
        $terms = get_post_meta( $coupon_id, '_love_coupon_terms', true );
        $expiry_date = get_post_meta( $coupon_id, '_love_coupon_expiry_date', true );
        $is_expired = $expiry_date && strtotime( $expiry_date ) < time();
        
        $classes = array( 'love-coupon' );
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
                        <small><?php printf( __( 'Expires: %s', 'love-coupons' ), date_i18n( get_option( 'date_format' ), strtotime( $expiry_date ) ) ); ?></small>
                    </div>
                <?php endif; ?>
                
                <?php if ( $terms ) : ?>
                    <details class="coupon-terms">
                        <summary><?php _e( 'Terms & Conditions', 'love-coupons' ); ?></summary>
                        <div class="terms-content"><?php echo wp_kses_post( nl2br( $terms ) ); ?></div>
                    </details>
                <?php endif; ?>
                
                <div class="coupon-actions">
                    <?php if ( $redeemed ) : ?>
                        <button class="button button-redeemed" disabled>
                            <span class="dashicons dashicons-yes"></span>
                            <?php _e( 'Redeemed', 'love-coupons' ); ?>
                        </button>
                    <?php elseif ( $is_expired ) : ?>
                        <button class="button button-expired" disabled>
                            <span class="dashicons dashicons-clock"></span>
                            <?php _e( 'Expired', 'love-coupons' ); ?>
                        </button>
                    <?php else : ?>
                        <button class="button button-primary redeem-button" data-coupon-id="<?php echo esc_attr( $coupon_id ); ?>">
                            <span class="dashicons dashicons-tickets-alt"></span>
                            <?php _e( 'Redeem', 'love-coupons' ); ?>
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
        
        if ( is_page() && $post && has_shortcode( $post->post_content, 'love_coupons' ) ) {
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
        
        echo '<link rel="manifest" href="' . esc_url( LOVE_COUPONS_PLUGIN_URL . 'manifest.json' ) . '">' . "\n";
        echo '<meta name="theme-color" content="#2c6e49">' . "\n";
        echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
        echo '<meta name="apple-mobile-web-app-status-bar-style" content="default">' . "\n";
        echo '<link rel="apple-touch-icon" href="' . esc_url( LOVE_COUPONS_PLUGIN_URL . 'assets/images/icon192.png' ) . '">' . "\n";
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
    
    /**
     * AJAX handler for creating coupons from frontend
     */
    public function ajax_create_coupon() {
        // Verify nonce
        if ( ! check_ajax_referer( 'love_create_coupon', 'nonce', false ) ) {
            wp_send_json_error( __( 'Security check failed.', 'love-coupons' ) );
        }
        
        // Check if user is logged in
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( __( 'You must be logged in to create coupons.', 'love-coupons' ) );
        }
        
        $current_user_id = get_current_user_id();
        
        // Validate inputs
        $title = isset( $_POST['coupon_title'] ) ? sanitize_text_field( $_POST['coupon_title'] ) : '';
        $recipient_id = isset( $_POST['coupon_recipient'] ) ? absint( $_POST['coupon_recipient'] ) : 0;
        $terms = isset( $_POST['coupon_terms'] ) ? sanitize_textarea_field( $_POST['coupon_terms'] ) : '';
        $expiry_date = isset( $_POST['coupon_expiry_date'] ) ? sanitize_text_field( $_POST['coupon_expiry_date'] ) : '';
        $usage_limit = isset( $_POST['coupon_usage_limit'] ) ? absint( $_POST['coupon_usage_limit'] ) : 1;
        
        // Validate title
        if ( empty( $title ) ) {
            wp_send_json_error( __( 'Coupon title is required.', 'love-coupons' ) );
        }
        
        // Validate recipient
        if ( ! $recipient_id || ! get_user_by( 'id', $recipient_id ) ) {
            wp_send_json_error( __( 'Invalid recipient selected.', 'love-coupons' ) );
        }
        
        // Check if user can post to this recipient
        $allowed_recipients = $this->get_allowed_recipients_for_user( $current_user_id );
        if ( ! in_array( $recipient_id, $allowed_recipients ) ) {
            wp_send_json_error( __( 'You don\'t have permission to post coupons to this user.', 'love-coupons' ) );
        }
        
        // Create the coupon post
        $coupon_data = array(
            'post_type' => 'love_coupon',
            'post_title' => $title,
            'post_status' => 'publish',
            'post_author' => $current_user_id,
        );
        
        $coupon_id = wp_insert_post( $coupon_data );
        
        if ( is_wp_error( $coupon_id ) ) {
            wp_send_json_error( __( 'Failed to create coupon.', 'love-coupons' ) );
        }
        
        // Save meta data
        update_post_meta( $coupon_id, '_love_coupon_created_by', $current_user_id );
        update_post_meta( $coupon_id, '_love_coupon_assigned_to', array( $recipient_id ) );
        update_post_meta( $coupon_id, '_love_coupon_terms', $terms );
        update_post_meta( $coupon_id, '_love_coupon_expiry_date', $expiry_date );
        update_post_meta( $coupon_id, '_love_coupon_usage_limit', $usage_limit );
        update_post_meta( $coupon_id, '_love_coupon_redemption_count', 0 );
        
        wp_send_json_success( array(
            'message' => __( 'Coupon created successfully!', 'love-coupons' ),
            'coupon_id' => $coupon_id,
        ) );
    }
    
    /**
     * Add admin settings page
     */
    public function add_admin_settings_page() {
        add_submenu_page(
            'edit.php?post_type=love_coupon',
            __( 'Coupon Posting Permissions', 'love-coupons' ),
            __( 'Posting Permissions', 'love-coupons' ),
            'manage_options',
            'love_coupons_permissions',
            array( $this, 'render_admin_settings_page' )
        );
    }
    
    /**
     * Render admin settings page
     */
    public function render_admin_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to access this page.', 'love-coupons' ) );
        }
        
        // Handle form submission
        if ( isset( $_POST['love_coupons_save_permissions'] ) && check_admin_referer( 'love_coupons_permissions_nonce' ) ) {
            $restrictions = isset( $_POST['love_coupons_restrictions'] ) ? $_POST['love_coupons_restrictions'] : array();
            
            // Sanitize the data
            $sanitized_restrictions = array();
            foreach ( $restrictions as $user_id => $allowed ) {
                $user_id = absint( $user_id );
                if ( $user_id > 0 ) {
                    $sanitized_restrictions[ $user_id ] = array();
                    
                    if ( isset( $allowed['all'] ) && $allowed['all'] === 'on' ) {
                        $sanitized_restrictions[ $user_id ][] = 'all';
                    } else {
                        if ( isset( $allowed['users'] ) && is_array( $allowed['users'] ) ) {
                            foreach ( $allowed['users'] as $recipient_id ) {
                                $recipient_id = absint( $recipient_id );
                                if ( $recipient_id > 0 && get_user_by( 'id', $recipient_id ) ) {
                                    $sanitized_restrictions[ $user_id ][] = $recipient_id;
                                }
                            }
                        }
                    }
                }
            }
            
            update_option( 'love_coupons_posting_restrictions', $sanitized_restrictions );
            echo '<div class="notice notice-success"><p>' . __( 'Permissions saved successfully!', 'love-coupons' ) . '</p></div>';
        }
        
        $restrictions = get_option( 'love_coupons_posting_restrictions', array() );
        $all_users = get_users( array( 'orderby' => 'display_name' ) );
        
        ?>
        <div class="wrap">
            <h1><?php _e( 'Coupon Posting Permissions', 'love-coupons' ); ?></h1>
            
            <form method="post">
                <?php wp_nonce_field( 'love_coupons_permissions_nonce' ); ?>
                
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e( 'User', 'love-coupons' ); ?></th>
                            <th><?php _e( 'Can Post To', 'love-coupons' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $all_users as $user ) : ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html( $user->display_name ); ?></strong><br>
                                    <small><?php echo esc_html( $user->user_email ); ?></small>
                                </td>
                                <td>
                                    <label>
                                        <input type="checkbox" name="love_coupons_restrictions[<?php echo esc_attr( $user->ID ); ?>][all]" 
                                            <?php checked( ! empty( $restrictions[ $user->ID ] ) && in_array( 'all', $restrictions[ $user->ID ] ) ); ?> />
                                        <?php _e( 'Can post to all users', 'love-coupons' ); ?>
                                    </label>
                                    
                                    <fieldset style="margin-top: 10px; margin-left: 20px; border-left: 2px solid #ddd; padding-left: 10px;">
                                        <legend><?php _e( 'Or select specific users:', 'love-coupons' ); ?></legend>
                                        <div style="max-height: 200px; overflow-y: auto;">
                                            <?php foreach ( $all_users as $recipient ) : 
                                                if ( $recipient->ID === $user->ID ) continue;
                                                $is_checked = ! empty( $restrictions[ $user->ID ] ) && 
                                                             ! in_array( 'all', $restrictions[ $user->ID ] ) && 
                                                             in_array( $recipient->ID, $restrictions[ $user->ID ] );
                                            ?>
                                                <label style="display: block; margin-bottom: 5px;">
                                                    <input type="checkbox" 
                                                        name="love_coupons_restrictions[<?php echo esc_attr( $user->ID ); ?>][users][]" 
                                                        value="<?php echo esc_attr( $recipient->ID ); ?>"
                                                        <?php checked( $is_checked ); ?> />
                                                    <?php echo esc_html( $recipient->display_name ); ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </fieldset>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <br>
                
                <?php submit_button( __( 'Save Permissions', 'love-coupons' ), 'primary', 'love_coupons_save_permissions' ); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Check if PWA should be loaded
     */
    private function should_load_pwa() {
        return is_user_logged_in() && $this->should_enqueue_assets();
    }
    
    /**
     * Check if user can access a specific coupon
     * 
     * @param int $coupon_id The coupon ID
     * @param int $user_id The user ID
     * @return bool True if user can access the coupon
     */
    private function user_can_access_coupon( $coupon_id, $user_id ) {
        // Admins can see all coupons
        if ( user_can( $user_id, 'manage_options' ) ) {
            return true;
        }
        
        $assigned_to = get_post_meta( $coupon_id, '_love_coupon_assigned_to', true );
        
        // If no assignment restriction, all logged-in users can access
        if ( empty( $assigned_to ) || ! is_array( $assigned_to ) ) {
            return true;
        }
        
        // Check if user is in the assigned list
        return in_array( $user_id, $assigned_to );
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
            $role->add_cap( 'manage_love_coupons' );
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
Love_Coupons_Plugin::get_instance();
