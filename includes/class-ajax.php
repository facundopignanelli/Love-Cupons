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
        $redeemed = get_post_meta( $coupon_id, '_love_coupon_redeemed', true );
        if ( $redeemed ) {
            wp_send_json_error( __( 'This coupon has already been redeemed.', 'love-coupons' ) );
        }
        $start_date = get_post_meta( $coupon_id, '_love_coupon_start_date', true );
        if ( $start_date && strtotime( $start_date ) > time() ) {
            wp_send_json_error( __( 'This coupon is not yet available.', 'love-coupons' ) );
        }
        $expiry_date = get_post_meta( $coupon_id, '_love_coupon_expiry_date', true );
        if ( $expiry_date && strtotime( $expiry_date ) < time() ) {
            wp_send_json_error( __( 'This coupon has expired.', 'love-coupons' ) );
        }
        $usage_limit = get_post_meta( $coupon_id, '_love_coupon_usage_limit', true );
        $redemption_count = get_post_meta( $coupon_id, '_love_coupon_redemption_count', true );
        if ( $usage_limit && $redemption_count >= $usage_limit ) {
            wp_send_json_error( __( 'This coupon has reached its usage limit.', 'love-coupons' ) );
        }
        $redemption_data = array(
            'user_id' => $current_user->ID,
            'user_email' => $current_user->user_email,
            'redemption_date' => current_time( 'mysql' ),
            'ip_address' => Love_Coupons_Core::get_user_ip(),
        );
        update_post_meta( $coupon_id, '_love_coupon_redeemed', true );
        update_post_meta( $coupon_id, '_love_coupon_redemption_date', current_time( 'mysql' ) );
        update_post_meta( $coupon_id, '_love_coupon_redemption_count', intval( $redemption_count ) + 1 );
        update_post_meta( $coupon_id, '_love_coupon_redemption_data', $redemption_data );
        Love_Coupons_Core::send_redemption_email( $coupon_id, $current_user );
        Love_Coupons_Core::log_redemption( $coupon_id, $current_user );
        wp_send_json_success( __( 'Coupon redeemed successfully!', 'love-coupons' ) );
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
        $restrictions = get_option( 'love_coupons_posting_restrictions', array() );
        $assigned_to  = array();
        if ( ! empty( $restrictions[ $current_user_id ] ) && ! in_array( 'all', $restrictions[ $current_user_id ], true ) ) {
            $assigned_to = array_filter( array_map( 'absint', (array) $restrictions[ $current_user_id ] ), function( $uid ) use ( $current_user_id ) {
                return $uid > 0 && $uid !== $current_user_id && get_user_by( 'id', $uid );
            } );
        }

        update_post_meta( $coupon_id, '_love_coupon_created_by', $current_user_id );
        if ( ! empty( $assigned_to ) ) {
            update_post_meta( $coupon_id, '_love_coupon_assigned_to', array_values( array_unique( $assigned_to ) ) );
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
        $allow_all       = isset( $_POST['allow_all'] ) && 'true' === $_POST['allow_all'];
        $recipients      = isset( $_POST['recipients'] ) ? (array) $_POST['recipients'] : array();

        if ( ! $allow_all && empty( $recipients ) ) {
            wp_send_json_error( __( 'Please choose at least one user or allow all.', 'love-coupons' ) );
        }

        $restrictions = get_option( 'love_coupons_posting_restrictions', array() );
        $restrictions[ $current_user_id ] = array();

        if ( $allow_all ) {
            $restrictions[ $current_user_id ][] = 'all';
        } else {
            foreach ( $recipients as $recipient_id ) {
                $recipient_id = absint( $recipient_id );
                if ( $recipient_id > 0 && $recipient_id !== $current_user_id && get_user_by( 'id', $recipient_id ) ) {
                    $restrictions[ $current_user_id ][] = $recipient_id;
                }
            }
            $restrictions[ $current_user_id ] = array_values( array_unique( $restrictions[ $current_user_id ] ) );
        }

        update_option( 'love_coupons_posting_restrictions', $restrictions );
        wp_send_json_success( __( 'Preferences saved.', 'love-coupons' ) );
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
}
