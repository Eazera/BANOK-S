<?php
/**
 * Provide a admin area view for the product list
 *
 * @link       https://banoks.com
 * @since      1.0.0
 * @package    Banoks_POS
 * @subpackage Banoks_POS/admin/partials
 * @author     Christian Fulache
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap banoks-pos-admin banoks-pos-page banoks-products-page">
    <div class="products-header">
        <div class="header-info">
            <h1>Product Management</h1>
            <p>
                <?php echo esc_html( $selected_branch_name ); ?> stock availability. Drag products using the handle to change their order in POS and online menu.
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=banoks-pos-owner-dashboard' ) ); ?>">Choose another branch</a>
            </p>
        </div>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=banoks-pos-products&action=add&branch_key=' . $selected_branch_key ) ); ?>" class="button button-primary action-add-new">
            <span class="dashicons dashicons-plus-alt2"></span> Add New Product
        </a>
    </div>

    <?php if ( ! empty( $message ) ) : ?>
        <div id="message" class="updated notice is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
    <?php endif; ?>

    <div class="products-table-container">
        <table class="banoks-custom-table">
            <thead>
                <tr>
                    <th class="col-sort">Sort</th>
                    <th class="col-id">ID</th>
                    <th class="col-name">Product Name</th>
                    <th class="col-category">Category</th>
                    <th class="col-price">Price</th>
                    <th>Stock</th>
                    <th>Status</th>
                    <th class="col-actions">Actions</th>
                </tr>
            </thead>
            <tbody id="banoks-sortable-products">
                <?php if ( ! empty( $products ) ) : ?>
                    <?php foreach ( $products as $product ) : ?>
                        <?php
                        $recipe_status = isset( $recipe_statuses[ $product->product_id ] ) ? $recipe_statuses[ $product->product_id ] : array(
                            'has_recipe'      => false,
                            'can_prepare'     => true,
                            'available_stock' => null,
                            'warnings'        => array(),
                        );
                        ?>
                        <tr data-product-id="<?php echo esc_attr( $product->product_id ); ?>">
                            <td class="col-sort"><span class="banoks-product-drag-handle dashicons dashicons-menu" title="Drag to reorder" aria-label="Drag to reorder"></span></td>
                            <td class="col-id">#<?php echo esc_html( $product->product_id ); ?></td>
                            <td class="col-name">
                                <span class="product-title"><?php echo esc_html( $product->product_name ); ?></span>
                            </td>
                            <td class="col-category">
                                <span class="category-pill"><?php echo esc_html( $product->category ?: 'General' ); ?></span>
                            </td>
                            <td class="col-price">&#8369;<?php echo esc_html( number_format( $product->current_price, 2 ) ); ?></td>
                            <td>
                                <?php if ( ! empty( $recipe_status['has_recipe'] ) && null !== $recipe_status['available_stock'] ) : ?>
                                    <span class="category-pill <?php echo ! empty( $recipe_status['can_prepare'] ) ? 'banoks-recipe-set' : 'banoks-stock-out'; ?>" title="<?php echo esc_attr( implode( ' ', $recipe_status['warnings'] ) ); ?>">
                                        <?php echo esc_html( number_format_i18n( absint( $recipe_status['available_stock'] ) ) ); ?> available
                                    </span>
                                    <?php if ( empty( $recipe_status['can_prepare'] ) ) : ?>
                                        <span class="category-pill banoks-stock-out" title="<?php echo esc_attr( implode( ' ', $recipe_status['warnings'] ) ); ?>">Cannot Prepare</span>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <span class="category-pill">Not tracked</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $available = ! isset( $product->is_available ) || intval( $product->is_available );
                                $active    = ! isset( $product->is_active ) || intval( $product->is_active );
                                ?>
                                <span class="category-pill"><?php echo esc_html( $active ? 'Active' : 'Inactive' ); ?></span>
                                <span class="category-pill"><?php echo esc_html( $available ? 'Available' : 'Out of Stock' ); ?></span>
                            </td>
                            <td class="col-actions">
                                <div class="action-group">
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=banoks-pos-products&action=edit&id=' . $product->product_id . '&branch_key=' . $selected_branch_key ) ); ?>" class="btn-edit" title="Edit Product">
                                        <span class="dashicons dashicons-edit"></span>
                                    </a>
                                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=banoks-pos-products&action=delete&id=' . $product->product_id . '&branch_key=' . $selected_branch_key ), 'delete_product_' . $product->product_id ) ); ?>" class="btn-delete" onclick="return confirm('Deactivate this product? It will stay in order history.');" title="Deactivate Product">
                                        <span class="dashicons dashicons-trash"></span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="8" class="empty-state">No products found. Start by adding your first item!</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
