<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once LOVE_COUPONS_PLUGIN_DIR . 'includes/class-core.php';

class Love_Coupons_Shortcodes {
    public function register() {
        add_shortcode( 'love_coupons', array( $this, 'display_coupons_shortcode' ) );
        add_shortcode( 'love_coupons_submit', array( $this, 'display_coupons_submit_shortcode' ) );
        add_shortcode( 'love_coupons_created', array( $this, 'display_coupons_created_shortcode' ) );
        add_shortcode( 'love_coupons_preferences', array( $this, 'display_preferences_shortcode' ) );
    }

    public function display_coupons_submit_shortcode( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<div class="love-coupons-login-message"><p>' . __( 'Please log in to submit a coupon!', 'love-coupons' ) . '</p></div>';
        }

        $current_user_id = get_current_user_id();
        ob_start();
        ?>
        <div class="love-coupons-wrapper">
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
        ob_start();
        ?>
        <div class="love-coupons-wrapper">
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
        $current_setting = isset( $restrictions[ $current_user_id ] ) ? (array) $restrictions[ $current_user_id ] : array();
        $selected_users  = array_map( 'absint', $current_setting );

        $all_users = get_users( array( 'orderby' => 'display_name', 'order' => 'ASC' ) );

        ob_start();
        ?>
        <div class="love-coupons-wrapper love-coupons-preferences">
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
        ob_start();
        ?>
        <div class="love-coupons-wrapper">
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
        if ( ! empty( $upcoming ) ) { echo '<div class="love-coupons-section"><h3 class="love-coupons-section-title">' . __( 'Upcoming', 'love-coupons' ) . '</h3><div class="love-coupons-grid">'; foreach ( $upcoming as $id ) { $this->render_coupon_item( $id ); } echo '</div></div><hr class="love-coupons-separator">'; }
        if ( ! empty( $available ) ) { echo '<div class="love-coupons-section"><h3 class="love-coupons-section-title">' . __( 'Available', 'love-coupons' ) . '</h3><div class="love-coupons-grid">'; foreach ( $available as $id ) { $this->render_coupon_item( $id ); } echo '</div></div>'; }
        if ( ! empty( $redeemed ) ) { if ( ! empty( $available ) ) echo '<hr class="love-coupons-separator">'; echo '<div class="love-coupons-section"><h3 class="love-coupons-section-title">' . __( 'Redeemed', 'love-coupons' ) . '</h3><div class="love-coupons-grid">'; foreach ( $redeemed as $id ) { $this->render_coupon_item( $id ); } echo '</div></div>'; }
        if ( ! empty( $expired ) && 'yes' === $atts['show_expired'] ) { if ( ! empty( $available ) || ! empty( $redeemed ) ) echo '<hr class="love-coupons-separator">'; echo '<div class="love-coupons-section"><h3 class="love-coupons-section-title">' . __( 'Expired', 'love-coupons' ) . '</h3><div class="love-coupons-grid">'; foreach ( $expired as $id ) { $this->render_coupon_item( $id ); } echo '</div></div>'; }
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
            foreach ( $upcoming as $id ) { $this->render_coupon_item( $id, true, true ); }
            echo '</div></div><hr class="love-coupons-separator">';
        }

        if ( ! empty( $available ) ) {
            echo '<div class="love-coupons-section">';
            echo '<h3 class="love-coupons-section-title">' . __( 'Available', 'love-coupons' ) . '</h3>';
            echo '<div class="love-coupons-grid">';
            foreach ( $available as $id ) { $this->render_coupon_item( $id, true, true ); }
            echo '</div></div>';
        }

        if ( ! empty( $redeemed ) ) {
            if ( ! empty( $available ) ) { echo '<hr class="love-coupons-separator">'; }
            echo '<div class="love-coupons-section">';
            echo '<h3 class="love-coupons-section-title">' . __( 'Redeemed', 'love-coupons' ) . '</h3>';
            echo '<div class="love-coupons-grid">';
            foreach ( $redeemed as $id ) { $this->render_coupon_item( $id, true, true ); }
            echo '</div></div>';
        }

        if ( ! empty( $expired ) ) {
            if ( ! empty( $available ) || ! empty( $redeemed ) ) { echo '<hr class="love-coupons-separator">'; }
            echo '<div class="love-coupons-section">';
            echo '<h3 class="love-coupons-section-title">' . __( 'Expired', 'love-coupons' ) . '</h3>';
            echo '<div class="love-coupons-grid">';
            foreach ( $expired as $id ) { $this->render_coupon_item( $id, true, true ); }
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

    private function render_coupon_item( $coupon_id, $show_delete = false, $suppress_redeem = false ) {
        $redeemed = get_post_meta( $coupon_id, '_love_coupon_redeemed', true );
        $terms = get_post_meta( $coupon_id, '_love_coupon_terms', true );
        $expiry_date = get_post_meta( $coupon_id, '_love_coupon_expiry_date', true );
        $start_date = get_post_meta( $coupon_id, '_love_coupon_start_date', true );
        $now = time(); $is_upcoming = $start_date && strtotime( $start_date ) > $now; $is_expired = $expiry_date && strtotime( $expiry_date ) < $now;
        $classes = array( 'love-coupon' ); if ( $redeemed ) $classes[] = 'redeemed'; if ( $is_expired ) $classes[] = 'expired'; if ( $is_upcoming ) $classes[] = 'upcoming';
        ?>
        <div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-coupon-id="<?php echo esc_attr( $coupon_id ); ?>">
            <?php if ( has_post_thumbnail( $coupon_id ) ) : ?><div class="coupon-image"><?php echo get_the_post_thumbnail( $coupon_id, 'large' ); ?></div><?php endif; ?>
            <div class="coupon-content">
                <h3 class="coupon-title"><?php echo esc_html( get_the_title( $coupon_id ) ); ?></h3>
                <?php if ( $expiry_date ) : ?><div class="coupon-expiry"><small><?php printf( __( 'Expires: %s', 'love-coupons' ), date_i18n( get_option( 'date_format' ), strtotime( $expiry_date ) ) ); ?></small></div><?php endif; ?>
                <?php if ( $is_upcoming && $start_date ) : ?><div class="coupon-expiry"><small><?php printf( __( 'Starts: %s', 'love-coupons' ), date_i18n( get_option( 'date_format' ), strtotime( $start_date ) ) ); ?></small></div><?php endif; ?>
                <?php if ( $terms ) : ?><details class="coupon-terms"><summary><?php _e( 'Terms & Conditions', 'love-coupons' ); ?></summary><div class="terms-content"><?php echo wp_kses_post( nl2br( $terms ) ); ?></div></details><?php endif; ?>
                <div class="coupon-actions">
                    <?php if ( ! $suppress_redeem ) : ?>
                        <?php if ( $redeemed ) : ?>
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
