<?php
/**
 * Product management admin methods for Banoks POS.
 *
 * @package Banoks_POS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Banoks_POS_Admin_Products {
    public function display_products_page() {
        global $wpdb;
        $this->display_admin_header();
        $this->maybe_update_products_schema();
        $table_name = $wpdb->prefix . 'banoks_items';
        $repository = new Banoks_POS_Repository();
        $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list';
        $message = '';
        $active_branches = $this->get_active_branches();
        $selected_branch_key = isset( $_GET['branch_key'] ) ? sanitize_key( wp_unslash( $_GET['branch_key'] ) ) : '';
        $selected_branch_name = '';

        foreach ( $active_branches as $branch ) {
            $branch_key = sanitize_key( $branch->branch_key );
            if ( '' === $selected_branch_key ) {
                $selected_branch_key = $branch_key;
            }

            if ( $selected_branch_key === $branch_key ) {
                $selected_branch_name = $branch->branch_name;
            }
        }

        if ( '' === $selected_branch_name ) {
            $selected_branch_key  = Banoks_POS_Repository::STOCK_LOCATION_MANUKAN;
            $selected_branch_name = 'Manukan Branch';
        }

        // Handle Deletion
        if ( 'delete' === $action && isset( $_GET['id'] ) ) {
            $product_id = absint( $_GET['id'] );
            check_admin_referer( 'delete_product_' . $product_id );
            $wpdb->update(
                $table_name,
                array(
                    'is_active'    => 0,
                    'is_available' => 0,
                ),
                array( 'product_id' => $product_id ),
                array( '%d', '%d' ),
                array( '%d' )
            );
            $message = 'Product deactivated successfully.';
            $action = 'list';
        }

        // Handle Form Submission (Add/Edit)
        if ( isset( $_POST['banoks_pos_save_product'] ) ) {
            check_admin_referer( 'banoks_pos_product_action' );

            $data = array(
                'product_name'     => isset( $_POST['product_name'] ) ? sanitize_text_field( wp_unslash( $_POST['product_name'] ) ) : '',
                'product_description' => isset( $_POST['product_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['product_description'] ) ) : '',
                'category'         => isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : 'General',
                'current_price'    => isset( $_POST['current_price'] ) ? floatval( wp_unslash( $_POST['current_price'] ) ) : 0,
                'product_image_id' => isset( $_POST['product_image_id'] ) ? absint( $_POST['product_image_id'] ) : 0,
                'is_available'     => ! empty( $_POST['is_available'] ) ? 1 : 0,
                'is_active'        => ! empty( $_POST['is_active'] ) ? 1 : 0,
            );
            if ( '' === trim( $data['category'] ) ) {
                $data['category'] = 'General';
            }

            if ( ! empty( $_POST['product_id'] ) ) {
                $product_id = absint( $_POST['product_id'] );
                $result = $wpdb->update(
                    $table_name,
                    $data,
                    array( 'product_id' => $product_id ),
                    array( '%s', '%s', '%s', '%f', '%d', '%d', '%d' ),
                    array( '%d' )
                );
                if ( false === $result ) {
                    $message = 'Error: Could not update product. ' . $wpdb->last_error;
                } else {
                    $this->save_product_recipe_rows( $product_id );
                    $this->save_product_addon_rows( $product_id );
                    $message = 'Product updated successfully.';
                    $action = 'list';
                }
            } else {
                $next_sort_order = absint( $wpdb->get_var( "SELECT COALESCE(MAX(sort_order), 0) + 10 FROM $table_name" ) );
                $data['sort_order'] = $next_sort_order;
                $result = $wpdb->insert(
                    $table_name,
                    $data,
                    array( '%s', '%s', '%s', '%f', '%d', '%d', '%d', '%d' )
                );
                if ( false === $result ) {
                    $message = 'Error: Could not add product. ' . $wpdb->last_error;
                } else {
                    $product_id = $wpdb->insert_id;
                    $this->save_product_recipe_rows( $product_id );
                    $this->save_product_addon_rows( $product_id );
                    $message = 'Product added successfully.';
                    $action = 'list';
                }
            }
        }

        // Routing
        if ( 'add' === $action || 'edit' === $action ) {
            $product = null;
            if ( 'edit' === $action && isset( $_GET['id'] ) ) {
                $product = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE product_id = %d", absint( $_GET['id'] ) ) );
            }
            $existing_categories = $wpdb->get_col( "SELECT DISTINCT category FROM $table_name WHERE category != '' ORDER BY category ASC" );
            $inventory_items = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}banoks_inventory_items WHERE is_active = 1 ORDER BY item_name ASC" );
            $addon_products = $wpdb->get_results( "SELECT product_id, product_name, current_price FROM $table_name WHERE COALESCE(is_active, 1) = 1 ORDER BY product_name ASC" );
            $selected_addon_ids = ( 'edit' === $action && ! empty( $product ) ) ? array_map( 'absint', $wpdb->get_col( $wpdb->prepare( "SELECT addon_product_id FROM {$wpdb->prefix}banoks_product_addons WHERE product_id = %d ORDER BY sort_order ASC, id ASC", absint( $product->product_id ) ) ) ) : array();
            $product_recipes = ( 'edit' === $action && ! empty( $product ) ) ? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}banoks_product_recipes WHERE product_id = %d ORDER BY id ASC", absint( $product->product_id ) ) ) : array();
            include_once dirname( __DIR__ ) . '/partials/banoks-pos-product-form.php';
        } else {
            $products = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY sort_order ASC, product_name ASC" );
            $product_ids = wp_list_pluck( $products, 'product_id' );
            $recipe_statuses = $repository->get_product_recipe_statuses( $product_ids, $selected_branch_key );
        include_once dirname( __DIR__ ) . '/partials/banoks-pos-products-list.php';
        }
    }

    public function ajax_save_product_order() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'You do not have permission to reorder products.' ), 403 );
        }

        check_ajax_referer( 'banoks_pos_order_nonce', 'nonce' );

        global $wpdb;

        if ( class_exists( 'Banoks_DB' ) ) {
            Banoks_DB::create_tables();
        }

        $table_name  = $wpdb->prefix . 'banoks_items';
        $product_ids = isset( $_POST['product_ids'] ) && is_array( $_POST['product_ids'] ) ? wp_unslash( $_POST['product_ids'] ) : array();
        $product_ids = array_values( array_filter( array_map( 'absint', $product_ids ) ) );

        if ( empty( $product_ids ) ) {
            wp_send_json_error( array( 'message' => 'No products were received for sorting.' ), 400 );
        }

        $sort_order = 10;
        foreach ( $product_ids as $product_id ) {
            $result = $wpdb->update(
                $table_name,
                array( 'sort_order' => $sort_order ),
                array( 'product_id' => $product_id ),
                array( '%d' ),
                array( '%d' )
            );

            if ( false === $result ) {
                wp_send_json_error(
                    array(
                        'message' => 'Could not save product order. Please refresh the page and try again.',
                    ),
                    500
                );
            }

            $sort_order += 10;
        }

        wp_send_json_success( array( 'message' => 'Product order updated.' ) );
    }

    private function save_product_recipe_rows( $product_id ) {
        global $wpdb;

        $product_id = absint( $product_id );
        if ( ! $product_id ) {
            return;
        }

        $inventory_ids = isset( $_POST['recipe_inventory_item_id'] ) && is_array( $_POST['recipe_inventory_item_id'] ) ? wp_unslash( $_POST['recipe_inventory_item_id'] ) : array();
        $quantities    = isset( $_POST['recipe_quantity_used'] ) && is_array( $_POST['recipe_quantity_used'] ) ? wp_unslash( $_POST['recipe_quantity_used'] ) : array();
        $conditions    = isset( $_POST['recipe_applies_to'] ) && is_array( $_POST['recipe_applies_to'] ) ? wp_unslash( $_POST['recipe_applies_to'] ) : array();
        $recipe_rows   = array();
        $allowed_conditions = array( 'all', 'walk_in', 'online', 'delivery', 'pickup' );

        foreach ( $inventory_ids as $index => $inventory_id ) {
            $inventory_id  = absint( $inventory_id );
            $quantity_used = isset( $quantities[ $index ] ) ? max( 0, floatval( $quantities[ $index ] ) ) : 0;
            $applies_to    = isset( $conditions[ $index ] ) ? sanitize_key( $conditions[ $index ] ) : 'all';

            if ( ! $inventory_id || $quantity_used <= 0 ) {
                continue;
            }

            if ( ! in_array( $applies_to, $allowed_conditions, true ) ) {
                $applies_to = 'all';
            }

            $recipe_key = $inventory_id . '|' . $applies_to;
            if ( ! isset( $recipe_rows[ $recipe_key ] ) ) {
                $recipe_rows[ $recipe_key ] = array(
                    'inventory_id'   => $inventory_id,
                    'quantity_used'  => 0,
                    'applies_to'     => $applies_to,
                );
            }

            $recipe_rows[ $recipe_key ]['quantity_used'] += $quantity_used;
        }

        $wpdb->delete(
            $wpdb->prefix . 'banoks_product_recipes',
            array( 'product_id' => $product_id ),
            array( '%d' )
        );

        foreach ( $recipe_rows as $recipe_row ) {
            $wpdb->insert(
                $wpdb->prefix . 'banoks_product_recipes',
                array(
                    'product_id'        => $product_id,
                    'inventory_item_id' => absint( $recipe_row['inventory_id'] ),
                    'quantity_used'     => floatval( $recipe_row['quantity_used'] ),
                    'applies_to'        => $recipe_row['applies_to'],
                    'created_at'        => current_time( 'mysql' ),
                    'updated_at'        => current_time( 'mysql' ),
                ),
                array( '%d', '%d', '%f', '%s', '%s', '%s' )
            );
        }
    }

    private function save_product_addon_rows( $product_id ) {
        global $wpdb;

        $product_id = absint( $product_id );
        if ( ! $product_id ) {
            return;
        }

        $addon_ids = isset( $_POST['addon_product_ids'] ) && is_array( $_POST['addon_product_ids'] ) ? wp_unslash( $_POST['addon_product_ids'] ) : array();
        $addon_ids = array_values( array_unique( array_filter( array_map( 'absint', $addon_ids ) ) ) );

        $wpdb->delete(
            $wpdb->prefix . 'banoks_product_addons',
            array( 'product_id' => $product_id ),
            array( '%d' )
        );

        $sort_order = 0;
        foreach ( $addon_ids as $addon_id ) {
            if ( $addon_id === $product_id ) {
                continue;
            }

            $wpdb->insert(
                $wpdb->prefix . 'banoks_product_addons',
                array(
                    'product_id'       => $product_id,
                    'addon_product_id' => $addon_id,
                    'sort_order'       => $sort_order,
                    'created_at'       => current_time( 'mysql' ),
                ),
                array( '%d', '%d', '%d', '%s' )
            );
            $sort_order++;
        }
    }
}
