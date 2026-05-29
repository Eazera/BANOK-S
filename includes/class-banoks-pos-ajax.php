<?php
/**
 * Handle AJAX requests for the POS system.
 *
 * Thin coordinator — all data logic is delegated to repositories.
 * Handles only request validation, authorization, and response formatting.
 *
 * @link       https://banoks.com
 * @since      1.0.0
 * @package    Banoks_POS
 * @subpackage Banoks_POS/includes
 * @author     Christian Fulache
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Banoks_POS_Ajax {

    /**
     * Repository.
     *
     * @var Banoks_POS_Repository
     */
    private $repository;

    /**
     * Initialize the class and register AJAX actions.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->repository = new Banoks_POS_Repository();

        add_action( 'wp_ajax_banoks_pos_place_order', array( $this, 'handle_place_order' ) );
        add_action( 'wp_ajax_banoks_pos_update_order_status', array( $this, 'handle_update_order_status' ) );
        add_action( 'wp_ajax_banoks_pos_walk_in_order_count', array( $this, 'handle_walk_in_order_count' ) );
        add_action( 'wp_ajax_banoks_pos_online_order_count', array( $this, 'handle_online_order_count' ) );
        add_action( 'wp_ajax_banoks_pos_online_order_notifications', array( $this, 'handle_online_order_notifications' ) );
        add_action( 'wp_ajax_banoks_pos_pending_request_count', array( $this, 'handle_pending_request_count' ) );
        add_action( 'wp_ajax_banoks_pos_owner_dashboard_summary', array( $this, 'handle_owner_dashboard_summary' ) );
        add_action( 'wp_ajax_banoks_pos_update_online_order_status', array( $this, 'handle_update_online_order_status' ) );
        add_action( 'wp_ajax_banoks_pos_update_payment_proof', array( $this, 'handle_update_payment_proof' ) );
    }

    /**
     * Return active walk-in order count for navigation badges.
     *
     * @since    1.0.13
     */
    public function handle_walk_in_order_count() {
        check_ajax_referer( 'banoks_pos_order_nonce', 'nonce' );

        if ( ! current_user_can( 'banoks_use_pos' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
        }

        wp_send_json_success(
            array( 'count' => $this->repository->count_active_walk_in_orders() )
        );
    }

    /**
     * Return pending owner request count for navigation badges.
     *
     * @since    1.2.4
     */
    public function handle_pending_request_count() {
        check_ajax_referer( 'banoks_pos_order_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
        }

        global $wpdb;

        wp_send_json_success(
            array(
                'count' => absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}banoks_requests WHERE request_status = 'pending'" ) ),
            )
        );
    }

    /**
     * Return today's owner dashboard sales summary.
     *
     * @since    1.2.4
     */
    public function handle_owner_dashboard_summary() {
        check_ajax_referer( 'banoks_pos_order_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
        }

        global $wpdb;

        $today      = current_time( 'Y-m-d' );
        $branch_key = Banoks_POS_Repository::STOCK_LOCATION_MANUKAN;

        $walkin_sales = $this->repository->get_sales_for_date_branch( $today, $branch_key );

        $online_sales = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(total_amount)
                 FROM {$wpdb->prefix}banoks_online_orders
                 WHERE DATE(created_at) = %s
                 AND order_status = 'completed'
                 AND (branch_key = %s OR branch_key IS NULL OR branch_key = '')
                 AND LOWER(payment_method) IN ('cod', 'pay_at_pickup', 'cash', 'gcash')",
                $today,
                $branch_key
            )
        ) ?: 0;

        $expenses = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(amount)
                 FROM {$wpdb->prefix}banoks_expenses
                 WHERE date = %s
                 AND branch_key = %s",
                $today,
                $branch_key
            )
        ) ?: 0;

        $stock_expenses = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(total_cost)
                 FROM {$wpdb->prefix}banoks_inventory_movements
                 WHERE affects_cash_balance = 1
                 AND change_amount > 0
                 AND movement_type = 'stock_in'
                 AND location_key = %s
                 AND cash_source = 'store_cash'
                 AND DATE(created_at) = %s",
                $branch_key,
                $today
            )
        ) ?: 0;

        $total_sales    = floatval( $walkin_sales ) + floatval( $online_sales );
        $total_expenses = floatval( $expenses ) + floatval( $stock_expenses );
        $final_sale     = $total_sales - $total_expenses;
        $chart_expenses = max( 0, $total_expenses );
        $chart_final    = max( 0, $final_sale );
        $chart_total    = $chart_expenses + $chart_final;
        $expense_pct    = $chart_total > 0 ? min( 100, max( 0, ( $chart_expenses / $chart_total ) * 100 ) ) : 0;
        $final_pct      = $chart_total > 0 ? max( 0, 100 - $expense_pct ) : 0;

        wp_send_json_success(
            array(
                'date'           => wp_date( 'F j, Y', strtotime( $today ) ),
                'total_sales'    => $total_sales,
                'total_expenses' => $total_expenses,
                'final_sale'     => $final_sale,
                'expense_pct'    => $expense_pct,
                'final_pct'      => $final_pct,
            )
        );
    }

    /**
     * Return online order count for POS notification polling.
     *
     * @since    1.0.9
     */
    public function handle_online_order_count() {
        check_ajax_referer( 'banoks_pos_order_nonce', 'nonce' );

        if ( ! current_user_can( 'banoks_use_pos' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
        }

        wp_send_json_success(
            array( 'count' => $this->repository->count_pending_online_orders() )
        );
    }

    /**
     * Return online order summaries for kiosk notifications.
     *
     * @since    1.0.9
     */
    public function handle_online_order_notifications() {
        check_ajax_referer( 'banoks_pos_order_nonce', 'nonce' );

        if ( ! current_user_can( 'banoks_use_pos' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
        }

        $orders = array();

        foreach ( $this->repository->get_online_order_notifications() as $order ) {
            $orders[] = array(
                'id'               => absint( $order->id ),
                'online_order_id'  => $order->online_order_id,
                'customer_name'    => $order->customer_name,
                'total_amount'     => number_format( floatval( $order->total_amount ), 2 ),
                'fulfillment_type' => 'pickup' === $order->fulfillment_type ? 'Pickup' : 'Delivery',
                'order_status'     => ucwords( str_replace( '_', ' ', $order->order_status ) ),
                'created_at'       => wp_date( 'M d, Y g:i A', strtotime( $order->created_at ) ),
            );
        }

        wp_send_json_success(
            array(
                'count'  => count( $orders ),
                'orders' => $orders,
            )
        );
    }

    /**
     * Handle online order status updates from admin cards.
     *
     * @since    1.0.9
     */
    public function handle_update_online_order_status() {
        check_ajax_referer( 'banoks_pos_order_nonce', 'nonce' );

        if ( ! current_user_can( 'banoks_use_pos' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
        }

        $result = $this->repository->update_online_order_status(
            isset( $_POST['online_order_id'] ) ? absint( $_POST['online_order_id'] ) : 0,
            isset( $_POST['new_status'] ) ? sanitize_key( wp_unslash( $_POST['new_status'] ) ) : '',
            array(
                'driver_name'    => isset( $_POST['driver_name'] ) ? sanitize_text_field( wp_unslash( $_POST['driver_name'] ) ) : '',
                'driver_contact' => isset( $_POST['driver_contact'] ) ? sanitize_text_field( wp_unslash( $_POST['driver_contact'] ) ) : '',
                'note'           => isset( $_POST['status_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['status_note'] ) ) : '',
            )
        );

        if ( isset( $result['error'] ) ) {
            wp_send_json_error( array( 'message' => $result['error'] ) );
        }

        wp_send_json_success( array( 'message' => 'Online order status updated successfully.' ) );
    }

    /**
     * Handle GCash payment proof updates.
     *
     * @since    1.0.9
     */
    public function handle_update_payment_proof() {
        check_ajax_referer( 'banoks_pos_order_nonce', 'nonce' );

        if ( ! current_user_can( 'banoks_use_pos' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
        }

        $result = $this->repository->update_payment_proof_status(
            isset( $_POST['payment_proof_id'] ) ? absint( $_POST['payment_proof_id'] ) : 0,
            isset( $_POST['payment_proof_status'] ) ? sanitize_key( wp_unslash( $_POST['payment_proof_status'] ) ) : '',
            isset( $_POST['payment_rejection_reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['payment_rejection_reason'] ) ) : ''
        );

        if ( isset( $result['error'] ) ) {
            wp_send_json_error( array( 'message' => $result['error'] ) );
        }

        wp_send_json_success( array( 'message' => 'Payment proof updated successfully.' ) );
    }

    /**
     * Handle walk-in order status change (Prepare/Complete/Cancel).
     *
     * @since    1.0.0
     */
    public function handle_update_order_status() {
        check_ajax_referer( 'banoks_pos_order_nonce', 'nonce' );

        if ( ! current_user_can( 'banoks_use_pos' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
        }

        global $wpdb;

        $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
        $status   = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';

        if ( ! $order_id || ! in_array( $status, array( 'preparing', 'completed', 'cancelled' ), true ) ) {
            wp_send_json_error( array( 'message' => 'Invalid order update.' ) );
        }

        $orders_table   = $wpdb->prefix . 'banoks_orders';
        $current_status = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT status FROM {$orders_table} WHERE order_id = %d",
                $order_id
            )
        );

        if ( null === $current_status ) {
            wp_send_json_error( array( 'message' => 'Order not found.' ) );
        }

        $allowed = array(
            'pending'   => array( 'preparing', 'cancelled' ),
            'preparing' => array( 'completed', 'cancelled' ),
        );

        if ( empty( $allowed[ $current_status ] ) || ! in_array( $status, $allowed[ $current_status ], true ) ) {
            wp_send_json_error( array( 'message' => 'Invalid status movement.' ) );
        }

        $restores_stock         = 'cancelled' === $status && 'preparing' === $current_status;
        $uses_stock_transaction = 'preparing' === $status || $restores_stock;

        if ( $uses_stock_transaction ) {
            $wpdb->query( 'START TRANSACTION' );
        }

        if ( 'preparing' === $status ) {
            $order_items  = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT product_id, qty FROM {$wpdb->prefix}banoks_order_items WHERE order_id = %d",
                    $order_id
                )
            );
            $stock_result = $this->repository->deduct_stock_for_items( $order_items, 'walk_in', 'POS-' . $order_id, 'walk_in' );

            if ( isset( $stock_result['error'] ) ) {
                $wpdb->query( 'ROLLBACK' );
                wp_send_json_error( array( 'message' => $stock_result['error'] ) );
            }
        }

        if ( $restores_stock ) {
            $stock_result = $this->repository->restore_stock_for_source( 'walk_in', 'POS-' . $order_id );

            if ( isset( $stock_result['error'] ) ) {
                $wpdb->query( 'ROLLBACK' );
                wp_send_json_error( array( 'message' => $stock_result['error'] ) );
            }
        }

        $updated = $wpdb->update(
            $orders_table,
            array( 'status' => $status ),
            array( 'order_id' => $order_id, 'status' => $current_status ),
            array( '%s' ),
            array( '%d', '%s' )
        );

        if ( false === $updated || 0 === $updated ) {
            if ( $uses_stock_transaction ) {
                $wpdb->query( 'ROLLBACK' );
            }
            wp_send_json_error( array( 'message' => 'Failed to update order status.' ) );
        }

        if ( $uses_stock_transaction ) {
            $wpdb->query( 'COMMIT' );
        }

        wp_send_json_success( array( 'message' => 'Order status updated to ' . $status ) );
    }

    /**
     * Handle placing a walk-in order.
     *
     * @since    1.0.0
     */
    public function handle_place_order() {
        check_ajax_referer( 'banoks_pos_order_nonce', 'nonce' );

        if ( ! current_user_can( 'banoks_use_pos' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized access.' ) );
        }

        $items       = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : array();
        $order_date  = ! empty( $_POST['order_date'] ) ? sanitize_text_field( wp_unslash( $_POST['order_date'] ) ) : current_time( 'Y-m-d' );
        $payment_method = isset( $_POST['payment_method'] ) ? sanitize_key( wp_unslash( $_POST['payment_method'] ) ) : 'cash';

        if ( empty( $items ) ) {
            wp_send_json_error( array( 'message' => 'Cart is empty.' ) );
        }

        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $order_date ) ) {
            wp_send_json_error( array( 'message' => 'Invalid order date.' ) );
        }

        if ( ! in_array( $payment_method, array( 'cash', 'gcash' ), true ) ) {
            wp_send_json_error( array( 'message' => 'Invalid payment method.' ) );
        }

        $current_user  = wp_get_current_user();
        $cashier_name  = ! empty( $current_user->user_login ) ? $current_user->user_login : 'unknown';

        $result = $this->repository->place_order(
            $items,
            array(
                'date'           => $order_date,
                'payment_method' => $payment_method,
                'cashier_name'   => $cashier_name,
                'branch_key'     => 'manukan_branch',
            )
        );

        if ( isset( $result['error'] ) ) {
            wp_send_json_error( array( 'message' => $result['error'] ) );
        }

        wp_send_json_success(
            array(
                'message'  => 'Order placed successfully!',
                'order_id' => $result['order_id'],
                'total'    => $result['total'],
            )
        );
    }
}