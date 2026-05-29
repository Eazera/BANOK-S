<?php
/**
 * Customer login button shortcode template.
 *
 * @package Banoks_POS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<a class="banoks-customer-login-button <?php echo $customer ? 'is-logged-in' : 'is-logged-out'; ?>" href="<?php echo esc_url( $url ); ?>">
    <?php if ( $customer ) : ?>
        <span class="banoks-customer-profile-avatar" aria-hidden="true">
            <img src="<?php echo esc_url( BANOKS_POS_URL . 'public/images/me.svg' ); ?>" alt="" loading="lazy" decoding="async">
        </span>
        <span class="banoks-customer-profile-name"><?php echo esc_html( $label ); ?></span>
    <?php else : ?>
        <span><?php echo esc_html( $label ); ?></span>
    <?php endif; ?>
</a>
