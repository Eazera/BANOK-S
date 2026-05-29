<?php
/**
 * Online order repository for Banoks POS.
 *
 * @link       https://banoks.com
 * @since      1.0.0
 * @package    Banoks_POS
 * @subpackage Banoks_POS/includes/repositories
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles online order data access, status management, and payment proofs.
 *
 * @since      1.0.0
 * @package    Banoks_POS
 * @subpackage Banoks_POS/includes/repositories
 */
class Banoks_Online_Order_Repository {

    const STATUS_PENDING          = 'pending';
    const STATUS_VERIFYING        = 'verifying';
    const STATUS_PREPARING        = 'preparing';
    const STATUS_READY_FOR_PICKUP = 'ready_for_pickup';
    const STATUS_DELIVERING       = 'delivering';
    const STATUS_COMPLETED        = 'completed';
    const STATUS_CANCELLED        = 'cancelled';
    const STATUS_REJECTED         = 'rejected';

    /**
     * Get accepted online order statuses.
     *
     * @since    1.0.7
     * @return   array
     */
    public function get_statuses() {
        return array(
            self::STATUS_PENDING,
            self::STATUS_VERIFYING,
            self::STATUS_PREPARING,
            self::STATUS_READY_FOR_PICKUP,
            self::STATUS_DELIVERING,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
            self::STATUS_REJECTED,
        );
    }

    /**
     * Generate the next public online order ID for today.
     *
     * @since    1.0.7
     * @return   string
     */
    public function generate_public_id() {
        global $wpdb;

        $date       = current_time( 'Ymd' );
        $prefix     = 'ONL-' . $date . '-';
        $like       = $wpdb->esc_like( $prefix ) . '%';
        $last_order = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT online_order_id FROM {$wpdb->prefix}banoks_online_orders WHERE online_order_id LIKE %s ORDER BY id DESC LIMIT 1",
                $like
            )
        );

        $next = 1;
        if ( $last_order && preg_match( '/-(\d+)$/', $last_order, $matches ) ) {
            $next = intval( $matches[1] ) + 1;
        }

        return $prefix . sprintf( '%04d', $next );
    }

    /**
     * Count online orders that need cashier attention.
     *
     * @since    1.0.9
     * @return   int
     */
    public function count_pending() {
        global $wpdb;

        return intval(
            $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}banoks_online_orders WHERE order_status IN ('pending', 'verifying')"
            )
        );
    }

    /**
     * Get online orders that need cashier notification.
     *
     * @since    1.0.9
     * @return   array
     */
    public function get_notifications() {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT id, online_order_id, customer_name, total_amount, fulfillment_type, order_status, created_at
             FROM {$wpdb->prefix}banoks_online_orders
             WHERE order_status IN ('pending', 'verifying')
             ORDER BY created_at DESC
             LIMIT 20"
        );
    }

    /**
     * Get an online order with its items.
     *
     * @since    1.0.8
     * @param    int $order_id Online order internal ID.
     * @return   array|null
     */
    public function get_with_items( $order_id ) {
        global $wpdb;

        $order = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}banoks_online_orders WHERE id = %d",
                absint( $order_id )
            )
        );

        if ( ! $order ) {
            return null;
        }

        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}banoks_online_order_items WHERE online_order_id = %d ORDER BY id ASC",
                absint( $order_id )
            )
        );

        return array(
            'order' => $order,
            'items' => $items,
        );
    }

    /**
     * Get online orders for a customer.
     *
     * @since    1.0.8
     * @param    int $customer_id Customer internal ID.
     * @return   array
     */
    public function get_by_customer( $customer_id ) {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}banoks_online_orders WHERE customer_id = %d ORDER BY created_at DESC LIMIT 50",
                absint( $customer_id )
            )
        );
    }

    /**
     * Get recent online orders.
     *
     * @since    1.0.7
     * @return   array
     */
    public function get_recent() {
        global $wpdb;

        return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}banoks_online_orders ORDER BY created_at DESC LIMIT 100" );
    }

    /**
     * Get related rows for many online orders (items, proofs, logs, stock warnings).
     *
     * @since    1.0.9
     * @param    array $orders Orders.
     * @return   array
     */
    public function get_related_data( $orders ) {
        global $wpdb;

        $ids = array();
        foreach ( $orders as $order ) {
            $ids[] = absint( $order->id );
        }

        if ( empty( $ids ) ) {
            return array(
                'items'          => array(),
                'proofs'         => array(),
                'logs'           => array(),
                'stock_warnings' => array(),
            );
        }

        $placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
        $items_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}banoks_online_order_items WHERE online_order_id IN ({$placeholders}) ORDER BY id ASC",
                $ids
            )
        );
        $proof_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}banoks_payment_proofs WHERE online_order_id IN ({$placeholders}) ORDER BY id DESC",
                $ids
            )
        );
        $log_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}banoks_online_order_status_logs WHERE online_order_id IN ({$placeholders}) ORDER BY created_at ASC",
                $ids
            )
        );

        $inventory_repo = new Banoks_Inventory_Repository();

        return array(
            'items'          => $this->group_rows_by_order_id( $items_rows ),
            'proofs'         => $this->group_rows_by_order_id( $proof_rows ),
            'logs'           => $this->group_rows_by_order_id( $log_rows ),
            'stock_warnings' => $this->get_stock_warnings( $orders, $items_rows, $inventory_repo ),
        );
    }

    /**
     * Group database rows by online order ID.
     *
     * @since    1.0.9
     * @param    array $rows Rows with online_order_id property.
     * @return   array
     */
    private function group_rows_by_order_id( $rows ) {
        $grouped = array();
        foreach ( $rows as $row ) {
            $order_id = intval( $row->online_order_id );
            if ( ! isset( $grouped[ $order_id ] ) ) {
                $grouped[ $order_id ] = array();
            }
            $grouped[ $order_id ][] = $row;
        }
        return $grouped;
    }

    /**
     * Build stock warnings for online orders before they move to preparing.
     *
     * @since    1.0.12
     * @param    array                      $orders         Online orders.
     * @param    array                      $items_rows     Online order item rows.
     * @param    Banoks_Inventory_Repository $inventory_repo Inventory repository.
     * @return   array
     */
    private function get_stock_warnings( $orders, $items_rows, $inventory_repo ) {
        global $wpdb;

        $warnings       = array();
        $orders_by_id   = array();
        $items_by_order = array();
        $product_ids    = array();

        foreach ( $orders as $order ) {
            $orders_by_id[ absint( $order->id ) ] = $order;
        }

        foreach ( $items_rows as $item ) {
            $order_id = absint( $item->online_order_id );
            if ( ! isset( $items_by_order[ $order_id ] ) ) {
                $items_by_order[ $order_id ] = array();
            }
            $items_by_order[ $order_id ][] = $item;
            $product_ids[] = absint( $item->product_id );
        }

        $product_ids = array_values( array_unique( array_filter( $product_ids ) ) );
        $products    = array();
        if ( ! empty( $product_ids ) ) {
            $placeholders = implode( ', ', array_fill( 0, count( $product_ids ), '%d' ) );
            $products     = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT product_id, product_name FROM {$wpdb->prefix}banoks_items WHERE product_id IN ({$placeholders})",
                    $product_ids
                ),
                OBJECT_K
            );
        }

        foreach ( $items_by_order as $order_id => $items ) {
            $order = isset( $orders_by_id[ $order_id ] ) ? $orders_by_id[ $order_id ] : null;
            if ( ! $order || ! in_array( $order->order_status, array( self::STATUS_PENDING, self::STATUS_VERIFYING ), true ) ) {
                continue;
            }

            $order_warnings = array();
            $quantities     = $inventory_repo->normalize_order_item_quantities( $items );

            foreach ( $quantities as $pid => $quantity ) {
                if ( empty( $products[ $pid ] ) ) {
                    $order_warnings[] = 'A product in this order no longer exists.';
                }
            }

            $recipe_context = 'pickup' === $order->fulfillment_type ? 'pickup' : 'delivery';
            $recipe_result  = $inventory_repo->validate_recipe_inventory_for_items( $items, $recipe_context );
            if ( isset( $recipe_result['error'] ) ) {
                $order_warnings[] = $recipe_result['error'];
            }

            if ( ! empty( $order_warnings ) ) {
                $warnings[ $order_id ] = array_values( array_unique( $order_warnings ) );
            }
        }

        return $warnings;
    }

    /**
     * Update online order status with transition validation.
     *
     * @since    1.0.9
     * @param    int    $order_id   Online order internal ID.
     * @param    string $new_status New status.
     * @param    array  $data       Extra data (driver_name, driver_contact, note).
     * @return   array
     */
    public function update_status( $order_id, $new_status, $data = array() ) {
        global $wpdb;

        $new_status = sanitize_key( $new_status );
        $order      = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}banoks_online_orders WHERE id = %d",
                absint( $order_id )
            )
        );

        if ( ! $order ) {
            return array( 'error' => 'Online order not found.' );
        }

        $fulfillment_type = ! empty( $order->fulfillment_type ) ? sanitize_key( $order->fulfillment_type ) : 'delivery';
        $allowed = array(
            self::STATUS_PENDING   => array( self::STATUS_PREPARING, self::STATUS_CANCELLED ),
            self::STATUS_VERIFYING => array( self::STATUS_PREPARING, self::STATUS_CANCELLED ),
        );

        if ( 'pickup' === $fulfillment_type ) {
            $allowed[ self::STATUS_PREPARING ] = array( self::STATUS_READY_FOR_PICKUP, self::STATUS_CANCELLED );
            $allowed[ self::STATUS_READY_FOR_PICKUP ] = array( self::STATUS_COMPLETED );
        } else {
            $allowed[ self::STATUS_PREPARING ] = array( self::STATUS_DELIVERING, self::STATUS_CANCELLED );
            $allowed[ self::STATUS_DELIVERING ] = array( self::STATUS_COMPLETED );
        }

        if ( empty( $allowed[ $order->order_status ] ) || ! in_array( $new_status, $allowed[ $order->order_status ], true ) ) {
            return array( 'error' => 'Invalid status movement.' );
        }

        if ( self::STATUS_CANCELLED === $new_status ) {
            $reason = isset( $data['note'] ) ? sanitize_textarea_field( $data['note'] ) : '';
            if ( '' === $reason ) {
                return array( 'error' => 'Please enter a cancellation reason.' );
            }
        }

        if ( 'gcash' === $order->payment_method && self::STATUS_PREPARING === $new_status && 'paid' !== $order->payment_status ) {
            return array( 'error' => 'Please verify the GCash payment proof before preparing this order.' );
        }

        $restores_stock = self::STATUS_CANCELLED === $new_status && self::STATUS_PREPARING === $order->order_status;
        $uses_stock_transaction = self::STATUS_PREPARING === $new_status || $restores_stock;

        if ( $uses_stock_transaction ) {
            $wpdb->query( 'START TRANSACTION' );
        }

        $inventory_repo = new Banoks_Inventory_Repository();

        if ( self::STATUS_PREPARING === $new_status ) {
            $order_items = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT product_id, quantity FROM {$wpdb->prefix}banoks_online_order_items WHERE online_order_id = %d",
                    absint( $order_id )
                )
            );
            $recipe_context = 'pickup' === $fulfillment_type ? 'pickup' : 'delivery';
            $stock_source   = 'pickup' === $fulfillment_type ? 'online_pickup' : 'online_delivery';
            $stock_result = $inventory_repo->deduct_stock_for_items( $order_items, $stock_source, $order->online_order_id, $recipe_context );

            if ( isset( $stock_result['error'] ) ) {
                $wpdb->query( 'ROLLBACK' );
                return $stock_result;
            }
        }

        if ( $restores_stock ) {
            $stock_source = 'pickup' === $fulfillment_type ? 'online_pickup' : 'online_delivery';
            $stock_result = $inventory_repo->restore_stock_for_source( $stock_source, $order->online_order_id );

            if ( isset( $stock_result['error'] ) ) {
                $wpdb->query( 'ROLLBACK' );
                return $stock_result;
            }
        }

        $update = array(
            'order_status' => $new_status,
            'updated_at'   => current_time( 'mysql' ),
        );

        if ( self::STATUS_DELIVERING === $new_status ) {
            $driver_name    = isset( $data['driver_name'] ) ? sanitize_text_field( $data['driver_name'] ) : '';
            $driver_contact = isset( $data['driver_contact'] ) ? sanitize_text_field( $data['driver_contact'] ) : '';

            if ( '' === $driver_name || '' === $driver_contact ) {
                return array( 'error' => 'Driver name and contact are required before delivering.' );
            }

            $update['driver_name']    = $driver_name;
            $update['driver_contact'] = $driver_contact;
        }

        if ( self::STATUS_COMPLETED === $new_status ) {
            $update['completed_at'] = current_time( 'mysql' );
        }

        if ( self::STATUS_CANCELLED === $new_status ) {
            $update['cancelled_at'] = current_time( 'mysql' );
        }

        $updated = $wpdb->update(
            $wpdb->prefix . 'banoks_online_orders',
            $update,
            array( 'id' => absint( $order_id ), 'order_status' => $order->order_status )
        );

        if ( false === $updated || 0 === $updated ) {
            if ( $uses_stock_transaction ) {
                $wpdb->query( 'ROLLBACK' );
            }
            return array( 'error' => 'Could not update online order status.' );
        }

        $this->create_status_log(
            $order_id,
            $order->order_status,
            $new_status,
            isset( $data['note'] ) ? sanitize_textarea_field( $data['note'] ) : ''
        );

        if ( $uses_stock_transaction ) {
            $wpdb->query( 'COMMIT' );
        }

        return array( 'success' => true );
    }

    /**
     * Create a status log row.
     *
     * @since    1.0.7
     * @param    int    $online_order_id Online order internal ID.
     * @param    string $old_status      Old status.
     * @param    string $new_status      New status.
     * @param    string $note            Note.
     * @return   bool
     */
    public function create_status_log( $online_order_id, $old_status, $new_status, $note = '' ) {
        global $wpdb;

        return false !== $wpdb->insert(
            $wpdb->prefix . 'banoks_online_order_status_logs',
            array(
                'online_order_id' => absint( $online_order_id ),
                'old_status'      => sanitize_key( $old_status ),
                'new_status'      => sanitize_key( $new_status ),
                'updated_by'      => get_current_user_id(),
                'note'            => sanitize_textarea_field( $note ),
                'created_at'      => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%d', '%s', '%s' )
        );
    }

    /**
     * Update GCash payment proof status.
     *
     * @since    1.0.9
     * @param    int    $proof_id Proof ID.
     * @param    string $status   New payment proof status (verified|rejected).
     * @param    string $reason   Rejection reason.
     * @return   array
     */
    public function update_payment_proof_status( $proof_id, $status, $reason = '' ) {
        global $wpdb;

        $status = sanitize_key( $status );
        if ( ! in_array( $status, array( 'verified', 'rejected' ), true ) ) {
            return array( 'error' => 'Invalid payment proof status.' );
        }

        $proof = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}banoks_payment_proofs WHERE id = %d",
                absint( $proof_id )
            )
        );

        if ( ! $proof ) {
            return array( 'error' => 'Payment proof not found.' );
        }

        $reason = sanitize_textarea_field( $reason );
        if ( 'rejected' === $status && '' === $reason ) {
            return array( 'error' => 'Please enter a rejection reason.' );
        }

        $payment_status = 'verified' === $status ? 'paid' : 'rejected';
        $order = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}banoks_online_orders WHERE id = %d",
                absint( $proof->online_order_id )
            )
        );

        if ( ! $order ) {
            return array( 'error' => 'Online order not found.' );
        }

        if ( 'pending' !== $proof->status || 'pending_verification' !== $order->payment_status || ! in_array( $order->order_status, array( self::STATUS_PENDING, self::STATUS_VERIFYING ), true ) ) {
            return array( 'error' => 'This payment proof has already been decided or the order has already moved forward.' );
        }

        $wpdb->update(
            $wpdb->prefix . 'banoks_payment_proofs',
            array(
                'status'      => $status,
                'verified_by' => get_current_user_id(),
                'verified_at' => current_time( 'mysql' ),
            ),
            array( 'id' => absint( $proof_id ) )
        );

        $order_update = array(
            'payment_status' => $payment_status,
            'updated_at'     => current_time( 'mysql' ),
        );

        if ( 'rejected' === $status ) {
            $order_update['order_status'] = self::STATUS_REJECTED;
            $order_update['cancelled_at'] = current_time( 'mysql' );
        }

        $wpdb->update(
            $wpdb->prefix . 'banoks_online_orders',
            $order_update,
            array( 'id' => absint( $proof->online_order_id ) )
        );

        if ( 'rejected' === $status ) {
            $this->create_status_log(
                $proof->online_order_id,
                $order->order_status,
                self::STATUS_REJECTED,
                'GCash payment rejected: ' . $reason
            );
        }

        return array( 'success' => true );
    }

    /**
     * Create an online order and related rows.
     *
     * @since    1.0.7
     * @param    array $data Order data.
     * @return   array
     */
    public function create( $data ) {
        global $wpdb;

        $customer_repo = new Banoks_Customer_Repository();
        $customer = ! empty( $data['customer_id'] ) ? $customer_repo->get( absint( $data['customer_id'] ) ) : null;

        if ( ! $customer && ! empty( $data['new_customer'] ) ) {
            $customer = $customer_repo->create( $data['new_customer'] );
            if ( is_array( $customer ) && isset( $customer['error'] ) ) {
                return $customer;
            }
        }

        if ( empty( $customer ) ) {
            return array( 'error' => 'Please select or create a customer.' );
        }

        $fulfillment_type = isset( $data['fulfillment_type'] ) ? sanitize_key( $data['fulfillment_type'] ) : 'delivery';
        if ( ! in_array( $fulfillment_type, array( 'delivery', 'pickup' ), true ) ) {
            return array( 'error' => 'Invalid fulfillment type.' );
        }

        $delivery_area      = null;
        $delivery_area_id   = 0;
        $delivery_area_name = '';
        $delivery_address   = '';
        $delivery_fee       = 0;

        if ( 'delivery' === $fulfillment_type ) {
            $delivery_area_id = isset( $data['delivery_area_id'] ) ? absint( $data['delivery_area_id'] ) : 0;
            $delivery_area    = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}banoks_delivery_areas WHERE id = %d",
                    $delivery_area_id
                )
            );

            if ( ! $delivery_area || ! intval( $delivery_area->is_deliverable ) ) {
                return array( 'error' => 'Please select a deliverable area.' );
            }

            $delivery_area_id   = intval( $delivery_area->id );
            $delivery_area_name = $delivery_area->area_name;
            $delivery_address   = isset( $data['delivery_address'] ) ? sanitize_textarea_field( $data['delivery_address'] ) : $customer->address;
            $delivery_fee       = floatval( $delivery_area->delivery_fee );
        }

        $cart_quantities = array();
        $items           = isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : array();

        foreach ( $items as $product_id => $quantity ) {
            $product_id = absint( $product_id );
            $quantity   = absint( $quantity );
            if ( $product_id && $quantity ) {
                $cart_quantities[ $product_id ] = $quantity;
            }
        }

        if ( empty( $cart_quantities ) ) {
            return array( 'error' => 'Please add at least one product.' );
        }

        $product_ids  = array_keys( $cart_quantities );
        $placeholders = implode( ', ', array_fill( 0, count( $product_ids ), '%d' ) );
        $products     = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT product_id, product_name, current_price, COALESCE(is_available, 1) AS is_available, COALESCE(is_active, 1) AS is_active
                 FROM {$wpdb->prefix}banoks_items
                 WHERE product_id IN ({$placeholders})",
                $product_ids
            ),
            OBJECT_K
        );

        if ( count( $products ) !== count( $product_ids ) ) {
            return array( 'error' => 'One or more products could not be found.' );
        }

        $validated_items = array();
        $subtotal        = 0;

        foreach ( $cart_quantities as $product_id => $quantity ) {
            $product = $products[ $product_id ];
            if ( ! intval( $product->is_active ) || ! intval( $product->is_available ) ) {
                return array( 'error' => $product->product_name . ' is currently unavailable.' );
            }

            $price      = floatval( $product->current_price );
            $line_total = $price * $quantity;
            $subtotal  += $line_total;

            $validated_items[] = array(
                'product_id'   => $product_id,
                'product_name' => $product->product_name,
                'quantity'     => $quantity,
                'price'        => $price,
                'subtotal'     => $line_total,
            );
        }

        $inventory_repo = new Banoks_Inventory_Repository();
        $recipe_context = 'pickup' === $fulfillment_type ? 'pickup' : 'delivery';
        $recipe_result  = $inventory_repo->validate_recipe_inventory_for_items( $validated_items, $recipe_context );

        if ( isset( $recipe_result['error'] ) ) {
            return $recipe_result;
        }

        $payment_method          = isset( $data['payment_method'] ) ? sanitize_key( $data['payment_method'] ) : 'cod';
        $allowed_payment_methods = 'pickup' === $fulfillment_type ? array( 'pay_at_pickup', 'gcash' ) : array( 'cod', 'gcash' );
        if ( ! in_array( $payment_method, $allowed_payment_methods, true ) ) {
            return array( 'error' => 'Invalid payment method.' );
        }

        $payment_gateway = isset( $data['payment_gateway'] ) ? sanitize_key( $data['payment_gateway'] ) : '';
        if ( 'gcash' !== $payment_method || 'paymongo' !== $payment_gateway ) {
            $payment_gateway = '';
        }

        $payment_status = 'gcash' === $payment_method ? ( 'paymongo' === $payment_gateway ? 'pending_gateway' : 'pending_verification' ) : 'unpaid';
        $order_status   = 'gcash' === $payment_method ? self::STATUS_VERIFYING : self::STATUS_PENDING;
        $total_amount   = $subtotal + $delivery_fee;

        if ( ! empty( $data['validate_only'] ) ) {
            return array( 'success' => true );
        }

        if ( 'gcash' === $payment_method && '' === $payment_gateway && ( empty( $data['payment_screenshot_url'] ) || 0 !== strpos( (string) $data['payment_screenshot_url'], 'banoks-private://' ) ) ) {
            return array( 'error' => 'Please upload your GCash payment proof before placing the order.' );
        }

        $wpdb->query( 'START TRANSACTION' );

        $inserted        = false;
        $online_order_id = 0;
        $public_order_id = '';
        for ( $attempt = 0; $attempt < 3; $attempt++ ) {
            $public_order_id = $this->generate_public_id();
            if ( $attempt > 0 ) {
                $public_order_id .= '-' . strtoupper( wp_generate_password( 4, false, false ) );
            }

            $inserted = $wpdb->insert(
                $wpdb->prefix . 'banoks_online_orders',
                array(
                    'online_order_id'              => $public_order_id,
                    'branch_key'                   => 'manukan_branch',
                    'customer_id'                  => intval( $customer->id ),
                    'customer_public_id'           => $customer->customer_id,
                    'customer_name'                => $customer->full_name,
                    'customer_phone'               => $customer->phone,
                    'delivery_address'             => $delivery_address,
                    'delivery_area_id'             => $delivery_area_id,
                    'delivery_area_name'           => $delivery_area_name,
                    'fulfillment_type'             => $fulfillment_type,
                    'payment_method'               => $payment_method,
                    'payment_status'               => $payment_status,
                    'payment_gateway'              => $payment_gateway,
                    'gateway_checkout_status'      => 'paymongo' === $payment_gateway ? 'awaiting_payment_method' : '',
                    'order_status'                 => $order_status,
                    'subtotal'                     => $subtotal,
                    'delivery_fee'                 => $delivery_fee,
                    'total_amount'                 => $total_amount,
                    'notes'                        => isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : '',
                    'created_at'                   => current_time( 'mysql' ),
                    'updated_at'                   => current_time( 'mysql' ),
                ),
                array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%s', '%s', '%s' )
            );

            if ( false !== $inserted ) {
                $online_order_id = $wpdb->insert_id;
                break;
            }

            if ( false === stripos( (string) $wpdb->last_error, 'duplicate' ) && false === stripos( (string) $wpdb->last_error, '1062' ) ) {
                break;
            }
        }

        if ( false === $inserted || ! $online_order_id ) {
            $wpdb->query( 'ROLLBACK' );
            return array( 'error' => 'Could not create online order.' );
        }

        foreach ( $validated_items as $item ) {
            $item_inserted = $wpdb->insert(
                $wpdb->prefix . 'banoks_online_order_items',
                array(
                    'online_order_id' => $online_order_id,
                    'product_id'      => $item['product_id'],
                    'product_name'    => $item['product_name'],
                    'quantity'        => $item['quantity'],
                    'price'           => $item['price'],
                    'subtotal'        => $item['subtotal'],
                ),
                array( '%d', '%d', '%s', '%d', '%f', '%f' )
            );

            if ( false === $item_inserted ) {
                $wpdb->query( 'ROLLBACK' );
                return array( 'error' => 'Could not save all online order items.' );
            }
        }

        if ( ! $this->create_status_log( $online_order_id, '', $order_status, 'Online order created.' ) ) {
            $wpdb->query( 'ROLLBACK' );
            return array( 'error' => 'Could not create online order log.' );
        }

        if ( 'gcash' === $payment_method && '' === $payment_gateway ) {
            $proof_inserted = $wpdb->insert(
                $wpdb->prefix . 'banoks_payment_proofs',
                array(
                    'online_order_id'  => $online_order_id,
                    'reference_number' => '',
                    'screenshot_url'   => isset( $data['payment_screenshot_url'] ) ? esc_url_raw( $data['payment_screenshot_url'] ) : '',
                    'attachment_id'    => isset( $data['payment_attachment_id'] ) ? absint( $data['payment_attachment_id'] ) : 0,
                    'status'           => 'pending',
                    'created_at'       => current_time( 'mysql' ),
                ),
                array( '%d', '%s', '%s', '%d', '%s', '%s' )
            );

            if ( false === $proof_inserted ) {
                $wpdb->query( 'ROLLBACK' );
                return array( 'error' => 'Could not save GCash payment proof.' );
            }
        }

        $wpdb->query( 'COMMIT' );

        return array(
            'success'         => true,
            'online_order_id' => $online_order_id,
            'public_order_id' => $public_order_id,
            'total_amount'    => $total_amount,
        );
    }

    /**
     * Update gateway-related fields for an online order.
     *
     * @since    1.7.5
     * @param    int   $order_id Order ID.
     * @param    array $data     Field data.
     * @return   bool
     */
    public function update_gateway_data( $order_id, $data ) {
        global $wpdb;

        $allowed = array(
            'payment_gateway'            => '%s',
            'gateway_checkout_status'    => '%s',
            'paymongo_payment_intent_id' => '%s',
            'paymongo_payment_method_id' => '%s',
            'paymongo_payment_id'        => '%s',
            'paymongo_webhook_event_id'  => '%s',
            'payment_failure_reason'     => '%s',
            'payment_status'             => '%s',
            'order_status'               => '%s',
        );

        $update = array();
        $format = array();

        foreach ( $allowed as $key => $placeholder ) {
            if ( array_key_exists( $key, $data ) ) {
                $update[ $key ] = in_array( $key, array( 'payment_failure_reason' ), true )
                    ? sanitize_textarea_field( $data[ $key ] )
                    : sanitize_text_field( $data[ $key ] );
                $format[] = $placeholder;
            }
        }

        if ( empty( $update ) ) {
            return false;
        }

        $update['updated_at'] = current_time( 'mysql' );
        $format[] = '%s';

        return false !== $wpdb->update(
            $wpdb->prefix . 'banoks_online_orders',
            $update,
            array( 'id' => absint( $order_id ) ),
            $format,
            array( '%d' )
        );
    }

    /**
     * Get an online order by PayMongo payment intent ID.
     *
     * @since    1.7.5
     * @param    string $payment_intent_id PayMongo payment intent ID.
     * @return   object|null
     */
    public function get_by_paymongo_intent( $payment_intent_id ) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}banoks_online_orders WHERE paymongo_payment_intent_id = %s LIMIT 1",
                sanitize_text_field( $payment_intent_id )
            )
        );
    }

    /**
     * Mark a PayMongo order as paid.
     *
     * @since    1.7.5
     * @param    object $order             Order row.
     * @param    string $payment_id        Payment ID.
     * @param    string $payment_method_id Payment method ID.
     * @param    string $event_id          Webhook event ID.
     * @return   bool
     */
    public function mark_paymongo_paid( $order, $payment_id, $payment_method_id, $event_id ) {
        if ( ! $order || 'paymongo' !== $order->payment_gateway ) {
            return false;
        }

        if ( 'paid' === $order->payment_status ) {
            return true;
        }

        $updated = $this->update_gateway_data(
            $order->id,
            array(
                'payment_status'             => 'paid',
                'order_status'               => self::STATUS_PENDING,
                'gateway_checkout_status'    => 'paid',
                'paymongo_payment_id'        => $payment_id,
                'paymongo_payment_method_id' => $payment_method_id,
                'paymongo_webhook_event_id'  => $event_id,
                'payment_failure_reason'     => '',
            )
        );

        if ( $updated ) {
            $this->create_status_log( $order->id, $order->order_status, self::STATUS_PENDING, 'PayMongo GCash payment confirmed.' );
            $customer_repo = new Banoks_Customer_Repository();
            $customer_repo->remember_payment_profile( $order->customer_id, 'paymongo', 'gcash', 'GCash via PayMongo', '', $payment_method_id );
        }

        return $updated;
    }

    /**
     * Mark a PayMongo order as failed.
     *
     * @since    1.7.5
     * @param    object $order   Order row.
     * @param    string $reason  Failure reason.
     * @param    string $event_id Webhook event ID.
     * @return   bool
     */
    public function mark_paymongo_failed( $order, $reason, $event_id ) {
        if ( ! $order || 'paymongo' !== $order->payment_gateway ) {
            return false;
        }

        return $this->update_gateway_data(
            $order->id,
            array(
                'payment_status'          => 'failed',
                'gateway_checkout_status' => 'failed',
                'paymongo_webhook_event_id' => $event_id,
                'payment_failure_reason'  => $reason,
            )
        );
    }
}