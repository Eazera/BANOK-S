<?php
/**
 * Customer authentication for Banoks POS.
 *
 * @link       https://banoks.com
 * @since      1.7.5
 * @package    Banoks_POS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles customer login, registration, session cookies, and login lockout.
 *
 * @since      1.7.5
 * @package    Banoks_POS
 */
class Banoks_Customer_Auth {

    const COOKIE_NAME = 'banoks_customer_session';
    const LOGIN_LOCK_TTL = 15 * MINUTE_IN_SECONDS;
    const LOGIN_MAX_ATTEMPTS = 5;

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
     * Process register/login/logout form submissions (non-AJAX).
     *
     * @since    1.7.5
     * @return   void
     */
    public function handle_forms() {
        if ( wp_doing_ajax() ) {
            return;
        }

        if ( empty( $_POST['banoks_public_action'] ) ) {
            return;
        }

        $action = sanitize_key( wp_unslash( $_POST['banoks_public_action'] ) );

        if ( 'register' === $action ) {
            $this->handle_register();
        } elseif ( 'login' === $action ) {
            $this->handle_login();
        } elseif ( 'logout' === $action ) {
            check_admin_referer( 'banoks_customer_logout' );
            $this->clear_customer_cookie();
            wp_safe_redirect( remove_query_arg( array( 'banoks_notice', 'banoks_error', 'banoks_order_success' ), wp_get_referer() ?: home_url( '/' ) ) );
            exit;
        }
    }

    /**
     * Handle register form submission.
     *
     * @since    1.7.5
     * @return   void
     */
    public function handle_register() {
        check_admin_referer( 'banoks_customer_register' );

        $result = $this->register_customer_from_request();
        if ( is_wp_error( $result ) ) {
            $this->redirect_with_notice( $result->get_error_message(), true );
        }

        $this->set_customer_cookie( $result );
        $this->redirect_with_notice(
            'Account created successfully.',
            false,
            array(),
            $this->get_shortcode_page_url( 'banoks_online_menu', '#banoks-online-menu' )
        );
    }

    /**
     * Handle register via AJAX.
     *
     * @since    1.7.5
     * @return   void
     */
    public function ajax_register() {
        check_ajax_referer( 'banoks_customer_register', 'nonce' );

        $result = $this->register_customer_from_request();
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
        }

        $this->set_customer_cookie( $result );
        wp_send_json_success(
            array(
                'message'     => 'Account created successfully.',
                'redirectUrl' => $this->get_shortcode_page_url( 'banoks_online_menu', '#banoks-online-menu' ),
            )
        );
    }

    /**
     * Handle login via AJAX.
     *
     * @since    1.7.5
     * @return   void
     */
    public function ajax_login() {
        check_ajax_referer( 'banoks_customer_login', 'nonce' );

        $customer = $this->login_customer_from_request();
        if ( is_wp_error( $customer ) ) {
            wp_send_json_error( array( 'message' => $customer->get_error_message() ), 400 );
        }

        $this->set_customer_cookie( $customer );
        wp_send_json_success(
            array(
                'message'     => 'Logged in successfully.',
                'redirectUrl' => $this->get_shortcode_page_url( 'banoks_online_menu', '#banoks-online-menu' ),
            )
        );
    }

    /**
     * Get current customer from cookie.
     *
     * @since    1.7.5
     * @return   object|null
     */
    public function get_current_customer() {
        if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
            return null;
        }

        $parts = explode( ':', sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) ) );
        if ( ! in_array( count( $parts ), array( 2, 3 ), true ) ) {
            return null;
        }

        $customer_id = absint( $parts[0] );
        $expires     = 3 === count( $parts ) ? absint( $parts[1] ) : 0;
        $signature   = 3 === count( $parts ) ? $parts[2] : $parts[1];

        if ( $expires && time() > $expires ) {
            $this->clear_customer_cookie();
            return null;
        }

        $signed_value = $expires ? 'banoks_customer|' . $customer_id . '|' . $expires : 'banoks_customer|' . $customer_id;
        if ( ! hash_equals( wp_hash( $signed_value ), $signature ) ) {
            return null;
        }

        return $this->repository->get_customer( $customer_id );
    }

    /**
     * Set customer auth cookie.
     *
     * @since    1.7.5
     * @param    object $customer Customer row object.
     * @return   void
     */
    public function set_customer_cookie( $customer ) {
        $expires = time() + WEEK_IN_SECONDS;
        $value   = intval( $customer->id ) . ':' . $expires . ':' . wp_hash( 'banoks_customer|' . intval( $customer->id ) . '|' . $expires );
        setcookie( self::COOKIE_NAME, $value, $expires, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true );
        $_COOKIE[ self::COOKIE_NAME ] = $value;
    }

    /**
     * Clear customer auth cookie.
     *
     * @since    1.7.5
     * @return   void
     */
    public function clear_customer_cookie() {
        setcookie( self::COOKIE_NAME, '', time() - HOUR_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true );
        unset( $_COOKIE[ self::COOKIE_NAME ] );
    }

    // ---- Private methods ----

    /**
     * Process registration from request data.
     *
     * @since    1.7.5
     * @return   object|WP_Error
     */
    private function register_customer_from_request() {
        $password         = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
        $confirm_password = isset( $_POST['confirm_password'] ) ? (string) wp_unslash( $_POST['confirm_password'] ) : '';
        $full_name        = isset( $_POST['full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) : '';
        $username         = isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ), true ) : '';
        $phone            = isset( $_POST['contact_number'] ) ? sanitize_text_field( wp_unslash( $_POST['contact_number'] ) ) : '';
        $barangay         = isset( $_POST['barangay'] ) ? sanitize_text_field( wp_unslash( $_POST['barangay'] ) ) : '';
        $sitio            = isset( $_POST['sitio'] ) ? sanitize_text_field( wp_unslash( $_POST['sitio'] ) ) : '';
        $privacy_agree    = ! empty( $_POST['privacy_agree'] );

        if ( '' === $full_name || '' === $username || '' === $phone || '' === $barangay || '' === $sitio || '' === $password || '' === $confirm_password ) {
            return new WP_Error( 'banoks_missing_register_fields', 'Please complete all required fields.' );
        }

        if ( ! $privacy_agree ) {
            return new WP_Error( 'banoks_privacy_required', 'Please agree to the Data Privacy Policy before creating an account.' );
        }

        if ( $password !== $confirm_password ) {
            return new WP_Error( 'banoks_password_mismatch', 'Password and confirm password must match.' );
        }

        if ( strlen( $password ) < 6 ) {
            return new WP_Error( 'banoks_password_short', 'Password must be at least 6 characters.' );
        }

        $email            = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        $delivery_area_id = $this->get_delivery_area_id_by_name( $barangay );

        if ( null === $delivery_area_id ) {
            return new WP_Error( 'banoks_invalid_barangay', 'Please choose an available barangay.' );
        }

        if ( $this->repository->get_customer_by_identifier( $username ) || ( '' !== $email && $this->repository->get_customer_by_identifier( $email ) ) || $this->repository->get_customer_by_identifier( $phone ) ) {
            return new WP_Error( 'banoks_account_exists', 'An account with that username, email, or phone already exists.' );
        }

        $customer = $this->repository->create_customer(
            array(
                'full_name'        => $full_name,
                'username'         => $username,
                'phone'            => $phone,
                'contact_number'   => $phone,
                'email'            => isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '',
                'address'          => trim( 'Manukan, ' . $barangay . ', ' . $sitio ),
                'municipality'     => 'Manukan',
                'barangay'         => $barangay,
                'sitio'            => $sitio,
                'delivery_area_id' => $delivery_area_id,
                'password'         => $password,
            )
        );

        if ( is_array( $customer ) && isset( $customer['error'] ) ) {
            return new WP_Error( 'banoks_create_customer_failed', $customer['error'] );
        }

        return $customer;
    }

    /**
     * Handle login form submission (non-AJAX).
     *
     * @since    1.7.5
     * @return   void
     */
    private function handle_login() {
        check_admin_referer( 'banoks_customer_login' );

        $customer = $this->login_customer_from_request();
        if ( is_wp_error( $customer ) ) {
            $this->redirect_with_notice( $customer->get_error_message(), true );
        }

        $this->set_customer_cookie( $customer );
        $this->redirect_with_notice(
            'Logged in successfully.',
            false,
            array(),
            $this->get_shortcode_page_url( 'banoks_online_menu', '#banoks-online-menu' )
        );
    }

    /**
     * Process login from request data.
     *
     * @since    1.7.5
     * @return   object|WP_Error
     */
    private function login_customer_from_request() {
        $identifier = isset( $_POST['identifier'] ) ? sanitize_text_field( wp_unslash( $_POST['identifier'] ) ) : '';
        $password   = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';

        if ( '' === $identifier || '' === $password ) {
            return new WP_Error( 'banoks_missing_login_fields', 'Please enter your username and password.' );
        }

        if ( $this->is_login_locked( $identifier ) ) {
            return new WP_Error( 'banoks_login_locked', 'Too many login attempts. Please try again in 15 minutes.' );
        }

        $customer = $this->repository->get_customer_by_identifier( $identifier );

        if ( ! $customer || empty( $customer->password_hash ) || ! wp_check_password( $password, $customer->password_hash ) ) {
            $this->record_failed_login( $identifier );
            return new WP_Error( 'banoks_invalid_login', 'Invalid username or password.' );
        }

        $this->clear_failed_login( $identifier );
        return $customer;
    }

    /**
     * Check if login is locked for an identifier.
     *
     * @param string $identifier Email, phone, or username.
     * @return bool
     */
    private function is_login_locked( $identifier ) {
        $attempts = get_transient( $this->get_login_attempt_key( $identifier ) );
        return is_array( $attempts ) && ! empty( $attempts['locked'] );
    }

    /**
     * Record a failed login attempt.
     *
     * @param string $identifier Email, phone, or username.
     */
    private function record_failed_login( $identifier ) {
        $key      = $this->get_login_attempt_key( $identifier );
        $attempts = get_transient( $key );
        $count    = is_array( $attempts ) && isset( $attempts['count'] ) ? absint( $attempts['count'] ) : 0;
        $count++;

        set_transient(
            $key,
            array(
                'count'  => $count,
                'locked' => $count >= self::LOGIN_MAX_ATTEMPTS,
            ),
            self::LOGIN_LOCK_TTL
        );
    }

    /**
     * Clear failed login attempts for an identifier.
     *
     * @param string $identifier Email, phone, or username.
     */
    private function clear_failed_login( $identifier ) {
        delete_transient( $this->get_login_attempt_key( $identifier ) );
    }

    /**
     * Get transient key for login attempts.
     *
     * @param string $identifier Email, phone, or username.
     * @return string
     */
    private function get_login_attempt_key( $identifier ) {
        return 'banoks_login_' . md5( strtolower( trim( (string) $identifier ) ) . '|' . $this->get_client_ip() );
    }

    /**
     * Get client IP address.
     *
     * @return string
     */
    private function get_client_ip() {
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
        return preg_match( '/^[a-f0-9:\.]+$/i', $ip ) ? $ip : 'unknown';
    }

    /**
     * Get delivery area ID by name.
     *
     * @param string $name Area name.
     * @return int|null
     */
    private function get_delivery_area_id_by_name( $name ) {
        $name = sanitize_text_field( $name );
        foreach ( $this->get_auth_delivery_areas() as $area ) {
            if ( isset( $area->area_name ) && strtolower( $area->area_name ) === strtolower( $name ) ) {
                return isset( $area->id ) ? absint( $area->id ) : 0;
            }
        }
        return null;
    }

    /**
     * Get deliverable areas with fallback.
     *
     * @return array
     */
    private function get_auth_delivery_areas() {
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
     * Helper to redirect with notice.
     *
     * @param string $message     Notice message.
     * @param bool   $is_error    Whether this is an error.
     * @param array  $extra_args  Extra query args.
     * @param string $redirect_url Custom redirect URL.
     */
    private function redirect_with_notice( $message, $is_error, $extra_args = array(), $redirect_url = '' ) {
        $url = remove_query_arg( array( 'banoks_notice', 'banoks_error', 'banoks_order_success' ), $redirect_url ?: ( wp_get_referer() ?: home_url( '/' ) ) );
        $url = add_query_arg(
            array_merge(
                $extra_args,
                array(
                    $is_error ? 'banoks_error' : 'banoks_notice' => rawurlencode( $message ),
                )
            ),
            $url
        );
        wp_safe_redirect( $url );
        exit;
    }

    /**
     * Get shortcode page URL helper.
     *
     * @param string $shortcode Shortcode name.
     * @param string $fragment  URL fragment.
     * @return string
     */
    private function get_shortcode_page_url( $shortcode, $fragment = '' ) {
        $assets = new Banoks_POS_Public_Assets();
        return $assets->get_shortcode_page_url( $shortcode, $fragment );
    }
}