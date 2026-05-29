<?php
/**
 * Finance admin page methods for Banoks POS.
 *
 * @package Banoks_POS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait Banoks_POS_Admin_Finance {
    /**
     * Render admin-only finance management.
     *
     * @since    1.0.13
     */
    public function display_cash_management_page() {
        global $wpdb;

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access Finance.', 'banoks-pos' ) );
        }

        $this->display_admin_header();

        $message = '';
        $error   = '';
        $cash_date = current_time( 'Y-m-d' );
        $finance_table = $wpdb->prefix . 'banoks_finance_transactions';
        $expenses_table = $wpdb->prefix . 'banoks_expenses';

        if ( isset( $_POST['banoks_finance_claim_store_balance'] ) ) {
            check_admin_referer( 'banoks_finance_claim_store_balance' );

            $claim_date = isset( $_POST['claim_date'] ) ? sanitize_text_field( wp_unslash( $_POST['claim_date'] ) ) : $cash_date;
            if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $claim_date ) ) {
                $claim_date = $cash_date;
            }

            $destination = isset( $_POST['destination_account'] ) ? $this->sanitize_cash_source( wp_unslash( $_POST['destination_account'] ) ) : '';
            $claim_amount = isset( $_POST['claim_amount'] ) ? round( max( 0, floatval( wp_unslash( $_POST['claim_amount'] ) ) ), 2 ) : 0;

            $claim_branch = isset( $_POST['claim_branch_key'] ) ? sanitize_key( wp_unslash( $_POST['claim_branch_key'] ) ) : 'manukan_branch';
            $claim_type = isset( $_POST['claim_type'] ) ? sanitize_key( wp_unslash( $_POST['claim_type'] ) ) : 'cash_sales_claim';
            if ( ! in_array( $claim_type, array( 'cash_sales_claim', 'gcash_sales_claim' ), true ) ) {
                $claim_type = 'cash_sales_claim';
            }
            $claimable_breakdown = $this->get_branch_claimable_breakdown_for_date( $claim_date, $claim_branch );
            $claim_available = 'gcash_sales_claim' === $claim_type ? $claimable_breakdown['gcash_available'] : $claimable_breakdown['cash_available'];

            if ( ! in_array( $destination, array( 'cash_on_hand', 'gcash_balance', 'bank_balance' ), true ) ) {
                $error = 'Please choose Cash on Hand, GCash Balance, or Bank Balance.';
            } elseif ( 'gcash_sales_claim' === $claim_type && 'cash_on_hand' === $destination ) {
                $error = 'GCash sales cannot be claimed as Cash on Hand. Claim them to GCash Balance or Bank Balance.';
            } elseif ( $claim_amount <= 0 ) {
                $error = 'Please enter a valid amount to claim.';
            } elseif ( $claim_amount > $claim_available ) {
                $error = 'Claim amount cannot be greater than the available sales amount.';
            } else {
                $inserted = $wpdb->insert(
                    $finance_table,
                    array(
                        'transaction_type'    => $claim_type,
                        'source_account'      => 'gcash_sales_claim' === $claim_type ? 'gcash_sales' : 'store_cash',
                        'destination_account' => $destination,
                        'branch_key'          => $claim_branch,
                        'amount'              => $claim_amount,
                        'transaction_date'    => $claim_date,
                        'note'                => isset( $_POST['claim_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['claim_note'] ) ) : '',
                        'created_by'          => get_current_user_id(),
                        'created_at'          => current_time( 'mysql' ),
                    ),
                    array( '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%d', '%s' )
                );

                if ( false === $inserted ) {
                    $error = 'Could not record owner claim.';
                } else {
                    $message = 'Branch sales claimed successfully.';
                    $cash_date = $claim_date;
                }
            }
        }

        if ( isset( $_POST['banoks_finance_add_balance'] ) ) {
            check_admin_referer( 'banoks_finance_add_balance' );

            $balance_date = isset( $_POST['balance_date'] ) ? sanitize_text_field( wp_unslash( $_POST['balance_date'] ) ) : $cash_date;
            if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $balance_date ) ) {
                $balance_date = $cash_date;
            }

            $destination_account = isset( $_POST['balance_destination_account'] ) ? $this->sanitize_cash_source( wp_unslash( $_POST['balance_destination_account'] ) ) : '';
            $balance_amount      = isset( $_POST['balance_amount'] ) ? round( max( 0, floatval( wp_unslash( $_POST['balance_amount'] ) ) ), 2 ) : 0;

            if ( ! in_array( $destination_account, array( 'cash_on_hand', 'gcash_balance', 'bank_balance' ), true ) ) {
                $error = 'Please choose where to add the owner balance.';
            } elseif ( $balance_amount <= 0 ) {
                $error = 'Please enter a valid balance amount.';
            } else {
                $inserted = $wpdb->insert(
                    $finance_table,
                    array(
                        'transaction_type'    => 'owner_capital_addition',
                        'source_account'      => 'owner_capital',
                        'destination_account' => $destination_account,
                        'branch_key'          => '',
                        'amount'              => $balance_amount,
                        'transaction_date'    => $balance_date,
                        'note'                => isset( $_POST['balance_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['balance_note'] ) ) : '',
                        'created_by'          => get_current_user_id(),
                        'created_at'          => current_time( 'mysql' ),
                    ),
                    array( '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%d', '%s' )
                );

                if ( false === $inserted ) {
                    $error = 'Could not add owner balance.';
                } else {
                    $message = 'Owner balance added successfully.';
                    $cash_date = $balance_date;
                }
            }
        }

        if ( isset( $_POST['banoks_owner_pay_bill'] ) ) {
            check_admin_referer( 'banoks_owner_pay_bill_action' );

            $bill_description = isset( $_POST['bill_description'] ) ? sanitize_text_field( wp_unslash( $_POST['bill_description'] ) ) : '';
            $bill_category    = isset( $_POST['bill_category'] ) ? sanitize_text_field( wp_unslash( $_POST['bill_category'] ) ) : 'Other';
            $bill_amount      = isset( $_POST['bill_amount'] ) ? round( max( 0, floatval( wp_unslash( $_POST['bill_amount'] ) ) ), 2 ) : 0;
            $bill_date        = isset( $_POST['bill_date'] ) ? sanitize_text_field( wp_unslash( $_POST['bill_date'] ) ) : $cash_date;
            $bill_cash_source = isset( $_POST['bill_cash_source'] ) ? $this->sanitize_cash_source( wp_unslash( $_POST['bill_cash_source'] ) ) : '';
            $bill_note        = isset( $_POST['bill_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['bill_note'] ) ) : '';
            $bill_categories  = array( 'Rent', 'Utilities', 'Supplier', 'Maintenance', 'Salary', 'Other' );

            if ( '' === $bill_description ) {
                $error = 'Please enter a bill description.';
            } elseif ( $bill_amount <= 0 ) {
                $error = 'Please enter a valid bill amount.';
            } elseif ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $bill_date ) ) {
                $error = 'Please select a valid bill date.';
            } elseif ( ! in_array( $bill_cash_source, array( 'cash_on_hand', 'gcash_balance', 'bank_balance' ), true ) ) {
                $error = 'Please choose a valid payment account.';
            } else {
                if ( ! in_array( $bill_category, $bill_categories, true ) ) {
                    $bill_category = 'Other';
                }

                $expense_description = '[' . $bill_category . '] ' . $bill_description;
                if ( '' !== $bill_note ) {
                    $expense_description .= ' - ' . $bill_note;
                }

                $inserted = $wpdb->insert(
                    $expenses_table,
                    array(
                        'description' => $expense_description,
                        'amount'      => $bill_amount,
                        'date'        => $bill_date,
                        'branch_key'  => '',
                        'cash_source' => $bill_cash_source,
                    ),
                    array( '%s', '%f', '%s', '%s', '%s' )
                );

                if ( false === $inserted ) {
                    $error = 'Could not record paid bill. ' . $wpdb->last_error;
                } else {
                    $message = 'Bill paid and recorded successfully.';
                    $cash_date = $bill_date;
                }
            }
        }

        $cash_on_hand_balance = $this->get_finance_account_balance( 'cash_on_hand' );
        $gcash_balance = $this->get_finance_account_balance( 'gcash_balance' );
        $bank_balance  = $this->get_finance_account_balance( 'bank_balance' );
        $banoks_total_balance = $cash_on_hand_balance + $gcash_balance + $bank_balance;
        $finance_account_options = array(
            'cash_on_hand'  => 'Cash on Hand',
            'gcash_balance' => 'GCash Balance',
            'bank_balance'  => 'Bank Balance',
        );
        $overall_balance_transactions = $this->get_overall_balance_transactions();
        $branch_finance_groups = array();
        $branch_finance_rows = array();
        foreach ( $this->get_active_branches() as $branch ) {
            $branch_key = sanitize_key( $branch->branch_key );
            $daily_rows = $this->get_branch_daily_unclaimed_rows( $branch_key, $branch->branch_name );
            $branch_finance_groups[] = array(
                'branch_key'     => $branch_key,
                'branch_name'    => $branch->branch_name,
                'total_sales'    => $this->get_branch_total_sales( $branch_key ),
                'total_expenses' => $this->get_branch_total_expenses( $branch_key ),
                'rows'           => $daily_rows,
            );
            $branch_finance_rows = array_merge( $branch_finance_rows, $daily_rows );
        }

        include_once dirname( __DIR__ ) . '/partials/banoks-pos-cash-management-display.php';
    }

    private function get_active_branches() {
        global $wpdb;

        $branches = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}banoks_branches WHERE is_active = 1 ORDER BY branch_name ASC" );
        if ( ! empty( $branches ) ) {
            return $branches;
        }

        return array(
            (object) array(
                'branch_key'  => 'manukan_branch',
                'branch_name' => 'Manukan Branch',
            ),
        );
    }

    private function get_branch_total_sales( $branch_key = 'manukan_branch' ) {
        $branch_key = sanitize_key( $branch_key );
        return $this->get_branch_sales_for_period( '1970-01-01', '9999-12-31', $branch_key );
    }

    private function get_branch_total_expenses( $branch_key = 'manukan_branch' ) {
        $branch_key = sanitize_key( $branch_key );
        return $this->get_branch_store_expenses_for_period( '1970-01-01', '9999-12-31', $branch_key );
    }

    private function get_branch_sales_for_period( $start_date, $end_date, $branch_key = 'manukan_branch' ) {
        return $this->get_branch_cash_sales_for_period( $start_date, $end_date, $branch_key ) + $this->get_branch_gcash_sales_for_period( $start_date, $end_date, $branch_key );
    }

    private function get_branch_cash_sales_for_date( $date, $branch_key = 'manukan_branch' ) {
        return $this->get_branch_cash_sales_for_period( $date, $date, $branch_key );
    }

    private function get_branch_cash_sales_for_period( $start_date, $end_date, $branch_key = 'manukan_branch' ) {
        global $wpdb;

        $branch_key = sanitize_key( $branch_key );
        $walkin_branch_where = $this->get_branch_where_clause( 'branch_key', $branch_key );
        $online_branch_where = $this->get_branch_where_clause( 'branch_key', $branch_key );

        $walkin_sales = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(grand_total)
                 FROM {$wpdb->prefix}banoks_orders
                 WHERE date BETWEEN %s AND %s
                 AND status = 'completed'
                 AND $walkin_branch_where
                 AND (received_account = 'store_cash' OR received_account IS NULL OR received_account = '')",
                $start_date,
                $end_date,
                $branch_key
            )
        ) ?: 0;
        $online_sales = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(total_amount)
                 FROM {$wpdb->prefix}banoks_online_orders
                 WHERE DATE(created_at) BETWEEN %s AND %s
                 AND order_status = 'completed'
                 AND $online_branch_where
                 AND LOWER(payment_method) IN ('cod', 'pay_at_pickup', 'cash')",
                $start_date,
                $end_date,
                $branch_key
            )
        ) ?: 0;

        return floatval( $walkin_sales ) + floatval( $online_sales );
    }

    private function get_branch_gcash_sales_for_date( $date, $branch_key = 'manukan_branch' ) {
        return $this->get_branch_gcash_sales_for_period( $date, $date, $branch_key );
    }

    private function get_branch_gcash_sales_for_period( $start_date, $end_date, $branch_key = 'manukan_branch' ) {
        global $wpdb;

        $branch_key = sanitize_key( $branch_key );
        $walkin_branch_where = $this->get_branch_where_clause( 'branch_key', $branch_key );
        $online_branch_where = $this->get_branch_where_clause( 'branch_key', $branch_key );

        $walkin_sales = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(grand_total)
                 FROM {$wpdb->prefix}banoks_orders
                 WHERE date BETWEEN %s AND %s
                 AND status = 'completed'
                 AND $walkin_branch_where
                 AND received_account = 'gcash_balance'",
                $start_date,
                $end_date,
                $branch_key
            )
        ) ?: 0;
        $online_sales = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(total_amount)
                 FROM {$wpdb->prefix}banoks_online_orders
                 WHERE DATE(created_at) BETWEEN %s AND %s
                 AND order_status = 'completed'
                 AND $online_branch_where
                 AND LOWER(payment_method) = 'gcash'",
                $start_date,
                $end_date,
                $branch_key
            )
        ) ?: 0;

        return floatval( $walkin_sales ) + floatval( $online_sales );
    }

    private function get_branch_store_expenses_for_date( $date, $branch_key = 'manukan_branch' ) {
        return $this->get_branch_store_expenses_for_period( $date, $date, $branch_key );
    }

    private function get_branch_store_expenses_for_period( $start_date, $end_date, $branch_key = 'manukan_branch' ) {
        global $wpdb;

        $branch_key = sanitize_key( $branch_key );
        $expense_branch_where = $this->get_branch_where_clause( 'branch_key', $branch_key );

        $store_expenses = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(amount)
                 FROM {$wpdb->prefix}banoks_expenses
                 WHERE date BETWEEN %s AND %s
                 AND cash_source = 'store_cash'
                 AND $expense_branch_where",
                $start_date,
                $end_date,
                $branch_key
            )
        ) ?: 0;
        $store_stock_purchases = 'manukan_branch' === $branch_key ? $this->get_stock_cash_expenses_for_period( $start_date, $end_date, 'store_cash' ) : 0;

        return floatval( $store_expenses ) + floatval( $store_stock_purchases );
    }

    private function get_branch_claimable_breakdown_for_date( $date, $branch_key = 'manukan_branch' ) {
        $branch_key = sanitize_key( $branch_key );

        $cash_sales = $this->get_branch_cash_sales_for_date( $date, $branch_key );
        $gcash_sales = $this->get_branch_gcash_sales_for_date( $date, $branch_key );
        $daily_expenses = $this->get_branch_store_expenses_for_date( $date, $branch_key );
        $cash_claimed = $this->get_finance_claimed_sales_for_date( $date, $branch_key, 'cash_sales_claim' );
        $gcash_claimed = $this->get_finance_claimed_sales_for_date( $date, $branch_key, 'gcash_sales_claim' );

        $cash_expense_share = min( $cash_sales, $daily_expenses );
        $remaining_expenses = max( 0, $daily_expenses - $cash_expense_share );
        $gcash_expense_share = min( $gcash_sales, $remaining_expenses );

        $cash_available = max( 0, $cash_sales - $cash_expense_share - $cash_claimed );
        $gcash_available = max( 0, $gcash_sales - $gcash_expense_share - $gcash_claimed );

        return array(
            'cash_sales'          => $cash_sales,
            'gcash_sales'         => $gcash_sales,
            'daily_expenses'      => $daily_expenses,
            'cash_expense_share'  => $cash_expense_share,
            'gcash_expense_share' => $gcash_expense_share,
            'cash_claimed'        => $cash_claimed,
            'gcash_claimed'       => $gcash_claimed,
            'cash_available'      => $cash_available,
            'gcash_available'     => $gcash_available,
            'total_available'     => $cash_available + $gcash_available,
            'daily_sales'         => $cash_sales + $gcash_sales,
            'daily_final'         => max( 0, ( $cash_sales + $gcash_sales ) - $daily_expenses - $cash_claimed - $gcash_claimed ),
        );
    }

    private function get_branch_daily_unclaimed_rows( $branch_key, $branch_name ) {
        global $wpdb;

        $branch_key = sanitize_key( $branch_key );
        $walkin_branch_where = $this->get_branch_where_clause( 'branch_key', $branch_key );
        $online_branch_where = $this->get_branch_where_clause( 'branch_key', $branch_key );
        $expense_branch_where = $this->get_branch_where_clause( 'branch_key', $branch_key );
        $finance_branch_where = $this->get_branch_where_clause( 'branch_key', $branch_key );
        $dates = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT sale_date FROM (
                    SELECT date AS sale_date FROM {$wpdb->prefix}banoks_orders WHERE status = 'completed' AND $walkin_branch_where
                    UNION
                    SELECT DATE(created_at) AS sale_date FROM {$wpdb->prefix}banoks_online_orders WHERE order_status = 'completed' AND $online_branch_where
                    UNION
                    SELECT date AS sale_date FROM {$wpdb->prefix}banoks_expenses WHERE cash_source = 'store_cash' AND $expense_branch_where
                    UNION
                    SELECT transaction_date AS sale_date FROM {$wpdb->prefix}banoks_finance_transactions WHERE transaction_type IN ('cash_sales_claim', 'gcash_sales_claim') AND $finance_branch_where
                 ) finance_dates
                 WHERE sale_date IS NOT NULL
                 ORDER BY sale_date DESC",
                $branch_key,
                $branch_key,
                $branch_key,
                $branch_key
            )
        );

        $rows = array();
        foreach ( $dates as $date ) {
            $claimable_breakdown = $this->get_branch_claimable_breakdown_for_date( $date, $branch_key );
            $cash_unclaimed = $claimable_breakdown['cash_available'];
            $gcash_unclaimed = $claimable_breakdown['gcash_available'];
            $total_unclaimed = $cash_unclaimed + $gcash_unclaimed;

            if ( $total_unclaimed <= 0 ) {
                continue;
            }

            $rows[] = array(
                'row_key'          => sanitize_key( $branch_key . '-' . $date ),
                'branch_key'       => $branch_key,
                'branch_name'      => $branch_name,
                'claim_date'       => $date,
                'cash_unclaimed'   => $cash_unclaimed,
                'gcash_unclaimed'  => $gcash_unclaimed,
                'total_unclaimed'  => $total_unclaimed,
                'daily_sales'      => $claimable_breakdown['daily_sales'],
                'daily_expenses'   => $claimable_breakdown['daily_expenses'],
                'daily_final'      => $claimable_breakdown['daily_final'],
            );
        }

        return $rows;
    }

    private function get_branch_where_clause( $column, $branch_key ) {
        $column = preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $column );
        $branch_key = sanitize_key( $branch_key );

        if ( 'manukan_branch' === $branch_key ) {
            return "($column = %s OR $column IS NULL OR $column = '')";
        }

        return "$column = %s";
    }

    private function get_finance_claimed_sales_for_date( $date, $branch_key = 'manukan_branch', $claim_type = 'cash_sales_claim' ) {
        return $this->get_finance_claimed_sales_for_period( $date, $date, $branch_key, $claim_type );
    }

    private function get_finance_claimed_sales_for_period( $start_date, $end_date, $branch_key = 'manukan_branch', $claim_type = 'cash_sales_claim' ) {
        global $wpdb;

        $branch_key = sanitize_key( $branch_key );
        $claim_type = sanitize_key( $claim_type );
        if ( ! in_array( $claim_type, array( 'cash_sales_claim', 'gcash_sales_claim' ), true ) ) {
            $claim_type = 'cash_sales_claim';
        }
        $finance_branch_where = $this->get_branch_where_clause( 'branch_key', $branch_key );

        $claimed = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(amount)
                 FROM {$wpdb->prefix}banoks_finance_transactions
                 WHERE transaction_type = %s
                 AND $finance_branch_where
                 AND transaction_date BETWEEN %s AND %s",
                $claim_type,
                $branch_key,
                $start_date,
                $end_date
            )
        );

        return $claimed ? floatval( $claimed ) : 0;
    }

    private function get_finance_account_balance( $account ) {
        global $wpdb;

        $account = $this->sanitize_cash_source( $account );
        if ( ! in_array( $account, array( 'cash_on_hand', 'gcash_balance', 'bank_balance' ), true ) ) {
            return 0;
        }

        $incoming = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(amount)
                 FROM {$wpdb->prefix}banoks_finance_transactions
                 WHERE destination_account = %s",
                $account
            )
        );

        $outgoing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(amount)
                 FROM {$wpdb->prefix}banoks_finance_transactions
                 WHERE source_account = %s",
                $account
            )
        );

        $expense_outgoing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(amount)
                 FROM {$wpdb->prefix}banoks_expenses
                 WHERE cash_source = %s",
                $account
            )
        );

        $stock_outgoing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(total_cost)
                 FROM {$wpdb->prefix}banoks_inventory_movements
                 WHERE affects_cash_balance = 1
                 AND change_amount > 0
                 AND movement_type = 'stock_in'
                 AND location_key = 'production'
                 AND cash_source = %s",
                $account
            )
        );

        return floatval( $incoming ) - floatval( $outgoing ) - floatval( $expense_outgoing ) - floatval( $stock_outgoing );
    }

    private function get_overall_balance_transactions( $limit = 50 ) {
        global $wpdb;

        $limit = max( 1, min( 200, absint( $limit ) ) );
        $accounts = array( 'cash_on_hand', 'gcash_balance', 'bank_balance' );
        $rows = array();

        $finance_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ft.*, u.display_name AS created_by_name, b.branch_name
                 FROM {$wpdb->prefix}banoks_finance_transactions ft
                 LEFT JOIN {$wpdb->users} u ON ft.created_by = u.ID
                 LEFT JOIN {$wpdb->prefix}banoks_branches b ON ft.branch_key = b.branch_key
                 WHERE ft.transaction_type IN ('owner_capital_addition', 'cash_sales_claim', 'gcash_sales_claim')
                 ORDER BY ft.created_at DESC, ft.id DESC
                 LIMIT %d",
                $limit
            )
        );

        foreach ( $finance_rows as $transaction ) {
            if ( ! in_array( $transaction->destination_account, $accounts, true ) ) {
                continue;
            }

            $rows[] = array(
                'timestamp'   => $transaction->created_at,
                'date'        => $transaction->transaction_date,
                'type'        => $this->format_finance_transaction_type( $transaction->transaction_type ),
                'source'      => ! empty( $transaction->branch_name ) ? $transaction->branch_name : $this->format_finance_account_label( $transaction->source_account ),
                'destination' => $this->format_finance_account_label( $transaction->destination_account ),
                'amount'      => floatval( $transaction->amount ),
                'effect'      => 'in',
                'note'        => $transaction->note,
            );
        }

        $expense_rows = $wpdb->get_results(
            "SELECT e.description, e.amount, e.cash_source, e.date, e.created_at, b.branch_name
             FROM {$wpdb->prefix}banoks_expenses e
             LEFT JOIN {$wpdb->prefix}banoks_branches b ON e.branch_key = b.branch_key
             WHERE e.cash_source IN ('cash_on_hand', 'gcash_balance', 'bank_balance')
             ORDER BY e.created_at DESC
             LIMIT $limit"
        );

        foreach ( $expense_rows as $expense ) {
            $rows[] = array(
                'timestamp'   => $expense->created_at,
                'date'        => $expense->date,
                'type'        => 'Expense',
                'source'      => ! empty( $expense->branch_name ) ? $expense->branch_name : $this->format_finance_account_label( $expense->cash_source ),
                'destination' => 'Expense',
                'amount'      => floatval( $expense->amount ),
                'effect'      => 'out',
                'note'        => $expense->description,
            );
        }

        $stock_rows = $wpdb->get_results(
            "SELECT COALESCE(i.item_name, 'Deleted item') AS item_name,
                    COALESCE(i.unit, '') AS unit,
                    COALESCE(l.location_name, m.location_key) AS location_name,
                    m.movement_type,
                    m.change_amount,
                    m.note,
                    m.total_cost AS amount,
                    m.cash_source,
                    DATE(m.created_at) AS date,
                    m.created_at
             FROM {$wpdb->prefix}banoks_inventory_movements m
             LEFT JOIN {$wpdb->prefix}banoks_inventory_items i ON m.inventory_item_id = i.id
             LEFT JOIN {$wpdb->prefix}banoks_stock_locations l ON m.location_key = l.location_key
             WHERE m.affects_cash_balance = 1
             AND m.change_amount > 0
             AND m.movement_type = 'stock_in'
             AND m.location_key = 'production'
             AND m.cash_source IN ('cash_on_hand', 'gcash_balance', 'bank_balance')
             ORDER BY m.created_at DESC
             LIMIT $limit"
        );

        foreach ( $stock_rows as $stock_row ) {
            $stock_note = 'stock_in' === $stock_row->movement_type
                ? sprintf( 'Stock in: %s - %s.', $stock_row->item_name, $this->format_stock_quantity( $stock_row->change_amount, $stock_row->unit ) )
                : ( '' !== trim( (string) $stock_row->note ) ? $stock_row->note : sprintf( 'Stock purchase: %s', $stock_row->item_name ) );

            $rows[] = array(
                'timestamp'   => $stock_row->created_at,
                'date'        => $stock_row->date,
                'type'        => 'Stock Purchase',
                'source'      => $this->format_finance_account_label( $stock_row->cash_source ),
                'destination' => $stock_row->location_name,
                'amount'      => floatval( $stock_row->amount ),
                'effect'      => 'out',
                'note'        => $stock_note,
            );
        }

        usort(
            $rows,
            function ( $a, $b ) {
                return strtotime( $b['timestamp'] ) <=> strtotime( $a['timestamp'] );
            }
        );

        return array_slice( $rows, 0, $limit );
    }

    private function format_finance_transaction_type( $type ) {
        $labels = array(
            'owner_capital_addition' => 'Owner Added Balance',
            'cash_sales_claim'       => 'Claimed Cash Sales',
            'gcash_sales_claim'      => 'Claimed GCash Sales',
        );

        return isset( $labels[ $type ] ) ? $labels[ $type ] : ucwords( str_replace( '_', ' ', (string) $type ) );
    }

    private function format_finance_account_label( $account ) {
        $labels = array(
            'owner_capital'  => 'Owner Capital',
            'store_cash'     => 'Branch Cash Sales',
            'gcash_sales'    => 'Branch GCash Sales',
            'cash_on_hand'   => 'Cash on Hand',
            'gcash_balance'  => 'GCash Balance',
            'bank_balance'   => 'Bank Balance',
        );

        return isset( $labels[ $account ] ) ? $labels[ $account ] : ucwords( str_replace( '_', ' ', (string) $account ) );
    }
}
