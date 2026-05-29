<?php
/**
 * Shared POS interface.
 *
 * @package Banoks_POS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$show_header = isset( $show_header ) ? $show_header : true;
$show_nav    = isset( $show_nav ) ? $show_nav : true;
$dashboard_url = isset( $dashboard_url ) ? $dashboard_url : admin_url( 'admin.php?page=banoks-pos&view=pending' );
$recipe_statuses = isset( $recipe_statuses ) && is_array( $recipe_statuses ) ? $recipe_statuses : array();
?>

<?php if ( $show_header ) : ?>
    <?php include BANOKS_POS_PATH . 'templates/parts/admin-header.php'; ?>
<?php endif; ?>

<div class="wrap banoks-pos-admin banoks-pos-page banoks-pos-redesign">
    <div class="banoks-pos-wrapper">
        <div class="pos-products-column">
            <div class="pos-top-bar">
                <div class="pos-search-wrap">
                    <input type="text" id="product-search" placeholder="Search Product...">
                    <select id="product-category">
                        <option value="">All Categories</option>
                        <?php foreach ( $categories as $cat ) : ?>
                            <option value="<?php echo esc_attr( $cat ); ?>"><?php echo esc_html( $cat ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="pos-date-wrap">
                    <label>Date:</label>
                    <input type="date" id="pos-order-date" value="<?php echo esc_attr( $active_date ); ?>">
                </div>
            </div>

            <div class="pos-products-grid" id="pos-product-grid">
                <?php foreach ( $products as $product ) : ?>
                    <?php
                    $product_image_url = ! empty( $product->product_image_id ) ? wp_get_attachment_image_url( absint( $product->product_image_id ), 'medium' ) : '';
                    $recipe_status     = isset( $recipe_statuses[ absint( $product->product_id ) ] ) ? $recipe_statuses[ absint( $product->product_id ) ] : array();
                    $stock_label       = isset( $recipe_status['available_stock'] ) && null !== $recipe_status['available_stock'] ? number_format_i18n( absint( $recipe_status['available_stock'] ) ) : 'N/A';
                    ?>
                    <div class="product-item"
                         data-id="<?php echo esc_attr( $product->product_id ); ?>"
                         data-name="<?php echo esc_attr( $product->product_name ); ?>"
                         data-category="<?php echo esc_attr( $product->category ); ?>"
                         data-price="<?php echo esc_attr( $product->current_price ); ?>">
                        <div class="product-image-wrap">
                            <?php if ( $product_image_url ) : ?>
                                <img src="<?php echo esc_url( $product_image_url ); ?>" alt="<?php echo esc_attr( $product->product_name ); ?>">
                            <?php else : ?>
                                <span class="product-image-placeholder dashicons dashicons-format-image" aria-hidden="true"></span>
                            <?php endif; ?>
                        </div>
                        <span class="p-name"><?php echo esc_html( $product->product_name ); ?></span>
                        <span class="p-price">&#8369;<?php echo esc_html( number_format( $product->current_price, 2 ) ); ?></span>
                        <span class="p-stock">Stock: <?php echo esc_html( $stock_label ); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="pos-cart-column">
            <div class="cart-header">
                <h2 id="current-order-id"><?php echo esc_html( sprintf( 'BNK-ORD-%06d', $next_id ) ); ?></h2>
            </div>

            <div class="cart-items-list" id="pos-cart-items">
                <div class="empty-msg">Select items to start order</div>
            </div>

            <div class="cart-summary">
                <div class="total-row">
                    <span class="label">TOTAL</span>
                    <span class="amount" id="pos-grand-total">&#8369;0.00</span>
                </div>
                <div class="payment-row">
                    <label for="pos-payment-method">Payment Method</label>
                    <select id="pos-payment-method">
                        <option value="cash">Cash</option>
                        <option value="gcash">GCash</option>
                    </select>
                </div>
                <div class="payment-row">
                    <label for="pos-money-received">Money Received</label>
                    <input type="number" id="pos-money-received" min="0" step="0.01" inputmode="decimal" placeholder="0.00">
                </div>
                <div class="change-row">
                    <span class="label">CHANGE</span>
                    <span class="amount" id="pos-change-amount">&#8369;0.00</span>
                </div>
            </div>

            <div class="pos-actions-footer">
                <button type="button" class="button action-clear" id="pos-clear-btn">Clear All</button>
                <button type="button" class="button button-primary action-generate" id="pos-generate-btn">Generate Order</button>
            </div>
        </div>
    </div>
</div>
