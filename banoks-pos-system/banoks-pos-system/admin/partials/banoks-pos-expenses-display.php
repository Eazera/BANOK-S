<?php
/**
 * Requests management page.
 *
 * @link       https://banoks.com
 * @since      1.0.0
 * @package    Banoks_POS
 * @subpackage Banoks_POS/admin/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$is_owner = current_user_can( 'manage_options' );
$request_labels = array(
    'expense_request'             => 'Expense Request',
    'stock_purchase_request'      => 'Stock Purchase',
    'production_transfer_request' => 'Production Transfer',
);
$branch_labels = array(
    'manukan_branch' => 'Manukan Branch',
    'production'     => 'Production',
);
$request_branch_label = function ( $request ) use ( $branch_labels ) {
    $branch_key = ! empty( $request->branch_key ) ? $request->branch_key : 'manukan_branch';

    return isset( $branch_labels[ $branch_key ] ) ? $branch_labels[ $branch_key ] : ucwords( str_replace( '_', ' ', $branch_key ) );
};
$request_description = function ( $request ) {
    return ! empty( $request->description ) ? $request->description : '-';
};
$request_item_quantity_label = function ( $request ) {
    $parts = array();

    if ( ! empty( $request->item_name ) ) {
        $parts[] = $request->item_name;
    }

    if ( floatval( $request->quantity ) > 0 ) {
        $quantity = rtrim( rtrim( number_format( floatval( $request->quantity ), 3, '.', '' ), '0' ), '.' );
        $parts[]  = $quantity . ( ! empty( $request->unit ) ? ' ' . $request->unit : '' );
    }

    return implode( ' - ', array_filter( $parts ) );
};
$request_amount_label = function ( $request ) {
    if ( 'production_transfer_request' === $request->request_type || floatval( $request->estimated_cost ) <= 0 ) {
        return '-';
    }

    $label = '&#8369;' . number_format( floatval( $request->estimated_cost ), 2 );

    return 'stock_purchase_request' === $request->request_type ? $label . ' / unit' : $label;
};
$requester_label = function ( $request ) {
    return ! empty( $request->requester_name ) ? $request->requester_name : 'User #' . $request->requested_by;
};
$request_date_from = isset( $request_date_from ) ? $request_date_from : '';
$request_date_to   = isset( $request_date_to ) ? $request_date_to : '';
$request_type_filter = isset( $request_type_filter ) ? $request_type_filter : '';
$filter_requests = function ( $requests ) use ( $request_date_from, $request_date_to, $request_type_filter ) {
    if ( empty( $request_date_from ) && empty( $request_date_to ) && empty( $request_type_filter ) ) {
        return $requests;
    }

    return array_filter(
        $requests,
        function ( $request ) use ( $request_date_from, $request_date_to, $request_type_filter ) {
            $request_date = ! empty( $request->created_at ) ? date( 'Y-m-d', strtotime( $request->created_at ) ) : '';

            return ( empty( $request_date_from ) || $request_date >= $request_date_from )
                && ( empty( $request_date_to ) || $request_date <= $request_date_to )
                && ( empty( $request_type_filter ) || $request->request_type === $request_type_filter );
        }
    );
};
$render_request_filter_modal = function ( $modal_id ) use ( $request_labels, $request_date_from, $request_date_to, $request_type_filter ) {
    ?>
    <div class="banoks-admin-edit-modal banoks-request-filter-modal" id="<?php echo esc_attr( $modal_id ); ?>" aria-hidden="true">
        <div class="banoks-admin-edit-dialog" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr( $modal_id ); ?>-title">
            <div class="banoks-admin-edit-header">
                <div>
                    <h2 id="<?php echo esc_attr( $modal_id ); ?>-title">Filter Request Log</h2>
                    <p>Choose a date range and transaction type for the request log.</p>
                </div>
                <button type="button" class="banoks-admin-edit-close" aria-label="Close filter">&times;</button>
            </div>
            <form class="banoks-request-filter-form">
                <div class="banoks-request-filter-grid">
                    <label for="<?php echo esc_attr( $modal_id ); ?>-date-from">
                        Date From
                        <input type="date" id="<?php echo esc_attr( $modal_id ); ?>-date-from" name="request_date_from" value="<?php echo esc_attr( $request_date_from ); ?>">
                    </label>
                    <label for="<?php echo esc_attr( $modal_id ); ?>-date-to">
                        Date To
                        <input type="date" id="<?php echo esc_attr( $modal_id ); ?>-date-to" name="request_date_to" value="<?php echo esc_attr( $request_date_to ); ?>">
                    </label>
                    <label for="<?php echo esc_attr( $modal_id ); ?>-type">
                        Transaction Type
                        <select id="<?php echo esc_attr( $modal_id ); ?>-type" name="request_type_filter">
                            <option value="">All Transactions</option>
                            <?php foreach ( $request_labels as $request_type => $request_label ) : ?>
                                <option value="<?php echo esc_attr( $request_type ); ?>" <?php selected( $request_type_filter, $request_type ); ?>><?php echo esc_html( $request_label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <div class="modal-actions">
                    <button type="button" class="button banoks-request-filter-clear">Clear</button>
                    <button type="button" class="button banoks-admin-edit-cancel">Cancel</button>
                    <button type="submit" class="button button-primary">Apply Filter</button>
                </div>
            </form>
        </div>
    </div>
    <?php
};
$render_request_history_table = function ( $requests, $empty_message, $table_id ) use ( $request_labels, $request_branch_label, $request_description, $request_item_quantity_label, $request_amount_label, $requester_label ) {
    ?>
    <div class="banoks-stock-table-scroll">
        <table class="widefat striped banoks-expenses-table banoks-request-history-table" id="<?php echo esc_attr( $table_id ); ?>">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Branch</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Item / Quantity</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Requested by</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $requests as $request ) : ?>
                    <?php
                    $modal_id       = $table_id . '-detail-' . absint( $request->id );
                    $request_type   = isset( $request_labels[ $request->request_type ] ) ? $request_labels[ $request->request_type ] : ucwords( str_replace( '_', ' ', $request->request_type ) );
                    $item_quantity  = $request_item_quantity_label( $request );
                    $amount_label   = $request_amount_label( $request );
                    $status_label   = ucwords( str_replace( '_', ' ', $request->request_status ) );
                    ?>
                    <tr
                        class="banoks-request-history-row banoks-open-owner-request"
                        data-target="#<?php echo esc_attr( $modal_id ); ?>"
                        data-request-date="<?php echo esc_attr( ! empty( $request->created_at ) ? date( 'Y-m-d', strtotime( $request->created_at ) ) : '' ); ?>"
                        data-request-type="<?php echo esc_attr( $request->request_type ); ?>"
                    >
                        <td><?php echo esc_html( mysql2date( 'M j, Y g:i A', $request->created_at ) ); ?></td>
                        <td><?php echo esc_html( $request_branch_label( $request ) ); ?></td>
                        <td><?php echo esc_html( $request_type ); ?></td>
                        <td><?php echo esc_html( $request_description( $request ) ); ?></td>
                        <td><?php echo esc_html( $item_quantity ? $item_quantity : '-' ); ?></td>
                        <td><?php echo wp_kses_post( $amount_label ); ?></td>
                        <td><span class="category-pill"><?php echo esc_html( $status_label ); ?></span></td>
                        <td><?php echo esc_html( $requester_label( $request ) ); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ( empty( $requests ) ) : ?>
                    <tr class="banoks-request-history-empty"><td colspan="8"><?php echo esc_html( $empty_message ); ?></td></tr>
                    <tr class="banoks-request-history-no-results" style="display:none;"><td colspan="8">No request transactions match the selected filters.</td></tr>
                <?php endif; ?>
                <?php if ( ! empty( $requests ) ) : ?>
                    <tr class="banoks-request-history-no-results" style="display:none;"><td colspan="8">No request transactions match the selected filters.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php foreach ( $requests as $request ) : ?>
        <?php
        $modal_id      = $table_id . '-detail-' . absint( $request->id );
        $request_type  = isset( $request_labels[ $request->request_type ] ) ? $request_labels[ $request->request_type ] : ucwords( str_replace( '_', ' ', $request->request_type ) );
        $item_quantity = $request_item_quantity_label( $request );
        $amount_label  = $request_amount_label( $request );
        ?>
        <div class="banoks-admin-edit-modal banoks-request-detail-modal" id="<?php echo esc_attr( $modal_id ); ?>" aria-hidden="true">
            <div class="banoks-admin-edit-dialog" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr( $modal_id ); ?>-title">
                <div class="banoks-admin-edit-header">
                    <div>
                        <h2 id="<?php echo esc_attr( $modal_id ); ?>-title">Transaction Details</h2>
                        <p><?php echo esc_html( $request_type ); ?></p>
                    </div>
                    <button type="button" class="banoks-admin-edit-close" aria-label="Close transaction details">&times;</button>
                </div>
                <div class="banoks-owner-request-detail-grid">
                    <div>
                        <span>Date</span>
                        <strong><?php echo esc_html( mysql2date( 'M j, Y g:i A', $request->created_at ) ); ?></strong>
                    </div>
                    <div>
                        <span>Branch</span>
                        <strong><?php echo esc_html( $request_branch_label( $request ) ); ?></strong>
                    </div>
                    <div>
                        <span>Status</span>
                        <strong><?php echo esc_html( ucwords( str_replace( '_', ' ', $request->request_status ) ) ); ?></strong>
                    </div>
                    <div>
                        <span>Requested By</span>
                        <strong><?php echo esc_html( $requester_label( $request ) ); ?></strong>
                    </div>
                    <div>
                        <span>Item / Quantity</span>
                        <strong><?php echo esc_html( $item_quantity ? $item_quantity : '-' ); ?></strong>
                    </div>
                    <div>
                        <span><?php echo 'stock_purchase_request' === $request->request_type ? 'Cost Per Unit' : 'Amount'; ?></span>
                        <strong><?php echo wp_kses_post( $amount_label ); ?></strong>
                    </div>
                </div>
                <div class="banoks-owner-request-copy">
                    <label>Description</label>
                    <p><?php echo esc_html( $request_description( $request ) ); ?></p>
                </div>
                <?php if ( ! empty( $request->note ) ) : ?>
                    <div class="banoks-owner-request-copy">
                        <label>Notes</label>
                        <p><?php echo esc_html( $request->note ); ?></p>
                    </div>
                <?php endif; ?>
                <?php if ( ! empty( $request->decision_note ) ) : ?>
                    <div class="banoks-owner-request-copy">
                        <label>Decision Note</label>
                        <p><?php echo esc_html( $request->decision_note ); ?></p>
                    </div>
                <?php endif; ?>
                <div class="modal-actions">
                    <button type="button" class="button button-primary banoks-admin-edit-cancel">Close</button>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php
};
?>

<div class="wrap banoks-pos-admin banoks-pos-page banoks-expenses-page <?php echo $is_owner ? 'banoks-requests-owner-page' : 'banoks-requests-worker-page'; ?>">
    <div class="products-header">
        <div class="header-info">
            <h1>Requests</h1>
            <?php if ( $is_owner ) : ?>
                <p>Review worker requests, approve stock or expense actions, and monitor recent decisions.</p>
            <?php else : ?>
                <p>Submit requests for owner approval and review your recent request status.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ( ! empty( $message ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
    <?php endif; ?>

    <?php if ( ! empty( $error ) ) : ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ); ?></p></div>
    <?php endif; ?>

    <?php if ( $is_owner ) : ?>
        <div class="banoks-request-overview">
            <div>
                <span>Pending</span>
                <strong><?php echo esc_html( number_format_i18n( count( $pending_requests ) ) ); ?></strong>
            </div>
            <div>
                <span>Recent</span>
                <strong><?php echo esc_html( number_format_i18n( count( $recent_requests ) ) ); ?></strong>
            </div>
            <div>
                <span>Transactions</span>
                <strong><?php echo esc_html( number_format_i18n( count( $recent_requests ) ) ); ?></strong>
            </div>
        </div>

        <section class="banoks-stock-panel banoks-owner-approval-panel banoks-request-review-panel">
            <div class="banoks-request-section-header">
                <div>
                    <h2>Pending Requests</h2>
                    <p>Review each request before it affects stock or finance.</p>
                </div>
            </div>
            <?php if ( empty( $pending_requests ) ) : ?>
                <p class="empty-msg">No pending requests.</p>
            <?php else : ?>
                <div class="banoks-request-list">
                    <?php foreach ( $pending_requests as $request ) : ?>
                        <?php
                        $request_type_label = isset( $request_labels[ $request->request_type ] ) ? $request_labels[ $request->request_type ] : $request->request_type;
                        $request_quantity   = floatval( $request->quantity ) > 0 ? rtrim( rtrim( number_format( floatval( $request->quantity ), 3, '.', '' ), '0' ), '.' ) . ' ' . $request->unit : '';
                        $requester_name     = $request->requester_name ? $request->requester_name : 'User #' . $request->requested_by;
                        ?>
                        <button type="button" class="banoks-request-row banoks-open-owner-request" data-target="#banoks-expenses-owner-request-<?php echo esc_attr( $request->id ); ?>">
                            <span class="banoks-request-row-type"><?php echo esc_html( $request_type_label ); ?></span>
                            <span class="banoks-request-row-content">
                                <span class="banoks-request-row-main"><?php echo esc_html( $request->description ); ?></span>
                                <?php if ( ! empty( $request->item_name ) ) : ?>
                                    <span class="banoks-request-row-item"><?php echo esc_html( $request->item_name . ( $request_quantity ? ' - ' . $request_quantity : '' ) ); ?></span>
                                <?php else : ?>
                                    <span class="banoks-request-row-item"><?php echo esc_html( $requester_name ); ?></span>
                                <?php endif; ?>
                            </span>
                            <span class="banoks-request-row-meta"><?php echo esc_html( mysql2date( 'M j, g:i A', $request->created_at ) ); ?></span>
                            <span class="banoks-request-row-action">View</span>
                        </button>

                        <div class="banoks-admin-edit-modal banoks-owner-request-modal" id="banoks-expenses-owner-request-<?php echo esc_attr( $request->id ); ?>" aria-hidden="true">
                            <div class="banoks-admin-edit-dialog" role="dialog" aria-modal="true" aria-labelledby="banoks-expenses-owner-request-title-<?php echo esc_attr( $request->id ); ?>">
                                <div class="banoks-admin-edit-header">
                                    <h2 id="banoks-expenses-owner-request-title-<?php echo esc_attr( $request->id ); ?>">Request Details</h2>
                                    <button type="button" class="banoks-admin-edit-close" aria-label="Close request details">&times;</button>
                                </div>

                                <div class="banoks-owner-request-detail-grid">
                                    <div>
                                        <span>Type</span>
                                        <strong><?php echo esc_html( $request_type_label ); ?></strong>
                                    </div>
                                    <div>
                                        <span>Requested By</span>
                                        <strong><?php echo esc_html( $requester_name ); ?></strong>
                                    </div>
                                    <div>
                                        <span>Date</span>
                                        <strong><?php echo esc_html( mysql2date( 'M j, Y g:i A', $request->created_at ) ); ?></strong>
                                    </div>
                                    <?php if ( ! empty( $request->item_name ) ) : ?>
                                        <div>
                                            <span>Item / Quantity</span>
                                            <strong><?php echo esc_html( $request->item_name . ( $request_quantity ? ' - ' . $request_quantity : '' ) ); ?></strong>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ( floatval( $request->estimated_cost ) > 0 ) : ?>
                                        <div>
                                            <span><?php echo 'stock_purchase_request' === $request->request_type ? 'Cost Per Unit' : 'Estimated Amount'; ?></span>
                                            <strong>&#8369;<?php echo esc_html( number_format( floatval( $request->estimated_cost ), 2 ) ); ?></strong>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="banoks-owner-request-copy">
                                    <label>Description / Reason</label>
                                    <p><?php echo esc_html( $request->description ); ?></p>
                                </div>
                                <?php if ( ! empty( $request->note ) ) : ?>
                                    <div class="banoks-owner-request-copy">
                                        <label>Notes</label>
                                        <p><?php echo esc_html( $request->note ); ?></p>
                                    </div>
                                <?php endif; ?>

                                <form method="post" class="banoks-request-decision-form">
                                    <?php wp_nonce_field( 'banoks_owner_request_action' ); ?>
                                    <input type="hidden" name="banoks_owner_request_action" value="1">
                                    <input type="hidden" name="request_id" value="<?php echo esc_attr( $request->id ); ?>">
                                    <label for="expenses-decision-note-<?php echo esc_attr( $request->id ); ?>">Decision Note</label>
                                    <textarea id="expenses-decision-note-<?php echo esc_attr( $request->id ); ?>" name="decision_note" rows="3" placeholder="Required for rejection. Optional for approval."></textarea>
                                    <div class="modal-actions">
                                        <button type="button" class="button banoks-admin-edit-cancel">Close</button>
                                        <button type="submit" name="decision" value="rejected" class="button">Reject</button>
                                        <button type="submit" name="decision" value="approved" class="button button-primary">Approve</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php $overall_requests = $recent_requests; ?>

        <section class="banoks-stock-panel banoks-owner-approval-panel banoks-request-history-panel">
            <div class="banoks-request-section-header">
                <div>
                    <h2>Request Log</h2>
                    <p>Stock purchase requests, expense requests, and production transfer requests.</p>
                </div>
                <button type="button" class="button banoks-finance-filter-trigger banoks-request-filter-trigger banoks-open-owner-request" data-target="#banoks-owner-request-filter-modal">
                    Filter
                </button>
            </div>
            <?php $render_request_history_table( $overall_requests, 'No request transactions found.', 'banoks-owner-overall-transactions' ); ?>
            <?php $render_request_filter_modal( 'banoks-owner-request-filter-modal' ); ?>
        </section>
    <?php else : ?>
        <div class="banoks-expense-form-panel">
            <form method="post" class="banoks-expense-form" id="banoks-expense-form">
                <?php wp_nonce_field( 'banoks_pos_expense_action' ); ?>
                <input type="hidden" name="banoks_pos_save_expense" value="1">

                <div class="expense-field">
                    <label for="request-type">Request Type</label>
                    <select id="request-type" name="request_type" required>
                        <option value="expense_request">Expense Request</option>
                        <option value="stock_purchase_request">Stock Purchase Request</option>
                        <option value="production_transfer_request">Production Stock Transfer Request</option>
                    </select>
                </div>

                <div class="expense-field">
                    <label for="expense-description">Description / Reason</label>
                    <input type="text" id="expense-description" name="description" required>
                </div>

                <div class="expense-field banoks-request-stock-field">
                    <label for="request-inventory-item">Inventory Item</label>
                    <select id="request-inventory-item" name="inventory_item_id">
                        <option value="">Select item</option>
                        <?php foreach ( $inventory_items as $inventory_item ) : ?>
                            <option value="<?php echo esc_attr( $inventory_item->id ); ?>" data-unit="<?php echo esc_attr( $inventory_item->unit ); ?>"><?php echo esc_html( $inventory_item->item_name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="expense-field banoks-request-stock-field">
                    <label for="request-quantity">Quantity</label>
                    <input type="number" id="request-quantity" name="quantity" min="0.001" step="0.001" inputmode="decimal">
                </div>

                <div class="expense-field banoks-request-stock-field">
                    <label for="request-unit">Unit</label>
                    <select id="request-unit" name="unit">
                        <?php foreach ( $unit_options as $unit_key => $unit_label ) : ?>
                            <option value="<?php echo esc_attr( $unit_key ); ?>"><?php echo esc_html( $unit_label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="expense-field banoks-request-amount-field">
                    <label for="expense-amount" id="expense-amount-label">Estimated Amount</label>
                    <input type="number" id="expense-amount" name="amount" min="0" step="0.01" inputmode="decimal">
                </div>

                <div class="expense-field">
                    <label for="expense-date">Date</label>
                    <input type="date" id="expense-date" name="date" value="<?php echo esc_attr( $expense_form_date ); ?>" required>
                </div>

                <div class="expense-field banoks-request-cash-source-field">
                    <label for="expense-cash-source">Requested Cash Source</label>
                    <select id="expense-cash-source" name="cash_source" required>
                        <?php foreach ( $cash_source_options as $source_key => $source_label ) : ?>
                            <option value="<?php echo esc_attr( $source_key ); ?>" <?php selected( 'store_cash', $source_key ); ?>><?php echo esc_html( $source_label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="expense-field">
                    <label for="request-note">Notes</label>
                    <textarea id="request-note" name="note" rows="3"></textarea>
                </div>

                <button type="submit" class="button button-primary">Submit Request</button>
            </form>
        </div>

        <?php $overall_requests = $my_requests; ?>

        <div class="banoks-expenses-list-panel">
            <div class="banoks-expenses-list-header">
                <div>
                    <h2>Request Log</h2>
                    <p>Stock purchase requests, expense requests, and production transfer requests.</p>
                </div>
                <button type="button" class="button banoks-finance-filter-trigger banoks-request-filter-trigger banoks-open-owner-request" data-target="#banoks-worker-request-filter-modal">
                    Filter
                </button>
            </div>

            <?php $render_request_history_table( $overall_requests, 'No request transactions submitted yet.', 'banoks-worker-overall-transactions' ); ?>
            <?php $render_request_filter_modal( 'banoks-worker-request-filter-modal' ); ?>
        </div>
    <?php endif; ?>
</div>
