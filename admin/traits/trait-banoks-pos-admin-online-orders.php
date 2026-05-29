<?php
/**
 * Online order admin methods for Banoks POS.
 *
 * @package Banoks_POS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Banoks_POS_Admin_Online_Orders {
    public function display_online_orders_page() {
        global $wpdb;

        $this->display_admin_header();
        $this->maybe_update_products_schema();

        $repository = new Banoks_POS_Repository();
        $message    = '';
        $error      = '';

        if ( isset( $_POST['banoks_pos_update_online_order_status'] ) ) {
            check_admin_referer( 'banoks_pos_online_status_action' );

            $result = $repository->update_online_order_status(
                isset( $_POST['online_order_id'] ) ? absint( $_POST['online_order_id'] ) : 0,
                isset( $_POST['new_status'] ) ? sanitize_key( wp_unslash( $_POST['new_status'] ) ) : '',
                array(
                    'driver_name'    => isset( $_POST['driver_name'] ) ? wp_unslash( $_POST['driver_name'] ) : '',
                    'driver_contact' => isset( $_POST['driver_contact'] ) ? wp_unslash( $_POST['driver_contact'] ) : '',
                    'note'           => isset( $_POST['status_note'] ) ? wp_unslash( $_POST['status_note'] ) : '',
                )
            );

            if ( isset( $result['error'] ) ) {
                $error = $result['error'];
            } else {
                $message = 'Online order status updated successfully.';
            }
        }

        if ( isset( $_POST['banoks_pos_update_payment_proof'] ) ) {
            check_admin_referer( 'banoks_pos_payment_proof_action' );

            $result = $repository->update_payment_proof_status(
                isset( $_POST['payment_proof_id'] ) ? absint( $_POST['payment_proof_id'] ) : 0,
                isset( $_POST['payment_proof_status'] ) ? sanitize_key( wp_unslash( $_POST['payment_proof_status'] ) ) : '',
                isset( $_POST['payment_rejection_reason'] ) ? wp_unslash( $_POST['payment_rejection_reason'] ) : ''
            );

            if ( isset( $result['error'] ) ) {
                $error = $result['error'];
            } else {
                $message = 'Payment proof updated successfully.';
            }
        }

        $online_orders  = $repository->get_recent_online_orders();
        $online_related = $repository->get_online_order_related_data( $online_orders );
        $critical_inventory_alerts = $repository->get_inventory_stock_alerts( 0, Banoks_POS_Repository::STOCK_LOCATION_MANUKAN );

        include_once dirname( __DIR__ ) . '/partials/banoks-pos-online-orders-display.php';
    }
}
