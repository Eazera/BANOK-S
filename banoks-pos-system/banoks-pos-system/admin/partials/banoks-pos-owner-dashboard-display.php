<?php
/**
 * Owner dashboard.
 *
 * @package Banoks_POS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$dashboard_chart_style = sprintf(
    '--banoks-expense-pct:%s%%;--banoks-final-pct:%s%%;',
    esc_attr( number_format( $dashboard_expense_pct, 2, '.', '' ) ),
    esc_attr( number_format( $dashboard_final_pct, 2, '.', '' ) )
);
?>

<div class="wrap banoks-pos-admin banoks-pos-page banoks-owner-dashboard-page">
    <div class="products-header">
        <div class="header-info">
            <h1>Dashboard</h1>
            <p>Manage approvals, stock, cash, reports, and setup from one place.</p>
        </div>
    </div>

    <?php if ( ! empty( $message ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
    <?php endif; ?>
    <?php if ( ! empty( $error ) ) : ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ); ?></p></div>
    <?php endif; ?>

    <section class="banoks-owner-sales-summary">
        <div class="banoks-owner-sales-header">
            <div>
                <h2><?php echo esc_html( $dashboard_branch_name ); ?></h2>
                <p>Date: <span data-banoks-dashboard-date><?php echo esc_html( wp_date( 'F j, Y', strtotime( $today ) ) ); ?></span></p>
            </div>
        </div>

        <div class="banoks-owner-sales-layout">
            <div class="banoks-stats-grid banoks-owner-sales-cards">
                <div class="stat-card" data-banoks-dashboard-card="sales">
                    <h3>Today's Sales</h3>
                    <p class="amount" data-banoks-dashboard-sales data-banoks-dashboard-value="<?php echo esc_attr( number_format( $dashboard_total_sales, 2, '.', '' ) ); ?>">&#8369;<?php echo esc_html( number_format( $dashboard_total_sales, 2 ) ); ?></p>
                </div>
                <div class="stat-card" data-banoks-dashboard-card="expenses">
                    <h3>Today's Expenses</h3>
                    <p class="amount" data-banoks-dashboard-expenses data-banoks-dashboard-value="<?php echo esc_attr( number_format( $dashboard_total_expenses, 2, '.', '' ) ); ?>">&#8369;<?php echo esc_html( number_format( $dashboard_total_expenses, 2 ) ); ?></p>
                </div>
                <div class="stat-card highlighted" data-banoks-dashboard-card="final">
                    <h3>Today's Final Sale</h3>
                    <p class="amount" data-banoks-dashboard-final data-banoks-dashboard-value="<?php echo esc_attr( number_format( $dashboard_final_sale, 2, '.', '' ) ); ?>">&#8369;<?php echo esc_html( number_format( $dashboard_final_sale, 2 ) ); ?></p>
                </div>
            </div>

            <div class="banoks-owner-sales-chart" aria-label="Today's sales split">
                <div class="banoks-owner-pie" style="<?php echo esc_attr( $dashboard_chart_style ); ?>" data-banoks-dashboard-pie>
                    <span data-banoks-dashboard-final-pct><?php echo esc_html( number_format( $dashboard_final_pct, 0 ) ); ?>%</span>
                </div>
                <div class="banoks-owner-chart-legend">
                    <span><i class="is-final"></i>Final sale</span>
                    <span><i class="is-expense"></i>Expenses</span>
                </div>
            </div>
        </div>
    </section>

    <div class="banoks-owner-card-grid">
        <?php foreach ( $owner_cards as $card ) : ?>
            <?php if ( ! empty( $card['modal'] ) ) : ?>
                <button type="button" class="banoks-owner-card banoks-open-owner-branch-picker" data-target="#<?php echo esc_attr( $card['modal'] ); ?>">
                    <strong>
                        <?php echo esc_html( $card['label'] ); ?>
                        <?php if ( ! empty( $card['badge'] ) ) : ?>
                            <span class="banoks-owner-card-badge"><?php echo esc_html( number_format_i18n( absint( $card['badge'] ) ) ); ?></span>
                        <?php endif; ?>
                    </strong>
                    <span><?php echo esc_html( $card['desc'] ); ?></span>
                </button>
            <?php else : ?>
                <a class="banoks-owner-card" href="<?php echo esc_url( $card['url'] ); ?>">
                    <strong>
                        <?php echo esc_html( $card['label'] ); ?>
                        <?php if ( ! empty( $card['badge'] ) ) : ?>
                            <span class="banoks-owner-card-badge"><?php echo esc_html( number_format_i18n( absint( $card['badge'] ) ) ); ?></span>
                        <?php endif; ?>
                    </strong>
                    <span><?php echo esc_html( $card['desc'] ); ?></span>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <div class="banoks-admin-edit-modal banoks-owner-branch-modal" id="banoks-owner-product-branch-modal" aria-hidden="true">
        <div class="banoks-admin-edit-dialog" role="dialog" aria-modal="true" aria-labelledby="banoks-owner-product-branch-title">
            <div class="banoks-admin-edit-header">
                <div>
                    <h2 id="banoks-owner-product-branch-title">Choose a Branch</h2>
                    <p>Select the branch for Product Management stock availability.</p>
                </div>
                <button type="button" class="banoks-admin-edit-close" aria-label="Close branch chooser">&times;</button>
            </div>

            <div class="banoks-owner-branch-grid">
                <?php foreach ( $owner_product_branches as $branch ) : ?>
                    <a class="banoks-owner-branch-card" href="<?php echo esc_url( admin_url( 'admin.php?page=banoks-pos-products&branch_key=' . sanitize_key( $branch->branch_key ) ) ); ?>">
                        <strong><?php echo esc_html( $branch->branch_name ); ?></strong>
                        <span>Open Product Management</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

</div>
