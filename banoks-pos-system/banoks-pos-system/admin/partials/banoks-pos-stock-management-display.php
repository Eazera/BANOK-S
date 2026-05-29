<?php
/**
 * Stock management admin page.
 *
 * @package Banoks_POS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$format_stock = function ( $value ) {
    return rtrim( rtrim( number_format( floatval( $value ), 3, '.', '' ), '0' ), '.' );
};
$format_money = function ( $value ) {
    return '&#8369;' . number_format( floatval( $value ), 2 );
};
$stock_movement_actions = array(
    array( 'action' => 'stock_in', 'type' => 'stock_in', 'label' => 'Stock In', 'location' => Banoks_POS_Repository::STOCK_LOCATION_PRODUCTION ),
    array( 'action' => 'add_branch_stock', 'type' => 'stock_in', 'label' => 'Add Branch Stock', 'location' => Banoks_POS_Repository::STOCK_LOCATION_MANUKAN ),
);
?>

<div class="wrap banoks-pos-admin banoks-pos-page banoks-stock-management-page">
    <div class="products-header banoks-stock-main-header">
        <div class="header-info">
            <h1>Stock Management</h1>
            <p>Manage inventory counts for Production storage and branches.</p>
        </div>
        <div class="banoks-stock-header-actions">
            <div class="banoks-stock-movement-menu">
                <button type="button" class="button button-primary banoks-stock-movement-toggle" aria-expanded="false">
                    Stock Movement
                </button>
                <div class="banoks-stock-movement-dropdown" aria-hidden="true">
                    <?php foreach ( $stock_movement_actions as $movement_action ) : ?>
                        <button
                            type="button"
                            class="banoks-stock-movement-option banoks-open-stock-movement"
                            data-movement-action="<?php echo esc_attr( $movement_action['action'] ); ?>"
                            data-movement-type="<?php echo esc_attr( $movement_action['type'] ); ?>"
                            data-movement-label="<?php echo esc_attr( $movement_action['label'] ); ?>"
                            data-location="<?php echo esc_attr( $movement_action['location'] ); ?>"
                        >
                            <?php echo esc_html( $movement_action['label'] ); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ( ! empty( $message ) ) : ?>
        <div class="notice notice-<?php echo 'error' === $message_type ? 'error' : 'success'; ?> is-dismissible">
            <p><?php echo esc_html( $message ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( ! empty( $inventory_alerts ) ) : ?>
        <section class="banoks-stock-alert-panel">
            <div>
                <h2>Stock Alerts</h2>
                <p>Low-stock and out-of-stock active Manukan Branch inventory items.</p>
            </div>
            <div class="banoks-stock-alert-list">
                <?php foreach ( $inventory_alerts as $alert ) : ?>
                    <span class="banoks-stock-alert-chip <?php echo 'out' === $alert->alert_type ? 'is-out' : 'is-low'; ?>">
                        <strong><?php echo esc_html( $alert->item_name ); ?></strong>
                        <?php if ( 'out' === $alert->alert_type ) : ?>
                            Out of stock
                        <?php else : ?>
                            <?php echo esc_html( $alert->formatted_stock ); ?> left
                        <?php endif; ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="banoks-stock-panel banoks-stock-movement-overall-panel">
        <div class="banoks-stock-section-header">
            <div>
                <h2>Overall Stock Movement</h2>
                <p>Movement log for Production Inventory and Branch Inventory.</p>
            </div>
            <button type="button" class="button banoks-finance-filter-trigger banoks-open-owner-request" data-target="#banoks-stock-overall-filter-modal">
                Filter
            </button>
        </div>
        <div class="banoks-stock-table-scroll">
            <table class="wp-list-table widefat fixed striped banoks-stock-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Inventory</th>
                        <th>Item</th>
                        <th>Type</th>
                        <th>Qty</th>
                        <th>Cost</th>
                        <th>Reference</th>
                        <th>New Stock</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $overall_stock_movements ) ) : ?>
                        <?php foreach ( $overall_stock_movements as $movement ) : ?>
                            <tr
                                class="banoks-stock-overall-row"
                                data-date="<?php echo esc_attr( date( 'Y-m-d', strtotime( $movement->created_at ) ) ); ?>"
                                data-location="<?php echo esc_attr( $movement->location_key ); ?>"
                            >
                                <td><?php echo esc_html( mysql2date( 'M j, Y g:i A', $movement->created_at ) ); ?></td>
                                <td><?php echo esc_html( isset( $stock_locations[ $movement->location_key ] ) ? $stock_locations[ $movement->location_key ] : $movement->location_key ); ?></td>
                                <td><strong><?php echo esc_html( ! empty( $movement->item_name ) ? $movement->item_name : 'Deleted item' ); ?></strong></td>
                                <td><?php echo esc_html( isset( $movement_options[ $movement->movement_type ] ) ? $movement_options[ $movement->movement_type ] : ucwords( str_replace( '_', ' ', $movement->movement_type ) ) ); ?></td>
                                <td><?php echo esc_html( $format_stock( $movement->change_amount ) . ' ' . $movement->unit ); ?></td>
                                <td><?php echo wp_kses_post( $format_money( $movement->total_cost ) ); ?></td>
                                <td><?php echo ! empty( $movement->source_id ) ? esc_html( $movement->source_id ) : '&mdash;'; ?></td>
                                <td><?php echo esc_html( $format_stock( $movement->new_stock ) . ' ' . $movement->unit ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="8">No stock movements found.</td>
                        </tr>
                    <?php endif; ?>
                    <tr class="banoks-stock-overall-no-results" style="display:none;">
                        <td colspan="8">No stock movements match the selected filters.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <div class="banoks-admin-edit-modal banoks-stock-overall-filter-modal" id="banoks-stock-overall-filter-modal" aria-hidden="true">
        <div class="banoks-admin-edit-dialog" role="dialog" aria-modal="true" aria-labelledby="banoks-stock-overall-filter-title">
            <div class="banoks-admin-edit-header">
                <div>
                    <h2 id="banoks-stock-overall-filter-title">Filter Stock Movement</h2>
                    <p>Choose a date range and inventory location.</p>
                </div>
                <button type="button" class="banoks-admin-edit-close" aria-label="Close stock movement filters">&times;</button>
            </div>
            <form class="banoks-stock-movement-overall-filter">
                <div class="banoks-stock-overall-filter-grid">
                    <label for="banoks-stock-overall-date-from">
                        From
                        <input type="date" id="banoks-stock-overall-date-from" name="movement_date_from" value="<?php echo esc_attr( $movement_date_from ); ?>">
                    </label>
                    <label for="banoks-stock-overall-date-to">
                        To
                        <input type="date" id="banoks-stock-overall-date-to" name="movement_date_to" value="<?php echo esc_attr( $movement_date_to ); ?>">
                    </label>
                    <label for="banoks-stock-overall-location">
                        Inventory
                        <select id="banoks-stock-overall-location" name="movement_location">
                            <option value="">All Inventory</option>
                            <?php foreach ( $stock_locations as $filter_location_key => $filter_location_label ) : ?>
                                <option value="<?php echo esc_attr( $filter_location_key ); ?>" <?php selected( $movement_location, $filter_location_key ); ?>><?php echo esc_html( $filter_location_label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <div class="modal-actions">
                    <button type="button" class="button banoks-stock-overall-filter-clear">Clear</button>
                    <button type="button" class="button banoks-admin-edit-cancel">Cancel</button>
                    <button type="submit" class="button button-primary">Apply Filter</button>
                </div>
            </form>
        </div>
    </div>

    <?php
    $render_inventory_table = function ( $location_key, $empty_message, $show_actions = false ) use ( $inventory_items, $inventory_balance_map, $format_stock, $format_money ) {
        ?>
        <div class="banoks-stock-table-scroll">
            <table class="wp-list-table widefat fixed striped banoks-stock-table banoks-stock-clickable-table">
                <thead>
                    <tr>
                        <th>Items</th>
                        <th>Category</th>
                        <th>Remaining Stock</th>
                        <th>Unit Cost</th>
                        <th>Stock Value</th>
                        <th>Low Alert</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $inventory_items ) ) : ?>
                        <?php foreach ( $inventory_items as $inventory_item ) : ?>
                            <?php
                            $remaining_stock  = isset( $inventory_balance_map[ $inventory_item->id ][ $location_key ] ) ? floatval( $inventory_balance_map[ $inventory_item->id ][ $location_key ] ) : 0;
                            $production_stock = isset( $inventory_balance_map[ $inventory_item->id ][ Banoks_POS_Repository::STOCK_LOCATION_PRODUCTION ] ) ? floatval( $inventory_balance_map[ $inventory_item->id ][ Banoks_POS_Repository::STOCK_LOCATION_PRODUCTION ] ) : 0;
                            $is_out           = $remaining_stock <= 0;
                            $is_low           = $remaining_stock > 0 && floatval( $inventory_item->low_stock_threshold ) > 0 && $remaining_stock <= floatval( $inventory_item->low_stock_threshold );
                            ?>
                            <tr class="banoks-stock-item-row" data-item-id="<?php echo esc_attr( $inventory_item->id ); ?>" data-item-name="<?php echo esc_attr( $inventory_item->item_name ); ?>" data-location-key="<?php echo esc_attr( $location_key ); ?>" tabindex="0">
                                <td><strong><?php echo esc_html( $inventory_item->item_name ); ?></strong></td>
                                <td><span class="category-pill"><?php echo esc_html( $inventory_item->category ); ?></span></td>
                                <td>
                                    <span class="category-pill <?php echo $is_out ? 'banoks-stock-out' : ( $is_low ? 'banoks-stock-low' : '' ); ?>">
                                        <?php echo esc_html( $format_stock( $remaining_stock ) . ' ' . $inventory_item->unit ); ?>
                                    </span>
                                </td>
                                <td><?php echo wp_kses_post( $format_money( $inventory_item->unit_cost ) ); ?></td>
                                <td><?php echo wp_kses_post( $format_money( $remaining_stock * floatval( $inventory_item->unit_cost ) ) ); ?></td>
                                <td><?php echo esc_html( $format_stock( $inventory_item->low_stock_threshold ) . ' ' . $inventory_item->unit ); ?></td>
                                <td><?php echo intval( $inventory_item->is_active ) ? 'Active' : 'Inactive'; ?></td>
                                <td>
                                    <div class="action-group">
                                        <button type="button" class="btn-edit banoks-open-stock-movements" title="View Stock Movements" aria-label="View Stock Movements" data-item-id="<?php echo esc_attr( $inventory_item->id ); ?>" data-item-name="<?php echo esc_attr( $inventory_item->item_name ); ?>" data-location-key="<?php echo esc_attr( $location_key ); ?>">
                                            <span class="dashicons dashicons-list-view"></span>
                                        </button>
                                        <?php if ( $show_actions ) : ?>
                                            <button type="button" class="btn-edit banoks-open-stock-edit" title="Edit Inventory Item" aria-label="Edit Inventory Item"
                                                data-id="<?php echo esc_attr( $inventory_item->id ); ?>"
                                                data-name="<?php echo esc_attr( $inventory_item->item_name ); ?>"
                                                data-category="<?php echo esc_attr( $inventory_item->category ); ?>"
                                                data-unit="<?php echo esc_attr( $inventory_item->unit ); ?>"
                                                data-unit-cost="<?php echo esc_attr( number_format( floatval( $inventory_item->unit_cost ), 2, '.', '' ) ); ?>"
                                                data-low-stock="<?php echo esc_attr( $format_stock( $inventory_item->low_stock_threshold ) ); ?>"
                                                data-active="<?php echo esc_attr( intval( $inventory_item->is_active ) ); ?>">
                                                <span class="dashicons dashicons-edit"></span>
                                            </button>
                                            <?php if ( intval( $inventory_item->is_active ) ) : ?>
                                                <a class="btn-delete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=banoks-pos-stock-management&action=deactivate&id=' . absint( $inventory_item->id ) ), 'banoks_deactivate_inventory_' . absint( $inventory_item->id ) ) ); ?>" title="Deactivate Inventory Item" aria-label="Deactivate Inventory Item" onclick="return confirm('Deactivate this inventory item?');">
                                                    <span class="dashicons dashicons-hidden"></span>
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="8"><?php echo esc_html( $empty_message ); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    };
    ?>

    <section class="banoks-stock-panel banoks-stock-list-panel banoks-stock-primary-list">
        <div class="banoks-stock-section-header banoks-stock-inventory-header">
            <div>
                <h2>Inventory Items</h2>
                <p>Production inventory stock on hand.</p>
            </div>
            <button type="button" class="button button-primary banoks-open-stock-add">Add Inventory Item</button>
        </div>
        <?php $render_inventory_table( Banoks_POS_Repository::STOCK_LOCATION_PRODUCTION, 'No inventory items yet. Add your first inventory item.', true ); ?>
    </section>

    <section class="banoks-stock-panel banoks-stock-list-panel">
        <div class="banoks-stock-section-header banoks-stock-inventory-header">
            <div>
                <h2>Manukan Branch Inventory</h2>
                <p>Branch stock available for products and selling.</p>
            </div>
        </div>
        <?php $render_inventory_table( Banoks_POS_Repository::STOCK_LOCATION_MANUKAN, 'No Manukan branch inventory yet.', false ); ?>
    </section>

    <div class="banoks-admin-edit-modal" id="banoks-stock-movements-modal" aria-hidden="true">
        <div class="banoks-admin-edit-dialog banoks-admin-edit-dialog-wide" role="dialog" aria-modal="true" aria-labelledby="banoks-stock-movements-title">
            <div class="banoks-admin-edit-header">
                <div>
                    <h2 id="banoks-stock-movements-title">Stock Movements</h2>
                    <p id="banoks-stock-movements-description">Movement history for the selected item.</p>
                </div>
                <button type="button" class="banoks-admin-edit-close" aria-label="Close">&times;</button>
            </div>
            <div class="banoks-stock-movement-history-filters">
                <label for="banoks-stock-history-date-from">From
                    <input type="date" id="banoks-stock-history-date-from">
                </label>
                <label for="banoks-stock-history-date-to">To
                    <input type="date" id="banoks-stock-history-date-to">
                </label>
                <label for="banoks-stock-history-type">Type
                    <select id="banoks-stock-history-type">
                        <option value="">All types</option>
                        <?php foreach ( $movement_filter_options as $movement_type_key => $movement_type_label ) : ?>
                            <option value="<?php echo esc_attr( $movement_type_key ); ?>"><?php echo esc_html( $movement_type_label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label for="banoks-stock-history-location">Location
                    <select id="banoks-stock-history-location">
                        <option value="">All locations</option>
                        <?php foreach ( $stock_locations as $history_location_key => $history_location_label ) : ?>
                            <option value="<?php echo esc_attr( $history_location_key ); ?>"><?php echo esc_html( $history_location_label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="button" class="button" id="banoks-stock-history-clear">Clear</button>
            </div>
            <div class="banoks-stock-table-scroll banoks-stock-movement-history-scroll">
                <table class="wp-list-table widefat fixed striped banoks-stock-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Location</th>
                            <th>Type</th>
                            <th>Change</th>
                            <th>Cost</th>
                            <th>Reference</th>
                            <th>Cash Balance</th>
                            <th>Paid From</th>
                            <th>New Stock</th>
                            <th>Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $item_movements ) ) : ?>
                            <?php foreach ( $item_movements as $movement ) : ?>
                                <tr class="banoks-stock-movement-history-row" data-item-id="<?php echo esc_attr( $movement->inventory_item_id ); ?>" data-location-key="<?php echo esc_attr( $movement->location_key ); ?>" data-movement-type="<?php echo esc_attr( $movement->movement_type ); ?>" data-date="<?php echo esc_attr( date( 'Y-m-d', strtotime( $movement->created_at ) ) ); ?>" style="display:none;">
                                    <td><?php echo esc_html( mysql2date( 'M j, Y g:i A', $movement->created_at ) ); ?></td>
                                    <td><?php echo esc_html( isset( $stock_locations[ $movement->location_key ] ) ? $stock_locations[ $movement->location_key ] : $movement->location_key ); ?></td>
                                    <td><?php echo esc_html( isset( $movement_options[ $movement->movement_type ] ) ? $movement_options[ $movement->movement_type ] : ucwords( str_replace( '_', ' ', $movement->movement_type ) ) ); ?></td>
                                    <td><?php echo esc_html( $format_stock( $movement->change_amount ) . ' ' . $movement->unit ); ?></td>
                                    <td><?php echo wp_kses_post( $format_money( $movement->total_cost ) ); ?></td>
                                    <td><?php echo ! empty( $movement->source_id ) ? esc_html( $movement->source_id ) : '&mdash;'; ?></td>
                                    <td><?php echo ! empty( $movement->affects_cash_balance ) ? '<span class="category-pill banoks-recipe-set">Deducted</span>' : '<span class="category-pill banoks-recipe-missing">No</span>'; ?></td>
                                    <td><?php echo ! empty( $movement->affects_cash_balance ) ? esc_html( isset( $cash_source_options[ $movement->cash_source ] ) ? $cash_source_options[ $movement->cash_source ] : $cash_source_options['store_cash'] ) : '&mdash;'; ?></td>
                                    <td><?php echo esc_html( $format_stock( $movement->new_stock ) . ' ' . $movement->unit ); ?></td>
                                    <td><?php echo esc_html( $movement->note ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <tr class="banoks-stock-movement-history-empty">
                            <td colspan="10">No movements recorded for this item yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="banoks-admin-edit-modal" id="banoks-stock-add-modal" aria-hidden="true">
        <div class="banoks-admin-edit-dialog" role="dialog" aria-modal="true" aria-labelledby="banoks-stock-add-title">
            <div class="banoks-admin-edit-header">
                <div>
                    <h2 id="banoks-stock-add-title">Add Inventory Item</h2>
                    <p>Create the item details first, then use Stock In to add purchased stock.</p>
                </div>
                <button type="button" class="banoks-admin-edit-close" aria-label="Close">&times;</button>
            </div>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=banoks-pos-stock-management' ) ); ?>">
                <?php wp_nonce_field( 'banoks_inventory_item_action' ); ?>

                <label for="item_name">Item Name</label>
                <input type="text" name="item_name" id="item_name" class="regular-text" required value="" placeholder="Chicken leg quarter">

                <label for="inventory_category">Category</label>
                <div class="banoks-category-control">
                    <select name="category" id="inventory_category" class="regular-text">
                        <?php
                        $selected_category = 'Ingredients';
                        foreach ( $inventory_categories as $category ) :
                            ?>
                            <option value="<?php echo esc_attr( $category ); ?>" <?php selected( $selected_category, $category ); ?>><?php echo esc_html( $category ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="button" id="banoks-add-inventory-category">New Category</button>
                </div>

                <div class="banoks-stock-form-grid">
                    <label for="unit">Unit
                        <select name="unit" id="unit">
                            <?php foreach ( $unit_options as $unit_key => $unit_label ) : ?>
                                <option value="<?php echo esc_attr( $unit_key ); ?>" <?php selected( 'pcs', $unit_key ); ?>><?php echo esc_html( $unit_label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label for="unit_cost">Unit Cost
                        <input type="number" name="unit_cost" id="unit_cost" min="0" step="0.01" value="0.00">
                    </label>
                    <label for="low_stock_threshold">Low Stock Alert
                        <input type="number" name="low_stock_threshold" id="low_stock_threshold" min="0" step="0.001" value="0">
                    </label>
                </div>

                <label class="banoks-toggle-control banoks-toggle-compact">
                    <input type="checkbox" name="is_active" value="1" checked>
                    <span class="banoks-toggle-switch"></span>
                    <span>
                        <strong>Active inventory item</strong>
                        <small>Inactive items stay in history but are not used in recipes.</small>
                    </span>
                </label>

                <p class="submit">
                    <button type="submit" name="banoks_save_inventory_item" class="button button-primary">Add Item</button>
                    <button type="button" class="button banoks-admin-edit-cancel">Cancel</button>
                </p>
            </form>
        </div>
    </div>

    <div class="banoks-admin-edit-modal" id="banoks-stock-movement-modal" aria-hidden="true">
        <div class="banoks-admin-edit-dialog" role="dialog" aria-modal="true" aria-labelledby="banoks-stock-movement-title">
            <div class="banoks-admin-edit-header">
                <div>
                    <h2 id="banoks-stock-movement-title">Stock Movement</h2>
                    <p id="banoks-stock-movement-description">Record an inventory movement.</p>
                </div>
                <button type="button" class="banoks-admin-edit-close" aria-label="Close">&times;</button>
            </div>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=banoks-pos-stock-management' ) ); ?>">
                <?php wp_nonce_field( 'banoks_inventory_adjust_action' ); ?>
                <input type="hidden" name="movement_type" id="movement_type" value="stock_in">
                <input type="hidden" name="stock_movement_action" id="banoks_stock_movement_action" value="stock_in">

                <input type="hidden" name="movement_location_key" id="movement_location_key" value="<?php echo esc_attr( Banoks_POS_Repository::STOCK_LOCATION_PRODUCTION ); ?>">

                <div class="banoks-stock-movement-step" data-step="branch">
                    <label for="movement_location_key_select">Choose Branch</label>
                    <select id="movement_location_key_select">
                        <option value="">Select branch</option>
                        <?php foreach ( $stock_locations as $location_key => $location_label ) : ?>
                            <?php if ( Banoks_POS_Repository::STOCK_LOCATION_PRODUCTION === $location_key ) : ?>
                                <?php continue; ?>
                            <?php endif; ?>
                            <option value="<?php echo esc_attr( $location_key ); ?>"><?php echo esc_html( $location_label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="modal-actions">
                        <button type="button" class="button banoks-admin-edit-cancel">Cancel</button>
                        <button type="button" class="button button-primary banoks-stock-movement-next" data-next-step="item">Proceed</button>
                    </div>
                </div>

                <div class="banoks-stock-movement-step" data-step="item">
                    <label for="adjust_inventory_item_id">Choose Inventory Item</label>
                    <select name="inventory_item_id" id="adjust_inventory_item_id" class="regular-text" required>
                        <option value="">Select item</option>
                        <?php foreach ( $inventory_items as $inventory_item ) : ?>
                            <?php if ( intval( $inventory_item->is_active ) ) : ?>
                                <?php
                                $option_production_stock = isset( $inventory_balance_map[ $inventory_item->id ][ Banoks_POS_Repository::STOCK_LOCATION_PRODUCTION ] ) ? floatval( $inventory_balance_map[ $inventory_item->id ][ Banoks_POS_Repository::STOCK_LOCATION_PRODUCTION ] ) : 0;
                                $option_branch_stock     = isset( $inventory_balance_map[ $inventory_item->id ][ Banoks_POS_Repository::STOCK_LOCATION_MANUKAN ] ) ? floatval( $inventory_balance_map[ $inventory_item->id ][ Banoks_POS_Repository::STOCK_LOCATION_MANUKAN ] ) : 0;
                                ?>
                                <option
                                    value="<?php echo esc_attr( $inventory_item->id ); ?>"
                                    data-unit="<?php echo esc_attr( $inventory_item->unit ); ?>"
                                    data-production-stock="<?php echo esc_attr( $format_stock( $option_production_stock ) ); ?>"
                                    data-branch-stock="<?php echo esc_attr( $format_stock( $option_branch_stock ) ); ?>"
                                >
                                    <?php echo esc_html( $inventory_item->item_name . ' (' . $inventory_item->unit . ')' ); ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                    <div class="modal-actions">
                        <button type="button" class="button banoks-stock-movement-back" data-back-step="branch">Back</button>
                        <button type="button" class="button button-primary banoks-stock-movement-next" data-next-step="details">Proceed</button>
                    </div>
                </div>

                <div class="banoks-stock-movement-step" data-step="details">
                    <div class="banoks-stock-transfer-flow" aria-hidden="true">
                        <div class="banoks-stock-transfer-node">
                            <span>From</span>
                            <strong id="banoks-stock-transfer-source">Supplier / New Stock</strong>
                            <small id="banoks-stock-transfer-source-stock">Remaining: -</small>
                        </div>
                        <div class="banoks-stock-transfer-arrow">
                            <span class="dashicons dashicons-arrow-left-alt2"></span>
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </div>
                        <div class="banoks-stock-transfer-node">
                            <span>To</span>
                            <strong id="banoks-stock-transfer-destination">Production Inventory</strong>
                            <small id="banoks-stock-transfer-destination-stock">Remaining: -</small>
                        </div>
                    </div>

                    <div class="banoks-stock-form-grid">
                        <label for="quantity">Quantity
                            <input type="number" name="quantity" id="quantity" min="0.001" step="0.001" required>
                        </label>
                        <label for="movement_unit_cost" class="banoks-stock-unit-cost-field">Unit Cost
                            <input type="number" name="movement_unit_cost" id="movement_unit_cost" min="0" step="0.01" placeholder="Use saved cost">
                        </label>
                    </div>

                    <label for="inventory_note">Note</label>
                    <textarea name="note" id="inventory_note" rows="3" class="large-text" placeholder="Supplier delivery, branch stock update..."></textarea>

                    <label class="banoks-toggle-control banoks-toggle-compact banoks-cash-balance-toggle">
                        <input type="checkbox" name="affects_cash_balance" id="affects_cash_balance" value="1" checked>
                        <span class="banoks-toggle-switch"></span>
                        <span>
                            <strong>Deduct this stock purchase from today's cash balance</strong>
                            <small>Use this when store cash or today's sales money is used to buy stock. Do not also add the same purchase in Expenses.</small>
                        </span>
                    </label>

                    <label for="movement_cash_source" class="banoks-stock-cash-source-field">Paid From
                        <select name="movement_cash_source" id="movement_cash_source">
                            <?php foreach ( $cash_source_options as $source_key => $source_label ) : ?>
                                <option value="<?php echo esc_attr( $source_key ); ?>" <?php selected( 'store_cash', $source_key ); ?>><?php echo esc_html( $source_label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <p class="submit">
                        <button type="button" class="button banoks-stock-movement-back" data-back-step="item">Back</button>
                        <button type="submit" name="banoks_adjust_inventory_stock" class="button button-primary">Save Movement</button>
                    </p>
                </div>
            </form>
        </div>
    </div>

    <div class="banoks-inventory-category-modal" id="banoks-inventory-category-modal" aria-hidden="true">
        <div class="banoks-inventory-category-dialog" role="dialog" aria-modal="true" aria-labelledby="banoks-inventory-category-title">
            <h2 id="banoks-inventory-category-title">Add Category</h2>
            <label for="banoks-new-inventory-category">Category Name</label>
            <input type="text" id="banoks-new-inventory-category" class="regular-text" placeholder="e.g. Marinade">
            <div class="modal-actions">
                <button type="button" class="button" id="banoks-cancel-inventory-category">Cancel</button>
                <button type="button" class="button button-primary" id="banoks-save-inventory-category">Add Category</button>
            </div>
        </div>
    </div>

    <div class="banoks-admin-edit-modal" id="banoks-stock-edit-modal" aria-hidden="true">
        <div class="banoks-admin-edit-dialog" role="dialog" aria-modal="true" aria-labelledby="banoks-stock-edit-title">
            <div class="banoks-admin-edit-header">
                <h2 id="banoks-stock-edit-title">Edit Inventory Item</h2>
                <button type="button" class="banoks-admin-edit-close" aria-label="Close">&times;</button>
            </div>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=banoks-pos-stock-management' ) ); ?>">
                <?php wp_nonce_field( 'banoks_inventory_item_action' ); ?>
                <input type="hidden" name="inventory_item_id" id="stock-modal-item-id" value="">

                <label for="stock-modal-item-name">Item Name</label>
                <input type="text" name="item_name" id="stock-modal-item-name" class="regular-text" required>

                <label for="stock-modal-category">Category</label>
                <div class="banoks-category-control">
                    <select name="category" id="stock-modal-category" class="regular-text">
                        <?php foreach ( $inventory_categories as $category ) : ?>
                            <option value="<?php echo esc_attr( $category ); ?>"><?php echo esc_html( $category ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="banoks-stock-form-grid">
                    <label for="stock-modal-unit">Unit
                        <select name="unit" id="stock-modal-unit">
                            <?php foreach ( $unit_options as $unit_key => $unit_label ) : ?>
                                <option value="<?php echo esc_attr( $unit_key ); ?>"><?php echo esc_html( $unit_label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label for="stock-modal-unit-cost">Unit Cost
                        <input type="number" name="unit_cost" id="stock-modal-unit-cost" min="0" step="0.01">
                    </label>
                    <label for="stock-modal-low-stock">Low Stock Alert
                        <input type="number" name="low_stock_threshold" id="stock-modal-low-stock" min="0" step="0.001">
                    </label>
                </div>

                <label class="banoks-toggle-control banoks-toggle-compact">
                    <input type="checkbox" name="is_active" id="stock-modal-active" value="1">
                    <span class="banoks-toggle-switch"></span>
                    <span>
                        <strong>Active inventory item</strong>
                        <small>Inactive items stay in history but are not used in recipes.</small>
                    </span>
                </label>

                <p class="submit">
                    <button type="submit" name="banoks_save_inventory_item" class="button button-primary">Update Item</button>
                    <button type="button" class="button banoks-admin-edit-cancel">Cancel</button>
                </p>
            </form>
        </div>
    </div>
</div>
