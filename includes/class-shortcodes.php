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
        add_shortcode( 'love_coupons_all', array( $this, 'display_all_coupons_shortcode' ) );
    }

    public function display_dashboard_shortcode( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<div class="love-coupons-login-message"><p>' . __( 'Please log in to view your dashboard.', 'love-coupons' ) . '</p></div>';
        }

        $current_user_id = get_current_user_id();
        $current_user    = wp_get_current_user();
        $accent          = Love_Coupons_Core::get_user_accent_color( $current_user_id );
        $accent_color    = ( $accent && ! empty( $accent['color'] ) ) ? $accent['color'] : '#2c6e49';
        $accent_contrast = '#ffffff';

        if ( preg_match( '/^#?([a-fA-F0-9]{6})$/', $accent_color, $matches ) ) {
            $hex = $matches[1];
            $r   = hexdec( substr( $hex, 0, 2 ) );
            $g   = hexdec( substr( $hex, 2, 2 ) );
            $b   = hexdec( substr( $hex, 4, 2 ) );
            // YIQ formula to decide text contrast
            $yiq = ( ( $r * 299 ) + ( $g * 587 ) + ( $b * 114 ) ) / 1000;
            $accent_contrast = ( $yiq >= 140 ) ? '#000000' : '#ffffff';
        }

        // Target accent: first allowed recipient (if any) for differentiated CTA
        $target_accent       = $accent_color;
        $target_accent_contr = $accent_contrast;
        $allowed_recipients  = Love_Coupons_Core::get_allowed_recipients_for_user( $current_user_id );
        if ( ! empty( $allowed_recipients ) && is_array( $allowed_recipients ) ) {
            $first_target = reset( $allowed_recipients );
            if ( $first_target ) {
                $target_palette = Love_Coupons_Core::get_user_accent_color( $first_target );
                if ( $target_palette && ! empty( $target_palette['color'] ) ) {
                    $target_accent = $target_palette['color'];
                    if ( preg_match( '/^#?([a-fA-F0-9]{6})$/', $target_accent, $tm ) ) {
                        $h2  = $tm[1];
                        $rt  = hexdec( substr( $h2, 0, 2 ) );
                        $gt  = hexdec( substr( $h2, 2, 2 ) );
                        $bt  = hexdec( substr( $h2, 4, 2 ) );
                        $yiq2 = ( ( $rt * 299 ) + ( $gt * 587 ) + ( $bt * 114 ) ) / 1000;
                        $target_accent_contr = ( $yiq2 >= 140 ) ? '#000000' : '#ffffff';
                    }
                }
            }
        }

        // Get key URLs
        $all_coupons_url = $this->find_page_with_shortcode( 'love_coupons_all' );
        $submit_url = $this->find_page_with_shortcode( 'love_coupons_submit' );
        $prefs_url  = $this->find_page_with_shortcode( 'love_coupons_preferences' );

        // Get avatar
        $avatar = get_avatar( $current_user_id, 48, '', $current_user->display_name, array( 'class' => 'love-menu-avatar' ) );

        ob_start();
        ?>
        <header class="love-coupons-menu" style="--love-accent: <?php echo esc_attr( $accent_color ); ?>; --love-accent-contrast: <?php echo esc_attr( $accent_contrast ); ?>; --love-target-accent: <?php echo esc_attr( $target_accent ); ?>; --love-target-contrast: <?php echo esc_attr( $target_accent_contr ); ?>;">
            <div class="love-menu-container">
                <!-- Logo Row -->
                <div class="love-menu-logo-row">
                    <div class="love-menu-logo">
                        <img src="<?php echo esc_url( LOVE_COUPONS_PLUGIN_URL . 'assets/images/icon512.png' ); ?>" alt="Love Coupons" />
                        <span class="love-menu-brand">Love Coupons</span>
                    </div>
                </div>

                <!-- Greeting & Actions Row -->
                <div class="love-menu-greeting-row">
                    <div class="love-menu-greeting-left">
                        <div class="love-menu-avatar-wrapper">
                            <?php echo $avatar; ?>
                        </div>
                        <span class="love-menu-greeting"><?php printf( esc_html__( 'Hello, %s!', 'love-coupons' ), esc_html( $current_user->display_name ) ); ?></span>
                    </div>
                    <div class="love-menu-actions">
                        <?php if ( $submit_url ) : ?>
                        <a href="<?php echo esc_url( $submit_url ); ?>" class="love-menu-icon-btn love-menu-icon-btn-new love-menu-nav-item-new" aria-label="<?php esc_attr_e( 'New Coupon', 'love-coupons' ); ?>" title="<?php esc_attr_e( 'New Coupon', 'love-coupons' ); ?>">
                            <i class="fas fa-plus"></i>
                        </a>
                        <?php endif; ?>
                        <?php if ( $all_coupons_url ) : ?>
                        <a href="<?php echo esc_url( $all_coupons_url ); ?>" class="love-menu-icon-btn love-menu-coupons" aria-label="<?php esc_attr_e( 'View Coupons', 'love-coupons' ); ?>" title="<?php esc_attr_e( 'View Coupons', 'love-coupons' ); ?>">
                            <i class="fas fa-ticket-alt"></i>
                        </a>
                        <?php endif; ?>
                        <?php if ( $prefs_url ) : ?>
                        <a href="<?php echo esc_url( $prefs_url ); ?>" class="love-menu-icon-btn love-menu-preferences" aria-label="<?php esc_attr_e( 'Preferences', 'love-coupons' ); ?>" title="<?php esc_attr_e( 'Preferences', 'love-coupons' ); ?>">
                            <i class="fas fa-cog"></i>
                        </a>
                        <?php endif; ?>
                        <a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>" class="love-menu-icon-btn love-menu-logout" aria-label="<?php esc_attr_e( 'Logout', 'love-coupons' ); ?>" title="<?php esc_attr_e( 'Logout', 'love-coupons' ); ?>">
                            <i class="fas fa-sign-out-alt"></i>
                        </a>
                    </div>
                </div>
            </div>
        </header>
        <?php
        return ob_get_clean();
    }

    public function display_all_coupons_shortcode( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<div class="love-coupons-login-message"><p>' . __( 'Please log in to view your coupons.', 'love-coupons' ) . '</p></div>';
        }

        $current_user_id = get_current_user_id();
        $wrapper_attrs   = Love_Coupons_Core::get_accent_attributes_for_user( $current_user_id );

        ob_start();
        ?>
        <div class="love-coupons-wrapper love-coupons-tabs-wrapper" <?php echo $wrapper_attrs; ?>>
            <h2 class="love-coupons-section-title"><?php _e( 'Coupons', 'love-coupons' ); ?></h2>

            <div class="love-coupons-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Coupons', 'love-coupons' ); ?>">
                <button type="button" class="love-tab-button active" role="tab" aria-selected="true" aria-controls="love-tab-available" data-target="love-tab-available">
                    <?php esc_html_e( 'For Me', 'love-coupons' ); ?>
                </button>
                <button type="button" class="love-tab-button" role="tab" aria-selected="false" aria-controls="love-tab-created" data-target="love-tab-created">
                    <?php esc_html_e( 'By Me', 'love-coupons' ); ?>
                </button>
            </div>
            <div class="love-tabs-content">
                <div id="love-tab-available" class="love-tab-pane active" role="tabpanel">
                    <?php echo $this->render_my_coupons( $current_user_id, array( 'limit' => -1, 'category' => '', 'show_expired' => 'yes' ) ); ?>
                </div>
                <div id="love-tab-created" class="love-tab-pane" role="tabpanel" aria-hidden="true">
                    <?php echo $this->render_posted_coupons( $current_user_id, array( 'limit' => -1 ) ); ?>
                </div>
            </div>
        </div>
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
                'label'     => __( 'My coupons', 'love-coupons' ),
                'icon'      => 'tickets-alt',
                'shortcode' => 'love_coupons',
            ),
            'love_coupons_created' => array(
                'label'     => __( 'Created Coupons', 'love-coupons' ),
                'icon'      => 'admin-post',
                'shortcode' => 'love_coupons_created',
            ),
            'love_coupons_submit' => array(
                'label'     => __( 'New Coupon', 'love-coupons' ),
                'icon'      => 'plus-alt',
                'shortcode' => 'love_coupons_submit',
            ),
            'love_coupons_preferences' => array(
                'label'     => __( 'Preferences', 'love-coupons' ),
                'icon'      => 'admin-settings',
                'shortcode' => 'love_coupons_preferences',
            ),
        );

        $nav_items = array();
        $needs_update = false;

        foreach ( $shortcodes as $shortcode => $data ) {
            if ( ! empty( $stored_urls[ $shortcode ] ) ) {
                $page_id = url_to_postid( $stored_urls[ $shortcode ] );
                if ( $page_id && get_post_status( $page_id ) === 'publish' ) {
                    $nav_items[] = array(
                        'label'     => $data['label'],
                        'icon'      => $data['icon'],
                        'url'       => $stored_urls[ $shortcode ],
                        'shortcode' => $shortcode,
                    );
                    continue;
                }
            }

            $url = $this->find_page_with_shortcode( $shortcode );
            if ( $url ) {
                $stored_urls[ $shortcode ] = $url;
                $needs_update = true;
                $nav_items[] = array(
                    'label'     => $data['label'],
                    'icon'      => $data['icon'],
                    'url'       => $url,
                    'shortcode' => $shortcode,
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
        <div class="love-coupons-wrapper love-coupons-preferences love-coupons-tabs-wrapper" <?php echo $wrapper_attrs; ?>>
            <h2 class="love-coupons-section-title"><?php _e( 'Preferences', 'love-coupons' ); ?></h2>

            <div class="love-coupons-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Preferences', 'love-coupons' ); ?>">
                <button type="button" class="love-tab-button active" role="tab" aria-selected="true" aria-controls="love-tab-recipients" data-target="love-tab-recipients">
                    <?php esc_html_e( 'Recipients', 'love-coupons' ); ?>
                </button>
                <button type="button" class="love-tab-button" role="tab" aria-selected="false" aria-controls="love-tab-appearance" data-target="love-tab-appearance">
                    <?php esc_html_e( 'Appearance', 'love-coupons' ); ?>
                </button>
                <button type="button" class="love-tab-button" role="tab" aria-selected="false" aria-controls="love-tab-notifications" data-target="love-tab-notifications">
                    <?php esc_html_e( 'Notifications', 'love-coupons' ); ?>
                </button>
            </div>

            <div class="love-tabs-content">
                <!-- Recipients Tab -->
                <div id="love-tab-recipients" class="love-tab-pane active" role="tabpanel">
                    <form id="love-coupons-preferences-form">
                        <?php wp_nonce_field( 'love_coupons_nonce', 'love_coupons_preferences_nonce' ); ?>
                        <p class="description" style="margin-bottom: 1.5rem;"><?php _e( 'Select which users you can create coupons for:', 'love-coupons' ); ?></p>
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
                        <button type="submit" class="wp-element-button button button-primary" id="love-save-preferences" style="margin-top: 1.5rem;"><?php _e( 'Save Recipients', 'love-coupons' ); ?></button>
                        <div class="form-message" style="display:none; margin-top: 1rem;"></div>
                    </form>
                </div>

                <!-- Appearance Tab -->
                <div id="love-tab-appearance" class="love-tab-pane" role="tabpanel" aria-hidden="true">
                    <form id="love-coupons-appearance-form">
                        <?php wp_nonce_field( 'love_coupons_nonce', 'love_coupons_appearance_nonce' ); ?>
                        <div class="love-preferences-colors" role="radiogroup" aria-label="<?php esc_attr_e( 'Choose your accent colour', 'love-coupons' ); ?>">
                            <h3 class="love-coupons-section-subtitle"><?php _e( 'Accent Colour', 'love-coupons' ); ?></h3>
                            <p class="description" style="margin-bottom: 1.5rem;"><?php _e( 'Pick a theme accent colour for the coupons you create. Others will keep their own colours so it is easy to tell who posted what.', 'love-coupons' ); ?></p>
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
                        <button type="submit" class="wp-element-button button button-primary" id="love-save-appearance" style="margin-top: 1.5rem;"><?php _e( 'Save Appearance', 'love-coupons' ); ?></button>
                        <div class="form-message-appearance" style="display:none; margin-top: 1rem;"></div>
                    </form>
                </div>

                <!-- Notifications Tab -->
                <div id="love-tab-notifications" class="love-tab-pane" role="tabpanel" aria-hidden="true">
                    <h3 class="love-coupons-section-subtitle"><?php _e( 'Push Notifications', 'love-coupons' ); ?></h3>
                    <p class="description" style="margin-bottom: 1.5rem;"><?php _e( 'Enable push notifications to receive instant updates when coupons are posted or redeemed.', 'love-coupons' ); ?></p>
                    <div id="love-notification-settings">
                        <div id="love-notification-status" style="margin-bottom: 1rem;">
                            <p class="love-notification-status-text"><strong><?php _e( 'Status:', 'love-coupons' ); ?></strong> <span id="love-notification-status-value"><?php _e( 'Checking...', 'love-coupons' ); ?></span></p>
                        </div>
                        <div class="love-notification-buttons">
                            <button type="button" class="wp-element-button button button-primary" id="love-enable-notifications-btn" style="display:none;">
                                <?php _e( 'Enable Notifications', 'love-coupons' ); ?>
                            </button>
                            <button type="button" class="wp-element-button button button-secondary" id="love-refresh-notifications-btn">
                                <?php _e( 'Refresh Status', 'love-coupons' ); ?>
                            </button>
                        </div>
                        <p class="love-notification-message" id="love-notification-message" style="display:none; margin-top: 1rem;"></p>
                    </div>
                </div>
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
        $wrapper_attrs = Love_Coupons_Core::get_accent_attributes_for_user( $user_id );
        ?>
        <div class="love-coupons-tabs-wrapper" <?php echo $wrapper_attrs; ?>>
            <div class="love-coupons-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Coupon Form', 'love-coupons' ); ?>">
                <button type="button" class="love-tab-button active" role="tab" aria-selected="true" aria-controls="love-tab-basic" data-target="love-tab-basic">
                    <?php esc_html_e( 'Basic Info', 'love-coupons' ); ?>
                </button>
                <button type="button" class="love-tab-button" role="tab" aria-selected="false" aria-controls="love-tab-image" data-target="love-tab-image">
                    <?php esc_html_e( 'Image', 'love-coupons' ); ?>
                </button>
                <button type="button" class="love-tab-button" role="tab" aria-selected="false" aria-controls="love-tab-time" data-target="love-tab-time">
                    <?php esc_html_e( 'Time', 'love-coupons' ); ?>
                </button>
            </div>

            <form class="love-create-coupon-form" id="love-create-coupon-form">
                <?php wp_nonce_field( 'love_create_coupon', 'love_create_coupon_nonce' ); ?>
                
                <div class="love-tabs-content">
                    <!-- Basic Info Tab -->
                    <div id="love-tab-basic" class="love-tab-pane active" role="tabpanel">
                        <div class="form-group">
                            <label for="coupon_title"><?php _e( 'Coupon Title', 'love-coupons' ); ?> <span class="required">*</span></label>
                            <input type="text" name="coupon_title" id="coupon_title" required placeholder="<?php _e( 'Enter coupon title', 'love-coupons' ); ?>" />
                        </div>
                        <div class="form-group">
                            <label for="coupon_terms"><?php _e( 'Terms & Conditions', 'love-coupons' ); ?> <span class="required">*</span></label>
                            <textarea name="coupon_terms" id="coupon_terms" rows="4" required placeholder="<?php _e( 'Add any details or terms', 'love-coupons' ); ?>"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="coupon_usage_limit"><?php _e( 'Usage Limit', 'love-coupons' ); ?></label>
                            <input type="number" name="coupon_usage_limit" id="coupon_usage_limit" value="1" min="1" />
                        </div>
                    </div>

                    <!-- Image Tab -->
                    <div id="love-tab-image" class="love-tab-pane" role="tabpanel" aria-hidden="true">
                        <div class="form-group">
                            <label for="coupon_hero_image"><?php _e( 'Image', 'love-coupons' ); ?> <span class="required">*</span></label>
                            <div class="love-image-dropzone" id="coupon_image_dropzone">
                                <div class="dropzone-instructions">
                                    <span class="dashicons dashicons-format-image"></span>
                                    <p><?php _e( 'Drag and drop an image, or click to upload.', 'love-coupons' ); ?></p>
                                </div>
                                <input type="file" name="coupon_hero_image" id="coupon_hero_image" accept="image/*" required />
                            </div>
                            <div id="coupon_hero_preview" class="love-image-preview" style="display:none;">
                                <img alt="<?php esc_attr_e('Image preview','love-coupons'); ?>" />
                                <button type="button" class="button love-image-remove" id="love-remove-image"><?php _e( 'Remove Image', 'love-coupons' ); ?></button>
                            </div>
                        </div>
                    </div>

                    <!-- Time Tab -->
                    <div id="love-tab-time" class="love-tab-pane" role="tabpanel" aria-hidden="true">
                        <div class="form-group">
                            <label><?php _e( 'Schedule', 'love-coupons' ); ?></label>
                            <div class="schedule-options">
                                <label><input type="radio" name="coupon_schedule_option" value="now" checked /> <?php _e( 'Post immediately', 'love-coupons' ); ?></label>
                                <label><input type="radio" name="coupon_schedule_option" value="schedule" /> <?php _e( 'Schedule for later', 'love-coupons' ); ?></label>
                            </div>
                            <div class="schedule-date" id="schedule_date_group" style="display:none;">
                                <input type="date" name="coupon_start_date" id="coupon_start_date" placeholder="<?php esc_attr_e( 'Select start date', 'love-coupons' ); ?>" data-placeholder="<?php esc_attr_e( 'Select start date', 'love-coupons' ); ?>" />
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="coupon_expiry_date"><?php _e( 'Valid until', 'love-coupons' ); ?> <span class="required">*</span></label>
                            <input type="date" name="coupon_expiry_date" id="coupon_expiry_date" placeholder="<?php esc_attr_e( 'Select expiry date', 'love-coupons' ); ?>" data-placeholder="<?php esc_attr_e( 'Select expiry date', 'love-coupons' ); ?>" required />
                        </div>
                    </div>
                </div>

                <button type="submit" class="wp-element-button button button-primary" style="margin-top: 1.5rem;"><?php _e( 'Create Coupon', 'love-coupons' ); ?></button>
                <div class="form-message" style="display: none;"></div>
            </form>
        </div>
        <div class="love-modal" id="love-cropper-modal" aria-hidden="true" style="display:none;">
            <div class="love-modal-overlay" data-dismiss></div>
            <div class="love-modal-content" role="dialog" aria-modal="true" aria-labelledby="love-cropper-title">
                <div class="love-modal-header"><h4 id="love-cropper-title"><?php _e('Crop Image','love-coupons');?></h4><button type="button" class="wp-element-button button love-modal-close" data-dismiss aria-label="<?php esc_attr_e('Close','love-coupons');?>"><?php _e('Close','love-coupons'); ?></button></div>
                <div class="love-modal-body"><div class="love-cropper-container"><img id="love-cropper-image" alt="<?php esc_attr_e('Image to crop','love-coupons');?>" /></div><p class="description"><?php _e('Drag to select.','love-coupons');?></p></div>
                <div class="love-modal-footer"><button type="button" class="wp-element-button button" id="love-cropper-cancel"><?php _e('Cancel','love-coupons');?></button><button type="button" class="wp-element-button button button-primary" id="love-cropper-apply"><?php _e('Crop & Use','love-coupons');?></button></div>
            </div>
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
