<?php
/**
 * Inventory repository for Banoks POS.
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
 * Handles inventory, recipes, stock movements, and stock alerts.
 *
 * @since      1.0.0
 * @package    Banoks_POS
 * @subpackage Banoks_POS/includes/repositories
 */
class Banoks_Inventory_Repository {

    const STOCK_LOCATION_PRODUCTION = 'production';
    const STOCK_LOCATION_MANUKAN    = 'manukan_branch';

    /**
     * Normalize order item quantities by product.
     *
     * @since    1.0.11
     * @param    array $items Order items.
     * @return   array
     */
    public function normalize_order_item_quantities( $items ) {
        $quantities = array();

        foreach ( $items as $item ) {
            if ( is_object( $item ) ) {
                $product_id = isset( $item->product_id ) ? absint( $item->product_id ) : 0;
                $quantity   = isset( $item->quantity ) ? absint( $item->quantity ) : ( isset( $item->qty ) ? absint( $item->qty ) : 0 );
            } else {
                $product_id = isset( $item['product_id'] ) ? absint( $item['product_id'] ) : 0;
                $quantity   = isset( $item['quantity'] ) ? absint( $item['quantity'] ) : ( isset( $item['qty'] ) ? absint( $item['qty'] ) : 0 );
            }

            if ( ! $product_id || ! $quantity ) {
                continue;
            }

            if ( ! isset( $quantities[ $product_id ] ) ) {
                $quantities[ $product_id ] = 0;
            }
            $quantities[ $product_id ] += $quantity;
        }

        return $quantities;
    }

    /**
     * Sanitize and validate a stock location key.
     *
     * @since    1.0.11
     * @param    string $location_key Location key.
     * @return   string
     */
    public function sanitize_stock_location_key( $location_key ) {
        $location_key = sanitize_key( $location_key );
        return in_array( $location_key, array( self::STOCK_LOCATION_PRODUCTION, self::STOCK_LOCATION_MANUKAN ), true ) ? $location_key : self::STOCK_LOCATION_MANUKAN;
    }

    /**
     * Format inventory quantity for messages.
     *
     * @since    1.0.11
     * @param    float $quantity Quantity.
     * @return   string
     */
    public function format_inventory_quantity( $quantity ) {
        return rtrim( rtrim( number_format( floatval( $quantity ), 3, '.', '' ), '0' ), '.' );
    }

    /**
     * Get recipe condition keys for an order context.
     *
     * @since    1.0.12
     * @param    string $recipe_context Context key.
     * @return   array
     */
    public function get_recipe_conditions_for_context( $recipe_context ) {
        $recipe_context = sanitize_key( $recipe_context );

        if ( 'walk_in' === $recipe_context ) {
            return array( 'all', 'walk_in' );
        }

        if ( 'delivery' === $recipe_context ) {
            return array( 'all', 'online', 'delivery' );
        }

        if ( 'pickup' === $recipe_context ) {
            return array( 'all', 'online', 'pickup' );
        }

        if ( 'online' === $recipe_context ) {
            return array( 'all', 'online' );
        }

        return array( 'all' );
    }

    /**
     * Get a human label for a recipe condition.
     *
     * @since    1.0.12
     * @param    string $condition Condition key.
     * @return   string
     */
    public function get_recipe_condition_label( $condition ) {
        $labels = array(
            'all'      => 'All orders',
            'walk_in'  => 'Walk-in only',
            'online'   => 'Online only',
            'delivery' => 'Delivery only',
            'pickup'   => 'Pickup only',
        );

        $condition = sanitize_key( $condition );
        return isset( $labels[ $condition ] ) ? $labels[ $condition ] : $labels['all'];
    }

    /**
     * Infer recipe context from an order source.
     *
     * @since    1.0.12
     * @param    string $source Source key.
     * @return   string
     */
    public function get_recipe_context_from_source( $source ) {
        $source = sanitize_key( $source );

        if ( 'walk_in' === $source ) {
            return 'walk_in';
        }

        if ( 'online_delivery' === $source ) {
            return 'delivery';
        }

        if ( 'online_pickup' === $source ) {
            return 'pickup';
        }

        if ( 'online' === $source ) {
            return 'online';
        }

        return 'all';
    }

    /**
     * Build ingredient requirements from product recipes.
     *
     * @since    1.0.11
     * @param    array  $product_quantities Product quantities.
     * @param    string $recipe_context     Recipe context.
     * @param    string $location_key       Stock location key.
     * @return   array
     */
    public function get_recipe_inventory_requirements( $product_quantities, $recipe_context = 'all', $location_key = self::STOCK_LOCATION_MANUKAN ) {
        global $wpdb;

        $allowed_conditions = $this->get_recipe_conditions_for_context( $recipe_context );
        $location_key       = $this->sanitize_stock_location_key( $location_key );
        $requirements       = array();

        foreach ( $product_quantities as $product_id => $product_quantity ) {
            $recipes = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT r.inventory_item_id, r.quantity_used, COALESCE(r.applies_to, 'all') AS applies_to, i.item_name, i.unit, COALESCE(b.current_stock, 0) AS current_stock, i.unit_cost, i.is_active
                     FROM {$wpdb->prefix}banoks_product_recipes r
                     INNER JOIN {$wpdb->prefix}banoks_inventory_items i ON r.inventory_item_id = i.id
                     LEFT JOIN {$wpdb->prefix}banoks_inventory_balances b ON b.inventory_item_id = i.id AND b.location_key = %s
                     WHERE r.product_id = %d",
                    $location_key,
                    $product_id
                )
            );

            foreach ( $recipes as $recipe ) {
                if ( ! in_array( sanitize_key( $recipe->applies_to ), $allowed_conditions, true ) ) {
                    continue;
                }

                $inventory_item_id = absint( $recipe->inventory_item_id );
                $needed            = floatval( $recipe->quantity_used ) * intval( $product_quantity );

                if ( $needed <= 0 ) {
                    continue;
                }

                if ( ! isset( $requirements[ $inventory_item_id ] ) ) {
                    $requirements[ $inventory_item_id ] = array(
                        'inventory_item_id' => $inventory_item_id,
                        'item_name'         => $recipe->item_name,
                        'unit'              => $recipe->unit,
                        'current_stock'     => floatval( $recipe->current_stock ),
                        'unit_cost'         => floatval( $recipe->unit_cost ),
                        'is_active'         => intval( $recipe->is_active ),
                        'quantity_needed'   => 0,
                    );
                }

                $requirements[ $inventory_item_id ]['quantity_needed'] += $needed;
            }
        }

        return $requirements;
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
        $requirements = $this->get_recipe_inventory_requirements(
            $this->normalize_order_item_quantities( $items ),
            $recipe_context,
            $location_key
        );

        foreach ( $requirements as $requirement ) {
            if ( ! $requirement['is_active'] ) {
                return array( 'error' => $requirement['item_name'] . ' is inactive in Stock Management.' );
            }

            if ( $requirement['current_stock'] < $requirement['quantity_needed'] ) {
                return array(
                    'error' => $requirement['item_name'] . ' has only ' . $this->format_inventory_quantity( $requirement['current_stock'] ) . ' ' . $requirement['unit'] . ' available, but needs ' . $this->format_inventory_quantity( $requirement['quantity_needed'] ) . ' ' . $requirement['unit'] . '.',
                );
            }
        }

        return array( 'success' => true );
    }

    /**
     * Deduct inventory ingredients based on product recipes.
     *
     * @since    1.0.11
     * @param    array  $product_quantities Product quantities.
     * @param    string $source             Source type.
     * @param    string $source_id          Source/order ID.
     * @param    string $recipe_context     Recipe context.
     * @param    string $location_key       Stock location key.
     * @return   array
     */
    public function deduct_recipe_inventory_for_items( $product_quantities, $source, $source_id = '', $recipe_context = 'all', $location_key = self::STOCK_LOCATION_MANUKAN ) {
        global $wpdb;

        $location_key = $this->sanitize_stock_location_key( $location_key );
        $requirements = $this->get_recipe_inventory_requirements( $product_quantities, $recipe_context, $location_key );

        foreach ( $requirements as $requirement ) {
            if ( ! $requirement['is_active'] ) {
                return array( 'error' => $requirement['item_name'] . ' is inactive in Stock Management.' );
            }

            if ( $requirement['current_stock'] < $requirement['quantity_needed'] ) {
                return array( 'error' => $requirement['item_name'] . ' does not have enough ingredient stock.' );
            }
        }

        foreach ( $requirements as $requirement ) {
            $old_stock = floatval( $requirement['current_stock'] );
            $new_stock = max( 0, $old_stock - floatval( $requirement['quantity_needed'] ) );
            $updated   = $wpdb->update(
                $wpdb->prefix . 'banoks_inventory_balances',
                array(
                    'current_stock' => $new_stock,
                    'updated_at'    => current_time( 'mysql' ),
                ),
                array(
                    'inventory_item_id' => $requirement['inventory_item_id'],
                    'location_key'      => $location_key,
                    'current_stock'     => $old_stock,
                ),
                array( '%f', '%s' ),
                array( '%d', '%s', '%f' )
            );

            if ( false === $updated || 0 === $updated ) {
                return array( 'error' => 'Could not deduct ingredient stock. Please try again.' );
            }

            $this->create_movement(
                $requirement['inventory_item_id'],
                $location_key,
                $old_stock,
                $new_stock,
                'recipe_usage',
                $source,
                $source_id,
                'Ingredient stock deducted from order.',
                floatval( $requirement['unit_cost'] )
            );
        }

        return array( 'success' => true );
    }

    /**
     * Deduct recipe inventory for a set of order items.
     *
     * @since    1.0.10
     * @param    array  $items           Items with product_id and quantity.
     * @param    string $source          Source type.
     * @param    string $source_id       Source/order ID.
     * @param    string $recipe_context  Recipe context override.
     * @return   array
     */
    public function deduct_stock_for_items( $items, $source, $source_id = '', $recipe_context = '' ) {
        $quantities      = $this->normalize_order_item_quantities( $items );
        $recipe_context  = $recipe_context ? sanitize_key( $recipe_context ) : $this->get_recipe_context_from_source( $source );

        $recipe_result = $this->deduct_recipe_inventory_for_items( $quantities, $source, $source_id, $recipe_context, self::STOCK_LOCATION_MANUKAN );
        if ( isset( $recipe_result['error'] ) ) {
            return $recipe_result;
        }

        return array( 'success' => true );
    }

    /**
     * Restore ingredient stock that was previously deducted for an order source.
     *
     * @since    1.0.12
     * @param    string $source    Source type used when stock was deducted.
     * @param    string $source_id Source/order ID used when stock was deducted.
     * @return   array
     */
    public function restore_stock_for_source( $source, $source_id ) {
        global $wpdb;

        $source    = sanitize_key( $source );
        $source_id = sanitize_text_field( $source_id );

        if ( '' === $source || '' === $source_id ) {
            return array( 'error' => 'Missing stock restoration source.' );
        }

        $movements = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT m.inventory_item_id, m.location_key, m.change_amount, m.unit_cost, i.item_name
                 FROM {$wpdb->prefix}banoks_inventory_movements m
                 LEFT JOIN {$wpdb->prefix}banoks_inventory_items i ON m.inventory_item_id = i.id
                 WHERE m.source = %s
                 AND m.source_id = %s
                 AND m.movement_type = 'recipe_usage'
                 AND m.change_amount < 0
                 ORDER BY m.id ASC",
                $source,
                $source_id
            )
        );

        if ( empty( $movements ) ) {
            return array( 'success' => true );
        }

        foreach ( $movements as $movement ) {
            $location_key = $this->sanitize_stock_location_key( $movement->location_key );
            $old_stock = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT current_stock
                     FROM {$wpdb->prefix}banoks_inventory_balances
                     WHERE inventory_item_id = %d
                     AND location_key = %s",
                    absint( $movement->inventory_item_id ),
                    $location_key
                )
            );

            if ( null === $old_stock ) {
                $inserted = $wpdb->insert(
                    $wpdb->prefix . 'banoks_inventory_balances',
                    array(
                        'inventory_item_id' => absint( $movement->inventory_item_id ),
                        'location_key'      => $location_key,
                        'current_stock'     => 0,
                        'updated_at'        => current_time( 'mysql' ),
                    ),
                    array( '%d', '%s', '%f', '%s' )
                );

                if ( false === $inserted ) {
                    return array( 'error' => 'Could not restore ingredient stock. Please try again.' );
                }

                $old_stock = 0;
            }

            $restore_quantity = abs( floatval( $movement->change_amount ) );
            $new_stock        = floatval( $old_stock ) + $restore_quantity;
            $updated          = $wpdb->update(
                $wpdb->prefix . 'banoks_inventory_balances',
                array(
                    'current_stock' => $new_stock,
                    'updated_at'    => current_time( 'mysql' ),
                ),
                array(
                    'inventory_item_id' => absint( $movement->inventory_item_id ),
                    'location_key'      => $location_key,
                    'current_stock'     => floatval( $old_stock ),
                ),
                array( '%f', '%s' ),
                array( '%d', '%s', '%f' )
            );

            if ( false === $updated || 0 === $updated ) {
                return array( 'error' => 'Could not restore ingredient stock. Please try again.' );
            }

            $this->create_movement(
                $movement->inventory_item_id,
                $location_key,
                $old_stock,
                $new_stock,
                'recipe_restore',
                $source,
                $source_id,
                'Ingredient stock restored from cancelled order.',
                floatval( $movement->unit_cost )
            );
        }

        return array( 'success' => true );
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
    public function create_movement( $inventory_item_id, $location_key, $old_stock, $new_stock, $movement_type, $source = 'manual', $source_id = '', $note = '', $unit_cost = 0, $affects_cash_balance = 0, $cash_source = 'store_cash' ) {
        global $wpdb;

        $location_key  = $this->sanitize_stock_location_key( $location_key );
        $change_amount = floatval( $new_stock ) - floatval( $old_stock );
        $unit_cost     = max( 0, floatval( $unit_cost ) );
        $total_cost    = abs( $change_amount ) * $unit_cost;
        $movement_type = sanitize_key( $movement_type );
        $affects_cash_balance = $affects_cash_balance && $change_amount > 0 && in_array( $movement_type, array( 'stock_in', 'correction' ), true ) ? 1 : 0;
        $cash_source   = sanitize_key( $cash_source );
        $cash_source   = '' !== $cash_source ? $cash_source : 'store_cash';

        return false !== $wpdb->insert(
            $wpdb->prefix . 'banoks_inventory_movements',
            array(
                'inventory_item_id'   => absint( $inventory_item_id ),
                'location_key'        => $location_key,
                'movement_type'       => $movement_type,
                'old_stock'           => floatval( $old_stock ),
                'new_stock'           => floatval( $new_stock ),
                'change_amount'       => $change_amount,
                'unit_cost'           => $unit_cost,
                'total_cost'          => $total_cost,
                'affects_cash_balance' => $affects_cash_balance,
                'cash_source'         => $cash_source,
                'source'              => sanitize_key( $source ),
                'source_id'           => sanitize_text_field( $source_id ),
                'updated_by'          => get_current_user_id(),
                'note'                => sanitize_textarea_field( $note ),
                'created_at'          => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%f', '%f', '%f', '%f', '%f', '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
        );
    }

    /**
     * Get active inventory items that need attention.
     *
     * @since    1.0.12
     * @param    int    $limit         Maximum alerts to return. Use 0 for no limit.
     * @param    string $location_key  Stock location key.
     * @return   array
     */
    public function get_stock_alerts( $limit = 0, $location_key = self::STOCK_LOCATION_MANUKAN ) {
        global $wpdb;

        $location_key = $this->sanitize_stock_location_key( $location_key );
        $sql = $wpdb->prepare(
            "SELECT i.*, COALESCE(b.current_stock, 0) AS current_stock
             FROM {$wpdb->prefix}banoks_inventory_items i
             LEFT JOIN {$wpdb->prefix}banoks_inventory_balances b ON b.inventory_item_id = i.id AND b.location_key = %s
             WHERE i.is_active = 1
             AND (
                 COALESCE(b.current_stock, 0) <= 0
                 OR (
                     i.low_stock_threshold > 0
                     AND COALESCE(b.current_stock, 0) <= i.low_stock_threshold
                 )
             )
             ORDER BY
                 CASE WHEN COALESCE(b.current_stock, 0) <= 0 THEN 0 ELSE 1 END ASC,
                 i.item_name ASC",
            $location_key
        );

        if ( $limit > 0 ) {
            $sql .= $wpdb->prepare( ' LIMIT %d', absint( $limit ) );
        }

        $alerts = $wpdb->get_results( $sql );
        foreach ( $alerts as $alert ) {
            $alert->alert_type      = floatval( $alert->current_stock ) <= 0 ? 'out' : 'low';
            $alert->formatted_stock = $this->format_inventory_quantity( $alert->current_stock ) . ' ' . $alert->unit;
            $alert->formatted_low   = $this->format_inventory_quantity( $alert->low_stock_threshold ) . ' ' . $alert->unit;
        }

        return $alerts ? $alerts : array();
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
        global $wpdb;

        $product_ids = array_values( array_filter( array_map( 'absint', (array) $product_ids ) ) );
        if ( empty( $product_ids ) ) {
            return array();
        }

        $statuses = array();
        foreach ( $product_ids as $product_id ) {
            $statuses[ $product_id ] = array(
                'has_recipe'      => false,
                'can_prepare'     => true,
                'available_stock' => null,
                'warnings'        => array(),
            );
        }

        $location_key  = $this->sanitize_stock_location_key( $location_key );
        $placeholders  = implode( ', ', array_fill( 0, count( $product_ids ), '%d' ) );
        $recipe_rows   = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.product_id, r.quantity_used, COALESCE(r.applies_to, 'all') AS applies_to, i.item_name, i.unit, COALESCE(b.current_stock, 0) AS current_stock, i.is_active
                 FROM {$wpdb->prefix}banoks_product_recipes r
                 LEFT JOIN {$wpdb->prefix}banoks_inventory_items i ON r.inventory_item_id = i.id
                 LEFT JOIN {$wpdb->prefix}banoks_inventory_balances b ON b.inventory_item_id = i.id AND b.location_key = %s
                 WHERE r.product_id IN ({$placeholders})
                 ORDER BY r.product_id ASC, r.id ASC",
                array_merge( array( $location_key ), $product_ids )
            )
        );

        foreach ( $recipe_rows as $recipe ) {
            $product_id = absint( $recipe->product_id );
            if ( ! isset( $statuses[ $product_id ] ) ) {
                continue;
            }

            $statuses[ $product_id ]['has_recipe'] = true;
            $quantity_needed = floatval( $recipe->quantity_used );
            $current_stock   = null === $recipe->current_stock ? 0 : floatval( $recipe->current_stock );
            $item_name       = ! empty( $recipe->item_name ) ? $recipe->item_name : 'Deleted ingredient';
            $unit            = ! empty( $recipe->unit ) ? $recipe->unit : '';
            $condition_label = $this->get_recipe_condition_label( $recipe->applies_to );

            if ( empty( $recipe->is_active ) ) {
                $statuses[ $product_id ]['can_prepare'] = false;
                $statuses[ $product_id ]['warnings'][]  = $item_name . ' is inactive for ' . $condition_label . '.';
                $statuses[ $product_id ]['available_stock'] = 0;
                continue;
            }

            if ( $quantity_needed > 0 ) {
                $available_stock = floor( $current_stock / $quantity_needed );
                if ( null === $statuses[ $product_id ]['available_stock'] || $available_stock < $statuses[ $product_id ]['available_stock'] ) {
                    $statuses[ $product_id ]['available_stock'] = max( 0, intval( $available_stock ) );
                }
            }

            if ( $quantity_needed > 0 && $current_stock < $quantity_needed ) {
                $statuses[ $product_id ]['can_prepare'] = false;
                $statuses[ $product_id ]['warnings'][]  = $condition_label . ': ' . $item_name . ' needs ' . $this->format_inventory_quantity( $quantity_needed ) . ' ' . $unit . ', has ' . $this->format_inventory_quantity( $current_stock ) . ' ' . $unit . '.';
            }
        }

        return $statuses;
    }
}