<?php
/**
 * Finance admin page.
 *
 * @package Banoks_POS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$format_money = function ( $value ) {
    return '&#8369;' . number_format( floatval( $value ), 2 );
};

$format_account = function ( $account ) use ( $finance_account_options ) {
    if ( 'owner_capital' === $account ) {
        return 'Owner Capital';
    }

    return isset( $finance_account_options[ $account ] ) ? $finance_account_options[ $account ] : ucwords( str_replace( '_', ' ', (string) $account ) );
};

$finance_account_balances = array(
    'cash_on_hand'  => $cash_on_hand_balance,
    'gcash_balance' => $gcash_balance,
    'bank_balance'  => $bank_balance,
);

$overall_filter_options = array(
    'type'        => array(),
    'source'      => array(),
    'destination' => array(),
    'effect'      => array(
        'in'  => 'In',
        'out' => 'Out',
    ),
);

foreach ( $overall_balance_transactions as $transaction ) {
    $overall_filter_options['type'][ $transaction['type'] ]      = $transaction['type'];
    $overall_filter_options['source'][ $transaction['source'] ]  = $transaction['source'];
    $overall_filter_options['destination'][ $transaction['destination'] ] = $transaction['destination'];
}

foreach ( array( 'type', 'source', 'destination' ) as $filter_key ) {
    asort( $overall_filter_options[ $filter_key ] );
}
?>

<div class="wrap banoks-pos-admin banoks-pos-page banoks-cash-management-page">
    <div class="banoks-cash-header">
        <div>
            <h1>Finance</h1>
            <p>Track account balances and branch cash flow.</p>
        </div>
    </div>

    <?php if ( ! empty( $message ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
    <?php endif; ?>
    <?php if ( ! empty( $error ) ) : ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ); ?></p></div>
    <?php endif; ?>

    <div class="banoks-finance-actions">
        <button type="button" class="button button-primary banoks-open-finance-add-balance" data-target="#banoks-finance-add-balance-modal">Add Balance</button>
        <button type="button" class="button button-primary banoks-open-pay-bill" data-target="#banoks-pay-bill-modal">Pay Bill</button>
    </div>

    <div class="banoks-stock-summary banoks-cash-summary banoks-finance-account-summary">
        <div class="banoks-stock-summary-card">
            <span>Banok's Total Balance</span>
            <strong><?php echo wp_kses_post( $format_money( $banoks_total_balance ) ); ?></strong>
            <div class="banoks-finance-balance-breakdown" aria-label="Finance account balance breakdown">
                <span>Cash on Hand <strong><?php echo wp_kses_post( $format_money( $cash_on_hand_balance ) ); ?></strong></span>
                <span>GCash <strong><?php echo wp_kses_post( $format_money( $gcash_balance ) ); ?></strong></span>
                <span>Bank <strong><?php echo wp_kses_post( $format_money( $bank_balance ) ); ?></strong></span>
            </div>
        </div>
    </div>

    <section class="banoks-stock-panel banoks-cash-flow-panel banoks-finance-overall-panel">
        <div class="banoks-stock-section-header">
            <div>
                <h2>Transactions</h2>
                <p>Money movements that affect Banok's total balance.</p>
                <button type="button" class="button banoks-finance-filter-trigger" data-target="#banoks-finance-filter-modal">
                    Filter
                </button>
            </div>
        </div>

        <div class="banoks-stock-table-scroll">
            <table class="widefat striped banoks-stock-table banoks-finance-overall-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Source</th>
                        <th>Destination</th>
                        <th>Amount</th>
                        <th>Effect</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $overall_balance_transactions ) ) : ?>
                        <tr class="banoks-finance-overall-empty">
                            <td colspan="7">No overall balance transactions yet.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ( $overall_balance_transactions as $transaction ) : ?>
                        <tr
                            class="banoks-finance-overall-row"
                            data-date="<?php echo esc_attr( date( 'Y-m-d', strtotime( $transaction['date'] ) ) ); ?>"
                            data-type="<?php echo esc_attr( $transaction['type'] ); ?>"
                            data-source="<?php echo esc_attr( $transaction['source'] ); ?>"
                            data-destination="<?php echo esc_attr( $transaction['destination'] ); ?>"
                            data-effect="<?php echo esc_attr( $transaction['effect'] ); ?>"
                            data-amount="<?php echo esc_attr( number_format( floatval( $transaction['amount'] ), 2, '.', '' ) ); ?>"
                        >
                            <td><?php echo esc_html( wp_date( 'M d, Y', strtotime( $transaction['date'] ) ) ); ?></td>
                            <td><strong><?php echo esc_html( $transaction['type'] ); ?></strong></td>
                            <td><?php echo esc_html( $transaction['source'] ); ?></td>
                            <td><?php echo esc_html( $transaction['destination'] ); ?></td>
                            <td><?php echo wp_kses_post( $format_money( $transaction['amount'] ) ); ?></td>
                            <td>
                                <span class="banoks-finance-effect banoks-finance-effect-<?php echo esc_attr( $transaction['effect'] ); ?>">
                                    <?php echo 'in' === $transaction['effect'] ? 'In' : 'Out'; ?>
                                </span>
                            </td>
                            <td><?php echo esc_html( $transaction['note'] ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="banoks-finance-overall-no-results" style="display:none;">
                        <td colspan="7">No transactions match the selected filters.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="banoks-finance-filter-totals" aria-live="polite" style="display:none;">
            <span>Total In: <strong id="banoks-finance-filter-total-in"><?php echo wp_kses_post( $format_money( 0 ) ); ?></strong></span>
            <span>Total Out: <strong id="banoks-finance-filter-total-out"><?php echo wp_kses_post( $format_money( 0 ) ); ?></strong></span>
        </div>
    </section>

    <div class="banoks-admin-edit-modal banoks-finance-filter-modal" id="banoks-finance-filter-modal" aria-hidden="true">
        <div class="banoks-admin-edit-dialog" role="dialog" aria-modal="true" aria-labelledby="banoks-finance-filter-title">
            <div class="banoks-admin-edit-header">
                <div>
                    <h2 id="banoks-finance-filter-title">Filter Transactions</h2>
                    <p>Refine the overall balance table.</p>
                </div>
                <button type="button" class="banoks-admin-edit-close" aria-label="Close transaction filters">&times;</button>
            </div>

            <form class="banoks-finance-filter-form">
                <div class="banoks-finance-filter-grid">
                    <label>
                        Date From
                        <input type="date" id="banoks-finance-filter-date-from" name="date_from">
                    </label>

                    <label>
                        Date To
                        <input type="date" id="banoks-finance-filter-date-to" name="date_to">
                    </label>

                    <label>
                        Type
                        <select id="banoks-finance-filter-type" name="type">
                            <option value="">All types</option>
                            <?php foreach ( $overall_filter_options['type'] as $type_value ) : ?>
                                <option value="<?php echo esc_attr( $type_value ); ?>"><?php echo esc_html( $type_value ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        Source
                        <select id="banoks-finance-filter-source" name="source">
                            <option value="">All sources</option>
                            <?php foreach ( $overall_filter_options['source'] as $source_value ) : ?>
                                <option value="<?php echo esc_attr( $source_value ); ?>"><?php echo esc_html( $source_value ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        Destination
                        <select id="banoks-finance-filter-destination" name="destination">
                            <option value="">All destinations</option>
                            <?php foreach ( $overall_filter_options['destination'] as $destination_value ) : ?>
                                <option value="<?php echo esc_attr( $destination_value ); ?>"><?php echo esc_html( $destination_value ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        Effect
                        <select id="banoks-finance-filter-effect" name="effect">
                            <option value="">All effects</option>
                            <?php foreach ( $overall_filter_options['effect'] as $effect_value => $effect_label ) : ?>
                                <option value="<?php echo esc_attr( $effect_value ); ?>"><?php echo esc_html( $effect_label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <div class="modal-actions">
                    <button type="button" class="button banoks-finance-filter-clear">Clear</button>
                    <button type="submit" class="button button-primary">Apply Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="banoks-admin-edit-modal banoks-finance-add-balance-modal" id="banoks-finance-add-balance-modal" aria-hidden="true">
        <div class="banoks-admin-edit-dialog" role="dialog" aria-modal="true" aria-labelledby="banoks-finance-add-balance-title">
            <div class="banoks-admin-edit-header">
                <div>
                    <h2 id="banoks-finance-add-balance-title">Add Balance</h2>
                    <p>Add owner money into the business balance.</p>
                </div>
                <button type="button" class="banoks-admin-edit-close" aria-label="Close add balance form">&times;</button>
            </div>

            <form method="post" class="banoks-expense-form banoks-finance-add-balance-form">
                <?php wp_nonce_field( 'banoks_finance_add_balance' ); ?>
                <input type="hidden" name="banoks_finance_add_balance" value="1">
                <input type="hidden" name="balance_date" value="<?php echo esc_attr( $cash_date ); ?>">

                <div class="expense-field">
                    <label for="finance-balance-destination-account">Add To</label>
                    <select id="finance-balance-destination-account" name="balance_destination_account" required>
                        <?php foreach ( $finance_account_options as $account_key => $account_label ) : ?>
                            <option
                                value="<?php echo esc_attr( $account_key ); ?>"
                                data-balance="<?php echo esc_attr( number_format( max( 0, $finance_account_balances[ $account_key ] ), 2, '.', '' ) ); ?>"
                            >
                                <?php echo esc_html( $account_label ); ?> - <?php echo wp_kses_post( $format_money( $finance_account_balances[ $account_key ] ) ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="expense-field">
                    <label for="finance-balance-amount">Amount</label>
                    <input type="number" id="finance-balance-amount" name="balance_amount" min="0.01" step="0.01" inputmode="decimal" required>
                </div>

                <div class="expense-field">
                    <label for="finance-balance-note">Note</label>
                    <input type="text" id="finance-balance-note" name="balance_note" placeholder="Optional note">
                </div>

                <div class="modal-actions">
                    <button type="button" class="button banoks-admin-edit-cancel">Cancel</button>
                    <button type="submit" class="button button-primary">Add Balance</button>
                </div>
            </form>
        </div>
    </div>

    <div class="banoks-admin-edit-modal banoks-finance-add-balance-modal" id="banoks-pay-bill-modal" aria-hidden="true">
        <div class="banoks-admin-edit-dialog" role="dialog" aria-modal="true" aria-labelledby="banoks-pay-bill-title">
            <div class="banoks-admin-edit-header">
                <div>
                    <h2 id="banoks-pay-bill-title">Pay Bill</h2>
                    <p>Record a bill or direct owner-paid expense.</p>
                </div>
                <button type="button" class="banoks-admin-edit-close" aria-label="Close pay bill">&times;</button>
            </div>

            <form method="post" class="banoks-expense-form banoks-pay-bill-form" id="banoks-pay-bill-form">
                <?php wp_nonce_field( 'banoks_owner_pay_bill_action' ); ?>
                <input type="hidden" name="banoks_owner_pay_bill" value="1">

                <div class="expense-field">
                    <label for="bill-description">Bill Name / Description</label>
                    <input type="text" id="bill-description" name="bill_description" required>
                </div>

                <div class="banoks-pay-bill-grid">
                    <div class="expense-field">
                        <label for="bill-category">Category</label>
                        <select id="bill-category" name="bill_category" required>
                            <option value="Rent">Rent</option>
                            <option value="Utilities">Utilities</option>
                            <option value="Supplier">Supplier</option>
                            <option value="Maintenance">Maintenance</option>
                            <option value="Salary">Salary</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="expense-field">
                        <label for="bill-amount">Amount</label>
                        <input type="number" id="bill-amount" name="bill_amount" min="0.01" step="0.01" inputmode="decimal" required>
                    </div>
                </div>

                <div class="banoks-pay-bill-grid">
                    <div class="expense-field">
                        <label for="bill-date">Date Paid</label>
                        <input type="date" id="bill-date" name="bill_date" value="<?php echo esc_attr( $cash_date ); ?>" required>
                    </div>

                    <div class="expense-field">
                        <label for="bill-cash-source">Paid From</label>
                        <select id="bill-cash-source" name="bill_cash_source" required>
                            <option value="cash_on_hand"><?php echo esc_html( $finance_account_options['cash_on_hand'] ); ?></option>
                            <option value="gcash_balance"><?php echo esc_html( $finance_account_options['gcash_balance'] ); ?></option>
                            <option value="bank_balance"><?php echo esc_html( $finance_account_options['bank_balance'] ); ?></option>
                        </select>
                    </div>
                </div>

                <div class="expense-field">
                    <label for="bill-note">Note / Receipt Reference</label>
                    <textarea id="bill-note" name="bill_note" rows="3"></textarea>
                </div>

                <div class="modal-actions">
                    <button type="button" class="button banoks-admin-edit-cancel">Cancel</button>
                    <button type="submit" class="button button-primary">Record Paid Bill</button>
                </div>
            </form>
        </div>
    </div>

    <?php foreach ( $branch_finance_groups as $branch_group ) : ?>
        <section class="banoks-stock-panel banoks-cash-flow-panel banoks-finance-branch-panel">
            <div class="banoks-stock-section-header banoks-finance-branch-header">
                <div>
                    <h2><?php echo esc_html( $branch_group['branch_name'] ); ?></h2>
                    <p>Daily unclaimed sales for owner claiming.</p>
                </div>
                <div class="banoks-finance-branch-totals">
                    <span>Overall Sales: <strong><?php echo wp_kses_post( $format_money( $branch_group['total_sales'] ) ); ?></strong></span>
                    <span>Overall Expenses: <strong><?php echo wp_kses_post( $format_money( $branch_group['total_expenses'] ) ); ?></strong></span>
                </div>
            </div>

            <div class="banoks-stock-table-scroll">
                <table class="widefat striped banoks-stock-table banoks-finance-branch-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Sale</th>
                            <th>Expenses</th>
                            <th>Final Sale</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $branch_group['rows'] ) ) : ?>
                            <tr>
                                <td colspan="5">No unclaimed sales found for this branch.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ( $branch_group['rows'] as $branch_row ) : ?>
                            <tr>
                                <td><strong><?php echo esc_html( wp_date( 'M d, Y', strtotime( $branch_row['claim_date'] ) ) ); ?></strong></td>
                                <td><?php echo wp_kses_post( $format_money( $branch_row['daily_sales'] ) ); ?></td>
                                <td><?php echo wp_kses_post( $format_money( $branch_row['daily_expenses'] ) ); ?></td>
                                <td><strong><?php echo wp_kses_post( $format_money( $branch_row['daily_final'] ) ); ?></strong></td>
                                <td>
                                    <button
                                        type="button"
                                        class="button button-primary banoks-open-finance-claim"
                                        data-target="#banoks-finance-claim-<?php echo esc_attr( $branch_row['row_key'] ); ?>"
                                        data-cash-available="<?php echo esc_attr( number_format( max( 0, $branch_row['cash_unclaimed'] ), 2, '.', '' ) ); ?>"
                                        data-gcash-available="<?php echo esc_attr( number_format( max( 0, $branch_row['gcash_unclaimed'] ), 2, '.', '' ) ); ?>"
                                    >
                                        Claim
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endforeach; ?>

    <?php foreach ( $branch_finance_rows as $branch_row ) : ?>
        <div class="banoks-admin-edit-modal banoks-finance-claim-modal" id="banoks-finance-claim-<?php echo esc_attr( $branch_row['row_key'] ); ?>" aria-hidden="true">
            <div class="banoks-admin-edit-dialog" role="dialog" aria-modal="true" aria-labelledby="banoks-finance-claim-title-<?php echo esc_attr( $branch_row['row_key'] ); ?>">
                <div class="banoks-admin-edit-header">
                    <h2 id="banoks-finance-claim-title-<?php echo esc_attr( $branch_row['row_key'] ); ?>">Claim <?php echo esc_html( $branch_row['branch_name'] ); ?> Sales</h2>
                    <button type="button" class="banoks-admin-edit-close" aria-label="Close claim form">&times;</button>
                </div>

                <form method="post" class="banoks-expense-form banoks-finance-claim-form">
                    <?php wp_nonce_field( 'banoks_finance_claim_store_balance' ); ?>
                    <input type="hidden" name="banoks_finance_claim_store_balance" value="1">
                    <input type="hidden" name="claim_date" value="<?php echo esc_attr( $branch_row['claim_date'] ); ?>">
                    <input type="hidden" name="claim_branch_key" value="<?php echo esc_attr( $branch_row['branch_key'] ); ?>">

                    <div class="expense-field">
                        <label for="claim-type-<?php echo esc_attr( $branch_row['branch_key'] ); ?>">Claim Type</label>
                        <select
                            id="claim-type-<?php echo esc_attr( $branch_row['branch_key'] ); ?>"
                            name="claim_type"
                            class="banoks-finance-claim-type"
                            required
                        >
                            <option value="cash_sales_claim" data-available="<?php echo esc_attr( number_format( max( 0, $branch_row['cash_unclaimed'] ), 2, '.', '' ) ); ?>">
                                Claimable Cash: <?php echo wp_kses_post( $format_money( $branch_row['cash_unclaimed'] ) ); ?>
                            </option>
                            <option value="gcash_sales_claim" data-available="<?php echo esc_attr( number_format( max( 0, $branch_row['gcash_unclaimed'] ), 2, '.', '' ) ); ?>">
                                Claimable GCash: <?php echo wp_kses_post( $format_money( $branch_row['gcash_unclaimed'] ) ); ?>
                            </option>
                        </select>
                    </div>

                    <div class="expense-field">
                        <label for="claim-amount-<?php echo esc_attr( $branch_row['branch_key'] ); ?>">Amount to Claim</label>
                        <input type="number" id="claim-amount-<?php echo esc_attr( $branch_row['branch_key'] ); ?>" class="banoks-finance-claim-amount" name="claim_amount" min="0.01" step="0.01" max="<?php echo esc_attr( number_format( max( 0, $branch_row['cash_unclaimed'] ), 2, '.', '' ) ); ?>" value="<?php echo esc_attr( number_format( max( 0, $branch_row['cash_unclaimed'] ), 2, '.', '' ) ); ?>" inputmode="decimal" required>
                    </div>

                    <div class="expense-field">
                        <label for="destination-account-<?php echo esc_attr( $branch_row['branch_key'] ); ?>">Move To</label>
                        <select id="destination-account-<?php echo esc_attr( $branch_row['branch_key'] ); ?>" name="destination_account" required>
                            <option value="cash_on_hand" data-cash-only="1">Cash on Hand</option>
                            <option value="gcash_balance">GCash Balance</option>
                            <option value="bank_balance">Bank Balance</option>
                        </select>
                    </div>

                    <div class="expense-field">
                        <label for="claim-note-<?php echo esc_attr( $branch_row['branch_key'] ); ?>">Note</label>
                        <textarea id="claim-note-<?php echo esc_attr( $branch_row['branch_key'] ); ?>" name="claim_note" rows="3" placeholder="Optional note"></textarea>
                    </div>

                    <div class="modal-actions">
                        <button type="button" class="button banoks-admin-edit-cancel">Cancel</button>
                        <button type="submit" class="button button-primary">Confirm Claim</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endforeach; ?>

    <script>
        function syncFinanceClaimDestination(form) {
            var claimType = form ? form.querySelector('.banoks-finance-claim-type') : null;
            var destination = form ? form.querySelector('select[name="destination_account"]') : null;
            if (!claimType || !destination) {
                return;
            }

            var isGcashClaim = claimType.value === 'gcash_sales_claim';
            Array.prototype.forEach.call(destination.options, function(option) {
                if (option.getAttribute('data-cash-only') === '1') {
                    option.disabled = isGcashClaim;
                }
            });

            if (isGcashClaim && destination.value === 'cash_on_hand') {
                destination.value = 'gcash_balance';
            }
        }

        document.addEventListener('click', function(event) {
            var trigger = event.target.closest('.banoks-open-finance-claim');
            if (trigger) {
                var target = trigger.getAttribute('data-target');
                var modal = target ? document.querySelector(target) : null;
                if (modal) {
                    var claimType = modal.querySelector('.banoks-finance-claim-type');
                    if (claimType) {
                        claimType.dispatchEvent(new Event('change'));
                    }
                    syncFinanceClaimDestination(modal.querySelector('.banoks-finance-claim-form'));
                    modal.classList.add('active');
                    modal.setAttribute('aria-hidden', 'false');
                }
            }

            var addBalanceTrigger = event.target.closest('.banoks-open-finance-add-balance');
            if (addBalanceTrigger) {
                var addBalanceTarget = addBalanceTrigger.getAttribute('data-target');
                var addBalanceModal = addBalanceTarget ? document.querySelector(addBalanceTarget) : null;
                if (addBalanceModal) {
                    addBalanceModal.classList.add('active');
                    addBalanceModal.setAttribute('aria-hidden', 'false');
                }
            }

            var close = event.target.closest('.banoks-finance-claim-modal .banoks-admin-edit-close, .banoks-finance-claim-modal .banoks-admin-edit-cancel, .banoks-finance-add-balance-modal .banoks-admin-edit-close, .banoks-finance-add-balance-modal .banoks-admin-edit-cancel');
            if (close) {
                var closeModal = close.closest('.banoks-finance-claim-modal, .banoks-finance-add-balance-modal');
                if (closeModal) {
                    closeModal.classList.remove('active');
                    closeModal.setAttribute('aria-hidden', 'true');
                }
            }

            if (event.target.classList && (event.target.classList.contains('banoks-finance-claim-modal') || event.target.classList.contains('banoks-finance-add-balance-modal'))) {
                event.target.classList.remove('active');
                event.target.setAttribute('aria-hidden', 'true');
            }
        });

        document.addEventListener('change', function(event) {
            if (!event.target.classList || !event.target.classList.contains('banoks-finance-claim-type')) {
                return;
            }

            var form = event.target.closest('.banoks-finance-claim-form');
            var amount = form ? form.querySelector('.banoks-finance-claim-amount') : null;
            var selected = event.target.options[event.target.selectedIndex];
            var available = selected ? parseFloat(selected.getAttribute('data-available') || '0') : 0;
            var formatted = Math.max(0, available).toFixed(2);

            if (amount) {
                amount.setAttribute('max', formatted);
                amount.value = formatted;
            }

            syncFinanceClaimDestination(form);
        });
    </script>
</div>
