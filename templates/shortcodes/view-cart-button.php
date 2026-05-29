<?php
/**
 * View cart button shortcode template.
 *
 * @package Banoks_POS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<span class="banoks-view-cart-shortcode" style="<?php echo esc_attr( $style_attr ); ?>">
    <?php if ( $checkout_url ) : ?>
        <a href="<?php echo esc_url( $checkout_url ); ?>" class="banoks-view-cart-button" aria-label="<?php echo esc_attr( $label ); ?>" hidden>
    <?php else : ?>
        <button type="button" class="banoks-view-cart-button" aria-label="<?php echo esc_attr( $label ); ?>" hidden>
    <?php endif; ?>
        <span class="banoks-view-cart-count banoks-cart-count">0</span>
        <span class="banoks-view-cart-label"><?php echo esc_html( $label ); ?></span>
        <span class="banoks-view-cart-total">&#8369;0.00</span>
    <?php if ( $checkout_url ) : ?>
        </a>
    <?php else : ?>
        </button>
    <?php endif; ?>
</span>
