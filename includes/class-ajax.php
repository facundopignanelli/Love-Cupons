<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once LOVE_COUPONS_PLUGIN_DIR . 'includes/class-core.php';

class Love_Coupons_Ajax {
    public function ajax_redeem_coupon() {
        if ( ! check_ajax_referer( 'love_coupons_nonce', 'security', false ) ) {
            wp_send_json_error( __( 'Security check failed.', 'love-coupons' ) );
        }
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( __( 'You must be logged in to redeem coupons.', 'love-coupons' ) );
        }
        $coupon_id = isset( $_POST['coupon_id'] ) ? absint( $_POST['coupon_id'] ) : 0;
        if ( ! $coupon_id || 'love_coupon' !== get_post_type( $coupon_id ) ) {
            wp_send_json_error( __( 'Invalid coupon.', 'love-coupons' ) );
        }
        $coupon = get_post( $coupon_id );
        if ( ! $coupon || 'publish' !== $coupon->post_status ) {
            wp_send_json_error( __( 'Coupon not found or not available.', 'love-coupons' ) );
        }
        $current_user = wp_get_current_user();
        if ( ! Love_Coupons_Core::user_can_access_coupon( $coupon_id, $current_user->ID ) ) {
            wp_send_json_error( __( 'You do not have access to this coupon.', 'love-coupons' ) );
        }

        $start_date = get_post_meta( $coupon_id, '_love_coupon_start_date', true );
        if ( $start_date && strtotime( $start_date ) > time() ) {
            wp_send_json_error( __( 'This coupon is not yet available.', 'love-coupons' ) );
        }
        $expiry_date = get_post_meta( $coupon_id, '_love_coupon_expiry_date', true );
        if ( $expiry_date && strtotime( $expiry_date ) < time() ) {
            wp_send_json_error( __( 'This coupon has expired.', 'love-coupons' ) );
        }

        $usage_limit = absint( get_post_meta( $coupon_id, '_love_coupon_usage_limit', true ) );
        $redemption_count = intval( get_post_meta( $coupon_id, '_love_coupon_redemption_count', true ) );
        $legacy_redeemed = get_post_meta( $coupon_id, '_love_coupon_redeemed', true );

        $is_fully_redeemed = $usage_limit > 0 ? ( $redemption_count >= $usage_limit ) : (bool) $legacy_redeemed;
        if ( $is_fully_redeemed ) {
            wp_send_json_error( __( 'This coupon has reached its usage limit.', 'love-coupons' ) );
        }

        $redemption_data = array(
            'user_id' => $current_user->ID,
            'user_email' => $current_user->user_email,
            'redemption_date' => current_time( 'mysql' ),
            'ip_address' => Love_Coupons_Core::get_user_ip(),
        );

        $new_count      = $redemption_count + 1;
        $remaining      = $usage_limit > 0 ? max( 0, $usage_limit - $new_count ) : null;
        $fully_redeemed = $usage_limit > 0 ? ( $new_count >= $usage_limit ) : false;

        if ( $fully_redeemed ) {
            update_post_meta( $coupon_id, '_love_coupon_redeemed', true );
        } else {
            delete_post_meta( $coupon_id, '_love_coupon_redeemed' );
        }

        update_post_meta( $coupon_id, '_love_coupon_redemption_date', current_time( 'mysql' ) );
        update_post_meta( $coupon_id, '_love_coupon_redemption_count', $new_count );
        update_post_meta( $coupon_id, '_love_coupon_redemption_data', $redemption_data );

        Love_Coupons_Core::send_redemption_email( $coupon_id, $current_user );
        Love_Coupons_Core::log_redemption( $coupon_id, $current_user );

        $message = __( 'Coupon redeemed successfully!', 'love-coupons' );
        if ( $usage_limit > 0 ) {
            /* translators: 1: remaining uses, 2: total uses */
            $message = sprintf( __( 'Coupon redeemed! %1$d of %2$d uses left.', 'love-coupons' ), max( 0, $remaining ), $usage_limit );
        }

        wp_send_json_success( array(
            'message'   => $message,
            'remaining' => $remaining,
            'limit'     => $usage_limit,
            'count'     => $new_count,
        ) );
    }

    public function ajax_create_coupon() {
        if ( ! check_ajax_referer( 'love_create_coupon', 'nonce', false ) ) {
            wp_send_json_error( __( 'Security check failed.', 'love-coupons' ) );
        }
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( __( 'You must be logged in to create coupons.', 'love-coupons' ) );
        }
        $current_user_id = get_current_user_id();
        $title = isset( $_POST['coupon_title'] ) ? sanitize_text_field( $_POST['coupon_title'] ) : '';
        $terms = isset( $_POST['coupon_terms'] ) ? sanitize_textarea_field( $_POST['coupon_terms'] ) : '';
        $expiry_date = isset( $_POST['coupon_expiry_date'] ) ? sanitize_text_field( $_POST['coupon_expiry_date'] ) : '';
        $start_date  = isset( $_POST['coupon_start_date'] ) ? sanitize_text_field( $_POST['coupon_start_date'] ) : '';
        $usage_limit = isset( $_POST['coupon_usage_limit'] ) ? absint( $_POST['coupon_usage_limit'] ) : 1;
        if ( empty( $title ) ) {
            wp_send_json_error( __( 'Coupon title is required.', 'love-coupons' ) );
        }
        if ( empty( $expiry_date ) ) {
            wp_send_json_error( __( 'Valid until date is required.', 'love-coupons' ) );
        }
        $coupon_id = wp_insert_post( array(
            'post_type' => 'love_coupon',
            'post_title' => $title,
            'post_status' => 'publish',
            'post_author' => $current_user_id,
        ) );
        if ( is_wp_error( $coupon_id ) ) {
            wp_send_json_error( __( 'Failed to create coupon.', 'love-coupons' ) );
        }
        update_post_meta( $coupon_id, '_love_coupon_created_by', $current_user_id );
        $restrictions = get_option( 'love_coupons_posting_restrictions', array() );
        $assigned_to  = array();
        
        if ( ! empty( $restrictions[ $current_user_id ] ) && in_array( 'all', $restrictions[ $current_user_id ], true ) ) {
            // Admin set to 'all' - leave assigned_to empty to allow all users
            delete_post_meta( $coupon_id, '_love_coupon_assigned_to' );
        } elseif ( ! empty( $restrictions[ $current_user_id ] ) && is_array( $restrictions[ $current_user_id ] ) ) {
            $assigned_to = array_filter( array_map( 'absint', $restrictions[ $current_user_id ] ), function( $uid ) use ( $current_user_id ) {
                return $uid > 0 && $uid !== $current_user_id && get_user_by( 'id', $uid );
            } );
            if ( ! empty( $assigned_to ) ) {
                update_post_meta( $coupon_id, '_love_coupon_assigned_to', array_values( array_unique( $assigned_to ) ) );
            } else {
                delete_post_meta( $coupon_id, '_love_coupon_assigned_to' );
            }
        } else {
            delete_post_meta( $coupon_id, '_love_coupon_assigned_to' );
        }
        update_post_meta( $coupon_id, '_love_coupon_terms', $terms );
        update_post_meta( $coupon_id, '_love_coupon_expiry_date', $expiry_date );
        if ( ! empty( $start_date ) ) { update_post_meta( $coupon_id, '_love_coupon_start_date', $start_date ); }
        update_post_meta( $coupon_id, '_love_coupon_usage_limit', $usage_limit );
        update_post_meta( $coupon_id, '_love_coupon_redemption_count', 0 );

        if ( isset( $_FILES['coupon_hero_image'] ) && ! empty( $_FILES['coupon_hero_image']['tmp_name'] ) ) {
            $uploaded = wp_handle_upload( $_FILES['coupon_hero_image'], array( 'test_form' => false ) );
            if ( isset( $uploaded['file'] ) && empty( $uploaded['error'] ) ) {
                $file_path = $uploaded['file'];
                $editor = wp_get_image_editor( $file_path );
                if ( ! is_wp_error( $editor ) ) {
                    $size = $editor->get_size();
                    if ( isset( $size['width'], $size['height'] ) ) {
                        $target_ratio = 16 / 9; $width = (int) $size['width']; $height = (int) $size['height'];
                        $ratio = $width / $height;
                        if ( abs( $ratio - $target_ratio ) > 0.02 ) {
                            if ( $ratio > $target_ratio ) { $new_width = (int) round( $height * $target_ratio ); $src_x = (int) max( 0, floor( ( $width - $new_width ) / 2 ) ); $editor->crop( $src_x, 0, $new_width, $height ); }
                            else { $new_height = (int) round( $width / $target_ratio ); $src_y = (int) max( 0, floor( ( $height - $new_height ) / 2 ) ); $editor->crop( 0, $src_y, $width, $new_height ); }
                        }
                        $saved = $editor->save( $file_path );
                        if ( ! is_wp_error( $saved ) ) {
                            $filetype = wp_check_filetype( basename( $file_path ), null );
                            $attachment = array(
                                'post_mime_type' => $filetype['type'],
                                'post_title'     => sanitize_file_name( basename( $file_path ) ),
                                'post_content'   => '',
                                'post_status'    => 'inherit',
                            );
                            $attach_id = wp_insert_attachment( $attachment, $file_path, $coupon_id );
                            if ( ! is_wp_error( $attach_id ) ) {
                                require_once ABSPATH . 'wp-admin/includes/image.php';
                                $attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );
                                wp_update_attachment_metadata( $attach_id, $attach_data );
                                set_post_thumbnail( $coupon_id, $attach_id );
                            }
                        }
                    }
                }
            }
        }

        wp_send_json_success( array( 'message' => __( 'Coupon created successfully!', 'love-coupons' ), 'coupon_id' => $coupon_id ) );
    }

    public function ajax_save_preferences() {
        if ( ! check_ajax_referer( 'love_coupons_nonce', 'security', false ) ) {
            wp_send_json_error( __( 'Security check failed.', 'love-coupons' ) );
        }
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( __( 'You must be logged in to update preferences.', 'love-coupons' ) );
        }

        $current_user_id = get_current_user_id();
        $recipients      = isset( $_POST['recipients'] ) ? (array) $_POST['recipients'] : array();
        $accent_choice   = isset( $_POST['accent_color'] ) ? sanitize_text_field( wp_unslash( $_POST['accent_color'] ) ) : '';

        // Handle appearance-only updates (when only accent_color is sent)
        if ( ! empty( $accent_choice ) && empty( $recipients ) ) {
            $accent_slug = Love_Coupons_Core::sanitize_accent_choice( $accent_choice );
            if ( $accent_slug ) {
                update_user_meta( $current_user_id, '_love_coupons_accent_color', $accent_slug );
            }

            wp_send_json_success( array(
                'message' => __( 'Appearance saved.', 'love-coupons' ),
                'accent'  => Love_Coupons_Core::get_user_accent_color( $current_user_id ),
            ) );
        }

        // Handle recipients update (requires at least one recipient)
        if ( empty( $recipients ) ) {
            wp_send_json_error( __( 'Please select at least one user.', 'love-coupons' ) );
        }

        $restrictions = get_option( 'love_coupons_posting_restrictions', array() );
        $restrictions[ $current_user_id ] = array();

        foreach ( $recipients as $recipient_id ) {
            $recipient_id = absint( $recipient_id );
            if ( $recipient_id > 0 && $recipient_id !== $current_user_id && get_user_by( 'id', $recipient_id ) ) {
                $restrictions[ $current_user_id ][] = $recipient_id;
            }
        }
        $restrictions[ $current_user_id ] = array_values( array_unique( $restrictions[ $current_user_id ] ) );

        update_option( 'love_coupons_posting_restrictions', $restrictions );
        update_user_meta( $current_user_id, '_love_coupons_allowed_recipients', $restrictions[ $current_user_id ] );

        $accent_slug = Love_Coupons_Core::sanitize_accent_choice( $accent_choice );
        if ( $accent_slug ) {
            update_user_meta( $current_user_id, '_love_coupons_accent_color', $accent_slug );
        }

        wp_send_json_success( array(
            'message'    => __( 'Preferences saved.', 'love-coupons' ),
            'accent'     => Love_Coupons_Core::get_user_accent_color( $current_user_id ),
            'recipients' => $restrictions[ $current_user_id ],
        ) );
    }

    public function ajax_delete_coupon() {
        if ( ! check_ajax_referer( 'love_coupons_nonce', 'security', false ) ) {
            wp_send_json_error( __( 'Security check failed.', 'love-coupons' ) );
        }
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( __( 'You must be logged in to delete coupons.', 'love-coupons' ) );
        }
        $coupon_id = isset( $_POST['coupon_id'] ) ? absint( $_POST['coupon_id'] ) : 0;
        if ( ! $coupon_id || 'love_coupon' !== get_post_type( $coupon_id ) ) {
            wp_send_json_error( __( 'Invalid coupon.', 'love-coupons' ) );
        }
        $coupon = get_post( $coupon_id );
        if ( ! $coupon ) {
            wp_send_json_error( __( 'Coupon not found.', 'love-coupons' ) );
        }

        $current_user_id = get_current_user_id();
        $created_by = get_post_meta( $coupon_id, '_love_coupon_created_by', true );
        if ( intval( $created_by ) !== $current_user_id && ! current_user_can( 'delete_others_posts' ) ) {
            wp_send_json_error( __( 'You do not have permission to delete this coupon.', 'love-coupons' ) );
        }

        $deleted = wp_trash_post( $coupon_id );
        if ( ! $deleted ) {
            wp_send_json_error( __( 'Failed to delete coupon.', 'love-coupons' ) );
        }

        wp_send_json_success( __( 'Coupon removed.', 'love-coupons' ) );
    }

    public function ajax_get_coupon() {
        if ( ! check_ajax_referer( 'love_coupons_nonce', 'security', false ) ) {
            wp_send_json_error( __( 'Security check failed.', 'love-coupons' ) );
        }
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( __( 'You must be logged in to edit coupons.', 'love-coupons' ) );
        }
        $coupon_id = isset( $_POST['coupon_id'] ) ? absint( $_POST['coupon_id'] ) : 0;
        if ( ! $coupon_id || 'love_coupon' !== get_post_type( $coupon_id ) ) {
            wp_send_json_error( __( 'Invalid coupon.', 'love-coupons' ) );
        }
        $coupon = get_post( $coupon_id );
        if ( ! $coupon || 'publish' !== $coupon->post_status ) {
            wp_send_json_error( __( 'Coupon not found or not available.', 'love-coupons' ) );
        }

        $current_user_id = get_current_user_id();
        $created_by = get_post_meta( $coupon_id, '_love_coupon_created_by', true );
        if ( intval( $created_by ) !== $current_user_id && ! current_user_can( 'edit_others_posts' ) ) {
            wp_send_json_error( __( 'You do not have permission to edit this coupon.', 'love-coupons' ) );
        }

        $coupon_data = array(
            'id' => $coupon_id,
            'title' => $coupon->post_title,
            'description' => $coupon->post_content,
            'terms' => get_post_meta( $coupon_id, '_love_coupon_terms', true ),
            'start_date' => get_post_meta( $coupon_id, '_love_coupon_start_date', true ),
            'expiry_date' => get_post_meta( $coupon_id, '_love_coupon_expiry_date', true ),
            'usage_limit' => get_post_meta( $coupon_id, '_love_coupon_usage_limit', true ),
            'redemption_count' => intval( get_post_meta( $coupon_id, '_love_coupon_redemption_count', true ) ),
            'assigned_to' => (array) get_post_meta( $coupon_id, '_love_coupon_assigned_to', true ),
            'image_url' => has_post_thumbnail( $coupon_id ) ? get_the_post_thumbnail_url( $coupon_id, 'large' ) : '',
        );

        wp_send_json_success( $coupon_data );
    }

    public function ajax_update_coupon() {
        if ( ! check_ajax_referer( 'love_create_coupon', 'nonce', false ) ) {
            wp_send_json_error( __( 'Security check failed.', 'love-coupons' ) );
        }
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( __( 'You must be logged in to update coupons.', 'love-coupons' ) );
        }
        
        $coupon_id = isset( $_POST['coupon_id'] ) ? absint( $_POST['coupon_id'] ) : 0;
        if ( ! $coupon_id || 'love_coupon' !== get_post_type( $coupon_id ) ) {
            wp_send_json_error( __( 'Invalid coupon.', 'love-coupons' ) );
        }
        
        $coupon = get_post( $coupon_id );
        if ( ! $coupon || 'publish' !== $coupon->post_status ) {
            wp_send_json_error( __( 'Coupon not found or not available.', 'love-coupons' ) );
        }
        
        $current_user_id = get_current_user_id();
        $created_by = get_post_meta( $coupon_id, '_love_coupon_created_by', true );
        if ( intval( $created_by ) !== $current_user_id && ! current_user_can( 'edit_others_posts' ) ) {
            wp_send_json_error( __( 'You do not have permission to edit this coupon.', 'love-coupons' ) );
        }
        
        $title = isset( $_POST['coupon_title'] ) ? sanitize_text_field( $_POST['coupon_title'] ) : '';
        $terms = isset( $_POST['coupon_terms'] ) ? sanitize_textarea_field( $_POST['coupon_terms'] ) : '';
        $expiry_date = isset( $_POST['coupon_expiry_date'] ) ? sanitize_text_field( $_POST['coupon_expiry_date'] ) : '';
        $start_date = isset( $_POST['coupon_start_date'] ) ? sanitize_text_field( $_POST['coupon_start_date'] ) : '';
        $usage_limit = isset( $_POST['coupon_usage_limit'] ) ? absint( $_POST['coupon_usage_limit'] ) : 1;
        
        if ( empty( $title ) ) {
            wp_send_json_error( __( 'Coupon title is required.', 'love-coupons' ) );
        }
        if ( empty( $expiry_date ) ) {
            wp_send_json_error( __( 'Valid until date is required.', 'love-coupons' ) );
        }
        
        // Update post
        wp_update_post( array(
            'ID' => $coupon_id,
            'post_title' => $title,
            'post_content' => isset( $_POST['coupon_description'] ) ? sanitize_textarea_field( $_POST['coupon_description'] ) : '',
        ) );
        
        // Update meta
        update_post_meta( $coupon_id, '_love_coupon_terms', $terms );
        update_post_meta( $coupon_id, '_love_coupon_expiry_date', $expiry_date );
        
        if ( ! empty( $start_date ) ) {
            update_post_meta( $coupon_id, '_love_coupon_start_date', $start_date );
        } else {
            delete_post_meta( $coupon_id, '_love_coupon_start_date' );
        }
        
        update_post_meta( $coupon_id, '_love_coupon_usage_limit', $usage_limit );
        
        // Handle image upload if new one provided
        if ( isset( $_FILES['coupon_hero_image'] ) && ! empty( $_FILES['coupon_hero_image']['tmp_name'] ) ) {
            $uploaded = wp_handle_upload( $_FILES['coupon_hero_image'], array( 'test_form' => false ) );
            if ( isset( $uploaded['file'] ) && empty( $uploaded['error'] ) ) {
                $file_path = $uploaded['file'];
                $editor = wp_get_image_editor( $file_path );
                if ( ! is_wp_error( $editor ) ) {
                    $size = $editor->get_size();
                    if ( isset( $size['width'], $size['height'] ) ) {
                        $target_ratio = 16 / 9;
                        $width = (int) $size['width'];
                        $height = (int) $size['height'];
                        $ratio = $width / $height;
                        if ( abs( $ratio - $target_ratio ) > 0.02 ) {
                            if ( $ratio > $target_ratio ) {
                                $new_width = (int) round( $height * $target_ratio );
                                $src_x = (int) max( 0, floor( ( $width - $new_width ) / 2 ) );
                                $editor->crop( $src_x, 0, $new_width, $height );
                            } else {
                                $new_height = (int) round( $width / $target_ratio );
                                $src_y = (int) max( 0, floor( ( $height - $new_height ) / 2 ) );
                                $editor->crop( 0, $src_y, $width, $new_height );
                            }
                        }
                        $saved = $editor->save( $file_path );
                        if ( ! is_wp_error( $saved ) ) {
                            $filetype = wp_check_filetype( basename( $file_path ), null );
                            $attachment = array(
                                'post_mime_type' => $filetype['type'],
                                'post_title' => sanitize_file_name( basename( $file_path ) ),
                                'post_content' => '',
                                'post_status' => 'inherit',
                            );
                            // Delete old attachment
                            $old_attachment = get_post_thumbnail_id( $coupon_id );
                            if ( $old_attachment ) {
                                wp_delete_attachment( $old_attachment, true );
                            }
                            
                            $attach_id = wp_insert_attachment( $attachment, $file_path, $coupon_id );
                            if ( ! is_wp_error( $attach_id ) ) {
                                require_once ABSPATH . 'wp-admin/includes/image.php';
                                $attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );
                                wp_update_attachment_metadata( $attach_id, $attach_data );
                                set_post_thumbnail( $coupon_id, $attach_id );
                            }
                        }
                    }
                }
            }
        }
        
        wp_send_json_success( array( 'message' => __( 'Coupon updated successfully!', 'love-coupons' ), 'coupon_id' => $coupon_id ) );
    }

    public function ajax_save_push_subscription() {
        // PWA installs can cache pages and end up with stale nonces.
        // Allow the request if the user is logged in, even when the nonce is stale, to avoid blocking push opt-in.
        $nonce_ok = check_ajax_referer( 'love_coupons_nonce', 'security', false );
        if ( ! $nonce_ok && ! is_user_logged_in() ) {
            wp_send_json_error( __( 'Security check failed.', 'love-coupons' ) );
        }

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( __( 'You must be logged in.', 'love-coupons' ) );
        }
        
        $subscription = isset( $_POST['subscription'] ) ? sanitize_text_field( $_POST['subscription'] ) : '';
        if ( empty( $subscription ) ) {
            wp_send_json_error( __( 'Invalid subscription data.', 'love-coupons' ) );
        }
        
        $current_user_id = get_current_user_id();
        $updated = update_user_meta( $current_user_id, '_love_coupons_push_subscription', $subscription );
        
        if ( false === $updated ) {
            wp_send_json_error( __( 'Failed to save subscription.', 'love-coupons' ) );
        }
        
        wp_send_json_success( __( 'Push notification subscription saved.', 'love-coupons' ) );
    }

    /**
     * Provide a fresh nonce for cached PWA contexts where the page nonce may be stale.
     */
    public function ajax_get_nonce() {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( __( 'You must be logged in.', 'love-coupons' ) );
        }

        wp_send_json_success( array(
            'nonce' => wp_create_nonce( 'love_coupons_nonce' ),
        ) );
    }

    public function ajax_send_feedback() {
        if ( ! check_ajax_referer( 'love_coupons_nonce', 'security', false ) ) {
            wp_send_json_error( __( 'Security check failed.', 'love-coupons' ) );
        }
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( __( 'You must be logged in to send feedback.', 'love-coupons' ) );
        }

        $feedback = isset( $_POST['feedback'] ) ? sanitize_textarea_field( wp_unslash( $_POST['feedback'] ) ) : '';
        if ( empty( $feedback ) ) {
            wp_send_json_error( __( 'Please enter your feedback.', 'love-coupons' ) );
        }

        $current_user = wp_get_current_user();
        $admin_email  = get_option( 'admin_email' );
        $subject      = sprintf( __( 'Love Coupons Feedback from %s', 'love-coupons' ), $current_user->display_name );
        $message      = sprintf(
            __( 'Feedback from: %1$s (%2$s)\n\nFeedback:\n%3$s', 'love-coupons' ),
            $current_user->display_name,
            $current_user->user_email,
            $feedback
        );

        $sent = wp_mail( $admin_email, $subject, $message );

        if ( $sent ) {
            wp_send_json_success( __( 'Thank you for your feedback!', 'love-coupons' ) );
        } else {
            wp_send_json_error( __( 'Failed to send feedback. Please try again.', 'love-coupons' ) );
        }
    }

    public function ajax_reset_redemption_count() {
        if ( ! check_ajax_referer( 'love_coupons_nonce', 'security', false ) ) {
            wp_send_json_error( __( 'Security check failed.', 'love-coupons' ) );
        }
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( __( 'You must be logged in to edit coupons.', 'love-coupons' ) );
        }

        $coupon_id = isset( $_POST['coupon_id'] ) ? absint( $_POST['coupon_id'] ) : 0;
        if ( ! $coupon_id || 'love_coupon' !== get_post_type( $coupon_id ) ) {
            wp_send_json_error( __( 'Invalid coupon.', 'love-coupons' ) );
        }

        $coupon = get_post( $coupon_id );
        if ( ! $coupon || 'publish' !== $coupon->post_status ) {
            wp_send_json_error( __( 'Coupon not found or not available.', 'love-coupons' ) );
        }

        $current_user_id = get_current_user_id();
        $created_by = get_post_meta( $coupon_id, '_love_coupon_created_by', true );
        if ( intval( $created_by ) !== $current_user_id && ! current_user_can( 'edit_others_posts' ) ) {
            wp_send_json_error( __( 'You do not have permission to edit this coupon.', 'love-coupons' ) );
        }

        // Reset the redemption count to 0
        update_post_meta( $coupon_id, '_love_coupon_redemption_count', 0 );
        
        // Also clear any legacy redeemed flag so coupon shows as available again
        delete_post_meta( $coupon_id, '_love_coupon_redeemed' );

        wp_send_json_success( __( 'Redemption count has been reset to zero.', 'love-coupons' ) );
    }
}

