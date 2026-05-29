<?php
/**
 * Delivery area repository for Banoks POS.
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
 * Handles delivery area data access.
 *
 * @since      1.0.0
 * @package    Banoks_POS
 * @subpackage Banoks_POS/includes/repositories
 */
class Banoks_Delivery_Area_Repository {

    /**
     * Get all delivery areas.
     *
     * @since    1.0.7
     * @return   array
     */
    public function get_all() {
        global $wpdb;

        return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}banoks_delivery_areas ORDER BY sort_order ASC, area_name ASC" );
    }

    /**
     * Get a single delivery area by ID.
     *
     * @since    1.7.5
     * @param    int $area_id Area ID.
     * @return   object|null
     */
    public function get( $area_id ) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}banoks_delivery_areas WHERE id = %d",
                absint( $area_id )
            )
        );
    }

    /**
     * Create a delivery area.
     *
     * @since    1.0.7
     * @param    array $data Delivery area data.
     * @return   bool
     */
    public function create( $data ) {
        global $wpdb;

        $area_name = isset( $data['area_name'] ) ? sanitize_text_field( $data['area_name'] ) : '';
        if ( '' === $area_name ) {
            return false;
        }

        return false !== $wpdb->insert(
            $wpdb->prefix . 'banoks_delivery_areas',
            array(
                'area_name'      => $area_name,
                'is_deliverable' => ! empty( $data['is_deliverable'] ) ? 1 : 0,
                'delivery_fee'   => isset( $data['delivery_fee'] ) ? floatval( $data['delivery_fee'] ) : 0,
                'sort_order'     => isset( $data['sort_order'] ) ? intval( $data['sort_order'] ) : 0,
                'created_at'     => current_time( 'mysql' ),
                'updated_at'     => current_time( 'mysql' ),
            ),
            array( '%s', '%d', '%f', '%d', '%s', '%s' )
        );
    }

    /**
     * Get deliverable delivery areas.
     *
     * @since    1.7.5
     * @return   array
     */
    public function get_deliverable() {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}banoks_delivery_areas WHERE is_deliverable = 1 ORDER BY sort_order ASC, area_name ASC"
        );
    }

    /**
     * Find a delivery area by name.
     *
     * @since    1.7.5
     * @param    string $area_name Area name.
     * @return   object|null
     */
    public function get_by_name( $area_name ) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}banoks_delivery_areas WHERE area_name = %s LIMIT 1",
                sanitize_text_field( $area_name )
            )
        );
    }
}