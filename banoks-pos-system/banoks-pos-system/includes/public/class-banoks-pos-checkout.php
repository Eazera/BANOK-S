<?php
/**
 * Online checkout processing for Banoks POS.
 *
 * @link       https://banoks.com
 * @since      1.7.5
 * @package    Banoks_POS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles checkout AJAX, PayMongo integration, and payment proof serving.
 *
 * @since      1.7.5
 * @package    Banoks_POS
 */
class Banoks_POS_Checkout {

    /**
     * Repository.
     *
     * @var Banoks_POS_Repository
     */
    private $repository;

    /**
     * Constructor.
     *
     * @since    1.7.5
     */
    public function __construct() {
        $this->repository = new Banoks_POS_Repository();
    }

    /**
     * Handle checkout AJAX.
     *
     * @since    1.7.5
     * @return   void
     */
    public function ajax_checkout() {
        check_ajax_referer( 'banoks_customer_checkout', 'nonce' );

        if ( class_exists( 'Banoks_DB' ) ) {
            Banoks_DB::create_tables();
        }

        $auth     = new Banoks_Customer_Auth();
        $customer = $auth->get_current_customer();

        if ( ! $customer ) {
            wp_send_json_error( array( 'message' => 'Please log in before placing an order.' ), 401 );
        }

        $items = $this->get_checkout_items_from_request();
        if ( empty( $items ) ) {
            wp_send_json_error( array( 'message' => 'Please select at least one item.' ), 400 );
        }

        $fulfillment_type = isset( $_POST['fulfillment_type'] ) ? sanitize_key( wp_unslash( $_POST['fulfillment_type'] ) ) : 'delivery';
        if ( ! in_array( $fulfillment_type, array( 'delivery', 'pickup' ), true ) ) {
            wp_send_json_error( array( 'message' => 'Invalid order type.' ), 400 );
        }

        $payment_method = isset( $_POST['payment_method'] ) ? sanitize_key( wp_unslash( $_POST['payment_method'] ) ) : 'cod';
        if ( 'pickup' === $fulfillment_type && 'cod' === $payment_method ) {
            $payment_method = 'pay_at_pickup';
        }

        $order_data = array(
            'customer_id'      => $customer->id,
            'items'            => $items,
            'fulfillment_type' => $fulfillment_type,
            'payment_method'   => $payment_method,
        );

        if ( 'delivery' === $fulfillment_type ) {
            $address = $this->get_checkout_selected_address( $customer->id );
            if ( ! $address ) {
                wp_send_json_error( array( 'message' => 'Please select a delivery address.' ), 400 );
            }

            $order_data['delivery_area_id'] = $address->delivery_area_id;
            $order_data['delivery_address'] = $address->address;
        }

        if ( 'gcash' === $payment_method ) {
            if ( ! $this->is_paymongo_enabled() ) {
                wp_send_json_error( array( 'message' => 'GCash checkout is not available right now.' ), 400 );
            }
            $order_data['payment_gateway'] = 'paymongo';
        }

        $result = $this->repository->create_online_order( $order_data );
        if ( isset( $result['error'] ) ) {
            wp_send_json_error( array( 'message' => $result['error'] ), 400 );
        }

        if ( 'gcash' !== $payment_method ) {
            wp_send_json_success(
                array(
                    'message'       => 'Order placed successfully.',
                    'orderId'       => absint( $result['online_order_id'] ),
                    'publicOrderId' => $result['public_order_id'],
                    'paymentMethod' => $payment_method,
                )
            );
        }

        $intent = $this->create_paymongo_payment_intent( $result['online_order_id'], $result['public_order_id'], $result['total_amount'], $customer );
        if ( is_wp_error( $intent ) ) {
            $this->repository->mark_paymongo_order_failed(
                (object) array( 'id' => $result['online_order_id'], 'payment_gateway' => 'paymongo' ),
                $intent->get_error_message(),
                ''
            );
            wp_send_json_error( array( 'message' => $intent->get_error_message() ), 400 );
        }

        $this->repository->update_online_order_gateway_data(
            $result['online_order_id'],
            array(
                'paymongo_payment_intent_id' => $intent['id'],
                'gateway_checkout_status'    => $intent['status'],
            )
        );

        wp_send_json_success(
            array(
                'message'       => 'Continue to GCash to approve your payment.',
                'orderId'       => absint( $result['online_order_id'] ),
                'publicOrderId' => $result['public_order_id'],
                'paymentMethod' => 'gcash',
                'paymongo'      => array(
                    'publicKey'       => $this->get_paymongo_settings()['public_key'],
                    'paymentIntentId' => $intent['id'],
                    'clientKey'       => $intent['client_key'],
                    'returnUrl'       => add_query_arg(
                        array(
                            'banoks_paymongo_return' => '1',
                            'banoks_order_id'        => absint( $result['online_order_id'] ),
                        ),
                        $this->get_cart_page_url()
                    ),
                    'billing'         => array(
                        'name'  => $customer->full_name,
                        'email' => $customer->email,
                        'phone' => $customer->phone,
                    ),
                ),
            )
        );
    }

    /**
     * Handle AJAX order payment status check.
     *
     * @since    1.7.5
     * @return   void
     */
    public function ajax_order_payment_status() {
        check_ajax_referer( 'banoks_customer_order_payment_status', 'nonce' );

        $auth     = new Banoks_Customer_Auth();
        $customer = $auth->get_current_customer();
        if ( ! $customer ) {
            wp_send_json_error( array( 'message' => 'Please log in to view this order.' ), 401 );
        }

        $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
        $order    = $this->repository->get_online_order_with_items( $order_id );
        if ( ! $order || empty( $order['order'] ) || intval( $order['order']->customer_id ) !== intval( $customer->id ) ) {
            wp_send_json_error( array( 'message' => 'Order not found.' ), 404 );
        }

        wp_send_json_success(
            array(
                'paymentStatus'  => $order['order']->payment_status,
                'orderStatus'    => $order['order']->order_status,
                'checkoutStatus' => $order['order']->gateway_checkout_status,
                'failureReason'  => $order['order']->payment_failure_reason,
            )
        );
    }

    /**
     * Handle AJAX cart item availability check.
     *
     * @since    1.7.5
     * @return   void
     */
    public function ajax_cart_item_availability() {
        check_ajax_referer( 'banoks_cart_item_availability', 'nonce' );

        $raw_ids = isset( $_POST['product_ids'] ) ? wp_unslash( $_POST['product_ids'] ) : '';
        $ids     = is_string( $raw_ids ) ? json_decode( $raw_ids, true ) : $raw_ids;
        if ( ! is_array( $ids ) ) {
            $ids = array();
        }

        wp_send_json_success(
            array(
                'items' => $this->repository->get_online_product_availability( $ids, Banoks_POS_Repository::STOCK_LOCATION_MANUKAN ),
            )
        );
    }

    /**
     * Handle PayMongo webhook.
     *
     * @since    1.7.5
     * @param    WP_REST_Request $request Request.
     * @return   WP_REST_Response
     */
    public function handle_paymongo_webhook( $request ) {
        $payload = $request->get_body();
        if ( ! $this->verify_paymongo_webhook_signature( $payload ) ) {
            return new WP_REST_Response( array( 'message' => 'Invalid signature.' ), 401 );
        }

        $event = json_decode( $payload, true );
        if ( ! is_array( $event ) || empty( $event['data']['attributes']['type'] ) ) {
            return new WP_REST_Response( array( 'message' => 'Ignored.' ), 200 );
        }

        $event_id   = isset( $event['data']['id'] ) ? sanitize_text_field( $event['data']['id'] ) : '';
        $event_type = sanitize_text_field( $event['data']['attributes']['type'] );
        $resource   = isset( $event['data']['attributes']['data'] ) && is_array( $event['data']['attributes']['data'] ) ? $event['data']['attributes']['data'] : array();
        $attributes = isset( $resource['attributes'] ) && is_array( $resource['attributes'] ) ? $resource['attributes'] : array();

        if ( in_array( $event_type, array( 'payment.paid', 'payment.failed' ), true ) ) {
            $payment_intent_id = isset( $attributes['payment_intent_id'] ) ? sanitize_text_field( $attributes['payment_intent_id'] ) : '';
            $order             = $payment_intent_id ? $this->repository->get_online_order_by_paymongo_intent( $payment_intent_id ) : null;

            if ( $order ) {
                $payment_id        = isset( $resource['id'] ) ? sanitize_text_field( $resource['id'] ) : '';
                $source            = isset( $attributes['source'] ) && is_array( $attributes['source'] ) ? $attributes['source'] : array();
                $payment_method_id = isset( $source['id'] ) ? sanitize_text_field( $source['id'] ) : '';

                if ( 'payment.paid' === $event_type ) {
                    $this->repository->mark_paymongo_order_paid( $order, $payment_id, $payment_method_id, $event_id );
                } else {
                    $reason = isset( $attributes['failed_message'] ) ? $attributes['failed_message'] : 'PayMongo payment failed.';
                    $this->repository->mark_paymongo_order_failed( $order, $reason, $event_id );
                }
            }
        } elseif ( in_array( $event_type, array( 'payment_intent.succeeded', 'payment_intent.payment_failed' ), true ) ) {
            $payment_intent_id = isset( $resource['id'] ) ? sanitize_text_field( $resource['id'] ) : '';
            $order             = $payment_intent_id ? $this->repository->get_online_order_by_paymongo_intent( $payment_intent_id ) : null;

            if ( $order ) {
                $payments  = isset( $attributes['payments'] ) && is_array( $attributes['payments'] ) ? $attributes['payments'] : array();
                $payment   = ! empty( $payments ) && is_array( $payments[0] ) ? $payments[0] : array();
                $payment_id = isset( $payment['id'] ) ? sanitize_text_field( $payment['id'] ) : '';

                if ( 'payment_intent.succeeded' === $event_type || ( isset( $attributes['status'] ) && 'succeeded' === $attributes['status'] ) ) {
                    $this->repository->mark_paymongo_order_paid( $order, $payment_id, '', $event_id );
                } else {
                    $error = isset( $attributes['last_payment_error']['failed_message'] ) ? $attributes['last_payment_error']['failed_message'] : 'PayMongo GCash payment failed.';
                    $this->repository->mark_paymongo_order_failed( $order, $error, $event_id );
                }
            }
        }

        return new WP_REST_Response( array( 'message' => 'SUCCESS' ), 200 );
    }

    /**
     * Serve a private payment proof file.
     *
     * @since    1.7.5
     * @return   void
     */
    public function serve_private_payment_proof() {
        if ( ! current_user_can( 'banoks_use_pos' ) ) {
            wp_die( esc_html__( 'You do not have permission to view this payment proof.', 'banoks-pos' ), 403 );
        }

        $proof_id = isset( $_GET['proof_id'] ) ? absint( $_GET['proof_id'] ) : 0;
        if ( ! $proof_id || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'banoks_view_payment_proof_' . $proof_id ) ) {
            wp_die( esc_html__( 'Invalid payment proof link.', 'banoks-pos' ), 403 );
        }

        global $wpdb;
        $proof = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT screenshot_url FROM {$wpdb->prefix}banoks_payment_proofs WHERE id = %d",
                $proof_id
            )
        );

        if ( ! $proof || empty( $proof->screenshot_url ) || 0 !== strpos( $proof->screenshot_url, 'banoks-private://' ) ) {
            wp_die( esc_html__( 'Payment proof not found.', 'banoks-pos' ), 404 );
        }

        $base_dir = $this->get_private_payment_proof_dir();
        if ( is_wp_error( $base_dir ) ) {
            wp_die( esc_html( $base_dir->get_error_message() ), 500 );
        }

        $filename = basename( substr( $proof->screenshot_url, strlen( 'banoks-private://' ) ) );
        $path     = trailingslashit( $base_dir ) . $filename;
        if ( ! is_file( $path ) ) {
            wp_die( esc_html__( 'Payment proof file not found.', 'banoks-pos' ), 404 );
        }

        $mime = wp_check_filetype( $path );
        nocache_headers();
        header( 'Content-Type: ' . ( ! empty( $mime['type'] ) ? $mime['type'] : 'application/octet-stream' ) );
        header( 'Content-Length: ' . filesize( $path ) );
        header( 'X-Content-Type-Options: nosniff' );
        readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
        exit;
    }

    // ---- Private helpers ----

    /**
     * Get checkout items from request.
     *
     * @return array
     */
    private function get_checkout_items_from_request() {
        $raw  = isset( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : '';
        $items = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
        $cart_quantities = array();

        if ( ! is_array( $items ) ) {
            return array();
        }

        foreach ( $items as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $product_id = isset( $item['id'] ) ? absint( $item['id'] ) : 0;
            $quantity   = isset( $item['qty'] ) ? absint( $item['qty'] ) : 0;
            if ( $product_id && $quantity ) {
                $cart_quantities[ $product_id ] = isset( $cart_quantities[ $product_id ] ) ? $cart_quantities[ $product_id ] + $quantity : $quantity;
            }
        }

        return $cart_quantities;
    }

    /**
     * Get selected address from request.
     *
     * @param int $customer_id Customer ID.
     * @return object|null
     */
    private function get_checkout_selected_address( $customer_id ) {
        $address_id = isset( $_POST['address_id'] ) ? absint( $_POST['address_id'] ) : 0;
        if ( ! $address_id ) {
            return null;
        }

        foreach ( $this->repository->get_customer_addresses( $customer_id ) as $address ) {
            if ( intval( $address->id ) === $address_id ) {
                return $address;
            }
        }

        return null;
    }

    /**
     * Check if PayMongo is enabled.
     *
     * @return bool
     */
    private function is_paymongo_enabled() {
        $settings = $this->get_paymongo_settings();
        return '1' === $settings['enabled'] && ! empty( $settings['public_key'] ) && ! empty( $settings['secret_key'] );
    }

    /**
     * Get PayMongo settings.
     *
     * @return array
     */
    private function get_paymongo_settings() {
        if ( class_exists( 'Banoks_POS_Admin' ) ) {
            return Banoks_POS_Admin::get_paymongo_settings();
        }

        return array(
            'enabled'                => '0',
            'mode'                   => 'test',
            'public_key'             => '',
            'secret_key'             => '',
            'webhook_signing_secret' => '',
        );
    }

    /**
     * Get cart page URL for PayMongo return.
     *
     * @return string
     */
    private function get_cart_page_url() {
        $assets = new Banoks_POS_Public_Assets();
        return $assets->get_shortcode_page_url( 'banoks_cart', '#banoks-cart' );
    }

    /**
     * Create a PayMongo payment intent.
     *
     * @param int    $order_id         Order ID.
     * @param string $public_order_id  Public order ID.
     * @param float  $total_amount     Total amount.
     * @param object $customer         Customer object.
     * @return array|WP_Error
     */
    private function create_paymongo_payment_intent( $order_id, $public_order_id, $total_amount, $customer ) {
        $settings = $this->get_paymongo_settings();
        $amount   = max( 100, intval( round( floatval( $total_amount ) * 100 ) ) );
        $body     = array(
            'data' => array(
                'attributes' => array(
                    'amount'                 => $amount,
                    'currency'               => 'PHP',
                    'payment_method_allowed' => array( 'gcash' ),
                    'description'            => 'Banoks order ' . $public_order_id,
                    'metadata'               => array(
                        'banoks_order_id'        => (string) absint( $order_id ),
                        'banoks_public_order_id' => (string) $public_order_id,
                        'banoks_customer_id'     => (string) absint( $customer->id ),
                    ),
                ),
            ),
        );

        $response = wp_remote_post(
            'https://api.paymongo.com/v1/payment_intents',
            array(
                'timeout' => 20,
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode( $settings['secret_key'] . ':' ),
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ),
                'body'    => wp_json_encode( $body ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $data        = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( $status_code < 200 || $status_code >= 300 || empty( $data['data']['id'] ) || empty( $data['data']['attributes']['client_key'] ) ) {
            return new WP_Error( 'banoks_paymongo_intent_failed', $this->get_paymongo_error_message( $data, 'Could not start PayMongo GCash payment.' ) );
        }

        return array(
            'id'         => sanitize_text_field( $data['data']['id'] ),
            'client_key' => sanitize_text_field( $data['data']['attributes']['client_key'] ),
            'status'     => isset( $data['data']['attributes']['status'] ) ? sanitize_text_field( $data['data']['attributes']['status'] ) : 'awaiting_payment_method',
        );
    }

    /**
     * Extract error message from PayMongo response.
     *
     * @param array  $data     Response data.
     * @param string $fallback Fallback message.
     * @return string
     */
    private function get_paymongo_error_message( $data, $fallback ) {
        if ( isset( $data['errors'][0]['detail'] ) ) {
            return sanitize_text_field( $data['errors'][0]['detail'] );
        }

        if ( isset( $data['errors'][0]['message'] ) ) {
            return sanitize_text_field( $data['errors'][0]['message'] );
        }

        return $fallback;
    }

    /**
     * Verify PayMongo webhook signature.
     *
     * @param string $payload Raw request body.
     * @return bool
     */
    private function verify_paymongo_webhook_signature( $payload ) {
        $settings = $this->get_paymongo_settings();
        $secret   = $settings['webhook_signing_secret'];
        if ( '' === $secret ) {
            return false;
        }

        $header = isset( $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ) ) : '';
        if ( '' === $header ) {
            return false;
        }

        $parts = array();
        foreach ( explode( ',', $header ) as $piece ) {
            $pair = array_map( 'trim', explode( '=', $piece, 2 ) );
            if ( 2 === count( $pair ) ) {
                $parts[ $pair[0] ] = $pair[1];
            }
        }

        $timestamp = isset( $parts['t'] ) ? $parts['t'] : '';
        $mode_key  = 'live' === $settings['mode'] ? 'li' : 'te';
        $signature = isset( $parts[ $mode_key ] ) ? $parts[ $mode_key ] : '';

        if ( '' === $timestamp || '' === $signature ) {
            return false;
        }

        $expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret );
        return hash_equals( $expected, $signature );
    }

    /**
     * Get private payment proof directory.
     *
     * @return string|WP_Error
     */
    private function get_private_payment_proof_dir() {
        $upload_dir = wp_upload_dir();
        $base_dir   = trailingslashit( $upload_dir['basedir'] ) . 'banoks-private/payment-proofs';

        if ( ! wp_mkdir_p( $base_dir ) ) {
            return new WP_Error( 'banoks_private_upload_dir', 'Could not prepare secure payment proof storage.' );
        }

        $deny_file = trailingslashit( $base_dir ) . '.htaccess';
        if ( ! file_exists( $deny_file ) ) {
            file_put_contents( $deny_file, "Deny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        }

        $index_file = trailingslashit( $base_dir ) . 'index.html';
        if ( ! file_exists( $index_file ) ) {
            file_put_contents( $index_file, '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        }

        return $base_dir;
    }
}