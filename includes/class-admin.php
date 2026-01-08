<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Love_Coupons_Admin {
    public function add_admin_columns( $columns ) {
        $new = array();
        foreach ( $columns as $key => $value ) {
            $new[$key] = $value;
            if ( 'title' === $key ) {
                $new['created_by'] = __( 'Created By', 'love-coupons' );
                $new['assigned_to'] = __( 'Assigned To', 'love-coupons' );
                $new['expiry_date'] = __( 'Expires', 'love-coupons' );
                $new['start_date'] = __( 'Starts', 'love-coupons' );
                $new['usage_limit'] = __( 'Usage', 'love-coupons' );
                $new['redeemed'] = __( 'Status', 'love-coupons' );
            }
        }
        return $new;
    }

    public function render_admin_columns( $column, $post_id ) {
        switch ( $column ) {
            case 'created_by':
                $created_by = get_post_meta( $post_id, '_love_coupon_created_by', true );
                $creator = $created_by ? get_user_by( 'id', $created_by ) : null;
                echo $creator ? esc_html( $creator->display_name ) : '—';
                break;
            case 'assigned_to':
                $assigned_to = get_post_meta( $post_id, '_love_coupon_assigned_to', true );
                if ( empty( $assigned_to ) || ! is_array( $assigned_to ) ) {
                    echo '<span style="color: #666;">' . __( 'All Users', 'love-coupons' ) . '</span>';
                } else {
                    $names = array();
                    foreach ( $assigned_to as $uid ) { $u = get_user_by( 'id', $uid ); if ( $u ) { $names[] = esc_html( $u->display_name ); } }
                    echo implode( ', ', $names );
                }
                break;
            case 'expiry_date':
                $expiry_date = get_post_meta( $post_id, '_love_coupon_expiry_date', true );
                if ( $expiry_date ) {
                    $is_expired = strtotime( $expiry_date ) < time();
                    $color = $is_expired ? '#d63638' : '#2c3338';
                    echo '<span style="color:' . $color . ';">' . date_i18n( get_option( 'date_format' ), strtotime( $expiry_date ) ) . '</span>';
                } else {
                    echo '—';
                }
                break;
            case 'start_date':
                $start_date = get_post_meta( $post_id, '_love_coupon_start_date', true );
                echo $start_date ? date_i18n( get_option( 'date_format' ), strtotime( $start_date ) ) : '—';
                break;
            case 'usage_limit':
                $usage_limit = get_post_meta( $post_id, '_love_coupon_usage_limit', true );
                $redemption_count = get_post_meta( $post_id, '_love_coupon_redemption_count', true );
                echo intval( $redemption_count ) . ' / ' . ( $usage_limit ? intval( $usage_limit ) : '∞' );
                break;
            case 'redeemed':
                $redeemed = get_post_meta( $post_id, '_love_coupon_redeemed', true );
                $expiry_date = get_post_meta( $post_id, '_love_coupon_expiry_date', true );
                $start_date = get_post_meta( $post_id, '_love_coupon_start_date', true );
                $is_expired = $expiry_date && strtotime( $expiry_date ) < time();
                $is_upcoming = $start_date && strtotime( $start_date ) > time();
                
                if ( $redeemed ) {
                    echo '<span style="color:#d63638; font-weight: 600;">✓ ' . __( 'Redeemed', 'love-coupons' ) . '</span>';
                } elseif ( $is_expired ) {
                    echo '<span style="color:#ff9900; font-weight: 600;">⏰ ' . __( 'Expired', 'love-coupons' ) . '</span>';
                } elseif ( $is_upcoming ) {
                    echo '<span style="color:#0d6efd; font-weight: 600;">📅 ' . __( 'Upcoming', 'love-coupons' ) . '</span>';
                } else {
                    echo '<span style="color:#00a32a; font-weight: 600;">✓ ' . __( 'Available', 'love-coupons' ) . '</span>';
                }
                break;
        }
    }

    public function add_meta_boxes() {
        add_meta_box( 'love_coupon_details', __( 'Coupon Details', 'love-coupons' ), array( $this, 'render_meta_box' ), 'love_coupon', 'normal', 'high' );
        add_meta_box( 'love_coupon_recipients', __( 'Assign To Users', 'love-coupons' ), array( $this, 'render_recipients_meta_box' ), 'love_coupon', 'normal', 'high' );
        add_meta_box( 'love_coupon_settings', __( 'Coupon Settings', 'love-coupons' ), array( $this, 'render_settings_meta_box' ), 'love_coupon', 'side', 'high' );
    }

    public function render_meta_box( $post ) {
        wp_nonce_field( 'love_coupon_save', 'love_coupon_nonce' );
        $terms = get_post_meta( $post->ID, '_love_coupon_terms', true );
        $expiry_date = get_post_meta( $post->ID, '_love_coupon_expiry_date', true );
        $start_date = get_post_meta( $post->ID, '_love_coupon_start_date', true );
        $usage_limit = get_post_meta( $post->ID, '_love_coupon_usage_limit', true );
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="love_coupon_terms"><?php _e( 'Terms & Conditions', 'love-coupons' ); ?></label></th>
                <td>
                    <textarea id="love_coupon_terms" name="love_coupon_terms" rows="4" cols="50" class="large-text"><?php echo esc_textarea( $terms ); ?></textarea>
                    <p class="description"><?php _e( 'Enter the terms and conditions for this coupon.', 'love-coupons' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="love_coupon_expiry_date"><?php _e( 'Expiry Date', 'love-coupons' ); ?></label></th>
                <td>
                    <input type="date" id="love_coupon_expiry_date" name="love_coupon_expiry_date" value="<?php echo esc_attr( $expiry_date ); ?>" />
                    <p class="description"><?php _e( 'Optional: Set an expiry date for this coupon.', 'love-coupons' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="love_coupon_start_date"><?php _e( 'Start Date', 'love-coupons' ); ?></label></th>
                <td>
                    <input type="date" id="love_coupon_start_date" name="love_coupon_start_date" value="<?php echo esc_attr( $start_date ); ?>" />
                    <p class="description"><?php _e( 'Optional: Schedule this coupon to start on a specific date.', 'love-coupons' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="love_coupon_usage_limit"><?php _e( 'Usage Limit', 'love-coupons' ); ?></label></th>
                <td>
                    <input type="number" id="love_coupon_usage_limit" name="love_coupon_usage_limit" value="<?php echo esc_attr( $usage_limit ?: 1 ); ?>" min="1" />
                    <p class="description"><?php _e( 'How many times can this coupon be redeemed? (Default: 1)', 'love-coupons' ); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function render_recipients_meta_box( $post ) {
        $assigned_to = get_post_meta( $post->ID, '_love_coupon_assigned_to', true );
        if ( ! is_array( $assigned_to ) ) { $assigned_to = array(); }
        $users = get_users( array( 'orderby' => 'display_name', 'order' => 'ASC' ) );
        ?>
        <p><?php _e( 'Select which users this coupon should be assigned to. Leave empty to make available to all users.', 'love-coupons' ); ?></p>
        <div style="border: 1px solid #ddd; padding: 10px; max-height: 300px; overflow-y: auto;">
            <?php foreach ( $users as $user ) : ?>
                <label style="display: block; margin-bottom: 8px; cursor: pointer;">
                    <input type="checkbox" name="love_coupon_assigned_to[]" value="<?php echo esc_attr( $user->ID ); ?>" <?php checked( in_array( $user->ID, $assigned_to ) ); ?> />
                    <strong><?php echo esc_html( $user->display_name ); ?></strong> (<?php echo esc_html( $user->user_email ); ?>)
                </label>
            <?php endforeach; ?>
        </div>
        <p class="description" style="margin-top: 10px;">
            <?php _e( 'Currently assigned to: ', 'love-coupons' ); ?>
            <strong id="assigned-count"><?php echo count( $assigned_to ); ?></strong> <?php _e( 'user(s)', 'love-coupons' ); ?>
        </p>
        <script>
        jQuery(document).ready(function($){
            $('input[name="love_coupon_assigned_to[]"]').on('change', function(){
                var count = $('input[name="love_coupon_assigned_to[]"]:checked').length;
                $('#assigned-count').text(count);
            });
        });
        </script>
        <?php
    }

    public function render_settings_meta_box( $post ) {
        $redeemed = get_post_meta( $post->ID, '_love_coupon_redeemed', true );
        $redemption_date = get_post_meta( $post->ID, '_love_coupon_redemption_date', true );
        $redemption_count = get_post_meta( $post->ID, '_love_coupon_redemption_count', true );
        $created_by = get_post_meta( $post->ID, '_love_coupon_created_by', true );
        if ( ! $created_by && $post->post_author ) { $created_by = $post->post_author; }
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
                <button type="button" class="button button-secondary" onclick="if(confirm('<?php _e( 'Are you sure you want to reset this coupon?', 'love-coupons' ); ?>')){jQuery('#love_coupon_reset').val('1');jQuery('#post').submit();}"><?php _e( 'Reset Coupon', 'love-coupons' ); ?></button>
                <input type="hidden" name="love_coupon_reset" id="love_coupon_reset" value="0" />
            </p>
        <?php endif; ?>
        <?php
    }

    public function save_meta( $post_id ) {
        if ( ! isset( $_POST['love_coupon_nonce'] ) || ! wp_verify_nonce( $_POST['love_coupon_nonce'], 'love_coupon_save' ) ) { return; }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
        if ( 'love_coupon' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) { return; }
        $fields = array(
            '_love_coupon_terms' => 'sanitize_textarea_field',
            '_love_coupon_expiry_date' => 'sanitize_text_field',
            '_love_coupon_start_date' => 'sanitize_text_field',
            '_love_coupon_usage_limit' => 'absint',
        );
        foreach ( $fields as $field => $sanitizer ) {
            $post_field = str_replace( '_love_coupon_', 'love_coupon_', $field );
            if ( isset( $_POST[ $post_field ] ) ) {
                $value = call_user_func( $sanitizer, $_POST[ $post_field ] );
                update_post_meta( $post_id, $field, $value );
            }
        }
        if ( isset( $_POST['love_coupon_assigned_to'] ) ) {
            $assigned_to = array();
            if ( is_array( $_POST['love_coupon_assigned_to'] ) ) {
                foreach ( $_POST['love_coupon_assigned_to'] as $user_id ) { $user_id = absint( $user_id ); if ( $user_id > 0 && get_user_by( 'id', $user_id ) ) { $assigned_to[] = $user_id; } }
            }
            update_post_meta( $post_id, '_love_coupon_assigned_to', array_unique( $assigned_to ) );
        } else {
            delete_post_meta( $post_id, '_love_coupon_assigned_to' );
        }
        if ( ! get_post_meta( $post_id, '_love_coupon_created_by', true ) ) { update_post_meta( $post_id, '_love_coupon_created_by', get_current_user_id() ); }
        if ( isset( $_POST['love_coupon_reset'] ) && '1' === $_POST['love_coupon_reset'] ) {
            delete_post_meta( $post_id, '_love_coupon_redeemed' );
            delete_post_meta( $post_id, '_love_coupon_redemption_date' );
            update_post_meta( $post_id, '_love_coupon_redemption_count', 0 );
        }
    }

    public function add_admin_settings_page() {
        add_submenu_page(
            'edit.php?post_type=love_coupon',
            __( 'Coupon Posting Permissions', 'love-coupons' ),
            __( 'Posting Permissions', 'love-coupons' ),
            'manage_options',
            'love_coupons_permissions',
            array( $this, 'render_admin_settings_page' )
        );
        
        add_submenu_page(
            'edit.php?post_type=love_coupon',
            __( 'Push Notification Settings', 'love-coupons' ),
            __( 'Push Notifications', 'love-coupons' ),
            'manage_options',
            'love_coupons_push_settings',
            array( $this, 'render_push_settings_page' )
        );
    }

    public function render_admin_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( __( 'You do not have permission to access this page.', 'love-coupons' ) ); }
        if ( isset( $_POST['love_coupons_save_permissions'] ) && check_admin_referer( 'love_coupons_permissions_nonce' ) ) {
            $restrictions = isset( $_POST['love_coupons_restrictions'] ) ? $_POST['love_coupons_restrictions'] : array();
            $sanitized = array();
            foreach ( $restrictions as $user_id => $allowed ) {
                $user_id = absint( $user_id );
                if ( $user_id > 0 ) {
                    $sanitized[ $user_id ] = array();
                    if ( isset( $allowed['all'] ) && $allowed['all'] === 'on' ) {
                        $sanitized[ $user_id ][] = 'all';
                    } else {
                        if ( isset( $allowed['users'] ) && is_array( $allowed['users'] ) ) {
                            foreach ( $allowed['users'] as $recipient_id ) {
                                $recipient_id = absint( $recipient_id );
                                if ( $recipient_id > 0 && get_user_by( 'id', $recipient_id ) ) { $sanitized[ $user_id ][] = $recipient_id; }
                            }
                        }
                    }
                }
            }
            update_option( 'love_coupons_posting_restrictions', $sanitized );
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
                        <tr><th><?php _e( 'User', 'love-coupons' ); ?></th><th><?php _e( 'Can Post To', 'love-coupons' ); ?></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $all_users as $user ) : ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html( $user->display_name ); ?></strong><br>
                                    <small><?php echo esc_html( $user->user_email ); ?></small>
                                </td>
                                <td>
                                    <label style="margin-bottom: 10px; display: block;">
                                        <input type="checkbox" name="love_coupons_restrictions[<?php echo esc_attr( $user->ID ); ?>][all]" <?php checked( ! empty( $restrictions[ $user->ID ] ) && in_array( 'all', $restrictions[ $user->ID ] ) ); ?> />
                                        <?php _e( 'Can post to all users', 'love-coupons' ); ?>
                                    </label>
                                    <fieldset style="margin-left: 20px; border-left: 2px solid #ddd; padding-left: 10px;">
                                        <legend><?php _e( 'Or select specific users:', 'love-coupons' ); ?></legend>
                                        <div style="max-height: 200px; overflow-y: auto;">
                                            <?php foreach ( $all_users as $recipient ) : if ( $recipient->ID === $user->ID ) continue; $is_checked = ! empty( $restrictions[ $user->ID ] ) && ! in_array( 'all', $restrictions[ $user->ID ] ) && in_array( $recipient->ID, $restrictions[ $user->ID ] ); ?>
                                                <label style="display: block; margin-bottom: 5px;">
                                                    <input type="checkbox" name="love_coupons_restrictions[<?php echo esc_attr( $user->ID ); ?>][users][]" value="<?php echo esc_attr( $recipient->ID ); ?>" <?php checked( $is_checked ); ?> />
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

    public function render_push_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to access this page.', 'love-coupons' ) );
        }

        // Handle form submission
        if ( isset( $_POST['love_coupons_save_push_settings'] ) && check_admin_referer( 'love_coupons_push_settings_nonce' ) ) {
            $vapid_public_key = isset( $_POST['vapid_public_key'] ) ? sanitize_text_field( $_POST['vapid_public_key'] ) : '';
            $vapid_private_key = isset( $_POST['vapid_private_key'] ) ? sanitize_text_field( $_POST['vapid_private_key'] ) : '';
            
            update_option( 'love_coupons_vapid_public_key', $vapid_public_key );
            update_option( 'love_coupons_vapid_private_key', $vapid_private_key );
            
            echo '<div class="notice notice-success"><p>' . __( 'Push notification settings saved successfully!', 'love-coupons' ) . '</p></div>';
        }

        $vapid_public_key = get_option( 'love_coupons_vapid_public_key', '' );
        $vapid_private_key = get_option( 'love_coupons_vapid_private_key', '' );
        ?>
        <div class="wrap">
            <h1><?php _e( 'Push Notification Settings', 'love-coupons' ); ?></h1>
            
            <div class="notice notice-info">
                <p>
                    <strong><?php _e( 'How to generate VAPID keys:', 'love-coupons' ); ?></strong>
                </p>
                <ol>
                    <li><?php _e( 'Install Node.js if you haven\'t already', 'love-coupons' ); ?></li>
                    <li><?php _e( 'Run this command in your terminal:', 'love-coupons' ); ?> <code>npx web-push generate-vapid-keys</code></li>
                    <li><?php _e( 'Or use an online tool:', 'love-coupons' ); ?> <a href="https://web-push-codelab.glitch.me/" target="_blank">https://web-push-codelab.glitch.me/</a></li>
                    <li><?php _e( 'Copy and paste the public and private keys below', 'love-coupons' ); ?></li>
                </ol>
            </div>

            <form method="post" style="max-width: 800px;">
                <?php wp_nonce_field( 'love_coupons_push_settings_nonce' ); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="vapid_public_key"><?php _e( 'VAPID Public Key', 'love-coupons' ); ?></label>
                        </th>
                        <td>
                            <input 
                                type="text" 
                                id="vapid_public_key" 
                                name="vapid_public_key" 
                                value="<?php echo esc_attr( $vapid_public_key ); ?>" 
                                class="large-text code"
                                placeholder="BEl62iUYgUivxIkv69yViEuiBIa-Ib37gp2..."
                            />
                            <p class="description">
                                <?php _e( 'Your VAPID public key. This will be shared with browsers to subscribe to push notifications.', 'love-coupons' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="vapid_private_key"><?php _e( 'VAPID Private Key', 'love-coupons' ); ?></label>
                        </th>
                        <td>
                            <input 
                                type="password" 
                                id="vapid_private_key" 
                                name="vapid_private_key" 
                                value="<?php echo esc_attr( $vapid_private_key ); ?>" 
                                class="large-text code"
                                placeholder="dGhlIHNhbXBsZSBub25jZQ=="
                            />
                            <p class="description">
                                <?php _e( 'Your VAPID private key. Keep this secret! It\'s used to sign push notification requests.', 'love-coupons' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php _e( 'Status', 'love-coupons' ); ?>
                        </th>
                        <td>
                            <?php if ( empty( $vapid_public_key ) || empty( $vapid_private_key ) ) : ?>
                                <span style="color: #d63638;">
                                    <span class="dashicons dashicons-warning"></span>
                                    <?php _e( 'Push notifications are not configured. Please add your VAPID keys above.', 'love-coupons' ); ?>
                                </span>
                            <?php else : ?>
                                <span style="color: #00a32a;">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <?php _e( 'Push notifications are configured and ready to use!', 'love-coupons' ); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <?php submit_button( __( 'Save Settings', 'love-coupons' ), 'primary', 'love_coupons_save_push_settings' ); ?>
            </form>

            <hr style="margin: 40px 0;">

            <h2><?php _e( 'Additional Setup Required', 'love-coupons' ); ?></h2>
            <div class="notice notice-warning">
                <p>
                    <strong><?php _e( 'To actually send push notifications, you need to install the web-push library:', 'love-coupons' ); ?></strong>
                </p>
                <ol>
                    <li><?php _e( 'Navigate to your plugin directory in terminal', 'love-coupons' ); ?></li>
                    <li><?php _e( 'Run:', 'love-coupons' ); ?> <code>composer require minishlink/web-push</code></li>
                    <li><?php _e( 'The notification sending code will then work automatically', 'love-coupons' ); ?></li>
                </ol>
                <p>
                    <?php _e( 'Without this library, the system will fall back to sending email notifications.', 'love-coupons' ); ?>
                </p>
            </div>
        </div>
        <?php
    }
}
