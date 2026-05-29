<?php
/**
 * Stock management admin methods for Banoks POS.
 *
 * @package Banoks_POS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Banoks_POS_Admin_Stock {
    public function display_stock_management_page() {
        global $wpdb;

        $this->display_admin_header();
        $this->maybe_update_products_schema();

        $items_table      = $wpdb->prefix . 'banoks_inventory_items';
        $balances_table   = $wpdb->prefix . 'banoks_inventory_balances';
        $movements_table  = $wpdb->prefix . 'banoks_inventory_movements';
        $repository       = new Banoks_POS_Repository();
        $message          = '';
        $message_type     = 'updated';
        $unit_options     = $this->get_stock_unit_options();
        $stock_locations  = $this->get_stock_location_options();
        $action           = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list';
        $movement_options = array(
            'stock_in'          => 'Stock In',
            'transfer_in'       => 'Branch Stock Added',
            'transfer_out'      => 'Production Stock Transferred',
            'recipe_usage'      => 'Product Usage',
            'recipe_restore'    => 'Cancelled Order Stock Return',
            'manual_adjustment' => 'Manual Adjustment',
            'usage'             => 'Legacy Usage',
            'waste'             => 'Legacy Waste',
            'correction'        => 'Legacy Correction',
        );
        $movement_filter_options = array(
            'stock_in'          => $movement_options['stock_in'],
            'transfer_in'       => $movement_options['transfer_in'],
            'transfer_out'      => $movement_options['transfer_out'],
            'recipe_usage'      => $movement_options['recipe_usage'],
            'recipe_restore'    => $movement_options['recipe_restore'],
            'manual_adjustment' => $movement_options['manual_adjustment'],
        );
        $cash_source_options = $this->get_cash_source_options();
        if ( 'deactivate' === $action && isset( $_GET['id'] ) ) {
            $item_id = absint( $_GET['id'] );
            check_admin_referer( 'banoks_deactivate_inventory_' . $item_id );
            $wpdb->update(
                $items_table,
                array(
                    'is_active'  => 0,
                    'updated_at' => current_time( 'mysql' ),
                ),
                array( 'id' => $item_id ),
                array( '%d', '%s' ),
                array( '%d' )
            );
            $message = 'Inventory item deactivated.';
            $action  = 'list';
        }

        if ( isset( $_POST['banoks_save_inventory_item'] ) ) {
            check_admin_referer( 'banoks_inventory_item_action' );

            $item_id = isset( $_POST['inventory_item_id'] ) ? absint( $_POST['inventory_item_id'] ) : 0;
            $unit    = isset( $_POST['unit'] ) ? sanitize_key( wp_unslash( $_POST['unit'] ) ) : 'pcs';
            if ( ! isset( $unit_options[ $unit ] ) ) {
                $unit = 'pcs';
            }

            $data = array(
                'item_name'           => isset( $_POST['item_name'] ) ? sanitize_text_field( wp_unslash( $_POST['item_name'] ) ) : '',
                'category'            => isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : 'Ingredients',
                'unit'                => $unit,
                'unit_cost'           => isset( $_POST['unit_cost'] ) ? max( 0, floatval( wp_unslash( $_POST['unit_cost'] ) ) ) : 0,
                'low_stock_threshold' => isset( $_POST['low_stock_threshold'] ) ? max( 0, floatval( wp_unslash( $_POST['low_stock_threshold'] ) ) ) : 0,
                'is_active'           => ! empty( $_POST['is_active'] ) ? 1 : 0,
                'updated_at'          => current_time( 'mysql' ),
            );

            if ( '' === $data['item_name'] ) {
                $message      = 'Please enter an inventory item name.';
                $message_type = 'error';
            }

            if ( '' === trim( $data['category'] ) ) {
                $data['category'] = 'Ingredients';
            }

            if ( 'error' !== $message_type ) {
                if ( $item_id ) {
                    $updated = $wpdb->update(
                        $items_table,
                        $data,
                        array( 'id' => $item_id ),
                        array( '%s', '%s', '%s', '%f', '%f', '%d', '%s' ),
                        array( '%d' )
                    );

                    if ( false === $updated ) {
                        $message      = 'Could not update inventory item.';
                        $message_type = 'error';
                    } else {
                        $message = 'Inventory item updated.';
                        $action  = 'list';
                    }
                } else {
                    $data['created_at'] = current_time( 'mysql' );
                    $data['current_stock'] = 0;
                    $inserted = $wpdb->insert(
                        $items_table,
                        $data,
                        array( '%s', '%s', '%s', '%f', '%f', '%d', '%s', '%s', '%f' )
                    );

                    if ( false === $inserted ) {
                        $message      = 'Could not add inventory item.';
                        $message_type = 'error';
                    } else {
                        $item_id = $wpdb->insert_id;
                        $this->set_inventory_location_stock( $item_id, Banoks_POS_Repository::STOCK_LOCATION_PRODUCTION, 0 );
                        $this->set_inventory_location_stock( $item_id, Banoks_POS_Repository::STOCK_LOCATION_MANUKAN, 0 );
                        $message = 'Inventory item added.';
                        $action  = 'list';
                    }
                }
            }
        }

        if ( isset( $_POST['banoks_adjust_inventory_stock'] ) ) {
            check_admin_referer( 'banoks_inventory_adjust_action' );

            $item_id       = isset( $_POST['inventory_item_id'] ) ? absint( $_POST['inventory_item_id'] ) : 0;
            $raw_location_key = isset( $_POST['movement_location_key'] ) ? sanitize_key( wp_unslash( $_POST['movement_location_key'] ) ) : '';
            $movement_type = isset( $_POST['movement_type'] ) ? sanitize_key( wp_unslash( $_POST['movement_type'] ) ) : 'stock_in';
            $quantity      = isset( $_POST['quantity'] ) ? max( 0, floatval( wp_unslash( $_POST['quantity'] ) ) ) : 0;
            $posted_unit_cost = isset( $_POST['movement_unit_cost'] ) ? max( 0, floatval( wp_unslash( $_POST['movement_unit_cost'] ) ) ) : 0;
            $unit_cost     = $posted_unit_cost;
            $note          = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
            $affects_cash_balance = ! empty( $_POST['affects_cash_balance'] ) ? 1 : 0;
            $cash_source   = isset( $_POST['movement_cash_source'] ) ? $this->sanitize_cash_source( wp_unslash( $_POST['movement_cash_source'] ) ) : 'store_cash';
            $stock_movement_action = isset( $_POST['stock_movement_action'] ) ? sanitize_key( wp_unslash( $_POST['stock_movement_action'] ) ) : '';
            $location_key  = 'add_branch_stock' === $stock_movement_action ? $this->sanitize_stock_location_key( $raw_location_key ) : Banoks_POS_Repository::STOCK_LOCATION_PRODUCTION;

            $manual_movement_options = array( 'stock_in' );
            if ( ! $item_id || ! isset( $movement_options[ $movement_type ] ) || ! in_array( $movement_type, $manual_movement_options, true ) || $quantity <= 0 ) {
                $message      = 'Please choose an item, movement type, and quantity.';
                $message_type = 'error';
            } else {
                $item = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM $items_table WHERE id = %d",
                        $item_id
                    )
                );

                if ( ! $item ) {
                    $message      = 'Inventory item not found.';
                    $message_type = 'error';
                } else {
                    $old_stock = $this->get_inventory_location_stock( $item_id, $location_key );
                    $new_stock = in_array( $movement_type, array( 'usage', 'waste' ), true ) ? $old_stock - $quantity : $old_stock + $quantity;
                    if ( 0 === $unit_cost ) {
                        $unit_cost = isset( $item->unit_cost ) ? floatval( $item->unit_cost ) : 0;
                    }

                    if ( 'add_branch_stock' === $stock_movement_action ) {
                        $production_stock = $this->get_inventory_location_stock( $item_id, Banoks_POS_Repository::STOCK_LOCATION_PRODUCTION );
                        $branch_stock     = $this->get_inventory_location_stock( $item_id, $location_key );

                        if ( '' === $raw_location_key || ! isset( $stock_locations[ $raw_location_key ] ) || Banoks_POS_Repository::STOCK_LOCATION_PRODUCTION === $location_key ) {
                            $message      = 'Please choose a branch for branch stock.';
                            $message_type = 'error';
                        } elseif ( $production_stock < $quantity ) {
                            $message      = 'Production stock is not enough for this branch stock transfer.';
                            $message_type = 'error';
                        } else {
                            $wpdb->query( 'START TRANSACTION' );
                            $updated_production = $this->update_inventory_location_stock_if_current( $item_id, Banoks_POS_Repository::STOCK_LOCATION_PRODUCTION, $production_stock, $production_stock - $quantity );
                            $updated_branch     = $this->update_inventory_location_stock_if_current( $item_id, $location_key, $branch_stock, $branch_stock + $quantity );

                            if ( false === $updated_production || 0 === $updated_production || false === $updated_branch || 0 === $updated_branch ) {
                                $wpdb->query( 'ROLLBACK' );
                                $message      = 'Stock changed while saving. Please reload and try again.';
                                $message_type = 'error';
                            } else {
                                $transfer_note = '' !== $note ? $note : 'Branch stock added from Production Inventory.';
                                $this->record_inventory_movement( $item_id, Banoks_POS_Repository::STOCK_LOCATION_PRODUCTION, $production_stock, $production_stock - $quantity, 'transfer_out', $transfer_note, $unit_cost, 0, 'store_cash' );
                                $this->record_inventory_movement( $item_id, $location_key, $branch_stock, $branch_stock + $quantity, 'transfer_in', $transfer_note, $unit_cost, 0, 'store_cash' );
                                $wpdb->query( 'COMMIT' );
                                $message = 'Branch stock added.';
                            }
                        }

                    } else {
                        $new_unit_cost = $unit_cost;
                        if ( 'stock_in' === $movement_type && Banoks_POS_Repository::STOCK_LOCATION_PRODUCTION === $location_key && $posted_unit_cost > 0 && $old_stock > 0 && $new_stock > 0 ) {
                            $new_unit_cost = ( ( $old_stock * floatval( $item->unit_cost ) ) + ( $quantity * $posted_unit_cost ) ) / $new_stock;
                        }

                        if ( $new_stock < 0 ) {
                            $message      = 'Stock cannot go below zero.';
                            $message_type = 'error';
                        } else {
                            $wpdb->query( 'START TRANSACTION' );
                            $updated = $this->update_inventory_location_stock_if_current( $item_id, $location_key, $old_stock, $new_stock );

                            if ( in_array( $movement_type, array( 'stock_in', 'correction' ), true ) ) {
                                $wpdb->update(
                                    $items_table,
                                    array(
                                        'unit_cost'  => $new_unit_cost,
                                        'updated_at' => current_time( 'mysql' ),
                                    ),
                                    array( 'id' => $item_id ),
                                    array( '%f', '%s' ),
                                    array( '%d' )
                                );
                            }

                            if ( false === $updated || 0 === $updated ) {
                                $wpdb->query( 'ROLLBACK' );
                                $message      = 'Stock changed while saving. Please reload and try again.';
                                $message_type = 'error';
                            } else {
                                $this->record_inventory_movement( $item_id, $location_key, $old_stock, $new_stock, $movement_type, $note, $unit_cost, $affects_cash_balance, $cash_source );
                                $wpdb->query( 'COMMIT' );
                                $message = 'Inventory stock updated.';
                            }
                        }
                    }
                }
            }
        }

        $edit_item = null;
        if ( 'edit' === $action && isset( $_GET['id'] ) ) {
            $edit_item = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM $items_table WHERE id = %d",
                    absint( $_GET['id'] )
                )
            );
        }

        $default_inventory_categories = array( 'Ingredients', 'Rice', 'Sauce', 'Drinks', 'Packaging', 'Supplies' );
        $saved_inventory_categories   = $wpdb->get_col( "SELECT DISTINCT category FROM $items_table WHERE category != '' ORDER BY category ASC" );
        $inventory_categories         = array();
        foreach ( array_merge( $saved_inventory_categories ? $saved_inventory_categories : array(), $default_inventory_categories ) as $category_name ) {
            $category_name = trim( sanitize_text_field( $category_name ) );
            if ( '' === $category_name ) {
                continue;
            }
            $category_key = strtolower( $category_name );
            if ( ! isset( $inventory_categories[ $category_key ] ) ) {
                $inventory_categories[ $category_key ] = $category_name;
            }
        }
        natcasesort( $inventory_categories );

        $inventory_items = $wpdb->get_results( "SELECT * FROM $items_table ORDER BY is_active DESC, item_name ASC" );
        $inventory_balances = $wpdb->get_results( "SELECT inventory_item_id, location_key, current_stock FROM $balances_table" );
        $inventory_balance_map = array();
        foreach ( $inventory_balances as $balance ) {
            $inventory_balance_map[ absint( $balance->inventory_item_id ) ][ $balance->location_key ] = floatval( $balance->current_stock );
        }
        $inventory_alerts = $repository->get_inventory_stock_alerts( 0, Banoks_POS_Repository::STOCK_LOCATION_MANUKAN );
        $movement_date_from = $this->get_request_date( 'movement_date_from', wp_date( 'Y-m-01' ) );
        $movement_date_to   = $this->get_request_date( 'movement_date_to', wp_date( 'Y-m-d' ) );
        $movement_location  = isset( $_GET['movement_location'] ) ? sanitize_key( wp_unslash( $_GET['movement_location'] ) ) : '';
        if ( '' !== $movement_location && ! isset( $stock_locations[ $movement_location ] ) ) {
            $movement_location = '';
        }
        $stock_expenses = floatval(
            $wpdb->get_var(
                "SELECT SUM(total_cost)
                 FROM $movements_table
                 WHERE affects_cash_balance = 1
                 AND change_amount > 0"
            )
        );
        $stock_value = floatval(
            $wpdb->get_var(
                "SELECT SUM(b.current_stock * i.unit_cost)
                 FROM $balances_table b
                 INNER JOIN $items_table i ON b.inventory_item_id = i.id
                 WHERE i.is_active = 1"
            )
        );
        $item_movements = $wpdb->get_results(
            "SELECT m.*, i.item_name, i.unit
             FROM $movements_table m
             LEFT JOIN $items_table i ON m.inventory_item_id = i.id
             ORDER BY m.created_at DESC"
        );
        $overall_movement_where = 'WHERE DATE(m.created_at) BETWEEN %s AND %s';
        $overall_movement_args  = array( $movement_date_from, $movement_date_to );
        if ( '' !== $movement_location ) {
            $overall_movement_where .= ' AND m.location_key = %s';
            $overall_movement_args[] = $movement_location;
        }
        $overall_stock_movements = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT m.*, i.item_name, i.unit
                 FROM $movements_table m
                 LEFT JOIN $items_table i ON m.inventory_item_id = i.id
                 $overall_movement_where
                 ORDER BY m.created_at DESC, m.id DESC
                 LIMIT 300",
                $overall_movement_args
            )
        );

        include_once dirname( __DIR__ ) . '/partials/banoks-pos-stock-management-display.php';
    }

    private function get_inventory_location_stock( $item_id, $location_key ) {
        global $wpdb;

        $location_key = $this->sanitize_stock_location_key( $location_key );
        $stock = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT current_stock FROM {$wpdb->prefix}banoks_inventory_balances WHERE inventory_item_id = %d AND location_key = %s",
                absint( $item_id ),
                $location_key
            )
        );

        if ( null === $stock ) {
            $this->set_inventory_location_stock( $item_id, $location_key, 0 );
            return 0;
        }

        return floatval( $stock );
    }

    private function set_inventory_location_stock( $item_id, $location_key, $stock ) {
        global $wpdb;

        $location_key = $this->sanitize_stock_location_key( $location_key );
        $stock        = max( 0, floatval( $stock ) );
        $table        = $wpdb->prefix . 'banoks_inventory_balances';
        $exists       = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM $table WHERE inventory_item_id = %d AND location_key = %s",
                absint( $item_id ),
                $location_key
            )
        );

        if ( $exists ) {
            $result = $wpdb->update(
                $table,
                array( 'current_stock' => $stock, 'updated_at' => current_time( 'mysql' ) ),
                array( 'inventory_item_id' => absint( $item_id ), 'location_key' => $location_key ),
                array( '%f', '%s' ),
                array( '%d', '%s' )
            );
            if ( Banoks_POS_Repository::STOCK_LOCATION_PRODUCTION === $location_key ) {
                $wpdb->update(
                    $wpdb->prefix . 'banoks_inventory_items',
                    array( 'current_stock' => $stock, 'updated_at' => current_time( 'mysql' ) ),
                    array( 'id' => absint( $item_id ) ),
                    array( '%f', '%s' ),
                    array( '%d' )
                );
            }
            return $result;
        }

        $result = $wpdb->insert(
            $table,
            array(
                'inventory_item_id' => absint( $item_id ),
                'location_key'      => $location_key,
                'current_stock'     => $stock,
                'updated_at'        => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%f', '%s' )
        );
        if ( Banoks_POS_Repository::STOCK_LOCATION_PRODUCTION === $location_key ) {
            $wpdb->update(
                $wpdb->prefix . 'banoks_inventory_items',
                array( 'current_stock' => $stock, 'updated_at' => current_time( 'mysql' ) ),
                array( 'id' => absint( $item_id ) ),
                array( '%f', '%s' ),
                array( '%d' )
            );
        }
        return $result;
    }

    private function update_inventory_location_stock_if_current( $item_id, $location_key, $old_stock, $new_stock ) {
        global $wpdb;

        $this->ensure_inventory_location_stock_row( $item_id, $location_key );
        $result = $wpdb->update(
            $wpdb->prefix . 'banoks_inventory_balances',
            array(
                'current_stock' => max( 0, floatval( $new_stock ) ),
                'updated_at'    => current_time( 'mysql' ),
            ),
            array(
                'inventory_item_id' => absint( $item_id ),
                'location_key'      => $this->sanitize_stock_location_key( $location_key ),
                'current_stock'     => floatval( $old_stock ),
            ),
            array( '%f', '%s' ),
            array( '%d', '%s', '%f' )
        );
        if ( false !== $result && Banoks_POS_Repository::STOCK_LOCATION_PRODUCTION === $this->sanitize_stock_location_key( $location_key ) ) {
            $wpdb->update(
                $wpdb->prefix . 'banoks_inventory_items',
                array( 'current_stock' => max( 0, floatval( $new_stock ) ), 'updated_at' => current_time( 'mysql' ) ),
                array( 'id' => absint( $item_id ) ),
                array( '%f', '%s' ),
                array( '%d' )
            );
        }
        return $result;
    }

    private function ensure_inventory_location_stock_row( $item_id, $location_key ) {
        global $wpdb;

        $location_key = $this->sanitize_stock_location_key( $location_key );
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}banoks_inventory_balances WHERE inventory_item_id = %d AND location_key = %s",
                absint( $item_id ),
                $location_key
            )
        );

        if ( $exists ) {
            return true;
        }

        return false !== $wpdb->insert(
            $wpdb->prefix . 'banoks_inventory_balances',
            array(
                'inventory_item_id' => absint( $item_id ),
                'location_key'      => $location_key,
                'current_stock'     => 0,
                'updated_at'        => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%f', '%s' )
        );
    }

    private function record_inventory_movement( $item_id, $location_key, $old_stock, $new_stock, $movement_type, $note = '', $unit_cost = 0, $affects_cash_balance = 0, $cash_source = 'store_cash', $source = 'manual', $source_id = '' ) {
        global $wpdb;

        $location_key  = $this->sanitize_stock_location_key( $location_key );
        $movement_type = sanitize_key( $movement_type );
        $source        = sanitize_key( $source );
        $source_id     = sanitize_text_field( $source_id );
        $change_amount = floatval( $new_stock ) - floatval( $old_stock );
        $unit_cost     = max( 0, floatval( $unit_cost ) );
        $total_cost    = abs( $change_amount ) * $unit_cost;
        $affects_cash_balance = $affects_cash_balance && $change_amount > 0 && in_array( $movement_type, array( 'stock_in', 'correction' ), true ) ? 1 : 0;
        $cash_source = $this->sanitize_cash_source( $cash_source );

        if ( 'stock_in' === $movement_type ) {
            $item = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT item_name, unit FROM {$wpdb->prefix}banoks_inventory_items WHERE id = %d",
                    absint( $item_id )
                )
            );

            $item_name      = $item && ! empty( $item->item_name ) ? $item->item_name : 'Deleted item';
            $unit           = $item && ! empty( $item->unit ) ? $item->unit : '';
            $generated_note = sprintf( 'Stock in: %s - %s.', $item_name, $this->format_stock_quantity( abs( $change_amount ), $unit ) );
            $note           = '' !== trim( (string) $note ) ? $generated_note . ' Note: ' . $note : $generated_note;
        }

        $wpdb->insert(
            $wpdb->prefix . 'banoks_inventory_movements',
            array(
                'inventory_item_id' => absint( $item_id ),
                'location_key'      => $location_key,
                'movement_type'     => $movement_type,
                'old_stock'         => floatval( $old_stock ),
                'new_stock'         => floatval( $new_stock ),
                'change_amount'     => $change_amount,
                'unit_cost'         => $unit_cost,
                'total_cost'        => $total_cost,
                'affects_cash_balance' => $affects_cash_balance,
                'cash_source'       => $cash_source,
                'source'            => '' !== $source ? $source : 'manual',
                'source_id'         => $source_id,
                'updated_by'        => get_current_user_id(),
                'note'              => sanitize_textarea_field( $note ),
                'created_at'        => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%f', '%f', '%f', '%f', '%f', '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
        );
    }

    private function format_stock_quantity( $quantity, $unit = '' ) {
        $quantity = rtrim( rtrim( number_format( floatval( $quantity ), 3, '.', '' ), '0' ), '.' );
        $unit     = trim( (string) $unit );

        return '' !== $unit ? $quantity . ' ' . $unit : $quantity;
    }

    private function get_stock_cash_expenses_for_period( $start_date, $end_date, $cash_source = '' ) {
        global $wpdb;

        $where = "affects_cash_balance = 1
                 AND change_amount > 0
                 AND movement_type = 'stock_in'
                 AND location_key = %s
                 AND DATE(created_at) BETWEEN %s AND %s";
        $args  = array( Banoks_POS_Repository::STOCK_LOCATION_MANUKAN, $start_date, $end_date );

        if ( '' !== $cash_source ) {
            $where .= ' AND cash_source = %s';
            $args[] = $this->sanitize_cash_source( $cash_source );
        }

        $total = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(total_cost)
                 FROM {$wpdb->prefix}banoks_inventory_movements
                 WHERE $where",
                $args
            )
        );

        return $total ? floatval( $total ) : 0;
    }

    private function get_stock_cash_expense_rows_for_period( $start_date, $end_date, $cash_source = '' ) {
        global $wpdb;

        $where = "m.affects_cash_balance = 1
                 AND m.change_amount > 0
                 AND m.movement_type = 'stock_in'
                 AND m.location_key = %s
                 AND DATE(m.created_at) BETWEEN %s AND %s";
        $args  = array( Banoks_POS_Repository::STOCK_LOCATION_MANUKAN, $start_date, $end_date );

        if ( '' !== $cash_source ) {
            $where .= ' AND m.cash_source = %s';
            $args[] = $this->sanitize_cash_source( $cash_source );
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT COALESCE(i.item_name, 'Deleted item') AS description,
                        m.total_cost AS amount,
                        m.cash_source,
                        m.change_amount AS quantity,
                        COALESCE(i.unit, '') AS unit,
                        COALESCE(u.display_name, '') AS created_by_name,
                        DATE(m.created_at) AS date,
                        m.created_at
                 FROM {$wpdb->prefix}banoks_inventory_movements m
                 LEFT JOIN {$wpdb->prefix}banoks_inventory_items i ON m.inventory_item_id = i.id
                 LEFT JOIN {$wpdb->prefix}banoks_requests r ON m.source = 'request' AND m.source_id = CONCAT('REQ-', r.id)
                 LEFT JOIN {$wpdb->users} u ON r.requested_by = u.ID
                 WHERE $where
                 ORDER BY m.created_at ASC",
                $args
            )
        );
    }
}
