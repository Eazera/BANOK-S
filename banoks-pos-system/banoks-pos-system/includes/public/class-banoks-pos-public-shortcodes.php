<?php
/**
 * Public shortcode rendering for Banoks POS.
 *
 * @link       https://banoks.com
 * @since      1.7.5
 * @package    Banoks_POS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles all Banoks POS public shortcode rendering.
 *
 * @since      1.7.5
 * @package    Banoks_POS
 */
class Banoks_POS_Public_Shortcodes {

    /**
     * Repository.
     *
     * @var Banoks_POS_Repository
     */
    private $repository;

    /**
     * Assets helper.
     *
     * @var Banoks_POS_Public_Assets
     */
    private $assets;

    /**
     * Customer auth helper.
     *
     * @var Banoks_Customer_Auth
     */
    private $auth;

    /**
     * Constructor.
     *
     * @since    1.7.5
     */
    public function __construct() {
        $this->repository = new Banoks_POS_Repository();
        $this->assets     = new Banoks_POS_Public_Assets();
        $this->auth       = new Banoks_Customer_Auth();
    }

    /**
     * Render a shortcode template.
     *
     * @param string $template Template name.
     * @param array  $data     Template data.
     * @return string
     */
    private function render( $template, $data = array() ) {
        $renderer = new Banoks_POS_Renderer();
        return $renderer->render( 'shortcodes/' . $template, $data );
    }

    // =========================================================================
    // SHORTCODE: banoks_customer_auth
    // =========================================================================

    /**
     * Render customer register/login block.
     *
     * @return string
     */
    public function render_customer_auth() {
        $this->assets->enqueue_public_assets();

        ob_start();
        $this->render_notice();
        echo $this->render(
            'customer-auth',
            array(
                'customer'       => $this->auth->get_current_customer(),
                'delivery_areas' => $this->get_auth_delivery_areas(),
            )
        );

        return ob_get_clean();
    }

    // =========================================================================
    // SHORTCODE: banoks_customer_login_button
    // =========================================================================

    /**
     * Render login/account button.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function render_customer_login_button( $atts = array() ) {
        $this->assets->enqueue_public_assets();

        $atts = shortcode_atts(
            array(
                'url'   => '',
                'label' => 'LOG-IN',
            ),
            $atts,
            'banoks_customer_login_button'
        );

        $customer = $this->auth->get_current_customer();
        $label    = $customer && ! empty( $customer->full_name ) ? $customer->full_name : $atts['label'];
        $url      = ! empty( $atts['url'] ) ? $atts['url'] : $this->assets->get_shortcode_page_url( 'banoks_customer_auth' );

        return $this->render(
            'customer-login-button',
            array(
                'customer' => $customer,
                'label'    => $label,
                'url'      => $url,
            )
        );
    }

    // =========================================================================
    // SHORTCODE: banoks_customer_address
    // =========================================================================

    /**
     * Render the logged-in customer's address.
     *
     * @return string
     */
    public function render_customer_address() {
        $this->assets->enqueue_public_assets();

        $customer = $this->auth->get_current_customer();
        if ( ! $customer || empty( $customer->address ) ) {
            return '';
        }

        $municipality  = ! empty( $customer->municipality ) ? $customer->municipality : 'Manukan';
        $address_parts = array_filter(
            array(
                ! empty( $customer->sitio ) ? $customer->sitio : '',
                ! empty( $customer->barangay ) ? $customer->barangay : '',
            )
        );

        if ( empty( $address_parts ) ) {
            $saved_parts = array_map( 'trim', explode( ',', $customer->address ) );
            if ( ! empty( $saved_parts[0] ) ) {
                $municipality = $saved_parts[0];
            }
            $address_parts = array_filter( array_slice( $saved_parts, 1 ) );
        }

        return $this->render(
            'customer-address',
            array(
                'municipality' => $municipality,
                'address'      => implode( ', ', $address_parts ),
            )
        );
    }

    // =========================================================================
    // SHORTCODE: banoks_theme_header
    // =========================================================================

    /**
     * Render the Banoks mobile app-style header.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function render_theme_header( $atts = array() ) {
        $this->assets->enqueue_public_assets();

        $atts = shortcode_atts(
            array(
                'search_placeholder' => 'Search menu',
            ),
            $atts,
            'banoks_theme_header'
        );

        return $this->render(
            'theme-header',
            array(
                'address_html'       => $this->render_customer_address(),
                'cart_html'          => $this->render_cart_button(),
                'login_html'         => $this->render_customer_login_button(),
                'logo_url'           => BANOKS_POS_URL . 'public/images/logo-white.svg',
                'menu_url'           => $this->assets->get_shortcode_page_url( 'banoks_online_menu', '#banoks-online-menu' ),
                'search_placeholder' => $atts['search_placeholder'],
            )
        );
    }

    // =========================================================================
    // SHORTCODE: banoks_theme_footer
    // =========================================================================

    /**
     * Render the Banoks mobile app-style footer.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function render_theme_footer( $atts = array() ) {
        $this->assets->enqueue_public_assets();

        $atts = shortcode_atts(
            array(
                'active'         => '',
                'menu_url'       => '',
                'cart_url'       => '',
                'me_url'         => '',
                'show_view_cart' => '1',
            ),
            $atts,
            'banoks_theme_footer'
        );

        $menu_url = ! empty( $atts['menu_url'] ) ? esc_url( trim( $atts['menu_url'] ) ) : $this->assets->get_shortcode_page_url( 'banoks_online_menu', '#banoks-online-menu' );
        $cart_url = ! empty( $atts['cart_url'] ) ? esc_url( trim( $atts['cart_url'] ) ) : $this->assets->get_shortcode_page_url( 'banoks_cart', '#banoks-cart' );
        $me_url   = ! empty( $atts['me_url'] ) ? esc_url( trim( $atts['me_url'] ) ) : $this->assets->get_shortcode_page_url( 'banoks_me' );
        $active   = sanitize_key( $atts['active'] );

        if ( '' === $active ) {
            $active = $this->get_theme_footer_active_item();
        }

        return $this->render(
            'theme-footer',
            array(
                'active'         => $active,
                'cart_html'      => $this->render_cart_button( array( 'url' => $cart_url ) ),
                'cart_url'       => $cart_url,
                'me_icon_url'    => BANOKS_POS_URL . 'public/images/me.svg',
                'me_url'         => $me_url,
                'menu_icon_url'  => BANOKS_POS_URL . 'public/images/menu.svg',
                'menu_url'       => $menu_url,
                'view_cart_html' => '1' === (string) $atts['show_view_cart'] ? $this->render_view_cart_button( array( 'url' => $cart_url ) ) : '',
            )
        );
    }

    /**
     * Detect the active footer item from the current page.
     *
     * @return string
     */
    private function get_theme_footer_active_item() {
        $post = is_singular() ? get_post() : null;

        if ( $this->assets->page_has_specific_shortcode( $post, 'banoks_cart' ) ) {
            return 'cart';
        }

        if ( $this->assets->page_has_specific_shortcode( $post, 'banoks_me' )
            || $this->assets->page_has_specific_shortcode( $post, 'banoks_customer_auth' )
            || $this->assets->page_has_specific_shortcode( $post, 'banoks_my_orders' ) ) {
            return 'me';
        }

        return 'menu';
    }

    // =========================================================================
    // SHORTCODE: banoks_online_menu
    // =========================================================================

    /**
     * Render the public menu product grid.
     *
     * @return string
     */
    public function render_online_menu() {
        $this->assets->enqueue_public_assets();

        global $wpdb;

        $products    = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}banoks_items WHERE COALESCE(is_active, 1) = 1 ORDER BY sort_order ASC, category ASC, product_name ASC" );
        $addon_rows  = $wpdb->get_results(
            "SELECT a.product_id, p.product_id AS addon_product_id, p.product_name, p.current_price, p.product_image_id, p.track_stock, p.stock_quantity, COALESCE(p.is_available, 1) AS is_available, COALESCE(p.is_active, 1) AS is_active
             FROM {$wpdb->prefix}banoks_product_addons a
             INNER JOIN {$wpdb->prefix}banoks_items p ON a.addon_product_id = p.product_id
             WHERE COALESCE(p.is_active, 1) = 1
             ORDER BY a.sort_order ASC, p.product_name ASC"
        );
        $product_ids = wp_list_pluck( $products, 'product_id' );
        $addon_ids   = wp_list_pluck( $addon_rows, 'addon_product_id' );
        $all_status_ids = array_values( array_unique( array_merge( array_map( 'absint', $product_ids ), array_map( 'absint', $addon_ids ) ) ) );
        $recipe_statuses = $this->repository->get_product_recipe_statuses( $all_status_ids, Banoks_POS_Repository::STOCK_LOCATION_MANUKAN );
        $addon_map   = $this->build_addon_map( $addon_rows, $recipe_statuses );

        $menu_categories = array();
        foreach ( $products as $product ) {
            $category     = ! empty( $product->category ) ? $product->category : 'General';
            $category_key = sanitize_title( $category );
            if ( ! isset( $menu_categories[ $category_key ] ) ) {
                $menu_categories[ $category_key ] = $category;
            }
        }

        ob_start();
        $this->render_notice();
        echo $this->render(
            'online-menu',
            array(
                'products'        => $products,
                'menu_categories' => $menu_categories,
                'recipe_statuses' => $recipe_statuses,
                'addon_map'       => $addon_map,
            )
        );

        return ob_get_clean();
    }

    // =========================================================================
    // SHORTCODE: banoks_cart_button
    // =========================================================================

    /**
     * Render a cart icon with live item count badge.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function render_cart_button( $atts = array() ) {
        $this->assets->enqueue_public_assets();

        $atts = shortcode_atts(
            array(
                'url' => '',
            ),
            $atts,
            'banoks_cart_button'
        );

        $cart_url = ! empty( $atts['url'] ) ? esc_url( trim( $atts['url'] ) ) : $this->assets->get_shortcode_page_url( 'banoks_cart', '#banoks-cart' );

        return $this->render( 'cart-button', array( 'cart_url' => $cart_url ) );
    }

    // =========================================================================
    // SHORTCODE: banoks_cart
    // =========================================================================

    /**
     * Render the cart page shell.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function render_cart( $atts = array() ) {
        $this->assets->enqueue_public_assets();
        if ( class_exists( 'Banoks_DB' ) ) {
            Banoks_DB::create_tables();
        }

        $atts = shortcode_atts(
            array(
                'menu_url'     => '',
                'checkout_url' => '',
            ),
            $atts,
            'banoks_cart'
        );

        $menu_url     = ! empty( $atts['menu_url'] ) ? esc_url( trim( $atts['menu_url'] ) ) : $this->assets->get_shortcode_page_url( 'banoks_online_menu', '#banoks-online-menu' );
        $checkout_url = ! empty( $atts['checkout_url'] ) ? esc_url( trim( $atts['checkout_url'] ) ) : $this->assets->get_shortcode_page_url( 'banoks_checkout', '#banoks-checkout' );
        $customer     = $this->auth->get_current_customer();
        $addresses    = array();

        if ( $customer ) {
            $this->repository->ensure_customer_default_address( $customer );
            $addresses = $this->repository->get_customer_addresses( $customer->id );
        }

        $gcash_profile = $customer ? $this->repository->get_customer_payment_profile( $customer->id, 'paymongo', 'gcash' ) : null;

        return $this->render(
            'cart',
            array(
                'menu_url'       => $menu_url,
                'checkout_url'   => $checkout_url,
                'customer'       => $customer,
                'addresses'      => $addresses,
                'gcash_profile'  => $gcash_profile,
                'addon_map'      => $this->get_online_addon_map(),
                'delivery_areas' => $this->get_auth_delivery_areas(),
                'delivery_icon'  => BANOKS_POS_URL . 'public/images/icons_deliver.svg',
                'pickup_icon'    => BANOKS_POS_URL . 'public/images/icons_pickup.svg',
                'delete_icon'    => BANOKS_POS_URL . 'public/images/icons_delete.svg',
            )
        );
    }

    // =========================================================================
    // SHORTCODE: banoks_view_cart_button
    // =========================================================================

    /**
     * Render a wide cart summary button.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function render_view_cart_button( $atts = array() ) {
        $this->assets->enqueue_public_assets();

        $atts = shortcode_atts(
            array(
                'url'        => '',
                'label'      => 'View my cart',
                'width'      => 'min(92vw, 520px)',
                'max_width'  => 'calc(100vw - 32px)',
                'margin'     => '0 auto',
                'height'     => '44px',
                'padding'    => '5px 14px',
                'gap'        => '9px',
                'font_size'  => '12px',
                'count_size' => '24px',
            ),
            $atts,
            'banoks_view_cart_button'
        );

        $cart_url = ! empty( $atts['url'] ) ? esc_url( $atts['url'] ) : $this->assets->get_shortcode_page_url( 'banoks_cart', '#banoks-cart' );
        $label    = ! empty( $atts['label'] ) ? sanitize_text_field( $atts['label'] ) : 'View my cart';

        $style_vars = array(
            '--banoks-view-cart-width:' . sanitize_text_field( $atts['width'] ),
            '--banoks-view-cart-max-width:' . sanitize_text_field( $atts['max_width'] ),
            '--banoks-view-cart-margin:' . sanitize_text_field( $atts['margin'] ),
            '--banoks-view-cart-height:' . sanitize_text_field( $atts['height'] ),
            '--banoks-view-cart-padding:' . sanitize_text_field( $atts['padding'] ),
            '--banoks-view-cart-gap:' . sanitize_text_field( $atts['gap'] ),
            '--banoks-view-cart-font-size:' . sanitize_text_field( $atts['font_size'] ),
            '--banoks-view-cart-count-size:' . sanitize_text_field( $atts['count_size'] ),
        );
        $style_attr = implode( ';', $style_vars ) . ';';

        return $this->render(
            'view-cart-button',
            array(
                'checkout_url' => $cart_url,
                'label'        => $label,
                'style_attr'   => $style_attr,
            )
        );
    }

    // =========================================================================
    // SHORTCODE: banoks_my_orders
    // =========================================================================

    /**
     * Render logged-in customer's orders.
     *
     * @return string
     */
    public function render_my_orders() {
        $this->assets->enqueue_public_assets();

        $customer = $this->auth->get_current_customer();
        $orders   = $customer ? $this->repository->get_customer_online_orders( $customer->id ) : array();

        ob_start();
        $this->render_notice();
        echo $this->render(
            'my-orders',
            array(
                'customer' => $customer,
                'orders'   => $orders,
            )
        );

        return ob_get_clean();
    }

    // =========================================================================
    // SHORTCODE: banoks_me
    // =========================================================================

    /**
     * Render the customer Me dashboard.
     *
     * @return string
     */
    public function render_me() {
        $this->assets->enqueue_public_assets();

        $customer     = $this->auth->get_current_customer();
        $orders       = array();
        $related_data = array(
            'items'          => array(),
            'proofs'         => array(),
            'logs'           => array(),
            'stock_warnings' => array(),
        );

        if ( $customer ) {
            $orders       = $this->repository->get_customer_online_orders( $customer->id );
            $related_data = $this->repository->get_online_order_related_data( $orders );
        }

        ob_start();
        $this->render_notice();
        echo $this->render(
            'me',
            array(
                'auth_url'     => $this->assets->get_shortcode_page_url( 'banoks_customer_auth' ),
                'customer'     => $customer,
                'me_icon_url'  => BANOKS_POS_URL . 'public/images/me.svg',
                'orders'       => $orders,
                'related_data' => $related_data,
            )
        );

        return ob_get_clean();
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Get addon map for online menu (shared between menu and cart).
     *
     * @return array
     */
    public function get_online_addon_map() {
        global $wpdb;

        $addon_rows = $wpdb->get_results(
            "SELECT a.product_id, p.product_id AS addon_product_id, p.product_name, p.current_price, p.product_image_id, p.track_stock, p.stock_quantity, COALESCE(p.is_available, 1) AS is_available, COALESCE(p.is_active, 1) AS is_active
             FROM {$wpdb->prefix}banoks_product_addons a
             INNER JOIN {$wpdb->prefix}banoks_items p ON a.addon_product_id = p.product_id
             WHERE COALESCE(p.is_active, 1) = 1
             ORDER BY a.sort_order ASC, p.product_name ASC"
        );

        $addon_ids       = wp_list_pluck( $addon_rows, 'addon_product_id' );
        $recipe_statuses = $this->repository->get_product_recipe_statuses( $addon_ids, Banoks_POS_Repository::STOCK_LOCATION_MANUKAN );

        return $this->build_addon_map( $addon_rows, $recipe_statuses );
    }

    /**
     * Build addon map from rows and recipe statuses.
     *
     * @param array $addon_rows     Addon DB rows.
     * @param array $recipe_statuses Recipe statuses.
     * @return array
     */
    private function build_addon_map( $addon_rows, $recipe_statuses ) {
        $addon_map = array();
        foreach ( $addon_rows as $addon ) {
            $product_id = absint( $addon->product_id );
            if ( ! isset( $addon_map[ $product_id ] ) ) {
                $addon_map[ $product_id ] = array();
            }

            $addon_image_url     = ! empty( $addon->product_image_id ) ? wp_get_attachment_image_url( absint( $addon->product_image_id ), 'thumbnail' ) : '';
            $addon_recipe_status = isset( $recipe_statuses[ absint( $addon->addon_product_id ) ] ) ? $recipe_statuses[ absint( $addon->addon_product_id ) ] : array();
            $addon_blocked       = ! intval( $addon->is_active )
                                || ! intval( $addon->is_available )
                                || ( ! empty( $addon->track_stock ) && intval( $addon->stock_quantity ) <= 0 )
                                || ( ! empty( $addon_recipe_status['has_recipe'] ) && empty( $addon_recipe_status['can_prepare'] ) );

            $addon_map[ $product_id ][] = array(
                'id'          => absint( $addon->addon_product_id ),
                'name'        => $addon->product_name,
                'price'       => floatval( $addon->current_price ),
                'image'       => $addon_image_url ? $addon_image_url : '',
                'canCheckout' => ! $addon_blocked,
                'stockLabel'  => $addon_blocked ? 'Out of Stock' : '',
            );
        }
        return $addon_map;
    }

    /**
     * Get deliverable areas (with fallback).
     *
     * @return array
     */
    public function get_auth_delivery_areas() {
        $areas       = $this->repository->get_delivery_areas();
        $deliverable = array();

        if ( is_array( $areas ) ) {
            foreach ( $areas as $area ) {
                if ( ! empty( $area->is_deliverable ) ) {
                    $deliverable[] = $area;
                }
            }
        }

        if ( ! empty( $deliverable ) ) {
            return $deliverable;
        }

        $fallback_names = array(
            'Poblacion',
            'Linay',
            'San Antonio',
            'Dipane',
            'Lupasang',
        );

        return array_map(
            function ( $name ) {
                return (object) array(
                    'id'             => 0,
                    'area_name'      => $name,
                    'is_deliverable' => 1,
                );
            },
            $fallback_names
        );
    }

    /**
     * Render notice from query args.
     *
     * @return void
     */
    private function render_notice() {
        if ( isset( $_GET['banoks_error'] ) ) {
            echo '<div class="banoks-notice is-error">' . esc_html( sanitize_text_field( wp_unslash( $_GET['banoks_error'] ) ) ) . '</div>';
        } elseif ( isset( $_GET['banoks_notice'] ) ) {
            echo '<div class="banoks-notice is-success">' . esc_html( sanitize_text_field( wp_unslash( $_GET['banoks_notice'] ) ) ) . '</div>';
        }
    }
}