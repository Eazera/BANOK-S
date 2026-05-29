<?php
/**
 * Delivery areas admin page.
 *
 * @package Banoks_POS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

?>

<div class="wrap banoks-pos-admin banoks-pos-page banoks-delivery-areas-page">
    <div class="products-header">
        <div class="header-info">
            <h1>Delivery Areas</h1>
            <p>Manage allowed delivery locations and fees for online ordering.</p>
        </div>
    </div>

    <?php if ( ! empty( $message ) ) : ?>
        <div class="updated notice is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
    <?php endif; ?>

    <?php if ( ! empty( $error ) ) : ?>
        <div class="error notice is-dismissible"><p><?php echo esc_html( $error ); ?></p></div>
    <?php endif; ?>

    <div class="banoks-online-grid banoks-delivery-areas-grid">
        <div class="banoks-online-panel">
            <h2>Add Delivery Area</h2>

            <form method="post" class="banoks-inline-form banoks-delivery-area-form">
                <?php wp_nonce_field( 'banoks_pos_delivery_area_action' ); ?>
                <input type="hidden" name="banoks_pos_save_delivery_area" value="1">

                <p>
                    <label for="area_name">Area Name</label>
                    <input type="text" id="area_name" name="area_name" class="regular-text" value="" required>
                </p>

                <p>
                    <label for="delivery_fee">Delivery Fee</label>
                    <input type="number" id="delivery_fee" name="delivery_fee" min="0" step="0.01" value="0" required>
                </p>

                <p>
                    <label for="sort_order">Sort Order</label>
                    <input type="number" id="sort_order" name="sort_order" step="1" value="0">
                </p>

                <p>
                    <label class="banoks-toggle-control banoks-toggle-compact">
                        <input type="checkbox" name="is_deliverable" value="1" checked>
                        <span class="banoks-toggle-switch"></span>
                        <span>
                            <strong>Deliverable</strong>
                            <small>Customers can choose this area during delivery checkout.</small>
                        </span>
                    </label>
                </p>

                <p class="submit">
                    <button type="submit" class="button button-primary">Add Delivery Area</button>
                </p>
            </form>
        </div>

        <div class="banoks-online-panel banoks-delivery-areas-list">
            <h2>Saved Delivery Areas</h2>

            <?php if ( ! empty( $delivery_areas ) ) : ?>
                <div class="table-wrap">
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th>Area</th>
                                <th>Fee</th>
                                <th>Deliverable</th>
                                <th>Sort</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $delivery_areas as $area ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $area->area_name ); ?></td>
                                    <td>&#8369;<?php echo esc_html( number_format( floatval( $area->delivery_fee ), 2 ) ); ?></td>
                                    <td><?php echo intval( $area->is_deliverable ) ? 'Yes' : 'No'; ?></td>
                                    <td><?php echo esc_html( intval( $area->sort_order ) ); ?></td>
                                    <td>
                                        <div class="action-group">
                                            <button type="button" class="btn-edit banoks-open-delivery-edit" title="Edit Delivery Area" aria-label="Edit Delivery Area"
                                                data-id="<?php echo esc_attr( $area->id ); ?>"
                                                data-name="<?php echo esc_attr( $area->area_name ); ?>"
                                                data-fee="<?php echo esc_attr( $area->delivery_fee ); ?>"
                                                data-sort="<?php echo esc_attr( $area->sort_order ); ?>"
                                                data-deliverable="<?php echo esc_attr( intval( $area->is_deliverable ) ); ?>">
                                                <span class="dashicons dashicons-edit"></span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <div class="empty-state">No delivery areas configured yet.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="banoks-admin-edit-modal" id="banoks-delivery-edit-modal" aria-hidden="true">
        <div class="banoks-admin-edit-dialog" role="dialog" aria-modal="true" aria-labelledby="banoks-delivery-edit-title">
            <div class="banoks-admin-edit-header">
                <h2 id="banoks-delivery-edit-title">Edit Delivery Area</h2>
                <button type="button" class="banoks-admin-edit-close" aria-label="Close">&times;</button>
            </div>
            <form method="post" class="banoks-inline-form banoks-delivery-area-form">
                <?php wp_nonce_field( 'banoks_pos_delivery_area_action' ); ?>
                <input type="hidden" name="banoks_pos_save_delivery_area" value="1">
                <input type="hidden" name="area_id" id="delivery-modal-area-id" value="">

                <p>
                    <label for="delivery-modal-area-name">Area Name</label>
                    <input type="text" id="delivery-modal-area-name" name="area_name" class="regular-text" required>
                </p>
                <p>
                    <label for="delivery-modal-fee">Delivery Fee</label>
                    <input type="number" id="delivery-modal-fee" name="delivery_fee" min="0" step="0.01" required>
                </p>
                <p>
                    <label for="delivery-modal-sort">Sort Order</label>
                    <input type="number" id="delivery-modal-sort" name="sort_order" step="1">
                </p>
                <p>
                    <label class="banoks-toggle-control banoks-toggle-compact">
                        <input type="checkbox" id="delivery-modal-deliverable" name="is_deliverable" value="1">
                        <span class="banoks-toggle-switch"></span>
                        <span>
                            <strong>Deliverable</strong>
                            <small>Customers can choose this area during delivery checkout.</small>
                        </span>
                    </label>
                </p>
                <p class="submit">
                    <button type="submit" class="button button-primary">Update Delivery Area</button>
                    <button type="button" class="button banoks-admin-edit-cancel">Cancel</button>
                </p>
            </form>
        </div>
    </div>
</div>
