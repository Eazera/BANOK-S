<?php
/**
 * Customer repository for Banoks POS.
 *
 * @link       https://banoks.com
 * @since      1.0.0
 * @package    Banoks_POS
 * @subpackage Banoks_POS/includes/repositories
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles customer and address data access.
 *
 * @since      1.0.0
 * @package    Banoks_POS
 * @subpackage Banoks_POS/includes/repositories
 */
class Banoks_Customer_Repository {

    /**
     * Generate the next public customer ID.
     *
     * @since    1.0.7
     * @return   string
     */
    public function generate_public_id() {
        global $wpdb;

        $last_id = $wpdb->get_var( "SELECT MAX(id) FROM {$wpdb->prefix}banoks_customers" );

        return sprintf( 'USER-%06d', $last_id ? intval( $last_id ) + 1 : 1 );
    }

    /**
     * Get a customer by internal ID.
     *
     * @since    1.0.7
     * @param    int $customer_id Internal customer ID.
     * @return   object|null
     */
    public function get( $customer_id ) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}banoks_customers WHERE id = %d",
                absint( $customer_id )
            )
        );
    }

    /**
     * Get a customer by email, phone, or username.
     *
     * @since    1.0.8
     * @param    string $identifier Email address, phone number, or username.
     * @return   object|null
     */
    public function get_by_identifier( $identifier ) {
        global $wpdb;

        $identifier = sanitize_text_field( $identifier );

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}banoks_customers WHERE email = %s OR phone = %s OR username = %s LIMIT 1",
                $identifier,
                $identifier,
                $identifier
            )
        );
    }

    /**
     * Create a customer record.
     *
     * @since    1.0.7
     * @param    array $data Customer data.
     * @return   array|object Array with 'error' key on failure, or customer row object on success.
     */
    public function create( $data ) {
        global $wpdb;

        $full_name    = isset( $data['full_name'] ) ? sanitize_text_field( $data['full_name'] ) : '';
        $username     = isset( $data['username'] ) ? sanitize_user( $data['username'], true ) : '';
        $phone        = isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : '';
        $contact      = isset( $data['contact_number'] ) ? sanitize_text_field( $data['contact_number'] ) : $phone;
        $email        = isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '';
        $address      = isset( $data['address'] ) ? sanitize_textarea_field( $data['address'] ) : '';
        $municipality = 'Manukan';
        $barangay     = isset( $data['barangay'] ) ? sanitize_text_field( $data['barangay'] ) : '';
        $sitio        = isset( $data['sitio'] ) ? sanitize_text_field( $data['sitio'] ) : '';
        $password     = isset( $data['password'] ) ? (string) $data['password'] : '';

        if ( '' === $full_name || '' === $username || '' === $phone ) {
            return array( 'error' => 'Full name, username, and contact number are required.' );
        }

        if ( $this->get_by_identifier( $username ) || $this->get_by_identifier( $phone ) || ( '' !== $email && $this->get_by_identifier( $email ) ) ) {
            return array( 'error' => 'An account with that username, email, or phone already exists.' );
        }

        $inserted = $wpdb->insert(
            $wpdb->prefix . 'banoks_customers',
            array(
                'customer_id'      => $this->generate_public_id(),
                'full_name'        => $full_name,
                'username'         => $username,
                'phone'            => $phone,
                'contact_number'   => $contact,
                'email'            => $email,
                'password_hash'    => '' !== $password ? wp_hash_password( $password ) : '',
                'address'          => $address,
                'municipality'     => $municipality,
                'barangay'         => $barangay,
                'sitio'            => $sitio,
                'delivery_area_id' => isset( $data['delivery_area_id'] ) ? absint( $data['delivery_area_id'] ) : 0,
                'created_at'       => current_time( 'mysql' ),
                'updated_at'       => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
        );

        if ( false === $inserted ) {
            return array( 'error' => 'Could not create customer.' );
        }

        return $this->get( $wpdb->insert_id );
    }

    /**
     * Get saved addresses for a customer.
     *
     * @since    1.0.7
     * @param    int $customer_id Customer internal ID.
     * @return   array
     */
    public function get_addresses( $customer_id ) {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}banoks_customer_addresses WHERE customer_id = %d ORDER BY is_default DESC, updated_at DESC, id DESC",
                absint( $customer_id )
            )
        );
    }

    /**
     * Ensure an existing customer's original address is available as a saved address.
     *
     * @since    1.0.7
     * @param    object $customer Customer row.
     * @return   void
     */
    public function ensure_default_address( $customer ) {
        global $wpdb;

        if ( empty( $customer ) || empty( $customer->id ) || empty( $customer->address ) ) {
            return;
        }

        if ( ! empty( $this->get_addresses( $customer->id ) ) ) {
            return;
        }

        $barangay         = ! empty( $customer->barangay ) ? $customer->barangay : '';
        $sitio            = ! empty( $customer->sitio ) ? $customer->sitio : '';
        $delivery_area_id = ! empty( $customer->delivery_area_id ) ? absint( $customer->delivery_area_id ) : 0;

        if ( '' === $barangay || '' === $sitio ) {
            $parts = array_map( 'trim', explode( ',', $customer->address ) );
            if ( '' === $barangay && ! empty( $parts[1] ) ) {
                $barangay = $parts[1];
            }
            if ( '' === $sitio && ! empty( $parts[2] ) ) {
                $sitio = $parts[2];
            }
        }

        if ( ! $delivery_area_id && '' !== $barangay ) {
            $delivery_area_id = absint(
                $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}banoks_delivery_areas WHERE area_name = %s LIMIT 1",
                        $barangay
                    )
                )
            );
        }

        $this->create_address(
            $customer->id,
            array(
                'municipality'     => ! empty( $customer->municipality ) ? $customer->municipality : 'Manukan',
                'barangay'         => $barangay,
                'sitio'            => $sitio,
                'address'          => $customer->address,
                'delivery_area_id' => $delivery_area_id,
                'is_default'       => 1,
            )
        );
    }

    /**
     * Create a saved customer address.
     *
     * @since    1.0.7
     * @param    int   $customer_id Customer internal ID.
     * @param    array $data        Address data.
     * @return   object|array Object on success, array with 'error' on failure.
     */
    public function create_address( $customer_id, $data ) {
        global $wpdb;

        $customer_id      = absint( $customer_id );
        $municipality     = 'Manukan';
        $barangay         = isset( $data['barangay'] ) ? sanitize_text_field( $data['barangay'] ) : '';
        $sitio            = isset( $data['sitio'] ) ? sanitize_text_field( $data['sitio'] ) : '';
        $delivery_area_id = isset( $data['delivery_area_id'] ) ? absint( $data['delivery_area_id'] ) : 0;
        $address          = isset( $data['address'] ) ? sanitize_textarea_field( $data['address'] ) : trim( $municipality . ', ' . $barangay . ', ' . $sitio );
        $existing_count   = $customer_id ? absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}banoks_customer_addresses WHERE customer_id = %d", $customer_id ) ) ) : 0;
        $is_default       = ! empty( $data['is_default'] ) || 0 === $existing_count ? 1 : 0;

        if ( ! $customer_id || '' === $barangay || '' === $sitio || ! $delivery_area_id ) {
            return array( 'error' => 'Please complete the delivery address.' );
        }

        if ( $is_default ) {
            $wpdb->update(
                $wpdb->prefix . 'banoks_customer_addresses',
                array(
                    'is_default' => 0,
                    'updated_at' => current_time( 'mysql' ),
                ),
                array( 'customer_id' => $customer_id ),
                array( '%d', '%s' ),
                array( '%d' )
            );
        }

        $inserted = $wpdb->insert(
            $wpdb->prefix . 'banoks_customer_addresses',
            array(
                'customer_id'      => $customer_id,
                'municipality'     => $municipality,
                'barangay'         => $barangay,
                'sitio'            => $sitio,
                'address'          => $address,
                'delivery_area_id' => $delivery_area_id,
                'is_default'       => $is_default,
                'created_at'       => current_time( 'mysql' ),
                'updated_at'       => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
        );

        if ( false === $inserted ) {
            return array( 'error' => 'Could not save delivery address.' );
        }

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}banoks_customer_addresses WHERE id = %d",
                absint( $wpdb->insert_id )
            )
        );
    }

    /**
     * Set a saved customer address as default.
     *
     * @since    1.0.7
     * @param    int $customer_id Customer internal ID.
     * @param    int $address_id  Address ID.
     * @return   bool
     */
    public function set_default_address( $customer_id, $address_id ) {
        global $wpdb;

        $customer_id = absint( $customer_id );
        $address_id  = absint( $address_id );

        if ( ! $customer_id || ! $address_id ) {
            return false;
        }

        $address = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}banoks_customer_addresses WHERE id = %d AND customer_id = %d",
                $address_id,
                $customer_id
            )
        );

        if ( ! $address ) {
            return false;
        }

        $wpdb->update(
            $wpdb->prefix . 'banoks_customer_addresses',
            array(
                'is_default' => 0,
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'customer_id' => $customer_id ),
            array( '%d', '%s' ),
            array( '%d' )
        );

        return false !== $wpdb->update(
            $wpdb->prefix . 'banoks_customer_addresses',
            array(
                'is_default' => 1,
                'updated_at' => current_time( 'mysql' ),
            ),
            array(
                'id'          => $address_id,
                'customer_id' => $customer_id,
            ),
            array( '%d', '%s' ),
            array( '%d', '%d' )
        );
    }

    /**
     * Store or update a customer payment profile for a gateway.
     *
     * @since    1.7.5
     * @param    int    $customer_id Customer ID.
     * @param    string $gateway Gateway name (e.g. 'paymongo').
     * @param    string $payment_method Payment method (e.g. 'gcash').
     * @param    string $label Profile label.
     * @param    string $gateway_customer_id Gateway customer ID.
     * @param    string $gateway_payment_method_id Gateway payment method ID.
     * @return   bool
     */
    public function remember_payment_profile( $customer_id, $gateway, $payment_method, $label, $gateway_customer_id = '', $gateway_payment_method_id = '' ) {
        global $wpdb;

        $customer_id    = absint( $customer_id );
        $gateway        = sanitize_key( $gateway );
        $payment_method = sanitize_key( $payment_method );
        $existing_id    = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}banoks_customer_payment_profiles WHERE customer_id = %d AND gateway = %s AND payment_method = %s LIMIT 1",
                $customer_id,
                $gateway,
                $payment_method
            )
        );

        $data = array(
            'customer_id'               => $customer_id,
            'gateway'                   => $gateway,
            'payment_method'            => $payment_method,
            'profile_label'             => sanitize_text_field( $label ),
            'gateway_customer_id'       => sanitize_text_field( $gateway_customer_id ),
            'gateway_payment_method_id' => sanitize_text_field( $gateway_payment_method_id ),
            'last_used_at'              => current_time( 'mysql' ),
            'updated_at'                => current_time( 'mysql' ),
        );

        if ( $existing_id ) {
            unset( $data['customer_id'], $data['gateway'], $data['payment_method'] );
            return false !== $wpdb->update(
                $wpdb->prefix . 'banoks_customer_payment_profiles',
                $data,
                array( 'id' => absint( $existing_id ) )
            );
        }

        $data['created_at'] = current_time( 'mysql' );
        return false !== $wpdb->insert( $wpdb->prefix . 'banoks_customer_payment_profiles', $data );
    }

    /**
     * Get a customer's saved payment profile.
     *
     * @since    1.7.5
     * @param    int    $customer_id Customer ID.
     * @param    string $gateway Gateway name.
     * @param    string $payment_method Payment method.
     * @return   object|null
     */
    public function get_payment_profile( $customer_id, $gateway, $payment_method ) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}banoks_customer_payment_profiles WHERE customer_id = %d AND gateway = %s AND payment_method = %s LIMIT 1",
                absint( $customer_id ),
                sanitize_key( $gateway ),
                sanitize_key( $payment_method )
            )
        );
    }
}