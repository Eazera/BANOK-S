<?php
/**
 * Request and expense admin methods for Banoks POS.
 *
 * @package Banoks_POS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Banoks_POS_Admin_Requests {
    public function display_expenses_page() {
        global $wpdb;

        $this->display_admin_header();
        $this->maybe_update_products_schema();

        $table_name = $wpdb->prefix . 'banoks_expenses';
        $requests_table = $wpdb->prefix . 'banoks_requests';
        $is_owner = current_user_can( 'manage_options' );
        $message = '';
        $error = '';
        $expense_form_date = current_time( 'Y-m-d' );
        $cash_source_options = $this->get_cash_source_options();
        $unit_options = $this->get_stock_unit_options();
        $inventory_items = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}banoks_inventory_items WHERE is_active = 1 ORDER BY item_name ASC" );
        $expense_action = isset( $_GET['expense_action'] ) ? sanitize_key( wp_unslash( $_GET['expense_action'] ) ) : '';

        if ( $is_owner && isset( $_POST['banoks_owner_request_action'] ) ) {
            check_admin_referer( 'banoks_owner_request_action' );
            $result = $this->handle_owner_request_decision();
            if ( isset( $result['error'] ) ) {
                $error = $result['error'];
            } else {
                $message = $result['message'];
            }
        }

        if ( 'delete' === $expense_action && isset( $_GET['expense_id'] ) && $is_owner ) {
            $expense_id = absint( $_GET['expense_id'] );
            check_admin_referer( 'delete_expense_' . $expense_id );

            $deleted = $wpdb->delete( $table_name, array( 'expense_id' => $expense_id ), array( '%d' ) );

            if ( false === $deleted ) {
                $error = 'Error: Could not delete expense. ' . $wpdb->last_error;
            } else {
                $message = 'Expense deleted successfully.';
            }
        }

        if ( ! $is_owner && isset( $_POST['banoks_pos_save_expense'] ) ) {
            check_admin_referer( 'banoks_pos_expense_action' );

            $description = isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '';
            $amount      = isset( $_POST['amount'] ) ? floatval( wp_unslash( $_POST['amount'] ) ) : 0;
            $date        = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
            $date        = '' !== $date ? $date : current_time( 'Y-m-d' );
            $cash_source = isset( $_POST['cash_source'] ) ? $this->sanitize_cash_source( wp_unslash( $_POST['cash_source'] ) ) : 'store_cash';
            $request_type = isset( $_POST['request_type'] ) ? sanitize_key( wp_unslash( $_POST['request_type'] ) ) : 'expense_request';
            $inventory_item_id = isset( $_POST['inventory_item_id'] ) ? absint( $_POST['inventory_item_id'] ) : 0;
            $quantity = isset( $_POST['quantity'] ) ? max( 0, floatval( wp_unslash( $_POST['quantity'] ) ) ) : 0;
            $unit = '';
            $expense_form_date = $date;
            $is_stock_request = in_array( $request_type, array( 'stock_purchase_request', 'production_transfer_request' ), true );
            if ( 'production_transfer_request' === $request_type ) {
                $amount      = 0;
                $cash_source = 'store_cash';
            }

            if ( '' === $description ) {
                $error = 'Please enter a request description.';
            } elseif ( ! in_array( $request_type, array( 'expense_request', 'stock_purchase_request', 'production_transfer_request' ), true ) ) {
                $error = 'Please choose a valid request type.';
            } elseif ( $is_stock_request && ( ! $inventory_item_id || $quantity <= 0 ) ) {
                $error = 'Please choose an inventory item and quantity.';
            } elseif ( in_array( $request_type, array( 'expense_request', 'stock_purchase_request' ), true ) && $amount <= 0 ) {
                $error = 'stock_purchase_request' === $request_type ? 'Please enter a valid cost per unit.' : 'Please enter a valid estimated amount.';
            } elseif ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
                $error = 'Please select a valid request date.';
            } else {
                if ( $is_stock_request ) {
                    $unit = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT unit FROM {$wpdb->prefix}banoks_inventory_items WHERE id = %d AND is_active = 1",
                            $inventory_item_id
                        )
                    );
                    $unit = null === $unit ? '' : sanitize_key( $unit );

                    if ( null === $unit || '' === $unit ) {
                        $error = 'Please choose an active inventory item.';
                    }
                }

                if ( ! empty( $error ) ) {
                    $inserted = false;
                } else {
                    $inserted = $wpdb->insert(
                        $requests_table,
                        array(
                            'request_type'      => $request_type,
                            'request_status'    => 'pending',
                            'branch_key'        => 'manukan_branch',
                            'inventory_item_id' => $inventory_item_id,
                            'quantity'          => $quantity,
                            'unit'              => $unit,
                            'estimated_cost'    => $amount,
                            'expense_date'      => $date,
                            'description'       => $description,
                            'cash_source'       => $cash_source,
                            'note'              => isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '',
                            'requested_by'      => get_current_user_id(),
                            'created_at'        => current_time( 'mysql' ),
                            'updated_at'        => current_time( 'mysql' ),
                        ),
                        array( '%s', '%s', '%s', '%d', '%f', '%s', '%f', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
                    );
                }

                if ( false === $inserted ) {
                    if ( empty( $error ) ) {
                        $error = 'Error: Could not submit request. ' . $wpdb->last_error;
                    }
                } else {
                    $this->create_request_log( $wpdb->insert_id, '', 'pending', 'Request submitted.' );
                    $message = 'Request submitted for owner approval.';
                }
            }
        }

        $pending_requests = $is_owner ? $this->get_requests_for_owner( 'pending' ) : array();
        $recent_requests  = $is_owner ? $this->get_requests_for_owner( 'all' ) : array();
        $expense_filter_date = $this->get_request_date( 'expense_date', '' );
        $request_date_from   = $this->get_request_date( 'request_date_from', '' );
        $request_date_to     = $this->get_request_date( 'request_date_to', '' );
        $request_type_filter = isset( $_GET['request_type_filter'] ) ? sanitize_key( wp_unslash( $_GET['request_type_filter'] ) ) : '';
        if ( ! in_array( $request_type_filter, array( 'expense_request', 'stock_purchase_request', 'production_transfer_request' ), true ) ) {
            $request_type_filter = '';
        }
        $expenses = array();

        if ( $is_owner ) {
            if ( ! empty( $expense_filter_date ) ) {
                $expenses = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM $table_name WHERE date = %s ORDER BY created_at DESC LIMIT 100",
                        $expense_filter_date
                    )
                );
            } else {
                $expenses = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY date DESC, created_at DESC LIMIT 100" );
            }
        }

        $my_requests = array();
        if ( ! $is_owner ) {
            $my_requests = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT r.*, i.item_name, u.display_name AS requester_name
                     FROM $requests_table r
                     LEFT JOIN {$wpdb->prefix}banoks_inventory_items i ON r.inventory_item_id = i.id
                     LEFT JOIN {$wpdb->users} u ON r.requested_by = u.ID
                     WHERE r.requested_by = %d
                     ORDER BY r.created_at DESC
                     LIMIT 100",
                    get_current_user_id()
                )
            );
        }

        include_once dirname( __DIR__ ) . '/partials/banoks-pos-expenses-display.php';
    }

    private function get_requests_for_owner( $status = 'pending' ) {
        global $wpdb;

        $where = '';
        $args = array();
        if ( 'all' !== $status ) {
            $where = 'WHERE r.request_status = %s';
            $args[] = sanitize_key( $status );
        }

        $sql = "SELECT r.*, i.item_name, u.display_name AS requester_name
                FROM {$wpdb->prefix}banoks_requests r
                LEFT JOIN {$wpdb->prefix}banoks_inventory_items i ON r.inventory_item_id = i.id
                LEFT JOIN {$wpdb->users} u ON r.requested_by = u.ID
                $where
                ORDER BY r.created_at DESC
                LIMIT 100";

        return $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ) ) : $wpdb->get_results( $sql );
    }

    private function get_owner_request_count( $status = 'pending' ) {
        global $wpdb;

        $status = sanitize_key( $status );
        if ( '' === $status || 'all' === $status ) {
            return absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}banoks_requests" ) );
        }

        return absint(
            $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}banoks_requests WHERE request_status = %s",
                    $status
                )
            )
        );
    }

    private function create_request_log( $request_id, $old_status, $new_status, $note = '' ) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'banoks_request_logs',
            array(
                'request_id' => absint( $request_id ),
                'old_status' => sanitize_key( $old_status ),
                'new_status' => sanitize_key( $new_status ),
                'updated_by' => get_current_user_id(),
                'note'       => sanitize_textarea_field( $note ),
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%d', '%s', '%s' )
        );
    }

    private function handle_owner_request_decision() {
        global $wpdb;

        if ( ! current_user_can( 'manage_options' ) ) {
            return array( 'error' => 'Only owner/admin can approve requests.' );
        }

        $request_id = isset( $_POST['request_id'] ) ? absint( $_POST['request_id'] ) : 0;
        $decision   = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
        $note       = isset( $_POST['decision_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['decision_note'] ) ) : '';

        if ( ! $request_id || ! in_array( $decision, array( 'approved', 'rejected' ), true ) ) {
            return array( 'error' => 'Invalid request decision.' );
        }

        if ( 'rejected' === $decision && '' === $note ) {
            return array( 'error' => 'Please enter a rejection reason.' );
        }

        $request = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}banoks_requests WHERE id = %d",
                $request_id
            )
        );

        if ( ! $request || 'pending' !== $request->request_status ) {
            return array( 'error' => 'Request is no longer pending.' );
        }

        $wpdb->query( 'START TRANSACTION' );

        if ( 'approved' === $decision ) {
            if ( 'production_transfer_request' === $request->request_type ) {
                $transfer = $this->approve_production_transfer_request( $request );
                if ( isset( $transfer['error'] ) ) {
                    $wpdb->query( 'ROLLBACK' );
                    return $transfer;
                }
            } elseif ( 'stock_purchase_request' === $request->request_type ) {
                $purchase = $this->approve_stock_purchase_request( $request );
                if ( isset( $purchase['error'] ) ) {
                    $wpdb->query( 'ROLLBACK' );
                    return $purchase;
                }
            } elseif ( 'expense_request' === $request->request_type ) {
                $inserted = $wpdb->insert(
                    $wpdb->prefix . 'banoks_expenses',
                    array(
                        'description' => $request->description,
                        'amount'      => floatval( $request->estimated_cost ),
                        'date'        => $request->expense_date ? $request->expense_date : current_time( 'Y-m-d' ),
                        'branch_key'  => ! empty( $request->branch_key ) ? sanitize_key( $request->branch_key ) : 'manukan_branch',
                        'cash_source' => $this->sanitize_cash_source( $request->cash_source ),
                        'created_by'  => absint( $request->requested_by ),
                    ),
                    array( '%s', '%f', '%s', '%s', '%s', '%d' )
                );

                if ( false === $inserted ) {
                    $wpdb->query( 'ROLLBACK' );
                    return array( 'error' => 'Could not create approved expense.' );
                }
            }
        }

        $updated = $wpdb->update(
            $wpdb->prefix . 'banoks_requests',
            array(
                'request_status' => $decision,
                'decision_note'  => $note,
                'decided_by'     => get_current_user_id(),
                'decided_at'     => current_time( 'mysql' ),
                'updated_at'     => current_time( 'mysql' ),
            ),
            array( 'id' => $request_id, 'request_status' => 'pending' ),
            array( '%s', '%s', '%d', '%s', '%s' ),
            array( '%d', '%s' )
        );

        if ( false === $updated || 0 === $updated ) {
            $wpdb->query( 'ROLLBACK' );
            return array( 'error' => 'Could not update request.' );
        }

        $this->create_request_log( $request_id, 'pending', $decision, $note );
        $wpdb->query( 'COMMIT' );

        return array( 'message' => 'Request ' . $decision . ' successfully.' );
    }

    private function approve_stock_purchase_request( $request ) {
        global $wpdb;

        $item_id        = absint( $request->inventory_item_id );
        $qty            = floatval( $request->quantity );
        $purchase_unit_cost = floatval( $request->estimated_cost );
        $purchase_total = $purchase_unit_cost * $qty;

        if ( ! $item_id || $qty <= 0 || $purchase_unit_cost <= 0 ) {
            return array( 'error' => 'Stock purchase request has invalid item, quantity, or cost.' );
        }

        $item = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}banoks_inventory_items WHERE id = %d", $item_id ) );
        if ( ! $item ) {
            return array( 'error' => 'Inventory item not found.' );
        }

        $old_stock          = $this->get_inventory_location_stock( $item_id, Banoks_POS_Repository::STOCK_LOCATION_MANUKAN );
        $new_stock          = $old_stock + $qty;
        $old_unit_cost      = max( 0, floatval( $item->unit_cost ) );
        $new_unit_cost      = $purchase_unit_cost;

        if ( $old_stock > 0 && $new_stock > 0 ) {
            $new_unit_cost = ( ( $old_stock * $old_unit_cost ) + $purchase_total ) / $new_stock;
        }

        $updated_stock = $this->update_inventory_location_stock_if_current( $item_id, Banoks_POS_Repository::STOCK_LOCATION_MANUKAN, $old_stock, $new_stock );
        if ( false === $updated_stock || 0 === $updated_stock ) {
            return array( 'error' => 'Manukan branch stock changed while approving purchase. Please try again.' );
        }

        $updated_item = $wpdb->update(
            $wpdb->prefix . 'banoks_inventory_items',
            array(
                'unit_cost'  => $new_unit_cost,
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $item_id ),
            array( '%f', '%s' ),
            array( '%d' )
        );

        if ( false === $updated_item ) {
            return array( 'error' => 'Could not update inventory unit cost.' );
        }

        $request_ref = 'REQ-' . absint( $request->id );
        $note_parts  = array( 'Approved branch stock purchase request ' . $request_ref . '.' );
        if ( ! empty( $request->description ) ) {
            $note_parts[] = $request->description;
        }

        $this->record_inventory_movement(
            $item_id,
            Banoks_POS_Repository::STOCK_LOCATION_MANUKAN,
            $old_stock,
            $new_stock,
            'stock_in',
            implode( ' ', $note_parts ),
            $purchase_unit_cost,
            1,
            $this->sanitize_cash_source( $request->cash_source ),
            'request',
            $request_ref
        );

        return array( 'success' => true );
    }

    private function approve_production_transfer_request( $request ) {
        global $wpdb;

        $item_id = absint( $request->inventory_item_id );
        $qty     = floatval( $request->quantity );
        if ( ! $item_id || $qty <= 0 ) {
            return array( 'error' => 'Transfer request has invalid item or quantity.' );
        }

        $item = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}banoks_inventory_items WHERE id = %d", $item_id ) );
        if ( ! $item ) {
            return array( 'error' => 'Inventory item not found.' );
        }

        $production_stock = $this->get_inventory_location_stock( $item_id, Banoks_POS_Repository::STOCK_LOCATION_PRODUCTION );
        $branch_stock     = $this->get_inventory_location_stock( $item_id, Banoks_POS_Repository::STOCK_LOCATION_MANUKAN );

        if ( $production_stock < $qty ) {
            return array( 'error' => $item->item_name . ' has insufficient Production stock.' );
        }

        $production_new = $production_stock - $qty;
        $branch_new     = $branch_stock + $qty;

        $updated_production = $this->update_inventory_location_stock_if_current( $item_id, Banoks_POS_Repository::STOCK_LOCATION_PRODUCTION, $production_stock, $production_new );
        $updated_branch     = $this->update_inventory_location_stock_if_current( $item_id, Banoks_POS_Repository::STOCK_LOCATION_MANUKAN, $branch_stock, $branch_new );

        if ( false === $updated_production || 0 === $updated_production || false === $updated_branch || 0 === $updated_branch ) {
            return array( 'error' => 'Stock changed while approving transfer. Please try again.' );
        }

        $source_id = 'REQ-' . absint( $request->id );
        $this->record_inventory_movement( $item_id, Banoks_POS_Repository::STOCK_LOCATION_PRODUCTION, $production_stock, $production_new, 'transfer_out', 'Production transfer to Manukan Branch.', floatval( $item->unit_cost ), 0, 'store_cash', 'request', $source_id );
        $this->record_inventory_movement( $item_id, Banoks_POS_Repository::STOCK_LOCATION_MANUKAN, $branch_stock, $branch_new, 'transfer_in', 'Approved transfer from Production. ' . $source_id, floatval( $item->unit_cost ), 0, 'store_cash', 'request', $source_id );

        return array( 'success' => true );
    }
}
