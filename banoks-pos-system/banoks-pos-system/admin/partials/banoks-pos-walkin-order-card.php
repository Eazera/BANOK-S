<?php
/**
 * Walk-in order card.
 *
 * @package Banoks_POS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$order_created_at = date_create_immutable_from_format( 'Y-m-d H:i:s', $order->entry_timestamp, wp_timezone() );
$order_created_ts = $order_created_at ? $order_created_at->getTimestamp() * 1000 : strtotime( $order->entry_timestamp ) * 1000;
$order_is_aging   = $order_created_at && in_array( $order->status, array( 'pending', 'preparing' ), true ) && ( time() - $order_created_at->getTimestamp() ) >= 30 * 60;
$order_receipt_id = sprintf( 'BNK-ORD-%06d', $order->order_id );
$order_search     = strtolower( $order_receipt_id . ' ' . $order->created_by );
?>

<div
    class="order-card banoks-walkin-order-card banoks-aging-order-card <?php echo $order_is_aging ? 'banoks-order-aging-warning' : ''; ?>"
    data-id="<?php echo esc_attr( $order->order_id ); ?>"
    data-status="<?php echo esc_attr( $order->status ); ?>"
    data-order-date="<?php echo esc_attr( $order->date ); ?>"
    data-search="<?php echo esc_attr( $order_search ); ?>"
    data-order-created-ts="<?php echo esc_attr( $order_created_ts ); ?>"
>
    <div class="order-header">
        <div class="id-date-wrap">
            <span class="order-id"><?php echo esc_html( $order_receipt_id ); ?></span>
            <span class="order-date"><?php echo esc_html( date( 'M d, Y', strtotime( $order->date ) ) ); ?></span>
            <span class="banoks-order-age-warning">30+ min waiting</span>
        </div>
        <div class="status-cashier-wrap">
            <span class="order-status status-<?php echo esc_attr( $order->status ); ?>"><?php echo esc_html( ucfirst( $order->status ) ); ?></span>
            <span class="banoks-payment-pill payment-<?php echo esc_attr( 'gcash' === strtolower( (string) $order->payment_method ) ? 'gcash' : 'cod' ); ?>"><?php echo esc_html( $format_card_payment_method( $order->payment_method ) ); ?></span>
            <span class="order-cashier">By: <?php echo esc_html( $order->created_by ); ?></span>
        </div>
    </div>

    <div class="order-items">
        <?php
        $items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}banoks_order_items WHERE order_id = %d", $order->order_id ) );
        foreach ( $items as $item ) :
            $product_name = $wpdb->get_var( $wpdb->prepare( "SELECT product_name FROM {$wpdb->prefix}banoks_items WHERE product_id = %d", $item->product_id ) );
            ?>
            <div class="order-item">
                <span><?php echo esc_html( $product_name ); ?> x <?php echo esc_html( $item->qty ); ?></span>
                <span>&#8369;<?php echo esc_html( number_format( $item->sub_total, 2 ) ); ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="order-footer">
        <span class="order-total">&#8369;<?php echo esc_html( number_format( $order->grand_total, 2 ) ); ?></span>
        <div class="order-actions">
            <?php if ( 'pending' === $order->status ) : ?>
                <button class="button button-primary action-prepare" data-id="<?php echo esc_attr( $order->order_id ); ?>">Prepare</button>
                <button class="button action-cancel" data-id="<?php echo esc_attr( $order->order_id ); ?>">Cancel</button>
            <?php elseif ( 'preparing' === $order->status ) : ?>
                <button class="button button-primary action-complete" data-id="<?php echo esc_attr( $order->order_id ); ?>">Complete</button>
                <button class="button action-cancel" data-id="<?php echo esc_attr( $order->order_id ); ?>">Cancel</button>
            <?php endif; ?>
        </div>
    </div>
</div>
