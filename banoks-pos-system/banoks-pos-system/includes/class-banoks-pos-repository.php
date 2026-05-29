<?php
/**
 * Shared data access for Banoks POS.
 *
 * This is a backward-compatible facade that delegates to specialized
 * repository classes. All original public method signatures are preserved.
 *
 * @link       https://banoks.com
 * @since      1.0.0
 * @package    Banoks_POS
 * @subpackage Banoks_POS/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Facade for Banoks POS data access.
 *
 * Delegates to specialized repository classes:
 * - Banoks_Product_Repository
 * - Banoks_Order_Repository
 * - Banoks_Customer_Repository
 * - Banoks_Inventory_Repository
 * - Banoks_Online_Order_Repository
 * - Banoks_Delivery_Area_Repository
 *
 * @since      1.0.0
 * @package    Banoks_POS
 * @subpackage Banoks_POS/includes
 */
class Banoks_POS_Repository {

    // Forward compatibility: keep original constants.
    const STOCK_LOCATION_PRODUCTION = 'production';
    const STOCK_LOCATION_MANUKAN    = 'manukan_branch';

    const ONLINE_STATUS_PENDING    = 'pending';
    const ONLINE_STATUS_VERIFYING  = 'verifying';
    const ONLINE_STATUS_PREPARING  = 'preparing';
    const ONLINE_STATUS_READY_FOR_PICKUP = 'ready_for_pickup';
    const ONLINE_STATUS_DELIVERING = 'delivering';
    const ONLINE_STATUS_COMPLETED  = 'completed';
    const ONLINE_STATUS_CANCELLED  = 'cancelled';
    const ONLINE_STATUS_REJECTED   = 'rejected';

    /**
     * Product repository.
     *
     * @var Banoks_Product_Repository
     */
    protected $products;

    /**
     * Order repository.
     *
     * @var Banoks_Order_Repository
     */
    protected $orders;

    /**
     * Customer repository.
     *
     * @var Banoks_Customer_Repository
     */
    protected $customers;

    /**
     * Inventory repository.
     *
     * @var Banoks_Inventory_Repository
     */
    protected $inventory;

    /**
     * Online order repository.
     *
     * @var Banoks_Online_Order_Repository
     */
    protected $online_orders;

    /**
     * Delivery area repository.
     *
     * @var Banoks_Delivery_Area_Repository
     */
    protected $delivery_areas;

    /**
     * Initialize the facade with all specialized repositories.
     *
     * @since    1.7.5
     */
    public function __construct() {
        $this->products      = new Banoks_Product_Repository();
        $this->orders        = new Banoks_Order_Repository();
        $this->customers     = new Banoks_Customer_Repository();
        $this->inventory     = new Banoks_Inventory_Repository();
        $this->online_orders = new Banoks_Online_Order_Repository();
        $this->delivery_areas = new Banoks_Delivery_Area_Repository();
    }

    // =========================================================================
    // PRODUCTS
    // =========================================================================

    /**
     * Get products for the POS grid.
     *
     * @since    1.0.0
     * @return   array
     */
    public function get_products() {
        return $this->products->get_products();
    }

    /**
     * Get unique product categories.
     *
     * @since    1.0.0
     * @return   array
     */
    public function get_categories() {
        return $this->products->get_categories();
    }

    /**
     * Get product availability for online ordering.
     *
     * @since    1.7.5
     * @param    array  $product_ids  Product IDs.
     * @param    string $location_key Stock location key.
     * @return   array
     */
    public function get_online_product_availability( $product_ids, $location_key = self::STOCK_LOCATION_MANUKAN ) {
        return $this->products->get_availability( $product_ids, $location_key );
    }

    // =========================================================================
    // INVENTORY / RECIPES / STOCK
    // =========================================================================

    /**
     * Deduct recipe inventory for a set of order items.
     *
     * @since    1.0.10
     * @param    array  $items          Items with product_id and quantity.
     * @param    string $source         Source type.
     * @param    string $source_id      Source/order ID.
     * @param    string $recipe_context Recipe context override.
     * @return   array
     */
    public function deduct_stock_for_items( $items, $source, $source_id = '', $recipe_context = '' ) {
        return $this->inventory->deduct_stock_for_items( $items, $source, $source_id, $recipe_context );
    }

    /**
     * Restore ingredient stock that was previously deducted.
     *
     * @since    1.0.12
     * @param    string $source    Source type.
     * @param    string $source_id Source/order ID.
     * @return   array
     */
    public function restore_stock_for_source( $source, $source_id ) {
        return $this->inventory->restore_stock_for_source( $source, $source_id );
    }

    /**
     * Validate recipe inventory availability for order items.
     *
     * @since    1.0.11
     * @param    array  $items          Order items.
     * @param    string $recipe_context Recipe context.
     * @param    string $location_key   Stock location key.
     * @return   array
     */
    public function validate_recipe_inventory_for_items( $items, $recipe_context = 'all', $location_key = self::STOCK_LOCATION_MANUKAN ) {
        return $this->inventory->validate_recipe_inventory_for_items( $items, $recipe_context, $location_key );
    }

    /**
     * Get active inventory items that need attention.
     *
     * @since    1.0.12
     * @param    int    $limit         Maximum alerts. Use 0 for no limit.
     * @param    string $location_key  Stock location key.
     * @return   array
     */
    public function get_inventory_stock_alerts( $limit = 0, $location_key = self::STOCK_LOCATION_MANUKAN ) {
        return $this->inventory->get_stock_alerts( $limit, $location_key );
    }

    /**
     * Get recipe coverage and one-serving readiness for products.
     *
     * @since    1.0.12
     * @param    array  $product_ids  Product IDs.
     * @param    string $location_key Stock location key.
     * @return   array
     */
    public function get_product_recipe_statuses( $product_ids, $location_key = self::STOCK_LOCATION_MANUKAN ) {
        return $this->inventory->get_product_recipe_statuses( $product_ids, $location_key );
    }

    /**
     * Record an inventory movement.
     *
     * @since    1.0.11
     * @param    int    $inventory_item_id  Inventory item ID.
     * @param    string $location_key       Location key.
     * @param    float  $old_stock          Old stock.
     * @param    float  $new_stock          New stock.
     * @param    string $movement_type      Movement type.
     * @param    string $source             Source.
     * @param    string $source_id          Source ID.
     * @param    string $note               Note.
     * @param    float  $unit_cost          Unit cost.
     * @param    int    $affects_cash_balance Whether this affects cash balance.
     * @param    string $cash_source        Cash source.
     * @return   bool
     */
    public function create_inventory_movement( $inventory_item_id, $location_key, $old_stock, $new_stock, $movement_type, $source = 'manual', $source_id = '', $note = '', $unit_cost = 0, $affects_cash_balance = 0, $cash_source = 'store_cash' ) {
        return $this->inventory->create_movement( $inventory_item_id, $location_key, $old_stock, $new_stock, $movement_type, $source, $source_id, $note, $unit_cost, $affects_cash_balance, $cash_source );
    }

    /**
     * Sanitize stock location key.
     *
     * @since    1.0.11
     * @param    string $location_key Location key.
     * @return   string
     */
    public function sanitize_stock_location_key( $location_key ) {
        return $this->inventory->sanitize_stock_location_key( $location_key );
    }

    // =========================================================================
    // WALK-IN ORDERS
    // =========================================================================

    /**
     * Get the next visible order number.
     *
     * @since    1.0.0
     * @return   int
     */
    public function get_next_order_id() {
        return $this->orders->get_next_order_id();
    }

    /**
     * Get completed sales total for a date.
     *
     * @since    1.0.0
     * @param    string $date Date in Y-m-d format.
     * @return   float
     */
    public function get_sales_for_date( $date ) {
        return $this->orders->get_sales_for_date( $date );
    }

    /**
     * Build the data needed by the POS interface.
     *
     * @since    1.0.0
     * @param    array $args Optional display arguments.
     * @return   array
     */
    public function get_pos_data( $args = array() ) {
        return $this->orders->get_pos_data( $args );
    }

    /**
     * Count walk-in orders that need cashier attention.
     *
     * @since    1.0.13
     * @return   int
     */
    public function count_active_walk_in_orders() {
        return $this->orders->count_active();
    }

    // =========================================================================
    // CUSTOMERS / ADDRESSES
    // =========================================================================

    /**
     * Generate the next public customer ID.
     *
     * @since    1.0.7
     * @return   string
     */
    public function generate_customer_public_id() {
        return $this->customers->generate_public_id();
    }

    /**
     * Get a customer by internal ID.
     *
     * @since    1.0.7
     * @param    int $customer_id Internal customer ID.
     * @return   object|null
     */
    public function get_customer( $customer_id ) {
        return $this->customers->get( $customer_id );
    }

    /**
     * Get a customer by email, phone, or username.
     *
     * @since    1.0.8
     * @param    string $identifier Email, phone, or username.
     * @return   object|null
     */
    public function get_customer_by_identifier( $identifier ) {
        return $this->customers->get_by_identifier( $identifier );
    }

    /**
     * Create a customer record.
     *
     * @since    1.0.7
     * @param    array $data Customer data.
     * @return   array|object
     */
    public function create_customer( $data ) {
        return $this->customers->create( $data );
    }

    /**
     * Get saved addresses for a customer.
     *
     * @since    1.0.7
     * @param    int $customer_id Customer internal ID.
     * @return   array
     */
    public function get_customer_addresses( $customer_id ) {
        return $this->customers->get_addresses( $customer_id );
    }

    /**
     * Ensure an existing customer's original address is available as saved.
     *
     * @since    1.0.7
     * @param    object $customer Customer row.
     * @return   void
     */
    public function ensure_customer_default_address( $customer ) {
        $this->customers->ensure_default_address( $customer );
    }

    /**
     * Create a saved customer address.
     *
     * @since    1.0.7
     * @param    int   $customer_id Customer internal ID.
     * @param    array $data        Address data.
     * @return   object|array
     */
    public function create_customer_address( $customer_id, $data ) {
        return $this->customers->create_address( $customer_id, $data );
    }

    /**
     * Set a saved customer address as default.
     *
     * @since    1.0.7
     * @param    int $customer_id Customer internal ID.
     * @param    int $address_id  Address ID.
     * @return   bool
     */
    public function set_default_customer_address( $customer_id, $address_id ) {
        return $this->customers->set_default_address( $customer_id, $address_id );
    }

    /**
     * Store or update a customer payment profile.
     *
     * @since    1.7.5
     * @param    int    $customer_id Customer ID.
     * @param    string $gateway Gateway name.
     * @param    string $payment_method Payment method.
     * @param    string $label Profile label.
     * @param    string $gateway_customer_id Gateway customer ID.
     * @param    string $gateway_payment_method_id Gateway payment method ID.
     * @return   bool
     */
    public function remember_customer_payment_profile( $customer_id, $gateway, $payment_method, $label, $gateway_customer_id = '', $gateway_payment_method_id = '' ) {
        return $this->customers->remember_payment_profile( $customer_id, $gateway, $payment_method, $label, $gateway_customer_id, $gateway_payment_method_id );
    }

    /**
     * Get a customer's saved payment profile.
     *
     * @since    1.7.5
     * @param    int    $customer_id Customer ID.
     * @param    string $gateway Gateway name.
     * @param    string $payment_method Payment method.
     * @return   object|null
     */
    public function get_customer_payment_profile( $customer_id, $gateway, $payment_method ) {
        return $this->customers->get_payment_profile( $customer_id, $gateway, $payment_method );
    }

    // =========================================================================
    // ONLINE ORDERS
    // =========================================================================

    /**
     * Get the accepted online order statuses.
     *
     * @since    1.0.7
     * @return   array
     */
    public function get_online_order_statuses() {
        return $this->online_orders->get_statuses();
    }

    /**
     * Generate the next public online order ID.
     *
     * @since    1.0.7
     * @return   string
     */
    public function generate_online_order_public_id() {
        return $this->online_orders->generate_public_id();
    }

    /**
     * Count online orders needing cashier attention.
     *
     * @since    1.0.9
     * @return   int
     */
    public function count_pending_online_orders() {
        return $this->online_orders->count_pending();
    }

    /**
     * Get online order notifications.
     *
     * @since    1.0.9
     * @return   array
     */
    public function get_online_order_notifications() {
        return $this->online_orders->get_notifications();
    }

    /**
     * Get an online order with its items.
     *
     * @since    1.0.8
     * @param    int $order_id Online order internal ID.
     * @return   array|null
     */
    public function get_online_order_with_items( $order_id ) {
        return $this->online_orders->get_with_items( $order_id );
    }

    /**
     * Get online orders for a customer.
     *
     * @since    1.0.8
     * @param    int $customer_id Customer internal ID.
     * @return   array
     */
    public function get_customer_online_orders( $customer_id ) {
        return $this->online_orders->get_by_customer( $customer_id );
    }

    /**
     * Get recent online orders.
     *
     * @since    1.0.7
     * @return   array
     */
    public function get_recent_online_orders() {
        return $this->online_orders->get_recent();
    }

    /**
     * Get related data for online orders.
     *
     * @since    1.0.9
     * @param    array $orders Orders.
     * @return   array
     */
    public function get_online_order_related_data( $orders ) {
        return $this->online_orders->get_related_data( $orders );
    }

    /**
     * Update online order status.
     *
     * @since    1.0.9
     * @param    int    $order_id   Online order ID.
     * @param    string $new_status New status.
     * @param    array  $data       Extra data.
     * @return   array
     */
    public function update_online_order_status( $order_id, $new_status, $data = array() ) {
        return $this->online_orders->update_status( $order_id, $new_status, $data );
    }

    /**
     * Update payment proof status.
     *
     * @since    1.0.9
     * @param    int    $proof_id Proof ID.
     * @param    string $status   New status.
     * @param    string $reason   Rejection reason.
     * @return   array
     */
    public function update_payment_proof_status( $proof_id, $status, $reason = '' ) {
        return $this->online_orders->update_payment_proof_status( $proof_id, $status, $reason );
    }

    /**
     * Create an online order.
     *
     * @since    1.0.7
     * @param    array $data Order data.
     * @return   array
     */
    public function create_online_order( $data ) {
        return $this->online_orders->create( $data );
    }

    /**
     * Update gateway data for an online order.
     *
     * @since    1.7.5
     * @param    int   $order_id Order ID.
     * @param    array $data     Field data.
     * @return   bool
     */
    public function update_online_order_gateway_data( $order_id, $data ) {
        return $this->online_orders->update_gateway_data( $order_id, $data );
    }

    /**
     * Get online order by PayMongo intent ID.
     *
     * @since    1.7.5
     * @param    string $payment_intent_id PayMongo intent ID.
     * @return   object|null
     */
    public function get_online_order_by_paymongo_intent( $payment_intent_id ) {
        return $this->online_orders->get_by_paymongo_intent( $payment_intent_id );
    }

    /**
     * Mark a PayMongo order as paid.
     *
     * @since    1.7.5
     * @param    object $order             Order row.
     * @param    string $payment_id        Payment ID.
     * @param    string $payment_method_id Payment method ID.
     * @param    string $event_id          Event ID.
     * @return   bool
     */
    public function mark_paymongo_order_paid( $order, $payment_id, $payment_method_id, $event_id ) {
        return $this->online_orders->mark_paymongo_paid( $order, $payment_id, $payment_method_id, $event_id );
    }

    /**
     * Mark a PayMongo order as failed.
     *
     * @since    1.7.5
     * @param    object $order   Order row.
     * @param    string $reason  Failure reason.
     * @param    string $event_id Event ID.
     * @return   bool
     */
    public function mark_paymongo_order_failed( $order, $reason, $event_id ) {
        return $this->online_orders->mark_paymongo_failed( $order, $reason, $event_id );
    }

    /**
     * Create a status log for an online order.
     *
     * @since    1.0.7
     * @param    int    $online_order_id Order ID.
     * @param    string $old_status      Old status.
     * @param    string $new_status      New status.
     * @param    string $note            Note.
     * @return   bool
     */
    public function create_online_order_status_log( $online_order_id, $old_status, $new_status, $note = '' ) {
        return $this->online_orders->create_status_log( $online_order_id, $old_status, $new_status, $note );
    }

    // =========================================================================
    // DELIVERY AREAS
    // =========================================================================

    /**
     * Create a delivery area.
     *
     * @since    1.0.7
     * @param    array $data Delivery area data.
     * @return   bool
     */
    public function create_delivery_area( $data ) {
        return $this->delivery_areas->create( $data );
    }

    /**
     * Get delivery areas.
     *
     * @since    1.0.7
     * @return   array
     */
    public function get_delivery_areas() {
        return $this->delivery_areas->get_all();
    }

    /**
     * Get walk-in sales for a date and branch (dashboard).
     *
     * @since    1.7.5
     * @param    string $date       Date.
     * @param    string $branch_key Branch key.
     * @return   float
     */
    public function get_sales_for_date_branch( $date, $branch_key = 'manukan_branch' ) {
        return $this->orders->get_sales_for_date_branch( $date, $branch_key );
    }

    /**
     * Validate and place a walk-in order.
     *
     * @since    1.7.5
     * @param    array $items      Cart items.
     * @param    array $order_data Order fields.
     * @return   array
     */
    public function place_order( $items, $order_data ) {
        return $this->orders->place_order( $items, $order_data );
    }
}
