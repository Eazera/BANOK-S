<?php
/**
 * Shared Banoks POS admin header.
 *
 * @package Banoks_POS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$show_nav = isset( $show_nav ) ? $show_nav : true;
$dashboard_url = isset( $dashboard_url ) ? $dashboard_url : admin_url( 'admin.php?page=banoks-pos&view=pending' );
$pending_request_count = isset( $pending_request_count ) ? absint( $pending_request_count ) : 0;
?>

<div class="banoks-pos-admin banoks-pos-admin-shell">
<div class="banoks-admin-header">
    <div class="header-left">
        <img src="<?php echo esc_url( content_url( 'uploads/2026/05/540735940_723046727449754_9010205230483381260_n.jpg' ) ); ?>" alt="Banoks Logo" class="logo">
        <?php if ( $show_nav ) : ?>
            <button type="button" class="banoks-hamburger" aria-label="Toggle menu" aria-expanded="false">
                <span class="hamburger-bar"></span>
                <span class="hamburger-bar"></span>
                <span class="hamburger-bar"></span>
            </button>
            <nav>
                <?php if ( current_user_can( 'manage_options' ) ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=banoks-pos-owner-dashboard' ) ); ?>" class="<?php echo ( isset( $_GET['page'] ) && 'banoks-pos-owner-dashboard' === $_GET['page'] ) ? 'active' : ''; ?>">DASHBOARD <span id="banoks-request-badge" class="banoks-nav-order-badge" style="<?php echo $pending_request_count > 0 ? '' : 'display:none;'; ?>"><?php echo esc_html( $pending_request_count ); ?></span></a>
                <?php endif; ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=banoks-pos' ) ); ?>" class="<?php echo ( isset( $_GET['page'] ) && 'banoks-pos' === $_GET['page'] ) ? 'active' : ''; ?>">WALK-IN ORDERS <span id="banoks-walk-in-order-badge" class="banoks-nav-order-badge" style="display:none;">0</span></a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=banoks-pos-pos' ) ); ?>" class="<?php echo ( isset( $_GET['page'] ) && 'banoks-pos-pos' === $_GET['page'] ) ? 'active' : ''; ?>">POINT OF SALE</a>
                <?php if ( current_user_can( 'banoks_use_pos' ) ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=banoks-pos-online-orders' ) ); ?>" class="<?php echo ( isset( $_GET['page'] ) && 'banoks-pos-online-orders' === $_GET['page'] ) ? 'active' : ''; ?>">ONLINE ORDERS <span id="banoks-online-order-badge" class="banoks-nav-order-badge" style="display:none;">0</span></a>
                    <?php if ( ! current_user_can( 'manage_options' ) ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=banoks-pos-expenses' ) ); ?>" class="<?php echo ( isset( $_GET['page'] ) && 'banoks-pos-expenses' === $_GET['page'] ) ? 'active' : ''; ?>">REQUESTS</a>
                    <?php endif; ?>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </div>
    <div class="header-right">
        <span class="cashier-name"><?php echo esc_html( $cashier_name ); ?></span>
    </div>
</div>

<script type="text/javascript">
    var banoksPOS = {
        ajax_url: '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
        nonce: '<?php echo esc_js( wp_create_nonce( 'banoks_pos_order_nonce' ) ); ?>',
        dashboard_url: '<?php echo esc_js( $dashboard_url ); ?>',
        online_orders_url: '<?php echo esc_js( admin_url( 'admin.php?page=banoks-pos-online-orders' ) ); ?>',
        can_manage_options: <?php echo current_user_can( 'manage_options' ) ? 'true' : 'false'; ?>
    };

    (function() {
        var header = document.querySelector('.banoks-admin-header');
        var toggle = document.querySelector('.banoks-hamburger');
        if ( ! header || ! toggle ) {
            return;
        }
        toggle.addEventListener('click', function(event) {
            event.stopPropagation();
            var isOpen = header.classList.toggle('nav-open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
        document.addEventListener('click', function(event) {
            if ( header.classList.contains('nav-open') && ! header.contains(event.target) ) {
                header.classList.remove('nav-open');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    })();
</script>

<div id="banoks-modal" class="banoks-modal-overlay">
    <div class="banoks-modal-content">
        <div class="modal-icon is-warning"><span class="dashicons dashicons-warning"></span></div>
        <h2 id="modal-title">Are you sure?</h2>
        <p id="modal-message">Do you want to proceed with this action?</p>
        <div class="modal-actions">
            <button id="modal-cancel-btn" class="button">Go Back</button>
            <button id="modal-confirm-btn" class="button button-primary">Yes, Proceed</button>
        </div>
    </div>
</div>
</div>
