<?php
/**
 * Cashier dashboard view.
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

$this->display_admin_header();
$format_card_payment_method = function ( $payment_method ) {
    return 'gcash' === strtolower( (string) $payment_method ) ? 'E-Money' : 'Cash';
};
$active_orders = isset( $active_orders ) ? $active_orders : $orders;
$history_orders = isset( $history_orders ) ? $history_orders : array();
$walkin_status_counts = array(
    'pending'   => 0,
    'preparing' => 0,
    'completed' => 0,
    'cancelled' => 0,
);
foreach ( array_merge( $active_orders, $history_orders ) as $summary_order ) {
    if ( isset( $walkin_status_counts[ $summary_order->status ] ) ) {
        $walkin_status_counts[ $summary_order->status ]++;
    }
}
$walkin_work_statuses = array(
    'pending'   => 'Pending',
    'preparing' => 'Preparing',
);
$default_walkin_view = $walkin_status_counts['pending'] > 0 ? 'pending' : 'preparing';
?>

<div class="wrap banoks-pos-admin banoks-pos-page banoks-dashboard">
    <div class="products-header">
        <div class="header-info">
            <h1>Walk-in Order</h1>
            <p>Daily cash flow, branch stock warnings, and walk-in order queue.</p>
        </div>
    </div>
    <?php if ( ! empty( $critical_inventory_alerts ) ) : ?>
        <div class="banoks-stock-alert-panel banoks-dashboard-stock-alert">
            <div>
                <h2>Critical Stock Alert</h2>
                <p>Stock is critically low and needs attention before more orders are prepared.</p>
            </div>
            <div class="banoks-stock-alert-list">
                <?php foreach ( $critical_inventory_alerts as $alert ) : ?>
                    <span class="banoks-stock-alert-chip <?php echo 'out' === $alert->alert_type ? 'is-out' : 'is-low'; ?>">
                        <strong><?php echo esc_html( $alert->item_name ); ?></strong>
                        <?php echo esc_html( $alert->formatted_stock ); ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="banoks-online-status-tabs banoks-walkin-status-tabs" role="tablist" aria-label="Walk-in order views">
        <?php foreach ( $walkin_work_statuses as $status_key => $status_name ) : ?>
            <button type="button" class="button banoks-walkin-status-tab <?php echo $default_walkin_view === $status_key ? 'is-active' : ''; ?>" data-walkin-view="<?php echo esc_attr( $status_key ); ?>">
                <?php echo esc_html( $status_name ); ?> <span><?php echo esc_html( $walkin_status_counts[ $status_key ] ); ?></span>
            </button>
        <?php endforeach; ?>
        <button type="button" class="button banoks-walkin-status-tab" data-walkin-view="history">
            Order History <span><?php echo esc_html( count( $history_orders ) ); ?></span>
        </button>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=banoks-pos-pos&date=' . rawurlencode( $active_date ) ) ); ?>" class="button button-large button-secondary banoks-walkin-new-order-button">New Order</a>
    </div>

    <div class="banoks-online-panel banoks-walkin-orders-panel">
        <?php foreach ( $walkin_work_statuses as $status_key => $status_name ) : ?>
            <section class="banoks-walkin-status-section <?php echo $default_walkin_view === $status_key ? 'is-active' : ''; ?>" data-walkin-section="<?php echo esc_attr( $status_key ); ?>">
                <div class="banoks-online-section-header">
                    <div>
                        <h2><?php echo esc_html( $status_name ); ?> Orders</h2>
                        <p><?php echo esc_html( $walkin_status_counts[ $status_key ] ); ?> order<?php echo 1 === intval( $walkin_status_counts[ $status_key ] ) ? '' : 's'; ?> in this status.</p>
                    </div>
                </div>
                <?php if ( ! empty( $active_orders ) && $walkin_status_counts[ $status_key ] > 0 ) : ?>
                    <div class="order-grid">
                        <?php
                        foreach ( $active_orders as $order ) :
                            if ( $status_key !== $order->status ) {
                                continue;
                            }
                            include __DIR__ . '/banoks-pos-walkin-order-card.php';
                        endforeach;
                        ?>
                    </div>
                <?php else : ?>
                    <div class="empty-state">No <?php echo esc_html( strtolower( $status_name ) ); ?> walk-in orders right now.</div>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>

        <section class="banoks-walkin-status-section" data-walkin-section="history">
            <div class="banoks-online-section-header">
                <div>
                    <h2>Order History</h2>
                    <p>Search and filter recent walk-in order transactions.</p>
                </div>
                <button type="button" class="button banoks-finance-filter-trigger banoks-open-owner-request" data-target="#banoks-walkin-filter-modal">
                    Filter
                </button>
                <div class="banoks-online-filters banoks-walkin-filters" style="display:none;">
                    <input type="search" id="order-search" value="<?php echo esc_attr( ! empty( $search_query ) ? $search_query : 'BNK-ORD-' ); ?>" placeholder="Search Order ID">
                    <select id="banoks-order-status-filter">
                        <option value="all" <?php selected( $status_filter, 'all' ); ?>>All History</option>
                        <option value="completed" <?php selected( $status_filter, 'completed' ); ?>>Completed</option>
                        <option value="cancelled" <?php selected( $status_filter, 'cancelled' ); ?>>Cancelled</option>
                    </select>
                    <input type="date" id="banoks-dashboard-date" value="<?php echo esc_attr( $active_date ); ?>" data-has-date-filter="<?php echo $has_date_param ? '1' : '0'; ?>">
                </div>
            </div>
            <div class="order-grid banoks-walkin-history-grid">
                <?php if ( ! empty( $history_orders ) ) : ?>
                    <?php foreach ( $history_orders as $order ) : ?>
                        <?php include __DIR__ . '/banoks-pos-walkin-order-card.php'; ?>
                    <?php endforeach; ?>
                <?php else : ?>
                    <div class="empty-msg">No walk-in order history yet.</div>
                <?php endif; ?>
            </div>
            <div class="empty-msg banoks-walkin-filter-empty" style="display:none;">No walk-in orders match your filters.</div>
        </section>
    </div>

    <div class="banoks-admin-edit-modal banoks-walkin-filter-modal" id="banoks-walkin-filter-modal" aria-hidden="true">
        <div class="banoks-admin-edit-dialog" role="dialog" aria-modal="true" aria-labelledby="banoks-walkin-filter-title">
            <div class="banoks-admin-edit-header">
                <div>
                    <h2 id="banoks-walkin-filter-title">Filter Walk-in History</h2>
                    <p>Search by order ID, status, and date.</p>
                </div>
                <button type="button" class="banoks-admin-edit-close" aria-label="Close walk-in filters">&times;</button>
            </div>
            <form class="banoks-walkin-filter-form">
                <div class="banoks-walkin-filter-grid">
                    <label for="banoks-walkin-filter-search">
                        Search
                        <input type="search" id="banoks-walkin-filter-search" value="<?php echo esc_attr( ! empty( $search_query ) ? $search_query : 'BNK-ORD-' ); ?>" placeholder="Search Order ID">
                    </label>
                    <label for="banoks-walkin-filter-status">
                        Status
                        <select id="banoks-walkin-filter-status">
                            <option value="all" <?php selected( $status_filter, 'all' ); ?>>All History</option>
                            <option value="completed" <?php selected( $status_filter, 'completed' ); ?>>Completed</option>
                            <option value="cancelled" <?php selected( $status_filter, 'cancelled' ); ?>>Cancelled</option>
                        </select>
                    </label>
                    <label for="banoks-walkin-filter-date">
                        Date
                        <input type="date" id="banoks-walkin-filter-date" value="<?php echo esc_attr( $active_date ); ?>" data-has-date-filter="<?php echo $has_date_param ? '1' : '0'; ?>">
                    </label>
                </div>
                <div class="modal-actions">
                    <button type="button" class="button banoks-walkin-filter-clear">Clear</button>
                    <button type="button" class="button banoks-admin-edit-cancel">Cancel</button>
                    <button type="submit" class="button button-primary">Apply Filter</button>
                </div>
            </form>
        </div>
    </div>
</div>
