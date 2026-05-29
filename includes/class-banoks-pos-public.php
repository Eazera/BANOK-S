<?php
/**
 * Public online ordering shortcodes — coordinator.
 *
 * Delegates shortcode rendering to Banoks_POS_Public_Shortcodes,
 * customer auth to Banoks_Customer_Auth, checkout to Banoks_POS_Checkout,
 * and assets to Banoks_POS_Public_Assets.
 *
 * @package Banoks_POS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Coordinator for Banoks POS public functionality.
 *
 * All original public method signatures are preserved for backward
 * compatibility. Each responsibility is delegated to a focused class.
 *
 * @since      1.0.0
 * @package    Banoks_POS
 */
class Banoks_POS_Public {

    /**
     * Delegate shortcodes.
     *
     * @var Banoks_POS_Public_Shortcodes
     */
    private $shortcodes;

    /**
     * Delegate customer auth.
     *
     * @var Banoks_Customer_Auth
     */
    private $auth;

    /**
     * Delegate checkout.
     *
     * @var Banoks_POS_Checkout
     */
    private $checkout;

    /**
     * Delegate assets.
     *
     * @var Banoks_POS_Public_Assets
     */
    private $assets;

    /**
     * Initialize public hooks and delegates.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->assets     = new Banoks_POS_Public_Assets();
        $this->auth       = new Banoks_Customer_Auth();
        $this->shortcodes = new Banoks_POS_Public_Shortcodes();
        $this->checkout   = new Banoks_POS_Checkout();

        add_action( 'init', array( $this->auth, 'handle_forms' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_action( 'admin_post_banoks_view_payment_proof', array( $this->checkout, 'serve_private_payment_proof' ) );
        add_action( 'wp_enqueue_scripts', array( $this->assets, 'enqueue' ), 5 );
        add_filter( 'body_class', array( $this, 'add_auth_body_class' ) );
        add_filter( 'style_loader_tag', array( $this->assets, 'preload_stylesheet' ), 10, 4 );
        add_shortcode( 'banoks_customer_auth', array( $this->shortcodes, 'render_customer_auth' ) );
        add_shortcode( 'banoks_customer_login_button', array( $this->shortcodes, 'render_customer_login_button' ) );
        add_shortcode( 'banoks_customer_address', array( $this->shortcodes, 'render_customer_address' ) );
        add_shortcode( 'banoks_theme_header', array( $this->shortcodes, 'render_theme_header' ) );
        add_shortcode( 'banoks_theme_footer', array( $this->shortcodes, 'render_theme_footer' ) );
        add_shortcode( 'banoks_online_menu', array( $this->shortcodes, 'render_online_menu' ) );
        add_shortcode( 'banoks_cart', array( $this->shortcodes, 'render_cart' ) );
        add_shortcode( 'banoks_cart_button', array( $this->shortcodes, 'render_cart_button' ) );
        add_shortcode( 'banoks_view_cart_button', array( $this->shortcodes, 'render_view_cart_button' ) );
        add_shortcode( 'banoks_my_orders', array( $this->shortcodes, 'render_my_orders' ) );
        add_shortcode( 'banoks_me', array( $this->shortcodes, 'render_me' ) );
        add_action( 'wp_ajax_nopriv_banoks_customer_login', array( $this->auth, 'ajax_login' ) );
        add_action( 'wp_ajax_banoks_customer_login', array( $this->auth, 'ajax_login' ) );
        add_action( 'wp_ajax_nopriv_banoks_customer_register', array( $this->auth, 'ajax_register' ) );
        add_action( 'wp_ajax_banoks_customer_register', array( $this->auth, 'ajax_register' ) );
        add_action( 'wp_ajax_nopriv_banoks_customer_add_address', array( $this, 'ajax_add_customer_address' ) );
        add_action( 'wp_ajax_banoks_customer_add_address', array( $this, 'ajax_add_customer_address' ) );
        add_action( 'wp_ajax_nopriv_banoks_customer_checkout', array( $this->checkout, 'ajax_checkout' ) );
        add_action( 'wp_ajax_banoks_customer_checkout', array( $this->checkout, 'ajax_checkout' ) );
        add_action( 'wp_ajax_nopriv_banoks_customer_order_payment_status', array( $this->checkout, 'ajax_order_payment_status' ) );
        add_action( 'wp_ajax_banoks_customer_order_payment_status', array( $this->checkout, 'ajax_order_payment_status' ) );
        add_action( 'wp_ajax_nopriv_banoks_cart_item_availability', array( $this->checkout, 'ajax_cart_item_availability' ) );
        add_action( 'wp_ajax_banoks_cart_item_availability', array( $this->checkout, 'ajax_cart_item_availability' ) );
    }

    /**
     * Register REST routes.
     *
     * @since    1.7.5
     * @return   void
     */
    public function register_rest_routes() {
        register_rest_route(
            'banoks-pos/v1',
            '/paymongo/webhook',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this->checkout, 'handle_paymongo_webhook' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    /**
     * Add a body class to auth pages.
     *
     * @since    1.0.0
     * @param    array $classes Body classes.
     * @return   array
     */
    public function add_auth_body_class( $classes ) {
        if ( ! is_singular() ) {
            return $classes;
        }

        $post = get_post();
        if ( $this->assets->page_has_specific_shortcode( $post, 'banoks_customer_auth' ) ) {
            $classes[] = 'banoks-auth-page';
        }

        return $classes;
    }

    /**
     * Handle AJAX add customer address.
     *
     * Kept in the coordinator because it bridges auth, repository,
     * and formatting concerns.
     *
     * @since    1.0.0
     * @return   void
     */
    public function ajax_add_customer_address() {
        check_ajax_referer( 'banoks_customer_add_address', 'nonce' );

        if ( class_exists( 'Banoks_DB' ) ) {
            Banoks_DB::create_tables();
        }

        $customer = $this->auth->get_current_customer();
        if ( ! $customer ) {
            wp_send_json_error( array( 'message' => 'Please log in before adding a delivery address.' ), 401 );
        }

        $barangay = isset( $_POST['barangay'] ) ? sanitize_text_field( wp_unslash( $_POST['barangay'] ) ) : '';
        $sitio    = isset( $_POST['sitio'] ) ? sanitize_text_field( wp_unslash( $_POST['sitio'] ) ) : '';

        if ( '' === $barangay || '' === $sitio ) {
            wp_send_json_error( array( 'message' => 'Please complete the delivery address.' ), 400 );
        }

        $delivery_area_id = $this->get_delivery_area_id_by_name( $barangay );
        if ( null === $delivery_area_id ) {
            wp_send_json_error( array( 'message' => 'Please choose an available barangay.' ), 400 );
        }

        $repository = new Banoks_POS_Repository();
        $address    = $repository->create_customer_address(
            $customer->id,
            array(
                'municipality'     => 'Manukan',
                'barangay'         => $barangay,
                'sitio'            => $sitio,
                'delivery_area_id' => $delivery_area_id,
                'is_default'       => ! empty( $_POST['is_default'] ),
            )
        );

        if ( is_array( $address ) && isset( $address['error'] ) ) {
            wp_send_json_error( array( 'message' => $address['error'] ), 400 );
        }

        wp_send_json_success(
            array(
                'message' => 'Delivery address saved.',
                'address' => $this->format_checkout_address( $address ),
            )
        );
    }

    /**
     * Format an address for checkout response.
     *
     * @param object $address Address row.
     * @return array
     */
    private function format_checkout_address( $address ) {
        $delivery_fee = 0;
        if ( isset( $address->delivery_area_id ) ) {
            $areas = $this->shortcodes->get_auth_delivery_areas();
            foreach ( $areas as $area ) {
                if ( intval( $area->id ) === intval( $address->delivery_area_id ) ) {
                    $delivery_fee = isset( $area->delivery_fee ) ? floatval( $area->delivery_fee ) : 0;
                    break;
                }
            }
        }

        return array(
            'id'             => isset( $address->id ) ? absint( $address->id ) : 0,
            'municipality'   => isset( $address->municipality ) ? $address->municipality : 'Manukan',
            'barangay'       => isset( $address->barangay ) ? $address->barangay : '',
            'sitio'          => isset( $address->sitio ) ? $address->sitio : '',
            'address'        => isset( $address->address ) ? $address->address : '',
            'deliveryAreaId' => isset( $address->delivery_area_id ) ? absint( $address->delivery_area_id ) : 0,
            'deliveryFee'    => $delivery_fee,
            'isDefault'      => ! empty( $address->is_default ),
        );
    }

    /**
     * Get delivery area ID by name.
     *
     * @param string $name Area name.
     * @return int|null
     */
    private function get_delivery_area_id_by_name( $name ) {
        $name  = sanitize_text_field( $name );
        $areas = $this->shortcodes->get_auth_delivery_areas();
        foreach ( $areas as $area ) {
            if ( isset( $area->area_name ) && strtolower( $area->area_name ) === strtolower( $name ) ) {
                return isset( $area->id ) ? absint( $area->id ) : 0;
            }
        }
        return null;
    }
}