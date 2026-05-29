<?php
/**
 * Walk-in order repository for Banoks POS.
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
 * Handles walk-in POS order data access.
 *
 * @since      1.0.0
 * @package    Banoks_POS
 * @subpackage Banoks_POS/includes/repositories
 */
class Banoks_Order_Repository {

    /**
     * Get the next visible order number.
     *
     * @since    1.0.0
     * @return   int
     */
    public function get_next_order_id() {
        global $wpdb;

        $last_id = $wpdb->get_var( "SELECT MAX(order_id) FROM {$wpdb->prefix}banoks_orders" );

        return $last_id ? intval( $last_id ) + 1 : 1;
    }

    /**
     * Get completed sales total for a date.
     *
     * @since    1.0.0
     * @param    string $date Date in Y-m-d format.
     * @return   float
     */
    public function get_sales_for_date( $date ) {
        global $wpdb;

        $sales = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(grand_total) FROM {$wpdb->prefix}banoks_orders WHERE date = %s AND status = 'completed'",
                $date
            )
        );

        return $sales ? floatval( $sales ) : 0;
    }

    /**
     * Count walk-in orders that still need cashier attention.
     *
     * @since    1.0.13
     * @return   int
     */
    public function count_active() {
        global $wpdb;

        return intval(
            $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}banoks_orders WHERE status IN ('pending', 'preparing')"
            )
        );
    }

    /**
     * Create a new walk-in order.
     *
     * @since    1.7.5
     * @param    array $data Order data.
     * @return   int|false Inserted order ID or false on failure.
     */
    public function create( $data ) {
        global $wpdb;

        $inserted = $wpdb->insert(
            $wpdb->prefix . 'banoks_orders',
            array(
                'created_by'       => isset( $data['created_by'] ) ? sanitize_text_field( $data['created_by'] ) : '',
                'branch_key'       => isset( $data['branch_key'] ) ? sanitize_key( $data['branch_key'] ) : 'manukan_branch',
                'entry_timestamp'  => isset( $data['entry_timestamp'] ) ? $data['entry_timestamp'] : current_time( 'mysql' ),
                'date'             => isset( $data['date'] ) ? $data['date'] : current_time( 'Y-m-d' ),
                'grand_total'      => isset( $data['grand_total'] ) ? floatval( $data['grand_total'] ) : 0,
                'payment_method'   => isset( $data['payment_method'] ) ? sanitize_key( $data['payment_method'] ) : 'cash',
                'received_account' => isset( $data['received_account'] ) ? sanitize_key( $data['received_account'] ) : 'store_cash',
                'status'           => isset( $data['status'] ) ? sanitize_key( $data['status'] ) : 'pending',
            ),
            array( '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s' )
        );

        if ( ! $inserted ) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Add items to a walk-in order.
     *
     * @since    1.7.5
     * @param    int   $order_id Order ID.
     * @param    array $items    Array of item arrays with product_id, quantity, unit_price, sub_total.
     * @return   int   Number of successfully inserted items.
     */
    public function add_items( $order_id, $items ) {
        global $wpdb;

        $inserted_count = 0;

        foreach ( $items as $item ) {
            $result = $wpdb->insert(
                $wpdb->prefix . 'banoks_order_items',
                array(
                    'order_id'           => absint( $order_id ),
                    'product_id'         => isset( $item['product_id'] ) ? absint( $item['product_id'] ) : 0,
                    'qty'                => isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 0,
                    'unit_price_at_sale' => isset( $item['unit_price'] ) ? floatval( $item['unit_price'] ) : 0,
                    'sub_total'          => isset( $item['sub_total'] ) ? floatval( $item['sub_total'] ) : 0,
                ),
                array( '%d', '%d', '%d', '%f', '%f' )
            );

            if ( false !== $result ) {
                $inserted_count++;
            }
        }

        return $inserted_count;
    }

    /**
     * Get order items by order ID.
     *
     * @since    1.7.5
     * @param    int  $order_id Order ID.
     * @return   array
     */
    public function get_items( $order_id ) {
        global $wpdb;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT product_id, qty, unit_price_at_sale, sub_total FROM {$wpdb->prefix}banoks_order_items WHERE order_id = %d",
                absint( $order_id )
            )
        );

        return is_array( $results ) ? $results : array();
    }

    /**
     * Get order items with product_id and quantity fields.
     *
     * @since    1.7.5
     * @param    int  $order_id Order ID.
     * @return   array
     */
    public function get_items_for_stock( $order_id ) {
        global $wpdb;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT product_id, qty AS quantity FROM {$wpdb->prefix}banoks_order_items WHERE order_id = %d",
                absint( $order_id )
            )
        );

        return is_array( $results ) ? $results : array();
    }

    /**
     * Update order status.
     *
     * @since    1.7.5
     * @param    int    $order_id Order ID.
     * @param    string $new_status New status.
     * @param    string $current_status Current status for optimistic locking.
     * @return   bool|int
     */
    public function update_status( $order_id, $new_status, $current_status ) {
        global $wpdb;

        return $wpdb->update(
            $wpdb->prefix . 'banoks_orders',
            array( 'status' => sanitize_key( $new_status ) ),
            array(
                'order_id' => absint( $order_id ),
                'status'   => sanitize_key( $current_status ),
            ),
            array( '%s' ),
            array( '%d', '%s' )
        );
    }

    /**
     * Get a single order by ID.
     *
     * @since    1.7.5
     * @param    int  $order_id Order ID.
     * @return   object|null
     */
    public function get( $order_id ) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}banoks_orders WHERE order_id = %d",
                absint( $order_id )
            )
        );
    }

    /**
     * Delete order and its items.
     *
     * @since    1.7.5
     * @param    int  $order_id Order ID.
     * @return   bool
     */
    public function delete( $order_id ) {
        global $wpdb;

        $order_id = absint( $order_id );
        $wpdb->delete( $wpdb->prefix . 'banoks_order_items', array( 'order_id' => $order_id ), array( '%d' ) );

        return false !== $wpdb->delete( $wpdb->prefix . 'banoks_orders', array( 'order_id' => $order_id ), array( '%d' ) );
    }

    /**
     * Get orders by status.
     *
     * @since    1.7.5
     * @param    array|string $status Status or array of statuses.
     * @param    int          $limit  Max results.
     * @return   array
     */
    public function get_by_status( $status, $limit = 100 ) {
        global $wpdb;

        if ( is_array( $status ) ) {
            $placeholders = implode( ', ', array_fill( 0, count( $status ), '%s' ) );
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}banoks_orders WHERE status IN ({$placeholders}) ORDER BY entry_timestamp DESC LIMIT %d",
                    array_merge( $status, array( absint( $limit ) ) )
                )
            );
        } else {
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}banoks_orders WHERE status = %s ORDER BY entry_timestamp DESC LIMIT %d",
                    sanitize_key( $status ),
                    absint( $limit )
                )
            );
        }

        return is_array( $results ) ? $results : array();
    }

    /**
     * Build the data needed by the POS interface.
     *
     * @since    1.0.0
     * @param    array $args Optional display arguments.
     * @return   array
     */
    public function get_pos_data( $args = array() ) {
        $active_date  = isset( $args['active_date'] ) ? sanitize_text_field( $args['active_date'] ) : current_time( 'Y-m-d' );
        $today        = current_time( 'Y-m-d' );
        $current_user = wp_get_current_user();
        $product_repo = new Banoks_Product_Repository();
        $inventory_repo  = new Banoks_Inventory_Repository();
        $products     = $product_repo->get_products();
        $product_ids  = wp_list_pluck( $products, 'product_id' );

        return array(
            'products'        => $products,
            'categories'      => $product_repo->get_categories(),
            'active_date'     => $active_date,
            'next_id'         => $this->get_next_order_id(),
            'sales'           => $this->get_sales_for_date( $today ),
            'cashier_name'    => ! empty( $current_user->display_name ) ? $current_user->display_name : $current_user->user_login,
            'recipe_statuses' => $inventory_repo->get_product_recipe_statuses( $product_ids, Banoks_POS_Repository::STOCK_LOCATION_MANUKAN ),
        );
    }

    /**
     * Get walk-in sales total for a date range and branch.
     *
     * @since    1.7.5
     * @param    string $date       Date in Y-m-d format.
     * @param    string $branch_key Branch key.
     * @return   float
     */
    public function get_sales_for_date_branch( $date, $branch_key = 'manukan_branch' ) {
        global $wpdb;

        $sales = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(grand_total)
                 FROM {$wpdb->prefix}banoks_orders
                 WHERE date = %s
                 AND status = 'completed'
                 AND (branch_key = %s OR branch_key IS NULL OR branch_key = '')
                 AND (received_account IN ('store_cash', 'gcash_balance') OR received_account IS NULL OR received_account = '')",
                $date,
                $branch_key
            )
        );

        return $sales ? floatval( $sales ) : 0;
    }

    /**
     * Validate and place a walk-in order with items in one method.
     *
     * @since    1.7.5
     * @param    array $items      Cart items (id, qty).
     * @param    array $order_data Order fields (date, payment_method, cashier_name, branch_key).
     * @return   array
     */
    public function place_order( $items, $order_data ) {
        global $wpdb;

        $cart_quantities = array();
        foreach ( $items as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $product_id = isset( $item['id'] ) ? absint( $item['id'] ) : 0;
            $quantity   = isset( $item['qty'] ) ? absint( $item['qty'] ) : 0;
            if ( ! $product_id || ! $quantity ) {
                continue;
            }
            if ( ! isset( $cart_quantities[ $product_id ] ) ) {
                $cart_quantities[ $product_id ] = 0;
            }
            $cart_quantities[ $product_id ] += $quantity;
        }

        if ( empty( $cart_quantities ) ) {
            return array( 'error' => 'No valid order items were submitted.' );
        }

        $product_repo  = new Banoks_Product_Repository();
        $product_ids   = array_keys( $cart_quantities );
        $products      = $product_repo->get_products_by_ids( $product_ids, true );

        if ( ! is_array( $products ) ) {
            return array( 'error' => 'Failed to validate order products.' );
        }

        if ( count( $products ) !== count( $product_ids ) ) {
            return array( 'error' => 'One or more products are no longer available.' );
        }

        $validated_items = array();
        $grand_total     = 0;
        $order_date      = isset( $order_data['date'] ) ? $order_data['date'] : current_time( 'Y-m-d' );
        $payment_method  = isset( $order_data['payment_method'] ) ? $order_data['payment_method'] : 'cash';
        $received_account = 'gcash' === $payment_method ? 'gcash_balance' : 'store_cash';

        foreach ( $cart_quantities as $product_id => $quantity ) {
            $product = isset( $products[ $product_id ] ) ? $products[ $product_id ] : null;
            if ( ! $product || ! intval( $product->is_active ) || ! intval( $product->is_available ) ) {
                return array( 'error' => 'One or more products are no longer available.' );
            }

            $unit_price = floatval( $product->current_price );
            if ( $unit_price < 0 ) {
                return array( 'error' => 'One or more products have an invalid price.' );
            }

            $sub_total    = $unit_price * $quantity;
            $grand_total += $sub_total;
            $validated_items[] = array(
                'product_id' => $product_id,
                'quantity'   => $quantity,
                'unit_price' => $unit_price,
                'sub_total'  => $sub_total,
            );
        }

        $inventory_repo = new Banoks_Inventory_Repository();
        $recipe_result  = $inventory_repo->validate_recipe_inventory_for_items( $validated_items, 'walk_in' );
        if ( isset( $recipe_result['error'] ) ) {
            return array( 'error' => $recipe_result['error'] );
        }

        $cashier_name = isset( $order_data['cashier_name'] ) ? $order_data['cashier_name'] : 'unknown';
        $branch_key   = isset( $order_data['branch_key'] ) ? $order_data['branch_key'] : 'manukan_branch';

        $wpdb->query( 'START TRANSACTION' );

        $order_id = $this->create(
            array(
                'created_by'       => $cashier_name,
                'branch_key'       => $branch_key,
                'entry_timestamp'  => current_time( 'mysql' ),
                'date'             => $order_date,
                'grand_total'      => $grand_total,
                'payment_method'   => $payment_method,
                'received_account' => $received_account,
                'status'           => 'pending',
            )
        );

        if ( ! $order_id ) {
            $wpdb->query( 'ROLLBACK' );
            return array( 'error' => 'Failed to save order.' );
        }

        $inserted_items = $this->add_items( $order_id, $validated_items );

        if ( count( $validated_items ) !== $inserted_items ) {
            $this->delete( $order_id );
            $wpdb->query( 'ROLLBACK' );
            return array( 'error' => 'Failed to save all order items.' );
        }

        $wpdb->query( 'COMMIT' );

        return array(
            'success'  => true,
            'order_id' => $order_id,
            'total'    => $grand_total,
        );
    }
}
