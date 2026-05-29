<?php
/**
 * Customer auth shortcode template.
 *
 * @package Banoks_POS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="banoks-online-shell banoks-auth-shell banoks-customer-auth">
    <?php if ( $customer ) : ?>
        <div class="banoks-online-panel banoks-auth-card banoks-auth-card-single">
            <h2>Welcome, <?php echo esc_html( $customer->full_name ); ?></h2>
            <p class="banoks-muted">Customer ID: <?php echo esc_html( $customer->customer_id ); ?></p>
            <form method="post">
                <?php wp_nonce_field( 'banoks_customer_logout' ); ?>
                <input type="hidden" name="banoks_public_action" value="logout">
                <button type="submit">Log Out</button>
            </form>
        </div>
    <?php else : ?>
        <div class="banoks-auth-grid">
            <form method="post" class="banoks-online-panel banoks-auth-card" data-banoks-auth-form="login" novalidate>
                <?php wp_nonce_field( 'banoks_customer_login' ); ?>
                <input type="hidden" name="banoks_public_action" value="login">
                <h2>Login</h2>
                <label class="banoks-auth-field">
                    <span class="banoks-auth-icon" aria-hidden="true">
                        <img class="banoks-auth-icon-img banoks-auth-account-icon" src="<?php echo esc_url( BANOKS_POS_URL . 'public/images/banoks-auth-account.svg' ); ?>" alt="" loading="lazy" decoding="async">
                    </span>
                    <span class="screen-reader-text">Username</span>
                    <input type="text" name="identifier" placeholder="Username" required>
                </label>
                <label class="banoks-auth-field banoks-auth-password-field">
                    <span class="banoks-auth-icon" aria-hidden="true">
                        <img class="banoks-auth-icon-img banoks-auth-password-icon" src="<?php echo esc_url( BANOKS_POS_URL . 'public/images/banoks-auth-password.svg' ); ?>" alt="" loading="lazy" decoding="async">
                    </span>
                    <span class="screen-reader-text">Password</span>
                    <input type="password" name="password" placeholder="Password" required>
                    <button class="banoks-auth-password-toggle" type="button" aria-label="Show password" data-banoks-password-toggle>
                        <svg class="banoks-auth-eye-icon banoks-auth-eye-show" viewBox="0 0 24 24" focusable="false"><path d="M12 5c5 0 8.6 4.2 10 7-1.4 2.8-5 7-10 7s-8.6-4.2-10-7c1.4-2.8 5-7 10-7Zm0 2C8.7 7 6 9.4 4.4 12c1.6 2.6 4.3 5 7.6 5s6-2.4 7.6-5C18 9.4 15.3 7 12 7Zm0 2.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Z"/></svg>
                        <svg class="banoks-auth-eye-icon banoks-auth-eye-hide" viewBox="0 0 24 24" focusable="false"><path d="m3.3 2 18.7 18.7-1.3 1.3-3.2-3.2A10 10 0 0 1 12 20C7 20 3.4 15.8 2 13c.7-1.4 2-3.1 3.7-4.5L2 4.8 3.3 2Zm4 8.2A11.2 11.2 0 0 0 4.4 13c1.6 2.6 4.3 5 7.6 5 1.4 0 2.8-.5 4-1.2l-2-2A2.8 2.8 0 0 1 10.2 11l-2.9-2.8ZM12 6c5 0 8.6 4.2 10 7-.4.8-1.1 1.8-2 2.8l-2.1-2.1c.7-.2 1.3-.5 1.7-.7C18 10.4 15.3 8 12 8c-.8 0-1.6.1-2.3.4L8.1 6.8A9.9 9.9 0 0 1 12 6Z"/></svg>
                    </button>
                </label>
                <button class="banoks-auth-submit" type="submit">Login</button>
                <div class="banoks-social-login" aria-label="Social login options">
                    <span>Login with Others</span>
                    <button class="banoks-social-button" type="button" data-banoks-social-message="Google login setup is coming next."><img class="banoks-social-icon banoks-social-google-icon" src="<?php echo esc_url( BANOKS_POS_URL . 'public/images/banoks-auth-google.svg' ); ?>" alt="" loading="lazy" decoding="async"><span>Login with Google</span></button>
                    <button class="banoks-social-button" type="button" data-banoks-social-message="Facebook login setup is coming next."><img class="banoks-social-icon banoks-social-facebook-icon" src="<?php echo esc_url( BANOKS_POS_URL . 'public/images/banoks-auth-facebook.svg' ); ?>" alt="" loading="lazy" decoding="async"><span>Login with Facebook</span></button>
                </div>
                <p class="banoks-auth-switch">No account yet? <button type="button" data-banoks-open-register>Create account</button></p>
            </form>
        </div>
        <div class="banoks-auth-register-panel" data-banoks-register-modal aria-hidden="true">
            <div class="banoks-auth-register-dialog" role="region" aria-labelledby="banoks-register-title">
                <button class="banoks-auth-register-close" type="button" data-banoks-close-register aria-label="Close create account form">x</button>
                <form method="post" class="banoks-online-panel banoks-auth-card banoks-register-card" data-banoks-auth-form="register" novalidate>
                    <?php wp_nonce_field( 'banoks_customer_register' ); ?>
                    <input type="hidden" name="banoks_public_action" value="register">
                    <h2 id="banoks-register-title">Create Account</h2>
                    <label class="banoks-auth-field">
                        <span class="screen-reader-text">Full Name</span>
                        <input type="text" name="full_name" placeholder="Full Name" required>
                    </label>
                    <label class="banoks-auth-field">
                        <span class="screen-reader-text">Username</span>
                        <input type="text" name="username" placeholder="Username" required>
                    </label>
                    <label class="banoks-auth-field">
                        <span class="screen-reader-text">Contact Number</span>
                        <input type="text" name="contact_number" placeholder="Contact Number" required>
                    </label>
                    <div class="banoks-auth-address-section">
                        <label class="banoks-auth-field">
                            <span class="screen-reader-text">Municipality</span>
                            <input type="text" name="municipality" value="Manukan" readonly aria-readonly="true">
                        </label>
                        <label class="banoks-auth-field">
                            <span class="screen-reader-text">Barangay</span>
                            <select name="barangay" required>
                                <option value="">Barangay</option>
                                <?php foreach ( $delivery_areas as $area ) : ?>
                                    <?php if ( ! empty( $area->is_deliverable ) ) : ?>
                                        <option value="<?php echo esc_attr( $area->area_name ); ?>" data-delivery-area-id="<?php echo esc_attr( $area->id ); ?>"><?php echo esc_html( $area->area_name ); ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="banoks-auth-field">
                            <span class="screen-reader-text">Sitio</span>
                            <input type="text" name="sitio" placeholder="Sitio" required>
                        </label>
                    </div>
                    <label class="banoks-auth-field banoks-auth-password-field">
                        <span class="screen-reader-text">Password</span>
                        <input type="password" name="password" minlength="6" placeholder="Password" required>
                        <button class="banoks-auth-password-toggle" type="button" aria-label="Show password" data-banoks-password-toggle>
                            <svg class="banoks-auth-eye-icon banoks-auth-eye-show" viewBox="0 0 24 24" focusable="false"><path d="M12 5c5 0 8.6 4.2 10 7-1.4 2.8-5 7-10 7s-8.6-4.2-10-7c1.4-2.8 5-7 10-7Zm0 2C8.7 7 6 9.4 4.4 12c1.6 2.6 4.3 5 7.6 5s6-2.4 7.6-5C18 9.4 15.3 7 12 7Zm0 2.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Z"/></svg>
                            <svg class="banoks-auth-eye-icon banoks-auth-eye-hide" viewBox="0 0 24 24" focusable="false"><path d="m3.3 2 18.7 18.7-1.3 1.3-3.2-3.2A10 10 0 0 1 12 20C7 20 3.4 15.8 2 13c.7-1.4 2-3.1 3.7-4.5L2 4.8 3.3 2Zm4 8.2A11.2 11.2 0 0 0 4.4 13c1.6 2.6 4.3 5 7.6 5 1.4 0 2.8-.5 4-1.2l-2-2A2.8 2.8 0 0 1 10.2 11l-2.9-2.8ZM12 6c5 0 8.6 4.2 10 7-.4.8-1.1 1.8-2 2.8l-2.1-2.1c.7-.2 1.3-.5 1.7-.7C18 10.4 15.3 8 12 8c-.8 0-1.6.1-2.3.4L8.1 6.8A9.9 9.9 0 0 1 12 6Z"/></svg>
                        </button>
                    </label>
                    <label class="banoks-auth-field banoks-auth-password-field">
                        <span class="screen-reader-text">Confirm Password</span>
                        <input type="password" name="confirm_password" minlength="6" placeholder="Confirm Password" required>
                        <button class="banoks-auth-password-toggle" type="button" aria-label="Show password" data-banoks-password-toggle>
                            <svg class="banoks-auth-eye-icon banoks-auth-eye-show" viewBox="0 0 24 24" focusable="false"><path d="M12 5c5 0 8.6 4.2 10 7-1.4 2.8-5 7-10 7s-8.6-4.2-10-7c1.4-2.8 5-7 10-7Zm0 2C8.7 7 6 9.4 4.4 12c1.6 2.6 4.3 5 7.6 5s6-2.4 7.6-5C18 9.4 15.3 7 12 7Zm0 2.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Z"/></svg>
                            <svg class="banoks-auth-eye-icon banoks-auth-eye-hide" viewBox="0 0 24 24" focusable="false"><path d="m3.3 2 18.7 18.7-1.3 1.3-3.2-3.2A10 10 0 0 1 12 20C7 20 3.4 15.8 2 13c.7-1.4 2-3.1 3.7-4.5L2 4.8 3.3 2Zm4 8.2A11.2 11.2 0 0 0 4.4 13c1.6 2.6 4.3 5 7.6 5 1.4 0 2.8-.5 4-1.2l-2-2A2.8 2.8 0 0 1 10.2 11l-2.9-2.8ZM12 6c5 0 8.6 4.2 10 7-.4.8-1.1 1.8-2 2.8l-2.1-2.1c.7-.2 1.3-.5 1.7-.7C18 10.4 15.3 8 12 8c-.8 0-1.6.1-2.3.4L8.1 6.8A9.9 9.9 0 0 1 12 6Z"/></svg>
                        </button>
                    </label>
                    <label class="banoks-auth-policy">
                        <input type="checkbox" name="privacy_agree" value="1" required>
                        <span>I agree to the Data Privacy Policy and consent to the collection and processing of my personal information.</span>
                    </label>
                    <button class="banoks-auth-submit" type="submit">Create Account</button>
                </form>
            </div>
        </div>
        <div class="banoks-auth-message-modal" data-banoks-auth-message-modal aria-hidden="true">
            <div class="banoks-auth-message-backdrop" data-banoks-auth-message-close></div>
            <div class="banoks-auth-message-dialog" role="alertdialog" aria-modal="true" aria-labelledby="banoks-auth-message-title">
                <button class="banoks-auth-message-close" type="button" data-banoks-auth-message-close aria-label="Close message">x</button>
                <h3 id="banoks-auth-message-title">Banok's</h3>
                <p data-banoks-auth-message-text></p>
            </div>
        </div>
    <?php endif; ?>
</div>
