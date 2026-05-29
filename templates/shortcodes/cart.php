<?php
/**
 * Cart shortcode template.
 *
 * @package Banoks_POS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$delivery_area_fees = array();
foreach ( $delivery_areas as $delivery_area ) {
    $delivery_area_fees[ absint( $delivery_area->id ) ] = isset( $delivery_area->delivery_fee ) ? floatval( $delivery_area->delivery_fee ) : 0;
}
?>
<div class="banoks-online-shell banoks-cart-page-shell">
    <section class="banoks-cart-page" id="banoks-cart" data-banoks-cart-page data-menu-url="<?php echo esc_url( $menu_url ); ?>">
        <header class="banoks-cart-header">
            <div class="banoks-cart-title-row">
                <h2 class="banoks-cart-title" data-banoks-cart-title>Cart</h2>
                <button type="button" class="banoks-cart-back-button" data-banoks-header-back>Back</button>
            </div>

            <nav class="banoks-cart-progress" aria-label="Checkout progress">
                <ol class="banoks-cart-progress-line">
                    <li class="is-complete" data-banoks-progress-step="menu">
                        <span>1</span>
                    </li>
                    <li class="is-active" data-banoks-progress-step="cart">
                        <span>2</span>
                    </li>
                    <li data-banoks-progress-step="checkout">
                        <span>3</span>
                    </li>
                </ol>
                <ol class="banoks-cart-progress-labels">
                    <li>Menu</li>
                    <li>Cart</li>
                    <li>Checkout</li>
                </ol>
            </nav>
        </header>

        <div data-banoks-cart-view>
            <div class="banoks-cart-service-toggle" role="radiogroup" aria-label="Order type">
                <button type="button" class="is-active" data-banoks-service-option="delivery" role="radio" aria-checked="true">
                    <img src="<?php echo esc_url( $delivery_icon ); ?>" alt="" aria-hidden="true">
                    <span>Delivery</span>
                </button>
                <button type="button" data-banoks-service-option="pickup" role="radio" aria-checked="false">
                    <img src="<?php echo esc_url( $pickup_icon ); ?>" alt="" aria-hidden="true">
                    <span>Pick-up</span>
                </button>
            </div>

            <div class="banoks-cart-items-card">
                <div class="banoks-cart-empty" data-banoks-cart-empty>
                    <p>Your cart is empty.</p>
                    <a href="<?php echo esc_url( $menu_url ); ?>" class="banoks-cart-empty-button">Add Item</a>
                </div>

                <div class="banoks-cart-items" data-banoks-cart-items aria-live="polite"></div>

                <a href="<?php echo esc_url( $menu_url ); ?>" class="banoks-cart-add-more" data-banoks-cart-add-more hidden>
                    <span aria-hidden="true">+</span>
                    <span>Add more item</span>
                </a>
            </div>
        </div>

        <div class="banoks-checkout-view" data-banoks-checkout-view hidden>
            <section class="banoks-checkout-card banoks-checkout-address-card">
                <div class="banoks-checkout-card-heading">
                    <h3 data-banoks-address-heading>Delivery Address</h3>
                    <?php if ( $customer ) : ?>
                        <button type="button" class="banoks-checkout-add-address" data-banoks-add-address-toggle>
                            <span aria-hidden="true">+</span>
                            <span>Add delivery address</span>
                        </button>
                    <?php endif; ?>
                </div>
                <div class="banoks-checkout-rule"></div>
                <div data-banoks-delivery-address-panel>
                    <?php if ( ! $customer ) : ?>
                        <p class="banoks-checkout-muted">Please log in before adding a delivery address.</p>
                    <?php else : ?>
                        <div class="banoks-checkout-address-list" data-banoks-address-list>
                            <?php if ( empty( $addresses ) ) : ?>
                                <p class="banoks-checkout-muted" data-banoks-no-address>No saved delivery address yet.</p>
                            <?php endif; ?>
                            <?php foreach ( $addresses as $address ) : ?>
                                <label class="banoks-checkout-address-option">
                                    <input type="radio" name="banoks_checkout_address" value="<?php echo esc_attr( $address->id ); ?>" data-delivery-area-id="<?php echo esc_attr( $address->delivery_area_id ); ?>" data-delivery-fee="<?php echo esc_attr( isset( $delivery_area_fees[ absint( $address->delivery_area_id ) ] ) ? $delivery_area_fees[ absint( $address->delivery_area_id ) ] : 0 ); ?>" <?php checked( ! empty( $address->is_default ) || count( $addresses ) === 1 ); ?>>
                                    <span>
                                        <strong><?php echo esc_html( $address->municipality ); ?></strong>
                                        <small><?php echo esc_html( trim( $address->barangay . ', ' . $address->sitio, ', ' ) ); ?></small>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <form class="banoks-checkout-address-form" data-banoks-address-form hidden>
                            <label>
                                <span>Municipality</span>
                                <input type="text" name="municipality" value="Manukan" readonly aria-readonly="true">
                            </label>
                            <label>
                                <span>Barangay</span>
                                <select name="barangay" required>
                                    <option value="">Barangay</option>
                                    <?php foreach ( $delivery_areas as $area ) : ?>
                                        <?php if ( ! empty( $area->is_deliverable ) ) : ?>
                                            <option value="<?php echo esc_attr( $area->area_name ); ?>"><?php echo esc_html( $area->area_name ); ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                <span>Sitio</span>
                                <input type="text" name="sitio" placeholder="Sitio" required>
                            </label>
                            <div class="banoks-checkout-address-actions">
                                <button type="button" data-banoks-cancel-address>Cancel</button>
                                <button type="submit">Save address</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="banoks-checkout-pickup-location" data-banoks-pickup-address-panel hidden>
                    <span class="banoks-checkout-pickup-icon" aria-hidden="true">
                        <img src="<?php echo esc_url( BANOKS_POS_URL . 'public/images/address.svg' ); ?>" alt="">
                    </span>
                    <span class="banoks-checkout-pickup-text">
                        <strong>Manukan, Sunset Boulevard</strong>
                    </span>
                </div>
            </section>

            <section class="banoks-checkout-card">
                <h3>Payment Methods</h3>
                <div class="banoks-checkout-rule"></div>
                <div class="banoks-payment-methods" role="radiogroup" aria-label="Payment methods">
                    <label>
                        <input type="radio" name="banoks_payment_method" value="cod" data-banoks-payment-method checked>
                        <span class="banoks-payment-icon is-cod"></span>
                        <strong>Cash on Delivery</strong>
                    </label>
                    <label>
                        <input type="radio" name="banoks_payment_method" value="gcash" data-banoks-payment-method>
                        <span class="banoks-payment-icon is-gcash">G</span>
                        <strong>GCash</strong>
                        <?php if ( ! empty( $gcash_profile ) ) : ?>
                            <small>Use last GCash approval profile</small>
                        <?php endif; ?>
                    </label>
                </div>
            </section>

            <section class="banoks-checkout-card">
                <h3>Order Summary</h3>
                <div class="banoks-checkout-rule"></div>
                <dl class="banoks-checkout-summary">
                    <div>
                        <dt>Subtotal</dt>
                        <dd data-banoks-summary-subtotal>&#8369;0.00</dd>
                    </div>
                    <div>
                        <dt>Delivery Fee</dt>
                        <dd data-banoks-summary-delivery>25</dd>
                    </div>
                    <div>
                        <dt>Discount</dt>
                        <dd data-banoks-summary-discount>0</dd>
                    </div>
                    <div>
                        <dt>VAT</dt>
                        <dd data-banoks-summary-vat>25</dd>
                    </div>
                    <div>
                        <dt>Total</dt>
                        <dd data-banoks-summary-total>&#8369;0.00</dd>
                    </div>
                </dl>
            </section>
            <p class="banoks-checkout-message" data-banoks-place-order-message hidden></p>
        </div>

        <div class="banoks-order-confirm-modal" data-banoks-order-confirm-modal aria-hidden="true">
            <div class="banoks-order-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="banoks-order-confirm-title">
                <h3 id="banoks-order-confirm-title">Are you sure you want to place this order?</h3>
                <div class="banoks-order-confirm-body">
                    <div class="banoks-order-confirm-section">
                        <h4>Order Details</h4>
                        <dl data-banoks-confirm-details></dl>
                    </div>
                    <div class="banoks-order-confirm-section">
                        <h4>Items</h4>
                        <div class="banoks-order-confirm-items" data-banoks-confirm-items></div>
                    </div>
                    <div class="banoks-order-confirm-section">
                        <h4>Summary</h4>
                        <dl data-banoks-confirm-summary></dl>
                    </div>
                </div>
                <div class="banoks-order-confirm-actions">
                    <button type="button" class="banoks-order-confirm-back" data-banoks-confirm-back>Back</button>
                    <button type="button" class="banoks-order-confirm-ok" data-banoks-confirm-ok>Okay</button>
                </div>
            </div>
        </div>

        <footer class="banoks-cart-summary-footer banoks-cart-footer" data-banoks-cart-footer>
            <div>
                <span>Total</span>
                <strong data-banoks-cart-total>&#8369;0.00</strong>
            </div>
            <button type="button" class="banoks-cart-checkout-button" data-banoks-checkout-button data-banoks-go-checkout>Checkout</button>
        </footer>

        <footer class="banoks-checkout-summary-footer" data-banoks-checkout-footer hidden>
            <div class="banoks-cart-footer-total-row">
                <span>Total</span>
                <strong data-banoks-checkout-footer-total>&#8369;0.00</strong>
            </div>
            <button type="button" class="banoks-cart-checkout-button banoks-place-order-button" data-banoks-place-order>Place order</button>
        </footer>
    </section>
</div>
<template id="banoks-cart-item-template">
    <article class="banoks-cart-item" data-banoks-cart-item>
        <label class="banoks-cart-item-check">
            <input type="checkbox" data-banoks-cart-select checked>
            <span class="screen-reader-text">Select item</span>
        </label>
        <div class="banoks-cart-item-image" data-banoks-cart-image></div>
        <div class="banoks-cart-item-copy">
            <div class="banoks-cart-item-details">
                <strong data-banoks-cart-name></strong>
                <p data-banoks-cart-description></p>
                <div class="banoks-cart-qty-stepper">
                    <button type="button" data-banoks-cart-action="minus" data-banoks-cart-minus aria-label="Decrease quantity">-</button>
                    <span data-banoks-cart-qty>1</span>
                    <button type="button" data-banoks-cart-action="plus" aria-label="Increase quantity">+</button>
                </div>
                <button type="button" class="banoks-cart-add-addon-button" data-banoks-cart-action="toggle-addons" data-banoks-cart-add-addon hidden>
                    <span aria-hidden="true">+</span>
                    <span>Addons</span>
                </button>
            </div>
        </div>
        <div class="banoks-cart-item-side">
            <button type="button" class="banoks-cart-delete" data-banoks-cart-action="remove" aria-label="Remove item">
                <img src="<?php echo esc_url( $delete_icon ); ?>" alt="" aria-hidden="true">
            </button>
            <strong data-banoks-cart-price>&#8369;0.00</strong>
        </div>
        <div class="banoks-cart-addon-list" data-banoks-cart-addon-list hidden></div>
        <div class="banoks-cart-addon-picker" data-banoks-cart-addon-picker hidden></div>
        <p class="banoks-cart-stock-warning" data-banoks-cart-stock-warning hidden></p>
    </article>
</template>
<script>
    window.banoksOnlineAddons = Object.assign(
        {},
        window.banoksOnlineAddons || {},
        <?php echo wp_json_encode( isset( $addon_map ) ? $addon_map : array() ); ?>
    );
</script>
