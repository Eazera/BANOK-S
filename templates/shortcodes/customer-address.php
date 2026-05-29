<?php
/**
 * Customer address shortcode template.
 *
 * @package Banoks_POS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<span class="banoks-customer-address">
    <span class="banoks-customer-address-icon" aria-hidden="true">
        <img src="<?php echo esc_url( BANOKS_POS_URL . 'public/images/address.svg' ); ?>" alt="" loading="lazy" decoding="async">
    </span>
    <span class="banoks-customer-address-text">
        <span class="banoks-customer-address-heading"><?php echo esc_html( $municipality ); ?></span>
        <span class="banoks-customer-address-subheading"><?php echo esc_html( $address ); ?></span>
    </span>
</span>
