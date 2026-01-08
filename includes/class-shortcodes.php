<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once LOVE_COUPONS_PLUGIN_DIR . 'includes/class-core.php';

class Love_Coupons_Shortcodes {
    public function register() {
        add_shortcode( 'love_coupons', array( $this, 'display_coupons_shortcode' ) );
        add_shortcode( 'love_coupons_submit', array( $this, 'display_coupons_submit_shortcode' ) );
        add_shortcode( 'love_coupons_created', array( $this, 'display_coupons_created_shortcode' ) );
        add_shortcode( 'love_coupons_preferences', array( $this, 'display_preferences_shortcode' ) );
        add_shortcode( 'love_coupons_dashboard', array( $this, 'display_dashboard_shortcode' ) );
    }

    public function display_dashboard_shortcode( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<div class="love-coupons-login-message"><p>' . __( 'Please log in to view your dashboard.', 'love-coupons' ) . '</p></div>';
        }

        $current_user_id = get_current_user_id();
        $current_user    = wp_get_current_user();
        $accent          = Love_Coupons_Core::get_user_accent_color( $current_user_id );
        $accent_color    = ( $accent && ! empty( $accent['color'] ) ) ? $accent['color'] : '#2c6e49';

        // Get navigation URLs
        $nav_items = $this->get_navigation_items();

        // Get avatar
        $avatar = get_avatar( $current_user_id, 48, '', $current_user->display_name, array( 'class' => 'love-menu-avatar' ) );
        $prefs_url = !empty($nav_items) ? $this->find_page_with_shortcode( 'love_coupons_preferences' ) : '';

        ob_start();
        ?>
        <header class="love-coupons-menu" style="--love-accent: <?php echo esc_attr( $accent_color ); ?>;">
            <div class="love-menu-container">
                <div class="love-menu-logo">
                    <svg width="40" height="40" viewBox="0 0 1000 1000" xmlns="http://www.w3.org/2000/svg">
                        <g transform="matrix(0.789514,0,0,0.789514,-611.911892,-88.403575)">
                            <path d="M1056.5,314.848C1052.907,317.051 1001.245,369.776 999.376,373.146C996.828,377.741 997.681,383.843 1001.865,390.942C1007.183,399.965 1008.414,404.204 1008.417,413.5C1008.421,426.384 1003.397,438.346 994.622,446.347C992.905,447.912 988.575,450.608 985,452.337C979.71,454.895 976.982,455.547 970.345,455.839C960.332,456.279 953.942,454.561 945,449.024C937.586,444.433 933.16,443.277 929.233,444.904C926.492,446.04 914.673,457.37 886.265,486.095C872.498,500.015 870.949,501.935 870.187,506.018C868.973,512.533 870.336,515.497 878.493,524.081C886.751,532.77 895.828,544.609 898.843,550.622C909.509,571.893 911.83,597.974 904.974,619.5C899.056,638.08 891.962,650.083 879.758,662.164C875.5,666.379 871.562,671.021 871.008,672.479C869.454,676.568 869.816,682.188 871.848,685.5C873.542,688.262 924.011,739.788 998.642,814.953L1028.88,845.407L1082.477,791.453C1111.956,761.779 1173.746,699.54 1219.787,653.145C1265.829,606.75 1307.48,564.9 1312.345,560.145C1317.209,555.39 1327.559,545.013 1335.345,537.085C1343.13,529.157 1358.388,513.785 1369.25,502.925C1380.112,492.066 1389,482.576 1389,481.837C1389,480.329 1375.772,466.637 1317.651,407.979C1295.568,385.693 1265.676,355.449 1251.224,340.77C1223.471,312.58 1222.056,311.507 1215.784,313.892C1214.302,314.455 1210.482,317.678 1207.295,321.054C1197.176,331.771 1180.515,342.51 1166.398,347.415C1149.83,353.171 1130.202,353.16 1113,347.383C1102.482,343.851 1085.859,332.697 1076,322.555C1066.43,312.712 1062.509,311.162 1056.5,314.848M1593.465,314.4C1588.751,316.454 1565.975,339.71 1566.641,341.79C1567.029,343.002 1566.924,343.133 1566.278,342.238C1565.62,341.327 1555.959,350.494 1531.498,375.238C1442.478,465.289 1327.263,581.54 1325.222,583.369C1323.969,584.491 1323.176,586.016 1323.46,586.757C1323.744,587.498 1323.556,587.844 1323.042,587.526C1322.528,587.208 1295.112,614.072 1262.118,647.224C1229.124,680.376 1168.757,741.025 1127.969,782C1087.18,822.975 1053.123,857.514 1052.286,858.754C1050.354,861.617 1050.416,868.46 1052.402,871.491C1053.241,872.771 1055.132,875.097 1056.604,876.659C1060.416,880.704 1064.684,890.291 1066.469,898.815C1070.649,918.786 1065.344,940.301 1052.615,955C1046.579,961.971 1044.323,967.168 1045.427,971.564C1046.456,975.665 1129.25,1058.461 1134.391,1060.53C1139.543,1062.604 1143.009,1061.739 1150.734,1056.45C1159.183,1050.664 1165.637,1048.649 1175.5,1048.718C1186.551,1048.794 1193.31,1051.487 1201.37,1059.028C1208.381,1065.586 1212.212,1071.877 1214.467,1080.535C1217.541,1092.336 1215.757,1102.492 1208.514,1114.423C1203.596,1122.525 1202.728,1127.698 1205.43,1132.8C1207.51,1136.725 1291.908,1220.493 1295.7,1222.395C1299.461,1224.281 1304.258,1224.467 1307.29,1222.845C1308.477,1222.209 1311.329,1219.927 1313.627,1217.773C1326.219,1205.971 1338.851,1201.055 1356.685,1201.017C1372.912,1200.983 1384.65,1205.103 1396.572,1215.019C1402.814,1220.211 1406.815,1221.639 1411.957,1220.509C1415.422,1219.748 1410.228,1224.891 1590.85,1043.391C1784.178,849.123 1894.93,737.479 1928.188,703.335C1942.359,688.787 1946.007,684.478 1946.535,681.666C1947.743,675.228 1946.224,672.071 1937.573,663.036C1922.737,647.543 1915.432,635.086 1910.282,616.5C1907.825,607.631 1907.517,604.859 1907.559,592C1907.604,578.325 1907.796,576.931 1910.923,567.5C1912.746,562 1915.287,555.423 1916.568,552.884C1920.746,544.607 1929.715,532.405 1936.785,525.38C1940.594,521.596 1944.268,517.437 1944.95,516.138C1946.331,513.509 1946.56,504.817 1945.307,502.594C1943.708,499.755 1892.048,447.459 1889.154,445.75C1885.481,443.581 1882.398,443.594 1878.452,445.798C1876.776,446.734 1871.826,449.3 1867.452,451.5C1859.683,455.408 1859.258,455.5 1849,455.5C1839.286,455.5 1838,455.263 1831.82,452.335C1828.147,450.595 1823.065,447.258 1820.528,444.919C1811.583,436.674 1805.756,418.954 1808.01,406.851C1809.238,400.254 1810.527,397.031 1814.724,390.059C1819.085,382.816 1819.188,377.199 1815.077,370.946C1813.469,368.501 1800.306,354.462 1785.825,339.75C1757.855,311.333 1758.079,311.499 1751.315,314.07C1749.767,314.659 1745.125,318.403 1741,322.391C1728.208,334.759 1715.701,342.848 1702.404,347.354C1671.143,357.947 1639.633,349.719 1613,324.009C1605.587,316.853 1599.932,312.907 1597.282,313.039C1596.852,313.061 1595.134,313.673 1593.465,314.4M1247,338C1247,338.55 1246.802,339 1246.559,339C1246.316,339 1245.84,338.55 1245.5,338C1245.16,337.45 1245.359,337 1245.941,337C1246.523,337 1247,337.45 1247,338M1824.345,452.543C1824.019,453.392 1823.538,453.872 1823.276,453.61C1823.014,453.348 1823.096,452.653 1823.459,452.067C1824.445,450.471 1825.021,450.781 1824.345,452.543M1358.345,510.543C1358.019,511.392 1357.538,511.872 1357.276,511.61C1357.014,511.348 1357.096,510.653 1357.459,510.067C1358.445,508.471 1359.021,508.781 1358.345,510.543M1062.683,988.188C1062.364,988.985 1062.127,988.748 1062.079,987.583C1062.036,986.529 1062.272,985.939 1062.604,986.271C1062.936,986.603 1062.972,987.466 1062.683,988.188M1115.382,1039.552C1115.723,1040.442 1115.555,1040.843 1114.989,1040.493C1114.445,1040.157 1114,1039.459 1114,1038.941C1114,1037.503 1114.717,1037.82 1115.382,1039.552" fill="currentColor"/>
                        </g>
                    </svg>
                    <span class="love-menu-brand">Love Coupons</span>
                </div>

                <div class="love-menu-center">
                    <span class="love-menu-greeting"><?php printf( esc_html__( 'Hello, %s!', 'love-coupons' ), esc_html( $current_user->display_name ) ); ?></span>
                    <?php if ( ! empty( $nav_items ) ) : ?>
                    <nav class="love-menu-nav" role="navigation" aria-label="<?php esc_attr_e( 'Main Navigation', 'love-coupons' ); ?>">
                        <?php foreach ( $nav_items as $item ) : ?>
                            <a href="<?php echo esc_url( $item['url'] ); ?>" class="love-menu-nav-item">
                                <?php echo esc_html( $item['label'] ); ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                    <?php endif; ?>
                </div>

                <div class="love-menu-right">
                    <div class="love-menu-avatar-wrapper">
                        <?php echo $avatar; ?>
                    </div>
                    <?php if ( $prefs_url ) : ?>
                    <a href="<?php echo esc_url( $prefs_url ); ?>" class="love-menu-icon-btn love-menu-preferences" aria-label="<?php esc_attr_e( 'Preferences', 'love-coupons' ); ?>" title="<?php esc_attr_e( 'Preferences', 'love-coupons' ); ?>">
                        <i class="fas fa-sliders-h"></i>
                    </a>
                    <?php endif; ?>
                    <a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>" class="love-menu-icon-btn love-menu-logout" aria-label="<?php esc_attr_e( 'Logout', 'love-coupons' ); ?>" title="<?php esc_attr_e( 'Logout', 'love-coupons' ); ?>">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </header>
        <?php
        return ob_get_clean();
    }

    private function get_navigation_items() {
        static $cached_items = null;

        if ( $cached_items !== null ) {
            return $cached_items;
        }

        $option_key = 'love_coupons_nav_urls';
        $stored_urls = get_option( $option_key, array() );

        $shortcodes = array(
            'love_coupons' => array(
                'label' => __( 'My coupons', 'love-coupons' ),
                'icon'  => 'tickets-alt',
            ),
            'love_coupons_submit' => array(
                'label' => __( 'Create Coupon', 'love-coupons' ),
                'icon'  => 'plus-alt',
            ),
            'love_coupons_created' => array(
                'label' => __( 'Created Coupons', 'love-coupons' ),
                'icon'  => 'admin-post',
            ),
            'love_coupons_preferences' => array(
                'label' => __( 'Preferences', 'love-coupons' ),
                'icon'  => 'admin-settings',
            ),
        );

        $nav_items = array();
        $needs_update = false;

        foreach ( $shortcodes as $shortcode => $data ) {
            if ( ! empty( $stored_urls[ $shortcode ] ) ) {
                $page_id = url_to_postid( $stored_urls[ $shortcode ] );
                if ( $page_id && get_post_status( $page_id ) === 'publish' ) {
                    $nav_items[] = array(
                        'label' => $data['label'],
                        'icon'  => $data['icon'],
                        'url'   => $stored_urls[ $shortcode ],
                    );
                    continue;
                }
            }

            $url = $this->find_page_with_shortcode( $shortcode );
            if ( $url ) {
                $stored_urls[ $shortcode ] = $url;
                $needs_update = true;
                $nav_items[] = array(
                    'label' => $data['label'],
                    'icon'  => $data['icon'],
                    'url'   => $url,
                );
            }
        }

        if ( $needs_update ) {
            update_option( $option_key, $stored_urls, false );
        }

        $cached_items = $nav_items;
        return $nav_items;
    }

    private function find_page_with_shortcode( $shortcode ) {
        global $wpdb;
        $page_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
            WHERE post_type = 'page'
            AND post_status = 'publish'
            AND post_content LIKE %s
            LIMIT 1",
            '%[' . $wpdb->esc_like( $shortcode ) . '%'
        ) );

        if ( $page_id ) {
            return get_permalink( $page_id );
        }

        return '';
    }

    public function display_coupons_submit_shortcode( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<div class="love-coupons-login-message"><p>' . __( 'Please log in to submit a coupon!', 'love-coupons' ) . '</p></div>';
        }

        $current_user_id = get_current_user_id();
        $wrapper_attrs   = Love_Coupons_Core::get_accent_attributes_for_user( $current_user_id );
        ob_start();
        ?>
        <div class="love-coupons-wrapper" <?php echo $wrapper_attrs; ?>>
            <h2 class="love-coupons-section-title"><?php _e( 'Create a Coupon', 'love-coupons' ); ?></h2>
            <?php $this->render_create_coupon_form( $current_user_id ); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public function display_coupons_created_shortcode( $atts ) {
        $atts = shortcode_atts( array( 'limit' => -1 ), $atts, 'love_coupons_created' );
        if ( ! is_user_logged_in() ) {
            return '<div class="love-coupons-login-message"><p>' . __( 'Please log in to see your created coupons!', 'love-coupons' ) . '</p></div>';
        }

        $current_user_id = get_current_user_id();
        $wrapper_attrs   = Love_Coupons_Core::get_accent_attributes_for_user( $current_user_id );
        ob_start();
        ?>
        <div class="love-coupons-wrapper" <?php echo $wrapper_attrs; ?>>
            <?php echo $this->render_posted_coupons( $current_user_id, $atts ); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public function display_preferences_shortcode() {
        if ( ! is_user_logged_in() ) {
            return '<div class="love-coupons-login-message"><p>' . __( 'Please log in to manage your posting preferences.', 'love-coupons' ) . '</p></div>';
        }

        $current_user_id = get_current_user_id();
        $restrictions    = get_option( 'love_coupons_posting_restrictions', array() );
        $user_pref_meta  = get_user_meta( $current_user_id, '_love_coupons_allowed_recipients', true );
        $current_setting = ! empty( $user_pref_meta ) && is_array( $user_pref_meta )
            ? $user_pref_meta
            : ( isset( $restrictions[ $current_user_id ] ) ? (array) $restrictions[ $current_user_id ] : array() );
        $selected_users  = array_map( 'absint', $current_setting );
        $palette         = Love_Coupons_Core::get_theme_accent_palette();
        $current_accent  = Love_Coupons_Core::get_user_accent_color( $current_user_id );
        $wrapper_attrs   = Love_Coupons_Core::get_accent_attributes_for_user( $current_user_id );

        $all_users = get_users( array( 'orderby' => 'display_name', 'order' => 'ASC' ) );

        ob_start();
        ?>
        <div class="love-coupons-wrapper love-coupons-preferences" <?php echo $wrapper_attrs; ?>>
            <h2 class="love-coupons-section-title"><?php _e( 'Posting Preferences', 'love-coupons' ); ?></h2>
            <p class="description"><?php _e( 'Select which users you can create coupons for:', 'love-coupons' ); ?></p>
            <form id="love-coupons-preferences-form">
                <?php wp_nonce_field( 'love_coupons_nonce', 'love_coupons_preferences_nonce' ); ?>
                <div class="love-preferences-users" aria-live="polite">
                    <div class="love-preferences-list">
                        <?php foreach ( $all_users as $user ) : if ( $user->ID === $current_user_id ) { continue; } $checked = in_array( $user->ID, $selected_users, true ); ?>
                            <label class="love-preference-user">
                                <input type="checkbox" name="love_preference_recipients[]" value="<?php echo esc_attr( $user->ID ); ?>" <?php checked( $checked ); ?> />
                                <span><?php echo esc_html( $user->display_name ); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="love-preferences-colors" role="radiogroup" aria-label="<?php esc_attr_e( 'Choose your accent colour', 'love-coupons' ); ?>">
                    <h3 class="love-coupons-section-subtitle"><?php _e( 'Accent colour', 'love-coupons' ); ?></h3>
                    <p class="description"><?php _e( 'Pick a theme accent colour for the coupons you create. Others will keep their own colours so it is easy to tell who posted what.', 'love-coupons' ); ?></p>
                    <div class="love-preferences-color-grid">
                        <?php if ( ! empty( $palette ) ) : foreach ( $palette as $entry ) :
                            $checked = ( isset( $current_accent['slug'] ) && $entry['slug'] === $current_accent['slug'] );
                            $label   = ! empty( $entry['name'] ) ? $entry['name'] : $entry['slug'];
                        ?>
                        <label class="love-preference-color" style="--love-accent: <?php echo esc_attr( $entry['color'] ); ?>;" aria-label="<?php echo esc_attr( $label ); ?>">
                            <input type="radio" name="love_accent_color" value="<?php echo esc_attr( $entry['slug'] ); ?>" <?php checked( $checked ); ?> />
                            <span class="love-color-swatch" aria-hidden="true"></span>
                            <span class="love-color-label"><?php echo esc_html( $label ); ?></span>
                        </label>
                        <?php endforeach; else : ?>
                            <p class="description"><?php _e( 'Theme colours are unavailable. A default palette will be used.', 'love-coupons' ); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <button type="submit" class="wp-element-button button button-primary" id="love-save-preferences"><?php _e( 'Save Preferences', 'love-coupons' ); ?></button>
                <div class="form-message" style="display:none;"></div>
            </form>

            <hr class="love-coupons-separator" style="margin: 2rem 0;">

            <h2 class="love-coupons-section-title"><?php _e( 'Notifications', 'love-coupons' ); ?></h2>
            <p class="description"><?php _e( 'Enable push notifications to receive instant updates when coupons are posted or redeemed.', 'love-coupons' ); ?></p>
            <div id="love-notification-settings">
                <div id="love-notification-status" style="margin-bottom: 1rem;">
                    <p class="love-notification-status-text"><strong><?php _e( 'Status:', 'love-coupons' ); ?></strong> <span id="love-notification-status-value"><?php _e( 'Checking...', 'love-coupons' ); ?></span></p>
                </div>
                <button type="button" class="wp-element-button button button-primary" id="love-enable-notifications-btn" style="display:none;">
                    <?php _e( 'Enable Notifications', 'love-coupons' ); ?>
                </button>
                <button type="button" class="wp-element-button button button-secondary" id="love-refresh-notifications-btn" style="margin-left: 8px;">
                    <?php _e( 'Refresh Status', 'love-coupons' ); ?>
                </button>
                <p class="love-notification-message" id="love-notification-message" style="display:none;"></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function display_coupons_shortcode( $atts ) {
        $atts = shortcode_atts( array( 'limit' => -1, 'category' => '', 'show_expired' => 'yes' ), $atts, 'love_coupons' );
        if ( ! is_user_logged_in() ) {
            return '<div class="love-coupons-login-message"><p>' . __( 'Please log in to see the coupons!', 'love-coupons' ) . '</p></div>';
        }
        $current_user_id = get_current_user_id();
        $wrapper_attrs   = Love_Coupons_Core::get_accent_attributes_for_user( $current_user_id );
        ob_start();
        ?>
        <div class="love-coupons-wrapper" <?php echo $wrapper_attrs; ?>>
            <?php echo $this->render_my_coupons( $current_user_id, $atts ); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_my_coupons( $user_id, $atts ) {
        $query = new WP_Query( array(
            'post_type' => 'love_coupon',
            'post_status' => 'publish',
            'posts_per_page' => intval( $atts['limit'] ),
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_love_coupon_assigned_to',
                    'value' => sprintf('i:%d;', $user_id),
                    'compare' => 'LIKE'
                ),
                array(
                    'key' => '_love_coupon_assigned_to',
                    'compare' => 'NOT EXISTS'
                )
            )
        ) );
        $upcoming = array(); $available = array(); $redeemed = array(); $expired = array();
        while ( $query->have_posts() ) { $query->the_post(); $coupon_id = get_the_ID();
            if ( ! Love_Coupons_Core::user_can_access_coupon( $coupon_id, $user_id ) ) { continue; }
            $is_redeemed = get_post_meta( $coupon_id, '_love_coupon_redeemed', true );
            $expiry_date = get_post_meta( $coupon_id, '_love_coupon_expiry_date', true );
            $start_date  = get_post_meta( $coupon_id, '_love_coupon_start_date', true );
            $now = time(); $is_upcoming = $start_date && strtotime( $start_date ) > $now; $is_expired = $expiry_date && strtotime( $expiry_date ) < $now;
            if ( $is_upcoming ) { $upcoming[] = $coupon_id; }
            elseif ( $is_redeemed ) { $redeemed[] = $coupon_id; }
            elseif ( $is_expired ) { $expired[] = $coupon_id; }
            else { $available[] = $coupon_id; }
        }
        wp_reset_postdata();
        ob_start();
        if ( empty($upcoming) && empty($available) && empty($redeemed) && empty($expired) ) {
            echo '<div class="love-coupons-empty"><p>' . __( 'No coupons available for you yet.', 'love-coupons' ) . '</p></div>';
            return ob_get_clean();
        }
        echo '<div class="love-coupons-container">';
        $viewer_id = $user_id;
        if ( ! empty( $upcoming ) ) { echo '<div class="love-coupons-section"><h3 class="love-coupons-section-title">' . __( 'Upcoming', 'love-coupons' ) . '</h3><div class="love-coupons-grid">'; foreach ( $upcoming as $id ) { $this->render_coupon_item( $id, false, false, $viewer_id ); } echo '</div></div><hr class="love-coupons-separator">'; }
        if ( ! empty( $available ) ) { echo '<div class="love-coupons-section"><h3 class="love-coupons-section-title">' . __( 'Available', 'love-coupons' ) . '</h3><div class="love-coupons-grid">'; foreach ( $available as $id ) { $this->render_coupon_item( $id, false, false, $viewer_id ); } echo '</div></div>'; }
        if ( ! empty( $redeemed ) ) { if ( ! empty( $available ) ) echo '<hr class="love-coupons-separator">'; echo '<div class="love-coupons-section"><h3 class="love-coupons-section-title">' . __( 'Redeemed', 'love-coupons' ) . '</h3><div class="love-coupons-grid">'; foreach ( $redeemed as $id ) { $this->render_coupon_item( $id, false, false, $viewer_id ); } echo '</div></div>'; }
        if ( ! empty( $expired ) && 'yes' === $atts['show_expired'] ) { if ( ! empty( $available ) || ! empty( $redeemed ) ) echo '<hr class="love-coupons-separator">'; echo '<div class="love-coupons-section"><h3 class="love-coupons-section-title">' . __( 'Expired', 'love-coupons' ) . '</h3><div class="love-coupons-grid">'; foreach ( $expired as $id ) { $this->render_coupon_item( $id, false, false, $viewer_id ); } echo '</div></div>'; }
        echo '</div>';
        return ob_get_clean();
    }

    private function render_posted_coupons( $user_id, $atts ) {
        ob_start();
        $query = new WP_Query( array(
            'post_type' => 'love_coupon',
            'post_status' => 'publish',
            'posts_per_page' => intval( $atts['limit'] ),
            'meta_query' => array( array( 'key' => '_love_coupon_created_by', 'value' => $user_id, 'compare' => '=' ) ),
        ) );

        $upcoming = array();
        $available = array();
        $redeemed = array();
        $expired = array();

        while ( $query->have_posts() ) {
            $query->the_post();
            $cid = get_the_ID();
            $is_redeemed = get_post_meta( $cid, '_love_coupon_redeemed', true );
            $expiry_date = get_post_meta( $cid, '_love_coupon_expiry_date', true );
            $start_date  = get_post_meta( $cid, '_love_coupon_start_date', true );
            $now = time();
            $is_upcoming = $start_date && strtotime( $start_date ) > $now;
            $is_expired  = $expiry_date && strtotime( $expiry_date ) < $now;

            if ( $is_upcoming ) { $upcoming[] = $cid; }
            elseif ( $is_redeemed ) { $redeemed[] = $cid; }
            elseif ( $is_expired ) { $expired[] = $cid; }
            else { $available[] = $cid; }
        }
        wp_reset_postdata();

        echo '<div class="love-coupons-container">';
        echo '<h3 class="love-coupons-section-title">' . __( 'Created Coupons', 'love-coupons' ) . '</h3>';

        if ( empty( $upcoming ) && empty( $available ) && empty( $redeemed ) && empty( $expired ) ) {
            echo '<div class="love-coupons-empty"><p>' . __( 'You haven\'t created any coupons yet.', 'love-coupons' ) . '</p></div>';
            echo '</div>';
            return ob_get_clean();
        }

        if ( ! empty( $upcoming ) ) {
            echo '<div class="love-coupons-section">';
            echo '<h3 class="love-coupons-section-title">' . __( 'Upcoming', 'love-coupons' ) . '</h3>';
            echo '<div class="love-coupons-grid">';
            foreach ( $upcoming as $id ) { $this->render_coupon_item( $id, true, true, $user_id ); }
            echo '</div></div><hr class="love-coupons-separator">';
        }

        if ( ! empty( $available ) ) {
            echo '<div class="love-coupons-section">';
            echo '<h3 class="love-coupons-section-title">' . __( 'Available', 'love-coupons' ) . '</h3>';
            echo '<div class="love-coupons-grid">';
            foreach ( $available as $id ) { $this->render_coupon_item( $id, true, true, $user_id ); }
            echo '</div></div>';
        }

        if ( ! empty( $redeemed ) ) {
            if ( ! empty( $available ) ) { echo '<hr class="love-coupons-separator">'; }
            echo '<div class="love-coupons-section">';
            echo '<h3 class="love-coupons-section-title">' . __( 'Redeemed', 'love-coupons' ) . '</h3>';
            echo '<div class="love-coupons-grid">';
            foreach ( $redeemed as $id ) { $this->render_coupon_item( $id, true, true, $user_id ); }
            echo '</div></div>';
        }

        if ( ! empty( $expired ) ) {
            if ( ! empty( $available ) || ! empty( $redeemed ) ) { echo '<hr class="love-coupons-separator">'; }
            echo '<div class="love-coupons-section">';
            echo '<h3 class="love-coupons-section-title">' . __( 'Expired', 'love-coupons' ) . '</h3>';
            echo '<div class="love-coupons-grid">';
            foreach ( $expired as $id ) { $this->render_coupon_item( $id, true, true, $user_id ); }
            echo '</div></div>';
        }

        echo '</div>';
        return ob_get_clean();
    }

    private function render_create_coupon_form( $user_id ) {
        ?>
            <form class="love-create-coupon-form" id="love-create-coupon-form">
                <?php wp_nonce_field( 'love_create_coupon', 'love_create_coupon_nonce' ); ?>
                <div class="form-group"><label for="coupon_title"><?php _e( 'Coupon Title', 'love-coupons' ); ?> <span class="required">*</span></label><input type="text" name="coupon_title" id="coupon_title" required placeholder="<?php _e( 'Enter coupon title', 'love-coupons' ); ?>" /></div>
                <div class="form-group"><label for="coupon_terms"><?php _e( 'Terms & Conditions', 'love-coupons' ); ?> <span class="required">*</span></label>
                    <textarea name="coupon_terms" id="coupon_terms" rows="4" required placeholder="<?php _e( 'Add any details or terms', 'love-coupons' ); ?>"></textarea>
                </div>
                <div class="form-group">
                    <label for="coupon_hero_image"><?php _e( 'Image', 'love-coupons' ); ?> <span class="required">*</span></label>
                    <div class="love-image-dropzone" id="coupon_image_dropzone">
                        <div class="dropzone-instructions"><span class="dashicons dashicons-format-image"></span><p><?php _e( 'Drag and drop an image, or click to upload.', 'love-coupons' ); ?></p></div>
                        <input type="file" name="coupon_hero_image" id="coupon_hero_image" accept="image/*" required />
                    </div>
                    <div id="coupon_hero_preview" class="love-image-preview" style="display:none;">
                        <img alt="<?php esc_attr_e('Image preview','love-coupons'); ?>" />
                        <button type="button" class="button love-image-remove" id="love-remove-image"><?php _e( 'Remove Image', 'love-coupons' ); ?></button>
                    </div>
                </div>
                <div class="form-group"><label><?php _e( 'Schedule', 'love-coupons' ); ?></label>
                    <div class="schedule-options">
                        <label><input type="radio" name="coupon_schedule_option" value="now" checked /> <?php _e( 'Post immediately', 'love-coupons' ); ?></label>
                        <label><input type="radio" name="coupon_schedule_option" value="schedule" /> <?php _e( 'Schedule for later', 'love-coupons' ); ?></label>
                    </div>
                    <div class="schedule-date" id="schedule_date_group" style="display:none;">
                        <input type="date" name="coupon_start_date" id="coupon_start_date" placeholder="<?php esc_attr_e( 'Select start date', 'love-coupons' ); ?>" data-placeholder="<?php esc_attr_e( 'Select start date', 'love-coupons' ); ?>" />
                    </div>
                </div>
                <div class="form-row form-row-spaced">
                    <div class="form-group"><label for="coupon_expiry_date"><?php _e( 'Valid until', 'love-coupons' ); ?> <span class="required">*</span></label><input type="date" name="coupon_expiry_date" id="coupon_expiry_date" placeholder="<?php esc_attr_e( 'Select expiry date', 'love-coupons' ); ?>" data-placeholder="<?php esc_attr_e( 'Select expiry date', 'love-coupons' ); ?>" required /></div>
                    <div class="form-group"><label for="coupon_usage_limit"><?php _e( 'Usage Limit', 'love-coupons' ); ?></label><input type="number" name="coupon_usage_limit" id="coupon_usage_limit" value="1" min="1" /></div>
                </div>
                <button type="submit" class="wp-element-button button button-primary"><?php _e( 'Create Coupon', 'love-coupons' ); ?></button>
                <div class="form-message" style="display: none;"></div>
            </form>
            <div class="love-modal" id="love-cropper-modal" aria-hidden="true" style="display:none;">
                <div class="love-modal-overlay" data-dismiss></div>
                    <div class="love-modal-content" role="dialog" aria-modal="true" aria-labelledby="love-cropper-title">
                        <div class="love-modal-header"><h4 id="love-cropper-title"><?php _e('Crop Image','love-coupons');?></h4><button type="button" class="wp-element-button button love-modal-close" data-dismiss aria-label="<?php esc_attr_e('Close','love-coupons');?>"><?php _e('Close','love-coupons'); ?></button></div>
                        <div class="love-modal-body"><div class="love-cropper-container"><img id="love-cropper-image" alt="<?php esc_attr_e('Image to crop','love-coupons');?>" /></div><p class="description"><?php _e('Drag to select.','love-coupons');?></p></div>
                    <div class="love-modal-footer"><button type="button" class="wp-element-button button" id="love-cropper-cancel"><?php _e('Cancel','love-coupons');?></button><button type="button" class="wp-element-button button button-primary" id="love-cropper-apply"><?php _e('Crop & Use','love-coupons');?></button></div>
                </div>
        <?php
    }

    private function render_coupon_item( $coupon_id, $show_delete = false, $suppress_redeem = false, $accent_user_id = null ) {
        $terms            = get_post_meta( $coupon_id, '_love_coupon_terms', true );
        $expiry_date      = get_post_meta( $coupon_id, '_love_coupon_expiry_date', true );
        $start_date       = get_post_meta( $coupon_id, '_love_coupon_start_date', true );
        $usage_limit      = absint( get_post_meta( $coupon_id, '_love_coupon_usage_limit', true ) );
        $redemption_count = intval( get_post_meta( $coupon_id, '_love_coupon_redemption_count', true ) );
        $legacy_redeemed  = get_post_meta( $coupon_id, '_love_coupon_redeemed', true );

        $now = time();
        $is_upcoming = $start_date && strtotime( $start_date ) > $now;
        $is_expired  = $expiry_date && strtotime( $expiry_date ) < $now;

        $is_fully_redeemed = $usage_limit > 0 ? ( $redemption_count >= $usage_limit ) : (bool) $legacy_redeemed;
        $remaining         = $usage_limit > 0 ? max( 0, $usage_limit - $redemption_count ) : null;

        $classes = array( 'love-coupon' );
        if ( $is_fully_redeemed ) { $classes[] = 'redeemed'; }
        if ( $is_expired ) { $classes[] = 'expired'; }
        if ( $is_upcoming ) { $classes[] = 'upcoming'; }

        $creator_id = get_post_meta( $coupon_id, '_love_coupon_created_by', true );
        if ( ! $creator_id ) { $creator_id = get_post_field( 'post_author', $coupon_id ); }
        $accent_user = $accent_user_id ? $accent_user_id : $creator_id;
        $accent_attrs = Love_Coupons_Core::get_accent_attributes_for_user( $accent_user );
        ?>
        <div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-coupon-id="<?php echo esc_attr( $coupon_id ); ?>" <?php echo $accent_attrs; ?>>
            <?php if ( has_post_thumbnail( $coupon_id ) ) : ?><div class="coupon-image"><?php echo get_the_post_thumbnail( $coupon_id, 'large' ); ?></div><?php endif; ?>
            <div class="coupon-content">
                <h3 class="coupon-title"><?php echo esc_html( get_the_title( $coupon_id ) ); ?></h3>
                <?php if ( $expiry_date ) : ?><div class="coupon-expiry"><small><?php printf( __( 'Expires: %s', 'love-coupons' ), date_i18n( get_option( 'date_format' ), strtotime( $expiry_date ) ) ); ?></small></div><?php endif; ?>
                <?php if ( $is_upcoming && $start_date ) : ?><div class="coupon-expiry"><small><?php printf( __( 'Starts: %s', 'love-coupons' ), date_i18n( get_option( 'date_format' ), strtotime( $start_date ) ) ); ?></small></div><?php endif; ?>
                <?php if ( $usage_limit > 0 ) : ?><div class="coupon-usage"><small><?php printf( __( 'Uses left: %1$d of %2$d', 'love-coupons' ), $remaining, $usage_limit ); ?></small></div><?php endif; ?>
                <?php if ( $terms ) : ?><details class="coupon-terms"><summary><?php _e( 'Terms & Conditions', 'love-coupons' ); ?></summary><div class="terms-content"><?php echo wp_kses_post( nl2br( $terms ) ); ?></div></details><?php endif; ?>
                <div class="coupon-actions">
                    <?php if ( ! $suppress_redeem ) : ?>
                        <?php if ( $is_fully_redeemed ) : ?>
                            <button class="wp-element-button button button-redeemed" disabled><span class="dashicons dashicons-yes"></span><?php _e( 'Redeemed', 'love-coupons' ); ?></button>
                        <?php elseif ( $is_expired ) : ?>
                            <button class="wp-element-button button button-expired" disabled><span class="dashicons dashicons-clock"></span><?php _e( 'Expired', 'love-coupons' ); ?></button>
                        <?php else : ?>
                            <?php if ( $is_upcoming ) : ?>
                                <button class="wp-element-button button button-expired" disabled><span class="dashicons dashicons-clock"></span><?php _e( 'Upcoming', 'love-coupons' ); ?></button>
                            <?php else : ?>
                                <button class="wp-element-button button button-primary redeem-button" data-coupon-id="<?php echo esc_attr( $coupon_id ); ?>"><span class="dashicons dashicons-tickets-alt"></span><?php _e( 'Redeem', 'love-coupons' ); ?></button>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ( $show_delete ) : ?>
                        <button class="wp-element-button button button-danger delete-coupon" data-coupon-id="<?php echo esc_attr( $coupon_id ); ?>"><?php _e( 'Remove', 'love-coupons' ); ?></button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
}
