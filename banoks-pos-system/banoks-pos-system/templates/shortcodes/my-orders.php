<?php
/**
 * My orders shortcode template.
 *
 * @package Banoks_POS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="banoks-online-shell banoks-online-orders-shell">
    <div class="banoks-online-panel">
        <h2>My Orders</h2>
        <?php if ( ! $customer ) : ?>
            <p class="banoks-warning">Please login to view your orders.</p>
        <?php elseif ( empty( $orders ) ) : ?>
            <p class="banoks-muted">You do not have online orders yet.</p>
        <?php else : ?>
            <div class="banoks-order-list">
                <?php foreach ( $orders as $order ) : ?>
                    <?php
                    $status_label      = ucwords( str_replace( '_', ' ', $order->order_status ) );
                    $fulfillment_label = ! empty( $order->fulfillment_type ) && 'pickup' === $order->fulfillment_type ? 'Pickup' : 'Delivery';
                    $is_completed      = 'completed' === $order->order_status;
                    $is_cancelled      = in_array( $order->order_status, array( 'cancelled', 'rejected' ), true );
                    ?>
                    <div class="banoks-order-card">
                        <div>
                            <strong><?php echo esc_html( $order->online_order_id ); ?></strong>
                            <span><?php echo esc_html( wp_date( 'M d, Y g:i A', strtotime( $order->created_at ) ) ); ?></span>
                            <span><?php echo esc_html( $fulfillment_label ); ?></span>
                        </div>
                        <div>
                            <span class="banoks-status"><?php echo esc_html( $status_label ); ?></span>
                            <strong>&#8369;<?php echo esc_html( number_format( floatval( $order->total_amount ), 2 ) ); ?></strong>
                        </div>
                        <?php if ( 'delivery' === $order->fulfillment_type && 'delivering' === $order->order_status && ( $order->driver_name || $order->driver_contact ) ) : ?>
                            <p>Driver: <?php echo esc_html( trim( $order->driver_name . ' ' . $order->driver_contact ) ); ?></p>
                        <?php endif; ?>
                        <?php if ( $is_completed || $is_cancelled ) : ?>
                            <div class="banoks-order-terminal-status <?php echo esc_attr( $is_completed ? 'is-completed' : 'is-cancelled' ); ?>" role="status">
                                <span aria-hidden="true">
                                    <?php if ( $is_completed ) : ?>
                                        <svg viewBox="0 0 24 24" focusable="false">
                                            <path d="M9.2 16.7 4.9 12.4 3.5 13.8 9.2 19.5 21 7.7 19.6 6.3z"></path>
                                        </svg>
                                    <?php else : ?>
                                        <svg viewBox="0 0 24 24" focusable="false">
                                            <path d="M18.3 5.7 12 12l6.3 6.3-1.4 1.4L10.6 13.4 4.3 19.7 2.9 18.3 9.2 12 2.9 5.7 4.3 4.3l6.3 6.3 6.3-6.3z"></path>
                                        </svg>
                                    <?php endif; ?>
                                </span>
                                <strong><?php echo esc_html( $is_completed ? 'Order Successfully Delivered' : 'Order Cancelled' ); ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
