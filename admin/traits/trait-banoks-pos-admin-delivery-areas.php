<?php
/**
 * Delivery area admin methods for Banoks POS.
 *
 * @package Banoks_POS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Banoks_POS_Admin_Delivery_Areas {
    public function display_delivery_areas_page() {
        global $wpdb;

        $this->display_admin_header();
        $this->maybe_update_products_schema();

        $repository = new Banoks_POS_Repository();
        $message    = '';
        $error      = '';
        $table_name = $wpdb->prefix . 'banoks_delivery_areas';

        if ( isset( $_POST['banoks_pos_save_delivery_area'] ) ) {
            check_admin_referer( 'banoks_pos_delivery_area_action' );

            $area_id = isset( $_POST['area_id'] ) ? absint( $_POST['area_id'] ) : 0;
            $data = array(
                'area_name'      => isset( $_POST['area_name'] ) ? sanitize_text_field( wp_unslash( $_POST['area_name'] ) ) : '',
                'delivery_fee'   => isset( $_POST['delivery_fee'] ) ? floatval( wp_unslash( $_POST['delivery_fee'] ) ) : 0,
                'sort_order'     => isset( $_POST['sort_order'] ) ? intval( wp_unslash( $_POST['sort_order'] ) ) : 0,
                'is_deliverable' => isset( $_POST['is_deliverable'] ) ? 1 : 0,
                'updated_at'     => current_time( 'mysql' ),
            );

            if ( '' === $data['area_name'] ) {
                $error = 'Please enter a delivery area name.';
            } elseif ( $area_id ) {
                $updated = $wpdb->update(
                    $table_name,
                    $data,
                    array( 'id' => $area_id ),
                    array( '%s', '%f', '%d', '%d', '%s' ),
                    array( '%d' )
                );
                $message = false === $updated ? '' : 'Delivery area updated successfully.';
                $error   = false === $updated ? 'Could not update delivery area.' : $error;
            } else {
                $created = $repository->create_delivery_area( $data );
                $message = $created ? 'Delivery area added successfully.' : '';
                $error   = $created ? $error : 'Could not add delivery area.';
            }
        }

        $edit_area = null;
        if ( isset( $_GET['action'], $_GET['area_id'] ) && 'edit' === sanitize_key( wp_unslash( $_GET['action'] ) ) ) {
            $edit_area = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$table_name} WHERE id = %d",
                    absint( $_GET['area_id'] )
                )
            );
        }

        $delivery_areas = $repository->get_delivery_areas();

        include_once dirname( __DIR__ ) . '/partials/banoks-pos-delivery-areas-display.php';
    }
}
