<?php
/**
 * Online orders admin page.
 *
 * @package Banoks_POS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$online_status_counts = array(
    'pending'    => 0,
    'preparing'  => 0,
    'ready_for_pickup' => 0,
    'delivering' => 0,
    'completed'  => 0,
    'cancelled'  => 0,
    'rejected'   => 0,
);
$online_pending_total = 0;

foreach ( $online_orders as $summary_order ) {
    $summary_status = 'verifying' === $summary_order->order_status ? 'pending' : $summary_order->order_status;
    if ( isset( $online_status_counts[ $summary_status ] ) ) {
        $online_status_counts[ $summary_status ]++;
    }

    if ( in_array( $summary_order->order_status, array( 'pending', 'verifying' ), true ) ) {
        $online_pending_total++;
    }

}

$online_work_statuses = array(
    'pending'          => 'Pending',
    'preparing'        => 'Preparing',
    'ready_for_pickup' => 'Ready for Pickup',
    'delivering'       => 'Delivering',
);
$online_history_statuses = array(
    'pending'          => 'Pending',
    'preparing'        => 'Preparing',
    'ready_for_pickup' => 'Ready for Pickup',
    'delivering'       => 'Delivering',
    'completed'        => 'Completed',
    'cancelled'        => 'Cancelled',
    'rejected'         => 'Rejected',
);
$default_online_view = 'pending';
foreach ( $online_work_statuses as $status_key => $status_name ) {
    if ( ! empty( $online_status_counts[ $status_key ] ) ) {
        $default_online_view = $status_key;
        break;
    }
}

if ( ! function_exists( 'banoks_pos_render_online_order_card' ) ) {
    function banoks_pos_render_online_order_card( $order, $online_related, $show_actions = true, $extra_class = '' ) {
        $order_items       = isset( $online_related['items'][ $order->id ] ) ? $online_related['items'][ $order->id ] : array();
        $proofs            = isset( $online_related['proofs'][ $order->id ] ) ? $online_related['proofs'][ $order->id ] : array();
        $stock_warnings    = isset( $online_related['stock_warnings'][ $order->id ] ) ? $online_related['stock_warnings'][ $order->id ] : array();
        $proof             = ! empty( $proofs ) ? $proofs[0] : null;
        $proof_status      = $proof ? $proof->status : '';
        $proof_url         = '';
        if ( $proof && ! empty( $proof->screenshot_url ) ) {
            if ( 0 === strpos( $proof->screenshot_url, 'banoks-private://' ) ) {
                $proof_url = wp_nonce_url(
                    add_query_arg(
                        array(
                            'action'   => 'banoks_view_payment_proof',
                            'proof_id' => absint( $proof->id ),
                        ),
                        admin_url( 'admin-post.php' )
                    ),
                    'banoks_view_payment_proof_' . absint( $proof->id )
                );
            } else {
                $proof_url = $proof->screenshot_url;
            }
        }
        $fulfillment_type  = ! empty( $order->fulfillment_type ) && 'pickup' === $order->fulfillment_type ? 'pickup' : 'delivery';
        $fulfillment_label = 'pickup' === $fulfillment_type ? 'Pickup' : 'Delivery';
        $next_statuses     = array(
            'pending'   => array( 'preparing' => 'Preparing' ),
            'verifying' => array( 'preparing' => 'Preparing' ),
        );

        if ( 'pickup' === $fulfillment_type ) {
            $next_statuses['preparing']        = array( 'ready_for_pickup' => 'Ready for Pickup' );
            $next_statuses['ready_for_pickup'] = array( 'completed' => 'Complete Order' );
        } else {
            $next_statuses['preparing']  = array( 'delivering' => 'Deliver Order' );
            $next_statuses['delivering'] = array( 'completed' => 'Complete Order' );
        }

        $next_status_key = isset( $next_statuses[ $order->order_status ] ) ? array_key_first( $next_statuses[ $order->order_status ] ) : '';
        $can_cancel      = in_array( $order->order_status, array( 'pending', 'verifying', 'preparing' ), true );
        $status_label    = 'verifying' === $order->order_status ? 'Pending' : ucwords( str_replace( '_', ' ', $order->order_status ) );
        $payment_gateway = ! empty( $order->payment_gateway ) ? strtolower( (string) $order->payment_gateway ) : '';
        $payment_label   = strtoupper( str_replace( '_', ' ', $order->payment_method ) ) . ' - ' . ucwords( str_replace( '_', ' ', $order->payment_status ) );
        if ( 'paymongo' === $payment_gateway ) {
            $payment_label .= ' via PayMongo';
        }
        $payment_badge   = 'gcash' === strtolower( (string) $order->payment_method ) ? 'E-Money' : 'Cash';
        $modal_items     = array();

        foreach ( $order_items as $item ) {
            $modal_items[] = array(
                'name'     => $item->product_name,
                'quantity' => intval( $item->quantity ),
                'price'    => floatval( $item->price ),
                'subtotal' => floatval( $item->subtotal ),
            );
        }

        $order_created_at = date_create_immutable_from_format( 'Y-m-d H:i:s', $order->created_at, wp_timezone() );
        $order_created_ts = $order_created_at ? $order_created_at->getTimestamp() * 1000 : strtotime( $order->created_at ) * 1000;
        $order_is_aging   = $order_created_at && in_array( $order->order_status, array( 'pending', 'verifying', 'preparing', 'ready_for_pickup', 'delivering' ), true ) && ( time() - $order_created_at->getTimestamp() ) >= 30 * 60;
        ?>
        <div class="order-card banoks-online-order-row banoks-aging-order-card <?php echo esc_attr( trim( $extra_class . ' ' . ( $order_is_aging ? 'banoks-order-aging-warning' : '' ) ) ); ?>"
             role="button"
             tabindex="0"
             data-status="<?php echo esc_attr( $order->order_status ); ?>"
             data-order-created-ts="<?php echo esc_attr( $order_created_ts ); ?>"
             data-fulfillment="<?php echo esc_attr( $fulfillment_type ); ?>"
             data-payment="<?php echo esc_attr( $order->payment_method ); ?>"
             data-search="<?php echo esc_attr( strtolower( $order->online_order_id . ' ' . $order->customer_name . ' ' . $order->customer_phone . ' ' . $order->delivery_area_name . ' ' . $fulfillment_label ) ); ?>"
             data-public-id="<?php echo esc_attr( $order->online_order_id ); ?>"
             data-created="<?php echo esc_attr( wp_date( 'M d, Y g:i A', strtotime( $order->created_at ) ) ); ?>"
             data-customer="<?php echo esc_attr( $order->customer_name ); ?>"
             data-phone="<?php echo esc_attr( $order->customer_phone ); ?>"
             data-area="<?php echo esc_attr( $order->delivery_area_name ); ?>"
             data-address="<?php echo esc_attr( $order->delivery_address ); ?>"
             data-fulfillment-label="<?php echo esc_attr( $fulfillment_label ); ?>"
             data-payment-label="<?php echo esc_attr( $payment_label ); ?>"
             data-payment-status="<?php echo esc_attr( $order->payment_status ); ?>"
             data-proof-id="<?php echo esc_attr( $proof ? $proof->id : 0 ); ?>"
             data-proof-status="<?php echo esc_attr( $proof_status ); ?>"
             data-proof-url="<?php echo esc_url( $proof_url ); ?>"
             data-driver="<?php echo esc_attr( trim( $order->driver_name . ' ' . $order->driver_contact ) ); ?>"
             data-notes="<?php echo esc_attr( $order->notes ); ?>"
             data-total="<?php echo esc_attr( number_format( floatval( $order->total_amount ), 2 ) ); ?>"
             data-items="<?php echo esc_attr( wp_json_encode( $modal_items ) ); ?>">
            <div class="order-header">
                <div class="id-date-wrap">
                    <span class="order-id"><?php echo esc_html( $order->online_order_id ); ?></span>
                    <span class="order-date"><?php echo esc_html( wp_date( 'M d, g:i A', strtotime( $order->created_at ) ) ); ?></span>
                    <span class="banoks-order-age-warning">30+ min waiting</span>
                </div>
                <div class="status-cashier-wrap">
                    <span class="order-status status-<?php echo esc_attr( $order->order_status ); ?>"><?php echo esc_html( $status_label ); ?></span>
                    <span class="banoks-payment-pill payment-<?php echo esc_attr( 'gcash' === strtolower( (string) $order->payment_method ) ? 'gcash' : 'cod' ); ?>"><?php echo esc_html( $payment_badge ); ?></span>
                    <span class="order-cashier"><?php echo esc_html( $fulfillment_label ); ?></span>
                    <span class="order-cashier"><?php echo esc_html( human_time_diff( strtotime( $order->created_at ), current_time( 'timestamp' ) ) . ' ago' ); ?></span>
                </div>
            </div>

            <div class="order-items">
                <?php if ( ! empty( $order_items ) ) : ?>
                    <?php foreach ( $order_items as $item ) : ?>
                        <div class="order-item">
                            <span><?php echo esc_html( $item->product_name ); ?> x <?php echo esc_html( $item->quantity ); ?></span>
                            <span>&#8369;<?php echo esc_html( number_format( floatval( $item->subtotal ), 2 ) ); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <div class="empty-msg">No items found.</div>
                <?php endif; ?>
            </div>

            <?php if ( ! empty( $stock_warnings ) ) : ?>
                <div class="banoks-online-stock-warning">
                    <strong>Stock issue</strong>
                    <span><?php echo esc_html( implode( ' ', $stock_warnings ) ); ?></span>
                </div>
            <?php endif; ?>

            <div class="order-footer">
                <span class="order-total">&#8369;<?php echo esc_html( number_format( floatval( $order->total_amount ), 2 ) ); ?></span>
                <?php if ( $show_actions ) : ?>
                    <div class="order-actions">
                        <?php if ( $next_status_key || $can_cancel ) : ?>
                            <?php if ( 'gcash' === $order->payment_method && in_array( $order->order_status, array( 'pending', 'verifying' ), true ) && 'paid' !== $order->payment_status ) : ?>
                                <?php if ( 'pending_verification' === $order->payment_status && $proof ) : ?>
                                    <button type="button" class="button button-primary banoks-review-payment-button">Review Payment</button>
                                <?php elseif ( 'paymongo' === $payment_gateway && in_array( $order->payment_status, array( 'pending_gateway', 'failed' ), true ) ) : ?>
                                    <span class="description">PayMongo GCash <?php echo esc_html( str_replace( '_', ' ', $order->payment_status ) ); ?>.</span>
                                <?php else : ?>
                                    <span class="description">GCash payment <?php echo esc_html( str_replace( '_', ' ', $order->payment_status ) ); ?>.</span>
                                <?php endif; ?>
                            <?php else : ?>
                                <form method="post" class="banoks-online-status-form">
                                    <?php wp_nonce_field( 'banoks_pos_online_status_action' ); ?>
                                    <input type="hidden" name="banoks_pos_update_online_order_status" value="1">
                                    <input type="hidden" name="online_order_id" value="<?php echo esc_attr( $order->id ); ?>">
                                    <input type="hidden" name="new_status" value="<?php echo esc_attr( $next_status_key ); ?>">
                                    <input type="text" name="driver_name" placeholder="Driver name" class="banoks-driver-field">
                                    <input type="text" name="driver_contact" placeholder="Driver contact" class="banoks-driver-field">
                                    <button type="submit" class="button button-primary banoks-online-action-button" data-next-status="<?php echo esc_attr( $next_status_key ); ?>"><?php echo esc_html( $next_statuses[ $order->order_status ][ $next_status_key ] ); ?></button>
                                </form>
                            <?php endif; ?>
                            <?php if ( $can_cancel ) : ?>
                                <form method="post" class="banoks-online-status-form banoks-online-cancel-form">
                                    <?php wp_nonce_field( 'banoks_pos_online_status_action' ); ?>
                                    <input type="hidden" name="banoks_pos_update_online_order_status" value="1">
                                    <input type="hidden" name="online_order_id" value="<?php echo esc_attr( $order->id ); ?>">
                                    <input type="hidden" name="new_status" value="cancelled">
                                    <input type="hidden" name="status_note" class="banoks-cancel-reason-field" value="">
                                    <button type="submit" class="button banoks-online-action-button banoks-online-cancel-button" data-next-status="cancelled">Cancel Order</button>
                                </form>
                            <?php endif; ?>
                        <?php else : ?>
                            <span class="description">No actions</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
?>

<div class="wrap banoks-pos-admin banoks-pos-page banoks-online-orders-page">
    <div class="products-header">
        <div class="header-info">
            <h1>Online Orders</h1>
            <p>Review payment, prepare, and deliver customer website orders.</p>
        </div>
    </div>

    <?php if ( ! empty( $message ) ) : ?>
        <div class="updated notice is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
    <?php endif; ?>

    <?php if ( ! empty( $error ) ) : ?>
        <div class="error notice is-dismissible"><p><?php echo esc_html( $error ); ?></p></div>
    <?php endif; ?>

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

    <div class="banoks-online-stats">
        <div class="banoks-online-stat-card is-attention"><span>Needs Review</span><strong><?php echo esc_html( $online_pending_total ); ?></strong></div>
        <div class="banoks-online-stat-card"><span>Preparing</span><strong><?php echo esc_html( $online_status_counts['preparing'] ); ?></strong></div>
        <div class="banoks-online-stat-card"><span>Delivering</span><strong><?php echo esc_html( $online_status_counts['delivering'] ); ?></strong></div>
    </div>

    <div class="banoks-online-status-tabs" role="tablist" aria-label="Online order views">
        <?php
        foreach ( $online_work_statuses as $status_key => $status_name ) :
            ?>
            <button type="button" class="button banoks-online-status-tab <?php echo $default_online_view === $status_key ? 'is-active' : ''; ?>" data-online-view="<?php echo esc_attr( $status_key ); ?>">
                <?php echo esc_html( $status_name ); ?> <span><?php echo esc_html( $online_status_counts[ $status_key ] ); ?></span>
            </button>
            <?php
        endforeach;
        ?>
        <button type="button" class="button banoks-online-status-tab banoks-online-history-tab" data-online-view="history">
            Order History <span><?php echo esc_html( count( $online_orders ) ); ?></span>
        </button>
    </div>

    <div class="banoks-online-grid banoks-online-grid-single">
        <div class="banoks-online-panel banoks-online-orders-panel">
            <?php foreach ( $online_work_statuses as $status_key => $status_name ) : ?>
                <section class="banoks-online-status-section <?php echo $default_online_view === $status_key ? 'is-active' : ''; ?>" data-online-section="<?php echo esc_attr( $status_key ); ?>">
                    <div class="banoks-online-section-header">
                        <div>
                            <h2><?php echo esc_html( $status_name ); ?> Orders</h2>
                            <p><?php echo esc_html( $online_status_counts[ $status_key ] ); ?> order<?php echo 1 === intval( $online_status_counts[ $status_key ] ) ? '' : 's'; ?> in this status.</p>
                        </div>
                    </div>

                    <?php if ( ! empty( $online_orders ) && $online_status_counts[ $status_key ] > 0 ) : ?>
                        <div class="order-grid banoks-online-order-grid">
                            <?php
                            foreach ( $online_orders as $order ) :
                                $display_status_key = 'verifying' === $order->order_status ? 'pending' : $order->order_status;
                                if ( $status_key !== $display_status_key ) {
                                    continue;
                                }
                                banoks_pos_render_online_order_card( $order, $online_related, true );
                            endforeach;
                            ?>
                        </div>
                    <?php else : ?>
                        <div class="empty-state">No <?php echo esc_html( strtolower( $status_name ) ); ?> orders right now.</div>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>

            <section class="banoks-online-status-section banoks-online-history-section" data-online-section="history">
                <div class="banoks-online-section-header">
                    <div>
                        <h2>Order History</h2>
                        <p>Search and filter all online order transactions.</p>
                    </div>
                    <button type="button" class="button banoks-finance-filter-trigger banoks-open-owner-request" data-target="#banoks-online-filter-modal">
                        Filter
                    </button>
                    <div class="banoks-online-filters" style="display:none;">
                        <input type="search" id="banoks-online-search" placeholder="Search order, customer, phone, area">
                        <select id="banoks-online-status-filter">
                            <option value="">All Statuses</option>
                            <?php foreach ( $online_history_statuses as $status_key => $status_name ) : ?>
                                <option value="<?php echo esc_attr( $status_key ); ?>"><?php echo esc_html( $status_name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="banoks-online-payment-filter">
                            <option value="">All Payments</option>
                            <option value="cod">COD</option>
                            <option value="pay_at_pickup">Pay at Pickup</option>
                            <option value="gcash">GCash</option>
                        </select>
                    </div>
                </div>

                <?php if ( ! empty( $online_orders ) ) : ?>
                    <div class="order-grid banoks-online-order-grid banoks-online-history-grid">
                        <?php foreach ( $online_orders as $order ) : ?>
                            <?php banoks_pos_render_online_order_card( $order, $online_related, false, 'banoks-online-history-row' ); ?>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div class="empty-state">No online orders yet.</div>
                <?php endif; ?>
                <div class="empty-state banoks-online-filter-empty" style="display:none;">No online orders match your filters.</div>
            </section>
        </div>
    </div>

    <div class="banoks-admin-edit-modal banoks-online-filter-modal" id="banoks-online-filter-modal" aria-hidden="true">
        <div class="banoks-admin-edit-dialog" role="dialog" aria-modal="true" aria-labelledby="banoks-online-filter-title">
            <div class="banoks-admin-edit-header">
                <div>
                    <h2 id="banoks-online-filter-title">Filter Online History</h2>
                    <p>Search by order, customer, status, and payment.</p>
                </div>
                <button type="button" class="banoks-admin-edit-close" aria-label="Close online filters">&times;</button>
            </div>
            <form class="banoks-online-filter-form">
                <div class="banoks-online-filter-grid">
                    <label for="banoks-online-filter-search">
                        Search
                        <input type="search" id="banoks-online-filter-search" placeholder="Search order, customer, phone, area">
                    </label>
                    <label for="banoks-online-filter-status">
                        Status
                        <select id="banoks-online-filter-status">
                            <option value="">All Statuses</option>
                            <?php foreach ( $online_history_statuses as $status_key => $status_name ) : ?>
                                <option value="<?php echo esc_attr( $status_key ); ?>"><?php echo esc_html( $status_name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label for="banoks-online-filter-payment">
                        Payment
                        <select id="banoks-online-filter-payment">
                            <option value="">All Payments</option>
                            <option value="cod">COD</option>
                            <option value="pay_at_pickup">Pay at Pickup</option>
                            <option value="gcash">GCash</option>
                        </select>
                    </label>
                </div>
                <div class="modal-actions">
                    <button type="button" class="button banoks-online-filter-clear">Clear</button>
                    <button type="button" class="button banoks-admin-edit-cancel">Cancel</button>
                    <button type="submit" class="button button-primary">Apply Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="banoks-report-transaction-modal" id="banoks-online-order-detail-modal" aria-hidden="true">
        <div class="banoks-report-transaction-dialog" role="dialog" aria-modal="true" aria-labelledby="banoks-online-order-detail-title">
            <button type="button" class="banoks-report-modal-close" aria-label="Close order details">&times;</button>
            <h2 id="banoks-online-order-detail-title">Online Order Details</h2>
            <div class="banoks-report-transaction-summary">
                <div><span>Order ID</span><strong id="banoks-online-modal-order-id"></strong></div>
                <div><span>Date</span><strong id="banoks-online-modal-date"></strong></div>
                <div><span>Customer</span><strong id="banoks-online-modal-customer"></strong></div>
                <div><span>Type</span><strong id="banoks-online-modal-fulfillment"></strong></div>
                <div><span>Area</span><strong id="banoks-online-modal-area"></strong></div>
                <div><span>Payment</span><strong id="banoks-online-modal-payment"></strong></div>
                <div><span>Total</span><strong id="banoks-online-modal-total"></strong></div>
            </div>
            <p><strong>Contact:</strong> <span id="banoks-online-modal-phone"></span></p>
            <p><strong>Address:</strong> <span id="banoks-online-modal-address"></span></p>
            <p><strong>Payment Proof:</strong> <span id="banoks-online-modal-proof"></span> <a href="#" id="banoks-online-modal-proof-link" target="_blank" rel="noopener" style="display:none;">View Screenshot</a></p>
            <form method="post" id="banoks-online-payment-proof-form" class="banoks-payment-proof-form" style="display:none;">
                <?php wp_nonce_field( 'banoks_pos_payment_proof_action' ); ?>
                <input type="hidden" name="banoks_pos_update_payment_proof" value="1">
                <input type="hidden" name="payment_proof_id" id="banoks-online-modal-proof-id" value="">
                <input type="hidden" name="payment_proof_status" id="banoks-online-modal-proof-status-input" value="">
                <div class="banoks-rejection-fields" style="display:none;">
                    <label>Reason for rejection
                        <textarea name="payment_rejection_reason" id="banoks-online-rejection-reason" rows="3"></textarea>
                    </label>
                    <p class="description">This reason will be saved in the order status log.</p>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="button button-primary" data-proof-status="verified">Mark GCash Verified</button>
                    <button type="submit" class="button" data-proof-status="rejected">Reject GCash Proof</button>
                </div>
            </form>
            <p id="banoks-online-modal-driver-row" style="display:none;"><strong>Driver:</strong> <span id="banoks-online-modal-driver"></span></p>
            <p id="banoks-online-modal-notes-row" style="display:none;"><strong>Notes:</strong> <span id="banoks-online-modal-notes"></span></p>
            <table class="widefat striped banoks-report-modal-items">
                <thead><tr><th>Item</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr></thead>
                <tbody id="banoks-online-modal-items-body"></tbody>
            </table>
        </div>
    </div>

    <div class="banoks-report-transaction-modal" id="banoks-online-action-modal" aria-hidden="true">
        <div class="banoks-report-transaction-dialog banoks-online-action-dialog" role="dialog" aria-modal="true" aria-labelledby="banoks-online-action-title">
            <button type="button" class="banoks-report-modal-close" aria-label="Close action confirmation">&times;</button>
            <h2 id="banoks-online-action-title">Update Online Order?</h2>
            <p id="banoks-online-action-message"></p>
            <div class="banoks-online-delivery-fields" style="display:none;">
                <label>Driver Name <input type="text" id="banoks-modal-driver-name"></label>
                <label>Driver Contact <input type="text" id="banoks-modal-driver-contact"></label>
            </div>
            <div class="banoks-online-cancel-fields" style="display:none;">
                <label>Cancellation Reason
                    <textarea id="banoks-modal-cancel-reason" rows="3"></textarea>
                </label>
            </div>
            <div class="modal-actions">
                <button type="button" class="button" id="banoks-online-action-cancel">Go Back</button>
                <button type="button" class="button button-primary" id="banoks-online-action-confirm">Yes, Continue</button>
            </div>
        </div>
    </div>

</div>
