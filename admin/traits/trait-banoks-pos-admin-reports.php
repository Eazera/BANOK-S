<?php
/**
 * Reports admin page methods for Banoks POS.
 *
 * @package Banoks_POS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait Banoks_POS_Admin_Reports {
    /**
     * Render the Business Reports page.
     *
     * @since    1.0.0
     */
    public function display_reports_page() {
        global $wpdb;

        $start_date = $this->get_request_date( 'start_date', wp_date( 'Y-m-01' ) );
        $end_date = $this->get_request_date( 'end_date', wp_date( 'Y-m-d' ) );
        $transactions_start_date = $this->get_request_date( 'transactions_start_date', '' );
        $transactions_end_date   = $this->get_request_date( 'transactions_end_date', '' );
        $active_branches = $this->get_active_branches();
        $selected_branch_key = isset( $_GET['branch_key'] ) ? sanitize_key( wp_unslash( $_GET['branch_key'] ) ) : Banoks_POS_Repository::STOCK_LOCATION_MANUKAN;
        $selected_branch_name = 'Manukan Branch';
        $valid_branch_keys = array();

        foreach ( $active_branches as $branch ) {
            $branch_key = sanitize_key( $branch->branch_key );
            $valid_branch_keys[] = $branch_key;
            if ( $selected_branch_key === $branch_key ) {
                $selected_branch_name = $branch->branch_name;
            }
        }

        if ( ! in_array( $selected_branch_key, $valid_branch_keys, true ) ) {
            $selected_branch_key = Banoks_POS_Repository::STOCK_LOCATION_MANUKAN;
            foreach ( $active_branches as $branch ) {
                if ( $selected_branch_key === sanitize_key( $branch->branch_key ) ) {
                    $selected_branch_name = $branch->branch_name;
                    break;
                }
            }
        }

        $this->display_admin_header();

        $total_sales = $this->get_branch_sales_for_period( $start_date, $end_date, $selected_branch_key );
        $total_expenses = $this->get_report_expense_total_for_branch( $start_date, $end_date, $selected_branch_key );
        $net_profit = $total_sales - $total_expenses;
        $branch_expenses = $this->get_report_expense_rows_for_branch( $start_date, $end_date, $selected_branch_key );
        $top_products = $this->get_combined_top_products( $start_date, $end_date, $selected_branch_key );
        $sales_trend_granularity = $start_date === $end_date ? 'hourly' : 'daily';
        $daily_sales = 'hourly' === $sales_trend_granularity
            ? $this->get_combined_hourly_sales( $start_date, $selected_branch_key )
            : $this->get_combined_daily_sales( $start_date, $end_date, $selected_branch_key );
        $report_transactions = $this->get_combined_report_transaction_table( $transactions_start_date, $transactions_end_date, $selected_branch_key );

        include_once dirname( __DIR__ ) . '/partials/banoks-pos-reports-display.php';
    }

    /**
     * Export the selected report range as a simple PDF.
     *
     * @since    1.0.4
     * @param    string $start_date Start date in Y-m-d format.
     * @param    string $end_date End date in Y-m-d format.
     */
    private function export_report_pdf( $start_date, $end_date, $branch_key = '' ) {
        global $wpdb;

        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start_date ) ) {
            $start_date = wp_date( 'Y-m-01' );
        }

        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end_date ) ) {
            $end_date = wp_date( 'Y-m-d' );
        }

        $branch_key = sanitize_key( $branch_key );
        $branch_name = 'Manukan Branch';
        $active_branch_keys = array();
        foreach ( $this->get_active_branches() as $branch ) {
            $active_branch_key = sanitize_key( $branch->branch_key );
            $active_branch_keys[] = $active_branch_key;
            if ( $branch_key === $active_branch_key ) {
                $branch_name = $branch->branch_name;
            }
        }
        if ( ! in_array( $branch_key, $active_branch_keys, true ) ) {
            $branch_key = Banoks_POS_Repository::STOCK_LOCATION_MANUKAN;
            foreach ( $this->get_active_branches() as $branch ) {
                if ( $branch_key === sanitize_key( $branch->branch_key ) ) {
                    $branch_name = $branch->branch_name;
                    break;
                }
            }
        }

        $total_sales = $this->get_branch_sales_for_period( $start_date, $end_date, $branch_key );
        $total_expenses = $this->get_report_expense_total_for_branch( $start_date, $end_date, $branch_key );
        $top_products = $this->get_combined_top_products( $start_date, $end_date, $branch_key );

        $expenses          = $this->get_report_expense_rows_for_branch( $start_date, $end_date, $branch_key );
        $stock_report_rows = $this->get_branch_stock_report_rows( $start_date, $end_date, $branch_key );
        $net_profit        = floatval( $total_sales ) - floatval( $total_expenses );

        $pdf_branch_name = preg_replace( '/\s+Branch$/i', '', $branch_name );
        $lines = array(
            array( 'text' => "BANOK'S SALES REPORT", 'style' => 'title', 'align' => 'center' ),
            array( 'text' => 'Branch: ' . $pdf_branch_name ),
            array( 'text' => 'Report Period: ' . $this->format_report_date_numeric( $start_date ) . ' - ' . $this->format_report_date_numeric( $end_date ) ),
            array( 'type' => 'divider' ),
            array( 'text' => 'SUMMARY', 'style' => 'section' ),
            array( 'text' => $this->format_pdf_row( array( 'Metric', 'Amount' ), array( 42, 24 ) ), 'style' => 'table_header' ),
            array( 'text' => $this->format_pdf_row( array( 'Total Period Sales', 'PHP ' . number_format( floatval( $total_sales ), 2 ) ), array( 42, 24 ) ), 'style' => 'table_row' ),
            array( 'text' => $this->format_pdf_row( array( 'Total Period Expenses', 'PHP ' . number_format( floatval( $total_expenses ), 2 ) ), array( 42, 24 ) ), 'style' => 'table_row' ),
            array( 'text' => $this->format_pdf_row( array( 'Final Total Sales', 'PHP ' . number_format( $net_profit, 2 ) ), array( 42, 24 ) ), 'style' => 'table_row' ),
            array( 'type' => 'divider' ),
            array( 'text' => 'EXPENSES LIST', 'style' => 'section' ),
            array( 'text' => $this->format_pdf_row( array( 'Expense Name / Description', 'Qty', 'Amount' ), array( 46, 14, 16 ) ), 'style' => 'table_header' ),
        );

        if ( ! empty( $expenses ) ) {
            foreach ( $expenses as $expense ) {
                $expense_quantity = isset( $expense->quantity ) ? floatval( $expense->quantity ) : 0;
                $quantity_label = $expense_quantity > 0
                    ? rtrim( rtrim( number_format( $expense_quantity, 3, '.', '' ), '0' ), '.' ) . ( ! empty( $expense->unit ) ? ' ' . $expense->unit : '' )
                    : '-';
                $lines[] = array(
                    'text'  => $this->format_pdf_row(
                        array(
                            $expense->description,
                            $quantity_label,
                            'PHP ' . number_format( floatval( $expense->amount ), 2 ),
                        ),
                        array( 46, 14, 16 )
                    ),
                    'style' => 'table_row',
                );
            }
        }

        if ( empty( $expenses ) ) {
            $lines[] = array( 'text' => 'No expenses recorded for this period.' );
        }

        $lines[] = array( 'type' => 'divider' );
        $lines[] = array( 'text' => 'STOCK', 'style' => 'section' );
        $lines[] = array( 'text' => $this->format_pdf_row( array( 'Items', 'Start', 'End' ), array( 42, 16, 16 ) ), 'style' => 'table_header' );

        if ( empty( $stock_report_rows ) ) {
            $lines[] = array( 'text' => 'No stock movement found for this branch in this period.' );
        } else {
            foreach ( $stock_report_rows as $stock_row ) {
                $lines[] = array(
                    'text'  => $this->format_pdf_row(
                        array(
                            $stock_row['item_name'],
                            $this->format_stock_quantity( $stock_row['opening_stock'], $stock_row['unit'] ),
                            $this->format_stock_quantity( $stock_row['ending_stock'], $stock_row['unit'] ),
                        ),
                        array( 42, 16, 16 )
                    ),
                    'style' => 'table_row',
                );
            }
        }

        $lines[] = array( 'type' => 'divider' );
        $lines[] = array( 'text' => 'TOP 10 BEST-SELLING PRODUCTS', 'style' => 'section' );
        $lines[] = array( 'text' => $this->format_pdf_row( array( 'Rank', 'Product Name', 'Quantity Sold', 'Total Sales' ), array( 6, 30, 12, 16 ) ), 'style' => 'table_header' );

        if ( ! empty( $top_products ) ) {
            $rank = 1;
            foreach ( $top_products as $product ) {
                $lines[] = array(
                    'text' => $this->format_pdf_row(
                        array(
                            $rank,
                            $product->product_name,
                            intval( $product->total_qty ),
                            'PHP ' . number_format( floatval( $product->total_revenue ), 2 ),
                        ),
                        array( 6, 30, 12, 16 )
                    ),
                    'style' => 'table_row',
                );
                $rank++;
            }
        } else {
            $lines[] = array( 'text' => 'No product sales found for this period.' );
        }
        $lines[] = array( 'type' => 'divider' );

        $pdf      = $this->build_simple_report_pdf( $lines );
        $filename = sanitize_file_name( $branch_name . '-sales-expense-report-' . wp_date( 'm-d-Y', strtotime( $start_date ) ) . '-to-' . wp_date( 'm-d-Y', strtotime( $end_date ) ) . '.pdf' );

        while ( ob_get_level() ) {
            ob_end_clean();
        }

        nocache_headers();
        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . strlen( $pdf ) );
        echo $pdf;
        exit;
    }

    private function get_report_expense_total_for_branch( $start_date, $end_date, $branch_key ) {
        global $wpdb;

        $branch_key = sanitize_key( $branch_key );
        $expenses = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(amount)
                 FROM {$wpdb->prefix}banoks_expenses
                 WHERE branch_key = %s
                 AND date BETWEEN %s AND %s",
                $branch_key,
                $start_date,
                $end_date
            )
        );

        return floatval( $expenses ) + $this->get_report_stock_expenses_for_branch( $start_date, $end_date, $branch_key );
    }

    private function get_report_expense_rows_for_branch( $start_date, $end_date, $branch_key ) {
        global $wpdb;

        $branch_key = sanitize_key( $branch_key );
        $expenses = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT e.description,
                        e.amount,
                        e.date,
                        e.created_at,
                        0 AS quantity,
                        '' AS unit,
                        COALESCE(u.display_name, '') AS created_by_name
                 FROM {$wpdb->prefix}banoks_expenses e
                 LEFT JOIN {$wpdb->users} u ON e.created_by = u.ID
                 WHERE e.branch_key = %s
                 AND e.date BETWEEN %s AND %s
                 ORDER BY e.date ASC, e.created_at ASC",
                $branch_key,
                $start_date,
                $end_date
            )
        );

        $rows = array_merge( $expenses ?: array(), $this->get_report_stock_expense_rows_for_branch( $start_date, $end_date, $branch_key ) );

        usort(
            $rows,
            function ( $a, $b ) {
                $first_date  = ! empty( $a->created_at ) ? $a->created_at : $a->date;
                $second_date = ! empty( $b->created_at ) ? $b->created_at : $b->date;
                return strtotime( $first_date ) <=> strtotime( $second_date );
            }
        );

        return $rows;
    }

    private function get_report_stock_expenses_for_branch( $start_date, $end_date, $branch_key ) {
        $branch_key = sanitize_key( $branch_key );
        if ( Banoks_POS_Repository::STOCK_LOCATION_MANUKAN !== $branch_key ) {
            return 0;
        }

        return $this->get_stock_cash_expenses_for_period( $start_date, $end_date, 'store_cash' );
    }

    private function get_report_stock_expense_rows_for_branch( $start_date, $end_date, $branch_key ) {
        $branch_key = sanitize_key( $branch_key );
        if ( Banoks_POS_Repository::STOCK_LOCATION_MANUKAN !== $branch_key ) {
            return array();
        }

        return $this->get_stock_cash_expense_rows_for_period( $start_date, $end_date, 'store_cash' );
    }

    private function get_branch_stock_report_rows( $start_date, $end_date, $branch_key ) {
        global $wpdb;

        $branch_key = sanitize_key( $branch_key );
        $period_start = $start_date . ' 00:00:00';
        $period_end   = $end_date . ' 23:59:59';

        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DISTINCT i.id, i.item_name, i.unit
                 FROM {$wpdb->prefix}banoks_inventory_movements m
                 JOIN {$wpdb->prefix}banoks_inventory_items i ON m.inventory_item_id = i.id
                 WHERE m.location_key = %s
                 AND m.created_at <= %s
                 ORDER BY i.item_name ASC",
                $branch_key,
                $period_end
            )
        );

        $rows = array();
        foreach ( $items as $item ) {
            $opening = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT new_stock
                     FROM {$wpdb->prefix}banoks_inventory_movements
                     WHERE inventory_item_id = %d
                     AND location_key = %s
                     AND created_at < %s
                     ORDER BY created_at DESC, id DESC
                     LIMIT 1",
                    absint( $item->id ),
                    $branch_key,
                    $period_start
                )
            );

            if ( null === $opening ) {
                $first_period_movement = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT old_stock, new_stock, change_amount
                         FROM {$wpdb->prefix}banoks_inventory_movements
                         WHERE inventory_item_id = %d
                         AND location_key = %s
                         AND created_at BETWEEN %s AND %s
                         ORDER BY created_at ASC, id ASC
                         LIMIT 1",
                        absint( $item->id ),
                        $branch_key,
                        $period_start,
                        $period_end
                    )
                );

                if ( $first_period_movement ) {
                    $opening = floatval( $first_period_movement->old_stock );
                    if ( $opening <= 0 && floatval( $first_period_movement->change_amount ) > 0 ) {
                        $opening = floatval( $first_period_movement->new_stock );
                    }
                }
            }

            if ( null === $opening ) {
                $opening = $this->get_inventory_location_stock( absint( $item->id ), $branch_key );
            }

            $ending = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT new_stock
                     FROM {$wpdb->prefix}banoks_inventory_movements
                     WHERE inventory_item_id = %d
                     AND location_key = %s
                     AND created_at <= %s
                     ORDER BY created_at DESC, id DESC
                     LIMIT 1",
                    absint( $item->id ),
                    $branch_key,
                    $period_end
                )
            );

            if ( null === $ending ) {
                $ending = $opening;
            }

            $period_moves = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT
                        SUM(CASE WHEN change_amount > 0 THEN change_amount ELSE 0 END) AS stock_in,
                        SUM(CASE WHEN change_amount < 0 THEN ABS(change_amount) ELSE 0 END) AS stock_out,
                        COUNT(*) AS movement_count,
                        SUM(CASE WHEN old_stock > 0 AND new_stock <= 0 THEN 1 ELSE 0 END) AS sold_out_count,
                        SUM(CASE WHEN old_stock <= 0 AND change_amount > 0 THEN 1 ELSE 0 END) AS restock_count
                     FROM {$wpdb->prefix}banoks_inventory_movements
                     WHERE inventory_item_id = %d
                     AND location_key = %s
                     AND created_at BETWEEN %s AND %s",
                    absint( $item->id ),
                    $branch_key,
                    $period_start,
                    $period_end
                )
            );

            $movement_count = $period_moves ? intval( $period_moves->movement_count ) : 0;
            if ( 0 === $movement_count && floatval( $opening ) === floatval( $ending ) ) {
                continue;
            }

            $activity_note = $movement_count . ' movement' . ( 1 === $movement_count ? '' : 's' );
            if ( $period_moves && intval( $period_moves->sold_out_count ) > 0 && intval( $period_moves->restock_count ) > 0 ) {
                $activity_note = 'Sold out then restocked; ' . $activity_note;
            } elseif ( $period_moves && intval( $period_moves->sold_out_count ) > 0 ) {
                $activity_note = 'Sold out during period; ' . $activity_note;
            } elseif ( $period_moves && intval( $period_moves->restock_count ) > 0 ) {
                $activity_note = 'Restocked during period; ' . $activity_note;
            }

            $rows[] = array(
                'item_name'      => $item->item_name,
                'unit'           => $item->unit,
                'opening_stock'  => floatval( $opening ),
                'stock_in'       => $period_moves ? floatval( $period_moves->stock_in ) : 0,
                'stock_out'      => $period_moves ? floatval( $period_moves->stock_out ) : 0,
                'ending_stock'   => floatval( $ending ),
                'activity_note'  => $activity_note,
            );
        }

        return $rows;
    }

    private function get_combined_top_products( $start_date, $end_date, $branch_key = '' ) {
        global $wpdb;

        $branch_key = sanitize_key( $branch_key );
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT product_name, SUM(total_qty) AS total_qty, SUM(total_revenue) AS total_revenue
                 FROM (
                    SELECT p.product_name, SUM(oi.qty) AS total_qty, SUM(oi.sub_total) AS total_revenue
                    FROM {$wpdb->prefix}banoks_order_items oi
                    JOIN {$wpdb->prefix}banoks_items p ON oi.product_id = p.product_id
                    JOIN {$wpdb->prefix}banoks_orders o ON oi.order_id = o.order_id
                    WHERE o.date BETWEEN %s AND %s AND o.status = 'completed' AND o.branch_key = %s
                    GROUP BY p.product_name
                    UNION ALL
                    SELECT oi.product_name, SUM(oi.quantity) AS total_qty, SUM(oi.subtotal) AS total_revenue
                    FROM {$wpdb->prefix}banoks_online_order_items oi
                    JOIN {$wpdb->prefix}banoks_online_orders o ON oi.online_order_id = o.id
                    WHERE DATE(o.created_at) BETWEEN %s AND %s AND o.order_status = 'completed' AND o.branch_key = %s
                    GROUP BY oi.product_name
                 ) product_sales
                 GROUP BY product_name
                 ORDER BY total_qty DESC
                 LIMIT 10",
                $start_date,
                $end_date,
                $branch_key,
                $start_date,
                $end_date,
                $branch_key
            )
        );
    }

    /**
     * Get combined walk-in and online daily sales.
     *
     * @since    1.0.9
     * @param    string $start_date Start date.
     * @param    string $end_date End date.
     * @return   array
     */
    private function get_combined_daily_sales( $start_date, $end_date, $branch_key = '' ) {
        global $wpdb;

        $branch_key = sanitize_key( $branch_key );
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT date, SUM(total) AS total
                 FROM (
                    SELECT date, SUM(grand_total) AS total
                    FROM {$wpdb->prefix}banoks_orders
                    WHERE date BETWEEN %s AND %s AND status = 'completed' AND branch_key = %s
                    GROUP BY date
                    UNION ALL
                    SELECT DATE(created_at) AS date, SUM(total_amount) AS total
                    FROM {$wpdb->prefix}banoks_online_orders
                    WHERE DATE(created_at) BETWEEN %s AND %s AND order_status = 'completed' AND branch_key = %s
                    GROUP BY DATE(created_at)
                 ) daily
                 GROUP BY date
                 ORDER BY date ASC",
                $start_date,
                $end_date,
                $branch_key,
                $start_date,
                $end_date,
                $branch_key
            )
        );
    }

    /**
     * Get combined walk-in and online hourly sales for one report day.
     *
     * @since    1.0.12
     * @param    string $date Report date.
     * @param    string $branch_key Branch key.
     * @return   array
     */
    private function get_combined_hourly_sales( $date, $branch_key = '' ) {
        global $wpdb;

        $branch_key = sanitize_key( $branch_key );
        $rows       = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT sale_hour, SUM(total) AS total
                 FROM (
                    SELECT HOUR(entry_timestamp) AS sale_hour, SUM(grand_total) AS total
                    FROM {$wpdb->prefix}banoks_orders
                    WHERE date = %s AND status = 'completed' AND branch_key = %s
                    GROUP BY HOUR(entry_timestamp)
                    UNION ALL
                    SELECT HOUR(created_at) AS sale_hour, SUM(total_amount) AS total
                    FROM {$wpdb->prefix}banoks_online_orders
                    WHERE DATE(created_at) = %s AND order_status = 'completed' AND branch_key = %s
                    GROUP BY HOUR(created_at)
                 ) hourly
                 GROUP BY sale_hour
                 ORDER BY sale_hour ASC",
                $date,
                $branch_key,
                $date,
                $branch_key
            )
        );
        $totals     = array();

        foreach ( $rows as $row ) {
            $totals[ intval( $row->sale_hour ) ] = floatval( $row->total );
        }

        $hourly_sales = array();
        for ( $hour = 0; $hour < 24; $hour++ ) {
            $hourly_sales[] = (object) array(
                'date'  => wp_date( 'g A', strtotime( sprintf( '%s %02d:00:00', $date, $hour ) ) ),
                'total' => isset( $totals[ $hour ] ) ? $totals[ $hour ] : 0,
            );
        }

        return $hourly_sales;
    }

    /**
     * Get combined transaction rows for the report table.
     *
     * @since    1.0.10
     * @param    string $start_date Start date.
     * @param    string $end_date End date.
     * @return   array
     */
    private function get_combined_report_transaction_table( $start_date, $end_date, $branch_key = '' ) {
        global $wpdb;

        $branch_key = sanitize_key( $branch_key );
        $start_date = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $start_date ) ? $start_date : '';
        $end_date   = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $end_date ) ? $end_date : '';

        $walkin_where = array(
            "o.status IN ('completed', 'cancelled')",
            'o.branch_key = %s',
        );
        $walkin_args  = array( $branch_key );
        $online_where = array(
            "oo.order_status IN ('completed', 'cancelled')",
            'oo.branch_key = %s',
        );
        $online_args  = array( $branch_key );

        if ( '' !== $start_date ) {
            $walkin_where[] = 'o.date >= %s';
            $walkin_args[]  = $start_date;
            $online_where[] = 'DATE(oo.created_at) >= %s';
            $online_args[]  = $start_date;
        }

        if ( '' !== $end_date ) {
            $walkin_where[] = 'o.date <= %s';
            $walkin_args[]  = $end_date;
            $online_where[] = 'DATE(oo.created_at) <= %s';
            $online_args[]  = $end_date;
        }

        $walkin_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT CONCAT('BNK-', LPAD(o.order_id, 6, '0')) AS order_id,
                    o.entry_timestamp AS transaction_date,
                    'Walk-in' AS order_type,
                    o.payment_method AS payment_method,
                    o.grand_total AS total_amount,
                    o.status AS status,
                    GROUP_CONCAT(CONCAT_WS('::', p.product_name, oi.qty, oi.unit_price_at_sale, oi.sub_total) ORDER BY p.product_name SEPARATOR '||') AS item_rows
                 FROM {$wpdb->prefix}banoks_orders o
                 LEFT JOIN {$wpdb->prefix}banoks_order_items oi ON o.order_id = oi.order_id
                 LEFT JOIN {$wpdb->prefix}banoks_items p ON oi.product_id = p.product_id
                 WHERE " . implode( ' AND ', $walkin_where ) . "
                 GROUP BY o.order_id, o.payment_method",
                $walkin_args
            )
        );

        $online_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT oo.online_order_id AS order_id,
                    oo.created_at AS transaction_date,
                    'Online' AS order_type,
                    oo.payment_method AS payment_method,
                    oo.total_amount AS total_amount,
                    oo.order_status AS status,
                    GROUP_CONCAT(CONCAT_WS('::', oi.product_name, oi.quantity, oi.price, oi.subtotal) ORDER BY oi.product_name SEPARATOR '||') AS item_rows
                 FROM {$wpdb->prefix}banoks_online_orders oo
                 LEFT JOIN {$wpdb->prefix}banoks_online_order_items oi ON oo.id = oi.online_order_id
                 WHERE " . implode( ' AND ', $online_where ) . "
                 GROUP BY oo.id, oo.payment_method",
                $online_args
            )
        );

        $transactions = array_merge( $walkin_rows ?: array(), $online_rows ?: array() );

        foreach ( $transactions as $transaction ) {
            $transaction->items_detail = $this->parse_report_transaction_items( $transaction->item_rows );
        }

        usort(
            $transactions,
            function ( $a, $b ) {
                return strtotime( $b->transaction_date ) <=> strtotime( $a->transaction_date );
            }
        );

        return $transactions;
    }

    /**
     * Parse transaction item rows for the clickable report modal.
     *
     * @since    1.0.10
     * @param    string $item_rows Concatenated item data.
     * @return   array
     */
    private function parse_report_transaction_items( $item_rows ) {
        $items = array();

        if ( empty( $item_rows ) ) {
            return $items;
        }

        foreach ( explode( '||', $item_rows ) as $item_row ) {
            $parts = explode( '::', $item_row );
            if ( count( $parts ) < 4 ) {
                continue;
            }

            $items[] = array(
                'name'     => sanitize_text_field( $parts[0] ),
                'quantity' => intval( $parts[1] ),
                'price'    => floatval( $parts[2] ),
                'subtotal' => floatval( $parts[3] ),
            );
        }

        return $items;
    }

    /**
     * Build a basic text PDF without external dependencies.
     *
     * @since    1.0.4
     * @param    array $lines Report lines.
     * @return   string
     */
    private function build_simple_report_pdf( $lines ) {
        $pages      = array();
        $page_lines = array();
        $y          = 790;

        foreach ( $lines as $line ) {
            if ( isset( $line['type'] ) && 'divider' === $line['type'] ) {
                if ( $y < 52 ) {
                    $pages[]    = $page_lines;
                    $page_lines = array();
                    $y          = 790;
                }

                $page_lines[] = array(
                    'type'  => 'divider',
                    'style' => 'divider',
                    'x'     => 42,
                    'y'     => $y,
                );
                $y -= 18;
                continue;
            }

            $style = isset( $line['style'] ) ? $line['style'] : 'normal';
            $text  = isset( $line['text'] ) ? $line['text'] : '';
            $align = isset( $line['align'] ) ? $line['align'] : 'left';
            $parts = $this->wrap_pdf_text( $text, in_array( $style, array( 'title', 'heading', 'section' ), true ) ? 78 : 96 );

            foreach ( $parts as $part ) {
                if ( $y < 52 ) {
                    $pages[]    = $page_lines;
                    $page_lines = array();
                    $y          = 790;
                }

                $page_lines[] = array(
                    'text'  => $part,
                    'style' => $style,
                    'align' => $align,
                    'x'     => 42,
                    'y'     => $y,
                );

                $y -= in_array( $style, array( 'title', 'heading', 'section' ), true ) ? 22 : 17;
            }
        }

        if ( ! empty( $page_lines ) ) {
            $pages[] = $page_lines;
        }

        if ( empty( $pages ) ) {
            $pages[] = array(
                array(
                    'text'  => 'No report data available.',
                    'style' => 'normal',
                    'x'     => 42,
                    'y'     => 790,
                ),
            );
        }

        $objects  = array();
        $catalog  = 1;
        $pages_id = 2;
        $font_id  = 3;
        $mono_font_id = 4;
        $next_id  = 5;
        $page_ids = array();

        foreach ( $pages as $page ) {
            $page_id    = $next_id++;
            $content_id = $next_id++;
            $page_ids[] = $page_id;

            $stream = '';
            foreach ( $page as $entry ) {
                if ( isset( $entry['type'] ) && 'divider' === $entry['type'] ) {
                    $stream .= "[] 0 d\n";
                    $stream .= "0.7 w\n";
                    $stream .= "[4 4] 0 d\n";
                    $stream .= '42 ' . intval( $entry['y'] ) . " m\n";
                    $stream .= '553 ' . intval( $entry['y'] ) . " l\n";
                    $stream .= "S\n";
                    $stream .= "[] 0 d\n";
                    continue;
                }

                $font = 'F1';
                $size = 'normal' === $entry['style'] ? 11 : 12;
                if ( 'table_header' === $entry['style'] ) {
                    $size = 10;
                    $font = 'F2';
                } elseif ( 'table_row' === $entry['style'] ) {
                    $size = 10;
                    $font = 'F2';
                }
                if ( 'title' === $entry['style'] ) {
                    $size = 18;
                } elseif ( 'heading' === $entry['style'] || 'section' === $entry['style'] ) {
                    $size = 15;
                }
                $x = intval( $entry['x'] );
                if ( isset( $entry['align'] ) && 'center' === $entry['align'] ) {
                    $text_width_factor = 'title' === $entry['style'] ? 0.56 : 0.50;
                    $x = max( 42, intval( ( 595 - ( strlen( $entry['text'] ) * $size * $text_width_factor ) ) / 2 ) );
                }
                $stream .= "BT\n";
                $stream .= '/' . $font . ' ' . $size . " Tf\n";
                $stream .= '1 0 0 1 ' . $x . ' ' . intval( $entry['y'] ) . " Tm\n";
                $stream .= '(' . $this->escape_pdf_text( $entry['text'] ) . ") Tj\n";
                $stream .= "ET\n";
            }

            $objects[ $content_id ] = "<< /Length " . strlen( $stream ) . " >>\nstream\n" . $stream . "endstream";
            $objects[ $page_id ]    = "<< /Type /Page /Parent {$pages_id} 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 {$font_id} 0 R /F2 {$mono_font_id} 0 R >> >> /Contents {$content_id} 0 R >>";
        }

        $objects[ $catalog ] = "<< /Type /Catalog /Pages {$pages_id} 0 R >>";
        $kids = array();
        foreach ( $page_ids as $page_id ) {
            $kids[] = $page_id . ' 0 R';
        }
        $objects[ $pages_id ] = '<< /Type /Pages /Kids [ ' . implode( ' ', $kids ) . ' ] /Count ' . count( $page_ids ) . ' >>';
        $objects[ $font_id ]  = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[ $mono_font_id ] = '<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>';

        ksort( $objects );
        $pdf     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = array( 0 );

        foreach ( $objects as $id => $object ) {
            $offsets[ $id ] = strlen( $pdf );
            $pdf .= $id . " 0 obj\n" . $object . "\nendobj\n";
        }

        $xref = strlen( $pdf );
        $pdf .= "xref\n0 " . ( count( $objects ) + 1 ) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ( $i = 1; $i <= count( $objects ); $i++ ) {
            $pdf .= sprintf( "%010d 00000 n \n", $offsets[ $i ] );
        }

        $pdf .= "trailer\n<< /Size " . ( count( $objects ) + 1 ) . " /Root {$catalog} 0 R >>\n";
        $pdf .= "startxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    /**
     * Wrap PDF text into fixed-width rows.
     *
     * @since    1.0.4
     * @param    string $text Text.
     * @param    int    $width Width.
     * @return   array
     */
    private function wrap_pdf_text( $text, $width ) {
        $text = trim( wp_strip_all_tags( (string) $text ) );

        if ( '' === $text ) {
            return array( '' );
        }

        return explode( "\n", wordwrap( $text, $width, "\n", true ) );
    }

    /**
     * Escape PDF text.
     *
     * @since    1.0.4
     * @param    string $text Text.
     * @return   string
     */
    private function escape_pdf_text( $text ) {
        $text = preg_replace( '/[^\x09\x0A\x0D\x20-\x7E]/', '', (string) $text );
        return str_replace( array( '\\', '(', ')' ), array( '\\\\', '\\(', '\\)' ), $text );
    }

    /**
     * Format a fixed-width PDF table row.
     *
     * @since    1.0.4
     * @param    array $cells Cell values.
     * @param    array $widths Cell widths.
     * @return   string
     */
    private function format_pdf_row( $cells, $widths ) {
        $parts = array();

        foreach ( $cells as $index => $cell ) {
            $width = isset( $widths[ $index ] ) ? intval( $widths[ $index ] ) : 12;
            $cell  = preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $cell ) );
            $cell  = strlen( $cell ) > $width ? substr( $cell, 0, max( 0, $width - 3 ) ) . '...' : $cell;
            $parts[] = str_pad( $cell, $width );
        }

        return implode( ' | ', $parts );
    }

    /**
     * Format report dates numerically for the PDF header.
     *
     * @since    1.0.4
     * @param    string $date Date.
     * @return   string
     */
    private function format_report_date_numeric( $date ) {
        return wp_date( 'm/d/Y', strtotime( $date ) );
    }
}
