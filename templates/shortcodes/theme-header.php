<?php
/**
 * Banoks theme header shortcode template.
 *
 * @package Banoks_POS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<header class="banoks-theme-header" aria-label="<?php esc_attr_e( 'Banoks ordering header', 'banoks-pos' ); ?>">
    <div class="banoks-theme-header-inner">
        <div class="banoks-theme-header-desktop">
            <button type="button" class="banoks-theme-header-burger" aria-label="<?php esc_attr_e( 'Open menu', 'banoks-pos' ); ?>">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <a class="banoks-theme-header-logo" href="<?php echo esc_url( $menu_url ); ?>" aria-label="<?php esc_attr_e( 'Banoks menu', 'banoks-pos' ); ?>">
                <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php esc_attr_e( "Banok's", 'banoks-pos' ); ?>" loading="eager" decoding="async">
            </a>

            <nav class="banoks-theme-header-nav" aria-label="<?php esc_attr_e( 'Banoks desktop navigation', 'banoks-pos' ); ?>">
                <a class="is-active" href="<?php echo esc_url( $menu_url ); ?>"><?php esc_html_e( 'Menu', 'banoks-pos' ); ?></a>
                <a href="#branches"><?php esc_html_e( 'Branches', 'banoks-pos' ); ?></a>
                <a href="#about-us"><?php esc_html_e( 'About Us', 'banoks-pos' ); ?></a>
                <a href="#contact-us"><?php esc_html_e( 'Contact Us', 'banoks-pos' ); ?></a>
            </nav>

            <div class="banoks-theme-header-actions">
                <div class="banoks-theme-header-message">
                    <button type="button" class="banoks-theme-header-message-button" aria-label="<?php esc_attr_e( 'Messages', 'banoks-pos' ); ?>">
                        <img src="<?php echo esc_url( BANOKS_POS_URL . 'public/images/icon_message.svg' ); ?>" alt="" loading="lazy" decoding="async">
                    </button>
                </div>
                <div class="banoks-theme-header-cart">
                    <?php echo $cart_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- controlled shortcode output with SVG. ?>
                </div>
                <div class="banoks-theme-header-login">
                    <?php echo wp_kses_post( $login_html ); ?>
                </div>
            </div>
        </div>

        <div class="banoks-theme-header-bar">
            <div class="banoks-theme-header-address">
                <?php echo $address_html ? wp_kses_post( $address_html ) : ''; ?>
            </div>
            <div class="banoks-theme-header-login">
                <?php echo wp_kses_post( $login_html ); ?>
            </div>
        </div>

        <div class="banoks-theme-search" role="search">
            <input
                type="search"
                class="banoks-theme-search-input"
                placeholder="<?php echo esc_attr( $search_placeholder ); ?>"
                aria-label="<?php echo esc_attr( $search_placeholder ); ?>"
            >
        </div>
    </div>
    <div class="banoks-theme-header-curve" aria-hidden="true"></div>
</header>
