<?php
/**
 * Product repository for Banoks POS.
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
 * Handles product and category data access.
 *
 * @since      1.0.0
 * @package    Banoks_POS
 * @subpackage Banoks_POS/includes/repositories
 */
class Banoks_Product_Repository {

    /**
     * Get products for the POS grid.
     *
     * @since    1.0.0
     * @return   array
     */
    public function get_products() {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}banoks_items WHERE COALESCE(is_active, 1) = 1 AND COALESCE(is_available, 1) = 1 ORDER BY sort_order ASC, product_name ASC"
        );
    }

    /**
     * Get unique product categories.
     *
     * @since    1.0.0
     * @return   array
     */
    public function get_categories() {
        global $wpdb;

        return $wpdb->get_col(
            "SELECT DISTINCT category FROM {$wpdb->prefix}banoks_items WHERE category != '' ORDER BY category ASC"
        );
    }

    /**
     * Get a single product by ID.
     *
     * @since    1.7.5
     * @param    int $product_id Product ID.
     * @return   object|null
     */
    public function get_product( $product_id ) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}banoks_items WHERE product_id = %d",
                absint( $product_id )
            )
        );
    }

    /**
     * Get multiple products by IDs.
     *
     * @since    1.7.5
     * @param    array $product_ids Product IDs.
     * @param    bool  $object_k    Return as OBJECT_K keyed by product_id.
     * @return   array
     */
    public function get_products_by_ids( $product_ids, $object_k = true ) {
        global $wpdb;

        $product_ids = array_values( array_filter( array_map( 'absint', (array) $product_ids ) ) );
        if ( empty( $product_ids ) ) {
            return array();
        }

        $placeholders = implode( ', ', array_fill( 0, count( $product_ids ), '%d' ) );
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT product_id, product_name, current_price, COALESCE(is_active, 1) AS is_active, COALESCE(is_available, 1) AS is_available
                 FROM {$wpdb->prefix}banoks_items
                 WHERE product_id IN ({$placeholders})",
                $product_ids
            ),
            $object_k ? OBJECT_K : OBJECT
        );

        return is_array( $results ) ? $results : array();
    }

    /**
     * Get product availability status for online ordering.
     *
     * @since    1.7.5
     * @param    array $product_ids Product IDs.
     * @param    string $location_key Stock location key.
     * @return   array
     */
    public function get_availability( $product_ids, $location_key = Banoks_POS_Repository::STOCK_LOCATION_MANUKAN ) {
        global $wpdb;

        $product_ids = array_values(
            array_filter(
                array_unique( array_map( 'absint', (array) $product_ids ) )
            )
        );

        if ( empty( $product_ids ) ) {
            return array();
        }

        $placeholders = implode( ', ', array_fill( 0, count( $product_ids ), '%d' ) );
        $products     = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT product_id, product_name, COALESCE(is_available, 1) AS is_available, COALESCE(is_active, 1) AS is_active, COALESCE(track_stock, 0) AS track_stock, COALESCE(stock_quantity, 0) AS stock_quantity
                 FROM {$wpdb->prefix}banoks_items
                 WHERE product_id IN ({$placeholders})",
                $product_ids
            ),
            OBJECT_K
        );

        $inventory_repo  = new Banoks_Inventory_Repository();
        $recipe_statuses = $inventory_repo->get_product_recipe_statuses( $product_ids, $location_key );
        $availability    = array();

        foreach ( $product_ids as $pid ) {
            $product       = isset( $products[ $pid ] ) ? $products[ $pid ] : null;
            $recipe_status = isset( $recipe_statuses[ $pid ] ) ? $recipe_statuses[ $pid ] : array();
            $reason        = '';
            $can_checkout  = true;

            if ( ! $product ) {
                $can_checkout = false;
                $reason       = 'This item is no longer available.';
            } elseif ( ! intval( $product->is_active ) || ! intval( $product->is_available ) ) {
                $can_checkout = false;
                $reason       = 'This item is currently unavailable.';
            } elseif ( intval( $product->track_stock ) && intval( $product->stock_quantity ) <= 0 ) {
                $can_checkout = false;
                $reason       = 'Out of Stock.';
            } elseif ( ! empty( $recipe_status['has_recipe'] ) && empty( $recipe_status['can_prepare'] ) ) {
                $can_checkout = false;
                $reason       = 'Out of Stock.';
            }

            $availability[ $pid ] = array(
                'canCheckout'    => $can_checkout,
                'reason'         => $reason,
                'stockQuantity'  => $product ? intval( $product->stock_quantity ) : 0,
                'availableStock' => isset( $recipe_status['available_stock'] ) ? $recipe_status['available_stock'] : null,
            );
        }

        return $availability;
    }
}