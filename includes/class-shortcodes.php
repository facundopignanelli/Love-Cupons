<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once LOVE_COUPONS_PLUGIN_DIR . 'includes/class-core.php';

class Love_Coupons_Shortcodes {
    public function register() {
        add_shortcode( 'love_coupons', array( $this, 'display_coupons_shortcode' ) );
        add_shortcode( 'love_coupons_submit', array( $this, 'display_coupons_submit_shortcode' ) );
        add_shortcode( 'love_coupons_created', array( $this, 'display_coupons_created_shortcode' ) );
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

    public function display_coupons_submit_shortcode( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<div class="love-coupons-login-message"><p>' . __( 'Please log in to create coupons!', 'love-coupons' ) . '</p></div>';
        }
        $current_user_id = get_current_user_id();
        ob_start();
        echo '<div class="love-coupons-wrapper">';
        echo '<div class="love-create-coupon-form-wrapper">';
        echo '<h3>' . __( 'Create New Coupon', 'love-coupons' ) . '</h3>';
        $this->render_create_coupon_form( $current_user_id );
        echo '</div></div>';
        return ob_get_clean();
    }

    public function display_coupons_created_shortcode( $atts ) {
        $atts = shortcode_atts( array( 'limit' => -1, 'category' => '', 'show_expired' => 'yes' ), $atts, 'love_coupons_created' );
        if ( ! is_user_logged_in() ) {
            return '<div class="love-coupons-login-message"><p>' . __( 'Please log in to see the coupons you created!', 'love-coupons' ) . '</p></div>';
        }
        $current_user_id = get_current_user_id();
        ob_start();
        echo '<div class="love-coupons-wrapper">';
        echo $this->render_posted_coupons( $current_user_id, $atts );
        echo '</div>';
        return ob_get_clean();
    }

    private function render_my_coupons( $user_id, $atts ) {
        $query = new WP_Query( array( 'post_type' => 'love_coupon', 'post_status' => 'publish', 'posts_per_page' => intval( $atts['limit'] ) ) );
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
            foreach ( $upcoming as $id ) { $this->render_coupon_item( $id, true ); }
            echo '</div></div><hr class="love-coupons-separator">';
        }

        if ( ! empty( $available ) ) {
            echo '<div class="love-coupons-section">';
            echo '<h3 class="love-coupons-section-title">' . __( 'Available', 'love-coupons' ) . '</h3>';
            echo '<div class="love-coupons-grid">';
            foreach ( $available as $id ) { $this->render_coupon_item( $id, true ); }
            echo '</div></div>';
        }

        if ( ! empty( $redeemed ) ) {
            if ( ! empty( $available ) ) { echo '<hr class="love-coupons-separator">'; }
            echo '<div class="love-coupons-section">';
            echo '<h3 class="love-coupons-section-title">' . __( 'Redeemed', 'love-coupons' ) . '</h3>';
            echo '<div class="love-coupons-grid">';
            foreach ( $redeemed as $id ) { $this->render_coupon_item( $id, true ); }
            echo '</div></div>';
        }

        if ( ! empty( $expired ) ) {
            if ( ! empty( $available ) || ! empty( $redeemed ) ) { echo '<hr class="love-coupons-separator">'; }
            echo '<div class="love-coupons-section">';
            echo '<h3 class="love-coupons-section-title">' . __( 'Expired', 'love-coupons' ) . '</h3>';
            echo '<div class="love-coupons-grid">';
            foreach ( $expired as $id ) { $this->render_coupon_item( $id, true ); }
            echo '</div></div>';
        }

        echo '</div>';
        return ob_get_clean();
    }

    private function render_create_coupon_form( $user_id ) {
        $allowed_recipients = Love_Coupons_Core::get_allowed_recipients_for_user( $user_id );
        if ( empty( $allowed_recipients ) ) { echo '<p style="color:#d63638;">' . __( 'You don\'t have permission to post coupons to any user.', 'love-coupons' ) . '</p>'; return; }
        wp_nonce_field( 'love_create_coupon', 'love_create_coupon_nonce' );
        ?>
        <form class="love-create-coupon-form" id="love-create-coupon-form">
            <div class="form-group"><label for="coupon_title"><?php _e( 'Coupon Title', 'love-coupons' ); ?> <span class="required">*</span></label><input type="text" name="coupon_title" id="coupon_title" required placeholder="<?php _e( 'Enter coupon title', 'love-coupons' ); ?>" /></div>
            <div class="form-group"><label for="coupon_recipient"><?php _e( 'Send To', 'love-coupons' ); ?> <span class="required">*</span></label>
                <select name="coupon_recipient" id="coupon_recipient" required>
                    <option value=""><?php _e( 'Select a user', 'love-coupons' ); ?></option>
                    <?php foreach ( $allowed_recipients as $recipient_id ) : $recipient = get_user_by( 'id', $recipient_id ); if ( $recipient ) : ?>
                        <option value="<?php echo esc_attr( $recipient_id ); ?>"><?php echo esc_html( $recipient->display_name . ' (' . $recipient->user_email . ')' ); ?></option>
                    <?php endif; endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label for="coupon_terms"><?php _e( 'Terms & Conditions', 'love-coupons' ); ?></label>
                <textarea name="coupon_terms" id="coupon_terms" rows="4" placeholder="<?php _e( 'Enter terms and conditions', 'love-coupons' ); ?>"></textarea>
            </div>
            <div class="form-group"><label for="coupon_hero_image"><?php _e( 'Hero Image (16:9)', 'love-coupons' ); ?></label>
                <input type="file" name="coupon_hero_image" id="coupon_hero_image" accept="image/*" />
                <small class="description"><?php _e( 'A 16:9 image works best. Non-16:9 images are center-cropped automatically.', 'love-coupons' ); ?></small>
                <div id="coupon_hero_image_note" style="margin-top:6px;color:#6c757d;display:none;"></div>
                <div id="coupon_hero_preview" class="love-crop-preview" style="display:none;"><img alt="<?php esc_attr_e('Cropped preview','love-coupons');?>"/></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label for="coupon_start_date"><?php _e( 'Start Date', 'love-coupons' ); ?></label><input type="date" name="coupon_start_date" id="coupon_start_date" /></div>
                <div class="form-group"><label for="coupon_expiry_date"><?php _e( 'Expiry Date', 'love-coupons' ); ?></label><input type="date" name="coupon_expiry_date" id="coupon_expiry_date" /></div>
                <div class="form-group"><label for="coupon_usage_limit"><?php _e( 'Usage Limit', 'love-coupons' ); ?></label><input type="number" name="coupon_usage_limit" id="coupon_usage_limit" value="1" min="1" /></div>
            </div>
            <button type="submit" class="button button-primary"><?php _e( 'Create Coupon', 'love-coupons' ); ?></button>
            <div class="form-message" style="display: none;"></div>
        </form>
        <div class="love-modal" id="love-cropper-modal" aria-hidden="true" style="display:none;">
            <div class="love-modal-overlay" data-dismiss></div>
            <div class="love-modal-content" role="dialog" aria-modal="true" aria-labelledby="love-cropper-title">
                <div class="love-modal-header"><h4 id="love-cropper-title"><?php _e('Crop Image','love-coupons');?></h4><button type="button" class="love-modal-close" data-dismiss aria-label="<?php esc_attr_e('Close','love-coupons');?>">×</button></div>
                <div class="love-modal-body"><div class="love-cropper-container"><img id="love-cropper-image" alt="<?php esc_attr_e('Image to crop','love-coupons');?>" /></div><p class="description"><?php _e('Drag to select. Aspect ratio fixed to 16:9.','love-coupons');?></p></div>
                <div class="love-modal-footer"><button type="button" class="button" id="love-cropper-cancel"><?php _e('Cancel','love-coupons');?></button><button type="button" class="button button-primary" id="love-cropper-apply"><?php _e('Crop & Use','love-coupons');?></button></div>
            </div>
        </div>
        <?php
    }

    private function render_coupon_item( $coupon_id, $show_delete = false ) {
        $redeemed = get_post_meta( $coupon_id, '_love_coupon_redeemed', true );
        $terms = get_post_meta( $coupon_id, '_love_coupon_terms', true );
        $expiry_date = get_post_meta( $coupon_id, '_love_coupon_expiry_date', true );
        $start_date = get_post_meta( $coupon_id, '_love_coupon_start_date', true );
        $now = time(); $is_upcoming = $start_date && strtotime( $start_date ) > $now; $is_expired = $expiry_date && strtotime( $expiry_date ) < $now;
        $classes = array( 'love-coupon' ); if ( $redeemed ) $classes[] = 'redeemed'; if ( $is_expired ) $classes[] = 'expired'; if ( $is_upcoming ) $classes[] = 'upcoming';
        ?>
        <div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-coupon-id="<?php echo esc_attr( $coupon_id ); ?>">
            <?php if ( has_post_thumbnail( $coupon_id ) ) : ?><div class="coupon-image"><?php echo get_the_post_thumbnail( $coupon_id, 'medium' ); ?></div><?php endif; ?>
            <div class="coupon-content">
                <h3 class="coupon-title"><?php echo esc_html( get_the_title( $coupon_id ) ); ?></h3>
                <?php if ( $expiry_date ) : ?><div class="coupon-expiry"><small><?php printf( __( 'Expires: %s', 'love-coupons' ), date_i18n( get_option( 'date_format' ), strtotime( $expiry_date ) ) ); ?></small></div><?php endif; ?>
                <?php if ( $is_upcoming && $start_date ) : ?><div class="coupon-expiry"><small><?php printf( __( 'Starts: %s', 'love-coupons' ), date_i18n( get_option( 'date_format' ), strtotime( $start_date ) ) ); ?></small></div><?php endif; ?>
                <?php if ( $terms ) : ?><details class="coupon-terms"><summary><?php _e( 'Terms & Conditions', 'love-coupons' ); ?></summary><div class="terms-content"><?php echo wp_kses_post( nl2br( $terms ) ); ?></div></details><?php endif; ?>
                <div class="coupon-actions">
                    <?php if ( $redeemed ) : ?>
                        <button class="button button-redeemed" disabled><span class="dashicons dashicons-yes"></span><?php _e( 'Redeemed', 'love-coupons' ); ?></button>
                    <?php elseif ( $is_expired ) : ?>
                        <button class="button button-expired" disabled><span class="dashicons dashicons-clock"></span><?php _e( 'Expired', 'love-coupons' ); ?></button>
                    <?php else : ?>
                        <?php if ( $is_upcoming ) : ?>
                            <button class="button button-expired" disabled><span class="dashicons dashicons-clock"></span><?php _e( 'Upcoming', 'love-coupons' ); ?></button>
                        <?php else : ?>
                            <button class="button button-primary redeem-button" data-coupon-id="<?php echo esc_attr( $coupon_id ); ?>"><span class="dashicons dashicons-tickets-alt"></span><?php _e( 'Redeem', 'love-coupons' ); ?></button>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ( $show_delete ) : ?>
                        <button class="button button-secondary delete-coupon" data-coupon-id="<?php echo esc_attr( $coupon_id ); ?>"><?php _e( 'Remove', 'love-coupons' ); ?></button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
}
