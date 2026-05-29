<?php
/**
 * Business Reports View
 *
 * @link       https://banoks.com
 * @since      1.0.0
 * @package    Banoks_POS
 * @subpackage Banoks_POS/admin/partials
 * @author     Christian Fulache
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$format_report_payment_method = function ( $payment_method ) {
    $payment_method = strtolower( (string) $payment_method );

    return 'gcash' === $payment_method ? 'E-Money' : 'Cash';
};
?>

<div class="wrap banoks-pos-admin banoks-pos-page banoks-reports-page">
    <div class="reports-header">
        <form method="get" class="reports-filter-form">
            <input type="hidden" name="page" value="banoks-pos-reports">
            <div class="filter-row">
                <div class="filter-group">
                    <label>Branch:</label>
                    <select name="branch_key">
                        <?php foreach ( $active_branches as $branch ) : ?>
                            <?php $branch_key = sanitize_key( $branch->branch_key ); ?>
                            <option value="<?php echo esc_attr( $branch_key ); ?>" <?php selected( $selected_branch_key, $branch_key ); ?>>
                                <?php echo esc_html( $branch->branch_name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>From:</label>
                    <input type="date" name="start_date" value="<?php echo esc_attr( $start_date ); ?>">
                </div>
                <div class="filter-group">
                    <label>To:</label>
                    <input type="date" name="end_date" value="<?php echo esc_attr( $end_date ); ?>">
                </div>
                <div class="reports-button-group">
                    <button type="submit" class="button button-primary">Generate Report</button>
                    <a
                        href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=banoks-pos-reports&report_action=export_pdf&branch_key=' . rawurlencode( $selected_branch_key ) . '&start_date=' . rawurlencode( $start_date ) . '&end_date=' . rawurlencode( $end_date ) ), 'banoks_export_report_pdf' ) ); ?>"
                        class="button button-primary reports-print-button"
                    >Print Report</a>
                </div>
            </div>
        </form>
    </div>

    <div class="banoks-stats-grid">
        <div class="stat-card">
            <h3><?php echo esc_html( $selected_branch_name ); ?> Sales</h3>
            <p class="amount">&#8369;<?php echo esc_html( number_format( $total_sales, 2 ) ); ?></p>
            <span class="stat-label">Completed walk-in and online revenue</span>
        </div>
        <div class="stat-card">
            <h3>Total Period Expenses</h3>
            <p class="amount">&#8369;<?php echo esc_html( number_format( $total_expenses, 2 ) ); ?></p>
            <span class="stat-label">Branch expenses + branch cash stock purchases</span>
        </div>
        <div class="stat-card highlighted">
            <h3>Final Total Sales</h3>
            <p class="amount">&#8369;<?php echo esc_html( number_format( $net_profit, 2 ) ); ?></p>
            <span class="stat-label">Sales after expenses</span>
        </div>
    </div>

    <div class="reports-section">
        <h2><?php echo esc_html( $selected_branch_name ); ?> Revenue Performance Trend</h2>
        <div class="chart-container" style="position: relative; height:300px; width:100%">
            <canvas id="banoksSalesChart"></canvas>
        </div>
        <input type="hidden" id="daily-sales-data" value="<?php echo esc_attr( wp_json_encode( $daily_sales ) ); ?>">
        <input type="hidden" id="sales-trend-granularity" value="<?php echo esc_attr( $sales_trend_granularity ); ?>">
    </div>

    <div class="reports-section">
        <h2><?php echo esc_html( $selected_branch_name ); ?> Expense List</h2>
        <div class="table-wrap">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Qty</th>
                        <th>Worker</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $branch_expenses ) ) : ?>
                        <?php foreach ( $branch_expenses as $expense ) : ?>
                            <tr>
                                <td><?php echo esc_html( wp_date( 'M d, Y', strtotime( $expense->date ) ) ); ?></td>
                                <td><strong><?php echo esc_html( $expense->description ); ?></strong></td>
                                <td>
                                    <?php
                                    $expense_quantity = isset( $expense->quantity ) ? floatval( $expense->quantity ) : 0;
                                    echo $expense_quantity > 0 ? esc_html( rtrim( rtrim( number_format( $expense_quantity, 3, '.', '' ), '0' ), '.' ) . ( ! empty( $expense->unit ) ? ' ' . $expense->unit : '' ) ) : '&mdash;';
                                    ?>
                                </td>
                                <td><?php echo ! empty( $expense->created_by_name ) ? esc_html( $expense->created_by_name ) : '&mdash;'; ?></td>
                                <td>&#8369;<?php echo esc_html( number_format( floatval( $expense->amount ), 2 ) ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="5">No expenses found for this branch and period.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="reports-main-content">
        <div class="reports-section">
            <h2>Top 10 Best Selling Products in <?php echo esc_html( $selected_branch_name ); ?></h2>
            <div class="table-wrap">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>Quantity Sold</th>
                            <th>Total Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $top_products ) ) : ?>
                            <?php foreach ( $top_products as $product ) : ?>
                                <tr>
                                    <td><strong><?php echo esc_html( $product->product_name ); ?></strong></td>
                                    <td><?php echo esc_html( $product->total_qty ); ?> pcs</td>
                                    <td>&#8369;<?php echo esc_html( number_format( $product->total_revenue, 2 ) ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="3">No sales data found for this branch and period.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="reports-section banoks-transactions-report-section">
        <div class="banoks-transactions-section-header">
            <h2>All <?php echo esc_html( $selected_branch_name ); ?> Transactions</h2>
            <button type="button" class="button banoks-finance-filter-trigger banoks-open-owner-request" data-target="#banoks-report-transactions-filter-modal">
                Filter
            </button>
        </div>
        <p class="description">Showing completed and cancelled walk-in and online transactions for <?php echo esc_html( $selected_branch_name ); ?> only.</p>
        <div class="table-wrap banoks-report-transactions-scroll">
            <table class="wp-list-table widefat fixed striped banoks-report-transactions-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Total Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $report_transactions ) ) : ?>
                        <?php foreach ( $report_transactions as $transaction ) : ?>
                            <?php $items_json = wp_json_encode( $transaction->items_detail ); ?>
                            <tr
                                class="banoks-report-transaction-row"
                                tabindex="0"
                                role="button"
                                data-order-id="<?php echo esc_attr( $transaction->order_id ); ?>"
                                data-transaction-date="<?php echo esc_attr( wp_date( 'Y-m-d', strtotime( $transaction->transaction_date ) ) ); ?>"
                                data-order-date="<?php echo esc_attr( wp_date( 'M d, Y g:i A', strtotime( $transaction->transaction_date ) ) ); ?>"
                                data-order-type="<?php echo esc_attr( $transaction->order_type ); ?>"
                                data-order-payment="<?php echo esc_attr( $format_report_payment_method( $transaction->payment_method ) ); ?>"
                                data-order-status="<?php echo esc_attr( ucfirst( $transaction->status ) ); ?>"
                                data-order-total="<?php echo esc_attr( number_format( floatval( $transaction->total_amount ), 2 ) ); ?>"
                                data-order-items="<?php echo esc_attr( $items_json ); ?>"
                            >
                                <td><strong><?php echo esc_html( $transaction->order_id ); ?></strong></td>
                                <td><?php echo esc_html( wp_date( 'M d, Y g:i A', strtotime( $transaction->transaction_date ) ) ); ?></td>
                                <td><?php echo esc_html( $transaction->order_type ); ?></td>
                                <td><span class="banoks-payment-pill payment-<?php echo esc_attr( 'gcash' === strtolower( (string) $transaction->payment_method ) ? 'gcash' : 'cod' ); ?>"><?php echo esc_html( $format_report_payment_method( $transaction->payment_method ) ); ?></span></td>
                                <td><?php echo esc_html( ucfirst( $transaction->status ) ); ?></td>
                                <td>&#8369;<?php echo esc_html( number_format( floatval( $transaction->total_amount ), 2 ) ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="6">No transactions found for this branch.</td>
                        </tr>
                    <?php endif; ?>
                    <tr class="banoks-report-transactions-no-results" style="display:none;">
                        <td colspan="6">No transactions match the selected filters.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="banoks-admin-edit-modal banoks-report-transactions-filter-modal" id="banoks-report-transactions-filter-modal" aria-hidden="true">
        <div class="banoks-admin-edit-dialog" role="dialog" aria-modal="true" aria-labelledby="banoks-report-transactions-filter-title">
            <div class="banoks-admin-edit-header">
                <div>
                    <h2 id="banoks-report-transactions-filter-title">Filter Transactions</h2>
                    <p>Choose date, payment, type, and status filters for this report table.</p>
                </div>
                <button type="button" class="banoks-admin-edit-close" aria-label="Close transaction filters">&times;</button>
            </div>
            <form class="reports-filter-form banoks-transactions-filter-form">
                <div class="banoks-report-transactions-filter-grid">
                    <label for="banoks-report-transactions-date-from">
                        From
                        <input type="date" id="banoks-report-transactions-date-from" name="transactions_start_date" value="<?php echo esc_attr( $transactions_start_date ); ?>">
                    </label>
                    <label for="banoks-report-transactions-date-to">
                        To
                        <input type="date" id="banoks-report-transactions-date-to" name="transactions_end_date" value="<?php echo esc_attr( $transactions_end_date ); ?>">
                    </label>
                    <label for="banoks-report-transactions-payment">
                        Payment
                        <select id="banoks-report-transactions-payment" name="transactions_payment">
                            <option value="">All Payments</option>
                            <option value="Cash">Cash</option>
                            <option value="E-Money">E-Money</option>
                        </select>
                    </label>
                    <label for="banoks-report-transactions-type">
                        Type
                        <select id="banoks-report-transactions-type" name="transactions_type">
                            <option value="">All Types</option>
                            <option value="Walk-in">Walk-in</option>
                            <option value="Online">Online</option>
                        </select>
                    </label>
                    <label for="banoks-report-transactions-status">
                        Status
                        <select id="banoks-report-transactions-status" name="transactions_status">
                            <option value="">All Statuses</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </label>
                </div>
                <div class="modal-actions">
                    <button type="button" class="button banoks-report-transactions-filter-clear">Clear</button>
                    <button type="button" class="button banoks-admin-edit-cancel">Cancel</button>
                    <button type="submit" class="button button-primary">Apply Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="banoks-report-transaction-modal" id="banoks-report-transaction-modal" aria-hidden="true">
        <div class="banoks-report-transaction-dialog" role="dialog" aria-modal="true" aria-labelledby="banoks-report-transaction-title">
            <button type="button" class="banoks-report-modal-close" aria-label="Close transaction details">&times;</button>
            <h2 id="banoks-report-transaction-title">Transaction Details</h2>
            <div class="banoks-report-transaction-summary">
                <div><span>Order ID</span><strong id="banoks-report-modal-order-id"></strong></div>
                <div><span>Date</span><strong id="banoks-report-modal-date"></strong></div>
                <div><span>Type</span><strong id="banoks-report-modal-type"></strong></div>
                <div><span>Payment</span><strong id="banoks-report-modal-payment"></strong></div>
                <div><span>Status</span><strong id="banoks-report-modal-status"></strong></div>
                <div><span>Total</span><strong id="banoks-report-modal-total"></strong></div>
            </div>
            <table class="widefat striped banoks-report-modal-items">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Price</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody id="banoks-report-modal-items-body"></tbody>
            </table>
        </div>
    </div>
</div>
