<?php
/**
 * Handle database schema and migrations
 *
 * @link       https://banoks.com
 * @since      1.0.0
 * @package    Banoks_POS
 * @subpackage Banoks_POS/includes/database
 * @author     Christian Fulache
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Banoks_DB {

    /**
     * Create custom database tables for the POS system.
     *
     * This method is called during plugin activation.
     *
     * @since    1.0.0
     */
    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Table C: Products/Items
        $table_items = $wpdb->prefix . 'banoks_items';
        $sql_items = "CREATE TABLE $table_items (
            product_id bigint(20) NOT NULL AUTO_INCREMENT,
            product_name varchar(255) NOT NULL,
            product_description text,
            category varchar(100) DEFAULT 'General' NOT NULL,
            current_price decimal(10,2) NOT NULL,
            product_image_id bigint(20) DEFAULT 0 NOT NULL,
            is_available tinyint(1) DEFAULT 1 NOT NULL,
            is_active tinyint(1) DEFAULT 1 NOT NULL,
            track_stock tinyint(1) DEFAULT 0 NOT NULL,
            stock_quantity int(11) DEFAULT 0 NOT NULL,
            stock_unit varchar(30) DEFAULT 'pcs' NOT NULL,
            low_stock_threshold int(11) DEFAULT 5 NOT NULL,
            sort_order int(11) DEFAULT 0 NOT NULL,
            PRIMARY KEY  (product_id)
        ) $charset_collate;";

        // Table A: Orders
        $table_orders = $wpdb->prefix . 'banoks_orders';
        $sql_orders = "CREATE TABLE $table_orders (
            order_id bigint(20) NOT NULL AUTO_INCREMENT,
            created_by varchar(255) NOT NULL,
            branch_key varchar(50) DEFAULT 'manukan_branch' NOT NULL,
            entry_timestamp datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            date date NOT NULL,
            grand_total decimal(10,2) NOT NULL,
            payment_method varchar(30) DEFAULT 'cash' NOT NULL,
            received_account varchar(30) DEFAULT 'store_cash' NOT NULL,
            status varchar(50) DEFAULT 'pending' NOT NULL,
            PRIMARY KEY  (order_id),
            KEY payment_method (payment_method),
            KEY received_account (received_account),
            KEY branch_key (branch_key),
            KEY status (status)
        ) $charset_collate;";

        // Table B: Order Items (Junction Table)
        $table_order_items = $wpdb->prefix . 'banoks_order_items';
        $sql_order_items = "CREATE TABLE $table_order_items (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            product_id bigint(20) NOT NULL,
            qty int(11) NOT NULL,
            unit_price_at_sale decimal(10,2) NOT NULL,
            sub_total decimal(10,2) NOT NULL,
            PRIMARY KEY  (id),
            KEY order_id (order_id),
            KEY product_id (product_id)
        ) $charset_collate;";

        // Table D: Expenses
        $table_expenses = $wpdb->prefix . 'banoks_expenses';
        $sql_expenses = "CREATE TABLE $table_expenses (
            expense_id bigint(20) NOT NULL AUTO_INCREMENT,
            description varchar(255) NOT NULL,
            amount decimal(10,2) NOT NULL,
            date date NOT NULL,
            branch_key varchar(50) DEFAULT 'manukan_branch' NOT NULL,
            cash_source varchar(30) DEFAULT 'store_cash' NOT NULL,
            created_by bigint(20) DEFAULT 0 NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (expense_id),
            KEY branch_key (branch_key)
        ) $charset_collate;";

        $table_branches = $wpdb->prefix . 'banoks_branches';
        $sql_branches = "CREATE TABLE $table_branches (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            branch_key varchar(50) NOT NULL,
            branch_name varchar(255) NOT NULL,
            is_active tinyint(1) DEFAULT 1 NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY branch_key (branch_key),
            KEY is_active (is_active)
        ) $charset_collate;";

        $table_customers = $wpdb->prefix . 'banoks_customers';
        $sql_customers = "CREATE TABLE $table_customers (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            customer_id varchar(30) NOT NULL,
            full_name varchar(255) NOT NULL,
            username varchar(100) DEFAULT '' NOT NULL,
            phone varchar(50) NOT NULL,
            contact_number varchar(50) DEFAULT '' NOT NULL,
            email varchar(255) DEFAULT '' NOT NULL,
            password_hash varchar(255) DEFAULT '' NOT NULL,
            address text,
            municipality varchar(100) DEFAULT 'Manukan' NOT NULL,
            barangay varchar(255) DEFAULT '' NOT NULL,
            sitio varchar(255) DEFAULT '' NOT NULL,
            delivery_area_id bigint(20) DEFAULT 0 NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY customer_id (customer_id),
            KEY username (username),
            KEY phone (phone),
            KEY delivery_area_id (delivery_area_id)
        ) $charset_collate;";

        $table_customer_addresses = $wpdb->prefix . 'banoks_customer_addresses';
        $sql_customer_addresses = "CREATE TABLE $table_customer_addresses (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) NOT NULL,
            municipality varchar(100) DEFAULT 'Manukan' NOT NULL,
            barangay varchar(255) DEFAULT '' NOT NULL,
            sitio varchar(255) DEFAULT '' NOT NULL,
            address text NOT NULL,
            delivery_area_id bigint(20) DEFAULT 0 NOT NULL,
            is_default tinyint(1) DEFAULT 0 NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY customer_id (customer_id),
            KEY delivery_area_id (delivery_area_id),
            KEY is_default (is_default)
        ) $charset_collate;";

        $table_delivery_areas = $wpdb->prefix . 'banoks_delivery_areas';
        $sql_delivery_areas = "CREATE TABLE $table_delivery_areas (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            area_name varchar(255) NOT NULL,
            is_deliverable tinyint(1) DEFAULT 1 NOT NULL,
            delivery_fee decimal(10,2) DEFAULT 0.00 NOT NULL,
            sort_order int(11) DEFAULT 0 NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY is_deliverable (is_deliverable),
            KEY sort_order (sort_order)
        ) $charset_collate;";

        $table_online_orders = $wpdb->prefix . 'banoks_online_orders';
        $sql_online_orders = "CREATE TABLE $table_online_orders (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            online_order_id varchar(40) NOT NULL,
            branch_key varchar(50) DEFAULT 'manukan_branch' NOT NULL,
            customer_id bigint(20) NOT NULL,
            customer_public_id varchar(30) NOT NULL,
            customer_name varchar(255) NOT NULL,
            customer_phone varchar(50) NOT NULL,
            delivery_address text NOT NULL,
            delivery_area_id bigint(20) NOT NULL,
            delivery_area_name varchar(255) NOT NULL,
            fulfillment_type varchar(20) DEFAULT 'delivery' NOT NULL,
            payment_method varchar(30) NOT NULL,
            payment_status varchar(50) DEFAULT 'unpaid' NOT NULL,
            payment_gateway varchar(50) DEFAULT '' NOT NULL,
            gateway_checkout_status varchar(50) DEFAULT '' NOT NULL,
            paymongo_payment_intent_id varchar(120) DEFAULT '' NOT NULL,
            paymongo_payment_method_id varchar(120) DEFAULT '' NOT NULL,
            paymongo_payment_id varchar(120) DEFAULT '' NOT NULL,
            paymongo_webhook_event_id varchar(120) DEFAULT '' NOT NULL,
            payment_failure_reason text,
            order_status varchar(50) DEFAULT 'pending' NOT NULL,
            subtotal decimal(10,2) DEFAULT 0.00 NOT NULL,
            delivery_fee decimal(10,2) DEFAULT 0.00 NOT NULL,
            total_amount decimal(10,2) DEFAULT 0.00 NOT NULL,
            driver_name varchar(255) DEFAULT '' NOT NULL,
            driver_contact varchar(50) DEFAULT '' NOT NULL,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            completed_at datetime NULL,
            cancelled_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY online_order_id (online_order_id),
            KEY branch_key (branch_key),
            KEY customer_id (customer_id),
            KEY fulfillment_type (fulfillment_type),
            KEY order_status (order_status),
            KEY payment_status (payment_status),
            KEY payment_gateway (payment_gateway),
            KEY paymongo_payment_intent_id (paymongo_payment_intent_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        $table_customer_payment_profiles = $wpdb->prefix . 'banoks_customer_payment_profiles';
        $sql_customer_payment_profiles = "CREATE TABLE $table_customer_payment_profiles (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) NOT NULL,
            gateway varchar(50) DEFAULT 'paymongo' NOT NULL,
            payment_method varchar(30) DEFAULT 'gcash' NOT NULL,
            profile_label varchar(120) DEFAULT '' NOT NULL,
            gateway_customer_id varchar(120) DEFAULT '' NOT NULL,
            gateway_payment_method_id varchar(120) DEFAULT '' NOT NULL,
            last_used_at datetime NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY customer_gateway_method (customer_id, gateway, payment_method),
            KEY customer_id (customer_id),
            KEY gateway (gateway),
            KEY payment_method (payment_method)
        ) $charset_collate;";

        $table_online_order_items = $wpdb->prefix . 'banoks_online_order_items';
        $sql_online_order_items = "CREATE TABLE $table_online_order_items (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            online_order_id bigint(20) NOT NULL,
            product_id bigint(20) NOT NULL,
            product_name varchar(255) NOT NULL,
            quantity int(11) NOT NULL,
            price decimal(10,2) NOT NULL,
            subtotal decimal(10,2) NOT NULL,
            PRIMARY KEY  (id),
            KEY online_order_id (online_order_id),
            KEY product_id (product_id)
        ) $charset_collate;";

        $table_payment_proofs = $wpdb->prefix . 'banoks_payment_proofs';
        $sql_payment_proofs = "CREATE TABLE $table_payment_proofs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            online_order_id bigint(20) NOT NULL,
            reference_number varchar(120) DEFAULT '' NOT NULL,
            screenshot_url text,
            attachment_id bigint(20) DEFAULT 0 NOT NULL,
            verified_by bigint(20) DEFAULT 0 NOT NULL,
            verified_at datetime NULL,
            status varchar(50) DEFAULT 'pending' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY online_order_id (online_order_id),
            KEY status (status)
        ) $charset_collate;";

        $table_status_logs = $wpdb->prefix . 'banoks_online_order_status_logs';
        $sql_status_logs = "CREATE TABLE $table_status_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            online_order_id bigint(20) NOT NULL,
            old_status varchar(50) DEFAULT '' NOT NULL,
            new_status varchar(50) NOT NULL,
            updated_by bigint(20) DEFAULT 0 NOT NULL,
            note text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY online_order_id (online_order_id),
            KEY new_status (new_status)
        ) $charset_collate;";

        $table_stock_logs = $wpdb->prefix . 'banoks_stock_logs';
        $sql_stock_logs = "CREATE TABLE $table_stock_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            old_stock int(11) DEFAULT 0 NOT NULL,
            new_stock int(11) DEFAULT 0 NOT NULL,
            change_amount int(11) DEFAULT 0 NOT NULL,
            source varchar(50) DEFAULT 'manual' NOT NULL,
            source_id varchar(80) DEFAULT '' NOT NULL,
            updated_by bigint(20) DEFAULT 0 NOT NULL,
            note text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY product_id (product_id),
            KEY source (source),
            KEY created_at (created_at)
        ) $charset_collate;";

        $table_inventory_items = $wpdb->prefix . 'banoks_inventory_items';
        $sql_inventory_items = "CREATE TABLE $table_inventory_items (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            item_name varchar(255) NOT NULL,
            category varchar(100) DEFAULT 'Ingredients' NOT NULL,
            unit varchar(30) DEFAULT 'pcs' NOT NULL,
            current_stock decimal(12,3) DEFAULT 0.000 NOT NULL,
            unit_cost decimal(12,2) DEFAULT 0.00 NOT NULL,
            low_stock_threshold decimal(12,3) DEFAULT 0.000 NOT NULL,
            is_active tinyint(1) DEFAULT 1 NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY category (category),
            KEY is_active (is_active)
        ) $charset_collate;";

        $table_stock_locations = $wpdb->prefix . 'banoks_stock_locations';
        $sql_stock_locations = "CREATE TABLE $table_stock_locations (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            location_key varchar(50) NOT NULL,
            location_name varchar(255) NOT NULL,
            location_type varchar(30) DEFAULT 'branch' NOT NULL,
            is_active tinyint(1) DEFAULT 1 NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY location_key (location_key),
            KEY location_type (location_type),
            KEY is_active (is_active)
        ) $charset_collate;";

        $table_inventory_balances = $wpdb->prefix . 'banoks_inventory_balances';
        $sql_inventory_balances = "CREATE TABLE $table_inventory_balances (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            inventory_item_id bigint(20) NOT NULL,
            location_key varchar(50) NOT NULL,
            current_stock decimal(12,3) DEFAULT 0.000 NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY item_location (inventory_item_id, location_key),
            KEY location_key (location_key),
            KEY inventory_item_id (inventory_item_id)
        ) $charset_collate;";

        $table_inventory_movements = $wpdb->prefix . 'banoks_inventory_movements';
        $sql_inventory_movements = "CREATE TABLE $table_inventory_movements (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            inventory_item_id bigint(20) NOT NULL,
            location_key varchar(50) DEFAULT 'production' NOT NULL,
            movement_type varchar(40) NOT NULL,
            old_stock decimal(12,3) DEFAULT 0.000 NOT NULL,
            new_stock decimal(12,3) DEFAULT 0.000 NOT NULL,
            change_amount decimal(12,3) DEFAULT 0.000 NOT NULL,
            unit_cost decimal(12,2) DEFAULT 0.00 NOT NULL,
            total_cost decimal(12,2) DEFAULT 0.00 NOT NULL,
            affects_cash_balance tinyint(1) DEFAULT 0 NOT NULL,
            cash_source varchar(30) DEFAULT 'store_cash' NOT NULL,
            source varchar(50) DEFAULT 'manual' NOT NULL,
            source_id varchar(80) DEFAULT '' NOT NULL,
            updated_by bigint(20) DEFAULT 0 NOT NULL,
            note text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY inventory_item_id (inventory_item_id),
            KEY location_key (location_key),
            KEY movement_type (movement_type),
            KEY created_at (created_at)
        ) $charset_collate;";

        $table_requests = $wpdb->prefix . 'banoks_requests';
        $sql_requests = "CREATE TABLE $table_requests (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            request_type varchar(50) NOT NULL,
            request_status varchar(30) DEFAULT 'pending' NOT NULL,
            branch_key varchar(50) DEFAULT 'manukan_branch' NOT NULL,
            inventory_item_id bigint(20) DEFAULT 0 NOT NULL,
            quantity decimal(12,3) DEFAULT 0.000 NOT NULL,
            unit varchar(30) DEFAULT '' NOT NULL,
            estimated_cost decimal(12,2) DEFAULT 0.00 NOT NULL,
            cash_source varchar(30) DEFAULT 'store_cash' NOT NULL,
            expense_date date NULL,
            description varchar(255) DEFAULT '' NOT NULL,
            note text,
            decision_note text,
            requested_by bigint(20) DEFAULT 0 NOT NULL,
            decided_by bigint(20) DEFAULT 0 NOT NULL,
            decided_at datetime NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY request_type (request_type),
            KEY request_status (request_status),
            KEY branch_key (branch_key),
            KEY inventory_item_id (inventory_item_id),
            KEY requested_by (requested_by)
        ) $charset_collate;";

        $table_finance_transactions = $wpdb->prefix . 'banoks_finance_transactions';
        $sql_finance_transactions = "CREATE TABLE $table_finance_transactions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            transaction_type varchar(50) DEFAULT 'cash_sales_claim' NOT NULL,
            source_account varchar(30) DEFAULT 'store_cash' NOT NULL,
            destination_account varchar(30) DEFAULT '' NOT NULL,
            branch_key varchar(50) DEFAULT 'manukan_branch' NOT NULL,
            amount decimal(12,2) DEFAULT 0.00 NOT NULL,
            transaction_date date NOT NULL,
            note text,
            created_by bigint(20) DEFAULT 0 NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY transaction_type (transaction_type),
            KEY source_account (source_account),
            KEY destination_account (destination_account),
            KEY branch_key (branch_key),
            KEY transaction_date (transaction_date)
        ) $charset_collate;";

        $table_request_logs = $wpdb->prefix . 'banoks_request_logs';
        $sql_request_logs = "CREATE TABLE $table_request_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            request_id bigint(20) NOT NULL,
            old_status varchar(30) DEFAULT '' NOT NULL,
            new_status varchar(30) NOT NULL,
            updated_by bigint(20) DEFAULT 0 NOT NULL,
            note text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY request_id (request_id),
            KEY new_status (new_status)
        ) $charset_collate;";

        $table_product_recipes = $wpdb->prefix . 'banoks_product_recipes';
        $sql_product_recipes = "CREATE TABLE $table_product_recipes (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            inventory_item_id bigint(20) NOT NULL,
            quantity_used decimal(12,3) DEFAULT 0.000 NOT NULL,
            applies_to varchar(30) DEFAULT 'all' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY product_id (product_id),
            KEY inventory_item_id (inventory_item_id),
            KEY applies_to (applies_to)
        ) $charset_collate;";

        $table_product_addons = $wpdb->prefix . 'banoks_product_addons';
        $sql_product_addons = "CREATE TABLE $table_product_addons (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            addon_product_id bigint(20) NOT NULL,
            sort_order int(11) DEFAULT 0 NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY product_id (product_id),
            KEY addon_product_id (addon_product_id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql_items );
        dbDelta( $sql_orders );
        dbDelta( $sql_order_items );
        dbDelta( $sql_expenses );
        dbDelta( $sql_branches );
        dbDelta( $sql_customers );
        dbDelta( $sql_customer_addresses );
        dbDelta( $sql_delivery_areas );
        dbDelta( $sql_online_orders );
        dbDelta( $sql_customer_payment_profiles );
        dbDelta( $sql_online_order_items );
        dbDelta( $sql_payment_proofs );
        dbDelta( $sql_status_logs );
        dbDelta( $sql_stock_logs );
        dbDelta( $sql_inventory_items );
        dbDelta( $sql_stock_locations );
        dbDelta( $sql_inventory_balances );
        dbDelta( $sql_inventory_movements );
        dbDelta( $sql_requests );
        dbDelta( $sql_finance_transactions );
        dbDelta( $sql_request_logs );
        dbDelta( $sql_product_recipes );
        dbDelta( $sql_product_addons );

        self::maybe_add_column( $table_items, 'product_image_id', "ALTER TABLE $table_items ADD product_image_id bigint(20) DEFAULT 0 NOT NULL AFTER current_price" );
        self::maybe_add_column( $table_items, 'product_description', "ALTER TABLE $table_items ADD product_description text AFTER product_name" );
        self::maybe_add_column( $table_items, 'is_available', "ALTER TABLE $table_items ADD is_available tinyint(1) DEFAULT 1 NOT NULL AFTER product_image_id" );
        self::maybe_add_column( $table_items, 'is_active', "ALTER TABLE $table_items ADD is_active tinyint(1) DEFAULT 1 NOT NULL AFTER is_available" );
        self::maybe_add_column( $table_items, 'track_stock', "ALTER TABLE $table_items ADD track_stock tinyint(1) DEFAULT 0 NOT NULL AFTER is_active" );
        self::maybe_add_column( $table_items, 'stock_quantity', "ALTER TABLE $table_items ADD stock_quantity int(11) DEFAULT 0 NOT NULL AFTER track_stock" );
        self::maybe_add_column( $table_items, 'stock_unit', "ALTER TABLE $table_items ADD stock_unit varchar(30) DEFAULT 'pcs' NOT NULL AFTER stock_quantity" );
        self::maybe_add_column( $table_items, 'low_stock_threshold', "ALTER TABLE $table_items ADD low_stock_threshold int(11) DEFAULT 5 NOT NULL AFTER stock_unit" );
        self::maybe_add_column( $table_items, 'sort_order', "ALTER TABLE $table_items ADD sort_order int(11) DEFAULT 0 NOT NULL AFTER low_stock_threshold" );
        self::maybe_add_column( $table_orders, 'branch_key', "ALTER TABLE $table_orders ADD branch_key varchar(50) DEFAULT 'manukan_branch' NOT NULL AFTER created_by" );
        self::maybe_add_column( $table_orders, 'payment_method', "ALTER TABLE $table_orders ADD payment_method varchar(30) DEFAULT 'cash' NOT NULL AFTER grand_total" );
        self::maybe_add_column( $table_orders, 'received_account', "ALTER TABLE $table_orders ADD received_account varchar(30) DEFAULT 'store_cash' NOT NULL AFTER payment_method" );
        self::maybe_add_column( $table_expenses, 'branch_key', "ALTER TABLE $table_expenses ADD branch_key varchar(50) DEFAULT 'manukan_branch' NOT NULL AFTER date" );
        self::maybe_add_column( $table_expenses, 'cash_source', "ALTER TABLE $table_expenses ADD cash_source varchar(30) DEFAULT 'store_cash' NOT NULL AFTER date" );
        self::maybe_add_column( $table_expenses, 'created_by', "ALTER TABLE $table_expenses ADD created_by bigint(20) DEFAULT 0 NOT NULL AFTER cash_source" );
        self::maybe_add_column( $table_customers, 'username', "ALTER TABLE $table_customers ADD username varchar(100) DEFAULT '' NOT NULL AFTER full_name" );
        self::maybe_add_column( $table_customers, 'contact_number', "ALTER TABLE $table_customers ADD contact_number varchar(50) DEFAULT '' NOT NULL AFTER phone" );
        self::maybe_add_column( $table_customers, 'municipality', "ALTER TABLE $table_customers ADD municipality varchar(100) DEFAULT 'Manukan' NOT NULL AFTER address" );
        self::maybe_add_column( $table_customers, 'barangay', "ALTER TABLE $table_customers ADD barangay varchar(255) DEFAULT '' NOT NULL AFTER municipality" );
        self::maybe_add_column( $table_customers, 'sitio', "ALTER TABLE $table_customers ADD sitio varchar(255) DEFAULT '' NOT NULL AFTER barangay" );
        self::maybe_add_column( $table_online_orders, 'branch_key', "ALTER TABLE $table_online_orders ADD branch_key varchar(50) DEFAULT 'manukan_branch' NOT NULL AFTER online_order_id" );
        self::maybe_add_column( $table_online_orders, 'fulfillment_type', "ALTER TABLE $table_online_orders ADD fulfillment_type varchar(20) DEFAULT 'delivery' NOT NULL AFTER delivery_area_name" );
        self::maybe_add_column( $table_online_orders, 'payment_gateway', "ALTER TABLE $table_online_orders ADD payment_gateway varchar(50) DEFAULT '' NOT NULL AFTER payment_status" );
        self::maybe_add_column( $table_online_orders, 'gateway_checkout_status', "ALTER TABLE $table_online_orders ADD gateway_checkout_status varchar(50) DEFAULT '' NOT NULL AFTER payment_gateway" );
        self::maybe_add_column( $table_online_orders, 'paymongo_payment_intent_id', "ALTER TABLE $table_online_orders ADD paymongo_payment_intent_id varchar(120) DEFAULT '' NOT NULL AFTER gateway_checkout_status" );
        self::maybe_add_column( $table_online_orders, 'paymongo_payment_method_id', "ALTER TABLE $table_online_orders ADD paymongo_payment_method_id varchar(120) DEFAULT '' NOT NULL AFTER paymongo_payment_intent_id" );
        self::maybe_add_column( $table_online_orders, 'paymongo_payment_id', "ALTER TABLE $table_online_orders ADD paymongo_payment_id varchar(120) DEFAULT '' NOT NULL AFTER paymongo_payment_method_id" );
        self::maybe_add_column( $table_online_orders, 'paymongo_webhook_event_id', "ALTER TABLE $table_online_orders ADD paymongo_webhook_event_id varchar(120) DEFAULT '' NOT NULL AFTER paymongo_payment_id" );
        self::maybe_add_column( $table_online_orders, 'payment_failure_reason', "ALTER TABLE $table_online_orders ADD payment_failure_reason text AFTER paymongo_webhook_event_id" );
        self::maybe_add_column( $table_inventory_items, 'unit_cost', "ALTER TABLE $table_inventory_items ADD unit_cost decimal(12,2) DEFAULT 0.00 NOT NULL AFTER current_stock" );
        self::maybe_add_column( $table_inventory_movements, 'location_key', "ALTER TABLE $table_inventory_movements ADD location_key varchar(50) DEFAULT 'production' NOT NULL AFTER inventory_item_id" );
        self::maybe_add_column( $table_inventory_movements, 'unit_cost', "ALTER TABLE $table_inventory_movements ADD unit_cost decimal(12,2) DEFAULT 0.00 NOT NULL AFTER change_amount" );
        self::maybe_add_column( $table_inventory_movements, 'total_cost', "ALTER TABLE $table_inventory_movements ADD total_cost decimal(12,2) DEFAULT 0.00 NOT NULL AFTER unit_cost" );
        self::maybe_add_column( $table_inventory_movements, 'affects_cash_balance', "ALTER TABLE $table_inventory_movements ADD affects_cash_balance tinyint(1) DEFAULT 0 NOT NULL AFTER total_cost" );
        self::maybe_add_column( $table_inventory_movements, 'cash_source', "ALTER TABLE $table_inventory_movements ADD cash_source varchar(30) DEFAULT 'store_cash' NOT NULL AFTER affects_cash_balance" );
        self::maybe_add_column( $table_requests, 'branch_key', "ALTER TABLE $table_requests ADD branch_key varchar(50) DEFAULT 'manukan_branch' NOT NULL AFTER request_status" );
        self::maybe_add_column( $table_product_recipes, 'applies_to', "ALTER TABLE $table_product_recipes ADD applies_to varchar(30) DEFAULT 'all' NOT NULL AFTER quantity_used" );
        $wpdb->query( "UPDATE $table_items SET is_available = 1 WHERE is_available IS NULL" );
        $wpdb->query( "UPDATE $table_items SET is_active = 1 WHERE is_active IS NULL" );
        $wpdb->query( "UPDATE $table_items SET track_stock = 0 WHERE track_stock IS NULL" );
        $wpdb->query( "UPDATE $table_items SET stock_quantity = 0 WHERE stock_quantity IS NULL OR stock_quantity < 0" );
        $wpdb->query( "UPDATE $table_items SET stock_unit = 'pcs' WHERE stock_unit IS NULL OR stock_unit = ''" );
        $wpdb->query( "UPDATE $table_items SET low_stock_threshold = 5 WHERE low_stock_threshold IS NULL OR low_stock_threshold < 0" );
        $wpdb->query( "UPDATE $table_items SET sort_order = product_id WHERE sort_order IS NULL OR sort_order <= 0" );
        $wpdb->query( "UPDATE $table_orders SET branch_key = 'manukan_branch' WHERE branch_key IS NULL OR branch_key = ''" );
        $wpdb->query( "UPDATE $table_orders SET payment_method = 'cash' WHERE payment_method IS NULL OR payment_method = ''" );
        $wpdb->query( "UPDATE $table_orders SET received_account = 'store_cash' WHERE received_account IS NULL OR received_account = ''" );
        $wpdb->query( "UPDATE $table_expenses SET branch_key = 'manukan_branch' WHERE branch_key IS NULL OR branch_key = ''" );
        $wpdb->query( "UPDATE $table_expenses SET cash_source = 'store_cash' WHERE cash_source IS NULL OR cash_source = ''" );
        $wpdb->query( "UPDATE $table_expenses SET cash_source = 'gcash_balance' WHERE cash_source = 'gcash_bank'" );
        $wpdb->query( "UPDATE $table_expenses SET cash_source = 'bank_balance' WHERE cash_source IN ('owner_external', 'payable')" );
        $wpdb->query( "UPDATE $table_customers SET contact_number = phone WHERE contact_number IS NULL OR contact_number = ''" );
        $wpdb->query( "UPDATE $table_customers SET municipality = 'Manukan' WHERE municipality IS NULL OR municipality = ''" );
        $wpdb->query( "UPDATE $table_online_orders SET branch_key = 'manukan_branch' WHERE branch_key IS NULL OR branch_key = ''" );
        $wpdb->query( "UPDATE $table_online_orders SET fulfillment_type = 'delivery' WHERE fulfillment_type IS NULL OR fulfillment_type = ''" );
        $wpdb->query( "UPDATE $table_inventory_items SET unit_cost = 0 WHERE unit_cost IS NULL OR unit_cost < 0" );
        $wpdb->query( "UPDATE $table_inventory_movements SET unit_cost = 0 WHERE unit_cost IS NULL OR unit_cost < 0" );
        $wpdb->query( "UPDATE $table_inventory_movements SET total_cost = 0 WHERE total_cost IS NULL OR total_cost < 0" );
        $wpdb->query( "UPDATE $table_inventory_movements SET affects_cash_balance = 0 WHERE affects_cash_balance IS NULL" );
        $wpdb->query( "UPDATE $table_inventory_movements SET cash_source = 'store_cash' WHERE cash_source IS NULL OR cash_source = ''" );
        $wpdb->query( "UPDATE $table_inventory_movements SET cash_source = 'gcash_balance' WHERE cash_source = 'gcash_bank'" );
        $wpdb->query( "UPDATE $table_inventory_movements SET cash_source = 'bank_balance' WHERE cash_source IN ('owner_external', 'payable')" );
        $wpdb->query( "UPDATE $table_inventory_movements SET location_key = 'production' WHERE location_key IS NULL OR location_key = ''" );
        $wpdb->query( "UPDATE $table_requests SET branch_key = 'manukan_branch' WHERE branch_key IS NULL OR branch_key = ''" );
        $wpdb->query( "UPDATE $table_product_recipes SET applies_to = 'all' WHERE applies_to IS NULL OR applies_to = ''" );
        $wpdb->query( "UPDATE $table_finance_transactions SET transaction_type = 'cash_sales_claim' WHERE transaction_type = 'store_claim'" );

        $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO $table_branches (branch_key, branch_name, is_active, created_at, updated_at)
                 VALUES (%s, %s, 1, %s, %s)",
                'manukan_branch',
                'Manukan Branch',
                current_time( 'mysql' ),
                current_time( 'mysql' )
            )
        );

        $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO $table_stock_locations (location_key, location_name, location_type, is_active, created_at, updated_at)
                 VALUES (%s, %s, %s, 1, %s, %s)",
                'production',
                'Production Stock',
                'production',
                current_time( 'mysql' ),
                current_time( 'mysql' )
            )
        );
        $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO $table_stock_locations (location_key, location_name, location_type, is_active, created_at, updated_at)
                 VALUES (%s, %s, %s, 1, %s, %s)",
                'manukan_branch',
                'Manukan Branch',
                'branch',
                current_time( 'mysql' ),
                current_time( 'mysql' )
            )
        );
        $wpdb->query(
            "INSERT IGNORE INTO $table_inventory_balances (inventory_item_id, location_key, current_stock, updated_at)
             SELECT id, 'production', current_stock, updated_at FROM $table_inventory_items"
        );
        $wpdb->query(
            "INSERT IGNORE INTO $table_inventory_balances (inventory_item_id, location_key, current_stock, updated_at)
             SELECT id, 'manukan_branch', 0, updated_at FROM $table_inventory_items"
        );
    }

    /**
     * Add a column when updating an existing install.
     *
     * @since    1.0.7
     * @param    string $table_name Table name.
     * @param    string $column     Column name.
     * @param    string $sql        ALTER TABLE statement.
     */
    private static function maybe_add_column( $table_name, $column, $sql ) {
        global $wpdb;

        $exists = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM $table_name LIKE %s", $column ) );

        if ( empty( $exists ) ) {
            $wpdb->query( $sql );
        }
    }
}
