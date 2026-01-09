<?php
/*
Plugin Name: Love Coupons
Version: 1.2
Author: Facundo Pignanelli
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'LOVE_COUPONS_VERSION', '1.2' );
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

        // Serve service worker with correct headers and root scope allowance
        add_action( 'template_redirect', array( $this, 'maybe_serve_service_worker' ) );
        $this->init_hooks();

    }

    /**
     * Serve service worker JS with Service-Worker-Allowed header for root scope.
     */
    public function maybe_serve_service_worker() {
        if ( isset( $_GET['love_coupons_sw'] ) && '1' === $_GET['love_coupons_sw'] ) {
            // Allow SW to control root scope
            header( 'Content-Type: application/javascript; charset=utf-8' );
            header( 'Service-Worker-Allowed: /' );
            header( 'Cache-Control: no-store, no-cache, must-revalidate' );
            $sw_path = LOVE_COUPONS_PLUGIN_DIR . 'service-worker.js';
            if ( file_exists( $sw_path ) ) {
                // Pass the resolved plugin base URL so the worker can cache the right paths regardless of folder casing
                $plugin_base = trailingslashit( LOVE_COUPONS_PLUGIN_URL );
                echo "const LOVE_COUPONS_PLUGIN_BASE = '" . esc_url_raw( $plugin_base ) . "';\n";
                readfile( $sw_path );
            } else {
                echo "// Service worker file not found";
            }
            exit;
        }
    }

    private function get_menu_icon() {
        $svg = '<svg width="20" height="20" viewBox="0 0 2816 1536" xmlns="http://www.w3.org/2000/svg" fill="white"><path d="M1056.5,314.848C1052.907,317.051 1001.245,369.776 999.376,373.146C996.828,377.741 997.681,383.843 1001.865,390.942C1007.183,399.965 1008.414,404.204 1008.417,413.5C1008.421,426.384 1003.397,438.346 994.622,446.347C992.905,447.912 988.575,450.608 985,452.337C979.71,454.895 976.982,455.547 970.345,455.839C960.332,456.279 953.942,454.561 945,449.024C937.586,444.433 933.16,443.277 929.233,444.904C926.492,446.04 914.673,457.37 886.265,486.095C872.498,500.015 870.949,501.935 870.187,506.018C868.973,512.533 870.336,515.497 878.493,524.081C886.751,532.77 895.828,544.609 898.843,550.622C909.509,571.893 911.83,597.974 904.974,619.5C899.056,638.08 891.962,650.083 879.758,662.164C875.5,666.379 871.562,671.021 871.008,672.479C869.454,676.568 869.816,682.188 871.848,685.5C873.542,688.262 924.011,739.788 998.642,814.953L1028.88,845.407L1082.477,791.453C1111.956,761.779 1173.746,699.54 1219.787,653.145C1265.829,606.75 1307.48,564.9 1312.345,560.145C1317.209,555.39 1327.559,545.013 1335.345,537.085C1343.13,529.157 1358.388,513.785 1369.25,502.925C1380.112,492.066 1389,482.576 1389,481.837C1389,480.329 1375.772,466.637 1317.651,407.979C1295.568,385.693 1265.676,355.449 1251.224,340.77C1223.471,312.58 1222.056,311.507 1215.784,313.892C1214.302,314.455 1210.482,317.678 1207.295,321.054C1197.176,331.771 1180.515,342.51 1166.398,347.415C1149.83,353.171 1130.202,353.16 1113,347.383C1102.482,343.851 1085.859,332.697 1076,322.555C1066.43,312.712 1062.509,311.162 1056.5,314.848M1593.465,314.4C1588.751,316.454 1565.975,339.71 1566.641,341.79C1567.029,343.002 1566.924,343.133 1566.278,342.238C1565.62,341.327 1555.959,350.494 1531.498,375.238C1442.478,465.289 1327.263,581.54 1325.222,583.369C1323.969,584.491 1323.176,586.016 1323.46,586.757C1323.744,587.498 1323.556,587.844 1323.042,587.526C1322.528,587.208 1295.112,614.072 1262.118,647.224C1229.124,680.376 1168.757,741.025 1127.969,782C1087.18,822.975 1053.123,857.514 1052.286,858.754C1050.354,861.617 1050.416,868.46 1052.402,871.491C1053.241,872.771 1055.132,875.097 1056.604,876.659C1060.416,880.704 1064.684,890.291 1066.469,898.815C1070.649,918.786 1065.344,940.301 1052.615,955C1046.579,961.971 1044.323,967.168 1045.427,971.564C1046.456,975.665 1129.25,1058.461 1134.391,1060.53C1139.543,1062.604 1143.009,1061.739 1150.734,1056.45C1159.183,1050.664 1165.637,1048.649 1175.5,1048.718C1186.551,1048.794 1193.31,1051.487 1201.37,1059.028C1208.381,1065.586 1212.212,1071.877 1214.467,1080.535C1217.541,1092.336 1215.757,1102.492 1208.514,1114.423C1203.596,1122.525 1202.728,1127.698 1205.43,1132.8C1207.51,1136.725 1291.908,1220.493 1295.7,1222.395C1299.461,1224.281 1304.258,1224.467 1307.29,1222.845C1308.477,1222.209 1311.329,1219.927 1313.627,1217.773C1326.219,1205.971 1338.851,1201.055 1356.685,1201.017C1372.912,1200.983 1384.65,1205.103 1396.572,1215.019C1402.814,1220.211 1406.815,1221.639 1411.957,1220.509C1415.422,1219.748 1410.228,1224.891 1590.85,1043.391C1784.178,849.123 1894.93,737.479 1928.188,703.335C1942.359,688.787 1946.007,684.478 1946.535,681.666C1947.743,675.228 1946.224,672.071 1937.573,663.036C1922.737,647.543 1915.432,635.086 1910.282,616.5C1907.825,607.631 1907.517,604.859 1907.559,592C1907.604,578.325 1907.796,576.931 1910.923,567.5C1912.746,562 1915.287,555.423 1916.568,552.884C1920.746,544.607 1929.715,532.405 1936.785,525.38C1940.594,521.596 1944.268,517.437 1944.95,516.138C1946.331,513.509 1946.56,504.817 1945.307,502.594C1943.708,499.755 1892.048,447.459 1889.154,445.75C1885.481,443.581 1882.398,443.594 1878.452,445.798C1876.776,446.734 1871.826,449.3 1867.452,451.5C1859.683,455.408 1859.258,455.5 1849,455.5C1839.286,455.5 1838,455.263 1831.82,452.335C1828.147,450.595 1823.065,447.258 1820.528,444.919C1811.583,436.674 1805.756,418.954 1808.01,406.851C1809.238,400.254 1810.527,397.031 1814.724,390.059C1819.085,382.816 1819.188,377.199 1815.077,370.946C1813.469,368.501 1800.306,354.462 1785.825,339.75C1757.855,311.333 1758.079,311.499 1751.315,314.07C1749.767,314.659 1745.125,318.403 1741,322.391C1728.208,334.759 1715.701,342.848 1702.404,347.354C1671.143,357.947 1639.633,349.719 1613,324.009C1605.587,316.853 1599.932,312.907 1597.282,313.039C1596.852,313.061 1595.134,313.673 1593.465,314.4M1247,338C1247,338.55 1246.802,339 1246.559,339C1246.316,339 1245.84,338.55 1245.5,338C1245.16,337.45 1245.359,337 1245.941,337C1246.523,337 1247,337.45 1247,338M1824.345,452.543C1824.019,453.392 1823.538,453.872 1823.276,453.61C1823.014,453.348 1823.096,452.653 1823.459,452.067C1824.445,450.471 1825.021,450.781 1824.345,452.543M1358.345,510.543C1358.019,511.392 1357.538,511.872 1357.276,511.61C1357.014,511.348 1357.096,510.653 1357.459,510.067C1358.445,508.471 1359.021,508.781 1358.345,510.543M1062.683,988.188C1062.364,988.985 1062.127,988.748 1062.079,987.583C1062.036,986.529 1062.272,985.939 1062.604,986.271C1062.936,986.603 1062.972,987.466 1062.683,988.188M1115.382,1039.552C1115.723,1040.442 1115.555,1040.843 1114.989,1040.493C1114.445,1040.157 1114,1039.459 1114,1038.941C1114,1037.503 1114.717,1037.82 1115.382,1039.552"/></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode( $svg );
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
        add_action( 'wp_ajax_love_coupons_update', array( $this->ajax, 'ajax_update_coupon' ) );
        add_action( 'wp_ajax_love_coupons_delete', array( $this->ajax, 'ajax_delete_coupon' ) );
        add_action( 'wp_ajax_love_coupons_get_coupon', array( $this->ajax, 'ajax_get_coupon' ) );
        add_action( 'wp_ajax_love_coupons_save_preferences', array( $this->ajax, 'ajax_save_preferences' ) );
        add_action( 'wp_ajax_love_coupons_save_push_subscription', array( $this->ajax, 'ajax_save_push_subscription' ) );
        add_action( 'wp_ajax_love_coupons_get_nonce', array( $this->ajax, 'ajax_get_nonce' ) );
        add_action( 'wp_ajax_love_coupons_send_feedback', array( $this->ajax, 'ajax_send_feedback' ) );

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
            'menu_icon'          => $this->get_menu_icon(),
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
        if ( ! $post || ! is_page() ) {
            return;
        }

        $shortcodes = array( 'love_coupons', 'love_coupons_submit', 'love_coupons_created', 'love_coupons_preferences', 'love_coupons_dashboard' );
        foreach ( $shortcodes as $code ) {
            if ( has_shortcode( $post->post_content, $code ) ) {
                auth_redirect();
                break;
            }
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
