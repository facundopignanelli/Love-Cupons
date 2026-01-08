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
    private $assets;
    private $admin;
    private $ajax;
    private $shortcodes;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    private function load_dependencies() {
        require_once LOVE_COUPONS_PLUGIN_DIR . 'includes/class-core.php';
        require_once LOVE_COUPONS_PLUGIN_DIR . 'includes/class-assets.php';
        require_once LOVE_COUPONS_PLUGIN_DIR . 'includes/class-admin.php';
        require_once LOVE_COUPONS_PLUGIN_DIR . 'includes/class-ajax.php';
        require_once LOVE_COUPONS_PLUGIN_DIR . 'includes/class-shortcodes.php';

        $this->assets     = new Love_Coupons_Assets();
        $this->admin      = new Love_Coupons_Admin();
        $this->ajax       = new Love_Coupons_Ajax();
        $this->shortcodes = new Love_Coupons_Shortcodes();
    }

    private function init_hooks() {
        add_action( 'init', array( $this, 'register_cpt' ) );
        add_action( 'init', array( $this, 'load_textdomain' ) );
        add_action( 'template_redirect', array( $this, 'force_login' ) );

        add_action( 'wp_enqueue_scripts', array( $this->assets, 'enqueue_assets' ) );
        add_action( 'wp_head', array( $this->assets, 'add_pwa_manifest' ) );
        add_action( 'wp_footer', array( $this->assets, 'register_service_worker' ) );

        add_filter( 'manage_love_coupon_posts_columns', array( $this->admin, 'add_admin_columns' ) );
        add_action( 'manage_love_coupon_posts_custom_column', array( $this->admin, 'render_admin_columns' ), 10, 2 );
        add_action( 'add_meta_boxes', array( $this->admin, 'add_meta_boxes' ) );
        add_action( 'save_post', array( $this->admin, 'save_meta' ) );
        add_action( 'admin_menu', array( $this->admin, 'add_admin_settings_page' ) );

        add_action( 'wp_ajax_love_coupons_redeem', array( $this->ajax, 'ajax_redeem_coupon' ) );
        add_action( 'wp_ajax_nopriv_love_coupons_redeem', array( $this->ajax, 'ajax_redeem_coupon' ) );
        add_action( 'wp_ajax_love_coupons_create', array( $this->ajax, 'ajax_create_coupon' ) );

        $this->shortcodes->register();

        register_activation_hook( LOVE_COUPONS_PLUGIN_FILE, array( $this, 'activate' ) );
        register_deactivation_hook( LOVE_COUPONS_PLUGIN_FILE, array( $this, 'deactivate' ) );
    }
    
    public function load_textdomain() {
        load_plugin_textdomain( 'love-coupons', false, dirname( plugin_basename( LOVE_COUPONS_PLUGIN_FILE ) ) . '/languages' );
    }
    
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
    
    public function force_login() {
        if ( is_admin() || is_user_logged_in() ) {
            return;
        }

        global $post;
        if ( is_page() && $post && ( has_shortcode( $post->post_content, 'love_coupons' ) || has_shortcode( $post->post_content, 'love_coupons_submit' ) ) ) {
            auth_redirect();
        }
    }

    public function activate() {
        $this->register_cpt();
        flush_rewrite_rules();
    }

    public function deactivate() {
        flush_rewrite_rules();
    }
}

Love_Coupons_Plugin::get_instance();
