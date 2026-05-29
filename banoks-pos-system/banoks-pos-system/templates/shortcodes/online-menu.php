<?php
/**
 * Online menu shortcode template.
 *
 * @package Banoks_POS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="banoks-online-shell banoks-online-menu-shell">
    <div class="banoks-online-menu" id="banoks-online-menu" data-banoks-menu>
        <section class="banoks-online-panel banoks-menu-panel">
            <h2 class="banoks-menu-title">Menu</h2>
            <?php if ( empty( $products ) ) : ?>
                <p class="banoks-muted">No menu items available yet.</p>
            <?php else : ?>
                <div class="banoks-menu-category-filter" aria-label="Menu categories">
                    <button type="button" class="banoks-menu-category-btn is-active" data-category-filter="all">Popular</button>
                    <?php foreach ( $menu_categories as $category_key => $category_label ) : ?>
                        <?php if ( 'popular' === strtolower( $category_label ) ) : ?>
                            <?php continue; ?>
                        <?php endif; ?>
                        <button type="button" class="banoks-menu-category-btn" data-category-filter="<?php echo esc_attr( $category_key ); ?>"><?php echo esc_html( $category_label ); ?></button>
                    <?php endforeach; ?>
                </div>

                <div class="banoks-menu-grid">
                    <?php foreach ( $products as $product ) : ?>
                        <?php
                        $available       = ! isset( $product->is_available ) || intval( $product->is_available );
                        $image_url       = ! empty( $product->product_image_id ) ? wp_get_attachment_image_url( absint( $product->product_image_id ), 'large' ) : '';
                        $category        = ! empty( $product->category ) ? $product->category : 'General';
                        $description     = ! empty( $product->product_description ) ? $product->product_description : 'No description available.';
                        $recipe_status   = isset( $recipe_statuses[ absint( $product->product_id ) ] ) ? $recipe_statuses[ absint( $product->product_id ) ] : array();
                        $tracks_stock    = ! empty( $product->track_stock );
                        $stock_quantity  = isset( $product->stock_quantity ) ? absint( $product->stock_quantity ) : 0;
                        $recipe_blocked  = ! empty( $recipe_status['has_recipe'] ) && empty( $recipe_status['can_prepare'] );
                        $stock_blocked   = $tracks_stock && $stock_quantity <= 0;
                        $available       = $available && ! $stock_blocked && ! $recipe_blocked;
                        $stock_label     = '';
                        $stock_class     = '';
                        $low_stock_limit = 5;

                        if ( $tracks_stock ) {
                            if ( $stock_quantity <= 0 ) {
                                $stock_label = 'Out of Stock';
                                $stock_class = ' is-out';
                            } elseif ( $stock_quantity <= $low_stock_limit ) {
                                $stock_label = 'Low Stock';
                                $stock_class = ' is-low';
                            }
                        }

                        if ( ! $available ) {
                            $stock_label = 'Out of Stock';
                            $stock_class = ' is-out';
                        }
                        ?>
                        <div class="banoks-menu-item<?php echo $available ? '' : ' is-disabled'; ?>" data-price="<?php echo esc_attr( $product->current_price ); ?>" data-category="<?php echo esc_attr( sanitize_title( $category ) ); ?>">
                            <div class="banoks-menu-image">
                                <?php if ( $image_url ) : ?>
                                    <img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $product->product_name ); ?>">
                                <?php endif; ?>
                            </div>
                            <div class="banoks-menu-copy">
                                <strong><?php echo esc_html( $product->product_name ); ?></strong>
                                <p class="banoks-menu-description"><?php echo esc_html( $description ); ?></p>
                                <?php if ( $stock_label ) : ?>
                                    <span class="banoks-menu-stock<?php echo esc_attr( $stock_class ); ?>"><?php echo esc_html( $stock_label ); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="banoks-menu-row">
                                <span>&#8369;<?php echo esc_html( number_format( floatval( $product->current_price ), 2 ) ); ?></span>
                                <button type="button" class="banoks-add-cart-btn" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-product-name="<?php echo esc_attr( $product->product_name ); ?>" data-product-price="<?php echo esc_attr( $product->current_price ); ?>" data-product-description="<?php echo esc_attr( $description ); ?>" data-product-image="<?php echo esc_url( $image_url ); ?>">Add to Cart</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <div class="banoks-cart-modal" id="banoks-cart-modal" aria-hidden="true">
        <div class="banoks-cart-dialog" role="dialog" aria-modal="true" aria-labelledby="banoks-cart-modal-title">
            <button type="button" class="banoks-cart-modal-close" aria-label="Close">&times;</button>
            <div class="banoks-cart-modal-product">
                <div class="banoks-cart-modal-image" id="banoks-cart-modal-image"></div>
                <div>
                    <h3 id="banoks-cart-modal-title">Add to Cart</h3>
                    <p id="banoks-cart-modal-price"></p>
                </div>
            </div>
            <div class="banoks-cart-quantity-control">
                <span>Quantity</span>
                <div>
                    <button type="button" class="banoks-cart-qty-btn" data-qty-action="minus">-</button>
                    <input type="number" id="banoks-cart-modal-qty" value="1" min="1" step="1" readonly tabindex="-1" inputmode="none" aria-readonly="true">
                    <button type="button" class="banoks-cart-qty-btn" data-qty-action="plus">+</button>
                </div>
            </div>
            <div class="banoks-cart-addons">
                <h4>Add-ons</h4>
                <div id="banoks-cart-addon-list"></div>
            </div>
            <button type="button" class="banoks-cart-confirm-btn" id="banoks-cart-confirm">Add to Cart</button>
        </div>
    </div>
</div>
<script>
    window.banoksOnlineAddons = <?php echo wp_json_encode( $addon_map ); ?>;
</script>
