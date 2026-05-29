<?php
/**
 * Banoks theme footer shortcode template.
 *
 * @package Banoks_POS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$active = in_array( $active, array( 'menu', 'cart', 'me' ), true ) ? $active : 'menu';
?>
<nav class="banoks-theme-footer" aria-label="<?php esc_attr_e( 'Banoks ordering footer', 'banoks-pos' ); ?>">
    <?php if ( $view_cart_html ) : ?>
        <div class="banoks-theme-floating-cart">
            <?php echo $view_cart_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- controlled shortcode output. ?>
        </div>
    <?php endif; ?>

    <div class="banoks-theme-footer-bar">
        <a class="banoks-theme-footer-item <?php echo 'menu' === $active ? 'is-active' : ''; ?>" href="<?php echo esc_url( $menu_url ); ?>" data-banoks-footer-nav="menu" aria-label="<?php esc_attr_e( 'Menu', 'banoks-pos' ); ?>">
            <span class="banoks-theme-footer-icon" aria-hidden="true">
                <img src="<?php echo esc_url( $menu_icon_url ); ?>" alt="" loading="lazy" decoding="async">
            </span>
            <span class="banoks-theme-footer-label"><?php esc_html_e( 'Menu', 'banoks-pos' ); ?></span>
        </a>

        <div class="banoks-theme-footer-item banoks-theme-footer-cart <?php echo 'cart' === $active ? 'is-active' : ''; ?>" data-banoks-footer-nav="cart">
            <span class="banoks-theme-footer-icon" aria-hidden="true">
                <?php echo $cart_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- controlled shortcode output with SVG. ?>
            </span>
            <span class="banoks-theme-footer-label"><?php esc_html_e( 'Cart', 'banoks-pos' ); ?></span>
        </div>

        <a class="banoks-theme-footer-item <?php echo 'me' === $active ? 'is-active' : ''; ?>" href="<?php echo esc_url( $me_url ); ?>" data-banoks-footer-nav="me" aria-label="<?php esc_attr_e( 'Me', 'banoks-pos' ); ?>">
            <span class="banoks-theme-footer-icon" aria-hidden="true">
                <img src="<?php echo esc_url( $me_icon_url ); ?>" alt="" loading="lazy" decoding="async">
            </span>
            <span class="banoks-theme-footer-label"><?php esc_html_e( 'Me', 'banoks-pos' ); ?></span>
        </a>
    </div>
</nav>
